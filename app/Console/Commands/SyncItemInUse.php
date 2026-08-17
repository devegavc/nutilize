<?php

namespace App\Console\Commands;

use App\Services\ItemUnitService;
use Illuminate\Console\Command;

class SyncItemInUse extends Command
{
    protected $signature = 'items:sync-in-use';

    protected $description = 'Mark item units in_use only while their approved reservation is happening today';

    public function handle(): int
    {
        $counts = ItemUnitService::reconcileLiveBorrowedUnits();
        $inUse = array_sum($counts);

        $this->info(sprintf(
            'Reconciled %d item(s); %d unit(s) currently in use today.',
            count($counts),
            $inUse,
        ));

        return self::SUCCESS;
    }
}
