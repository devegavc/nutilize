<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\User;
use App\Services\AdminActivityService;
use App\Services\ItemOwnerService;
use App\Services\UserAccountStatusService;
use App\Services\UserNameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DashboardUserController extends Controller
{
    public function index()
    {
        $deactivated = UserAccountStatusService::applyInactivityPolicy();

        // #region agent log
        file_put_contents(
            base_path('debug-e19b10.log'),
            json_encode([
                'sessionId' => 'e19b10',
                'runId' => 'feature-verify',
                'hypothesisId' => 'A',
                'location' => 'DashboardUserController.php:index',
                'message' => 'users index inactivity sync',
                'data' => [
                    'deactivated' => $deactivated,
                    'inactivityWeeks' => UserAccountStatusService::INACTIVITY_WEEKS,
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ])."\n",
            FILE_APPEND
        );
        // #endregion

        $users = User::with(['office', 'academicProgram.office'])
            ->orderBy('created_at', 'desc')
            ->get();

        // #region agent log
        $sampleManaged = $users->first(fn ($u) => UserAccountStatusService::isStatusManaged($u));
        $sampleAdmin = $users->first(fn ($u) => in_array(strtolower((string) $u->role), ['admin', 'pf_admin', 'pc_admin'], true));
        file_put_contents(
            base_path('debug-e19b10.log'),
            json_encode([
                'sessionId' => 'e19b10',
                'runId' => 'post-fix',
                'hypothesisId' => 'A,B',
                'location' => 'DashboardUserController.php:index',
                'message' => 'users index status policy sample',
                'data' => [
                    'deactivated' => $deactivated,
                    'managedCount' => $users->filter(fn ($u) => UserAccountStatusService::isStatusManaged($u))->count(),
                    'unmanagedCount' => $users->reject(fn ($u) => UserAccountStatusService::isStatusManaged($u))->count(),
                    'sampleManagedDuration' => $sampleManaged
                        ? UserAccountStatusService::statusDurationDebug($sampleManaged)
                        : null,
                    'sampleAdminManaged' => $sampleAdmin
                        ? UserAccountStatusService::isStatusManaged($sampleAdmin)
                        : null,
                    'sampleAdminRole' => $sampleAdmin?->role,
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ])."\n",
            FILE_APPEND
        );
        // #endregion

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
            'role' => ['required', Rule::in(['user', 'faculty', 'admin', 'pf_admin', 'pc_admin', 'item_owner'])],
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
            'password' => 'nullable|string|min:8|confirmed',
            'role' => ['required', Rule::in(['user', 'faculty', 'admin', 'pf_admin', 'pc_admin', 'item_owner'])],
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

        $before = UserAccountStatusService::isActive($user);
        UserAccountStatusService::toggle($user);
        $after = UserAccountStatusService::isActive($user);
        $durationDebug = UserAccountStatusService::statusDurationDebug($user);
        $durationLabel = UserAccountStatusService::statusDurationLabel($user);
        $message = $after ? 'User marked as active.' : 'User marked as inactive.';

        // #region agent log
        file_put_contents(
            base_path('debug-e19b10.log'),
            json_encode([
                'sessionId' => 'e19b10',
                'runId' => 'ajax-verify',
                'hypothesisId' => 'AJAX1',
                'location' => 'DashboardUserController.php:toggleStatus',
                'message' => 'user status toggled',
                'data' => [
                    'userId' => $user->user_id,
                    'role' => $user->role,
                    'beforeActive' => $before,
                    'afterActive' => $after,
                    'expectsJson' => $request->expectsJson() || $request->ajax(),
                    'createdAt' => optional($user->created_at)?->toIso8601String(),
                    'statusChangedAt' => optional($user->status_changed_at)?->toIso8601String(),
                    'duration' => $durationDebug,
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ])."\n",
            FILE_APPEND
        );
        // #endregion

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

        // #region agent log
        file_put_contents(
            base_path('debug-e19b10.log'),
            json_encode([
                'sessionId' => 'e19b10',
                'runId' => 'ajax-verify',
                'hypothesisId' => 'AJAX2',
                'location' => 'DashboardUserController.php:destroy',
                'message' => 'user deleted via request',
                'data' => [
                    'userId' => $deletedId,
                    'expectsJson' => $request->expectsJson() || $request->ajax(),
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ])."\n",
            FILE_APPEND
        );
        // #endregion

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
