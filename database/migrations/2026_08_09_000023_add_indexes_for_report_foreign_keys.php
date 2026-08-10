<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `reports` and `report_targets` were created directly against Supabase rather than
 * through a migration, so their foreign keys were never given covering indexes.
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

            try {
                Schema::table($table, function ($blueprint) use ($column, $indexName) {
                    $blueprint->index([$column], $indexName);
                });
            } catch (\Throwable) {
                // Index already exists.
            }
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
