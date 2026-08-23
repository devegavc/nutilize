<?php

namespace App\Http\Controllers;

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

        // #region agent log
        \App\Support\AgentDebugLog::write(
            'LoginController.php:authenticate',
            'login attempt start',
            \App\Support\AgentDebugLog::snapshot(null, $request),
            'C'
        );
        // #endregion

        try {
            $authenticated = Auth::attempt($credentials);
        } catch (\Throwable $throwable) {
            // #region agent log
            \App\Support\AgentDebugLog::write(
                'LoginController.php:authenticate',
                'login attempt threw',
                \App\Support\AgentDebugLog::snapshot($throwable, $request),
                'C'
            );
            // #endregion
            throw $throwable;
        }

        if ($authenticated) {
            try {
                $request->session()->regenerate();
            } catch (\Throwable $throwable) {
                // #region agent log
                \App\Support\AgentDebugLog::write(
                    'LoginController.php:authenticate',
                    'session regenerate threw',
                    \App\Support\AgentDebugLog::snapshot($throwable, $request),
                    'C'
                );
                // #endregion
                throw $throwable;
            }
            $user = $request->user();

            if ($user && (int) $user->user_id === 8) {
                return redirect()->route('office.home');
            }

            return redirect()->route('dashboard.home');
        }

        return back()->withErrors([
            'username' => 'Invalid username or password.',
        ])->onlyInput('username');
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

        return redirect()->route('login');
    }
}
