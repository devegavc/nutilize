<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Carbon\Carbon;
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

        // Find reservations where:
        // 1. Start_of_activity or start_of_activity has passed
        // 2. overall_status is NOT already approved, rejected, or cancelled
        $expiredCount = Reservation::query()
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
            ->whereRaw("LOWER(COALESCE(overall_status, '')) NOT LIKE ?", ['cancel%'])
            ->get()
            ->count();

        if ($expiredCount === 0) {
            $this->info('No reservations to expire.');
            return self::SUCCESS;
        }

        $updated = Reservation::query()
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
            ->whereRaw("LOWER(COALESCE(overall_status, '')) NOT LIKE ?", ['cancel%'])
            ->update(['overall_status' => 'expired']);

        $this->info("Successfully marked {$updated} reservation(s) as expired.");

        return self::SUCCESS;
    }
}
