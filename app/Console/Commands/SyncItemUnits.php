<?php

namespace App\Console\Commands;

use App\Services\ItemUnitService;
use Illuminate\Console\Command;

class SyncItemUnits extends Command
{
    protected $signature = 'items:sync-units {--dry-run : Report changes without writing to the database}';

    protected $description = 'Backfill missing item_units rows for all existing items (create/update already syncs units automatically)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run only — no database changes will be made.');
        }

        $stats = ItemUnitService::backfillMissingUnitsForAllItems($dryRun);

        $this->info(sprintf(
            'Scanned %d item(s); %d item(s) need unit rows; %d unit row(s) would be created.',
            $stats['items_scanned'],
            $stats['items_updated'],
            $stats['units_created'],
        ));

        foreach ($stats['details'] as $detail) {
            $this->line(sprintf(
                '  • #%d %s — total %d, had %d unit(s), creating %d',
                $detail['item_id'],
                $detail['item_name'],
                $detail['quantity_total'],
                $detail['existing_units'],
                $detail['units_created'],
            ));
        }

        if (!$dryRun && $stats['items_updated'] > 0) {
            $this->info('Item units backfill complete.');
        }

        return self::SUCCESS;
    }
}
