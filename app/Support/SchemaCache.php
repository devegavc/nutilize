<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Caches Postgres catalog lookups (`Schema::hasTable`, `Schema::hasColumn`).
 *
 * The application calls those helpers defensively in well over a hundred places,
 * several of them inside per-reservation loops. Against a remote Supabase instance
 * each call is a full network round trip, so a single office queue page was spending
 * most of its wall time asking Postgres about its own structure.
 *
 * The cache is bypassed while migrations run, and flushed when they finish. If the
 * schema is changed directly in the Supabase console instead, entries expire on their
 * own within the TTL below, or `php artisan cache:clear` picks the change up at once.
 */
class SchemaCache
{
    private const VERSION_KEY = 'schema-cache.version';
    private const TTL_MINUTES = 15;

    /** @var array<string, mixed> Per-request memo, avoids re-reading the cache file. */
    private static array $memo = [];

    private static ?int $version = null;

    private static bool $bypassed = false;

    public static function remember(string $key, Closure $resolver): mixed
    {
        if (self::$bypassed) {
            return $resolver();
        }

        $key = 'schema-cache.v' . self::version() . '.' . $key;

        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        try {
            return self::$memo[$key] = Cache::remember(
                $key,
                now()->addMinutes(self::TTL_MINUTES),
                $resolver
            );
        } catch (Throwable) {
            // Never let a cache problem hide the real schema.
            return $resolver();
        }
    }

    /**
     * Stop caching for the rest of the process. Used while migrations are in flight,
     * where the schema is changing underneath us.
     */
    public static function bypass(): void
    {
        self::$bypassed = true;
        self::$memo = [];
    }

    /**
     * Invalidate every cached answer by moving to a new version namespace, which avoids
     * having to enumerate keys on cache drivers that cannot do tag lookups.
     */
    public static function flush(): void
    {
        self::$memo = [];

        try {
            $next = self::readVersion() + 1;
            Cache::forever(self::VERSION_KEY, $next);
            self::$version = $next;
        } catch (Throwable) {
            // A cache failure must never break a migration.
        }
    }

    private static function version(): int
    {
        return self::$version ??= self::readVersion();
    }

    private static function readVersion(): int
    {
        try {
            return (int) Cache::get(self::VERSION_KEY, 1);
        } catch (Throwable) {
            return 1;
        }
    }
}
