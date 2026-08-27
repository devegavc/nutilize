<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class UserAccountStatusService
{
    public const INACTIVITY_WEEKS = 14;

    /**
     * Roles that auto-inactivate after prolonged inactivity.
     *
     * @var list<string>
     */
    public const TRACKED_ROLES = ['user', 'student', 'faculty'];

    public static function isTrackedRole(User $user): bool
    {
        return in_array(strtolower((string) $user->role), self::TRACKED_ROLES, true);
    }

    public static function isActive(User $user): bool
    {
        return (bool) ($user->is_active ?? true);
    }

    public static function inactivityCutoff(?CarbonInterface $now = null): CarbonInterface
    {
        return ($now ?? now())->copy()->subWeeks(self::INACTIVITY_WEEKS);
    }

    public static function lastActivityAt(User $user): ?CarbonInterface
    {
        if ($user->last_login_at) {
            return $user->last_login_at;
        }

        return $user->created_at;
    }

    public static function isPastInactivityLimit(User $user, ?CarbonInterface $now = null): bool
    {
        if (!self::isTrackedRole($user) || !self::isActive($user)) {
            return false;
        }

        $lastActivity = self::lastActivityAt($user);
        if (!$lastActivity) {
            return false;
        }

        return $lastActivity->lte(self::inactivityCutoff($now));
    }

    public static function markActive(User $user, ?CarbonInterface $at = null): void
    {
        $user->is_active = true;
        $user->status_changed_at = $at ?? now();
        $user->save();
    }

    public static function markInactive(User $user, ?CarbonInterface $at = null): void
    {
        $user->is_active = false;
        $user->status_changed_at = $at ?? now();
        $user->save();
    }

    public static function toggle(User $user): User
    {
        if (self::isActive($user)) {
            self::markInactive($user);
        } else {
            self::markActive($user);
        }

        return $user->refresh();
    }

    /**
     * Auto-inactivate tracked users/faculties past the 14-week inactivity window.
     *
     * @param  Collection<int, User>|null  $users
     * @return int Number of accounts deactivated
     */
    public static function applyInactivityPolicy(?Collection $users = null): int
    {
        $query = User::query()
            ->where('is_active', true)
            ->whereIn('role', self::TRACKED_ROLES);

        if ($users !== null) {
            $ids = $users->pluck('user_id')->filter()->all();
            if ($ids === []) {
                return 0;
            }
            $query->whereIn('user_id', $ids);
        }

        $deactivated = 0;
        $cutoff = self::inactivityCutoff();

        $query->orderBy('user_id')->chunkById(100, function ($chunk) use (&$deactivated, $cutoff) {
            foreach ($chunk as $user) {
                $lastActivity = self::lastActivityAt($user);
                if (!$lastActivity || $lastActivity->gt($cutoff)) {
                    continue;
                }

                self::markInactive($user, now());
                $deactivated++;
            }
        }, 'user_id');

        return $deactivated;
    }

    public static function statusDurationLabel(User $user, ?CarbonInterface $now = null): string
    {
        $now = $now ?? now();
        $since = $user->status_changed_at
            ?? (self::isActive($user) ? $user->created_at : self::lastActivityAt($user))
            ?? $user->created_at
            ?? $now;

        $diff = $since->diffForHumans($now, true, false, 2);

        return self::isActive($user)
            ? 'Active for '.$diff
            : 'Inactive for '.$diff;
    }

    public static function recordLogin(User $user): void
    {
        $user->last_login_at = now();

        if (!isset($user->status_changed_at) || $user->status_changed_at === null) {
            $user->status_changed_at = $user->created_at ?? now();
        }

        if ($user->is_active === null) {
            $user->is_active = true;
        }

        $user->save();
    }
}
