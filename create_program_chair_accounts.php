<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$programs = [
    ['name' => 'B Multimedia Arts', 'username' => 'MMA_program_chair'],
    ['name' => 'BS Architecture', 'username' => 'Archi_program_chair'],
    ['name' => 'BS Civil Engineering', 'username' => 'CE_program_chair'],
    ['name' => 'BS Computer Science', 'username' => 'CS_program_chair'],
    ['name' => 'BS Computer Engineering', 'username' => 'ComEng_program_chair'],
    ['name' => 'BS Information Technology with specialization in Mobile and Web Applications', 'username' => 'IT_program_chair'],
    ['name' => 'BS Accountancy', 'username' => 'BSA_program_chair'],
    ['name' => 'BSBA Major in Financial Management', 'username' => 'FinMan_program_chair'],
    ['name' => 'BSBA Major in Marketing Management', 'username' => 'BusinessAdd_program_chair'],
    ['name' => 'BS Management Accounting', 'username' => 'Accounting_program_chair'],
    ['name' => 'BS Tourism Management', 'username' => 'TM_program_chair'],
    ['name' => 'BS Psychology', 'username' => 'Psyc_program_chair'],
    ['name' => 'BS Medical Technology', 'username' => 'MedTech_program_chair'],
    ['name' => 'BS Nursing', 'username' => '_program_chair'],
];

$passwordHash = Illuminate\Support\Facades\Hash::make('@Admin123');

foreach ($programs as $program) {
    $academicProgram = Illuminate\Support\Facades\DB::table('academic_programs')
        ->where('name', $program['name'])
        ->first();

    if (!$academicProgram) {
        echo "Missing program: {$program['name']}\n";
        continue;
    }

    $username = $program['username'];
    $email = $username . '@nutilize.local';

    Illuminate\Support\Facades\DB::table('users')->updateOrInsert(
        ['username' => $username],
        [
            'email' => $email,
            'password' => $passwordHash,
            'role' => 'pc_admin',
            'office_id' => $academicProgram->office_id,
            'program_id' => $academicProgram->program_id,
            'first_name' => $academicProgram->name,
            'last_name' => 'Chair',
            'full_name' => $academicProgram->name . ' Chair',
            'updated_at' => now(),
            'created_at' => now(),
        ]
    );

    echo "Created/updated {$username}\n";
}
