<?php

namespace App\Http\Controllers;

use App\Models\RegistrationAllowance;
use App\Models\User;
use App\Services\RegistrationAllowanceService;
use App\Support\RoleCatalog;
use App\Support\SimpleXlsx;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RegistrationAllowanceController extends Controller
{
    public function exportRegistered()
    {
        $allowances = RegistrationAllowance::query()->orderBy('email')->get(['email']);
        $emails = $allowances->pluck('email')->all();
        $users = collect();
        if ($emails !== []) {
            $users = User::query()
                ->whereRaw('LOWER(email) IN ('.implode(', ', array_fill(0, count($emails), '?')).')', $emails)
                ->get()
                ->keyBy(fn (User $user): string => RegistrationAllowanceService::normalizeEmail((string) $user->email));
        }

        $rows = [['Email', 'Name', 'Username']];
        foreach ($allowances as $allowance) {
            $user = $users->get($allowance->email);
            if (!$user instanceof User) {
                continue;
            }
            $rows[] = [
                (string) $allowance->email,
                $user->displayName(),
                (string) $user->username,
            ];
        }

        $filename = 'registered-emails-'.now()->format('Y-m-d').'.xlsx';

        return response(SimpleXlsx::binary($rows), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:100'],
            'role' => ['required', 'string', Rule::in(RoleCatalog::assignableValues())],
        ]);

        $normalized = RegistrationAllowanceService::normalizeEmail($data['email']);
        $existed = RegistrationAllowance::query()->where('email', $normalized)->exists();
        $hasAccount = RegistrationAllowanceService::accountExists($normalized);

        try {
            RegistrationAllowanceService::addEmail(
                $normalized,
                Auth::id() ? (int) Auth::id() : null,
                $data['role']
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'role' => $exception->getMessage(),
            ]);
        }

        if ($existed && $hasAccount) {
            return $this->notice(
                $request,
                'used',
                'This email already has an account.',
                $normalized
            );
        }

        $roleLabel = $data['role'] === 'item_owner'
            ? 'Item Owner'
            : RoleCatalog::label(RegistrationAllowanceService::storedRole($data['role']));

        return $this->notice(
            $request,
            'added',
            $existed
                ? 'The role for that email was updated. It will be applied when they register.'
                : 'That person can now register as '.$roleLabel.'.',
            $normalized
        );
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
        $kind = 'added';
        if ($result['added'] === 0 && $result['already_listed'] > 0 && $result['invalid'] === 0) {
            $kind = 'used';
            $message = $result['already_listed'] === 1
                ? 'That email is already on the registration list.'
                : $result['already_listed'].' emails are already on the registration list.';
        } elseif ($result['added'] === 0) {
            $kind = 'error';
        }

        return $this->notice($request, $kind, $message);
    }

    public function destroy(Request $request, RegistrationAllowance $allowance): RedirectResponse
    {
        try {
            $email = $allowance->email;
            RegistrationAllowanceService::removeUnused($allowance);
        } catch (InvalidArgumentException $exception) {
            return $this->notice($request, 'used', $exception->getMessage());
        }

        return $this->notice($request, 'removed', 'It was removed from the registration list.', $email);
    }

    private function notice(Request $request, string $kind, string $message, ?string $email = null): RedirectResponse
    {
        $title = match ($kind) {
            'used' => 'Email already used',
            'removed' => 'Email removed',
            'error' => 'Nothing was added',
            default => 'Email added',
        };

        return redirect()
            ->route('dashboard.users', $this->listQuery($request))
            ->with('allowance_notice', [
                'title' => $title,
                'message' => $message,
                'kind' => $kind,
                'email' => $email,
            ]);
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
