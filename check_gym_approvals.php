<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Reservations with Gym room:\n";
$gymReservations = DB::table('reservations as res')
    ->join('reservation_details as rd', 'rd.reservation_id', '=', 'res.reservation_id')
    ->join('reservation_rooms as rr', 'rr.reservation_rooms_id', '=', 'rd.reservation_rooms_id')
    ->join('rooms', 'rooms.room_id', '=', 'rr.room_id')
    ->where('rooms.room_number', 'Gym')
    ->select('res.reservation_id', 'res.activity_name', 'res.overall_status', 'res.created_at')
    ->get();

foreach ($gymReservations as $res) {
    echo "\nReservation ID: {$res->reservation_id}\n";
    echo "Activity: {$res->activity_name}\n";
    echo "Status: {$res->overall_status}\n";
    echo "Created: {$res->created_at}\n";
    
    // Get approvals for this reservation
    $approvals = DB::table('reservation_approvals')
        ->where('reservation_id', $res->reservation_id)
        ->join('offices', 'offices.office_id', '=', 'reservation_approvals.office_id')
        ->orderBy('reservation_approvals.created_at')
        ->select('offices.short_code', 'offices.department_name')
        ->get();
    
    echo "Approval order:\n";
    foreach ($approvals as $i => $approval) {
        echo "  " . ($i + 1) . ". " . $approval->short_code . " (" . $approval->department_name . ")\n";
    }
    
    if (count($approvals) > 0) {
        echo "==> First office: " . $approvals[0]->short_code . "\n";
        if ($approvals[0]->short_code === 'PC') {
            echo "ISSUE: Going to PC first instead of IO/GENED!\n";
        }
    }
}

if (count($gymReservations) === 0) {
    echo "No gym reservations found\n";
}
?>
