<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Office;
use App\Models\Reservation;
use App\Services\ReservationApprovalNotifier;
use App\Models\ReservationApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class ApprovalController extends Controller
{
    /** After this many completed reservations touching a unit, flag it for maintenance. */
    private const EQUIPMENT_RESERVATION_USAGE_THRESHOLD = 5;

    private ?array $officeIdsByShortCodeCache = null;
    private ?int $physicalFacilitiesOfficeIdCache = null;
    private ?array $officeIdByDepartmentNameCache = null;
    private array $ownerOfficeIdCache = [];
    /** @var array<int, true>|null */
    private ?array $batchGymLookup = null;
    /** @var array<int, true>|null */
    private ?array $batchGymWithItemsLookup = null;

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
        // For Physical Facilities admin, ensure final approval rows exist for the most recent open requests.
        $actionableReservationIds = [];

        if ($user->isPhysicalFacilitiesAdmin()) {
            $this->syncReservationApprovalWorkflow();
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

        // For pf_admin, exclude reservations that have any office rejections
        $rejectedByOfficeReservationIds = [];
        if ($user->isPhysicalFacilitiesAdmin()) {
            $physicalFacilitiesOfficeId = $this->getPhysicalFacilitiesOfficeId();
            $query = ReservationApproval::where('status', 'rejected');
            
            if (!is_null($physicalFacilitiesOfficeId)) {
                $query->where('office_id', '!=', $physicalFacilitiesOfficeId);
            }
            
            $rejectedByOfficeReservationIds = $query
                ->pluck('reservation_id')
                ->unique()
                ->values()
                ->all();
        }

        $pendingQuery = ReservationApproval::where('office_id', $user->office_id)
            ->whereNull('approved_at')
            ->whereIn('reservation_id', $actionableReservationIds)
            ->whereNotIn('reservation_id', $rejectedByOfficeReservationIds)
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

        // For pf_admin, add rejected approvals query
        $rejectedApprovals = new LengthAwarePaginator([], 0, 10, 1);
        if ($user->isPhysicalFacilitiesAdmin()) {
            $physicalFacilitiesOfficeId = $this->getPhysicalFacilitiesOfficeId();
            
            // Get all rejections from offices (not from PF admin itself)
            $query = ReservationApproval::where('status', 'rejected')
                ->whereNotNull('approved_at')
                ->with(['reservation.user', 'office'])
                ->orderByDesc('approved_at');
            
            if (!is_null($physicalFacilitiesOfficeId)) {
                $query->where('office_id', '!=', $physicalFacilitiesOfficeId);
            }
            
            $allRejections = $query->get();
            
            // Get only the latest rejection per reservation
            $rejectionsByReservation = [];
            foreach ($allRejections as $rejection) {
                $resId = $rejection->reservation_id;
                if (!isset($rejectionsByReservation[$resId])) {
                    $rejectionsByReservation[$resId] = $rejection;
                }
            }
            
            // Convert to paginated collection
            $page = request()->get('rejected_page', 1);
            $perPage = 10;
            $items = collect($rejectionsByReservation)->values();
            $total = $items->count();
            $pagedItems = $items->slice(($page - 1) * $perPage, $perPage)->values();
            
            $rejectedApprovals = new LengthAwarePaginator(
                $pagedItems,
                $total,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                    'pageName' => 'rejected_page',
                ]
            );
        }

        $approvedApprovals = ReservationApproval::where('office_id', $user->office_id)
            ->whereNotNull('approved_at')
            ->with(['reservation.user'])
            ->orderByDesc('approved_at')
            ->paginate(10);

        return view('dashboard-approvals', [
            'pendingApprovals' => $pendingApprovals,
            'returnApprovals' => $returnApprovals,
            'rejectedApprovals' => $rejectedApprovals,
            'approvedApprovals' => $approvedApprovals,
            'authUser' => $user,
            'isPfAdmin' => $user->isPhysicalFacilitiesAdmin(),
        ]);
    }

    public function approve($approvalId)
    {
        try {
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

            // This office has acted already; remove its approval request notifications.
            $this->clearOfficeApprovalNotifications((int) $approval->reservation_id, (int) $approval->office_id);

            // Notify the next actionable office when workflow advances
            $this->notifyNextActionableOffice((int) $approval->reservation_id, (int) $approval->office_id);

            // Update the overall reservation status if all office approvals are done
            $this->updateReservationStatus($approval->reservation_id);

            return response()->json([
                'success' => true,
                'message' => 'Request approved successfully.',
                'approval' => $approval,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'error' => $throwable->getMessage() ?: 'Unable to approve request.',
            ], 500);
        }
    }

    public function reject($approvalId)
    {
        try {
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

            // Request rejected: remove approval notifications tied to this reservation.
            $this->clearAllApprovalNotificationsForReservation((int) $approval->reservation_id);

            return response()->json([
                'success' => true,
                'message' => 'Request rejected.',
                'approval' => $approval,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'error' => $throwable->getMessage() ?: 'Unable to reject request.',
            ], 500);
        }
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

            if ($status === 'approved') {
                $this->recordEquipmentUnitUsageForApprovedReservation((int) $reservation->reservation_id);
            }

            if (in_array($status, ['approved', 'rejected', 'returned', 'damaged'], true)) {
                $this->clearAllApprovalNotificationsForReservation((int) $reservation->reservation_id);
            }

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

        if ($anyRejected) {
            // If any office rejected, set overall status to rejected
            // Do NOT cascade rejection to other pending approvals - only mark those that actually rejected
            $reservation->update(['overall_status' => 'rejected']);
            return;
        }

        if (is_null($physicalFacilitiesOfficeId)) {
            $allApproved = $approvals->every(fn($a) => $a->status === 'approved' && !is_null($a->approved_at));

            if ($allApproved) {
                $reservation->update(['overall_status' => 'approved']);
                $this->recordEquipmentUnitUsageForApprovedReservation((int) $reservationId);
            } else {
                $reservation->update(['overall_status' => 'pending_office_approvals']);
            }

            return;
        }

        $pfApproval = $approvals->firstWhere('office_id', $physicalFacilitiesOfficeId);
        $allNonPfApproved = $approvals
            ->where('office_id', '!=', $physicalFacilitiesOfficeId)
            ->every(fn($a) => $a->status === 'approved' && !is_null($a->approved_at));

        if ($pfApproval && $pfApproval->status === 'approved' && $pfApproval->approved_at) {
            $reservation->update(['overall_status' => 'approved']);
            $this->recordEquipmentUnitUsageForApprovedReservation((int) $reservationId);
        } elseif ($allNonPfApproved) {
            $reservation->update(['overall_status' => 'awaiting_physical_facilities']);
        } else {
            $reservation->update(['overall_status' => 'pending_office_approvals']);
        }
    }

    /**
     * Each fully-approved reservation counts as one use per assigned physical unit (tracked in reservation_item_units).
     * After threshold uses, the unit is marked maintenance for inspection/repair.
     */
    private function recordEquipmentUnitUsageForApprovedReservation(int $reservationId): void
    {
        if (
            !Schema::hasTable('item_units')
            || !Schema::hasTable('reservation_item_units')
            || !Schema::hasTable('reservation_details')
            || !Schema::hasTable('reservation_items')
        ) {
            return;
        }

        $reservation = Reservation::query()->find($reservationId);
        if (!$reservation || strtolower((string) $reservation->overall_status) !== 'approved') {
            return;
        }

        $lines = DB::table('reservation_details as details')
            ->join('reservation_items as ri', 'ri.reservation_items_id', '=', 'details.reservation_items_id')
            ->where('details.reservation_id', $reservationId)
            ->whereNotNull('details.reservation_items_id')
            ->select(['details.reservation_items_id', 'details.quantity', 'ri.item_id'])
            ->get();

        if ($lines->isEmpty()) {
            return;
        }

        foreach ($lines as $line) {
            $reservationItemsId = (int) $line->reservation_items_id;
            $itemId = (int) $line->item_id;
            $qty = max(1, (int) ($line->quantity ?? 1));

            $alreadyRecorded = DB::table('reservation_item_units')
                ->where('reservation_items_id', $reservationItemsId)
                ->exists();

            if ($alreadyRecorded) {
                continue;
            }

            $unitIds = $this->pickUnitsForReservationUsage($itemId, $qty);

            foreach ($unitIds as $unitId) {
                $unitId = (int) $unitId;

                DB::table('reservation_item_units')->insert([
                    'reservation_items_id' => $reservationItemsId,
                    'unit_id' => $unitId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $usageCount = (int) DB::table('reservation_item_units')
                    ->where('unit_id', $unitId)
                    ->count();

                if ($usageCount >= self::EQUIPMENT_RESERVATION_USAGE_THRESHOLD) {
                    DB::table('item_units')
                        ->where('unit_id', $unitId)
                        ->where('status', '<>', 'retired')
                        ->update([
                            'status' => 'maintenance',
                            'condition_notes' => 'Automatic maintenance after '.self::EQUIPMENT_RESERVATION_USAGE_THRESHOLD.' reservations — inspection or repair required.',
                            'last_maintenance_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
            }

            $this->syncItemAggregatesFromItemUnits($itemId);
        }
    }

    /**
     * Prefer units with fewer prior reservations, then lower unit_number. Only assign units that can still be lent (available/in_use).
     *
     * @return array<int, int>
     */
    private function pickUnitsForReservationUsage(int $itemId, int $quantity): array
    {
        $quantity = max(1, $quantity);

        $rows = DB::table('item_units as u')
            ->leftJoinSub(
                DB::table('reservation_item_units')
                    ->select('unit_id', DB::raw('count(*) as usage_count'))
                    ->groupBy('unit_id'),
                'usage',
                'usage.unit_id',
                '=',
                'u.unit_id'
            )
            ->where('u.item_id', $itemId)
            ->whereIn('u.status', ['available', 'in_use'])
            ->orderByRaw('COALESCE(usage.usage_count, 0) ASC')
            ->orderBy('u.unit_number')
            ->limit($quantity)
            ->pluck('u.unit_id')
            ->all();

        return array_map('intval', $rows);
    }

    private function syncItemAggregatesFromItemUnits(int $itemId): void
    {
        if (!Schema::hasTable('item_units') || !Schema::hasTable('items')) {
            return;
        }

        $itemStats = DB::table('item_units')
            ->where('item_id', $itemId)
            ->selectRaw("COUNT(*) FILTER (WHERE status <> 'retired') as total_active")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'in_use') as in_use_count")
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ('maintenance', 'damaged')) as issue_count")
            ->first();

        DB::table('items')
            ->where('item_id', $itemId)
            ->update([
                'quantity_total' => max(1, (int) ($itemStats->total_active ?? 1)),
                'quantity_in_use' => max(0, min(max(1, (int) ($itemStats->total_active ?? 1)), (int) ($itemStats->in_use_count ?? 0))),
                'maintenance_status' => DB::raw(((int) ($itemStats->issue_count ?? 0) > 0) ? 'true' : 'false'),
                'availability_status' => DB::raw((((int) ($itemStats->in_use_count ?? 0)) <= 0 && ((int) ($itemStats->issue_count ?? 0)) <= 0) ? 'true' : 'false'),
                'updated_at' => now(),
            ]);
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

        $reservation = Reservation::with('user')->find($reservationId);
        $actionableOfficeId = $this->getCurrentActionableOfficeId($reservationId);
        $pfOfficeId = $this->getOfficeIdsByShortCode()['PF'] ?? $this->getPhysicalFacilitiesOfficeId();

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

                // Create notifications only for the current actionable office or PF admin
                $this->createApprovalNotifications($reservation, $officeId, $actionableOfficeId, $pfOfficeId);
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
        $reservationIds = array_values(array_unique(array_map('intval', $reservationIds)));
        $this->warmBatchWorkflowLookups($reservationIds);

        try {
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

                $approvals = ($approvalsByReservation->get($reservationId) ?? collect())
                    ->keyBy(fn (ReservationApproval $row) => (int) $row->office_id);

                foreach ($actionSequence as $officeId) {
                    $officeId = (int) $officeId;
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
        } finally {
            $this->batchGymLookup = null;
            $this->batchGymWithItemsLookup = null;
        }
    }

    private function warmBatchWorkflowLookups(array $reservationIds): void
    {
        $this->batchGymLookup = [];
        $this->batchGymWithItemsLookup = [];

        if (!Schema::hasTable('reservation_details')) {
            return;
        }

        $gymIds = DB::table('reservation_details as details')
            ->join('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
            ->join('rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
            ->whereIn('details.reservation_id', $reservationIds)
            ->whereRaw('LOWER(TRIM(rooms.room_number)) = ?', ['gym'])
            ->distinct()
            ->pluck('details.reservation_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($gymIds as $gymId) {
            $this->batchGymLookup[$gymId] = true;
        }

        if ($gymIds !== []) {
            $gymWithItemsIds = DB::table('reservation_details as details')
                ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
                ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
                ->whereIn('details.reservation_id', $gymIds)
                ->whereNotNull('items.item_id')
                ->distinct()
                ->pluck('details.reservation_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($gymWithItemsIds as $gymId) {
                $this->batchGymWithItemsLookup[$gymId] = true;
            }
        }

        $officeIdsByCode = $this->getOfficeIdsByShortCode();
        $pfOfficeId = $officeIdsByCode['PF'] ?? $this->getPhysicalFacilitiesOfficeId();

        $ownerRows = DB::table('reservation_details as details')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->leftJoin('item_owners as owners', 'owners.owner_id', '=', 'items.owner_id')
            ->whereIn('details.reservation_id', $reservationIds)
            ->select(['details.reservation_id', 'owners.owner_name', 'owners.department_affiliation'])
            ->get();

        $groupedOwnerRows = [];
        foreach ($ownerRows as $row) {
            $groupedOwnerRows[(int) $row->reservation_id][] = $row;
        }

        foreach ($reservationIds as $reservationId) {
            if (array_key_exists($reservationId, $this->ownerOfficeIdCache)) {
                continue;
            }

            $rows = $groupedOwnerRows[$reservationId] ?? [];
            $this->ownerOfficeIdCache[$reservationId] = $this->computeOwnerOfficeIdFromItemOwnerRows(
                $rows,
                $officeIdsByCode,
                $pfOfficeId,
            );
        }
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

        // Create notifications for admins in this office
        foreach ($missingReservationIds as $reservationId) {
            $this->createApprovalNotifications(
                Reservation::find($reservationId),
                $officeId,
                $officeId,
                $this->getOfficeIdsByShortCode()['PF'] ?? $this->getPhysicalFacilitiesOfficeId()
            );
        }
    }

    private function resolveWorkflowOfficeIds(int $reservationId, bool $includePf): array
    {
        $ids = $this->getOfficeIdsByShortCode();
        $idsMulti = $this->getOfficeIdsByShortCodeMulti();
        $actionSequence = $this->getActionSequenceOfficeIds();

        if (empty($actionSequence)) {
            return [];
        }

        $pfOfficeId = $ids['PF'] ?? $this->getPhysicalFacilitiesOfficeId();
        $ioOfficeIds = $idsMulti['IO'] ?? (isset($ids['IO']) ? [(int) $ids['IO']] : []);
        $ownerOfficeId = $this->resolveOwnerOfficeId($reservationId, $ids, $pfOfficeId);
        $pcOfficeId = $ids['PC'] ?? null;
        $genEdOfficeId = $ids['GENED'] ?? null;
        $startOfficeId = $ownerOfficeId;

        if ($this->isGymRoomRequest($reservationId) && !is_null($genEdOfficeId)) {
            if ($this->isGymRoomRequestWithItems($reservationId) && !empty($ioOfficeIds)) {
                $actionSequence = array_values(array_filter(array_merge(
                    [
                        $genEdOfficeId,
                    ],
                    $ioOfficeIds,
                    [
                        $pcOfficeId,
                        $ids['SDAO'] ?? null,
                        $ids['DO'] ?? null,
                        $ids['SEC'] ?? null,
                    ]
                )));
            } else {
                $actionSequence = array_values(array_filter([
                    $genEdOfficeId,
                    $pcOfficeId,
                    $ids['SDAO'] ?? null,
                    $ids['DO'] ?? null,
                    $ids['SEC'] ?? null,
                ]));
            }

            $startOfficeId = $genEdOfficeId;
        } else {
            // Non-gym flow: start from item-owner office if it is in the sequence, otherwise fall back to PC.
            if (is_null($startOfficeId) || (!is_null($pfOfficeId) && (int) $startOfficeId === (int) $pfOfficeId)) {
                $startOfficeId = $pcOfficeId;
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

        if ($includePf && !is_null($pfOfficeId) && !in_array($pfOfficeId, $workflowOfficeIds, true)) {
            $workflowOfficeIds[] = $pfOfficeId;
        }

        // Keep order stable; do not collapse distinct offices with the same short_code (e.g. multiple IO offices).
        $seen = [];
        $deduped = [];
        foreach ($workflowOfficeIds as $officeId) {
            $officeId = (int) $officeId;
            if ($officeId <= 0 || isset($seen[$officeId])) {
                continue;
            }
            $seen[$officeId] = true;
            $deduped[] = $officeId;
        }

        return $deduped;
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

    /**
     * Some deployments may have multiple offices sharing the same short_code (e.g. two IO offices).
     * Return all matching office IDs ordered by order_sequence then office_id.
     *
     * @return array<string, array<int, int>>
     */
    private function getOfficeIdsByShortCodeMulti(): array
    {
        $rows = DB::table('offices')
            ->select(['office_id', 'short_code', 'order_sequence'])
            ->whereNotNull('short_code')
            ->orderByRaw('COALESCE(order_sequence, 999999) ASC')
            ->orderBy('office_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row->short_code ?? '')));
            if ($code === '') {
                continue;
            }
            $map[$code] ??= [];
            $map[$code][] = (int) $row->office_id;
        }

        return $map;
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
        $reservationId = (int) $reservationId;
        if (!is_null($this->batchGymLookup)) {
            return isset($this->batchGymLookup[$reservationId]);
        }

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
        $reservationId = (int) $reservationId;
        if (!is_null($this->batchGymWithItemsLookup)) {
            return isset($this->batchGymWithItemsLookup[$reservationId]);
        }

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

        return $this->ownerOfficeIdCache[$reservationId] = $this->computeOwnerOfficeIdFromItemOwnerRows(
            $ownerRows->all(),
            $officeIdsByCode,
            $pfOfficeId,
        );
    }

    /**
     * @param  array<int, object>  $ownerRows
     */
    private function computeOwnerOfficeIdFromItemOwnerRows(array $ownerRows, array $officeIdsByCode, ?int $pfOfficeId): ?int
    {
        if ($ownerRows === []) {
            return null;
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
            return $ioOfficeId ?? $fallbackOfficeId;
        }

        if ($hasPfOwner) {
            return $pfOfficeId ?? $fallbackOfficeId;
        }

        return $fallbackOfficeId;
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

    private function createApprovalNotifications(Reservation $reservation, int $officeId, ?int $actionableOfficeId, ?int $pfOfficeId): void
    {
        ReservationApprovalNotifier::notifyOfficeIfRelevant($reservation, $officeId, $actionableOfficeId, $pfOfficeId);
    }

    private function notifyNextActionableOffice(int $reservationId, ?int $fromApprovingOfficeId = null): void
    {
        $reservation = Reservation::with('user')->find($reservationId);
        if (!$reservation) {
            return;
        }

        $nextActionableOfficeId = $this->getCurrentActionableOfficeId($reservationId);
        if (is_null($nextActionableOfficeId)) {
            return;
        }

        $pfOfficeId = $this->getOfficeIdsByShortCode()['PF'] ?? $this->getPhysicalFacilitiesOfficeId();

        if (!is_null($fromApprovingOfficeId) && (int) $fromApprovingOfficeId !== (int) $nextActionableOfficeId) {
            ReservationApprovalNotifier::notifyOfficeAfterPriorApproval(
                $reservation,
                (int) $nextActionableOfficeId,
                (int) $fromApprovingOfficeId,
                $pfOfficeId,
            );

            return;
        }

        $this->createApprovalNotifications($reservation, $nextActionableOfficeId, $nextActionableOfficeId, $pfOfficeId);
    }

    public function getNotifications()
    {
        $user = Auth::user();

        if ($user->isOfficeApprover()) {
            $actionableReservationIdsForUser = $this->getActionableReservationIdsForApprover($user);
            $this->syncApprovalNotificationsForUser($user, $actionableReservationIdsForUser);
        }

        $notifications = Notification::where('user_id', $user->user_id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->notification_id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'related_id' => $notification->related_id,
                    'read' => $notification->read,
                    'created_at' => $notification->created_at->format('M d, Y h:i A'),
                ];
            });

        $unreadCount = Notification::where('user_id', $user->user_id)
            ->whereRaw('notifications.read = false')
            ->count();

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * @return array<int, int> reservation ids currently actionable for this approver user.
     */
    private function getActionableReservationIdsForApprover(\App\Models\User $user): array
    {
        $officeId = (int) $user->office_id;
        if ($officeId <= 0) {
            return [];
        }

        // Start from this office's pending approvals to avoid scanning all reservations.
        $candidateReservationIds = ReservationApproval::query()
            ->where('office_id', $officeId)
            ->whereNull('approved_at')
            ->pluck('reservation_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($candidateReservationIds)) {
            return [];
        }

        // Exclude closed reservations.
        $candidateReservationIds = Reservation::query()
            ->whereIn('reservation_id', $candidateReservationIds)
            ->whereNotIn(DB::raw("LOWER(COALESCE(overall_status, ''))"), ['approved', 'rejected', 'returned', 'damaged'])
            ->pluck('reservation_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($candidateReservationIds)) {
            return [];
        }

        $actionableOfficeIds = $this->getActionableOfficeIdsForReservations($candidateReservationIds);
        $this->ensureActionableApprovalRows($actionableOfficeIds, $officeId);

        $actionableReservationIds = [];
        foreach ($actionableOfficeIds as $reservationId => $actionableOfficeId) {
            if ((int) $actionableOfficeId === $officeId) {
                $actionableReservationIds[] = (int) $reservationId;
            }
        }

        return array_values(array_unique($actionableReservationIds));
    }

    /**
     * Deterministic notification sync.
     * - Ensures exactly one approval notification per actionable reservation for this user.
     * - Deletes approval notifications when they are no longer actionable or the reservation is closed.
     */
    private function syncApprovalNotificationsForUser(\App\Models\User $user, array $actionableReservationIds): void
    {
        $userId = (int) $user->user_id;
        $actionableReservationIds = array_values(array_unique(array_map('intval', $actionableReservationIds)));

        $types = ['reservation_approval_request', 'reservation_approval_handoff'];

        // Option A (task inbox): show ONLY currently actionable requests.
        // Clear anything that is not actionable anymore.
        $cleanupQuery = Notification::query()
            ->where('user_id', $userId)
            ->whereIn('type', $types);

        if (empty($actionableReservationIds)) {
            $cleanupQuery->delete();
            return;
        }

        $cleanupQuery
            ->whereNotIn('related_id', $actionableReservationIds)
            ->delete();

        if (empty($actionableReservationIds)) {
            return;
        }

        $reservations = Reservation::with('user')
            ->whereIn('reservation_id', $actionableReservationIds)
            ->get();

        // Option A: single task notification per reservation.
        // Remove other approval notification types (handoff, etc.) for actionable reservations.
        Notification::query()
            ->where('user_id', $userId)
            ->whereIn('related_id', $actionableReservationIds)
            ->whereIn('type', $types)
            ->where('type', '<>', 'reservation_approval_request')
            ->delete();

        foreach ($reservations as $reservation) {
            $requesterName = $reservation->user->full_name ?? $reservation->user->username ?? 'Unknown';
            $activityName = trim((string) $reservation->activity_name) ?: 'Reservation request';

            $key = [
                'user_id' => $userId,
                'type' => 'reservation_approval_request',
                'related_id' => (int) $reservation->reservation_id,
            ];

            // Update copy without resetting read/unread.
            $updated = DB::table('notifications')
                ->where($key)
                ->update([
                    'title' => 'Reservation approval needed',
                    'message' => "Request '{$activityName}' by {$requesterName} is waiting for your approval.",
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                continue;
            }

            // Insert new notification as unread.
            DB::table('notifications')->insert($key + [
                'title' => 'Reservation approval needed',
                'message' => "Request '{$activityName}' by {$requesterName} is waiting for your approval.",
                'read' => DB::raw('false'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureActionableOfficeNotificationsFromWorkflow(\App\Models\User $user, array $actionableReservationIds): void
    {
        $userId = (int) $user->user_id;
        $officeId = (int) $user->office_id;

        if ($userId <= 0 || $officeId <= 0 || empty($actionableReservationIds)) {
            return;
        }

        $actionableReservationIds = array_values(array_unique(array_map('intval', $actionableReservationIds)));

        // If a request is actionable again, resurface prior read alerts as unread.
        Notification::query()
            ->where('user_id', $userId)
            ->whereIn('related_id', $actionableReservationIds)
            ->whereIn('type', ['reservation_approval_request', 'reservation_approval_handoff'])
            ->whereRaw('notifications.read = true')
            ->update([
                'read' => DB::raw('false'),
                'updated_at' => now(),
            ]);

        $existingReservationIds = Notification::query()
            ->where('user_id', $userId)
            ->whereIn('related_id', $actionableReservationIds)
            ->whereIn('type', ['reservation_approval_request', 'reservation_approval_handoff'])
            ->whereRaw('notifications.read = false')
            ->pluck('related_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $missingReservationIds = array_values(array_diff($actionableReservationIds, $existingReservationIds));

        if (empty($missingReservationIds)) {
            return;
        }

        $reservations = Reservation::with('user')
            ->whereIn('reservation_id', $missingReservationIds)
            ->get();

        foreach ($reservations as $reservation) {
            try {
                $requesterName = $reservation->user->full_name ?? $reservation->user->username ?? 'Unknown';
                $activityName = trim((string) $reservation->activity_name) ?: 'Reservation request';

                Notification::create([
                    'user_id' => $userId,
                    'type' => 'reservation_approval_request',
                    'title' => 'Reservation approval needed',
                    'message' => "Request '{$activityName}' by {$requesterName} is waiting for your approval.",
                    'related_id' => $reservation->reservation_id,
                    'read' => false,
                ]);
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        }
    }

    private function pruneStaleApprovalNotificationsForUser(\App\Models\User $user): void
    {
        // Keep approval notifications for active requests; deletion is handled by closed-status pruning.
        // This prevents valid notifications from disappearing while requests are still in workflow.
        return;
    }

    public function markNotificationAsRead($notificationId)
    {
        $user = Auth::user();

        DB::table('notifications')
            ->where('user_id', (int) $user->user_id)
            ->where('notification_id', (int) $notificationId)
            ->update([
                'read' => DB::raw('true'),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    private function ensurePendingApprovalNotifications(\App\Models\User $user): void
    {
        $officeId = (int) $user->office_id;
        if ($officeId <= 0) {
            return;
        }

        $pendingApprovals = ReservationApproval::where('office_id', $officeId)
            // Match dashboard pending queue logic: actionable rows are not yet acted on.
            ->whereNull('approved_at')
            ->pluck('reservation_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($pendingApprovals)) {
            return;
        }

        $userId = (int) $user->user_id;

        // Re-open read notifications for requests that are currently pending again.
        Notification::query()
            ->where('user_id', $userId)
            ->whereIn('related_id', $pendingApprovals)
            ->whereIn('type', ['reservation_approval_request', 'reservation_approval_handoff'])
            ->whereRaw('notifications.read = true')
            ->update([
                'read' => DB::raw('false'),
                'updated_at' => now(),
            ]);

        $existingNotifications = Notification::where('user_id', $userId)
            ->whereIn('related_id', $pendingApprovals)
            ->whereIn('type', ['reservation_approval_request', 'reservation_approval_handoff'])
            ->whereRaw('notifications.read = false')
            ->pluck('related_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $missingReservationIds = array_values(array_diff($pendingApprovals, $existingNotifications));

        if (empty($missingReservationIds)) {
            return;
        }

        $reservations = Reservation::with('user')
            ->whereIn('reservation_id', $missingReservationIds)
            ->get();

        foreach ($reservations as $reservation) {
            try {
                $requesterName = $reservation->user->full_name ?? $reservation->user->username ?? 'Unknown';
                $activityName = trim((string) $reservation->activity_name) ?: 'Reservation request';

                Notification::create([
                    'user_id' => $userId,
                    'type' => 'reservation_approval_request',
                    'title' => 'Reservation approval needed',
                    'message' => "Request '{$activityName}' by {$requesterName} is waiting for your approval.",
                    'related_id' => $reservation->reservation_id,
                    'read' => false,
                ]);
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        }
    }

    private function clearOfficeApprovalNotifications(int $reservationId, int $officeId): void
    {
        if ($reservationId <= 0 || $officeId <= 0) {
            return;
        }

        $userIds = $this->getApproverUserIdsForOffice($officeId);
        if (empty($userIds)) {
            return;
        }

        Notification::query()
            ->whereIn('user_id', $userIds)
            ->where('related_id', $reservationId)
            ->whereIn('type', ['reservation_approval_request', 'reservation_approval_handoff'])
            ->delete();
    }

    private function clearAllApprovalNotificationsForReservation(int $reservationId): void
    {
        if ($reservationId <= 0) {
            return;
        }

        Notification::query()
            ->where('related_id', $reservationId)
            ->whereIn('type', ['reservation_approval_request', 'reservation_approval_handoff'])
            ->delete();
    }

    private function getApproverUserIdsForOffice(int $officeId): array
    {
        if ($officeId <= 0) {
            return [];
        }

        return DB::table('users')
            ->where('office_id', $officeId)
            ->whereRaw('LOWER(role) IN (?, ?)', ['admin', 'pf_admin'])
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function getReservationDetails($reservationId)
    {
        $user = Auth::user();

        // Ensure the user is an admin
        if (!$user->isOfficeApprover()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this reservation.',
            ], 403);
        }

        $reservation = Reservation::with(['user', 'approvals.office', 'approvals.approvedByUser'])
            ->findOrFail($reservationId);

        // Get reservation details with items
        $details = DB::table('reservation_details as details')
            ->leftJoin('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->leftJoin('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->leftJoin('item_units as units', 'units.unit_id', '=', 'items.unit_id')
            ->where('details.reservation_id', $reservationId)
            ->select([
                'items.item_name as item_name',
                'details.quantity',
                'units.name as unit_name',
            ])
            ->get();

        $items = $details->map(function ($detail) {
            return [
                'name' => $detail->item_name ?? 'Unknown Item',
                'quantity' => $detail->quantity,
                'unit' => $detail->unit_name ?? '',
            ];
        });

        $reservationData = [
            'id' => $reservation->reservation_id,
            'activity_name' => $reservation->activity_name,
            'requester' => $reservation->user->full_name ?? $reservation->user->username,
            'requested_date' => $reservation->created_at->format('M d, Y'),
            'start_date' => $reservation->date_of_activity ? $reservation->date_of_activity->format('M d, Y') : 'N/A',
            'end_date' => $reservation->date_of_activity ? $reservation->date_of_activity->format('M d, Y') : 'N/A', // Assuming same date
            'start_time' => $reservation->start_of_activity ? $reservation->start_of_activity->format('H:i') : 'N/A',
            'end_time' => $reservation->Start_of_activity ? $reservation->Start_of_activity->format('H:i') : 'N/A', // Note: inconsistent casing
            'status' => $reservation->status ?? $reservation->overall_status ?? 'Unknown',
            'items' => $items,
            'approvals' => $reservation->approvals->map(function ($approval) {
                return [
                    'office' => $approval->office->name ?? 'Unknown Office',
                    'status' => $approval->status,
                    'approved_by' => $approval->approvedByUser->full_name ?? $approval->approvedByUser->username ?? null,
                    'approved_at' => $approval->approved_at ? $approval->approved_at->format('M d, Y h:i A') : null,
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'reservation' => $reservationData,
        ]);
    }
}
