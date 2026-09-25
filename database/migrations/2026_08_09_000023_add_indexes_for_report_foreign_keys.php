<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Covering indexes for report foreign keys. The columns are created in the
 * original reservation-system migration; this only adds the indexes.
 */
return new class extends Migration
{
    /**
     * @return array<int, array{0: string, 1: string, 2: string}> [table, column, index name]
     */
    private function indexes(): array
    {
        return [
            ['report_targets', 'report_id', 'report_targets_report_id_idx'],
            ['reports', 'reservation_id', 'reports_reservation_id_idx'],
        ];
    }

    public function up(): void
    {
        foreach ($this->indexes() as [$table, $column, $indexName]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            $alreadyExists = DB::selectOne(
                'select 1 as present from pg_indexes where schemaname = current_schema() and indexname = ?',
                [$indexName]
            );
            if ($alreadyExists) {
                continue;
            }

            Schema::table($table, function ($blueprint) use ($column, $indexName) {
                $blueprint->index([$column], $indexName);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes() as [, , $indexName]) {
            try {
                DB::statement("DROP INDEX IF EXISTS {$indexName}");
            } catch (\Throwable) {
                // Not present.
            }
        }
    }
};
