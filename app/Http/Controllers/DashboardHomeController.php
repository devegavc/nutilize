<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\DashboardCacheService;
use Illuminate\Support\Facades\Auth;

class DashboardHomeController extends Controller
{
    public function index()
    {

        $user = Auth::user();

        if (!$user || !$user->isPhysicalFacilitiesAdmin()) {
            return redirect('/dashboard/office/home')->with('error', 'Unauthorized access.');
        }

        $data = DashboardCacheService::getDashboardData(
            (int) $user->user_id,
            (int) ($user->office_id ?? 0)
        );

        $announcementsTableReady = Announcement::tableReady();
        $announcements = collect();

        if ($announcementsTableReady) {
            // purgeExpired is already rate-limited; keep announcement query small.
            Announcement::purgeExpired();

            $query = Announcement::query()
                ->with('author:user_id,first_name,middle_initial,last_name,suffix,full_name,username')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->limit(12);

            if (Announcement::hasAnnouncementsColumn('expires_at')) {
                $query->active();
            }

            $announcements = $query->get();
        }

        $suffix = trim((string) ($user->suffix ?? ''));
        $announcementAnnouncerDefault = trim((string) old('announcer_name', 'Physical Facilities Admin'));
        if ($announcementAnnouncerDefault === '' || preg_match('/not set/i', $announcementAnnouncerDefault)) {
            $announcementAnnouncerDefault = 'Physical Facilities Admin';
        }

        // #region agent log
        $debugLog = base_path('.cursor/debug-61468c.log');
        @file_put_contents($debugLog, json_encode([
            'sessionId' => '61468c',
            'runId' => 'post-fix',
            'hypothesisId' => 'F',
            'location' => 'DashboardHomeController.php:announcer',
            'message' => 'announcer default computed',
            'data' => [
                'suffixIsPlaceholder' => strcasecmp($suffix, 'Not Set') === 0,
                'hasMiddleInitial' => trim((string) ($user->middle_initial ?? '')) !== '',
                'displayNameHasNotSet' => str_contains($user->displayName(), 'Not Set'),
                'defaultIsPfAdmin' => $announcementAnnouncerDefault === 'Physical Facilities Admin',
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ])."\n", FILE_APPEND);
        // #endregion

        return response()
            ->view('dashboard-home', array_merge($data, [
                'announcements' => $announcements,
                'announcementsTableReady' => $announcementsTableReady,
                'announcementTtlDays' => Announcement::DEFAULT_TTL_DAYS,
                'announcementAnnouncerDefault' => $announcementAnnouncerDefault,
                'openAnnouncementsModal' => (bool) (
                    session('open_announcements')
                    || request()->boolean('announcements')
                    || old('title') !== null
                    || old('body') !== null
                    || old('announcer_name') !== null
                ),
            ]))
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate');
    }
}
