<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PurgeExpiredAnnouncements extends Command
{
    protected $signature = 'announcements:purge-expired';

    protected $description = 'Announcements no longer expire; this command does not delete anything';

    public function handle(): int
    {
        $this->info('Announcement expiry is disabled. No announcements were deleted.');

        return self::SUCCESS;
    }
}
