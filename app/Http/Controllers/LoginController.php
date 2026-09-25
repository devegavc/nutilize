<?php

namespace App\Http\Controllers;

use App\Services\UserAccountStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    private const LOGIN_MAX_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 900;

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

        if (!Auth::attempt($credentials)) {
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
            UserAccountStatusService::recordLogin($user);
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
