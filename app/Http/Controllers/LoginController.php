<?php

namespace App\Http\Controllers;

use App\Services\UserAccountStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'login|'.Str::lower($credentials['username']);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withErrors([
                    'username' => 'Too many login attempts.',
                ])
                ->with('login_lock_seconds', $seconds)
                ->with('login_lock_label', self::formatLockDuration($seconds))
                ->onlyInput('username');
        }

        if (!Auth::attempt($credentials)) {
            RateLimiter::hit($throttleKey, 900);

            return back()->withErrors([
                'username' => 'Invalid username or password.',
            ])->onlyInput('username');
        }

        RateLimiter::clear($throttleKey);
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
