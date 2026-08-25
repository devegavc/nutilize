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

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
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

        // #region agent log
        $redirectRoute = 'index';
        @file_put_contents(base_path('.cursor/debug-e19b10.log'), json_encode([
            'sessionId' => 'e19b10',
            'runId' => 'post-fix',
            'hypothesisId' => 'C,E',
            'location' => 'LoginController.php:logout',
            'message' => 'logout redirect target',
            'data' => [
                'redirectRoute' => $redirectRoute,
                'indexRouteExists' => \Illuminate\Support\Facades\Route::has('index'),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ]) . PHP_EOL, FILE_APPEND);
        // #endregion

        return redirect()->route($redirectRoute);
    }
}
