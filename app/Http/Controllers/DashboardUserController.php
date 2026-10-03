<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\User;
use App\Services\AdminActivityService;
use App\Services\ItemOwnerService;
use App\Services\UserAccountStatusService;
use App\Services\UserNameService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class DashboardUserController extends Controller
{
    public function index()
    {
        $this->applyInactivityPolicySafely();

        $relations = ['office'];
        if (Schema::hasTable('academic_programs') && Schema::hasColumn('users', 'program_id')) {
            $relations[] = 'academicProgram.office';
        }

        $orderColumn = Schema::hasColumn('users', 'created_at') ? 'created_at' : 'user_id';

        try {
            $users = User::with($relations)
                ->orderBy($orderColumn, 'desc')
                ->get();
        } catch (QueryException $e) {
            $this->logUsersDb('A', 'user list eager load failed', $e);
            $users = User::query()->orderBy($orderColumn, 'desc')->get();
        }

        $offices = Office::orderBy('department_name', 'asc')->get();

        try {
            $itemOwnerOfficeId = ItemOwnerService::itemOwnerOfficeId();
        } catch (QueryException $e) {
            $this->logUsersDb('C', 'item owner office lookup failed', $e);
            $itemOwnerOfficeId = null;
        }

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
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => ['required', Rule::in(['user', 'student', 'faculty', 'admin', 'pf_admin', 'pc_admin', 'item_owner'])],
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
            'is_active' => true,
            'status_changed_at' => now(),
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
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => ['required', Rule::in(['user', 'student', 'faculty', 'admin', 'pf_admin', 'pc_admin', 'item_owner'])],
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

    public function toggleStatus(Request $request, int $userId)
    {
        $user = User::findOrFail($userId);

        if (Auth::id() === $user->user_id) {
            $message = 'You cannot change the status of your own account.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return redirect()->route('dashboard.users')->with('error', $message);
        }

        if (!UserAccountStatusService::isStatusManaged($user)) {
            $message = 'Admin accounts do not use active/inactive status.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return redirect()->route('dashboard.users')->with('error', $message);
        }

        UserAccountStatusService::toggle($user);
        $after = UserAccountStatusService::isActive($user);
        $durationLabel = UserAccountStatusService::statusDurationLabel($user);
        $message = $after ? 'User marked as active.' : 'User marked as inactive.';

        if ($actorId = (int) (Auth::id() ?? 0)) {
            AdminActivityService::log(
                $actorId,
                $after ? 'Activated user account' : 'Deactivated user account',
                'Account'
            );
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'userId' => $user->user_id,
                'isActive' => $after,
                'status' => $after ? 'active' : 'inactive',
                'durationLabel' => $durationLabel,
            ]);
        }

        return redirect()->route('dashboard.users')->with('success', $message);
    }

    public function destroy(Request $request, int $userId)
    {
        $user = User::findOrFail($userId);

        if (Auth::id() === $user->user_id) {
            $message = 'You cannot delete your own account.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return redirect()->route('dashboard.users')->with('error', $message);
        }

        $deletedId = $user->user_id;
        $user->delete();

        if ($actorId = (int) (Auth::id() ?? 0)) {
            AdminActivityService::log($actorId, 'Deleted user account', 'Account');
        }

        $message = 'User deleted successfully.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'userId' => $deletedId,
            ]);
        }

        return redirect()->route('dashboard.users')->with('success', $message);
    }

    private function applyInactivityPolicySafely(): void
    {
        if (!Schema::hasColumn('users', 'is_active')) {
            // #region agent log
            $this->logUsersDb('B', 'skipped inactivity, is_active missing');
            // #endregion
            return;
        }

        try {
            UserAccountStatusService::applyInactivityPolicy();
        } catch (QueryException $e) {
            $this->logUsersDb('B', 'inactivity policy failed', $e);
        }
    }

    private function logUsersDb(string $hypothesisId, string $message, ?\Throwable $e = null): void
    {
        // #region agent log
        $summary = '';
        if ($e) {
            $summary = strtok($e->getMessage(), "\n") ?: '';
            $summary = preg_replace('/\b[\w.+-]+@[\w.-]+\b/', '[email]', $summary) ?? $summary;
            $summary = substr($summary, 0, 240);
        }
        @file_put_contents(base_path('debug-afd7f1.log'), json_encode([
            'sessionId' => 'afd7f1',
            'runId' => 'users-fix',
            'hypothesisId' => $hypothesisId,
            'location' => 'DashboardUserController.php',
            'message' => $message,
            'data' => ['summary' => $summary],
            'timestamp' => (int) round(microtime(true) * 1000),
        ]) . "\n", FILE_APPEND);
        // #endregion
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
