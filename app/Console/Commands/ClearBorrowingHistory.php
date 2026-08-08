<?php

namespace App\Console\Commands;

use App\Services\DashboardInventoryCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearBorrowingHistory extends Command
{
    protected $signature = 'borrowing:clear-history {--dry-run : Show what would be cleared without changing data}';

    protected $description = 'Remove reservation/borrowing history and reset inventory counters without deleting items, rooms, or users';

    /** @var list<string> */
    private array $reservationTables = [
        'reservation_item_units',
        'reservation_approval_histories',
        'reservation_approvals',
        'reservation_details',
        'reservation_items',
        'reservation_rooms',
        'reservations',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run only — no changes will be made.');
        }

        $this->info('Borrowing history tables:');
        foreach ($this->reservationTables as $table) {
            $count = Schema::hasTable($table) ? DB::table($table)->count() : 0;
            $this->line("  {$table}: {$count}");
        }

        $reportCount = Schema::hasTable('reports') ? DB::table('reports')->count() : 0;
        $this->line("  reports: {$reportCount}");

        $notificationCount = DB::table('notifications')
            ->whereIn('type', ['reservation_approval_request', 'reservation_approval_handoff'])
            ->count();
        $this->line("  reservation notifications: {$notificationCount}");

        $itemsInUse = DB::table('items')->where('quantity_in_use', '>', 0)->count();
        $this->line("  items with quantity_in_use > 0: {$itemsInUse}");

        if ($dryRun) {
            return self::SUCCESS;
        }

        DB::transaction(function () {
            foreach ($this->reservationTables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            if (Schema::hasTable('schedule_import_details')) {
                DB::table('schedule_import_details')->update(['reservation_id' => null]);
            }

            if (Schema::hasTable('reports')) {
                DB::table('reports')->delete();
            }

            DB::table('notifications')
                ->whereIn('type', ['reservation_approval_request', 'reservation_approval_handoff'])
                ->delete();

            DB::table('items')->update([
                'quantity_in_use' => 0,
                'date_reserved' => null,
            ]);

            if (Schema::hasTable('item_units')) {
                DB::table('item_units')
                    ->where('status', 'in_use')
                    ->update(['status' => 'available']);
            }

            if (Schema::hasTable('rooms')) {
                DB::table('rooms')->update(['date_reserved' => null]);
            }
        });

        DashboardInventoryCacheService::clearCache();

        $this->newLine();
        $this->info('Borrowing history cleared and inventory counters reset.');
        $this->line('Kept: users, items, item units, rooms, offices, programs, and other core data.');

        return self::SUCCESS;
    }
}
