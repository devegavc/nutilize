<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DashboardUserController extends Controller
{
    public function index()
    {
        $users = User::with('office')
            ->orderBy('created_at', 'desc')
            ->get();

        $offices = Office::orderBy('department_name', 'asc')->get();

        return view('dashboard-users', [
            'users' => $users,
            'offices' => $offices,
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
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['user', 'admin', 'pf_admin', 'pc_admin'])],
            'full_name' => 'nullable|string|max:255',
            'office_id' => ['nullable', 'exists:offices,office_id'],
        ]);

        User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'full_name' => $data['full_name'] ?? null,
            'office_id' => $data['office_id'] ?? null,
        ]);

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
            'password' => 'nullable|string|min:8',
            'role' => ['required', Rule::in(['user', 'admin', 'pf_admin', 'pc_admin'])],
            'full_name' => 'nullable|string|max:255',
            'office_id' => ['nullable', 'exists:offices,office_id'],
        ]);

        if ($request->filled('password')) {
            $user->password = $data['password'];
        }

        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->full_name = $data['full_name'] ?? null;
        $user->office_id = $data['office_id'] ?? null;
        $user->save();

        return redirect()->route('dashboard.users')->with('success', 'User updated successfully.');
    }

    public function destroy(int $userId)
    {
        $user = User::findOrFail($userId);

        if (Auth::id() === $user->user_id) {
            return redirect()->route('dashboard.users')->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('dashboard.users')->with('success', 'User deleted successfully.');
    }
}
