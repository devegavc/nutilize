<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            if (Schema::getColumnType('reservation_approvals', 'approved_at') === 'date') {
                Schema::table('reservation_approvals', function ($table) {
                    $table->dateTime('approved_at')->nullable()->change();
                });
            }

            return;
        }

        DB::statement('ALTER TABLE reservation_approvals ALTER COLUMN approved_at TYPE timestamp(0) without time zone USING approved_at::timestamp(0)');

        if (Schema::hasTable('reservation_approval_histories')) {
            DB::statement("
                UPDATE reservation_approval_histories
                SET approved_at = ((approved_at AT TIME ZONE 'UTC') AT TIME ZONE 'Asia/Manila')
                WHERE approved_at IS NOT NULL
            ");

            DB::statement('
                UPDATE reservation_approvals AS approvals
                SET approved_at = history.approved_at
                FROM reservation_approval_histories AS history
                WHERE history.approval_id = approvals.approval_id
                  AND history.approved_at IS NOT NULL
            ');

            DB::statement("
                UPDATE reservation_approvals AS approvals
                SET approved_at = ((approvals.updated_at AT TIME ZONE 'UTC') AT TIME ZONE 'Asia/Manila')
                WHERE approvals.approved_at IS NOT NULL
                  AND approvals.updated_at IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1
                      FROM reservation_approval_histories AS history
                      WHERE history.approval_id = approvals.approval_id
                        AND history.approved_at IS NOT NULL
                  )
            ");

            return;
        }

        DB::statement("
            UPDATE reservation_approvals AS approvals
            SET approved_at = ((approvals.updated_at AT TIME ZONE 'UTC') AT TIME ZONE 'Asia/Manila')
            WHERE approvals.approved_at IS NOT NULL
              AND approvals.updated_at IS NOT NULL
        ");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasTable('reservation_approval_histories')) {
            DB::statement("
                UPDATE reservation_approval_histories
                SET approved_at = ((approved_at AT TIME ZONE 'Asia/Manila') AT TIME ZONE 'UTC')
                WHERE approved_at IS NOT NULL
            ");
        }

        DB::statement('ALTER TABLE reservation_approvals ALTER COLUMN approved_at TYPE date USING approved_at::date');
    }
};
