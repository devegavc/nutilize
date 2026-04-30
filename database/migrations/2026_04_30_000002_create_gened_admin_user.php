<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('offices') || !Schema::hasTable('users')) {
            return;
        }

        $officeId = DB::table('offices')
            ->whereRaw('LOWER(TRIM(department_name)) = ?', ['general education'])
            ->orWhere('short_code', 'GENED')
            ->value('office_id');

        if (is_null($officeId)) {
            return;
        }

        DB::table('users')->updateOrInsert(
            ['email' => 'gened.admin@nutilize.local'],
            [
                'username' => 'gened_admin',
                'password' => Hash::make('@Admin123'),
                'role' => 'admin',
                'office_id' => $officeId,
                'first_name' => 'General',
                'last_name' => 'Education',
                'full_name' => 'General Education Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        DB::table('users')->where('email', 'gened.admin@nutilize.local')->delete();
    }
};
