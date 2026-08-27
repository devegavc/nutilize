<?php

namespace App\Http\Controllers;

use App\Services\UserAccountStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials)) {
            return back()->withErrors([
                'username' => 'Invalid username or password.',
            ])->onlyInput('username');
        }

        $request->session()->regenerate();
        $user = $request->user();

        if ($user && UserAccountStatusService::isPastInactivityLimit($user)) {
            UserAccountStatusService::markInactive($user);

            // #region agent log
            file_put_contents(
                base_path('debug-e19b10.log'),
                json_encode([
                    'sessionId' => 'e19b10',
                    'runId' => 'feature-verify',
                    'hypothesisId' => 'C',
                    'location' => 'LoginController.php:authenticate',
                    'message' => 'login blocked by inactivity policy',
                    'data' => [
                        'userId' => $user->user_id,
                        'role' => $user->role,
                        'lastLoginAt' => optional($user->last_login_at)?->toIso8601String(),
                    ],
                    'timestamp' => (int) round(microtime(true) * 1000),
                ])."\n",
                FILE_APPEND
            );
            // #endregion

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'username' => 'This account was deactivated after '.UserAccountStatusService::INACTIVITY_WEEKS.' weeks of inactivity. Contact Physical Facilities to reactivate.',
            ])->onlyInput('username');
        }

        if ($user && !UserAccountStatusService::isActive($user)) {
            // #region agent log
            file_put_contents(
                base_path('debug-e19b10.log'),
                json_encode([
                    'sessionId' => 'e19b10',
                    'runId' => 'feature-verify',
                    'hypothesisId' => 'C',
                    'location' => 'LoginController.php:authenticate',
                    'message' => 'login blocked inactive account',
                    'data' => [
                        'userId' => $user->user_id,
                        'role' => $user->role,
                    ],
                    'timestamp' => (int) round(microtime(true) * 1000),
                ])."\n",
                FILE_APPEND
            );
            // #endregion

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'username' => 'This account is inactive. Contact Physical Facilities to reactivate.',
            ])->onlyInput('username');
        }

        if ($user) {
            UserAccountStatusService::recordLogin($user);

            // #region agent log
            file_put_contents(
                base_path('debug-e19b10.log'),
                json_encode([
                    'sessionId' => 'e19b10',
                    'runId' => 'feature-verify',
                    'hypothesisId' => 'C',
                    'location' => 'LoginController.php:authenticate',
                    'message' => 'login recorded',
                    'data' => [
                        'userId' => $user->user_id,
                        'isActive' => UserAccountStatusService::isActive($user),
                        'lastLoginAt' => optional($user->last_login_at)?->toIso8601String(),
                    ],
                    'timestamp' => (int) round(microtime(true) * 1000),
                ])."\n",
                FILE_APPEND
            );
            // #endregion
        }

        if ($user && (int) $user->user_id === 8) {
            return redirect()->route('office.home');
        }

        return redirect()->route('dashboard.home');
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
