<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\User;
use App\Services\AdminActivityService;
use App\Services\ItemOwnerService;
use App\Services\UserNameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DashboardUserController extends Controller
{
    public function index()
    {
        $users = User::with('office')
            ->orderBy('created_at', 'desc')
            ->get();

        $offices = Office::orderBy('department_name', 'asc')->get();
        $itemOwnerOfficeId = ItemOwnerService::itemOwnerOfficeId();

        return view('dashboard-users', [
            'users' => $users,
            'offices' => $offices,
            'itemOwnerOfficeId' => $itemOwnerOfficeId,
        ]);
    }

    public function store(Request $request)
    {
        if ($request->input('office_id') === '') {
            $request->merge(['office_id' => null]);
        }

        $data = $request->validate([
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|max:100|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::in(['user', 'admin', 'pf_admin', 'pc_admin', 'item_owner'])],
            'full_name' => 'nullable|string|max:255',
            'office_id' => ['nullable', 'exists:offices,office_id'],
        ]);

        $data = $this->normalizeRolePayload($data);
        $data = UserNameService::applyToUserData($data);

        $user = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'full_name' => $data['full_name'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'middle_initial' => $data['middle_initial'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'office_id' => $data['office_id'] ?? null,
        ]);

        ItemOwnerService::syncForUser($user);

        if ($actorId = (int) (Auth::id() ?? 0)) {
            AdminActivityService::log($actorId, 'Added new user', 'Account');
        }

        return redirect()->route('dashboard.users')->with('success', 'User added successfully.');
    }

    public function update(Request $request, int $userId)
    {
        $user = User::findOrFail($userId);

        if ($request->input('password') === '') {
            $request->merge(['password' => null]);
        }

        if ($request->input('office_id') === '') {
            $request->merge(['office_id' => null]);
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->user_id, 'user_id')],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => ['required', Rule::in(['user', 'admin', 'pf_admin', 'pc_admin', 'item_owner'])],
            'full_name' => 'nullable|string|max:255',
            'office_id' => ['nullable', 'exists:offices,office_id'],
        ]);

        $data = $this->normalizeRolePayload($data);
        $data = UserNameService::applyToUserData($data);

        if ($request->filled('password')) {
            $user->password = $data['password'];
        }

        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->full_name = $data['full_name'] ?? null;
        $user->first_name = $data['first_name'] ?? null;
        $user->middle_initial = $data['middle_initial'] ?? null;
        $user->last_name = $data['last_name'] ?? null;
        $user->office_id = $data['office_id'] ?? null;
        $user->save();

        ItemOwnerService::syncForUser($user);

        if ($actorId = (int) (Auth::id() ?? 0)) {
            AdminActivityService::log($actorId, 'Updated user account', 'Account');
        }

        return redirect()->route('dashboard.users')->with('success', 'User updated successfully.');
    }

    public function destroy(int $userId)
    {
        $user = User::findOrFail($userId);

        if (Auth::id() === $user->user_id) {
            return redirect()->route('dashboard.users')->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        if ($actorId = (int) (Auth::id() ?? 0)) {
            AdminActivityService::log($actorId, 'Deleted user account', 'Account');
        }

        return redirect()->route('dashboard.users')->with('success', 'User deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeRolePayload(array $data): array
    {
        if (($data['role'] ?? '') !== 'item_owner') {
            return $data;
        }

        $itemOwnerOfficeId = ItemOwnerService::itemOwnerOfficeId();

        if (is_null($itemOwnerOfficeId)) {
            throw ValidationException::withMessages([
                'role' => 'The Item Owner office is not configured in the system.',
            ]);
        }

        $data['role'] = 'admin';
        $data['office_id'] = $itemOwnerOfficeId;

        return $data;
    }
}
