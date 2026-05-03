<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$resId = 1;

echo "Detailed inspection of Reservation ID $resId:\n\n";

// Check reservation
$res = DB::table('reservations')->where('reservation_id', $resId)->first();
echo "Reservation: {$res->activity_name} ({$res->overall_status})\n\n";

// Check reservation_details
echo "Reservation Details:\n";
$details = DB::table('reservation_details')->where('reservation_id', $resId)->get();
foreach ($details as $d) {
    echo "  Detail ID: {$d->detail_id}\n";
    echo "  - reservation_rooms_id: {$d->reservation_rooms_id}\n";
    echo "  - reservation_items_id: {$d->reservation_items_id}\n";
}

echo "\nReservation Rooms:\n";
$rooms = DB::table('reservation_details as rd')
    ->leftJoin('reservation_rooms as rr', 'rr.reservation_rooms_id', '=', 'rd.reservation_rooms_id')
    ->leftJoin('rooms', 'rooms.room_id', '=', 'rr.room_id')
    ->where('rd.reservation_id', $resId)
    ->select('rr.room_id', 'rr.reservation_rooms_id', 'rooms.room_number', DB::raw("LOWER(TRIM(rooms.room_number)) as room_lower"))
    ->get();

foreach ($rooms as $r) {
    echo "  Room ID: {$r->room_id}\n";
    echo "  - Name: '{$r->room_number}'\n";
    echo "  - Lower: '{$r->room_lower}'\n";
    echo "  - Matches 'gym': " . ($r->room_lower === 'gym' ? 'YES' : 'NO') . "\n";
}

echo "\nReservation Items:\n";
$items = DB::table('reservation_details as rd')
    ->leftJoin('reservation_items as ri', 'ri.reservation_items_id', '=', 'rd.reservation_items_id')
    ->leftJoin('items', 'items.item_id', '=', 'ri.item_id')
    ->where('rd.reservation_id', $resId)
    ->select('ri.reservation_items_id', 'items.item_id', 'items.item_name')
    ->get();

if (count($items) === 0) {
    echo "  (No items)\n";
} else {
    foreach ($items as $i) {
        echo "  Item ID: {$i->item_id}\n";
        echo "  - Name: {$i->item_name}\n";
    }
}

echo "\nTesting isGymRoomRequest query directly:\n";
$isGym = DB::table('reservation_details as details')
    ->join('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
    ->join('rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
    ->where('details.reservation_id', $resId)
    ->whereRaw('LOWER(TRIM(rooms.room_number)) = ?', ['gym'])
    ->exists();
    
echo "Is gym request: " . ($isGym ? 'YES' : 'NO') . "\n";

echo "\nTesting isGymRoomRequestWithItems query directly:\n";
$hasGymRoom = DB::table('reservation_details as details')
    ->join('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
    ->join('rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
    ->where('details.reservation_id', $resId)
    ->whereRaw('LOWER(TRIM(rooms.room_number)) = ?', ['gym'])
    ->exists();
    
echo "Has gym room: " . ($hasGymRoom ? 'YES' : 'NO') . "\n";

if ($hasGymRoom) {
    $hasItems = DB::table('reservation_details as details')
        ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
        ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
        ->where('details.reservation_id', $resId)
        ->whereNotNull('items.item_id')
        ->exists();
    echo "Has items: " . ($hasItems ? 'YES' : 'NO') . "\n";
}
?>
