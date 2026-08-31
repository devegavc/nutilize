<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        // #region agent log
        $__dbgT0 = microtime(true);
        $__dbgLog = static function (string $hypothesisId, string $message, array $data = []) use ($__dbgT0): void {
            file_put_contents(base_path('debug-fa7298.log'), json_encode([
                'sessionId' => 'fa7298',
                'runId' => 'pre-fix',
                'hypothesisId' => $hypothesisId,
                'location' => 'DashboardAnnouncementController.php:store',
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
        if (!Schema::hasTable('announcements')) {
            // #region agent log
            $__dbgLog('F', 'announcements table missing', [
                'step_ms' => (int) round((microtime(true) - $__dbgStep) * 1000),
            ]);
            // #endregion
            return redirect()
                ->route('dashboard.home')
                ->with('open_announcements', true)
                ->with('error', 'Announcements are not ready yet. Please run migrations.');
        }
        // #region agent log
        $__dbgLog('F', 'after Schema::hasTable', [
            'step_ms' => (int) round((microtime(true) - $__dbgStep) * 1000),
        ]);
        // #endregion

        $validated = $request->validate([
            'announcer_name' => ['required', 'string', 'max:180'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        try {
            // #region agent log
            $__dbgStep = microtime(true);
            // #endregion
            Announcement::purgeExpired();
            // #region agent log
            $__dbgLog('F', 'after purgeExpired', [
                'step_ms' => (int) round((microtime(true) - $__dbgStep) * 1000),
            ]);
            // #endregion

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

            // #region agent log
            $__dbgStep = microtime(true);
            // #endregion
            if (Schema::hasColumn('announcements', 'announcer_name')) {
                $payload['announcer_name'] = $announcerName;
            }

            if (Schema::hasColumn('announcements', 'expires_at')) {
                $payload['expires_at'] = $now->copy()->addDays(Announcement::DEFAULT_TTL_DAYS);
            }
            // #region agent log
            $__dbgLog('F', 'after Schema::hasColumn checks', [
                'step_ms' => (int) round((microtime(true) - $__dbgStep) * 1000),
            ]);
            $__dbgStep = microtime(true);
            // #endregion

            DB::table('announcements')->insert($payload);
            // #region agent log
            $__dbgLog('F', 'after insert; redirecting home', [
                'step_ms' => (int) round((microtime(true) - $__dbgStep) * 1000),
                'total_ms' => (int) round((microtime(true) - $__dbgT0) * 1000),
            ]);
            // #endregion
        } catch (Throwable $throwable) {
            // #region agent log
            $__dbgLog('F', 'store threw', [
                'error' => $throwable->getMessage(),
                'total_ms' => (int) round((microtime(true) - $__dbgT0) * 1000),
            ]);
            // #endregion
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

        if (!Schema::hasTable('announcements')) {
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

            if (Schema::hasColumn('announcements', 'announcer_name')) {
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

        if (!Schema::hasTable('announcements')) {
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
