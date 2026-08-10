<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\ReservationApprovalWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';
    protected $description = 'Mark reservations as expired if the start_of_activity time has passed and they are not yet approved or rejected';

    public function handle()
    {
        $now = now();
        $this->info("Checking for expired reservations at {$now}...");

        $reservationIds = $this->expirableQuery()
            ->pluck('reservation_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($reservationIds === []) {
            $this->info('No reservations to expire.');

            return self::SUCCESS;
        }

        $updated = Reservation::query()
            ->whereIn('reservation_id', $reservationIds)
            ->update(['overall_status' => 'expired']);

        // Without this the outstanding approval rows stay pending and the expired
        // request keeps appearing in every approver queue it never reached.
        $retired = ReservationApprovalWorkflowService::closePendingApprovals($reservationIds, 'expired');

        $this->info("Successfully marked {$updated} reservation(s) as expired, retiring {$retired} pending approval row(s).");

        return self::SUCCESS;
    }

    private function expirableQuery()
    {
        return Reservation::query()
            ->where(function ($query) {
                $query->whereNotNull('Start_of_activity')
                    ->where('Start_of_activity', '<', now())
                    ->orWhere(function ($q) {
                        $q->whereNotNull('start_of_activity')
                            ->where('start_of_activity', '<', now());
                    });
            })
            ->whereNotIn(DB::raw("LOWER(COALESCE(overall_status, ''))"), [
                'approved',
                'rejected',
                'cancelled',
                'canceled',
                'expired',
            ])
            ->whereRaw("LOWER(COALESCE(overall_status, '')) NOT LIKE ?", ['cancel%']);
    }
}
