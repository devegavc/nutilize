<?php

namespace App\Providers;

use App\Database\CachedSchemaPostgresConnection;
use App\Support\SchemaCache;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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
    }
}
