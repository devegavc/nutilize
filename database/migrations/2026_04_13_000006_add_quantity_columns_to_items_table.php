<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('quantity_total')->default(1)->after('category');
            $table->unsignedInteger('quantity_in_use')->default(0)->after('quantity_total');
        });

        DB::table('items')->update([
            'quantity_total' => 1,
            'quantity_in_use' => DB::raw("CASE WHEN availability_status = false THEN 1 ELSE 0 END"),
        ]);
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['quantity_total', 'quantity_in_use']);
        });
    }
};
