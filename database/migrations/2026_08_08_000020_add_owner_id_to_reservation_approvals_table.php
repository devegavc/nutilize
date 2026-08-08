<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reservation_approvals') || Schema::hasColumn('reservation_approvals', 'owner_id')) {
            return;
        }

        Schema::table('reservation_approvals', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->after('office_id');
            $table->foreign('owner_id')
                ->references('owner_id')
                ->on('item_owners')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('reservation_approvals') || !Schema::hasColumn('reservation_approvals', 'owner_id')) {
            return;
        }

        Schema::table('reservation_approvals', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
