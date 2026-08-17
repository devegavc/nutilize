<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PurgeExpiredAnnouncements extends Command
{
    protected $signature = 'announcements:purge-expired';

    protected $description = 'Delete Physical Facilities announcements that have passed their expiry time';

    public function handle(): int
    {
        if (!Schema::hasTable('announcements')) {
            $this->info('Announcements table is not available.');

            return self::SUCCESS;
        }

        $deleted = Announcement::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info("Removed {$deleted} expired announcement(s).");

        return self::SUCCESS;
    }
}
