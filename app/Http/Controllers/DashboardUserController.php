<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountSetupDeliveryException;
use App\Models\Office;
use App\Models\User;
use App\Services\AccountSetupService;
use App\Services\AdminActivityService;
use App\Services\ItemOwnerService;
use App\Services\UserAccountStatusService;
use App\Services\UserNameService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DashboardUserController extends Controller
{
    public function __construct(private AccountSetupService $accountSetup) {}

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
        } catch (QueryException) {
            $users = User::query()->orderBy($orderColumn, 'desc')->get();
        }

        $offices = Office::orderBy('department_name', 'asc')->get();

        try {
            $itemOwnerOfficeId = ItemOwnerService::itemOwnerOfficeId();
        } catch (QueryException) {
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
            'role' => ['required', Rule::in(['user', 'student', 'faculty', 'admin', 'pf_admin', 'pc_admin', 'item_owner'])],
            'full_name' => 'nullable|string|max:255',
            'office_id' => ['nullable', 'exists:offices,office_id'],
        ]);

        $data = $this->normalizeRolePayload($data);
        $data = UserNameService::applyToUserData($data);

        try {
            $delivery = DB::transaction(function () use ($data) {
                $user = new User();
                $user->username = $data['username'];
                $user->email = $data['email'];
                $user->password = $this->accountSetup->unknownPassword();
                $user->role = $data['role'];
                $user->full_name = $data['full_name'] ?? null;
                $user->first_name = $data['first_name'] ?? null;
                $user->middle_initial = $data['middle_initial'] ?? null;
                $user->last_name = $data['last_name'] ?? null;
                $user->office_id = $data['office_id'] ?? null;
                $user->setAttribute('is_active', DB::raw('true'));
                $user->status_changed_at = now();
                $user->save();

                ItemOwnerService::syncForUser($user);

                $token = $this->accountSetup->issue($user);

                return $this->accountSetup->deliver($user, $token);
            });
        } catch (AccountSetupDeliveryException) {
            return redirect()
                ->route('dashboard.users')
                ->with('error', 'Account creation failed because the setup email could not be sent. No account was created.');
        }

        if ($actorId = (int) (Auth::id() ?? 0)) {
            AdminActivityService::log($actorId, 'Added new user', 'Account');
        }

        return redirect()
            ->route('dashboard.users')
            ->with('success', $this->accountSetup->successMessage($delivery));
    }

    public function update(Request $request, int $userId)
    {
        $user = User::findOrFail($userId);

        if ($request->input('office_id') === '') {
            $request->merge(['office_id' => null]);
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->user_id, 'user_id')],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
            'role' => ['required', Rule::in(['user', 'student', 'faculty', 'admin', 'pf_admin', 'pc_admin', 'item_owner'])],
            'full_name' => 'nullable|string|max:255',
            'office_id' => ['nullable', 'exists:offices,office_id'],
        ]);

        $data = $this->normalizeRolePayload($data);
        $data = UserNameService::applyToUserData($data);

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
            return;
        }

        try {
            UserAccountStatusService::applyInactivityPolicy();
        } catch (QueryException) {
            // A missing inactivity column should not block the user list.
        }
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
