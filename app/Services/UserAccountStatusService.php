<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class UserAccountStatusService
{
    public const INACTIVITY_WEEKS = 14;

    /**
     * Roles that get a renewing 14-week active window.
     *
     * @var list<string>
     */
    public const TRACKED_ROLES = ['user', 'student', 'faculty'];

    public static function isTrackedRole(User $user): bool
    {
        return self::roleUsesActivityWindow((string) $user->role);
    }

    public static function roleUsesActivityWindow(string $role): bool
    {
        return in_array(strtolower($role), self::TRACKED_ROLES, true);
    }

    /**
     * Students and faculty follow the 14-week window.
     * Program chairs, deans, and office accounts can be deactivated by hand when the person in that role changes.
     */
    public static function roleCanBeDeactivated(string $role): bool
    {
        if (self::roleUsesActivityWindow($role)) {
            return true;
        }

        return \App\Support\RoleCatalog::isProgramChairCode($role)
            || \App\Support\RoleCatalog::isDeanCode($role)
            || \App\Support\RoleCatalog::isOfficeRoleCode($role)
            || in_array(strtolower($role), ['pc_admin', 'pf_admin'], true);
    }

    public static function isStatusManaged(User $user): bool
    {
        return self::roleCanBeDeactivated((string) $user->role);
    }

    public static function isActive(User $user): bool
    {
        return (bool) ($user->is_active ?? true);
    }

    public static function activeWindowCutoff(?CarbonInterface $now = null): CarbonInterface
    {
        return ($now ?? now())->copy()->subWeeks(self::INACTIVITY_WEEKS);
    }

    /**
     * Start of the current active window.
     * Activate resets this to now so the 14-week timer starts over at 0.
     */
    public static function activePeriodStartedAt(User $user): ?CarbonInterface
    {
        return $user->status_changed_at ?? $user->created_at;
    }

    public static function isPastActiveWindow(User $user, ?CarbonInterface $now = null): bool
    {
        if (!self::isTrackedRole($user) || !self::isActive($user)) {
            return false;
        }

        $startedAt = self::activePeriodStartedAt($user);
        if (!$startedAt) {
            return false;
        }

        return $startedAt->lte(self::activeWindowCutoff($now));
    }

    /** @deprecated Use isPastActiveWindow() */
    public static function isPastInactivityLimit(User $user, ?CarbonInterface $now = null): bool
    {
        return self::isPastActiveWindow($user, $now);
    }

    public static function markActive(User $user, ?CarbonInterface $at = null): void
    {
        // Reset the 14-week window — active duration starts at 0 again.
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
     * Auto-inactivate tracked accounts whose current active window reached 14 weeks.
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
        $cutoff = self::activeWindowCutoff();

        $query->orderBy('user_id')->chunkById(100, function ($chunk) use (&$deactivated, $cutoff) {
            foreach ($chunk as $user) {
                $startedAt = self::activePeriodStartedAt($user);
                if (!$startedAt || $startedAt->gt($cutoff)) {
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

        if (self::isActive($user)) {
            $since = self::activePeriodStartedAt($user) ?? $now;
            $diff = $since->diffForHumans($now, true, false, 2);

            return 'Active for '.$diff;
        }

        $since = $user->status_changed_at
            ?? $user->created_at
            ?? $now;
        $diff = $since->diffForHumans($now, true, false, 2);

        return 'Inactive for '.$diff;
    }

    public static function recordLogin(User $user): void
    {
        // Do not reset the 14-week active window on login — only Activate does.
        if ($user->status_changed_at === null) {
            $user->status_changed_at = $user->created_at ?? now();
        }

        if ($user->is_active === null) {
            $user->is_active = true;
        }

        $user->save();
    }
}
