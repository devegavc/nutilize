<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('offices')) {
            return;
        }

        DB::table('offices')->updateOrInsert(
            ['short_code' => 'GENED'],
            [
                'department_name' => 'General Education',
                'officer_name' => 'TBD',
                'status_check_type' => 'approval',
                'short_code' => 'GENED',
                'order_sequence' => 2,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('offices')) {
            return;
        }

        DB::table('offices')->where('short_code', 'GENED')->delete();
    }
};
