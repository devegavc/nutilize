<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountSetupDeliveryException;
use App\Services\AccountSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountSetupController extends Controller
{
    public function __construct(private AccountSetupService $accountSetup) {}

    public function create(string $token): View
    {
        return view('account-setup', $this->accountSetup->pageData($token));
    }

    public function complete(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('account_setup_complete') !== true) {
            return redirect()->route('login');
        }

        return view('account-setup', [
            'state' => 'complete',
            'token' => null,
            'username' => null,
        ]);
    }

    public function store(Request $request, string $token): View|RedirectResponse
    {
        $page = $this->accountSetup->pageData($token);
        if ($page['state'] !== 'form') {
            return view('account-setup', $page);
        }

        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], [
            'password.required' => 'Enter a password.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('account.setup', ['token' => $token])
                ->withErrors($validator);
        }

        try {
            $this->accountSetup->complete($token, $validator->validated()['password']);
        } catch (AccountSetupDeliveryException $exception) {
            if (in_array($exception->getMessage(), [
                AccountSetupService::TOKEN_INVALID,
                AccountSetupService::TOKEN_EXPIRED,
                AccountSetupService::TOKEN_USED,
            ], true)) {
                return view('account-setup', [
                    'state' => $exception->getMessage(),
                    'token' => null,
                    'username' => null,
                ]);
            }

            return redirect()
                ->route('account.setup', ['token' => $token])
                ->withErrors(['password' => 'The password could not be saved. Please try again.']);
        }

        return redirect()
            ->route('account.setup.complete')
            ->with('account_setup_complete', true);
    }
}
