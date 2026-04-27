<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OfficeAdminSeeder extends Seeder
{
    public function run(): void
    {
        $passwordHash = Hash::make('@Admin123');

        $offices = DB::table('offices')
            ->select(['office_id', 'department_name', 'short_code'])
            ->orderBy('order_sequence')
            ->orderBy('office_id')
            ->get();

        foreach ($offices as $office) {
            $baseUsername = Str::lower($office->short_code ?: Str::slug($office->department_name, '_'));
            $username = Str::limit($baseUsername.'_admin', 50, '');
            $emailLocal = Str::limit($baseUsername.'.admin', 64, '');
            $email = $emailLocal.'@nutilize.local';

            DB::table('users')->updateOrInsert(
                ['email' => $email],
                [
                    'username' => $username,
                    'password' => $passwordHash,
                    'role' => 'admin',
                    'office_id' => $office->office_id,
                    'first_name' => $office->department_name,
                    'last_name' => 'Admin',
                    'full_name' => $office->department_name.' Admin',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}