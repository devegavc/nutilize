<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservation_approvals') && !Schema::hasColumn('reservation_approvals', 'rejection_reason')) {
            Schema::table('reservation_approvals', function (Blueprint $table) {
                $table->string('rejection_reason', 500)->nullable()->after('status');
            });
        }

        if (Schema::hasTable('reservation_approval_histories') && !Schema::hasColumn('reservation_approval_histories', 'rejection_reason')) {
            Schema::table('reservation_approval_histories', function (Blueprint $table) {
                $table->string('rejection_reason', 500)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reservation_approvals') && Schema::hasColumn('reservation_approvals', 'rejection_reason')) {
            Schema::table('reservation_approvals', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }

        if (Schema::hasTable('reservation_approval_histories') && Schema::hasColumn('reservation_approval_histories', 'rejection_reason')) {
            Schema::table('reservation_approval_histories', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};
