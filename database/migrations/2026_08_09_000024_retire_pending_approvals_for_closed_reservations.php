<?php

use App\Support\OpenReservationScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill for requests that were cancelled (or expired) before the application started
 * retiring their approval rows. Those rows were left with `approved_at IS NULL`, which
 * every approver queue reads as "still waiting on this office", so the requests kept
 * appearing long after the requester had cancelled them.
 *
 * Rows are retired, never deleted, and the status written is neither `approved` nor
 * `rejected` so decision counters and the office archive keep ignoring them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reservation_approvals') || !Schema::hasTable('reservations')) {
            return;
        }

        $closedReservations = DB::table('reservations')
            ->where(function ($query) {
                $query
                    ->whereIn(
                        DB::raw("LOWER(COALESCE(overall_status, ''))"),
                        OpenReservationScope::CLOSED_STATUSES
                    )
                    ->orWhereRaw("LOWER(COALESCE(overall_status, '')) LIKE 'cancel%'");
            })
            ->pluck('overall_status', 'reservation_id');

        // Handled per status so an expired request is not recorded as cancelled.
        $idsByStatus = [];

        foreach ($closedReservations as $reservationId => $status) {
            $normalized = strtolower(trim((string) $status));

            if ($normalized === '' || !$this->isClosed($normalized)) {
                continue;
            }

            $idsByStatus[$this->retirementStatusFor($normalized)][] = (int) $reservationId;
        }

        foreach ($idsByStatus as $status => $reservationIds) {
            DB::table('reservation_approvals')
                ->whereIn('reservation_id', $reservationIds)
                ->whereNull('approved_at')
                ->update([
                    'status' => $status,
                    'approved_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Reopening these rows would put cancelled requests back into the approver
        // queues, which is the bug this migration exists to clear.
    }

    private function isClosed(string $normalized): bool
    {
        return in_array($normalized, OpenReservationScope::CLOSED_STATUSES, true)
            || str_starts_with($normalized, 'cancel');
    }

    private function retirementStatusFor(string $normalized): string
    {
        return match (true) {
            str_starts_with($normalized, 'cancel') => 'cancelled',
            $normalized === 'expired' => 'expired',
            default => 'closed',
        };
    }
};
