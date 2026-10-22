<?php

namespace App\Services;

use App\Models\ReservationApproval;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProgramChairStageService
{
    public static function hasDeanRequiredColumn(): bool
    {
        return Schema::hasTable('reservation_approvals')
            && Schema::hasColumn('reservation_approvals', 'dean_required');
    }

    /**
     * @return array{allowed: bool, on_behalf: bool, code: string}
     */
    public static function authorize(User $actor, ReservationApproval $approval): array
    {
        $office = DB::table('offices')
            ->where('office_id', (int) $approval->office_id)
            ->first(['department_name', 'short_code']);

        $chairRole = RoleCatalog::chairCodeForDepartment($office->department_name ?? null);
        $schoolName = null;
        if ($chairRole !== null && Schema::hasTable('academic_programs')) {
            $schoolName = DB::table('academic_programs')
                ->where('office_id', (int) $approval->office_id)
                ->value('school_name');
        }

        $requester = DB::table('reservations')
            ->join('users', 'users.user_id', '=', 'reservations.user_id')
            ->where('reservations.reservation_id', (int) $approval->reservation_id)
            ->first(['users.user_id', 'users.role']);

        $deanRequired = self::hasDeanRequiredColumn() && (bool) ($approval->dean_required ?? false);

        return RoleCatalog::interpretProgramChairDecision(
            (string) $actor->role,
            (int) $actor->user_id,
            $actor->office_id !== null ? (int) $actor->office_id : null,
            (int) $approval->office_id,
            $requester ? (int) $requester->user_id : null,
            $requester ? (string) $requester->role : '',
            $deanRequired,
            $approval->approved_at !== null,
            $chairRole,
            RoleCatalog::deanForProgramOffice($office->department_name ?? null, $schoolName),
        );
    }

    public static function requesterIsProgramChair(int $reservationId): bool
    {
        return self::requesterChairRole($reservationId) !== null;
    }

    public static function markIfRequesterIsChair(int $reservationId): void
    {
        if ($reservationId <= 0 || !self::hasDeanRequiredColumn()) {
            return;
        }

        $chairRole = self::requesterChairRole($reservationId);
        if ($chairRole === null) {
            return;
        }

        $programOfficeId = ProgramChairOfficeResolver::resolveForReservation($reservationId);
        if ($programOfficeId === null) {
            return;
        }

        $programOffice = DB::table('offices')->where('office_id', $programOfficeId)->first(['department_name']);
        $schoolName = Schema::hasTable('academic_programs')
            ? DB::table('academic_programs')->where('office_id', $programOfficeId)->value('school_name')
            : null;

        $expectedChair = RoleCatalog::chairCodeForDepartment($programOffice->department_name ?? null);
        $dean = RoleCatalog::deanForProgramOffice($programOffice->department_name ?? null, $schoolName);
        $sameProgram = $expectedChair === $chairRole && $dean !== null && $dean === RoleCatalog::deanForChair($chairRole);

        if (!$sameProgram) {
            return;
        }

        DB::table('reservation_approvals')
            ->where('reservation_id', $reservationId)
            ->where('office_id', $programOfficeId)
            ->whereNull('approved_at')
            ->update([
                'dean_required' => true,
                'updated_at' => now(),
            ]);
    }

    private static function requesterChairRole(int $reservationId): ?string
    {
        if ($reservationId <= 0) {
            return null;
        }

        $requester = DB::table('reservations')
            ->join('users', 'users.user_id', '=', 'reservations.user_id')
            ->where('reservations.reservation_id', $reservationId)
            ->first(['users.role', 'users.office_id']);

        if (!$requester) {
            return null;
        }

        $role = (string) $requester->role;
        if (RoleCatalog::isProgramChairCode($role)) {
            return $role;
        }

        if (strtolower($role) === 'pc_admin') {
            $department = DB::table('offices')->where('office_id', (int) $requester->office_id)->value('department_name');

            return RoleCatalog::chairCodeForDepartment($department);
        }

        return null;
    }

    /**
     * @return Collection<int, User>
     */
    public static function recipientsForOffice(int $officeId, int $reservationId): Collection
    {
        $office = DB::table('offices')->where('office_id', $officeId)->first(['department_name', 'short_code']);
        if (!$office) {
            return collect();
        }

        $staff = User::query()
            ->where('office_id', $officeId)
            ->get()
            ->filter(fn (User $user) => UserAccountStatusService::isActive($user))
            ->filter(fn (User $user) => self::userStaffsOffice($user, $officeId, (string) $office->department_name, (string) ($office->short_code ?? '')))
            ->values();

        $chairRole = RoleCatalog::chairCodeForDepartment($office->department_name);
        $schoolName = $chairRole && Schema::hasTable('academic_programs')
            ? DB::table('academic_programs')->where('office_id', $officeId)->value('school_name')
            : null;
        $deanRole = RoleCatalog::deanForProgramOffice($office->department_name, $schoolName);

        $deans = $deanRole === null
            ? collect()
            : User::query()->where('role', $deanRole)->get()
                ->filter(fn (User $user) => UserAccountStatusService::isActive($user))
                ->values();

        $deanRequired = self::reservationRequiresDean($reservationId, $officeId);

        if ($deanRequired) {
            return $deans->values();
        }

        return $staff->concat($deans)->unique('user_id')->values();
    }

    public static function reservationRequiresDean(int $reservationId, int $officeId): bool
    {
        if (!self::hasDeanRequiredColumn() || $reservationId <= 0 || $officeId <= 0) {
            return false;
        }

        return DB::table('reservation_approvals')
            ->where('reservation_id', $reservationId)
            ->where('office_id', $officeId)
            ->whereRaw('dean_required = true')
            ->whereNull('approved_at')
            ->exists();
    }

    /**
     * @return list<int>
     */
    public static function programOfficeIdsForDean(string $deanRole): array
    {
        $chairs = RoleCatalog::DEAN_CHAIRS[$deanRole] ?? [];
        if ($chairs === []) {
            return [];
        }

        $names = [];
        foreach ($chairs as $chair) {
            $names[] = RoleCatalog::PROGRAM_OFFICES[$chair];
        }

        return DB::table('offices')
            ->whereIn('department_name', $names)
            ->pluck('office_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function userStaffsOffice(User $user, int $officeId, string $departmentName, string $shortCode): bool
    {
        if ((int) $user->office_id !== $officeId) {
            return false;
        }

        $role = RoleCatalog::canonicalCode((string) $user->role) ?? (string) $user->role;
        if (RoleCatalog::isProgramChairCode($role)) {
            return RoleCatalog::normalize(RoleCatalog::PROGRAM_OFFICES[$role]) === RoleCatalog::normalize($departmentName);
        }

        if (RoleCatalog::isOfficeRoleCode($role)) {
            return RoleCatalog::officeRoleForDepartment($departmentName, $shortCode) === $role;
        }

        $lower = strtolower($role);
        if ($lower === 'pc_admin') {
            return true;
        }

        if ($lower === 'admin') {
            return true;
        }

        if ($lower === 'pf_admin') {
            return RoleCatalog::officeRoleForDepartment($departmentName, $shortCode) === 'PFO';
        }

        return false;
    }
}
