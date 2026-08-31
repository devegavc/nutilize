<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardAnnouncementController extends Controller
{
    public function index(): RedirectResponse
    {
        $user = Auth::user();

        if (!$user || !$user->isPhysicalFacilitiesAdmin()) {
            return redirect('/dashboard/office/home')->with('error', 'Unauthorized access.');
        }

        return redirect()
            ->route('dashboard.home')
            ->with('open_announcements', true);
    }

    public function store(Request $request): RedirectResponse
    {

        $user = Auth::user();

        if (!$user || !$user->isPhysicalFacilitiesAdmin()) {
            return redirect('/dashboard/office/home')->with('error', 'Unauthorized access.');
        }

        if (!Announcement::tableReady()) {
            return redirect()
                ->route('dashboard.home')
                ->with('open_announcements', true)
                ->with('error', 'Announcements are not ready yet. Please run migrations.');
        }

        $validated = $request->validate([
            'announcer_name' => ['required', 'string', 'max:180'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        try {
            Announcement::purgeExpired();

            $now = now();
            $announcerName = trim($validated['announcer_name']);
            if ($announcerName === '') {
                $announcerName = trim((string) $user->displayName());
            }
            if ($announcerName === '' || strcasecmp($announcerName, 'Unknown') === 0) {
                $announcerName = trim((string) ($user->username ?? '')) ?: 'Physical Facilities staff';
            }

            $payload = [
                'created_by' => (int) $user->user_id,
                'title' => trim($validated['title']),
                'body' => trim($validated['body']),
                // Supabase/pgbouncer with emulated prepares binds PHP true as integer 1.
                'is_active' => DB::raw('TRUE'),
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Announcement::hasAnnouncementsColumn('announcer_name')) {
                $payload['announcer_name'] = $announcerName;
            }

            if (Announcement::hasAnnouncementsColumn('expires_at')) {
                $payload['expires_at'] = $now->copy()->addDays(Announcement::DEFAULT_TTL_DAYS);
            }

            DB::table('announcements')->insert($payload);
        } catch (Throwable $throwable) {
            report($throwable);

            return redirect()
                ->route('dashboard.home')
                ->with('open_announcements', true)
                ->withInput()
                ->with('error', 'Could not publish the announcement. Please try again.');
        }

        return redirect()
            ->route('dashboard.home')
            ->with('open_announcements', true)
            ->with('success', 'Announcement published.');
    }

    public function update(Request $request, int $announcementId): RedirectResponse
    {
        $user = Auth::user();

        if (!$user || !$user->isPhysicalFacilitiesAdmin()) {
            return redirect('/dashboard/office/home')->with('error', 'Unauthorized access.');
        }

        if (!Announcement::tableReady()) {
            return redirect()
                ->route('dashboard.home')
                ->with('open_announcements', true)
                ->with('error', 'Announcements are not ready yet.');
        }

        $validated = $request->validate([
            'announcer_name' => ['required', 'string', 'max:180'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $announcement = Announcement::query()->where('announcement_id', $announcementId)->first();

        if (!$announcement) {
            return redirect()
                ->route('dashboard.home')
                ->with('open_announcements', true)
                ->with('error', 'That announcement could not be found.');
        }

        try {
            $announcerName = trim($validated['announcer_name']);
            if ($announcerName === '') {
                $announcerName = trim((string) $user->displayName());
            }
            if ($announcerName === '' || strcasecmp($announcerName, 'Unknown') === 0) {
                $announcerName = trim((string) ($user->username ?? '')) ?: 'Physical Facilities staff';
            }

            $payload = [
                'title' => trim($validated['title']),
                'body' => trim($validated['body']),
                'updated_at' => now(),
            ];

            if (Announcement::hasAnnouncementsColumn('announcer_name')) {
                $payload['announcer_name'] = $announcerName;
            }

            DB::table('announcements')
                ->where('announcement_id', $announcementId)
                ->update($payload);
        } catch (Throwable $throwable) {
            report($throwable);

            return redirect()
                ->route('dashboard.home')
                ->with('open_announcements', true)
                ->withInput()
                ->with('error', 'Could not update the announcement. Please try again.');
        }

        return redirect()
            ->route('dashboard.home')
            ->with('open_announcements', true)
            ->with('success', 'Announcement updated.');
    }

    public function destroy(int $announcementId): RedirectResponse
    {
        $user = Auth::user();

        if (!$user || !$user->isPhysicalFacilitiesAdmin()) {
            return redirect('/dashboard/office/home')->with('error', 'Unauthorized access.');
        }

        if (!Announcement::tableReady()) {
            return redirect()
                ->route('dashboard.home')
                ->with('open_announcements', true)
                ->with('error', 'Announcements are not ready yet.');
        }

        Announcement::query()
            ->where('announcement_id', $announcementId)
            ->delete();

        return redirect()
            ->route('dashboard.home')
            ->with('open_announcements', true)
            ->with('success', 'Announcement removed.');
    }
}
