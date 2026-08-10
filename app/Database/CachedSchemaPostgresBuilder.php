<?php

namespace App\Database;

use App\Support\SchemaCache;
use Illuminate\Database\Schema\PostgresBuilder;

/**
 * `hasColumn()` and `hasColumns()` both resolve through `getColumns()`, so caching
 * `hasTable()` and `getColumns()` covers every schema check the application makes.
 */
class CachedSchemaPostgresBuilder extends PostgresBuilder
{
    public function hasTable($table)
    {
        return SchemaCache::remember(
            'has-table.' . $table,
            fn () => parent::hasTable($table)
        );
    }

    public function getColumns($table)
    {
        return SchemaCache::remember(
            'columns.' . $table,
            fn () => parent::getColumns($table)
        );
    }
}
