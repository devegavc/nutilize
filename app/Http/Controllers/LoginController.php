<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserAccountStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class LoginController extends Controller
{
    private const LOGIN_MAX_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 300;

    public function showPrivacyNotice(): View
    {
        return view('data-privacy');
    }

    public function acceptPrivacyNotice(Request $request): RedirectResponse
    {
        $request->validate([
            'privacy_agreement' => ['accepted'],
        ], [
            'privacy_agreement.accepted' => 'Check the box to agree to the NUtilize Data Privacy Notice before continuing.',
        ]);

        $request->session()->put('nutilize_privacy_accepted', true);

        return redirect()->route('login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);

        $throttleKeys = $this->loginThrottleKeys($request);

        if ($seconds = $this->loginLockSeconds($throttleKeys)) {
            return back()
                ->withErrors([
                    'username' => 'Too many login attempts.',
                ])
                ->with('login_lock_seconds', $seconds)
                ->with('login_lock_label', self::formatLockDuration($seconds))
                ->onlyInput('username');
        }

        $remember = $request->boolean('remember') && Schema::hasColumn('users', 'remember_token');

        if (!Auth::attempt($credentials, $remember)) {
            foreach ($throttleKeys as $throttleKey) {
                RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);
            }

            return back()->withErrors([
                'username' => 'Invalid username or password.',
            ])->onlyInput('username');
        }

        foreach ($throttleKeys as $throttleKey) {
            RateLimiter::clear($throttleKey);
        }
        $request->session()->regenerate();
        $user = $request->user();

        if ($user && UserAccountStatusService::isPastActiveWindow($user)) {
            UserAccountStatusService::markInactive($user);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'username' => 'This account was deactivated after '.UserAccountStatusService::INACTIVITY_WEEKS.' weeks. Contact Physical Facilities to reactivate.',
            ])->onlyInput('username');
        }

        if ($user && !UserAccountStatusService::isActive($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'username' => 'This account is inactive. Contact Physical Facilities to reactivate.',
            ])->onlyInput('username');
        }

        if ($user) {
            // #region agent log
            try {
                file_put_contents(base_path('debug-d19ccb.log'), json_encode([
                    'sessionId' => 'd19ccb',
                    'runId' => 'pre-fix',
                    'hypothesisId' => 'B',
                    'location' => 'LoginController.php:authenticate',
                    'message' => 'authenticated before recordLogin',
                    'data' => [
                        'user_id' => (int) $user->user_id,
                        'role' => (string) $user->role,
                        'status_changed_at_null' => $user->status_changed_at === null,
                        'is_active' => $user->is_active,
                    ],
                    'timestamp' => (int) round(microtime(true) * 1000),
                ], JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $exception) {
            }
            // #endregion
            UserAccountStatusService::recordLogin($user);
        }

        $redirect = self::redirectAuthenticated($user);
        // #region agent log
        try {
            file_put_contents(base_path('debug-28e049.log'), json_encode([
                'sessionId' => '28e049',
                'runId' => 'user-repro',
                'hypothesisId' => 'H2',
                'location' => 'LoginController.php:authenticate',
                'message' => 'login role decision',
                'data' => [
                    'user_id' => $user ? (int) $user->user_id : null,
                    'role' => $user ? (string) $user->role : null,
                    'office_id' => $user->office_id ?? null,
                    'is_office_approver' => $user ? $user->isOfficeApprover() : false,
                    'is_program_chair' => $user ? $user->isProgramChairAdmin() : false,
                    'canonical_role' => $user ? \App\Support\RoleCatalog::canonicalCode((string) $user->role) : null,
                    'target' => $redirect->getTargetUrl(),
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ], JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $exception) {
        }
        // #endregion
        // #region agent log
        try {
            file_put_contents(base_path('debug-d19ccb.log'), json_encode([
                'sessionId' => 'd19ccb',
                'runId' => 'pre-fix',
                'hypothesisId' => 'D',
                'location' => 'LoginController.php:authenticate',
                'message' => 'redirect chosen',
                'data' => [
                    'user_id' => $user ? (int) $user->user_id : null,
                    'target' => $redirect->getTargetUrl(),
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ], JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $exception) {
        }
        // #endregion

        return $redirect;
    }

    public static function redirectAuthenticated(?User $user): RedirectResponse
    {
        if ($user && $user->isPhysicalFacilitiesAdmin()) {
            return redirect()->route('dashboard.home');
        }

        if ($user && $user->isOfficeApprover()) {
            return redirect()->route('office.home');
        }

        if ($user && (int) $user->user_id === 8) {
            return redirect()->route('office.home');
        }

        return redirect()->route('dashboard.home');
    }

    /**
     * @return list<string>
     */
    private function loginThrottleKeys(Request $request): array
    {
        return [
            'login|browser|'.$request->session()->getId(),
        ];
    }

    /**
     * @param  list<string>  $throttleKeys
     */
    private function loginLockSeconds(array $throttleKeys): int
    {
        $seconds = 0;

        foreach ($throttleKeys as $throttleKey) {
            if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_MAX_ATTEMPTS)) {
                $seconds = max($seconds, RateLimiter::availableIn($throttleKey));
            }
        }

        return $seconds;
    }

    public static function formatLockDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $minutes = intdiv($seconds, 60);
        $remainder = $seconds % 60;
        $parts = [];

        if ($minutes > 0) {
            $parts[] = $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
        }

        if ($remainder > 0 || $minutes === 0) {
            $parts[] = $remainder.' '.($remainder === 1 ? 'second' : 'seconds');
        }

        return implode(' ', $parts);
    }

    public function logout(Request $request): RedirectResponse
    {
        try {
            Auth::logout();
        } catch (\Throwable $throwable) {
            report($throwable);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('index');
    }
}
