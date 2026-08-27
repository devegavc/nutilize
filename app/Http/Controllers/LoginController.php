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
