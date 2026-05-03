<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$controller = app('App\Http\Controllers\ApprovalController');

$reflection = new ReflectionClass($controller);

$methods = ['getOfficeIdsByShortCode', 'getActionSequenceOfficeIds', 'resolveWorkflowOfficeIds', 'isGymRoomRequest', 'isGymRoomRequestWithItems'];

foreach ($methods as $methodName) {
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);
}

$officeIds = $reflection->getMethod('getOfficeIdsByShortCode')->invoke($controller);
echo "Office IDs by short code:\n";
foreach ($officeIds as $code => $id) {
    echo "  $code => $id\n";
}

echo "\nAction sequence office IDs:\n";
$seq = $reflection->getMethod('getActionSequenceOfficeIds')->invoke($controller);
echo "  [" . implode(', ', $seq) . "]\n";

$resId = 1;
echo "\nReservation $resId gym flag checks:\n";
echo "  isGymRoomRequest: " . ($reflection->getMethod('isGymRoomRequest')->invoke($controller, $resId) ? 'YES' : 'NO') . "\n";
echo "  isGymRoomRequestWithItems: " . ($reflection->getMethod('isGymRoomRequestWithItems')->invoke($controller, $resId) ? 'YES' : 'NO') . "\n";

echo "\nResolved workflow office IDs for reservation $resId:\n";
$workflow = $reflection->getMethod('resolveWorkflowOfficeIds')->invoke($controller, $resId, true);
echo "  [" . implode(', ', $workflow) . "]\n";

echo "\nLookup names:\n";
if (!empty($workflow)) {
    $rows = DB::table('offices')->whereIn('office_id', $workflow)->select('office_id', 'short_code')->get();
    foreach ($rows as $row) {
        echo "  " . $row->office_id . " => " . $row->short_code . "\n";
    }
}
?>