<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProgramChairOfficeResolver
{
    /** @var array<int, int|null> */
    private static array $resolvedPcOfficeByReservation = [];

    /**
     * Program-chair office for this reservation (from the requester's program).
     * Returns null when the user has no program — callers should keep the legacy Program Chair office.
     */
    public static function resolveForReservation(int $reservationId): ?int
    {
        if (array_key_exists($reservationId, self::$resolvedPcOfficeByReservation)) {
            return self::$resolvedPcOfficeByReservation[$reservationId];
        }

        $resolved = self::batchResolveForReservations([$reservationId]);

        return $resolved[$reservationId] ?? null;
    }

    /**
     * @param  array<int, int>  $reservationIds
     * @return array<int, int> reservation_id => program pc office_id
     */
    public static function batchResolveForReservations(array $reservationIds): array
    {
        $reservationIds = array_values(array_unique(array_filter(array_map('intval', $reservationIds))));
        if ($reservationIds === []) {
            return [];
        }

        $missingIds = array_values(array_filter(
            $reservationIds,
            static fn (int $reservationId): bool => !array_key_exists($reservationId, self::$resolvedPcOfficeByReservation)
        ));

        if (
            $missingIds !== []
            && Schema::hasTable('academic_programs')
            && Schema::hasColumn('users', 'program_id')
        ) {
            $fetched = DB::table('reservations')
                ->join('users', 'users.user_id', '=', 'reservations.user_id')
                ->join('academic_programs', 'academic_programs.program_id', '=', 'users.program_id')
                ->whereIn('reservations.reservation_id', $missingIds)
                ->whereNotNull('users.program_id')
                ->whereNotNull('academic_programs.office_id')
                ->select(['reservations.reservation_id', 'academic_programs.office_id'])
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->reservation_id => (int) $row->office_id])
                ->all();

            foreach ($missingIds as $reservationId) {
                self::$resolvedPcOfficeByReservation[$reservationId] = $fetched[$reservationId] ?? null;
            }
        } elseif ($missingIds !== []) {
            foreach ($missingIds as $reservationId) {
                self::$resolvedPcOfficeByReservation[$reservationId] = null;
            }
        }

        $map = [];
        foreach ($reservationIds as $reservationId) {
            $officeId = self::$resolvedPcOfficeByReservation[$reservationId] ?? null;
            if (!is_null($officeId)) {
                $map[$reservationId] = $officeId;
            }
        }

        return $map;
    }

    /**
     * Open program reservations that still have a pending program-chair approval row (any PC office slot).
     *
     * @return array<int, int>
     */
    public static function reservationIdsWithPendingPcApprovalsForProgram(int $programPcOfficeId, int $limit = 60): array
    {
        if (
            $programPcOfficeId <= 0
            || !Schema::hasTable('academic_programs')
            || !Schema::hasColumn('users', 'program_id')
        ) {
            return [];
        }

        $pcOfficeIds = DB::table('offices')
            ->whereRaw('LOWER(TRIM(short_code)) = ?', ['pc'])
            ->pluck('office_id')
            ->map(fn ($officeId) => (int) $officeId)
            ->all();

        if ($pcOfficeIds === []) {
            return [];
        }

        $query = DB::table('reservation_approvals as ra')
            ->join('reservations as r', 'r.reservation_id', '=', 'ra.reservation_id')
            ->join('users as u', 'u.user_id', '=', 'r.user_id')
            ->join('academic_programs as ap', 'ap.program_id', '=', 'u.program_id')
            ->where('ap.office_id', $programPcOfficeId)
            ->whereIn('ra.office_id', $pcOfficeIds)
            ->whereNull('ra.approved_at');

        \App\Support\OpenReservationScope::apply($query, 'r.overall_status');

        return $query
            ->orderByDesc('r.created_at')
            ->limit($limit)
            ->pluck('ra.reservation_id')
            ->map(fn ($reservationId) => (int) $reservationId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Template PC slot in the default workflow (legacy single "Program Chair" office).
     */
    public static function defaultTemplateOfficeId(): ?int
    {
        $officeId = DB::table('offices')
            ->whereRaw('LOWER(TRIM(short_code)) = ?', ['pc'])
            ->whereRaw('LOWER(TRIM(department_name)) = ?', ['program chair'])
            ->value('office_id');

        return $officeId ? (int) $officeId : null;
    }

    /**
     * Ensure a pending program-chair step matches the requester's program.
     * Fixes legacy "Program Chair" and any other wrong program-specific PC office.
     */
    public static function reconcilePendingLegacyPcApproval(int $reservationId): bool
    {
        $resolvedPcOfficeId = self::resolveForReservation($reservationId);

        if (is_null($resolvedPcOfficeId)) {
            return false;
        }

        $pcOfficeIds = DB::table('offices')
            ->whereRaw('LOWER(TRIM(short_code)) = ?', ['pc'])
            ->pluck('office_id')
            ->map(fn ($officeId) => (int) $officeId)
            ->all();

        if ($pcOfficeIds === []) {
            return false;
        }

        $pendingPcApprovals = DB::table('reservation_approvals')
            ->where('reservation_id', $reservationId)
            ->whereIn('office_id', $pcOfficeIds)
            ->whereNull('approved_at')
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhereRaw("LOWER(COALESCE(status, '')) = 'pending'");
            })
            ->get(['approval_id', 'office_id']);

        if ($pendingPcApprovals->isEmpty()) {
            return false;
        }

        $correctExists = $pendingPcApprovals->contains(
            fn ($row) => (int) $row->office_id === $resolvedPcOfficeId
        );

        $wrongApprovals = $pendingPcApprovals->filter(
            fn ($row) => (int) $row->office_id !== $resolvedPcOfficeId
        );

        $changed = false;

        foreach ($wrongApprovals as $wrongApproval) {
            if ($correctExists) {
                DB::table('reservation_approvals')
                    ->where('approval_id', $wrongApproval->approval_id)
                    ->delete();
            } else {
                $targetAlreadyExists = DB::table('reservation_approvals')
                    ->where('reservation_id', $reservationId)
                    ->where('office_id', $resolvedPcOfficeId)
                    ->where('approval_id', '!=', $wrongApproval->approval_id)
                    ->exists();

                if ($targetAlreadyExists) {
                    DB::table('reservation_approvals')
                        ->where('approval_id', $wrongApproval->approval_id)
                        ->delete();
                } else {
                    DB::table('reservation_approvals')
                        ->where('approval_id', $wrongApproval->approval_id)
                        ->update([
                            'office_id' => $resolvedPcOfficeId,
                            'updated_at' => now(),
                        ]);
                    $correctExists = true;
                }
            }

            $changed = true;
        }

        $deduped = ReservationApprovalDeduper::deduplicatePendingForReservations([$reservationId]);

        return $changed || $deduped > 0;
    }

    /**
     * Fix misrouted program-chair approval rows for many open reservations.
     *
     * @param  array<int, int>  $reservationIds
     */
    public static function reconcileOpenReservationPcApprovals(array $reservationIds): int
    {
        $changed = 0;

        foreach (array_values(array_unique(array_map('intval', $reservationIds))) as $reservationId) {
            if ($reservationId <= 0) {
                continue;
            }

            if (self::reconcilePendingLegacyPcApproval($reservationId)) {
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Open reservations submitted by students belonging to a program-chair office.
     *
     * @return array<int, int>
     */
    public static function openReservationIdsForProgramOffice(int $programPcOfficeId): array
    {
        if ($programPcOfficeId <= 0 || !Schema::hasTable('academic_programs') || !Schema::hasColumn('users', 'program_id')) {
            return [];
        }

        $query = DB::table('reservations as r')
            ->join('users as u', 'u.user_id', '=', 'r.user_id')
            ->join('academic_programs as ap', 'ap.program_id', '=', 'u.program_id')
            ->where('ap.office_id', $programPcOfficeId);

        \App\Support\OpenReservationScope::apply($query, 'r.overall_status');

        return $query
            ->orderByDesc('r.created_at')
            ->limit(80)
            ->pluck('r.reservation_id')
            ->map(fn ($reservationId) => (int) $reservationId)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public static function reconcilePendingLegacyPcApprovalsForUser(int $userId): array
    {
        if (!Schema::hasTable('reservation_approvals') || !Schema::hasTable('reservations')) {
            return [];
        }

        $reservationIds = DB::table('reservations')
            ->where('user_id', $userId)
            ->whereNotIn(DB::raw("LOWER(COALESCE(overall_status, ''))"), ['approved', 'rejected', 'cancelled', 'canceled', 'expired'])
            ->whereRaw("LOWER(COALESCE(overall_status, '')) NOT LIKE ?", ['cancel%'])
            ->pluck('reservation_id')
            ->map(fn ($reservationId) => (int) $reservationId)
            ->all();

        $updatedReservationIds = [];

        foreach ($reservationIds as $reservationId) {
            if (self::reconcilePendingLegacyPcApproval($reservationId)) {
                $updatedReservationIds[] = $reservationId;
            }
        }

        return $updatedReservationIds;
    }

    /**
     * @param  array<int, int>  $officeIds
     * @return array<int, int>
     */
    public static function replaceTemplatePcInSequence(array $officeIds, int $templatePcOfficeId, int $resolvedPcOfficeId): array
    {
        if ($templatePcOfficeId === $resolvedPcOfficeId) {
            return array_values(array_map('intval', $officeIds));
        }

        $normalizedOfficeIds = array_values(array_map('intval', $officeIds));

        if (in_array($templatePcOfficeId, $normalizedOfficeIds, true)) {
            return array_values(array_map(
                fn (int $officeId): int => $officeId === $templatePcOfficeId ? $resolvedPcOfficeId : $officeId,
                $normalizedOfficeIds
            ));
        }

        if (in_array($resolvedPcOfficeId, $normalizedOfficeIds, true)) {
            return $normalizedOfficeIds;
        }

        if ($normalizedOfficeIds === []) {
            return [$resolvedPcOfficeId];
        }

        return array_values(array_merge(
            array_slice($normalizedOfficeIds, 0, 1),
            [$resolvedPcOfficeId],
            array_slice($normalizedOfficeIds, 1)
        ));
    }
}
