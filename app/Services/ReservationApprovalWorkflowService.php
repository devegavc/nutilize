<?php

namespace App\Services;

use App\Models\ReservationApproval;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReservationApprovalWorkflowService
{
    public static function supportsOwnerScopedApprovals(): bool
    {
        return Schema::hasTable('reservation_approvals')
            && Schema::hasColumn('reservation_approvals', 'owner_id');
    }

    /**
     * Retire the outstanding approval rows of a reservation that has reached a terminal
     * state without being decided office by office (the requester cancelled it, or it
     * expired).
     *
     * Approver queues treat `approved_at IS NULL` as "still waiting on this office", so
     * leaving those rows open is what makes a cancelled request keep appearing in the
     * queue. The rows are retired rather than deleted so the audit trail survives, and
     * the status written is neither `approved` nor `rejected`, so decision counters and
     * the office archive continue to ignore them.
     *
     * @param  array<int, int>|int  $reservationIds
     * @return int rows retired
     */
    public static function closePendingApprovals(array|int $reservationIds, string $status): int
    {
        $reservationIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $reservationIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($reservationIds === []) {
            return 0;
        }

        return DB::table('reservation_approvals')
            ->whereIn('reservation_id', $reservationIds)
            ->whereNull('approved_at')
            ->update([
                'status' => $status,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<int, int>  $workflowOfficeIds
     */
    public static function ensureApprovalRows(int $reservationId, array $workflowOfficeIds): void
    {
        if ($workflowOfficeIds === []) {
            return;
        }

        $existing = ReservationApproval::query()
            ->where('reservation_id', $reservationId)
            ->get(['approval_id', 'reservation_id', 'office_id', 'owner_id', 'status', 'approved_at']);

        $rows = self::buildMissingApprovalRows($reservationId, $workflowOfficeIds, $existing);

        if ($rows !== []) {
            DB::table('reservation_approvals')->insert($rows);
        }

        self::reconcileItemOwnerApprovals($reservationId);
    }

    /**
     * Stamp owner_id on IO approvals and remove legacy duplicate rows so owners are not asked twice.
     */
    public static function reconcileItemOwnerApprovals(int $reservationId): void
    {
        if (!self::supportsOwnerScopedApprovals()) {
            return;
        }

        $ioOfficeId = ItemOwnerService::itemOwnerOfficeId();
        if (!$ioOfficeId) {
            return;
        }

        $ioRows = ReservationApproval::query()
            ->where('reservation_id', $reservationId)
            ->where('office_id', (int) $ioOfficeId)
            ->get(['approval_id', 'owner_id', 'status', 'approved_at', 'approved_by_user_id']);

        if ($ioRows->isEmpty()) {
            return;
        }

        foreach ($ioRows as $row) {
            if (!is_null($row->owner_id) || is_null($row->approved_by_user_id)) {
                continue;
            }

            $status = strtolower((string) ($row->status ?? 'pending'));
            if ($status !== 'approved' || is_null($row->approved_at)) {
                continue;
            }

            $ownerId = ItemOwnerService::ownerIdForUser((int) $row->approved_by_user_id);
            if (!$ownerId) {
                continue;
            }

            DB::table('reservation_approvals')
                ->where('approval_id', (int) $row->approval_id)
                ->update([
                    'owner_id' => $ownerId,
                    'updated_at' => now(),
                ]);

            $row->owner_id = $ownerId;
        }

        $ioRows = ReservationApproval::query()
            ->where('reservation_id', $reservationId)
            ->where('office_id', (int) $ioOfficeId)
            ->get(['approval_id', 'owner_id', 'status', 'approved_at']);

        $scopedRows = $ioRows->filter(fn ($row) => !is_null($row->owner_id));
        $legacyRows = $ioRows->filter(fn ($row) => is_null($row->owner_id));

        if ($scopedRows->isEmpty() || $legacyRows->isEmpty()) {
            return;
        }

        $legacyPendingIds = $legacyRows
            ->filter(function ($row) {
                if (!is_null($row->approved_at)) {
                    return false;
                }

                $status = strtolower((string) ($row->status ?? 'pending'));

                return in_array($status, ['pending', ''], true);
            })
            ->pluck('approval_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($legacyPendingIds !== []) {
            DB::table('reservation_approvals')->whereIn('approval_id', $legacyPendingIds)->delete();
        }
    }

    /**
     * @param  array<int, int>  $workflowOfficeIds
     * @param  Collection<int, ReservationApproval>  $existing
     * @return array<int, array<string, mixed>>
     */
    public static function buildMissingApprovalRows(int $reservationId, array $workflowOfficeIds, Collection $existing): array
    {
        $now = now();
        $ioOfficeId = ItemOwnerService::itemOwnerOfficeId();
        $rows = [];

        foreach ($workflowOfficeIds as $officeId) {
            $officeId = (int) $officeId;
            if ($officeId <= 0) {
                continue;
            }

            if (
                self::supportsOwnerScopedApprovals()
                && $ioOfficeId
                && $officeId === (int) $ioOfficeId
            ) {
                $ownerIds = ItemOwnerService::distinctUserLinkedOwnerIdsForReservation($reservationId);

                foreach ($ownerIds as $ownerId) {
                    if (
                        self::approvalExists($existing, $officeId, $ownerId)
                        || self::ownerHasFinalizedIoApproval($existing, $officeId, $ownerId)
                    ) {
                        continue;
                    }

                    $rows[] = [
                        'reservation_id' => $reservationId,
                        'office_id' => $officeId,
                        'owner_id' => $ownerId,
                        'approved_by_user_id' => null,
                        'status' => 'pending',
                        'approved_at' => null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ];
                }

                continue;
            }

            if (self::approvalExists($existing, $officeId, null)) {
                continue;
            }

            $row = [
                'reservation_id' => $reservationId,
                'office_id' => $officeId,
                'approved_by_user_id' => null,
                'status' => 'pending',
                'approved_at' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ];

            if (self::supportsOwnerScopedApprovals()) {
                $row['owner_id'] = null;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public static function reconcileLegacyIoRows(int $reservationId, int $ioOfficeId): void
    {
        self::reconcileItemOwnerApprovals($reservationId);
    }

    /**
     * @param  Collection<int, ReservationApproval|object>  $existing
     */
    public static function ownerHasFinalizedIoApproval(Collection $existing, int $ioOfficeId, int $ownerId): bool
    {
        return $existing->contains(function ($row) use ($ioOfficeId, $ownerId) {
            if ((int) $row->office_id !== $ioOfficeId) {
                return false;
            }

            $status = strtolower((string) ($row->status ?? 'pending'));
            if ($status !== 'approved' || is_null($row->approved_at)) {
                return false;
            }

            $rowOwnerId = is_null($row->owner_id) ? null : (int) $row->owner_id;

            return $rowOwnerId === $ownerId;
        });
    }

    /**
     * @param  Collection<int, ReservationApproval|object>  $existing
     */
    public static function approvalExists(Collection $existing, int $officeId, ?int $ownerId): bool
    {
        return $existing->contains(function ($row) use ($officeId, $ownerId) {
            if ((int) $row->office_id !== $officeId) {
                return false;
            }

            if (!self::supportsOwnerScopedApprovals()) {
                return true;
            }

            $rowOwnerId = is_null($row->owner_id) ? null : (int) $row->owner_id;

            return $rowOwnerId === $ownerId;
        });
    }

    /**
     * Collapse multiple approval rows for the same office into one effective row.
     * IO offices require every owner-scoped row to be approved before the step is complete.
     *
     * @param  Collection<int, ReservationApproval|object>  $approvals
     * @return Collection<int, object> keyed by office_id
     */
    public static function collapseByOfficeId(Collection $approvals, ?int $ioOfficeId = null): Collection
    {
        $ioOfficeId ??= ItemOwnerService::itemOwnerOfficeId();

        return $approvals
            ->groupBy(fn ($row) => (int) $row->office_id)
            ->mapWithKeys(function (Collection $rows, int $officeId) use ($ioOfficeId) {
                $hasOwnerScopedRows = $rows->contains(fn ($row) => !is_null($row->owner_id));

                if ($ioOfficeId && $officeId === (int) $ioOfficeId && $hasOwnerScopedRows) {
                    return [$officeId => self::collapseIoOwnerRows($rows)];
                }

                $approved = $rows->first(function ($row) {
                    $status = strtolower((string) ($row->status ?? 'pending'));

                    return $status === 'approved' && !is_null($row->approved_at);
                });

                if ($approved) {
                    return [$officeId => $approved];
                }

                return [$officeId => $rows->sortBy(fn ($row) => (int) ($row->approval_id ?? 0))->first()];
            });
    }

    /**
     * @param  Collection<int, ReservationApproval|object>  $rows
     */
    private static function collapseIoOwnerRows(Collection $rows): object
    {
        $rejected = $rows->first(function ($row) {
            $status = strtolower((string) ($row->status ?? 'pending'));

            return $status === 'rejected' && !is_null($row->approved_at);
        });

        if ($rejected) {
            return $rejected;
        }

        $scopedRows = $rows->filter(fn ($row) => !is_null($row->owner_id));

        if ($scopedRows->isEmpty()) {
            return $rows->sortBy(fn ($row) => (int) ($row->approval_id ?? 0))->first();
        }

        $allApproved = $scopedRows->every(function ($row) {
            $status = strtolower((string) ($row->status ?? 'pending'));

            return $status === 'approved' && !is_null($row->approved_at);
        });

        if ($allApproved) {
            return $scopedRows->sortBy(fn ($row) => (int) ($row->approval_id ?? 0))->first();
        }

        $pending = $scopedRows->first(function ($row) {
            $status = strtolower((string) ($row->status ?? 'pending'));

            return $status !== 'approved' || is_null($row->approved_at);
        });

        return $pending ?? $scopedRows->first();
    }

    public static function userCanActOnApproval(User $user, ReservationApproval $approval): bool
    {
        if (!ItemOwnerService::itemOwnerCanActOnReservation($user, (int) $approval->reservation_id)) {
            return false;
        }

        if (!ItemOwnerService::isItemOwnerUser($user) || !self::supportsOwnerScopedApprovals()) {
            return true;
        }

        $ownerId = ItemOwnerService::ownerIdForUser((int) $user->user_id);
        if (!$ownerId) {
            return false;
        }

        $ioOfficeId = ItemOwnerService::itemOwnerOfficeId();
        if ($ioOfficeId && (int) $approval->office_id === (int) $ioOfficeId) {
            $existing = ReservationApproval::query()
                ->where('reservation_id', (int) $approval->reservation_id)
                ->where('office_id', (int) $ioOfficeId)
                ->get(['office_id', 'owner_id', 'status', 'approved_at']);

            if (self::ownerHasFinalizedIoApproval($existing, (int) $ioOfficeId, $ownerId)) {
                return false;
            }
        }

        $approvalOwnerId = is_null($approval->owner_id) ? null : (int) $approval->owner_id;

        if (is_null($approvalOwnerId) || $approvalOwnerId <= 0) {
            return true;
        }

        return $ownerId === $approvalOwnerId;
    }

    public static function isIoStepComplete(int $reservationId, ?int $ioOfficeId = null): bool
    {
        $ioOfficeId ??= ItemOwnerService::itemOwnerOfficeId();
        if (!$ioOfficeId) {
            return true;
        }

        $rows = ReservationApproval::query()
            ->where('reservation_id', $reservationId)
            ->where('office_id', (int) $ioOfficeId)
            ->get(['owner_id', 'status', 'approved_at']);

        if ($rows->isEmpty()) {
            return false;
        }

        $collapsed = self::collapseByOfficeId($rows, (int) $ioOfficeId)->get((int) $ioOfficeId);
        if (!$collapsed) {
            return false;
        }

        $status = strtolower((string) ($collapsed->status ?? 'pending'));

        return $status === 'approved' && !is_null($collapsed->approved_at);
    }
}
