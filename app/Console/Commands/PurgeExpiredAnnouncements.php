<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PurgeExpiredAnnouncements extends Command
{
    protected $signature = 'announcements:purge-expired';

    protected $description = 'Announcements no longer expire; this command does not delete anything';

    public function handle(): int
    {
        // #region agent log
        @file_put_contents(base_path('debug-09fa9e.log'), json_encode(['sessionId' => '09fa9e', 'runId' => 'post-fix', 'hypothesisId' => 'D', 'location' => 'PurgeExpiredAnnouncements.php:handle', 'message' => 'purge command skipped delete', 'data' => ['deleted' => 0], 'timestamp' => (int) round(microtime(true) * 1000)])."\n", FILE_APPEND);
        // #endregion

        $this->info('Announcement expiry is disabled. No announcements were deleted.');

        return self::SUCCESS;
    }
}
