<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Serialises the approval-workflow reconciliation passes.
 *
 * These syncs walk dozens of reservations and issue many small queries each. When
 * several approvers load their queue at the same moment they used to run the same
 * work in parallel, which is what produced the `statement timeout` and
 * `remaining connection slots are reserved` errors in the Postgres log.
 *
 * The work is opportunistic — approvals already create their own notifications on
 * handoff — so a caller that cannot get the lock simply skips this round instead of
 * queueing up behind the running one.
 */
class HeavySyncGate
{
    private const GLOBAL_LOCK = 'heavy-sync.global';

    /**
     * Run $callback only if no other request is running the same job, and no other
     * heavy sync is running at all.
     *
     * @return bool true when the callback ran, false when it was skipped.
     */
    public static function attempt(string $key, Closure $callback, int $maxSeconds = 60): bool
    {
        $globalLock = Cache::lock(self::GLOBAL_LOCK, $maxSeconds);

        if (!$globalLock->get()) {
            return false;
        }

        try {
            $jobLock = Cache::lock('heavy-sync.' . $key, $maxSeconds);

            if (!$jobLock->get()) {
                return false;
            }

            try {
                $callback();
            } finally {
                self::release($jobLock);
            }

            return true;
        } finally {
            self::release($globalLock);
        }
    }

    private static function release(mixed $lock): void
    {
        try {
            $lock->release();
        } catch (Throwable) {
            // Lock already expired; it will be reclaimed by the next caller.
        }
    }
}
