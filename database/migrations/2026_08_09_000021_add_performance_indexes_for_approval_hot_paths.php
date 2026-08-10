<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Partial / expression indexes are Postgres/Supabase-oriented and are the main win.
        if (DB::getDriverName() !== 'pgsql') {
            $this->addPortableIndexes();

            return;
        }

        $this->createIndexIfMissing(
            'reservation_approvals_office_pending_idx',
            'CREATE INDEX IF NOT EXISTS reservation_approvals_office_pending_idx
             ON reservation_approvals (office_id, reservation_id)
             WHERE approved_at IS NULL'
        );

        $this->createIndexIfMissing(
            'reservation_approvals_reservation_office_idx',
            'CREATE INDEX IF NOT EXISTS reservation_approvals_reservation_office_idx
             ON reservation_approvals (reservation_id, office_id)'
        );

        if (Schema::hasColumn('reservation_approvals', 'owner_id')) {
            $this->createIndexIfMissing(
                'reservation_approvals_office_owner_pending_idx',
                'CREATE INDEX IF NOT EXISTS reservation_approvals_office_owner_pending_idx
                 ON reservation_approvals (office_id, owner_id, reservation_id)
                 WHERE approved_at IS NULL'
            );
        }

        $this->createIndexIfMissing(
            'reservation_approvals_office_status_approved_at_idx',
            'CREATE INDEX IF NOT EXISTS reservation_approvals_office_status_approved_at_idx
             ON reservation_approvals (office_id, status, approved_at)'
        );

        $this->createIndexIfMissing(
            'reservations_created_at_idx',
            'CREATE INDEX IF NOT EXISTS reservations_created_at_idx
             ON reservations (created_at DESC)'
        );

        $this->createIndexIfMissing(
            'reservations_overall_status_lower_idx',
            'CREATE INDEX IF NOT EXISTS reservations_overall_status_lower_idx
             ON reservations ((LOWER(COALESCE(overall_status, \'\'))))'
        );

        $this->createIndexIfMissing(
            'reservations_status_created_at_idx',
            'CREATE INDEX IF NOT EXISTS reservations_status_created_at_idx
             ON reservations (overall_status, created_at DESC)'
        );

        $this->createIndexIfMissing(
            'reservation_details_reservation_id_idx',
            'CREATE INDEX IF NOT EXISTS reservation_details_reservation_id_idx
             ON reservation_details (reservation_id)'
        );

        $this->createIndexIfMissing(
            'reservation_details_items_id_idx',
            'CREATE INDEX IF NOT EXISTS reservation_details_items_id_idx
             ON reservation_details (reservation_items_id)
             WHERE reservation_items_id IS NOT NULL'
        );

        $this->createIndexIfMissing(
            'reservation_details_rooms_id_idx',
            'CREATE INDEX IF NOT EXISTS reservation_details_rooms_id_idx
             ON reservation_details (reservation_rooms_id)
             WHERE reservation_rooms_id IS NOT NULL'
        );

        $this->createIndexIfMissing(
            'items_owner_id_idx',
            'CREATE INDEX IF NOT EXISTS items_owner_id_idx
             ON items (owner_id)'
        );

        if (Schema::hasColumn('item_owners', 'user_id')) {
            $this->createIndexIfMissing(
                'item_owners_user_id_idx',
                'CREATE INDEX IF NOT EXISTS item_owners_user_id_idx
                 ON item_owners (user_id)
                 WHERE user_id IS NOT NULL'
            );
        }

        $this->createIndexIfMissing(
            'notifications_user_created_at_idx',
            'CREATE INDEX IF NOT EXISTS notifications_user_created_at_idx
             ON notifications (user_id, created_at DESC)'
        );

        $this->createIndexIfMissing(
            'notifications_user_unread_idx',
            'CREATE INDEX IF NOT EXISTS notifications_user_unread_idx
             ON notifications (user_id)
             WHERE read = false'
        );

        $this->createIndexIfMissing(
            'notifications_user_related_type_idx',
            'CREATE INDEX IF NOT EXISTS notifications_user_related_type_idx
             ON notifications (user_id, related_id, type)'
        );

        $this->createIndexIfMissing(
            'offices_short_code_lower_idx',
            'CREATE INDEX IF NOT EXISTS offices_short_code_lower_idx
             ON offices ((LOWER(TRIM(short_code))))
             WHERE short_code IS NOT NULL'
        );

        if (Schema::hasColumn('users', 'program_id')) {
            $this->createIndexIfMissing(
                'users_program_id_idx',
                'CREATE INDEX IF NOT EXISTS users_program_id_idx
                 ON users (program_id)
                 WHERE program_id IS NOT NULL'
            );
        }

        if (Schema::hasTable('academic_programs') && Schema::hasColumn('academic_programs', 'office_id')) {
            $this->createIndexIfMissing(
                'academic_programs_office_id_idx',
                'CREATE INDEX IF NOT EXISTS academic_programs_office_id_idx
                 ON academic_programs (office_id)
                 WHERE office_id IS NOT NULL'
            );
        }
    }

    public function down(): void
    {
        $indexes = [
            'reservation_approvals_office_pending_idx',
            'reservation_approvals_reservation_office_idx',
            'reservation_approvals_office_owner_pending_idx',
            'reservation_approvals_office_status_approved_at_idx',
            'reservations_created_at_idx',
            'reservations_overall_status_lower_idx',
            'reservations_status_created_at_idx',
            'reservation_details_reservation_id_idx',
            'reservation_details_items_id_idx',
            'reservation_details_rooms_id_idx',
            'items_owner_id_idx',
            'item_owners_user_id_idx',
            'notifications_user_created_at_idx',
            'notifications_user_unread_idx',
            'notifications_user_related_type_idx',
            'offices_short_code_lower_idx',
            'users_program_id_idx',
            'academic_programs_office_id_idx',
            'reservation_approvals_office_pending_portable_idx',
            'reservation_approvals_reservation_office_portable_idx',
            'notifications_user_created_portable_idx',
            'reservation_details_reservation_portable_idx',
            'items_owner_portable_idx',
        ];

        foreach ($indexes as $index) {
            try {
                DB::statement("DROP INDEX IF EXISTS {$index}");
            } catch (\Throwable) {
                // Ignore missing indexes / unsupported drivers.
            }
        }
    }

    private function addPortableIndexes(): void
    {
        $this->createPortableIndex('reservation_approvals', 'reservation_approvals_office_pending_portable_idx', ['office_id', 'approved_at', 'reservation_id']);
        $this->createPortableIndex('reservation_approvals', 'reservation_approvals_reservation_office_portable_idx', ['reservation_id', 'office_id']);
        $this->createPortableIndex('notifications', 'notifications_user_created_portable_idx', ['user_id', 'created_at']);
        $this->createPortableIndex('reservation_details', 'reservation_details_reservation_portable_idx', ['reservation_id']);
        $this->createPortableIndex('items', 'items_owner_portable_idx', ['owner_id']);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function createPortableIndex(string $table, string $indexName, array $columns): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function ($blueprint) use ($indexName, $columns) {
                $blueprint->index($columns, $indexName);
            });
        } catch (\Throwable) {
            // Index may already exist.
        }
    }

    private function createIndexIfMissing(string $indexName, string $sql): void
    {
        $exists = DB::selectOne(
            'SELECT 1 AS present FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ? LIMIT 1',
            [$indexName]
        );

        if ($exists) {
            return;
        }

        try {
            DB::statement($sql);
        } catch (\Throwable) {
            // Index may already exist under a different detection path.
        }
    }
};
