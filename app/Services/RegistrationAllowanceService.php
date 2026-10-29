<?php

namespace App\Services;

use App\Models\Office;
use App\Models\RegistrationAllowance;
use App\Models\User;
use App\Support\EmailSpreadsheet;
use App\Support\RoleCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class RegistrationAllowanceService
{
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function isAllowed(string $email): bool
    {
        $normalized = self::normalizeEmail($email);
        if ($normalized === '') {
            return false;
        }

        return RegistrationAllowance::query()->where('email', $normalized)->exists();
    }

    public static function accountExists(string $email): bool
    {
        $normalized = self::normalizeEmail($email);

        return $normalized !== '' && User::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->exists();
    }

    public static function addEmail(string $email, ?int $addedBy = null, ?string $role = null): RegistrationAllowance
    {
        $normalized = self::normalizeEmail($email);
        if (!self::validEmail($normalized)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        $storedRole = self::storedRole($role);
        self::assertRoleCanBeAssigned($storedRole);

        $existing = RegistrationAllowance::query()->where('email', $normalized)->first();
        if ($existing instanceof RegistrationAllowance) {
            if (!self::accountExists($normalized)) {
                $existing->role = $storedRole;
                $existing->save();
            }

            return $existing;
        }

        return RegistrationAllowance::query()->create([
            'email' => $normalized,
            'role' => $storedRole,
            'added_by' => $addedBy ?? (Auth::id() ? (int) Auth::id() : null),
        ]);
    }

    public static function storedRole(?string $role): string
    {
        $role = trim((string) $role);
        if ($role === '' || strcasecmp($role, RoleCatalog::STUDENT) === 0 || strcasecmp($role, 'user') === 0) {
            return RoleCatalog::STUDENT;
        }

        if ($role === 'item_owner') {
            return 'item_owner';
        }

        $canonical = RoleCatalog::canonicalCode($role);
        if ($canonical === null) {
            throw new InvalidArgumentException('Choose a valid role.');
        }

        return $canonical;
    }

    /**
     * @return array{role: string, office_id: int|null}
     */
    public static function accountAssignment(?string $storedRole): array
    {
        $storedRole = self::storedRole($storedRole);
        if ($storedRole === 'item_owner') {
            return [
                'role' => 'admin',
                'office_id' => self::officeIdForStoredRole($storedRole),
            ];
        }

        return [
            'role' => $storedRole,
            'office_id' => self::officeIdForStoredRole($storedRole),
        ];
    }

    public static function officeIdForStoredRole(string $storedRole): ?int
    {
        if ($storedRole === 'item_owner') {
            $officeId = ItemOwnerService::itemOwnerOfficeId();
            if ($officeId === null) {
                throw new InvalidArgumentException('The Item Owner office is not configured.');
            }

            return (int) $officeId;
        }

        $linked = RoleCatalog::linkedOffice($storedRole);
        if (($linked['kind'] ?? 'unknown') === 'none') {
            return null;
        }

        $officeId = self::officeIdForLink($linked);
        if ($officeId === null) {
            throw new InvalidArgumentException('No office is configured for this role.');
        }

        return $officeId;
    }

    public static function assertRoleCanBeAssigned(string $storedRole): void
    {
        self::officeIdForStoredRole($storedRole);
    }

    /**
     * @return array{added: int, already_listed: int, invalid: int}
     */
    public static function importFile(UploadedFile $file, ?int $addedBy = null): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $values = EmailSpreadsheet::emailColumn($file->getRealPath(), $extension);

        return self::importValues($values, $addedBy);
    }

    /**
     * @param  array<int, string>  $values
     * @return array{added: int, already_listed: int, invalid: int}
     */
    public static function importValues(array $values, ?int $addedBy = null): array
    {
        $added = 0;
        $alreadyListed = 0;
        $invalid = 0;
        $seen = [];

        foreach ($values as $value) {
            $normalized = self::normalizeEmail($value);
            if (!self::validEmail($normalized)) {
                $invalid++;
                continue;
            }
            if (isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;

            $existing = RegistrationAllowance::query()->where('email', $normalized)->exists();
            if ($existing) {
                $alreadyListed++;
                continue;
            }

            RegistrationAllowance::query()->create([
                'email' => $normalized,
                'role' => RoleCatalog::STUDENT,
                'added_by' => $addedBy,
            ]);
            $added++;
        }

        return [
            'added' => $added,
            'already_listed' => $alreadyListed,
            'invalid' => $invalid,
        ];
    }

    /**
     * @param  array{added: int, already_listed: int, invalid: int}  $result
     */
    public static function importMessage(array $result): string
    {
        $added = (int) $result['added'];
        $already = (int) $result['already_listed'];
        $invalid = (int) $result['invalid'];

        $parts = [];
        $parts[] = $added === 1 ? 'Added 1 email.' : 'Added '.$added.' emails.';
        if ($already > 0) {
            $parts[] = $already === 1 ? '1 was already listed.' : $already.' were already listed.';
        }
        if ($invalid > 0) {
            $parts[] = $invalid === 1 ? '1 row was not a valid email address.' : $invalid.' rows were not valid email addresses.';
        }

        return implode(' ', $parts);
    }

    public static function removeUnused(RegistrationAllowance $allowance): void
    {
        if (self::accountExists($allowance->email)) {
            throw new InvalidArgumentException('This email already has an account. Deactivate that account instead of removing it from the list.');
        }

        $allowance->delete();
    }

    public static function roleLabel(?string $role): string
    {
        if ($role === 'item_owner') {
            return 'Item Owner';
        }

        try {
            return RoleCatalog::label(self::storedRole($role));
        } catch (InvalidArgumentException) {
            return trim((string) $role) !== '' ? (string) $role : RoleCatalog::STUDENT;
        }
    }

    /**
     * @return array{all: int, registered: int, unregistered: int}
     */
    public static function counts(): array
    {
        $total = RegistrationAllowance::query()->count();
        $registered = self::registeredQuery(RegistrationAllowance::query())->count();

        return [
            'all' => $total,
            'registered' => $registered,
            'unregistered' => max(0, $total - $registered),
        ];
    }

    public static function page(?string $search, string $status = 'all'): LengthAwarePaginator
    {
        $term = self::normalizeEmail((string) $search);
        $status = in_array($status, ['registered', 'unregistered'], true) ? $status : 'all';

        $query = RegistrationAllowance::query()
            ->when($term !== '', fn ($query) => $query->where('email', 'like', '%'.$term.'%'));

        if ($status === 'registered') {
            self::registeredQuery($query);
        } elseif ($status === 'unregistered') {
            self::registeredQuery($query, false);
        }

        return $query
            ->orderBy('email')
            ->paginate(25, ['*'], 'allowance_page')
            ->withQueryString();
    }

    private static function registeredQuery(Builder $query, bool $registered = true): Builder
    {
        $method = $registered ? 'whereExists' : 'whereNotExists';

        return $query->{$method}(function ($accounts): void {
            $accounts->selectRaw('1')
                ->from('users')
                ->whereRaw('LOWER(users.email) = registration_allowances.email');
        });
    }

    /**
     * @param  array{kind?: string, department_name?: string, short_code?: string}  $linked
     */
    private static function officeIdForLink(array $linked): ?int
    {
        $offices = Office::query()->get(['office_id', 'department_name', 'short_code']);

        if (($linked['kind'] ?? '') === 'department') {
            $name = strtolower(trim((string) ($linked['department_name'] ?? '')));
            $office = $offices->first(
                fn (Office $office): bool => strtolower(trim((string) $office->department_name)) === $name
            );

            return $office instanceof Office ? (int) $office->office_id : null;
        }

        if (($linked['kind'] ?? '') === 'short_code') {
            $short = strtoupper(trim((string) ($linked['short_code'] ?? '')));
            $office = $offices->first(
                fn (Office $office): bool => strtoupper(trim((string) $office->short_code)) === $short
            );

            return $office instanceof Office ? (int) $office->office_id : null;
        }

        return null;
    }

    private static function validEmail(string $email): bool
    {
        return $email !== ''
            && strlen($email) <= 100
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
