<?php

namespace App\Http\Controllers;

use App\Models\RegistrationAllowance;
use App\Services\RegistrationAllowanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RegistrationAllowanceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:100'],
        ]);

        $normalized = RegistrationAllowanceService::normalizeEmail($data['email']);
        $existed = RegistrationAllowance::query()->where('email', $normalized)->exists();

        try {
            RegistrationAllowanceService::addEmail($normalized, Auth::id() ? (int) Auth::id() : null);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'email' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('dashboard.users', $this->listQuery($request))
            ->with('success', $existed
                ? 'That email is already on the registration list.'
                : 'Email added to the registration list.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:2048'],
        ]);

        $extension = strtolower((string) $request->file('file')->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            throw ValidationException::withMessages([
                'file' => 'Upload a CSV or Excel file.',
            ]);
        }

        try {
            $result = RegistrationAllowanceService::importFile(
                $request->file('file'),
                Auth::id() ? (int) Auth::id() : null
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        if ($result['added'] === 0 && $result['already_listed'] === 0 && $result['invalid'] === 0) {
            throw ValidationException::withMessages([
                'file' => 'No email addresses were found in the Email column.',
            ]);
        }

        $message = RegistrationAllowanceService::importMessage($result);
        $redirect = redirect()
            ->route('dashboard.users', $this->listQuery($request));

        if ($result['added'] === 0 && $result['already_listed'] === 0) {
            return $redirect->with('error', $message);
        }

        return $redirect->with('success', $message);
    }

    public function destroy(Request $request, RegistrationAllowance $allowance): RedirectResponse
    {
        try {
            RegistrationAllowanceService::removeUnused($allowance);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('dashboard.users', $this->listQuery($request))
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('dashboard.users', $this->listQuery($request))
            ->with('success', 'Email removed from the registration list.');
    }

    /**
     * @return array<string, string>
     */
    private function listQuery(Request $request): array
    {
        $query = [];
        $search = trim((string) $request->input('allowance_q', ''));
        $page = (int) $request->input('allowance_page', 0);
        if ($search !== '') {
            $query['allowance_q'] = $search;
        }
        if ($page > 1) {
            $query['allowance_page'] = (string) $page;
        }

        return $query;
    }
}
