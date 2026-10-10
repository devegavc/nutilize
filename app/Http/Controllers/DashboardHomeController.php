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
            return redirect('/office/home')->with('error', 'Unauthorized access.');
        }

        $data = DashboardCacheService::getDashboardData(
            (int) $user->user_id,
            (int) ($user->office_id ?? 0)
        );

        $announcementsTableReady = Announcement::tableReady();
        $announcements = collect();

        if ($announcementsTableReady) {
            $announcements = Announcement::query()
                ->with('author:user_id,first_name,middle_initial,last_name,suffix,full_name,username')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->limit(12)
                ->get();
        }

        $announcementAnnouncerDefault = trim((string) old('announcer_name', 'Physical Facilities Admin'));
        if ($announcementAnnouncerDefault === '' || preg_match('/not set/i', $announcementAnnouncerDefault)) {
            $announcementAnnouncerDefault = 'Physical Facilities Admin';
        }

        return response()
            ->view('dashboard-home', array_merge($data, [
                'announcements' => $announcements,
                'announcementsTableReady' => $announcementsTableReady,
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
