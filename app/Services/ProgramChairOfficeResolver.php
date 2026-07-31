<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProgramChairOfficeResolver
{
    /**
     * Program-chair office for this reservation (from the requester's program).
     * Returns null when the user has no program — callers should keep the legacy Program Chair office.
     */
    public static function resolveForReservation(int $reservationId): ?int
    {
        if (!Schema::hasTable('academic_programs') || !Schema::hasColumn('users', 'program_id')) {
            return null;
        }

        $officeId = DB::table('reservations')
            ->join('users', 'users.user_id', '=', 'reservations.user_id')
            ->join('academic_programs', 'academic_programs.program_id', '=', 'users.program_id')
            ->where('reservations.reservation_id', $reservationId)
            ->whereNotNull('users.program_id')
            ->whereNotNull('academic_programs.office_id')
            ->value('academic_programs.office_id');

        return $officeId ? (int) $officeId : null;
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

        if ($wrongApprovals->isEmpty()) {
            return false;
        }

        $changed = false;

        foreach ($wrongApprovals as $wrongApproval) {
            if ($correctExists) {
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

            $changed = true;
        }

        return $changed;
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
