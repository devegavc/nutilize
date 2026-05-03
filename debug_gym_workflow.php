<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Creating test gym reservation...\n";

// Get a regular user (not admin)
$user = DB::table('users')->where('role', '!=', 'admin')->first();
// Get Gym room - it's stored as "Gym" with capital G
$room = DB::table('rooms')->where('room_id', 26)->first();

if (!$user) {
    echo "No regular users found\n";
    exit(1);
}
if (!$room) {
    echo "Gym room not found\n";
    exit(1);
}

echo "Using User ID: {$user->user_id}, Room ID: {$room->room_id} ({$room->room_number})\n\n";

$resId = DB::table('reservations')->insertGetId([
    'user_id' => $user->user_id,
    'activity_name' => 'Test Gym Request',
    'overall_status' => 'pending',
    'created_at' => now(),
    'updated_at' => now()
], 'reservation_id');

echo "Created Reservation ID: $resId\n";

// Add room to reservation_rooms
$roomResId = DB::table('reservation_rooms')->insertGetId([
    'room_id' => $room->room_id,
    'created_at' => now(),
    'updated_at' => now()
], 'reservation_rooms_id');

// Link it through reservation_details
DB::table('reservation_details')->insert([
    'reservation_id' => $resId,
    'reservation_rooms_id' => $roomResId,
    'quantity' => 1,
    'created_at' => now(),
    'updated_at' => now()
]);

echo "\nFetching approval workflow...\n";

// Call the sync function from ApprovalController to create approvals
$controller = app('App\Http\Controllers\ApprovalController');
$controller->syncReservationApprovals($resId);

// Check workflow
$approvals = DB::table('reservation_approvals')
    ->where('reservation_id', $resId)
    ->join('offices', 'offices.office_id', '=', 'reservation_approvals.office_id')
    ->orderBy('reservation_approvals.created_at')
    ->select('offices.short_code', 'offices.department_name', 'reservation_approvals.created_at')
    ->get();

echo "Number of approval steps: " . count($approvals) . "\n\n";

if (count($approvals) === 0) {
    echo "ERROR: No approvals were created!\n";
    echo "The submitReservation or createApprovalNotifications function may not have been called.\n";
    exit(1);
}

echo "Approval workflow order:\n";
foreach ($approvals as $approval) {
    echo "  " . $approval->short_code . " (" . $approval->department_name . ") - created: " . $approval->created_at . "\n";
}

echo "\nExpected order for gym (room only, no items): IO, GENED, PC, SDAO, DO, SEC\n";
echo "Expected order for gym (with items): IO, GENED, PC, SDAO, DO, SEC\n";

$firstOffice = $approvals->first();
if ($firstOffice) {
    echo "\n==> First office: " . $firstOffice->short_code . "\n";
    if ($firstOffice->short_code === 'PC') {
        echo "ISSUE: Going to PC first instead of IO/GENED!\n";
    } elseif ($firstOffice->short_code === 'IO') {
        echo "CORRECT: Starting at IO\n";
    } elseif ($firstOffice->short_code === 'GENED') {
        echo "CORRECT: Starting at GENED (no items case)\n";
    }
}
?>
