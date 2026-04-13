<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FacilitiesRoomsSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement("SELECT setval(pg_get_serial_sequence('rooms', 'room_id'), COALESCE(MAX(room_id), 0) + 1, false) FROM rooms");

        $rooms = [
            ['room_number' => '501', 'room_type' => 'Classroom'],
            ['room_number' => '502', 'room_type' => 'Classroom'],
            ['room_number' => '503', 'room_type' => 'Classroom'],
            ['room_number' => '504', 'room_type' => 'Classroom'],
            ['room_number' => '505', 'room_type' => 'Classroom'],
            ['room_number' => '506', 'room_type' => 'Classroom'],
            ['room_number' => '507', 'room_type' => 'Classroom'],
            ['room_number' => '508', 'room_type' => 'Classroom'],
            ['room_number' => '509', 'room_type' => 'Classroom'],
            ['room_number' => '510', 'room_type' => 'Classroom'],
            ['room_number' => '511', 'room_type' => 'Classroom'],
            ['room_number' => '512', 'room_type' => 'Classroom'],
            ['room_number' => '630', 'room_type' => 'Computer Lab'],
            ['room_number' => '631', 'room_type' => 'Computer Lab'],
            ['room_number' => '632', 'room_type' => 'Computer Lab'],
            ['room_number' => '633', 'room_type' => 'Computer Lab'],
            ['room_number' => '615', 'room_type' => 'Computer Lab'],
            ['room_number' => '616', 'room_type' => 'Computer Lab'],
            ['room_number' => '617', 'room_type' => 'Computer Lab'],
            ['room_number' => '618', 'room_type' => 'Computer Lab'],
            ['room_number' => 'Gym', 'room_type' => 'Gymnasium'],
            ['room_number' => 'AVR', 'room_type' => 'Events Place'],
            ['room_number' => 'Library', 'room_type' => 'Library'],
            ['room_number' => 'Canteen', 'room_type' => 'Canteen'],
            ['room_number' => 'Student Lounge', 'room_type' => 'Lounge'],
            ['room_number' => 'Ground', 'room_type' => 'Ground'],
        ];

        foreach ($rooms as $room) {
            DB::table('rooms')->updateOrInsert(
                ['room_number' => $room['room_number']],
                [
                    'room_type' => $room['room_type'],
                    'maintenance_status' => false,
                    'availability_status' => true,
                    'date_reserved' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
