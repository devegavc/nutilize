<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Office;
use App\Models\Reservation;
use App\Services\ReservationApprovalNotifier;
use App\Services\ProgramChairOfficeResolver;
use App\Services\ItemOwnerService;
use App\Services\ItemUnitService;
use App\Services\ReservationApprovalWorkflowService;
use App\Services\ReservationApprovalDeduper;
use App\Models\ReservationApproval;
use Illuminate\Support\Facades\Cache;
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

    public static function usageThresholdMaintenanceReason(?int $usageCount = null): string
    {
        $uses = max(self::EQUIPMENT_RESERVATION_USAGE_THRESHOLD, (int) ($usageCount ?? self::EQUIPMENT_RESERVATION_USAGE_THRESHOLD));

        return "Scheduled maintenance required: this unit has completed {$uses} reservation uses and must be inspected before it can be borrowed again.";
    }

    private const NOTIFICATION_SYNC_BATCH_LIMIT = 40;

    private ?array $officeIdsByShortCodeCache = null;
    private ?int $physicalFacilitiesOfficeIdCache = null;
    private ?array $officeIdByDepartmentNameCache = null;
    private array $ownerOfficeIdCache = [];
    /** @var array<int, true>|null */
    private ?array $batchGymLookup = null;
    /** @var array<int, true>|null */
    private ?array $batchGymWithItemsLookup = null;

    /** @var array<int, int> */
    private array $batchPcOfficeLookup = [];

    /** @var array<int, int>|null */
    private ?array $actionableOfficeIdsRequestCache = null;

    /** @var array<string, bool> — static cache so Schema::hasTable() only queries DB once per process */
    private static array $tableExistsCache = [];

    public function index()
    {
        $user = Auth::user();
        
        if (!$user->isOfficeApprover()) {
            return redirect('/dashboard/home')->with('error', 'Unauthorized access.');
        }

        $actionableReservationIds = $this->getActionableReservationIdsForApprover($user);

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

        $distinctPendingApprovalIds = ReservationApproval::query()
            ->selectRaw('MIN(approval_id) as approval_id')
            ->where('office_id', $user->office_id)
            ->whereNull('approved_at')
            ->whereIn('reservation_id', $actionableReservationIds !== [] ? $actionableReservationIds : [-1])
            ->whereNotIn('reservation_id', $rejectedByOfficeReservationIds)
            ->groupBy('reservation_id')
            ->pluck('approval_id')
            ->all();

        $pendingApprovals = ReservationApproval::query()
            ->whereIn('approval_id', $distinctPendingApprovalIds !== [] ? $distinctPendingApprovalIds : [-1])
            ->with(['reservation.user', 'reservation.approvals', 'reservation.details'])
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

            if (!ReservationApprovalWorkflowService::userCanActOnApproval($user, $approval)) {
                return response()->json(['error' => 'This request is waiting for the item owner who registered the borrowed equipment.'], 403);
            }

            $now = now();
            $updatePayload = [
                'status' => 'approved',
                'approved_at' => $now,
                'approved_by_user_id' => (int) $user->user_id,
            ];

            if (
                ItemOwnerService::isItemOwnerUser($user)
                && ReservationApprovalWorkflowService::supportsOwnerScopedApprovals()
            ) {
                $ownerId = ItemOwnerService::ownerIdForUser((int) $user->user_id);
                if ($ownerId && is_null($approval->owner_id)) {
                    $updatePayload['owner_id'] = $ownerId;
                }
            }

            $approval->update($updatePayload);

            $this->recordApprovalHistory($approval);

            // Fix any null-status rows without the expensive full sync (rows already exist from workflow setup).
            $this->fixNullApprovalStatuses((int) $approval->reservation_id);

            // Load reservation once and reuse across all subsequent helpers.
            $reservation = Reservation::with('user')->find((int) $approval->reservation_id);

            // Route program-chair rows to the correct office before notifying the next approver.
            $this->prepareReservationWorkflowHandoff((int) $approval->reservation_id);
            ReservationApprovalWorkflowService::reconcileItemOwnerApprovals((int) $approval->reservation_id);
            ReservationApprovalDeduper::deduplicatePendingForReservations([(int) $approval->reservation_id]);
            $this->actionableOfficeIdsRequestCache = null;

            // Clear only the acting approver's notification; other item owners may still need to act.
            $this->clearApprovalNotificationsForUser((int) $approval->reservation_id, (int) $user->user_id);

            // Notify the next actionable office when workflow advances
            $this->notifyNextActionableOfficeWithReservation($reservation, (int) $approval->office_id, true);

            // Update the overall reservation status if all office approvals are done
            $this->updateReservationStatus($approval->reservation_id, $reservation);
            Cache::forget('office.decision_count.' . (int) $approval->office_id . '.approved');
            Cache::forget('notification_unread_count.user.' . (int) $user->user_id);

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

            if (!ReservationApprovalWorkflowService::userCanActOnApproval($user, $approval)) {
                return response()->json(['error' => 'This request is waiting for the item owner who registered the borrowed equipment.'], 403);
            }

            $approval->update([
                'status' => 'rejected',
                'approved_at' => now(),
                'approved_by_user_id' => (int) $user->user_id,
            ]);

            $this->recordApprovalHistory($approval);

            $this->fixNullApprovalStatuses((int) $approval->reservation_id);

            // Update the overall reservation status
            $this->updateReservationStatus($approval->reservation_id);

            // Request rejected: remove approval notifications tied to this reservation.
            $this->clearAllApprovalNotificationsForReservation((int) $approval->reservation_id);
            Cache::forget('office.decision_count.' . (int) $approval->office_id . '.rejected');
            Cache::forget('notification_unread_count.user.' . (int) $user->user_id);

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

            $reservation = Reservation::with('approvals')->findOrFail($reservationId);
            $physicalFacilitiesOfficeId = $this->getPhysicalFacilitiesOfficeId();

            ReservationApprovalDeduper::deduplicatePendingForReservations([(int) $reservationId]);

            DB::transaction(function () use ($reservation, $physicalFacilitiesOfficeId, $status, $user) {
                $reservation->update(['overall_status' => $status]);

                if ($status === 'returned') {
                    $this->releaseReservationInventory((int) $reservation->reservation_id);
                }

                if ($status === 'damaged') {
                    $this->applyReservationDamageToMaintenance($reservation);
                    $this->createDamageReportsForReservation($reservation);
                }

                if (is_null($physicalFacilitiesOfficeId)) {
                    return;
                }

                $now = now();

                DB::table('reservation_approvals')
                    ->where('reservation_id', $reservation->reservation_id)
                    ->where('office_id', $physicalFacilitiesOfficeId)
                    ->whereNull('approved_at')
                    ->update([
                        'status' => $status,
                        'approved_at' => $now,
                        'approved_by_user_id' => (int) $user->user_id,
                        'updated_at' => $now,
                    ]);

                $pfApproval = DB::table('reservation_approvals')
                    ->where('reservation_id', $reservation->reservation_id)
                    ->where('office_id', $physicalFacilitiesOfficeId)
                    ->orderBy('approval_id')
                    ->first();

                if (!$pfApproval) {
                    $approvalId = (int) DB::table('reservation_approvals')->insertGetId([
                        'reservation_id' => $reservation->reservation_id,
                        'office_id' => $physicalFacilitiesOfficeId,
                        'status' => $status,
                        'approved_at' => $now,
                        'approved_by_user_id' => (int) $user->user_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $approvalId = (int) $pfApproval->approval_id;

                    DB::table('reservation_approvals')
                        ->where('approval_id', $approvalId)
                        ->update([
                            'status' => $status,
                            'approved_at' => $now,
                            'approved_by_user_id' => (int) $user->user_id,
                            'updated_at' => $now,
                        ]);
                }

                $this->upsertApprovalHistory(
                    $approvalId,
                    (int) $reservation->reservation_id,
                    (int) $physicalFacilitiesOfficeId,
                    $status,
                    (int) $user->user_id,
                    $now,
                );
            });

            if ($status === 'approved') {
                $this->recordEquipmentUnitUsageForApprovedReservation((int) $reservation->reservation_id);
            }

            if (in_array($status, ['approved', 'rejected', 'returned', 'damaged', 'cancelled', 'canceled', 'expired'], true)) {
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

    /**
     * Allow a user to cancel their own reservation request
     */
    public function cancelReservation($reservationId)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $reservation = Reservation::findOrFail($reservationId);

            // Only allow the requester to cancel their own reservation
            if ((int) $reservation->user_id !== (int) $user->user_id) {
                return response()->json(['error' => 'You can only cancel your own requests.'], 403);
            }

            // Check if reservation can be cancelled
            if (!$reservation->canBeCancelled()) {
                return response()->json([
                    'error' => 'This request cannot be cancelled. It may have already been approved, rejected, or expired.',
                ], 422);
            }

            DB::transaction(function () use ($reservation) {
                $reservation->update(['overall_status' => 'cancelled']);

                // Otherwise these rows stay pending and the request keeps showing up in
                // every approver queue it had not reached a decision in yet.
                ReservationApprovalWorkflowService::closePendingApprovals(
                    (int) $reservation->reservation_id,
                    'cancelled'
                );

                // Clear all approval notifications for this reservation
                $this->clearAllApprovalNotificationsForReservation((int) $reservation->reservation_id);
            });

            return response()->json([
                'success' => true,
                'message' => 'Request cancelled successfully.',
                'reservation_id' => $reservation->reservation_id,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'error' => $throwable->getMessage() ?: 'Unable to cancel request.',
            ], 500);
        }
    }

    /**
     * Lightweight alternative to syncReservationApprovals() — only fixes NULL status rows.
     * Used after an approve/reject action when rows already exist from the initial workflow setup.
     */
    private function fixNullApprovalStatuses(int $reservationId): void
    {
        DB::table('reservation_approvals')
            ->where('reservation_id', $reservationId)
            ->whereNull('status')
            ->update(['status' => 'pending', 'updated_at' => now()]);
    }

    private function notifyNextActionableOfficeWithReservation(?Reservation $reservation, ?int $fromApprovingOfficeId = null, bool $handoffPrepared = false): void
    {
        if (!$reservation) {
            return;
        }

        if (!$handoffPrepared) {
            $this->prepareReservationWorkflowHandoff((int) $reservation->reservation_id);
            $this->actionableOfficeIdsRequestCache = null;
        }

        $nextActionableOfficeId = $this->getCurrentActionableOfficeId((int) $reservation->reservation_id);
        $pfOfficeId = $this->getOfficeIdsByShortCode()['PF'] ?? $this->getPhysicalFacilitiesOfficeId();

        if (!is_null($fromApprovingOfficeId)
            && (is_null($nextActionableOfficeId) || (int) $fromApprovingOfficeId !== (int) $nextActionableOfficeId)
        ) {
            ReservationApprovalNotifier::dismissOfficeApprovalNotifications(
                (int) $fromApprovingOfficeId,
                (int) $reservation->reservation_id,
            );
        }

        if (is_null($nextActionableOfficeId)) {
            return;
        }

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

    private function updateReservationStatus($reservationId, ?Reservation $reservation = null)
    {
        $reservation = $reservation ?? Reservation::findOrFail($reservationId);
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
            !$this->tableExists('item_units')
            || !$this->tableExists('reservation_item_units')
            || !$this->tableExists('reservation_details')
            || !$this->tableExists('reservation_items')
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

        $itemIdsToReconcile = [];

        foreach ($lines as $line) {
            $reservationItemsId = (int) $line->reservation_items_id;
            $itemId = (int) $line->item_id;
            $qty = max(1, (int) ($line->quantity ?? 1));

            $alreadyRecorded = DB::table('reservation_item_units')
                ->where('reservation_items_id', $reservationItemsId)
                ->exists();

            if ($alreadyRecorded) {
                if ($itemId > 0) {
                    $itemIdsToReconcile[$itemId] = $itemId;
                }
                continue;
            }

            $unitIds = ItemUnitService::pickUnitsForReservation($itemId, $qty, $reservation);

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
                            'condition_notes' => self::usageThresholdMaintenanceReason($usageCount),
                            'last_maintenance_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
            }

            if ($itemId > 0) {
                $itemIdsToReconcile[$itemId] = $itemId;
            }
        }

        if ($itemIdsToReconcile !== []) {
            ItemUnitService::reconcileInUseForItems(array_values($itemIdsToReconcile));
        }
    }

    /**
     * Prefer units with fewer prior reservations, then lower unit_number.
     * Skip units already reserved on overlapping activity days.
     *
     * @return array<int, int>
     */
    private function pickUnitsForReservationUsage(int $itemId, int $quantity, ?Reservation $reservation = null): array
    {
        return ItemUnitService::pickUnitsForReservation($itemId, $quantity, $reservation);
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
                'availability_status' => DB::raw(((int) ($itemStats->issue_count ?? 0) <= 0) ? 'true' : 'false'),
                'updated_at' => now(),
            ]);
    }

    /**
     * Prefer units assigned to this reservation; otherwise pick in-use/available units for the item.
     *
     * @return array<int, int>
     */
    private function resolveReservationUnitIds(int $reservationId, int $itemId, int $quantity): array
    {
        $quantity = max(1, $quantity);

        if ($this->tableExists('reservation_item_units') && $this->tableExists('reservation_items') && $this->tableExists('reservation_details')) {
            $assignedUnitIds = DB::table('reservation_item_units as riu')
                ->join('reservation_items as ri', 'ri.reservation_items_id', '=', 'riu.reservation_items_id')
                ->join('reservation_details as rd', 'rd.reservation_items_id', '=', 'ri.reservation_items_id')
                ->where('rd.reservation_id', $reservationId)
                ->where('ri.item_id', $itemId)
                ->orderBy('riu.unit_id')
                ->limit($quantity)
                ->pluck('riu.unit_id')
                ->map(fn ($unitId) => (int) $unitId)
                ->all();

            if (!empty($assignedUnitIds)) {
                return $assignedUnitIds;
            }
        }

        return $this->pickUnitsForReservationUsage($itemId, $quantity, Reservation::query()->find($reservationId));
    }

    private function releaseReservationInventory(int $reservationId): void
    {
        $itemIdsToSync = [];

        if ($this->tableExists('reservation_item_units') && $this->tableExists('item_units')) {
            $unitRows = DB::table('reservation_item_units as riu')
                ->join('reservation_items as ri', 'ri.reservation_items_id', '=', 'riu.reservation_items_id')
                ->join('reservation_details as rd', 'rd.reservation_items_id', '=', 'ri.reservation_items_id')
                ->join('item_units as units', 'units.unit_id', '=', 'riu.unit_id')
                ->where('rd.reservation_id', $reservationId)
                ->select(['riu.unit_id', 'ri.item_id', 'units.status'])
                ->get();

            foreach ($unitRows as $row) {
                $unitId = (int) $row->unit_id;
                $itemId = (int) $row->item_id;

                if ($unitId <= 0 || $itemId <= 0) {
                    continue;
                }

                if (in_array(strtolower((string) $row->status), ['in_use', 'available'], true)) {
                    DB::table('item_units')
                        ->where('unit_id', $unitId)
                        ->update([
                            'status' => 'available',
                            'updated_at' => now(),
                        ]);
                }

                $itemIdsToSync[$itemId] = $itemId;
            }
        }

        if ($itemIdsToSync !== []) {
            ItemUnitService::reconcileInUseForItems(array_values($itemIdsToSync));
        }

        if (Schema::hasTable('reservation_details') && Schema::hasTable('items') && empty($itemIdsToSync)) {
            $itemQuantities = DB::table('reservation_details as details')
                ->join('reservation_items as ri', 'ri.reservation_items_id', '=', 'details.reservation_items_id')
                ->join('items as items', 'items.item_id', '=', 'ri.item_id')
                ->where('details.reservation_id', $reservationId)
                ->select(['items.item_id', DB::raw('SUM(GREATEST(details.quantity, 1)) as qty')])
                ->groupBy('items.item_id')
                ->get();

            foreach ($itemQuantities as $row) {
                $itemId = (int) $row->item_id;
                $releaseQty = max(1, (int) ($row->qty ?? 1));

                DB::table('items')
                    ->where('item_id', $itemId)
                    ->update([
                        'quantity_in_use' => DB::raw('GREATEST(COALESCE(quantity_in_use, 0) - ' . $releaseQty . ', 0)'),
                        'updated_at' => now(),
                    ]);
            }
        }

        if (Schema::hasTable('reservation_rooms') && Schema::hasTable('rooms')) {
            $roomIds = DB::table('reservation_details as details')
                ->join('reservation_rooms as rr', 'rr.reservation_rooms_id', '=', 'details.reservation_rooms_id')
                ->where('details.reservation_id', $reservationId)
                ->pluck('rr.room_id')
                ->filter(fn ($roomId) => !is_null($roomId))
                ->map(fn ($roomId) => (int) $roomId)
                ->unique()
                ->all();

            if (!empty($roomIds)) {
                DB::table('rooms')
                    ->whereIn('room_id', $roomIds)
                    ->update([
                        'date_reserved' => null,
                        'updated_at' => now(),
                    ]);
            }
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
                $unitIds = $this->resolveReservationUnitIds((int) $reservation->reservation_id, $itemRow['item_id'], $itemRow['quantity']);

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
                        'availability_status' => DB::raw(((int) ($itemStats->issue_count ?? 0) <= 0) ? 'true' : 'false'),
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
            $openQuery = Reservation::query();
            \App\Support\OpenReservationScope::apply($openQuery);
            $reservationIds = $openQuery
                ->orderByDesc('created_at')
                ->limit(40)
                ->pluck('reservation_id')
                ->all();
        }

        foreach ($reservationIds as $reservationId) {
            $this->syncReservationApprovals((int) $reservationId);
        }
    }

    private function syncReservationApprovals(int $reservationId): void
    {
        ProgramChairOfficeResolver::reconcilePendingLegacyPcApproval($reservationId);

        $workflowOfficeIds = $this->resolveWorkflowOfficeIds($reservationId, true);

        if (empty($workflowOfficeIds)) {
            return;
        }

        ReservationApprovalWorkflowService::ensureApprovalRows($reservationId, $workflowOfficeIds);

        $reservation = Reservation::with('user')->find($reservationId);
        $actionableOfficeId = $this->getCurrentActionableOfficeId($reservationId);
        $pfOfficeId = $this->getOfficeIdsByShortCode()['PF'] ?? $this->getPhysicalFacilitiesOfficeId();

        if ($reservation && !is_null($actionableOfficeId)) {
            $this->createApprovalNotifications($reservation, (int) $actionableOfficeId, $actionableOfficeId, $pfOfficeId);
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
        if (
            is_array($this->actionableOfficeIdsRequestCache)
            && array_key_exists($reservationId, $this->actionableOfficeIdsRequestCache)
        ) {
            return $this->actionableOfficeIdsRequestCache[$reservationId];
        }

        $map = $this->getActionableOfficeIdsForReservations([$reservationId]);
        $this->actionableOfficeIdsRequestCache = array_merge($this->actionableOfficeIdsRequestCache ?? [], $map);

        return $map[$reservationId] ?? null;
    }

    private function getActionableOfficeIdsForReservations(array $reservationIds): array
    {
        if (empty($reservationIds)) {
            return [];
        }
        $reservationIds = array_slice(
            array_values(array_unique(array_map('intval', $reservationIds))),
            0,
            self::NOTIFICATION_SYNC_BATCH_LIMIT
        );
        $this->warmBatchWorkflowLookups($reservationIds);

        try {
            $approvalsByReservation = ReservationApproval::query()
                ->whereIn('reservation_id', $reservationIds)
                ->get(['reservation_id', 'office_id', 'owner_id', 'status', 'approved_at'])
                ->groupBy('reservation_id');

            $actionableOfficeIds = [];

            foreach ($reservationIds as $reservationId) {
                $reservationId = (int) $reservationId;
                $actionSequence = $this->resolveWorkflowOfficeIds($reservationId, false);

                if (empty($actionSequence)) {
                    continue;
                }

                $approvals = ReservationApprovalDeduper::collapseByOfficeId(
                    $approvalsByReservation->get($reservationId) ?? collect()
                );

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
            $this->batchPcOfficeLookup = [];
        }
    }

    private function warmBatchWorkflowLookups(array $reservationIds): void
    {
        $this->batchGymLookup = [];
        $this->batchGymWithItemsLookup = [];
        $this->batchPcOfficeLookup = ProgramChairOfficeResolver::batchResolveForReservations($reservationIds);

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
            ->select(['details.reservation_id', 'owners.owner_name', 'owners.department_affiliation', 'owners.user_id'])
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

        $pfOfficeId = $this->getOfficeIdsByShortCode()['PF'] ?? $this->getPhysicalFacilitiesOfficeId();

        foreach ($targetReservationIds as $reservationId) {
            ProgramChairOfficeResolver::reconcilePendingLegacyPcApproval($reservationId);
            $workflowOfficeIds = $this->resolveWorkflowOfficeIds($reservationId, true);
            ReservationApprovalWorkflowService::ensureApprovalRows($reservationId, $workflowOfficeIds);

            $reservation = Reservation::find($reservationId);
            if ($reservation) {
                $this->createApprovalNotifications($reservation, $officeId, $officeId, $pfOfficeId);
            }
        }
    }

    private function resolveWorkflowOfficeIds(int $reservationId, bool $includePf): array
    {
        $ids = $this->getOfficeIdsByShortCode();
        $actionSequence = $this->getActionSequenceOfficeIds();

        if (empty($actionSequence)) {
            return [];
        }

        $pfOfficeId = $ids['PF'] ?? $this->getPhysicalFacilitiesOfficeId();
        $ownerOfficeId = $this->resolveOwnerOfficeId($reservationId, $ids, $pfOfficeId);
        $templatePcOfficeId = $ids['PC'] ?? null;
        $pcOfficeId = $this->batchPcOfficeLookup[$reservationId]
            ?? ProgramChairOfficeResolver::resolveForReservation($reservationId)
            ?? $templatePcOfficeId;
        if (!is_null($templatePcOfficeId) && !is_null($pcOfficeId)) {
            $actionSequence = ProgramChairOfficeResolver::replaceTemplatePcInSequence(
                $actionSequence,
                (int) $templatePcOfficeId,
                (int) $pcOfficeId,
            );
        }
        $genEdOfficeId = $ids['GENED'] ?? null;
        $startOfficeId = $ownerOfficeId;

        if ($this->isGymRoomRequest($reservationId) && !is_null($genEdOfficeId)) {
            $gymOwnerOfficeId = (!is_null($ownerOfficeId) && (is_null($pfOfficeId) || (int) $ownerOfficeId !== (int) $pfOfficeId))
                ? (int) $ownerOfficeId
                : null;

            if ($this->isGymRoomRequestWithItems($reservationId) && !is_null($gymOwnerOfficeId)) {
                $actionSequence = array_values(array_filter([
                    $genEdOfficeId,
                    $gymOwnerOfficeId,
                    $pcOfficeId,
                    $ids['SDAO'] ?? null,
                    $ids['DO'] ?? null,
                    $ids['SEC'] ?? null,
                ]));
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

        $legacyProgramChairOfficeId = ProgramChairOfficeResolver::defaultTemplateOfficeId();
        if (!is_null($legacyProgramChairOfficeId)) {
            $ids['PC'] = $legacyProgramChairOfficeId;
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
            ->select(['owners.owner_name', 'owners.department_affiliation', 'owners.user_id'])
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
            if (ItemOwnerService::isUserLinkedOwner($row)) {
                return $ioOfficeId ?? $fallbackOfficeId;
            }

            $affiliation = strtolower(trim((string) ($row->department_affiliation ?? '')));
            $ownerName = strtolower(trim((string) ($row->owner_name ?? '')));

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

    private function prepareReservationWorkflowHandoff(int $reservationId): void
    {
        $this->warmBatchWorkflowLookups([$reservationId]);
        ProgramChairOfficeResolver::reconcilePendingLegacyPcApproval($reservationId);

        $workflowOfficeIds = $this->resolveWorkflowOfficeIds($reservationId, true);
        if ($workflowOfficeIds !== []) {
            ReservationApprovalWorkflowService::ensureApprovalRows($reservationId, $workflowOfficeIds);
        }

        $this->fixNullApprovalStatuses($reservationId);
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

        $this->prepareReservationWorkflowHandoff((int) $reservationId);

        $nextActionableOfficeId = $this->getCurrentActionableOfficeId($reservationId);
        $pfOfficeId = $this->getOfficeIdsByShortCode()['PF'] ?? $this->getPhysicalFacilitiesOfficeId();

        if (!is_null($fromApprovingOfficeId)
            && (is_null($nextActionableOfficeId) || (int) $fromApprovingOfficeId !== (int) $nextActionableOfficeId)
        ) {
            ReservationApprovalNotifier::dismissOfficeApprovalNotifications(
                (int) $fromApprovingOfficeId,
                (int) $reservationId,
            );
        }

        if (is_null($nextActionableOfficeId)) {
            return;
        }

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

    public function getNotifications(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();

        $this->pruneStaleApprovalNotificationsForUser($user);

        if ($request->boolean('sync')) {
            $this->maybeSyncApprovalNotificationsForUser($user, $request->boolean('force'));
        }

        // Cheap insert-if-missing so the bell is not empty while requests are waiting.
        // Runs after optional heavy sync so wiped-but-still-actionable rows come back.
        $this->seedApprovalNotificationsForUser($user);

        $limit = min(max((int) $request->query('limit', 20), 1), 50);
        $userId = (int) $user->user_id;

        $notificationRows = Notification::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('notification_id')
            ->limit($limit)
            ->get([
                'notification_id',
                'type',
                'title',
                'message',
                'related_id',
                'read',
                'created_at',
            ]);

        $relatedIds = $notificationRows
            ->pluck('related_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $reservationMeta = $relatedIds === []
            ? collect()
            : Reservation::query()
                ->whereIn('reservation_id', $relatedIds)
                ->get()
                ->keyBy('reservation_id');

        $notifications = $notificationRows
            ->map(function ($notification) {
                return [
                    'id' => $notification->notification_id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'related_id' => $notification->related_id,
                    'read' => (bool) $notification->read,
                    'created_at' => $this->formatNotificationTime($notification->created_at),
                    'created_at_iso' => optional($notification->created_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        // #region agent log
        $orderDebug = $notificationRows->take(8)->values()->map(function ($notification) use ($reservationMeta) {
            $createdAt = $notification->created_at;
            $relatedId = (int) ($notification->related_id ?? 0);
            $reservation = $relatedId > 0 ? $reservationMeta->get($relatedId) : null;
            $activityAt = $reservation
                ? ($reservation->Start_of_activity ?? $reservation->start_of_activity ?? $reservation->Date_of_Activity ?? $reservation->date_of_activity)
                : null;

            return [
                'id' => (int) $notification->notification_id,
                'type' => (string) $notification->type,
                'read' => (bool) $notification->read,
                'related_id' => $relatedId ?: null,
                'created_at' => optional($createdAt)?->toIso8601String(),
                'age_days' => $createdAt ? (int) now()->diffInDays($createdAt) : null,
                'status' => $reservation ? (string) $reservation->overall_status : null,
                'reservation_age_days' => ($reservation && $reservation->created_at)
                    ? (int) now()->diffInDays($reservation->created_at)
                    : null,
                'activity_past' => $activityAt ? \Carbon\Carbon::parse($activityAt)->lt(now()) : null,
            ];
        })->all();

        $debugPayload = [
            'count' => count($notifications),
            'first_age_days' => $orderDebug[0]['age_days'] ?? null,
            'first_activity_past' => $orderDebug[0]['activity_past'] ?? null,
            'oldest_in_page_days' => collect($orderDebug)->max('age_days'),
            'order' => $orderDebug,
        ];

        try {
            file_put_contents(base_path('debug-e19b10.log'), json_encode([
                'sessionId' => 'e19b10',
                'timestamp' => (int) round(microtime(true) * 1000),
                'location' => 'ApprovalController.php:getNotifications',
                'message' => 'notification list order',
                'data' => $debugPayload,
                'hypothesisId' => 'A',
                'runId' => 'post-fix',
            ], JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
        }
        // #endregion

        $unreadCount = $this->cachedUnreadNotificationCount($userId);

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            '_debug' => $debugPayload,
        ]);
    }

    public function getNotificationUnreadCount()
    {
        $user = Auth::user();
        $this->pruneStaleApprovalNotificationsForUser($user);

        return response()->json([
            'success' => true,
            'unread_count' => $this->cachedUnreadNotificationCount((int) $user->user_id),
        ]);
    }

    private function cachedUnreadNotificationCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $cacheKey = 'notification_unread_count.user.' . $userId;

        return (int) Cache::remember($cacheKey, now()->addSeconds(15), function () use ($userId) {
            return Notification::where('user_id', $userId)
                ->unread()
                ->count();
        });
    }

    private function seedApprovalNotificationsForUser(\App\Models\User $user): void
    {
        if (!$user->isOfficeApprover()) {
            return;
        }

        $userId = (int) $user->user_id;
        if ($userId <= 0) {
            return;
        }

        $seedCacheKey = 'notification_seed.user.' . $userId;
        $inboxIsEmpty = !Notification::where('user_id', $userId)->exists();
        if (!$inboxIsEmpty && Cache::has($seedCacheKey)) {
            return;
        }

        try {
            $actionableReservationIds = $this->getActionableReservationIdsForApprover($user);
            if ($actionableReservationIds === []) {
                return;
            }

            ReservationApprovalNotifier::ensureUnreadForUser(
                $user,
                $actionableReservationIds,
                self::NOTIFICATION_SYNC_BATCH_LIMIT
            );
            Cache::put($seedCacheKey, true, now()->addSeconds(20));
            Cache::forget('notification_unread_count.user.' . $userId);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    private function maybeSyncApprovalNotificationsForUser(\App\Models\User $user, bool $force = false): void
    {
        if (!$user->isOfficeApprover()) {
            return;
        }

        $syncCacheKey = 'approval_notification_sync.user.' . (int) $user->user_id;

        if (!$force && Cache::has($syncCacheKey)) {
            return;
        }

        try {
            \App\Support\HeavySyncGate::attempt('notification-sync.user.' . (int) $user->user_id, function () use ($user, $syncCacheKey) {
                if ($user->isProgramChairAdmin()) {
                    $reconcileIds = ProgramChairOfficeResolver::reservationIdsWithPendingPcApprovalsForProgram(
                        (int) $user->office_id,
                        self::NOTIFICATION_SYNC_BATCH_LIMIT
                    );
                    if ($reconcileIds !== []) {
                        ProgramChairOfficeResolver::reconcileOpenReservationPcApprovals($reconcileIds);
                    }
                }

                $actionableReservationIds = $this->getActionableReservationIdsForApprover($user);
                $this->syncApprovalNotificationsForUser($user, $actionableReservationIds);

                // Keep sync rare — approvals already create notifications on handoff.
                Cache::put($syncCacheKey, true, now()->addMinutes(15));
                Cache::forget('notification_unread_count.user.' . (int) $user->user_id);
            });
        } catch (Throwable $throwable) {
            report($throwable);
        }
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

        if ($user->isPhysicalFacilitiesAdmin()) {
            $pfOfficeId = $this->getPhysicalFacilitiesOfficeId() ?? $officeId;
            $pendingFinalIds = $this->pendingFinalReservationIdsForPhysicalFacilities(
                (int) $pfOfficeId,
                self::NOTIFICATION_SYNC_BATCH_LIMIT
            );

            $candidateReservationIds = array_values(array_unique(array_merge(
                $pendingFinalIds,
                $this->recentPendingReservationIdsForOffice(
                    (int) $pfOfficeId,
                    self::NOTIFICATION_SYNC_BATCH_LIMIT
                )
            )));
            $candidateReservationIds = array_slice($candidateReservationIds, 0, self::NOTIFICATION_SYNC_BATCH_LIMIT);

            if ($candidateReservationIds === []) {
                return $pendingFinalIds;
            }

            $openCandidates = Reservation::query()->whereIn('reservation_id', $candidateReservationIds);
            \App\Support\OpenReservationScope::apply($openCandidates);
            $candidateReservationIds = $openCandidates
                ->pluck('reservation_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $actionableOfficeIds = $this->getActionableOfficeIdsForReservations($candidateReservationIds);
            $actionableReservationIds = $pendingFinalIds;
            foreach ($actionableOfficeIds as $reservationId => $actionableOfficeId) {
                if ((int) $actionableOfficeId === (int) $pfOfficeId) {
                    $actionableReservationIds[] = (int) $reservationId;
                }
            }

            return array_values(array_unique($actionableReservationIds));
        }

        $candidateReservationIds = $this->recentPendingReservationIdsForOffice(
            $officeId,
            self::NOTIFICATION_SYNC_BATCH_LIMIT
        );

        if (ItemOwnerService::isItemOwnerUser($user)) {
            $ownerReservationIds = array_slice(
                ItemOwnerService::openReservationIdsForItemOwner((int) $user->user_id, self::NOTIFICATION_SYNC_BATCH_LIMIT),
                0,
                self::NOTIFICATION_SYNC_BATCH_LIMIT
            );
            $candidateReservationIds = array_values(array_unique(array_merge(
                $candidateReservationIds,
                $ownerReservationIds
            )));
            $candidateReservationIds = array_slice($candidateReservationIds, 0, self::NOTIFICATION_SYNC_BATCH_LIMIT);
        } elseif ($user->isProgramChairAdmin()) {
            $programReservationIds = array_slice(
                ProgramChairOfficeResolver::openReservationIdsForProgramOffice($officeId),
                0,
                self::NOTIFICATION_SYNC_BATCH_LIMIT
            );

            $reconcileIds = ProgramChairOfficeResolver::reservationIdsWithPendingPcApprovalsForProgram(
                $officeId,
                self::NOTIFICATION_SYNC_BATCH_LIMIT
            );
            if ($reconcileIds !== []) {
                ProgramChairOfficeResolver::reconcileOpenReservationPcApprovals($reconcileIds);
            }

            $candidateReservationIds = array_values(array_unique(array_merge(
                $candidateReservationIds,
                $programReservationIds
            )));
            $candidateReservationIds = array_slice($candidateReservationIds, 0, self::NOTIFICATION_SYNC_BATCH_LIMIT);
        }

        if (empty($candidateReservationIds)) {
            return [];
        }

        // Exclude closed reservations.
        $openCandidates = Reservation::query()->whereIn('reservation_id', $candidateReservationIds);
        \App\Support\OpenReservationScope::apply($openCandidates);
        $candidateReservationIds = $openCandidates
            ->pluck('reservation_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($candidateReservationIds)) {
            return [];
        }

        $actionableOfficeIds = $this->getActionableOfficeIdsForReservations($candidateReservationIds);

        $actionableReservationIds = [];
        foreach ($actionableOfficeIds as $reservationId => $actionableOfficeId) {
            if ((int) $actionableOfficeId === $officeId) {
                $actionableReservationIds[] = (int) $reservationId;
            }
        }

        if (ItemOwnerService::isItemOwnerUser($user)) {
            return ItemOwnerService::filterActionableReservationIdsForItemOwner(
                $user,
                $actionableOfficeIds,
                $actionableReservationIds,
            );
        }

        return array_values(array_unique($actionableReservationIds));
    }

    /**
     * PF Home "Pending Final Approvals" plus open PF approval rows.
     * Uses TRIM/case-insensitive status so "Pending Approval" matches the dashboard count.
     *
     * @return array<int, int>
     */
    private function pendingFinalReservationIdsForPhysicalFacilities(int $pfOfficeId, int $limit): array
    {
        $awaitingIds = Reservation::query()
            ->whereRaw("LOWER(TRIM(COALESCE(overall_status, ''))) = ?", ['awaiting_physical_facilities'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('reservation_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $pendingApprovalIds = [];
        if ($pfOfficeId > 0) {
            $query = ReservationApproval::query()
                ->join('reservations', 'reservations.reservation_id', '=', 'reservation_approvals.reservation_id')
                ->where('reservation_approvals.office_id', $pfOfficeId)
                ->whereNull('reservation_approvals.approved_at')
                ->whereRaw("LOWER(TRIM(COALESCE(reservations.overall_status, ''))) IN (?, ?, ?)", [
                    'awaiting_physical_facilities',
                    'pending approval',
                    'pending_office_approvals',
                ]);

            \App\Support\OpenReservationScope::apply($query, 'reservations.overall_status');

            $pendingApprovalIds = $query
                ->orderByDesc('reservations.created_at')
                ->limit($limit)
                ->pluck('reservation_approvals.reservation_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return array_values(array_unique(array_slice(
            array_merge($awaitingIds, $pendingApprovalIds),
            0,
            $limit
        )));
    }

    private function formatNotificationTime(mixed $createdAt): string
    {
        if (!$createdAt) {
            return '';
        }

        try {
            $date = $createdAt instanceof \DateTimeInterface
                ? \Carbon\Carbon::parse($createdAt)
                : \Carbon\Carbon::parse((string) $createdAt);

            return $date->timezone('Asia/Manila')->format('M j, Y · g:i A');
        } catch (Throwable $throwable) {
            return '';
        }
    }

    /**
     * @return array<int, int>
     */
    private function recentPendingReservationIdsForOffice(int $officeId, int $limit): array
    {
        $query = ReservationApproval::query()
            ->join('reservations', 'reservations.reservation_id', '=', 'reservation_approvals.reservation_id')
            ->where('reservation_approvals.office_id', $officeId)
            ->whereNull('reservation_approvals.approved_at');

        \App\Support\OpenReservationScope::apply($query, 'reservations.overall_status');

        return $query
            ->orderByDesc('reservations.created_at')
            ->limit($limit)
            ->pluck('reservation_approvals.reservation_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Deterministic notification sync.
     * - Ensures one approval notification per actionable reservation for this user.
     * - Removes approval notifications only when the related reservation is closed.
     *   Never delete "not in this batch" rows — an incomplete candidate set was
     *   wiping the whole bell while Home still showed pending work.
     */
    private function syncApprovalNotificationsForUser(\App\Models\User $user, array $actionableReservationIds): void
    {
        $userId = (int) $user->user_id;
        $actionableReservationIds = array_values(array_unique(array_map('intval', $actionableReservationIds)));

        $types = ['reservation_approval_request', 'reservation_approval_handoff'];

        $this->pruneStaleApprovalNotificationsForUser($user);

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

            // Insert new notification as unread, stamped with the request time
            // so a late backfill of an old pending request cannot jump to the top.
            if ($reservation->isPastActivity()) {
                continue;
            }

            DB::table('notifications')->insert($key + [
                'title' => 'Reservation approval needed',
                'message' => "Request '{$activityName}' by {$requesterName} is waiting for your approval.",
                'read' => DB::raw('false'),
                'created_at' => $reservation->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Drop approval alerts that are no longer this user's job:
     * closed requests, and open requests already forwarded to another office.
     * Evaluates only notifications already in the inbox so an incomplete
     * "actionable set" cannot wipe unrelated alerts.
     */
    private function pruneStaleApprovalNotificationsForUser(\App\Models\User $user): void
    {
        if (!$user->isOfficeApprover()) {
            return;
        }

        $user->loadMissing('office');

        $userId = (int) $user->user_id;
        $officeId = (int) $user->office_id;
        $types = ['reservation_approval_request', 'reservation_approval_handoff'];

        $relatedIds = Notification::query()
            ->where('user_id', $userId)
            ->whereIn('type', $types)
            ->whereNotNull('related_id')
            ->pluck('related_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($relatedIds === []) {
            return;
        }

        $reservations = Reservation::query()
            ->whereIn('reservation_id', $relatedIds)
            ->get()
            ->keyBy('reservation_id');

        $closedStatuses = array_map('strval', \App\Support\OpenReservationScope::CLOSED_STATUSES);
        $openIds = [];
        $staleIds = [];

        foreach ($relatedIds as $reservationId) {
            $reservation = $reservations->get($reservationId);
            if (!$reservation) {
                $staleIds[] = $reservationId;
                continue;
            }

            $status = strtolower(trim((string) $reservation->overall_status));
            if (in_array($status, $closedStatuses, true) || str_starts_with($status, 'cancel') || $reservation->isPastActivity()) {
                $staleIds[] = $reservationId;
                continue;
            }

            $openIds[] = $reservationId;
        }

        if ($openIds !== []) {
            foreach ($openIds as $reservationId) {
                $reservation = $reservations->get($reservationId);
                if (!$reservation || !$reservation->created_at) {
                    continue;
                }

                // Backfilled seeds used now(), which pinned old requests to the top.
                Notification::query()
                    ->where('user_id', $userId)
                    ->where('related_id', $reservationId)
                    ->where('type', 'reservation_approval_request')
                    ->where('created_at', '>', $reservation->created_at)
                    ->update(['created_at' => $reservation->created_at]);
            }

            $actionableOfficeIds = $this->getActionableOfficeIdsForReservations($openIds);
            $isPfAdmin = $user->isPhysicalFacilitiesAdmin();
            $pfOfficeId = $isPfAdmin
                ? ($this->getPhysicalFacilitiesOfficeId() ?? $officeId)
                : null;
            $pendingFinalIds = [];

            if ($isPfAdmin) {
                $pendingFinalIds = array_fill_keys(
                    $this->pendingFinalReservationIdsForPhysicalFacilities((int) $pfOfficeId, self::NOTIFICATION_SYNC_BATCH_LIMIT),
                    true
                );
            }

            $isItemOwner = ItemOwnerService::isItemOwnerUser($user);

            foreach ($openIds as $reservationId) {
                $currentOfficeId = isset($actionableOfficeIds[$reservationId])
                    ? (int) $actionableOfficeIds[$reservationId]
                    : null;
                $keep = false;

                if ($isPfAdmin) {
                    $status = strtolower(trim((string) ($reservations->get($reservationId)->overall_status ?? '')));
                    $keep = ($currentOfficeId !== null && $currentOfficeId === (int) $pfOfficeId)
                        || $status === 'awaiting_physical_facilities'
                        || isset($pendingFinalIds[$reservationId]);
                } elseif ($isItemOwner) {
                    $keep = $currentOfficeId !== null
                        && $currentOfficeId === $officeId
                        && ItemOwnerService::itemOwnerHasPendingApproval($user, $reservationId);
                } else {
                    $keep = $currentOfficeId !== null && $currentOfficeId === $officeId;
                }

                if (!$keep) {
                    $staleIds[] = $reservationId;
                }
            }
        }

        $staleIds = array_values(array_unique(array_filter($staleIds)));
        if ($staleIds === []) {
            return;
        }

        Notification::query()
            ->where('user_id', $userId)
            ->whereIn('type', $types)
            ->whereIn('related_id', $staleIds)
            ->delete();

        Cache::forget('notification_unread_count.user.' . $userId);
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
                if ($reservation->isPastActivity()) {
                    continue;
                }

                $requesterName = $reservation->user->full_name ?? $reservation->user->username ?? 'Unknown';
                $activityName = trim((string) $reservation->activity_name) ?: 'Reservation request';

                Notification::insertUnread([
                    'user_id' => $userId,
                    'type' => 'reservation_approval_request',
                    'title' => 'Reservation approval needed',
                    'message' => "Request '{$activityName}' by {$requesterName} is waiting for your approval.",
                    'related_id' => $reservation->reservation_id,
                    'created_at' => $reservation->created_at ?? now(),
                ]);
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        }
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

        Cache::forget('notification_unread_count.user.' . (int) $user->user_id);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'unread_count' => $this->cachedUnreadNotificationCount((int) $user->user_id),
        ]);
    }

    private function ensurePendingApprovalNotifications(\App\Models\User $user): void
    {
        $officeId = (int) $user->office_id;
        if ($officeId <= 0) {
            return;
        }

        $pendingApprovalsQuery = ReservationApproval::query()
            ->join('reservations', 'reservations.reservation_id', '=', 'reservation_approvals.reservation_id')
            ->where('reservation_approvals.office_id', $officeId)
            // Match dashboard pending queue logic: actionable rows are not yet acted on.
            ->whereNull('reservation_approvals.approved_at');

        \App\Support\OpenReservationScope::apply($pendingApprovalsQuery, 'reservations.overall_status');

        $pendingApprovals = $pendingApprovalsQuery
            ->pluck('reservation_approvals.reservation_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
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
                if ($reservation->isPastActivity()) {
                    continue;
                }

                $requesterName = $reservation->user->full_name ?? $reservation->user->username ?? 'Unknown';
                $activityName = trim((string) $reservation->activity_name) ?: 'Reservation request';

                Notification::insertUnread([
                    'user_id' => $userId,
                    'type' => 'reservation_approval_request',
                    'title' => 'Reservation approval needed',
                    'message' => "Request '{$activityName}' by {$requesterName} is waiting for your approval.",
                    'related_id' => $reservation->reservation_id,
                    'created_at' => $reservation->created_at ?? now(),
                ]);
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        }
    }

    private function clearApprovalNotificationsForUser(int $reservationId, int $userId): void
    {
        if ($reservationId <= 0 || $userId <= 0) {
            return;
        }

        Notification::query()
            ->where('user_id', $userId)
            ->where('related_id', $reservationId)
            ->whereIn('type', ['reservation_approval_request', 'reservation_approval_handoff'])
            ->delete();
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

        $query = Notification::query()
            ->where('related_id', $reservationId)
            ->whereIn('type', ['reservation_approval_request', 'reservation_approval_handoff']);

        $affectedUserIds = (clone $query)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $query->delete();

        // The badge count is cached per user, so deleting the rows is not enough.
        foreach ($affectedUserIds as $userId) {
            Cache::forget('notification_unread_count.user.' . $userId);
        }
    }

    private function getApproverUserIdsForOffice(int $officeId): array
    {
        if ($officeId <= 0) {
            return [];
        }

        return DB::table('users')
            ->where('office_id', $officeId)
            ->whereRaw('LOWER(role) IN (?, ?, ?)', ['admin', 'pc_admin', 'pf_admin'])
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function getReservationDetails($reservationId)
    {
        $user = Auth::user();

        if (!$user->isOfficeApprover()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this reservation.',
            ], 403);
        }

        $reservation = Reservation::with(['user'])->findOrFail($reservationId);

        $requester = $reservation->user;
        $eventAt = $reservation->Start_of_activity
            ?? $reservation->start_of_activity
            ?? $reservation->Date_of_Activity
            ?? $reservation->date_of_activity;

        $eventEndAt = $reservation->End_of_Activity
            ?? $reservation->end_of_activity
            ?? null;

        if ($eventEndAt && !($eventEndAt instanceof \DateTimeInterface)) {
            try {
                $eventEndAt = \Carbon\Carbon::parse($eventEndAt);
            } catch (Throwable) {
                $eventEndAt = null;
            }
        }

        $proofOfConsentUrl = trim((string) ($reservation->proof_of_consent_url ?? ''));
        if ($proofOfConsentUrl !== '' && !preg_match('#^https?://#i', $proofOfConsentUrl)) {
            $proofOfConsentUrl = '';
        }

        $resourceRows = DB::table('reservation_details as details')
            ->leftJoin('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
            ->leftJoin('rooms as rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
            ->leftJoin('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->leftJoin('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->where('details.reservation_id', $reservationId)
            ->select([
                'details.quantity',
                'rooms.room_number',
                'items.item_name',
            ])
            ->get();

        $resources = [];
        $seenResourceKeys = [];

        foreach ($resourceRows as $row) {
            $quantity = max(1, (int) ($row->quantity ?? 1));
            $isRoom = !is_null($row->room_number) && trim((string) $row->room_number) !== '';

            if ($isRoom) {
                $label = 'Room ' . trim((string) $row->room_number);
                $type = 'room';
            } else {
                $label = trim((string) ($row->item_name ?? '')) !== '' ? (string) $row->item_name : 'Resource';
                $type = 'item';
            }

            $key = $type . ':' . strtolower($label);
            if (isset($seenResourceKeys[$key])) {
                continue;
            }
            $seenResourceKeys[$key] = true;

            $resources[] = [
                'label' => $label,
                'quantity' => $quantity,
                'unit' => '',
                'type' => $type,
            ];
        }

        $reservationData = [
            'id' => (int) $reservation->reservation_id,
            'reservation_code' => 'NU-' . str_pad((string) $reservation->reservation_id, 6, '0', STR_PAD_LEFT),
            'activity_name' => (string) ($reservation->activity_name ?? 'Untitled Activity'),
            'requester' => $requester ? $requester->displayName() : 'Unknown',
            'requester_username' => (string) ($requester?->username ?? ''),
            'requester_email' => (string) ($requester?->email ?? 'N/A'),
            'requester_phone' => (string) ($requester?->phone_number ?? $requester?->contact_number ?? 'N/A'),
            'requested_date' => $reservation->created_at
                ? $reservation->created_at->format('M d, Y h:i A')
                : 'N/A',
            'event_date' => $eventAt ? $eventAt->format('M d, Y') : 'N/A',
            'event_time' => $eventAt ? $eventAt->format('g:i A') : 'N/A',
            'event_end_date' => $eventEndAt ? $eventEndAt->format('M d, Y') : ($eventAt ? $eventAt->format('M d, Y') : 'N/A'),
            'event_end_time' => $eventEndAt ? $eventEndAt->format('g:i A') : 'N/A',
            'event_schedule' => $this->formatReservationScheduleLabel($eventAt, $eventEndAt),
            'start_date' => $eventAt ? $eventAt->format('M d, Y') : 'N/A',
            'end_date' => $eventEndAt ? $eventEndAt->format('M d, Y') : ($eventAt ? $eventAt->format('M d, Y') : 'N/A'),
            'start_time' => $eventAt ? $eventAt->format('g:i A') : 'N/A',
            'end_time' => $eventEndAt ? $eventEndAt->format('g:i A') : 'N/A',
            'status' => (string) ($reservation->overall_status ?? $reservation->status ?? 'Unknown'),
            'proof_of_consent_url' => $proofOfConsentUrl,
            'resources' => $resources,
            'items' => array_values(array_filter($resources, fn ($resource) => ($resource['type'] ?? '') === 'item')),
            // Approvers only need primary student + request details — not the full trail.
            'approvals' => [],
        ];

        return response()->json([
            'success' => true,
            'reservation' => $reservationData,
        ]);
    }

    private function formatReservationScheduleLabel($eventAt, $eventEndAt): string
    {
        if (!$eventAt) {
            return 'N/A';
        }

        $startDate = $eventAt->format('M d, Y');
        $startTime = $eventAt->format('g:i A');

        if (!$eventEndAt) {
            return "{$startDate} · {$startTime}";
        }

        $endDate = $eventEndAt->format('M d, Y');
        $endTime = $eventEndAt->format('g:i A');

        if ($startDate === $endDate) {
            return "{$startDate} · {$startTime} – {$endTime}";
        }

        return "{$startDate} {$startTime} – {$endDate} {$endTime}";
    }

    /**
     * Cached Schema::hasTable() — avoids repeated information_schema queries per request.
     */
    private function tableExists(string $table): bool
    {
        if (isset(self::$tableExistsCache[$table])) {
            return self::$tableExistsCache[$table];
        }

        self::$tableExistsCache[$table] = (bool) Cache::remember(
            "schema.table.{$table}",
            now()->addHours(6),
            fn () => Schema::hasTable($table)
        );

        return self::$tableExistsCache[$table];
    }
}
