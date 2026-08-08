<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('items') || !Schema::hasColumn('items', 'item_code')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(['item_code']);
            $table->dropColumn('item_code');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('items') || Schema::hasColumn('items', 'item_code')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->string('item_code', 64)->nullable()->after('item_name');
        });
    }
};
