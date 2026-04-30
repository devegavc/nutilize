<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\ReservationApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ApprovalController extends Controller
{
    private ?array $officeIdsByShortCodeCache = null;
    private ?int $physicalFacilitiesOfficeIdCache = null;
    private ?array $officeIdByDepartmentNameCache = null;
    private array $ownerOfficeIdCache = [];

    public function index()
    {
        $user = Auth::user();
        
        if (!$user->isOfficeApprover()) {
            return redirect('/dashboard/home')->with('error', 'Unauthorized access.');
        }

        $openReservationIds = Reservation::query()
            ->whereNotIn(DB::raw("LOWER(COALESCE(overall_status, ''))"), ['approved', 'rejected'])
            ->pluck('reservation_id')
            ->all();

        // Automatic workflow sync on every page load can time out on large datasets.

        $actionableReservationIds = [];

        if ($user->isPhysicalFacilitiesAdmin()) {
            $actionableReservationIds = $openReservationIds;
        } else {
            $actionableOfficeIds = $this->getActionableOfficeIdsForReservations($openReservationIds);

            $this->ensureActionableApprovalRows($actionableOfficeIds, (int) $user->office_id);

            foreach ($actionableOfficeIds as $reservationId => $officeId) {
                if ($officeId === (int) $user->office_id) {
                    $actionableReservationIds[] = (int) $reservationId;
                }
            }
        }

        $pendingQuery = ReservationApproval::where('office_id', $user->office_id)
            ->whereNull('approved_at')
            ->whereIn('reservation_id', $actionableReservationIds)
            ->with(['reservation.user', 'reservation.approvals', 'reservation.details']);

        $pendingApprovals = $pendingQuery
            ->orderByDesc('created_at')
            ->paginate(10);

        $returnApprovals = ReservationApproval::where('office_id', $user->office_id)
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->with(['reservation.user'])
            ->orderByDesc('approved_at')
            ->paginate(10);

        $approvedApprovals = ReservationApproval::where('office_id', $user->office_id)
            ->whereNotNull('approved_at')
            ->with(['reservation.user'])
            ->orderByDesc('approved_at')
            ->paginate(10);

        return view('dashboard-approvals', [
            'pendingApprovals' => $pendingApprovals,
            'returnApprovals' => $returnApprovals,
            'approvedApprovals' => $approvedApprovals,
            'authUser' => $user,
            'isPfAdmin' => $user->isPhysicalFacilitiesAdmin(),
        ]);
    }

    public function approve($approvalId)
    {
        $user = Auth::user();
        
        if (!$user->isOfficeApprover()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $approval = ReservationApproval::findOrFail($approvalId);

        if ($approval->office_id !== $user->office_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$user->isPhysicalFacilitiesAdmin() && $this->getCurrentActionableOfficeId((int) $approval->reservation_id) !== (int) $user->office_id) {
            return response()->json(['error' => 'This request is waiting for a previous office approval.'], 422);
        }

        $approval->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by_user_id' => (int) $user->user_id,
        ]);

        $this->recordApprovalHistory($approval);

        $this->syncReservationApprovals((int) $approval->reservation_id);

        // Update the overall reservation status if all office approvals are done
        $this->updateReservationStatus($approval->reservation_id);

        return response()->json([
            'success' => true,
            'message' => 'Request approved successfully.',
            'approval' => $approval,
        ]);
    }

    public function reject($approvalId)
    {
        $user = Auth::user();
        
        if (!$user->isOfficeApprover()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $approval = ReservationApproval::findOrFail($approvalId);

        if ($approval->office_id !== $user->office_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$user->isPhysicalFacilitiesAdmin() && $this->getCurrentActionableOfficeId((int) $approval->reservation_id) !== (int) $user->office_id) {
            return response()->json(['error' => 'This request is waiting for a previous office approval.'], 422);
        }

        $approval->update([
            'status' => 'rejected',
            'approved_at' => now(),
            'approved_by_user_id' => (int) $user->user_id,
        ]);

        $this->recordApprovalHistory($approval);

        $this->syncReservationApprovals((int) $approval->reservation_id);

        // Update the overall reservation status
        $this->updateReservationStatus($approval->reservation_id);

        return response()->json([
            'success' => true,
            'message' => 'Request rejected.',
            'approval' => $approval,
        ]);
    }

    public function finalApproveReservation($reservationId)
    {
        return $this->finalizePhysicalFacilitiesDecision($reservationId, 'approved');
    }

    public function finalRejectReservation($reservationId)
    {
        return $this->finalizePhysicalFacilitiesDecision($reservationId, 'rejected');
    }

    public function finalReturnReservation($reservationId)
    {
        return $this->finalizePhysicalFacilitiesDecision($reservationId, 'returned');
    }

    public function finalDamagedReservation($reservationId)
    {
        return $this->finalizePhysicalFacilitiesDecision($reservationId, 'damaged');
    }

    private function finalizePhysicalFacilitiesDecision($reservationId, string $status)
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->isPhysicalFacilitiesAdmin()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $reservation = Reservation::findOrFail($reservationId);
            $physicalFacilitiesOfficeId = $this->getPhysicalFacilitiesOfficeId();

            DB::transaction(function () use ($reservation, $physicalFacilitiesOfficeId, $status, $user) {
                $reservation->update(['overall_status' => $status]);

                if ($status === 'damaged') {
                    $this->applyReservationDamageToMaintenance($reservation);
                    $this->createDamageReportsForReservation($reservation);
                }

                if (is_null($physicalFacilitiesOfficeId)) {
                    return;
                }

                DB::table('reservation_approvals')->updateOrInsert(
                    [
                        'reservation_id' => $reservation->reservation_id,
                        'office_id' => $physicalFacilitiesOfficeId,
                    ],
                    [
                        'status' => $status,
                        'approved_at' => now(),
                        'approved_by_user_id' => (int) $user->user_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $historyApproval = DB::table('reservation_approvals')
                    ->where('reservation_id', $reservation->reservation_id)
                    ->where('office_id', $physicalFacilitiesOfficeId)
                    ->first();

                if ($historyApproval) {
                    $this->upsertApprovalHistory((int) $historyApproval->approval_id, (int) $reservation->reservation_id, (int) $physicalFacilitiesOfficeId, $status, (int) $user->user_id, now());
                }

                if ($status === 'damaged') {
                    $this->applyReservationDamageToMaintenance($reservation);
                }
            });

            return response()->json([
                'success' => true,
                'message' => match ($status) {
                    'approved' => 'Request approved successfully.',
                    'rejected' => 'Request rejected.',
                    'returned' => 'Request marked as returned.',
                    'damaged' => 'Request marked as damaged.',
                    default => 'Request updated successfully.',
                },
                'reservation_id' => $reservation->reservation_id,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'error' => $throwable->getMessage(),
            ], 500);
        }
    }

    private function updateReservationStatus($reservationId)
    {
        $reservation = Reservation::findOrFail($reservationId);
        $approvals = ReservationApproval::where('reservation_id', $reservationId)->get();
        $physicalFacilitiesOfficeId = $this->getPhysicalFacilitiesOfficeId();

        $anyRejected = $approvals->some(fn($a) => $a->status === 'rejected');

        if (is_null($physicalFacilitiesOfficeId)) {
            $allApproved = $approvals->every(fn($a) => $a->status === 'approved' && !is_null($a->approved_at));

            if ($anyRejected) {
                $reservation->update(['overall_status' => 'rejected']);
            } elseif ($allApproved) {
                $reservation->update(['overall_status' => 'approved']);
            } else {
                $reservation->update(['overall_status' => 'pending_office_approvals']);
            }

            return;
        }

        $pfApproval = $approvals->firstWhere('office_id', $physicalFacilitiesOfficeId);
        $allNonPfApproved = $approvals
            ->where('office_id', '!=', $physicalFacilitiesOfficeId)
            ->every(fn($a) => $a->status === 'approved' && !is_null($a->approved_at));

        if ($anyRejected) {
            $reservation->update(['overall_status' => 'rejected']);
        } elseif ($pfApproval && $pfApproval->status === 'approved' && $pfApproval->approved_at) {
            $reservation->update(['overall_status' => 'approved']);
        } elseif ($allNonPfApproved) {
            $reservation->update(['overall_status' => 'awaiting_physical_facilities']);
        } else {
            $reservation->update(['overall_status' => 'pending_office_approvals']);
        }
    }

    private function applyReservationDamageToMaintenance(Reservation $reservation): void
    {
        if (!Schema::hasTable('reservation_details')) {
            return;
        }

        $itemRows = DB::table('reservation_details as details')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->where('details.reservation_id', $reservation->reservation_id)
            ->select(['items.item_id', 'details.quantity'])
            ->get()
            ->map(function ($row) {
                return [
                    'item_id' => (int) ($row->item_id ?? 0),
                    'quantity' => max(1, (int) ($row->quantity ?? 1)),
                ];
            })
            ->filter(fn ($row) => $row['item_id'] > 0)
            ->values()
            ->all();

        if (!empty($itemRows) && Schema::hasTable('item_units')) {
            foreach ($itemRows as $itemRow) {
                $unitIds = DB::table('item_units')
                    ->where('item_id', $itemRow['item_id'])
                    ->whereIn('status', ['in_use', 'available'])
                    ->orderByRaw("CASE WHEN status = 'in_use' THEN 1 WHEN status = 'available' THEN 2 ELSE 3 END")
                    ->limit($itemRow['quantity'])
                    ->pluck('unit_id')
                    ->all();

                if (!empty($unitIds)) {
                    DB::table('item_units')
                        ->whereIn('unit_id', $unitIds)
                        ->update([
                            'status' => 'damaged',
                            'condition_notes' => 'Marked damaged by Physical Facilities',
                            'last_maintenance_at' => now(),
                            'updated_at' => now(),
                        ]);
                }

                $itemStats = DB::table('item_units')
                    ->where('item_id', $itemRow['item_id'])
                    ->selectRaw("COUNT(*) FILTER (WHERE status <> 'retired') as total_active")
                    ->selectRaw("COUNT(*) FILTER (WHERE status = 'in_use') as in_use_count")
                    ->selectRaw("COUNT(*) FILTER (WHERE status IN ('maintenance', 'damaged')) as issue_count")
                    ->first();

                DB::table('items')
                    ->where('item_id', $itemRow['item_id'])
                    ->update([
                        'quantity_total' => max(1, (int) ($itemStats->total_active ?? 1)),
                        'quantity_in_use' => max(0, min(max(1, (int) ($itemStats->total_active ?? 1)), (int) ($itemStats->in_use_count ?? 0))),
                        'maintenance_status' => DB::raw(((int) ($itemStats->issue_count ?? 0) > 0) ? 'true' : 'false'),
                        'availability_status' => DB::raw((((int) ($itemStats->in_use_count ?? 0)) <= 0 && ((int) ($itemStats->issue_count ?? 0)) <= 0) ? 'true' : 'false'),
                        'updated_at' => now(),
                    ]);
            }
        } elseif (!empty($itemRows) && Schema::hasTable('items')) {
            $itemIds = array_column($itemRows, 'item_id');
            DB::table('items')
                ->whereIn('item_id', $itemIds)
                ->update([
                    'maintenance_status' => true,
                    'availability_status' => false,
                    'updated_at' => now(),
                ]);
        }

        $roomIds = [];
        if (Schema::hasTable('reservation_rooms') && Schema::hasTable('rooms')) {
            $roomIds = DB::table('reservation_details as details')
                ->join('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
                ->join('rooms as rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
                ->where('details.reservation_id', $reservation->reservation_id)
                ->pluck('rooms.room_id')
                ->filter(fn ($roomId) => !is_null($roomId))
                ->map(fn ($roomId) => (int) $roomId)
                ->all();

            if (!empty($roomIds)) {
                DB::table('rooms')
                    ->whereIn('room_id', $roomIds)
                    ->update([
                        'maintenance_status' => true,
                        'availability_status' => false,
                        'updated_at' => now(),
                    ]);
            }
        }

        if (Schema::hasTable('maintenance')) {
            foreach ($itemRows as $itemRow) {
                DB::table('maintenance')->updateOrInsert(
                    ['item_id' => $itemRow['item_id'], 'room_id' => null],
                    [
                        'issue_description' => 'Request marked damaged by Physical Facilities',
                        'action_taken' => null,
                        'cost' => 0,
                        'date_resolved' => null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            foreach ($roomIds as $roomId) {
                DB::table('maintenance')->updateOrInsert(
                    ['item_id' => null, 'room_id' => $roomId],
                    [
                        'issue_description' => 'Request marked damaged by Physical Facilities',
                        'action_taken' => null,
                        'cost' => 0,
                        'date_resolved' => null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    private function createDamageReportsForReservation(Reservation $reservation): void
    {
        if (!Schema::hasTable('reports')) {
            return;
        }

        $hasGeneratedAtColumn = Schema::hasColumn('reports', 'generated_at');
        $now = now();
        $reportBase = [
            'user_id' => (int) $reservation->user_id,
            'report_info' => sprintf('Reservation #%s marked damaged by Physical Facilities.', $reservation->reservation_id),
            'updated_at' => $now,
            'created_at' => $now,
        ];
        if ($hasGeneratedAtColumn) {
            $reportBase['generated_at'] = $now;
        }

        $itemIds = DB::table('reservation_details as details')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->where('details.reservation_id', $reservation->reservation_id)
            ->whereNotNull('items.item_id')
            ->distinct()
            ->pluck('items.item_id')
            ->filter(fn ($itemId) => !is_null($itemId))
            ->map(fn ($itemId) => (int) $itemId)
            ->all();

        foreach ($itemIds as $itemId) {
            DB::table('reports')->insert(array_merge($reportBase, ['item_id' => $itemId, 'room_id' => null]));
        }

        $roomIds = DB::table('reservation_details as details')
            ->join('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
            ->join('rooms as rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
            ->where('details.reservation_id', $reservation->reservation_id)
            ->whereNotNull('rooms.room_id')
            ->distinct()
            ->pluck('rooms.room_id')
            ->filter(fn ($roomId) => !is_null($roomId))
            ->map(fn ($roomId) => (int) $roomId)
            ->all();

        foreach ($roomIds as $roomId) {
            DB::table('reports')->insert(array_merge($reportBase, ['item_id' => null, 'room_id' => $roomId]));
        }

        if (empty($itemIds) && empty($roomIds)) {
            DB::table('reports')->insert(array_merge($reportBase, ['item_id' => null, 'room_id' => null]));
        }
    }

    private function syncReservationApprovalWorkflow(?array $reservationIds = null): void
    {
        if (is_null($reservationIds)) {
            $reservationIds = Reservation::query()
                ->whereNotIn(DB::raw("LOWER(COALESCE(overall_status, ''))"), ['approved', 'rejected'])
                ->orderByDesc('created_at')
                ->limit(80)
                ->pluck('reservation_id')
                ->all();
        }

        foreach ($reservationIds as $reservationId) {
            $this->syncReservationApprovals((int) $reservationId);
        }
    }

    private function syncReservationApprovals(int $reservationId): void
    {
        $workflowOfficeIds = $this->resolveWorkflowOfficeIds($reservationId, true);

        if (empty($workflowOfficeIds)) {
            return;
        }

        foreach ($workflowOfficeIds as $officeId) {
            $exists = DB::table('reservation_approvals')
                ->where('reservation_id', $reservationId)
                ->where('office_id', $officeId)
                ->exists();

            if (!$exists) {
                DB::table('reservation_approvals')->insert([
                    'reservation_id' => $reservationId,
                    'office_id' => $officeId,
                    'approved_by_user_id' => null,
                    'status' => 'pending',
                    'approved_at' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }

        DB::table('reservation_approvals')
            ->where('reservation_id', $reservationId)
            ->whereIn('office_id', $workflowOfficeIds)
            ->whereNull('status')
            ->update([
                'status' => 'pending',
                'updated_at' => now(),
            ]);
    }

    private function getCurrentActionableOfficeId(int $reservationId): ?int
    {
        return $this->getActionableOfficeIdsForReservations([$reservationId])[$reservationId] ?? null;
    }

    private function getActionableOfficeIdsForReservations(array $reservationIds): array
    {
        if (empty($reservationIds)) {
            return [];
        }

        $approvalsByReservation = ReservationApproval::query()
            ->whereIn('reservation_id', $reservationIds)
            ->get(['reservation_id', 'office_id', 'status', 'approved_at'])
            ->groupBy('reservation_id');

        $actionableOfficeIds = [];

        foreach ($reservationIds as $reservationId) {
            $reservationId = (int) $reservationId;
            $actionSequence = $this->resolveWorkflowOfficeIds($reservationId, false);

            if (empty($actionSequence)) {
                continue;
            }

            $approvals = ($approvalsByReservation->get($reservationId) ?? collect())->keyBy('office_id');

            foreach ($actionSequence as $officeId) {
                $approval = $approvals->get($officeId);
                $status = strtolower((string) ($approval?->status ?? 'pending'));

                if ($status === 'rejected' && !is_null($approval?->approved_at)) {
                    continue 2;
                }

                if ($status !== 'approved' || is_null($approval?->approved_at)) {
                    $actionableOfficeIds[$reservationId] = (int) $officeId;
                    continue 2;
                }
            }
        }

        return $actionableOfficeIds;
    }

    private function ensureActionableApprovalRows(array $actionableOfficeIds, int $officeId): void
    {
        if ($officeId <= 0 || empty($actionableOfficeIds)) {
            return;
        }

        $targetReservationIds = [];

        foreach ($actionableOfficeIds as $reservationId => $actionableOfficeId) {
            if ((int) $actionableOfficeId === $officeId) {
                $targetReservationIds[] = (int) $reservationId;
            }
        }

        if (empty($targetReservationIds)) {
            return;
        }

        $existingReservationIds = DB::table('reservation_approvals')
            ->whereIn('reservation_id', $targetReservationIds)
            ->where('office_id', $officeId)
            ->pluck('reservation_id')
            ->map(fn($id) => (int) $id)
            ->all();

        $missingReservationIds = array_values(array_diff($targetReservationIds, $existingReservationIds));

        if (empty($missingReservationIds)) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($missingReservationIds as $reservationId) {
            $rows[] = [
                'reservation_id' => $reservationId,
                'office_id' => $officeId,
                'approved_by_user_id' => null,
                'status' => 'pending',
                'approved_at' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ];
        }

        DB::table('reservation_approvals')->insert($rows);
    }

    private function resolveWorkflowOfficeIds(int $reservationId, bool $includePf): array
    {
        $ids = $this->getOfficeIdsByShortCode();
        $actionSequence = $this->getActionSequenceOfficeIds();

        if (empty($actionSequence)) {
            return [];
        }

        $pfOfficeId = $ids['PF'] ?? $this->getPhysicalFacilitiesOfficeId();
        $ioOfficeId = $ids['IO'] ?? null;
        $ownerOfficeId = $this->resolveOwnerOfficeId($reservationId, $ids, $pfOfficeId);
        $pcOfficeId = $ids['PC'] ?? null;
        $genEdOfficeId = $ids['GENED'] ?? null;
        $startOfficeId = $ownerOfficeId;

        if ($this->isGymRoomRequest($reservationId) && !is_null($genEdOfficeId)) {
            $actionSequence = array_values(array_filter([
                $ioOfficeId,
                $genEdOfficeId,
                $pcOfficeId,
                $ids['SDAO'] ?? null,
                $ids['DO'] ?? null,
                $ids['SEC'] ?? null,
            ]));

            if ($this->isGymRoomRequestWithItems($reservationId) && !is_null($ioOfficeId)) {
                $startOfficeId = $ioOfficeId;
            } else {
                $startOfficeId = $genEdOfficeId;
            }
        } else {
            if (is_null($startOfficeId) || (!is_null($pfOfficeId) && $startOfficeId === $pfOfficeId)) {
                $startOfficeId = $pcOfficeId;
            } elseif (!is_null($ioOfficeId) && $startOfficeId !== $ioOfficeId) {
                $startOfficeId = $ioOfficeId;
            }
        }

        $startIndex = 0;
        if (!is_null($startOfficeId)) {
            $foundIndex = array_search($startOfficeId, $actionSequence, true);
            if ($foundIndex !== false) {
                $startIndex = $foundIndex;
            }
        }

        $workflowOfficeIds = array_slice($actionSequence, $startIndex);

        if (!is_null($pfOfficeId) && !in_array($pfOfficeId, $workflowOfficeIds, true)) {
            $workflowOfficeIds[] = $pfOfficeId;
        }

        return array_values(array_unique($workflowOfficeIds));
    }

    private function getOfficeIdsByShortCode(): array
    {
        if (!is_null($this->officeIdsByShortCodeCache)) {
            return $this->officeIdsByShortCodeCache;
        }

        $rows = DB::table('offices')
            ->select(['office_id', 'short_code'])
            ->whereNotNull('short_code')
            ->get();

        $ids = [];

        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row->short_code ?? '')));
            if ($code !== '') {
                $ids[$code] = (int) $row->office_id;
            }
        }

        $this->officeIdsByShortCodeCache = $ids;

        return $this->officeIdsByShortCodeCache;
    }

    private function getActionSequenceOfficeIds(): array
    {
        $ids = $this->getOfficeIdsByShortCode();

        return array_values(array_filter([
            $ids['IO'] ?? null,
            $ids['PC'] ?? null,
            $ids['SDAO'] ?? null,
            $ids['DO'] ?? null,
            $ids['SEC'] ?? null,
        ]));
    }

    private function isGymRoomRequest(int $reservationId): bool
    {
        if (!Schema::hasTable('reservation_details')) {
            return false;
        }

        return DB::table('reservation_details as details')
            ->join('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
            ->join('rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
            ->where('details.reservation_id', $reservationId)
            ->whereRaw('LOWER(TRIM(rooms.room_number)) = ?', ['gym'])
            ->exists();
    }

    private function isGymRoomRequestWithItems(int $reservationId): bool
    {
        if (!Schema::hasTable('reservation_details')) {
            return false;
        }

        $hasGymRoom = DB::table('reservation_details as details')
            ->join('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
            ->join('rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
            ->where('details.reservation_id', $reservationId)
            ->whereRaw('LOWER(TRIM(rooms.room_number)) = ?', ['gym'])
            ->exists();

        if (!$hasGymRoom) {
            return false;
        }

        return DB::table('reservation_details as details')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->where('details.reservation_id', $reservationId)
            ->whereNotNull('items.item_id')
            ->exists();
    }

    private function resolveOwnerOfficeId(int $reservationId, array $officeIdsByCode, ?int $pfOfficeId): ?int
    {
        if (array_key_exists($reservationId, $this->ownerOfficeIdCache)) {
            return $this->ownerOfficeIdCache[$reservationId];
        }

        $ownerRows = DB::table('reservation_details as details')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->leftJoin('item_owners as owners', 'owners.owner_id', '=', 'items.owner_id')
            ->where('details.reservation_id', $reservationId)
            ->select(['owners.owner_name', 'owners.department_affiliation'])
            ->get();

        if ($ownerRows->isEmpty()) {
            return $this->ownerOfficeIdCache[$reservationId] = null;
        }

        $ioOfficeId = $officeIdsByCode['IO'] ?? null;
        $fallbackOfficeId = $ioOfficeId ?? $officeIdsByCode['PC'] ?? null;
        $hasPfOwner = false;
        $hasNonPfOwner = false;

        foreach ($ownerRows as $row) {
            $affiliation = strtolower(trim((string) ($row->department_affiliation ?? '')));
            $ownerName = strtolower(trim((string) ($row->owner_name ?? '')));

            if ($affiliation !== '' && str_starts_with($affiliation, 'user:')) {
                return $ioOfficeId ?? $fallbackOfficeId;
            }

            if ($ownerName === '' && $affiliation === '') {
                continue;
            }

            $department = $ownerName !== '' ? $ownerName : $affiliation;
            $matchedOfficeId = $this->getOfficeIdByDepartmentName()[$department] ?? null;

            if (!is_null($matchedOfficeId)) {
                $isPfMatched = !is_null($pfOfficeId) && (int) $matchedOfficeId === (int) $pfOfficeId;
                if ($isPfMatched) {
                    $hasPfOwner = true;
                } else {
                    $hasNonPfOwner = true;
                }
                continue;
            }

            $looksLikePf = !is_null($pfOfficeId)
                && (str_contains($ownerName, 'physical facilities') || str_contains($affiliation, 'physical facilities'));

            if ($looksLikePf) {
                $hasPfOwner = true;
            } else {
                $hasNonPfOwner = true;
            }
        }

        if ($hasNonPfOwner) {
            return $this->ownerOfficeIdCache[$reservationId] = $ioOfficeId ?? $fallbackOfficeId;
        }

        if ($hasPfOwner) {
            return $this->ownerOfficeIdCache[$reservationId] = $pfOfficeId ?? $fallbackOfficeId;
        }

        return $this->ownerOfficeIdCache[$reservationId] = $fallbackOfficeId;
    }

    private function isPhysicalFacilitiesOwnedReservation(int $reservationId, ?int $pfOfficeId = null): bool
    {
        $pfOfficeId ??= $this->getPhysicalFacilitiesOfficeId();

        if (is_null($pfOfficeId)) {
            return false;
        }

        return $this->resolveOwnerOfficeId($reservationId, $this->getOfficeIdsByShortCode(), $pfOfficeId) === $pfOfficeId;
    }

    private function getPhysicalFacilitiesAdminUserId(): ?int
    {
        $pfOfficeId = $this->getPhysicalFacilitiesOfficeId();

        if (is_null($pfOfficeId)) {
            return null;
        }

        return DB::table('users')
            ->where('office_id', $pfOfficeId)
            ->whereRaw('LOWER(role) = ?', ['admin'])
            ->value('user_id');
    }

    private function isReadyForFinalPhysicalFacilitiesApproval(Reservation $reservation, int $physicalFacilitiesOfficeId): bool
    {
        $reservation->loadMissing('approvals');

        $nonPfApprovals = $reservation->approvals->where('office_id', '!=', $physicalFacilitiesOfficeId);

        if ($nonPfApprovals->isEmpty()) {
            return true;
        }

        $anyRejected = $nonPfApprovals->contains(fn($approval) => $approval->status === 'rejected');
        $allApproved = $nonPfApprovals->every(
            fn($approval) => $approval->status === 'approved' && !is_null($approval->approved_at)
        );

        return !$anyRejected && $allApproved;
    }

    private function getPhysicalFacilitiesOfficeId(): ?int
    {
        if (!is_null($this->physicalFacilitiesOfficeIdCache)) {
            return $this->physicalFacilitiesOfficeIdCache;
        }

        $this->physicalFacilitiesOfficeIdCache = Office::whereRaw('LOWER(department_name) = ?', ['physical facilities'])
            ->value('office_id');

        return $this->physicalFacilitiesOfficeIdCache;
    }

    private function getOfficeIdByDepartmentName(): array
    {
        if (!is_null($this->officeIdByDepartmentNameCache)) {
            return $this->officeIdByDepartmentNameCache;
        }

        $rows = DB::table('offices')
            ->select(['office_id', 'department_name'])
            ->whereNotNull('department_name')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $name = strtolower(trim((string) ($row->department_name ?? '')));
            if ($name !== '') {
                $map[$name] = (int) $row->office_id;
            }
        }

        $this->officeIdByDepartmentNameCache = $map;

        return $this->officeIdByDepartmentNameCache;
    }

    private function recordApprovalHistory(ReservationApproval $approval): void
    {
        if (is_null($approval->approved_at)) {
            return;
        }

        $this->upsertApprovalHistory(
            (int) $approval->approval_id,
            (int) $approval->reservation_id,
            (int) $approval->office_id,
            (string) $approval->status,
            $approval->approved_by_user_id ? (int) $approval->approved_by_user_id : null,
            $approval->approved_at,
        );
    }

    private function upsertApprovalHistory(int $approvalId, int $reservationId, int $officeId, string $status, ?int $approvedByUserId, $approvedAt): void
    {
        if (!Schema::hasTable('reservation_approval_histories')) {
            return;
        }

        DB::table('reservation_approval_histories')->updateOrInsert(
            ['approval_id' => $approvalId],
            [
                'reservation_id' => $reservationId,
                'office_id' => $officeId,
                'approved_by_user_id' => $approvedByUserId,
                'status' => $status,
                'approved_at' => $approvedAt,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
