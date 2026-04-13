<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardInventoryController extends Controller
{
    public function facilities()
    {
        $roomRows = DB::table('rooms')
            ->select([
                'room_id',
                'room_number',
                'room_type',
            ])
            ->orderBy('room_number')
            ->get()
            ->map(function ($room) {
                $roomNumber = (string) ($room->room_number ?? 'N/A');
                $roomType = (string) ($room->room_type ?? $this->deriveFacilityType($roomNumber));
                $classification = $this->normalizeFacilityCategory($roomType, $roomNumber);

                return [
                    'room_id' => (int) $room->room_id,
                    'asset_id' => '#ROOM-' . str_pad((string) $room->room_id, 4, '0', STR_PAD_LEFT),
                    'item_name' => preg_match('/^\d+$/', $roomNumber) === 1 ? 'Room ' . $roomNumber : $roomNumber,
                    'room_type' => $roomType,
                    'classification' => $this->facilityCategoryLabel($classification),
                    'classification_key' => $classification,
                    'location' => $this->locationFromRoomNumber($roomNumber, $classification),
                ];
            });

        return view('dashboard-inventory-facilities', [
            'facilityRows' => $roomRows,
        ]);
    }

    public function storeFacility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:rooms,lab,others'],
        ]);

        $roomNumber = trim($validated['item_name']);
        $roomType = $this->roomTypeFromFacilityCategory($validated['category']);

        $insertPayload = [
            'room_number' => $roomNumber,
            'room_type' => $roomType,
            'maintenance_status' => DB::raw('false'),
            'availability_status' => DB::raw('true'),
            'date_reserved' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $template = DB::table('rooms')->orderBy('room_id')->first();

        if (Schema::hasColumn('rooms', 'room_capacity')) {
            $insertPayload['room_capacity'] = max(1, (int) ($template->room_capacity ?? 1));
        }

        if (Schema::hasColumn('rooms', 'room_chair_quantity')) {
            $insertPayload['room_chair_quantity'] = max(1, (int) ($template->room_chair_quantity ?? 1));
        }

        if (Schema::hasColumn('rooms', 'room_table_type')) {
            $insertPayload['room_table_type'] = (string) ($template->room_table_type ?? 'Triangular_Table');
        }

        if (Schema::hasColumn('rooms', 'room_table_count')) {
            $insertPayload['room_table_count'] = max(1, (int) ($template->room_table_count ?? 1));
        }

        $roomId = DB::table('rooms')->insertGetId($insertPayload, 'room_id');

        return response()->json([
            'success' => true,
            'facility' => [
                'room_id' => $roomId,
                'asset_id' => '#ROOM-' . str_pad((string) $roomId, 4, '0', STR_PAD_LEFT),
                'item_name' => $roomNumber,
                'room_type' => $roomType,
                'classification' => $this->facilityCategoryLabel($validated['category']),
                'classification_key' => $validated['category'],
                'location' => $this->locationFromRoomNumber($roomNumber, $validated['category']),
            ],
        ]);
    }

    public function updateFacility(Request $request, int $roomId): JsonResponse
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:rooms,lab,others'],
        ]);

        $room = DB::table('rooms')->where('room_id', $roomId)->first();

        if (!$room) {
            return response()->json([
                'error' => 'Facility room not found.',
            ], 404);
        }

        $roomType = $this->roomTypeFromFacilityCategory($validated['category']);

        DB::table('rooms')
            ->where('room_id', $roomId)
            ->update([
                'room_number' => trim($validated['item_name']),
                'room_type' => $roomType,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'facility' => [
                'room_id' => $roomId,
                'asset_id' => '#ROOM-' . str_pad((string) $roomId, 4, '0', STR_PAD_LEFT),
                'item_name' => trim($validated['item_name']),
                'room_type' => $roomType,
                'classification' => $this->facilityCategoryLabel($validated['category']),
                'classification_key' => $validated['category'],
                'location' => $this->locationFromRoomNumber(trim($validated['item_name']), $validated['category']),
            ],
        ]);
    }

    public function maintenance()
    {
        $itemsNeedingAttention = DB::table('items')
            ->select([
                'item_id',
                'item_name',
                'category',
                'quantity_total',
                'quantity_in_use',
                'maintenance_status',
                'updated_at',
                'created_at',
            ])
            ->whereRaw('maintenance_status = true OR quantity_in_use >= quantity_total')
            ->orderByDesc('updated_at')
            ->get();

        $rowsByTab = [
            'maintenance' => [],
            'damaged' => [],
        ];

        foreach ($itemsNeedingAttention as $item) {
            $category = $this->normalizeCategory((string) ($item->category ?? 'multimedia'));
            $quantityTotal = max(1, (int) ($item->quantity_total ?? 1));
            $quantityInUse = max(0, min($quantityTotal, (int) ($item->quantity_in_use ?? 0)));
            $isDamaged = $quantityTotal > 0 && $quantityInUse >= $quantityTotal;
            $tab = $isDamaged ? 'damaged' : 'maintenance';

            $dateSource = $item->updated_at ?? $item->created_at;
            $dateLabel = $dateSource ? date('d/m/Y', strtotime((string) $dateSource)) : date('d/m/Y');

            $rowsByTab[$tab][] = [
                'id' => '#ITEM-' . str_pad((string) $item->item_id, 4, '0', STR_PAD_LEFT),
                'item' => (string) ($item->item_name ?? 'Unnamed Item'),
                'count' => (string) $quantityTotal,
                'date' => $dateLabel,
                'status' => $isDamaged ? 'Damaged' : 'Maintenance',
                'statusClass' => $isDamaged ? 'damaged' : 'maintenance',
                'location' => $this->locationFromCategory($category),
            ];
        }

        return view('dashboard-maintenance', [
            'maintenanceRowsByTab' => $rowsByTab,
        ]);
    }

    public function equipments()
    {
        $rows = DB::table('items')
            ->leftJoin('item_owners', 'item_owners.owner_id', '=', 'items.owner_id')
            ->select([
                'items.item_id',
                'items.item_name',
                'items.category',
                'items.quantity_total',
                'items.quantity_in_use',
                'items.maintenance_status',
                'items.availability_status',
                'item_owners.owner_name',
            ])
            ->orderBy('items.item_id')
            ->get()
            ->map(function ($row) {
                $category = $this->normalizeCategory((string) ($row->category ?? 'multimedia'));
                $totalCount = max(1, (int) ($row->quantity_total ?? 1));
                $inUseCount = max(0, min($totalCount, (int) ($row->quantity_in_use ?? 0)));
                $maintenanceCount = (bool) $row->maintenance_status ? 1 : 0;

                $statusKey = 'good';
                $statusLabel = 'Good';

                if ($maintenanceCount > 0) {
                    $statusKey = 'maintenance';
                    $statusLabel = 'Maintenance';
                } elseif ($inUseCount >= $totalCount) {
                    $statusKey = 'damaged';
                    $statusLabel = 'Damaged';
                }

                return [
                    'item_id' => (int) $row->item_id,
                    'asset_id' => '#ITEM-' . str_pad((string) $row->item_id, 4, '0', STR_PAD_LEFT),
                    'category' => $category,
                    'item_name' => (string) ($row->item_name ?? 'Unnamed Item'),
                    'total_count' => $totalCount,
                    'in_use' => $inUseCount,
                    'status_key' => $statusKey,
                    'status_label' => $statusLabel,
                    'owner_name' => $row->owner_name,
                ];
            });

        return view('dashboard-inventory-equipments', [
            'equipmentRows' => $rows,
        ]);
    }

    public function updateEquipment(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:multimedia,electronics,utility'],
            'total_count' => ['required', 'integer', 'min:1'],
            'in_use' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:good,maintenance,damaged'],
        ]);

        if ((int) $validated['in_use'] > (int) $validated['total_count']) {
            return response()->json([
                'error' => 'In Use cannot be greater than Total Count.',
            ], 422);
        }

        $item = DB::table('items')->where('item_id', $itemId)->first();

        if (!$item) {
            return response()->json([
                'error' => 'Equipment item not found.',
            ], 404);
        }

        $maintenanceStatus = in_array($validated['status'], ['maintenance', 'damaged'], true);
        $availabilityStatus = ((int) $validated['in_use'] <= 0) && !$maintenanceStatus;
        $databaseCategory = $this->toDatabaseCategory($validated['category']);

        DB::table('items')
            ->where('item_id', $itemId)
            ->update([
                'item_name' => $validated['item_name'],
                'category' => $databaseCategory,
                'quantity_total' => (int) $validated['total_count'],
                'quantity_in_use' => (int) $validated['in_use'],
                'maintenance_status' => DB::raw($maintenanceStatus ? 'true' : 'false'),
                'availability_status' => DB::raw($availabilityStatus ? 'true' : 'false'),
                'updated_at' => now(),
            ]);

        $normalizedCategory = $this->normalizeCategory($databaseCategory);
        return response()->json([
            'success' => true,
            'item' => [
                'item_id' => $itemId,
                'asset_id' => '#ITEM-' . str_pad((string) $itemId, 4, '0', STR_PAD_LEFT),
                'category' => $normalizedCategory,
                'item_name' => $validated['item_name'],
                'total_count' => (int) $validated['total_count'],
                'in_use' => (int) $validated['in_use'],
                'status_key' => $validated['status'],
                'status_label' => $this->statusLabel($validated['status']),
            ],
        ]);
    }

    public function storeEquipment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:multimedia,electronics,utility'],
            'total_count' => ['required', 'integer', 'min:1'],
            'in_use' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:good,maintenance,damaged'],
        ]);

        if ((int) $validated['in_use'] > (int) $validated['total_count']) {
            return response()->json([
                'error' => 'In Use cannot be greater than Total Count.',
            ], 422);
        }

        $ownerId = DB::table('item_owners')->orderBy('owner_id')->value('owner_id');

        if (!$ownerId) {
            return response()->json([
                'error' => 'No item owner found. Please create an owner first.',
            ], 422);
        }

        $maintenanceStatus = in_array($validated['status'], ['maintenance', 'damaged'], true);
        $availabilityStatus = ((int) $validated['in_use'] <= 0) && !$maintenanceStatus;
        $databaseCategory = $this->toDatabaseCategory($validated['category']);

        // Keep PostgreSQL sequence aligned with existing data to avoid duplicate key errors.
        DB::statement("SELECT setval(pg_get_serial_sequence('items', 'item_id'), COALESCE(MAX(item_id), 0) + 1, false) FROM items");

        $itemId = DB::table('items')->insertGetId([
            'owner_id' => (int) $ownerId,
            'item_name' => $validated['item_name'],
            'category' => $databaseCategory,
            'quantity_total' => (int) $validated['total_count'],
            'quantity_in_use' => (int) $validated['in_use'],
            'maintenance_status' => DB::raw($maintenanceStatus ? 'true' : 'false'),
            'availability_status' => DB::raw($availabilityStatus ? 'true' : 'false'),
            'date_reserved' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'item_id');

        $normalizedCategory = $this->normalizeCategory($databaseCategory);

        return response()->json([
            'success' => true,
            'item' => [
                'item_id' => (int) $itemId,
                'asset_id' => '#ITEM-' . str_pad((string) $itemId, 4, '0', STR_PAD_LEFT),
                'category' => $normalizedCategory,
                'item_name' => $validated['item_name'],
                'total_count' => (int) $validated['total_count'],
                'in_use' => (int) $validated['in_use'],
                'status_key' => $validated['status'],
                'status_label' => $this->statusLabel($validated['status']),
            ],
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'maintenance' => 'Maintenance',
            'damaged' => 'Damaged',
            default => 'Good',
        };
    }

    private function normalizeCategory(string $rawCategory): string
    {
        $value = strtolower(trim($rawCategory));

        if (str_contains($value, 'elect')) {
            return 'electronics';
        }

        if (str_contains($value, 'util') || str_contains($value, 'furnit') || str_contains($value, 'chair') || str_contains($value, 'table')) {
            return 'utility';
        }

        return 'multimedia';
    }

    private function toDatabaseCategory(string $category): string
    {
        return match ($category) {
            'electronics' => 'Electronics',
            'utility' => 'Utility',
            default => 'MultiMedia',
        };
    }

    private function roomTypeFromFacilityCategory(string $category): ?string
    {
        return match ($category) {
            'lab' => 'Laboratory',
            'others' => null,
            default => 'Classroom',
        };
    }

    private function deriveFacilityType(string $roomNumber): string
    {
        $normalized = strtolower(trim($roomNumber));

        if (str_contains($normalized, 'lab') || preg_match('/^6\d{2}$/', $normalized) === 1) {
            return 'Computer Lab';
        }

        if ($normalized === 'gym') {
            return 'Gymnasium';
        }

        if ($normalized === 'avr') {
            return 'Events Place';
        }

        if ($normalized === 'library') {
            return 'Library';
        }

        if ($normalized === 'canteen') {
            return 'Canteen';
        }

        if ($normalized === 'student lounge') {
            return 'Lounge';
        }

        if ($normalized === 'ground') {
            return 'Ground';
        }

        return 'Classroom';
    }

    private function locationFromCategory(string $category): string
    {
        return match ($category) {
            'electronics' => 'Storage B',
            'utility' => 'Storage C',
            default => 'Storage A',
        };
    }

    private function facilityCategoryLabel(string $category): string
    {
        return match ($category) {
            'lab' => 'Lab',
            'others' => 'Others',
            default => 'Rooms',
        };
    }

    private function normalizeFacilityCategory(string $roomType, string $roomNumber = ''): string
    {
        $value = strtolower(trim($roomType . ' ' . $roomNumber));

        if (str_contains($value, 'lab') || preg_match('/^6\d{2}$/', trim($roomNumber)) === 1) {
            return 'lab';
        }

        if (str_contains($value, 'gym') || str_contains($value, 'avr') || str_contains($value, 'library') || str_contains($value, 'canteen') || str_contains($value, 'lounge') || str_contains($value, 'ground')) {
            return 'others';
        }

        return 'rooms';
    }

    private function locationFromRoomNumber(string $roomNumber, ?string $category = null): string
    {
        $normalized = strtolower(trim($roomNumber));

        if ($category === 'lab' || str_contains($normalized, 'lab') || preg_match('/^6\d{2}$/', $normalized) === 1) {
            return 'Sixth Floor';
        }

        if ($normalized === 'gym' || $normalized === 'avr') {
            return 'Sixth Floor';
        }

        if ($normalized === 'library' || $normalized === 'canteen' || $normalized === 'student lounge' || $normalized === 'ground') {
            return $normalized === 'ground' ? 'Ground Floor' : 'Fifth Floor';
        }

        if (preg_match('/^(\d)/', $normalized, $matches) !== 1) {
            return 'Ground Floor';
        }

        return match ((int) $matches[1]) {
            1 => 'First Floor',
            2 => 'Second Floor',
            3 => 'Third Floor',
            4 => 'Fourth Floor',
            5 => 'Fifth Floor',
            6 => 'Sixth Floor',
            default => 'Ground Floor',
        };
    }
}
