<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every foreign key column below had no index covering it as its leading column,
 * which forces a sequential scan on the child table each time the parent row is
 * updated or deleted, and on any join that starts from the parent side.
 */
return new class extends Migration
{
    /**
     * @return array<int, array{0: string, 1: string, 2: string}> [table, column, index name]
     */
    private function foreignKeyIndexes(): array
    {
        return [
            ['reservations', 'user_id', 'reservations_user_id_idx'],
            ['reservation_rooms', 'room_id', 'reservation_rooms_room_id_idx'],
            ['reservation_items', 'item_id', 'reservation_items_item_id_idx'],
            ['reservation_item_units', 'unit_id', 'reservation_item_units_unit_id_idx'],
            ['room_approver_offices', 'office_id', 'room_approver_offices_office_id_idx'],
            ['reservation_approvals', 'approved_by_user_id', 'reservation_approvals_approved_by_user_id_idx'],
            ['reservation_approvals', 'owner_id', 'reservation_approvals_owner_id_idx'],
            ['reservation_approval_histories', 'reservation_id', 'rah_reservation_id_idx'],
            ['reservation_approval_histories', 'office_id', 'rah_office_id_idx'],
            ['reservation_approval_histories', 'approved_by_user_id', 'rah_approved_by_user_id_idx'],
            ['items', 'category_id', 'items_category_id_idx'],
            ['users', 'office_id', 'users_office_id_idx'],
            ['maintenance', 'item_id', 'maintenance_item_id_idx'],
            ['maintenance', 'room_id', 'maintenance_room_id_idx'],
            ['reports', 'user_id', 'reports_user_id_idx'],
            ['reports', 'room_id', 'reports_room_id_idx'],
            ['reports', 'item_id', 'reports_item_id_idx'],
            ['schedule_imports', 'user_id', 'schedule_imports_user_id_idx'],
            ['schedule_import_details', 'import_id', 'schedule_import_details_import_id_idx'],
            ['schedule_import_details', 'reservation_id', 'schedule_import_details_reservation_id_idx'],
            // Created outside migrations; see 2026_08_09_000023 for the tables that only
            // exist in Supabase. Listed here too so a fresh install is covered in one pass.
            ['report_targets', 'report_id', 'report_targets_report_id_idx'],
            ['reports', 'reservation_id', 'reports_reservation_id_idx'],
        ];
    }

    public function up(): void
    {
        foreach ($this->foreignKeyIndexes() as [$table, $column, $indexName]) {
            $this->createIndex($table, $indexName, [$column]);
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Item-owner queue: reservation_details -> reservation_items -> items(owner_id).
        // Carrying reservation_id in the index lets the join finish without touching the heap.
        $this->createRawIndex(
            'reservation_details_items_reservation_idx',
            "CREATE INDEX IF NOT EXISTS reservation_details_items_reservation_idx
             ON reservation_details (reservation_items_id, reservation_id)
             WHERE reservation_items_id IS NOT NULL"
        );
        $this->createRawIndex(
            'reservation_details_rooms_reservation_idx',
            "CREATE INDEX IF NOT EXISTS reservation_details_rooms_reservation_idx
             ON reservation_details (reservation_rooms_id, reservation_id)
             WHERE reservation_rooms_id IS NOT NULL"
        );

        // Superseded by the two covering indexes above; keeping them would only add write cost.
        $this->dropIndex('reservation_details_items_id_idx');
        $this->dropIndex('reservation_details_rooms_id_idx');

        // Partial index matching App\Support\OpenReservationScope exactly, so the planner can
        // prove the query predicate implies the index predicate and skip closed reservations.
        $this->createRawIndex(
            'reservations_open_created_at_idx',
            'CREATE INDEX IF NOT EXISTS reservations_open_created_at_idx
             ON reservations (created_at DESC, reservation_id)
             WHERE ' . \App\Support\OpenReservationScope::rawPredicate('overall_status')
        );

        // Plain btree on overall_status is unusable once every caller normalises with LOWER().
        $this->dropIndex('reservations_status_created_at_idx');
    }

    public function down(): void
    {
        foreach ($this->foreignKeyIndexes() as [, , $indexName]) {
            $this->dropIndex($indexName);
        }

        $this->dropIndex('reservation_details_items_reservation_idx');
        $this->dropIndex('reservation_details_rooms_reservation_idx');
        $this->dropIndex('reservations_open_created_at_idx');
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function createIndex(string $table, string $indexName, array $columns): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        try {
            Schema::table($table, function ($blueprint) use ($indexName, $columns) {
                $blueprint->index($columns, $indexName);
            });
        } catch (\Throwable) {
            // Index already exists.
        }
    }

    private function createRawIndex(string $indexName, string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable) {
            // Index already exists.
        }
    }

    private function dropIndex(string $indexName): void
    {
        try {
            DB::statement("DROP INDEX IF EXISTS {$indexName}");
        } catch (\Throwable) {
            // Not present, or the driver does not support bare index names.
        }
    }
};
