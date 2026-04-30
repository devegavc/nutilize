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
        $startOfficeId = $ownerOfficeId;

        if (is_null($startOfficeId) || (!is_null($pfOfficeId) && $startOfficeId === $pfOfficeId)) {
            $startOfficeId = $pcOfficeId;
        } elseif (!is_null($ioOfficeId) && $startOfficeId !== $ioOfficeId) {
            $startOfficeId = $ioOfficeId;
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
