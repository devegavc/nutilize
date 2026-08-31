<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\DashboardCacheService;
use Illuminate\Support\Facades\Auth;

class DashboardHomeController extends Controller
{
    public function index()
    {
        // #region agent log
        $__dbgT0 = microtime(true);
        $__dbgLog = static function (string $hypothesisId, string $message, array $data = []) use ($__dbgT0): void {
            file_put_contents(base_path('debug-fa7298.log'), json_encode([
                'sessionId' => 'fa7298',
                'runId' => 'post-fix',
                'hypothesisId' => $hypothesisId,
                'location' => 'DashboardHomeController.php:index',
                'message' => $message,
                'data' => array_merge([
                    'elapsed_ms' => (int) round((microtime(true) - $__dbgT0) * 1000),
                ], $data),
                'timestamp' => (int) round(microtime(true) * 1000),
            ])."\n", FILE_APPEND);
        };
        // #endregion

        $user = Auth::user();

        if (!$user || !$user->isPhysicalFacilitiesAdmin()) {
            return redirect('/dashboard/office/home')->with('error', 'Unauthorized access.');
        }

        // #region agent log
        $__dbgStep = microtime(true);
        // #endregion
        $data = DashboardCacheService::getDashboardData(
            (int) $user->user_id,
            (int) ($user->office_id ?? 0)
        );
        // #region agent log
        $__dbgLog('G', 'after getDashboardData', [
            'step_ms' => (int) round((microtime(true) - $__dbgStep) * 1000),
            'openAnnouncements' => (bool) session('open_announcements'),
        ]);
        $__dbgStep = microtime(true);
        // #endregion

        $announcementsTableReady = Announcement::tableReady();
        // #region agent log
        $__dbgLog('F,G', 'after Schema::hasTable announcements', [
            'step_ms' => (int) round((microtime(true) - $__dbgStep) * 1000),
            'ready' => $announcementsTableReady,
        ]);
        // #endregion
        $announcements = collect();

        if ($announcementsTableReady) {
            // #region agent log
            $__dbgStep = microtime(true);
            // #endregion
            Announcement::purgeExpired();
            // #region agent log
            $__dbgLog('F,G', 'after purgeExpired on home', [
                'step_ms' => (int) round((microtime(true) - $__dbgStep) * 1000),
            ]);
            $__dbgStep = microtime(true);
            // #endregion

            $query = Announcement::query()
                ->with('author:user_id,first_name,middle_initial,last_name,suffix,full_name,username')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->limit(30);

            if (Announcement::hasAnnouncementsColumn('expires_at')) {
                $query->active();
            }

            $announcements = $query->get();
            // #region agent log
            $__dbgLog('F,G', 'after announcements query', [
                'step_ms' => (int) round((microtime(true) - $__dbgStep) * 1000),
                'count' => $announcements->count(),
                'total_ms' => (int) round((microtime(true) - $__dbgT0) * 1000),
            ]);
            // #endregion
        }

        return view('dashboard-home', array_merge($data, [
            'announcements' => $announcements,
            'announcementsTableReady' => $announcementsTableReady,
            'announcementTtlDays' => Announcement::DEFAULT_TTL_DAYS,
            'openAnnouncementsModal' => (bool) (
                session('open_announcements')
                || request()->boolean('announcements')
                || old('title') !== null
                || old('body') !== null
                || old('announcer_name') !== null
            ),
        ]));
    }
}
