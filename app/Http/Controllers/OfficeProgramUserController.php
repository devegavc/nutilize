<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfficeProgramUserController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $chair = $this->programChair();
        $programIds = $this->resolveProgramIdsForChair($chair);

        $users = $this->programMembersQuery($chair, $programIds)
            ->orderByRaw("CASE WHEN LOWER(role) = 'pc_admin' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        $programName = $this->resolveProgramName($chair, $programIds);

        return view('office-users', [
            'users' => $users,
            'programName' => $programName,
            'programId' => (int) ($chair->program_id ?? ($programIds[0] ?? 0)),
            'currentUserId' => (int) $chair->user_id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $chair = $this->programChair();

        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'full_name' => ['nullable', 'string', 'max:255'],
        ]);

        User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'user',
            'full_name' => $data['full_name'] ?? null,
            'program_id' => (int) ($this->resolveProgramIdsForChair($chair)[0] ?? $chair->program_id),
            'office_id' => null,
        ]);

        return redirect()->route('office.users')->with('success', 'Student account created successfully.');
    }

    public function update(Request $request, int $userId): RedirectResponse
    {
        $chair = $this->programChair();
        $user = $this->findProgramMember($chair, $userId, $this->resolveProgramIdsForChair($chair));

        if ($request->input('password') === '') {
            $request->merge(['password' => null]);
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->user_id, 'user_id')],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
            'password' => ['nullable', 'string', 'min:8'],
            'full_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->filled('password')) {
            $user->password = $data['password'];
        }

        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->full_name = $data['full_name'] ?? null;
        $user->save();

        return redirect()->route('office.users')->with('success', 'Student account updated successfully.');
    }

    public function destroy(int $userId): RedirectResponse
    {
        $chair = $this->programChair();
        $user = $this->findProgramMember($chair, $userId, $this->resolveProgramIdsForChair($chair));

        if ((int) Auth::id() === (int) $user->user_id || strtolower((string) $user->role) === 'pc_admin') {
            return redirect()->route('office.users')->with('error', 'Program chair accounts cannot be deleted from this page.');
        }

        $user->delete();

        return redirect()->route('office.users')->with('success', 'Student account deleted successfully.');
    }

    private function programChair(): User
    {
        $user = Auth::user();

        if (!$user || !$user->isProgramChairAdmin()) {
            abort(403);
        }

        if (is_null($user->program_id) && is_null($user->office_id)) {
            abort(403);
        }

        return $user;
    }

    /**
     * @return array<int, int>
     */
    private function resolveProgramIdsForChair(User $chair): array
    {
        $programIds = [];

        if (!is_null($chair->program_id)) {
            $programIds[] = (int) $chair->program_id;
        }

        if (!is_null($chair->office_id)) {
            $officeProgramIds = DB::table('academic_programs')
                ->where('office_id', (int) $chair->office_id)
                ->pluck('program_id')
                ->map(fn ($programId) => (int) $programId)
                ->all();

            $programIds = array_merge($programIds, $officeProgramIds);
        }

        return array_values(array_unique(array_filter($programIds)));
    }

    private function resolveProgramName(User $chair, array $programIds): string
    {
        $chair->loadMissing('academicProgram');

        if ($chair->academicProgram?->name) {
            return $chair->academicProgram->name;
        }

        if ($programIds !== []) {
            $name = DB::table('academic_programs')
                ->whereIn('program_id', $programIds)
                ->orderBy('program_id')
                ->value('name');

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        if (!is_null($chair->office_id)) {
            $officeName = DB::table('offices')
                ->where('office_id', (int) $chair->office_id)
                ->value('department_name');

            if (is_string($officeName) && $officeName !== '') {
                return $officeName;
            }
        }

        return 'Program';
    }

    private function programMembersQuery(User $chair, array $programIds)
    {
        return User::query()->where(function ($query) use ($chair, $programIds): void {
            $query->where('user_id', (int) $chair->user_id);

            if ($programIds !== []) {
                $query->orWhere(function ($memberQuery) use ($programIds): void {
                    $memberQuery
                        ->whereIn('program_id', $programIds)
                        ->whereRaw('LOWER(role) IN (?, ?)', ['user', 'student']);
                });
            }
        });
    }

    private function findProgramMember(User $chair, int $userId, array $programIds): User
    {
        return $this->programMembersQuery($chair, $programIds)
            ->where('user_id', $userId)
            ->firstOrFail();
    }
}
