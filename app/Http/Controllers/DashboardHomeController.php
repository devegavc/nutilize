<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\DashboardCacheService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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

        $announcementsTableReady = Schema::hasTable('announcements');
        $announcements = collect();

        if ($announcementsTableReady) {
            Announcement::purgeExpired();

            $query = Announcement::query()
                ->with('author:user_id,full_name,username')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->limit(30);

            if (Schema::hasColumn('announcements', 'expires_at')) {
                $query->active();
            }

            $announcements = $query->get();
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
