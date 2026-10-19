<?php

namespace App\Models;

use App\Services\ItemOwnerService;
use App\Support\RoleCatalog;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['first_name', 'middle_initial', 'last_name', 'full_name', 'username', 'email', 'password', 'role', 'office_id', 'program_id', 'suffix', 'contact_number', 'phone_number', 'is_active', 'status_changed_at', 'role_needs_review'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'status_changed_at' => 'datetime',
            'role_needs_review' => 'boolean',
        ];
    }

    public function isFaculty(): bool
    {
        $role = (string) $this->role;

        return $role === RoleCatalog::FACULTY || strtolower($role) === 'faculty';
    }

    public function isStudentUser(): bool
    {
        $role = (string) $this->role;

        return $role === RoleCatalog::STUDENT
            || in_array(strtolower($role), ['user', 'student'], true);
    }

    public function isDean(): bool
    {
        return RoleCatalog::isDeanCode((string) $this->role);
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id', 'office_id');
    }

    public function academicProgram()
    {
        return $this->belongsTo(AcademicProgram::class, 'program_id', 'program_id');
    }

    public function isPhysicalFacilitiesAdmin()
    {
        if (!\App\Services\UserAccountStatusService::isActive($this)) {
            return false;
        }

        if ((string) $this->role === 'PFO') {
            return true;
        }

        if (!$this->office || !$this->office->isPhysicalFacilities()) {
            return false;
        }

        $role = strtolower((string) $this->role);

        return in_array($role, ['admin', 'pf_admin'], true);
    }

    public function isOfficeApprover()
    {
        if (!\App\Services\UserAccountStatusService::isActive($this)) {
            return false;
        }

        if ($this->isDean()) {
            return true;
        }

        if (is_null($this->office_id)) {
            return false;
        }

        $role = (string) $this->role;
        $lower = strtolower($role);

        if (RoleCatalog::isProgramChairCode($role)) {
            return $this->officeMatchesChairRole($role);
        }

        if (RoleCatalog::isOfficeRoleCode($role)) {
            return $this->officeMatchesOfficeRole($role);
        }

        if (in_array($lower, ['admin', 'pc_admin'], true)) {
            return true;
        }

        return $lower === 'pf_admin' && $this->office && $this->office->isPhysicalFacilities();
    }

    public function isProgramChairAdmin(): bool
    {
        if (!\App\Services\UserAccountStatusService::isActive($this)) {
            return false;
        }

        $role = (string) $this->role;

        if (RoleCatalog::isProgramChairCode($role)) {
            return $this->officeMatchesChairRole($role);
        }

        return strtolower($role) === 'pc_admin' && !is_null($this->office_id);
    }

    private function officeMatchesChairRole(string $role): bool
    {
        $expected = RoleCatalog::PROGRAM_OFFICES[$role] ?? null;
        if ($expected === null || !$this->office) {
            return false;
        }

        return RoleCatalog::normalize($this->office->department_name) === RoleCatalog::normalize($expected);
    }

    private function officeMatchesOfficeRole(string $role): bool
    {
        if (!$this->office) {
            return false;
        }

        return RoleCatalog::officeRoleForDepartment(
            (string) $this->office->department_name,
            (string) ($this->office->short_code ?? '')
        ) === $role;
    }

    public function isItemOwnerAdmin(): bool
    {
        return ItemOwnerService::isItemOwnerUser($this);
    }

    public function shouldSelectProgram(): bool
    {
        if (!is_null($this->office_id)) {
            return false;
        }

        $role = (string) $this->role;

        return $role === RoleCatalog::STUDENT
            || in_array(strtolower($role), ['user', 'student'], true);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'user_id', 'user_id');
    }

    /**
     * Prefer the composed first/middle/last name. Many student accounts have those
     * parts filled while `full_name` is left null (especially mobile registrations).
     */
    public function displayName(): string
    {
        $parts = array_filter([
            trim((string) ($this->first_name ?? '')),
            trim((string) ($this->middle_initial ?? '')) !== ''
                ? rtrim(trim((string) $this->middle_initial), '.') . '.'
                : '',
            trim((string) ($this->last_name ?? '')),
            trim((string) ($this->suffix ?? '')),
        ], static fn (string $part): bool => $part !== '');

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        $fullName = trim((string) ($this->full_name ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        return trim((string) ($this->username ?? 'Unknown')) ?: 'Unknown';
    }

    /**
     * Build a display name from a raw users-table row (query builder / join selects).
     */
    public static function formatDisplayName(object $row, string $fallback = 'Unknown'): string
    {
        $parts = array_filter([
            trim((string) ($row->first_name ?? '')),
            trim((string) ($row->middle_initial ?? '')) !== ''
                ? rtrim(trim((string) $row->middle_initial), '.') . '.'
                : '',
            trim((string) ($row->last_name ?? '')),
            trim((string) ($row->suffix ?? '')),
        ], static fn (string $part): bool => $part !== '');

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        $fullName = trim((string) ($row->full_name ?? $row->requester_full_name ?? $row->reporter_full_name ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        $username = trim((string) ($row->username ?? $row->requester_username ?? $row->reporter_username ?? ''));

        return $username !== '' ? $username : $fallback;
    }
}
