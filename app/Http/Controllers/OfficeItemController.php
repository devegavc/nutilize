<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class OfficeItemController extends Controller
{
    private ?array $equipmentCategoriesCache = null;

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$this->isIoAdmin($user?->office_id, (string) ($user?->username ?? ''), (string) ($user?->role ?? ''))) {
            abort(403);
        }

        $ownerId = $this->resolveOwnerIdForUser($user, true);

        $equipmentCategories = $this->getEquipmentCategories();

        $rows = DB::table('items')
            ->leftJoin('item_owners', 'item_owners.owner_id', '=', 'items.owner_id')
            ->leftJoin('item_categories as categories', 'categories.category_id', '=', 'items.category_id')
            ->select([
                'items.item_id',
                'items.item_name',
                'categories.category_key as category_key',
                'items.quantity_total',
                'items.quantity_in_use',
                'items.maintenance_status',
                'item_owners.owner_name',
            ])
            ->where('items.owner_id', $ownerId)
            ->orderBy('items.item_id')
            ->get()
            ->map(function ($row) {
                $category = $this->normalizeCategory((string) ($row->category_key ?? ''));
                $totalCount = max(0, (int) ($row->quantity_total ?? 0));
                $inUseCount = max(0, min($totalCount, (int) ($row->quantity_in_use ?? 0)));
                $maintenanceCount = (bool) $row->maintenance_status ? 1 : 0;

                $statusKey = 'good';
                $statusLabel = 'Good';

                if ($maintenanceCount > 0) {
                    $statusKey = 'maintenance';
                    $statusLabel = 'Maintenance';
                } elseif ($totalCount > 0 && $inUseCount >= $totalCount) {
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

        return view('office-items', [
            'equipmentRows' => $rows,
            'equipmentCategories' => $equipmentCategories,
            'defaultEquipmentCategory' => $equipmentCategories[0]['key'] ?? '',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->isIoAdmin($user?->office_id, (string) ($user?->username ?? ''), (string) ($user?->role ?? ''))) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $categoryKeys = $this->getEquipmentCategoryKeys();

        if (empty($categoryKeys)) {
            return response()->json([
                'error' => 'No equipment categories configured yet. Ask PF Admin to add categories first.',
            ], 422);
        }

        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in($categoryKeys)],
            'total_count' => ['required', 'integer', 'min:0'],
            'in_use' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:good,maintenance,damaged'],
        ]);

        if ((int) $validated['in_use'] > (int) $validated['total_count']) {
            return response()->json([
                'error' => 'In Use cannot be greater than Total Count.',
            ], 422);
        }

        $ownerId = $this->resolveOwnerIdForUser($user, true);

        $maintenanceStatus = in_array($validated['status'], ['maintenance', 'damaged'], true);
        $availabilityStatus = ((int) $validated['in_use'] <= 0) && !$maintenanceStatus;
        $categoryRecord = $this->resolveEquipmentCategoryByKey((string) $validated['category']);

        if (is_null($categoryRecord)) {
            return response()->json([
                'error' => 'Selected category does not exist.',
            ], 422);
        }

        $databaseCategory = $this->toDatabaseCategory((string) $categoryRecord['key']);

        DB::statement("SELECT setval(pg_get_serial_sequence('items', 'item_id'), COALESCE(MAX(item_id), 0) + 1, false) FROM items");

        $itemInsertPayload = [
            'owner_id' => $ownerId,
            'item_name' => $validated['item_name'],
            'quantity_total' => (int) $validated['total_count'],
            'quantity_in_use' => (int) $validated['in_use'],
            'maintenance_status' => DB::raw($maintenanceStatus ? 'true' : 'false'),
            'availability_status' => DB::raw($availabilityStatus ? 'true' : 'false'),
            'date_reserved' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('items', 'category')) {
            $itemInsertPayload['category'] = $this->toLegacyItemCategoryValue($databaseCategory);
        }

        if (Schema::hasColumn('items', 'category_id')) {
            $itemInsertPayload['category_id'] = (int) $categoryRecord['id'];
        }

        $itemId = DB::table('items')->insertGetId($itemInsertPayload, 'item_id');

        $this->syncItemUnitsForItem(
            (int) $itemId,
            (int) $validated['total_count'],
            (int) $validated['in_use'],
            $validated['status']
        );

        return response()->json([
            'success' => true,
            'item' => [
                'item_id' => (int) $itemId,
                'asset_id' => '#ITEM-' . str_pad((string) $itemId, 4, '0', STR_PAD_LEFT),
                'category' => $this->normalizeCategory($databaseCategory),
                'item_name' => $validated['item_name'],
                'total_count' => (int) $validated['total_count'],
                'in_use' => (int) $validated['in_use'],
                'status_key' => $validated['status'],
                'status_label' => $this->statusLabel($validated['status']),
            ],
        ]);
    }

    public function update(Request $request, int $itemId): JsonResponse
    {
        $user = $request->user();

        if (!$this->isIoAdmin($user?->office_id, (string) ($user?->username ?? ''), (string) ($user?->role ?? ''))) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $categoryKeys = $this->getEquipmentCategoryKeys();

        if (empty($categoryKeys)) {
            return response()->json([
                'error' => 'No equipment categories configured yet. Ask PF Admin to add categories first.',
            ], 422);
        }

        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in($categoryKeys)],
            'total_count' => ['required', 'integer', 'min:0'],
            'in_use' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:good,maintenance,damaged'],
        ]);

        if ((int) $validated['in_use'] > (int) $validated['total_count']) {
            return response()->json([
                'error' => 'In Use cannot be greater than Total Count.',
            ], 422);
        }

        $ownerId = $this->resolveOwnerIdForUser($user, true);

        $item = DB::table('items')
            ->where('item_id', $itemId)
            ->where('owner_id', $ownerId)
            ->first();

        if (!$item) {
            return response()->json([
                'error' => 'Item not found or not assigned to your office.',
            ], 404);
        }

        $maintenanceStatus = in_array($validated['status'], ['maintenance', 'damaged'], true);
        $availabilityStatus = ((int) $validated['in_use'] <= 0) && !$maintenanceStatus;
        $categoryRecord = $this->resolveEquipmentCategoryByKey((string) $validated['category']);

        if (is_null($categoryRecord)) {
            return response()->json([
                'error' => 'Selected category does not exist.',
            ], 422);
        }

        $databaseCategory = $this->toDatabaseCategory((string) $categoryRecord['key']);

        $itemUpdatePayload = [
            'item_name' => $validated['item_name'],
            'quantity_total' => (int) $validated['total_count'],
            'quantity_in_use' => (int) $validated['in_use'],
            'maintenance_status' => DB::raw($maintenanceStatus ? 'true' : 'false'),
            'availability_status' => DB::raw($availabilityStatus ? 'true' : 'false'),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('items', 'category')) {
            $itemUpdatePayload['category'] = $this->toLegacyItemCategoryValue($databaseCategory);
        }

        if (Schema::hasColumn('items', 'category_id')) {
            $itemUpdatePayload['category_id'] = (int) $categoryRecord['id'];
        }

        DB::table('items')
            ->where('item_id', $itemId)
            ->where('owner_id', $ownerId)
            ->update($itemUpdatePayload);

        $this->syncItemUnitsForItem(
            $itemId,
            (int) $validated['total_count'],
            (int) $validated['in_use'],
            $validated['status']
        );

        return response()->json([
            'success' => true,
            'item' => [
                'item_id' => $itemId,
                'asset_id' => '#ITEM-' . str_pad((string) $itemId, 4, '0', STR_PAD_LEFT),
                'category' => $this->normalizeCategory($databaseCategory),
                'item_name' => $validated['item_name'],
                'total_count' => (int) $validated['total_count'],
                'in_use' => (int) $validated['in_use'],
                'status_key' => $validated['status'],
                'status_label' => $this->statusLabel($validated['status']),
            ],
        ]);
    }

    public function destroy(Request $request, int $itemId): JsonResponse
    {
        $user = $request->user();

        if (!$this->isIoAdmin($user?->office_id, (string) ($user?->username ?? ''), (string) ($user?->role ?? ''))) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $ownerId = $this->resolveOwnerIdForUser($user, true);

        $item = DB::table('items')
            ->where('item_id', $itemId)
            ->where('owner_id', $ownerId)
            ->first(['item_id']);

        if (!$item) {
            return response()->json([
                'error' => 'Item not found or not assigned to your office.',
            ], 404);
        }

        if (Schema::hasTable('item_units')) {
            DB::table('item_units')
                ->where('item_id', $itemId)
                ->delete();
        }

        DB::table('items')
            ->where('item_id', $itemId)
            ->where('owner_id', $ownerId)
            ->delete();

        return response()->json([
            'success' => true,
            'deleted_item_id' => (int) $itemId,
        ]);
    }

    public function maintenance(Request $request)
    {
        $user = $request->user();

        if (!$this->isIoAdmin($user?->office_id, (string) ($user?->username ?? ''), (string) ($user?->role ?? ''))) {
            abort(403);
        }

        $ownerId = $this->resolveOwnerIdForUser($user, true);

        $rowsByTab = [
            'maintenance' => [],
            'damaged' => [],
        ];

        if (!Schema::hasTable('item_units')) {
            return view('office-maintenance', [
                'maintenanceRowsByTab' => $rowsByTab,
            ]);
        }

        $unitsNeedingAttention = DB::table('item_units')
            ->join('items', 'items.item_id', '=', 'item_units.item_id')
            ->leftJoin('item_categories as categories', 'categories.category_id', '=', 'items.category_id')
            ->select([
                'item_units.unit_id',
                'item_units.unit_code',
                'item_units.status',
                'item_units.condition_notes',
                'item_units.last_maintenance_at',
                'item_units.updated_at as unit_updated_at',
                'item_units.created_at as unit_created_at',
                'items.item_id',
                'items.item_name',
                'categories.category_key as category_key',
            ])
            ->where('items.owner_id', $ownerId)
            ->whereIn('item_units.status', ['maintenance', 'damaged'])
            ->orderByDesc('item_units.updated_at')
            ->get();

        foreach ($unitsNeedingAttention as $unit) {
            $category = $this->normalizeCategory((string) ($unit->category_key ?? ''));
            $isDamaged = strtolower((string) $unit->status) === 'damaged';
            $tab = $isDamaged ? 'damaged' : 'maintenance';

            $dateSource = $unit->last_maintenance_at ?? $unit->unit_updated_at ?? $unit->unit_created_at;
            $dateLabel = $dateSource ? date('d/m/Y', strtotime((string) $dateSource)) : date('d/m/Y');

            $rowsByTab[$tab][] = [
                'unit_id' => (int) $unit->unit_id,
                'id' => (string) ($unit->unit_code ?? ''),
                'item' => (string) ($unit->item_name ?? 'Unnamed Item'),
                'count' => '1',
                'date' => $dateLabel,
                'status' => $isDamaged ? 'Damaged' : 'Maintenance',
                'statusClass' => $isDamaged ? 'damaged' : 'maintenance',
                'location' => $this->locationFromCategory($category),
                'reason' => (string) ($unit->condition_notes ?? ''),
            ];
        }

        return view('office-maintenance', [
            'maintenanceRowsByTab' => $rowsByTab,
        ]);
    }

    public function updateMaintenanceUnit(Request $request, int $unitId): JsonResponse
    {
        $user = $request->user();

        if (!$this->isIoAdmin($user?->office_id, (string) ($user?->username ?? ''), (string) ($user?->role ?? ''))) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        if (!Schema::hasTable('item_units')) {
            return response()->json([
                'error' => 'Unit tracking table is not available.',
            ], 422);
        }

        $validated = $request->validate([
            'assessment' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:maintenance,damaged,fixed'],
        ]);

        $ownerId = $this->resolveOwnerIdForUser($user, true);

        $unit = DB::table('item_units')
            ->join('items', 'items.item_id', '=', 'item_units.item_id')
            ->leftJoin('item_categories as categories', 'categories.category_id', '=', 'items.category_id')
            ->where('item_units.unit_id', $unitId)
            ->where('items.owner_id', $ownerId)
            ->select([
                'item_units.unit_id',
                'item_units.item_id',
                'item_units.unit_code',
                'items.item_name',
                'categories.category_key as category_key',
            ])
            ->first();

        if (!$unit) {
            return response()->json([
                'error' => 'Item unit not found for your office.',
            ], 404);
        }

        $targetStatus = $validated['status'] === 'fixed' ? 'available' : $validated['status'];

        DB::table('item_units')
            ->where('unit_id', $unitId)
            ->update([
                'status' => $targetStatus,
                'condition_notes' => $validated['assessment'],
                'last_maintenance_at' => in_array($targetStatus, ['maintenance', 'damaged'], true) ? now() : null,
                'updated_at' => now(),
            ]);

        $unitStats = DB::table('item_units')
            ->where('item_id', (int) $unit->item_id)
            ->selectRaw("COUNT(*) FILTER (WHERE status <> 'retired') as total_active")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'in_use') as in_use_count")
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ('maintenance', 'damaged')) as issue_count")
            ->first();

        $totalActive = max(1, (int) ($unitStats->total_active ?? 1));
        $inUseCount = max(0, min($totalActive, (int) ($unitStats->in_use_count ?? 0)));
        $issueCount = (int) ($unitStats->issue_count ?? 0);

        DB::table('items')
            ->where('item_id', (int) $unit->item_id)
            ->where('owner_id', $ownerId)
            ->update([
                'quantity_total' => $totalActive,
                'quantity_in_use' => $inUseCount,
                'maintenance_status' => DB::raw($issueCount > 0 ? 'true' : 'false'),
                'availability_status' => DB::raw(($inUseCount <= 0 && $issueCount <= 0) ? 'true' : 'false'),
                'updated_at' => now(),
            ]);

        $normalizedCategory = $this->normalizeCategory((string) ($unit->category_key ?? ''));
        $isDamaged = $targetStatus === 'damaged';

        return response()->json([
            'success' => true,
            'unit' => [
                'unit_id' => (int) $unit->unit_id,
                'id' => (string) $unit->unit_code,
                'item' => (string) $unit->item_name,
                'count' => '1',
                'date' => date('d/m/Y'),
                'status' => $isDamaged ? 'Damaged' : 'Maintenance',
                'statusClass' => $isDamaged ? 'damaged' : 'maintenance',
                'location' => $this->locationFromCategory($normalizedCategory),
                'reason' => $validated['assessment'],
                'resolved' => $targetStatus === 'available',
            ],
        ]);
    }

    private function isIoAdmin($officeId, string $username, string $role): bool
    {
        if (strtolower($role) !== 'admin' || is_null($officeId)) {
            return false;
        }

        $shortCode = DB::table('offices')
            ->where('office_id', $officeId)
            ->value('short_code');

        return strtolower((string) $shortCode) === 'io' || strtolower($username) === 'io_admin';
    }

    private function resolveOwnerIdForUser($user, bool $createIfMissing): int
    {
        $userId = (int) ($user?->user_id ?? 0);
        $username = trim((string) ($user?->username ?? ''));
        $fullName = trim((string) ($user?->full_name ?? ''));

        if ($userId <= 0 || $username === '') {
            abort(422, 'User details are missing for item ownership mapping.');
        }

        // Per-user ownership key ensures one item owner cannot see another owner's items.
        $ownerKey = 'user:' . $userId;
        $ownerDisplayName = $fullName !== '' ? $fullName : $username;

        $ownerId = DB::table('item_owners')
            ->whereRaw('LOWER(department_affiliation) = ?', [strtolower($ownerKey)])
            ->value('owner_id');

        if ($ownerId) {
            return (int) $ownerId;
        }

        if (!$createIfMissing) {
            abort(422, 'No item owner is configured for this account yet.');
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('item_owners', 'owner_id'), COALESCE(MAX(owner_id), 0) + 1, false) FROM item_owners");

        return (int) DB::table('item_owners')->insertGetId([
            'owner_name' => $ownerDisplayName,
            'department_affiliation' => $ownerKey,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'owner_id');
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
        $value = $this->normalizeCategoryKey($rawCategory);

        if ($value === '') {
            return $this->getEquipmentCategoryKeys()[0] ?? '';
        }

        if (in_array($value, $this->getEquipmentCategoryKeys(), true)) {
            return $value;
        }

        if ($value === 'multi_media' || str_contains($value, 'multimedia') || str_contains($value, 'audio_visual')) {
            return 'multimedia';
        }

        if (str_starts_with($value, 'elect')) {
            return 'electronics';
        }

        if (str_contains($value, 'util') || str_contains($value, 'furnit') || str_contains($value, 'chair') || str_contains($value, 'table')) {
            return 'utility';
        }

        return $value;
    }

    private function toDatabaseCategory(string $category): string
    {
        return $this->normalizeCategory($category);
    }

    private function toLegacyItemCategoryValue(string $categoryKey): ?string
    {
        return match ($categoryKey) {
            'multimedia' => 'MultiMedia',
            'electronics' => 'Electronics',
            'utility' => 'Utility',
            default => null,
        };
    }

    private function normalizeCategoryKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);
        $normalized = trim((string) $normalized, '_');

        return $normalized;
    }

    private function getEquipmentCategories(): array
    {
        if (!is_null($this->equipmentCategoriesCache)) {
            return $this->equipmentCategoriesCache;
        }

        if (!Schema::hasTable('item_categories')) {
            return $this->equipmentCategoriesCache = [];
        }

        $rows = DB::table('item_categories')
            ->whereRaw('is_active = true')
            ->orderBy('display_name')
            ->get(['category_id', 'category_key', 'display_name']);

        if ($rows->isEmpty()) {
            return $this->equipmentCategoriesCache = [];
        }

        $categories = [];

        foreach ($rows as $row) {
            $key = $this->normalizeCategoryKey((string) ($row->category_key ?? ''));
            if ($key === '') {
                continue;
            }

            $categories[] = [
                'id' => (int) $row->category_id,
                'key' => $key,
                'label' => trim((string) ($row->display_name ?? '')) !== ''
                    ? (string) $row->display_name
                    : ucwords(str_replace('_', ' ', $key)),
            ];
        }

        return $this->equipmentCategoriesCache = array_values($categories);
    }

    private function getEquipmentCategoryKeys(): array
    {
        return array_values(array_map(
            fn(array $category) => (string) ($category['key'] ?? ''),
            $this->getEquipmentCategories()
        ));
    }

    private function resolveEquipmentCategoryByKey(string $categoryKey): ?array
    {
        foreach ($this->getEquipmentCategories() as $category) {
            if (($category['key'] ?? '') === $categoryKey) {
                return $category;
            }
        }

        return null;
    }

    private function locationFromCategory(string $category): string
    {
        return match ($category) {
            'electronics' => 'Storage B',
            'utility' => 'Storage C',
            default => 'Storage A',
        };
    }

    private function syncItemUnitsForItem(int $itemId, int $totalCount, int $inUseCount, string $status): void
    {
        if (!Schema::hasTable('item_units')) {
            return;
        }

        $total = max(0, $totalCount);
        $inUseTarget = max(0, min($total, $inUseCount));
        $specialStatus = $status === 'maintenance' ? 'maintenance' : ($status === 'damaged' ? 'damaged' : null);

        $units = DB::table('item_units')
            ->where('item_id', $itemId)
            ->orderBy('unit_number')
            ->get(['unit_id', 'unit_number']);

        $existingByNumber = [];
        foreach ($units as $unit) {
            $existingByNumber[(int) $unit->unit_number] = (int) $unit->unit_id;
        }

        for ($unitNumber = 1; $unitNumber <= $total; $unitNumber++) {
            if (!isset($existingByNumber[$unitNumber])) {
                DB::table('item_units')->insert([
                    'item_id' => $itemId,
                    'unit_number' => $unitNumber,
                    'unit_code' => sprintf('ITM%04d-U%03d', $itemId, $unitNumber),
                    'status' => 'available',
                    'condition_notes' => null,
                    'last_maintenance_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $orderedUnits = DB::table('item_units')
            ->where('item_id', $itemId)
            ->orderBy('unit_number')
            ->get(['unit_id', 'unit_number']);

        $inUseRemaining = $inUseTarget;
        foreach ($orderedUnits as $unit) {
            $unitNumber = (int) $unit->unit_number;
            $unitStatus = 'available';
            $maintenanceAt = null;

            if (!is_null($specialStatus) && $unitNumber === 1) {
                $unitStatus = $specialStatus;
                $maintenanceAt = $specialStatus === 'maintenance' ? now() : null;
            } elseif ($unitNumber <= $total && $inUseRemaining > 0) {
                $unitStatus = 'in_use';
                $inUseRemaining--;
            } elseif ($unitNumber > $total) {
                $unitStatus = 'retired';
            }

            DB::table('item_units')
                ->where('unit_id', (int) $unit->unit_id)
                ->update([
                    'status' => $unitStatus,
                    'last_maintenance_at' => $maintenanceAt,
                    'updated_at' => now(),
                ]);
        }
    }
}