<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReservationApprovalDeduper
{
    /**
     * Remove duplicate pending approval rows that share the same reservation, office, and owner.
     * Keeps the oldest row (lowest approval_id).
     *
     * @param  array<int, int>  $reservationIds
     */
    public static function deduplicatePendingForReservations(array $reservationIds): int
    {
        if (!Schema::hasTable('reservation_approvals') || $reservationIds === []) {
            return 0;
        }

        $reservationIds = array_values(array_unique(array_map('intval', $reservationIds)));
        $deleted = 0;
        $supportsOwnerScope = ReservationApprovalWorkflowService::supportsOwnerScopedApprovals();

        $duplicateGroups = DB::table('reservation_approvals')
            ->select(['reservation_id', 'office_id'])
            ->when($supportsOwnerScope, fn ($query) => $query->addSelect('owner_id'))
            ->whereIn('reservation_id', $reservationIds)
            ->whereNull('approved_at')
            ->groupBy('reservation_id', 'office_id')
            ->when($supportsOwnerScope, fn ($query) => $query->groupBy('owner_id'))
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $query = DB::table('reservation_approvals')
                ->where('reservation_id', (int) $group->reservation_id)
                ->where('office_id', (int) $group->office_id)
                ->whereNull('approved_at');

            if ($supportsOwnerScope) {
                if (is_null($group->owner_id)) {
                    $query->whereNull('owner_id');
                } else {
                    $query->where('owner_id', (int) $group->owner_id);
                }
            }

            $approvalIds = $query
                ->orderBy('approval_id')
                ->pluck('approval_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            array_shift($approvalIds);

            if ($approvalIds === []) {
                continue;
            }

            DB::table('reservation_approvals')->whereIn('approval_id', $approvalIds)->delete();
            $deleted += count($approvalIds);
        }

        return $deleted;
    }

    /**
     * Collapse multiple approval rows for the same office into one effective row.
     * IO offices require every owner-scoped row to be approved before the step is complete.
     *
     * @param  Collection<int, object>  $approvals
     * @return Collection<int, object> keyed by office_id
     */
    public static function collapseByOfficeId(Collection $approvals): Collection
    {
        return ReservationApprovalWorkflowService::collapseByOfficeId($approvals);
    }
}
