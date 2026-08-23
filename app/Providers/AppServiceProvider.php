<?php

namespace App\Providers;

use App\Database\CachedSchemaPostgresConnection;
use App\Support\SchemaCache;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Must be registered before the first connection is resolved.
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new CachedSchemaPostgresConnection($connection, $database, $prefix, $config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(MigrationsStarted::class, static fn () => SchemaCache::bypass());
        Event::listen(MigrationsEnded::class, static fn () => SchemaCache::flush());

        $this->ensureDatabaseCacheTables();
    }

    /**
     * Production uses CACHE_STORE=database, but this app never shipped a cache
     * migration. Create the tables on boot so dashboard caching does not 500.
     */
    private function ensureDatabaseCacheTables(): void
    {
        if (config('cache.default') !== 'database') {
            return;
        }

        try {
            $created = false;

            if (! Schema::hasTable('cache')) {
                Schema::create('cache', function (Blueprint $table) {
                    $table->string('key')->primary();
                    $table->mediumText('value');
                    $table->integer('expiration')->index();
                });
                $created = true;
            }

            if (! Schema::hasTable('cache_locks')) {
                Schema::create('cache_locks', function (Blueprint $table) {
                    $table->string('key')->primary();
                    $table->string('owner');
                    $table->integer('expiration')->index();
                });
                $created = true;
            }

            // #region agent log
            \App\Support\AgentDebugLog::write(
                'AppServiceProvider.php:ensureDatabaseCacheTables',
                $created ? 'created missing cache tables' : 'cache tables already present',
                array_merge(\App\Support\AgentDebugLog::snapshot(), ['created' => $created]),
                'A',
                'post-fix'
            );
            // #endregion
        } catch (Throwable $throwable) {
            config(['cache.default' => 'file']);
            $this->app->forgetInstance('cache');
            $this->app->forgetInstance('cache.store');

            // #region agent log
            \App\Support\AgentDebugLog::write(
                'AppServiceProvider.php:ensureDatabaseCacheTables',
                'cache table ensure failed; fell back to file cache',
                \App\Support\AgentDebugLog::snapshot($throwable),
                'A',
                'post-fix'
            );
            // #endregion
        }
    }
}
