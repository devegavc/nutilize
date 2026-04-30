<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OfficeSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement("SELECT setval(pg_get_serial_sequence('offices', 'office_id'), COALESCE(MAX(office_id), 0) + 1, false) FROM offices");

        $offices = [
            ['department_name' => 'Item Owner', 'officer_name' => 'TBD', 'short_code' => 'IO', 'order_sequence' => 1],
            ['department_name' => 'General Education', 'officer_name' => 'TBD', 'short_code' => 'GENED', 'order_sequence' => 2],
            ['department_name' => 'Program Chair', 'officer_name' => 'TBD', 'short_code' => 'PC', 'order_sequence' => 3],
            ['department_name' => 'SDAO', 'officer_name' => 'TBD', 'short_code' => 'SDAO', 'order_sequence' => 4],
            ['department_name' => 'DO', 'officer_name' => 'TBD', 'short_code' => 'DO', 'order_sequence' => 5],
            ['department_name' => 'Security', 'officer_name' => 'TBD', 'short_code' => 'SEC', 'order_sequence' => 6],
        ];

        foreach ($offices as $office) {
            DB::table('offices')->updateOrInsert(
                ['department_name' => $office['department_name']],
                [
                    'officer_name' => $office['officer_name'],
                    'status_check_type' => 'approval',
                    'short_code' => $office['short_code'],
                    'order_sequence' => $office['order_sequence'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}