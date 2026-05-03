<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Database Status:\n";
echo "Users: " . DB::table('users')->count() . "\n";
echo "Rooms: " . DB::table('rooms')->count() . "\n";
echo "Reservations: " . DB::table('reservations')->count() . "\n";
echo "Offices: " . DB::table('offices')->count() . "\n\n";

if (DB::table('users')->count() > 0) {
    echo "Sample users:\n";
    DB::table('users')->limit(3)->get(['user_id', 'email', 'role'])->each(function($u) {
        echo "  ID: {$u->user_id}, Email: {$u->email}, Role: {$u->role}\n";
    });
}

if (DB::table('rooms')->count() > 0) {
    echo "\nAll rooms:\n";
    DB::table('rooms')->get(['room_id', 'room_number'])->each(function($r) {
        echo "  ID: {$r->room_id}, Room: {$r->room_number}\n";
    });
}

if (DB::table('offices')->count() > 0) {
    echo "\nAll offices:\n";
    DB::table('offices')->orderBy('short_code')->get(['office_id', 'short_code', 'department_name'])->each(function($o) {
        echo "  ID: {$o->office_id}, Code: {$o->short_code}, Name: {$o->department_name}\n";
    });
}
