<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicProgramSeeder extends Seeder
{
    public function run(): void
    {
        $programs = [
            ['code' => 'b_multimedia_arts', 'school' => 'School of Architecture, Computing and Engineering', 'name' => 'B Multimedia Arts', 'sort' => 1],
            ['code' => 'bs_architecture', 'school' => 'School of Architecture, Computing and Engineering', 'name' => 'BS Architecture', 'sort' => 2],
            ['code' => 'bs_civil_engineering', 'school' => 'School of Architecture, Computing and Engineering', 'name' => 'BS Civil Engineering', 'sort' => 3],
            ['code' => 'bs_computer_science', 'school' => 'School of Architecture, Computing and Engineering', 'name' => 'BS Computer Science', 'sort' => 4],
            ['code' => 'bs_computer_engineering', 'school' => 'School of Architecture, Computing and Engineering', 'name' => 'BS Computer Engineering', 'sort' => 5],
            ['code' => 'bs_it_mobile_web', 'school' => 'School of Architecture, Computing and Engineering', 'name' => 'BS Information Technology with specialization in Mobile and Web Applications', 'sort' => 6],
            ['code' => 'bs_accountancy', 'school' => 'School of Accountancy, Business and Management', 'name' => 'BS Accountancy', 'sort' => 7],
            ['code' => 'bsba_financial_management', 'school' => 'School of Accountancy, Business and Management', 'name' => 'BSBA Major in Financial Management', 'sort' => 8],
            ['code' => 'bsba_marketing_management', 'school' => 'School of Accountancy, Business and Management', 'name' => 'BSBA Major in Marketing Management', 'sort' => 9],
            ['code' => 'bs_management_accounting', 'school' => 'School of Accountancy, Business and Management', 'name' => 'BS Management Accounting', 'sort' => 10],
            ['code' => 'bs_tourism_management', 'school' => 'School of Accountancy, Business and Management', 'name' => 'BS Tourism Management', 'sort' => 11],
            ['code' => 'bs_psychology', 'school' => 'School of Allied Health and Sciences', 'name' => 'BS Psychology', 'sort' => 12],
            ['code' => 'bs_medical_technology', 'school' => 'School of Allied Health and Sciences', 'name' => 'BS Medical Technology', 'sort' => 13],
            ['code' => 'bs_nursing', 'school' => 'School of Allied Health and Sciences', 'name' => 'BS Nursing', 'sort' => 14],
        ];

        $pcOrderSequence = (int) (DB::table('offices')
            ->whereRaw('LOWER(TRIM(department_name)) = ?', ['program chair'])
            ->value('order_sequence') ?? 3);

        foreach ($programs as $program) {
            DB::table('offices')->updateOrInsert(
                ['department_name' => $program['name']],
                [
                    'officer_name' => 'TBD',
                    'status_check_type' => 'approval',
                    'short_code' => 'PC',
                    'order_sequence' => $pcOrderSequence,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $officeId = (int) DB::table('offices')
                ->where('department_name', $program['name'])
                ->value('office_id');

            DB::table('academic_programs')->updateOrInsert(
                ['code' => $program['code']],
                [
                    'school_name' => $program['school'],
                    'name' => $program['name'],
                    'office_id' => $officeId,
                    'sort_order' => $program['sort'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
