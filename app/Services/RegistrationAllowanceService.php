<?php

namespace App\Services;

use App\Models\RegistrationAllowance;
use App\Models\User;
use App\Support\EmailSpreadsheet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public static function addEmail(string $email, ?int $addedBy = null): RegistrationAllowance
    {
        $normalized = self::normalizeEmail($email);
        if (!self::validEmail($normalized)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        $existing = RegistrationAllowance::query()->where('email', $normalized)->first();
        if ($existing instanceof RegistrationAllowance) {
            return $existing;
        }

        return RegistrationAllowance::query()->create([
            'email' => $normalized,
            'added_by' => $addedBy ?? (Auth::id() ? (int) Auth::id() : null),
        ]);
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

    public static function page(?string $search): LengthAwarePaginator
    {
        $term = self::normalizeEmail((string) $search);

        return RegistrationAllowance::query()
            ->when($term !== '', fn ($query) => $query->where('email', 'like', '%'.$term.'%'))
            ->orderBy('email')
            ->paginate(25, ['*'], 'allowance_page')
            ->withQueryString();
    }

    private static function validEmail(string $email): bool
    {
        return $email !== ''
            && strlen($email) <= 100
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
