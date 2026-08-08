<?php

namespace App\Services;

use App\Support\ItemAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ItemUnitService
{
    /**
     * Normalize admin input and fill any missing slots with unique temporary codes.
     *
     * @param  array<int, string>|list<string|null>  $rawCodes
     * @return list<string>
     */
    public static function resolveUnitCodes(array $rawCodes, int $expectedCount, int $itemId, ?int $ignoreItemId = null): array
    {
        $expectedCount = max(0, $expectedCount);

        if ($expectedCount === 0) {
            return [];
        }

        $existingCodes = ($ignoreItemId && $ignoreItemId === $itemId)
            ? self::loadUnitCodesForItem($itemId)
            : [];

        $resolved = [];

        for ($index = 0; $index < $expectedCount; $index++) {
            $provided = ItemAsset::normalizeCode((string) ($rawCodes[$index] ?? ''));
            $existing = ItemAsset::normalizeCode((string) ($existingCodes[$index] ?? ''));

            if ($provided !== '') {
                $resolved[] = $provided;
                continue;
            }

            if ($existing !== '') {
                $resolved[] = $existing;
                continue;
            }

            $resolved[] = self::generateUniqueTemporaryCode($itemId, $index + 1, $resolved);
        }

        self::assertUnitCodesAreValid($resolved, $itemId, $ignoreItemId);

        return $resolved;
    }

    /**
     * @param  list<string>  $codes
     */
    private static function assertUnitCodesAreValid(array $codes, int $itemId, ?int $ignoreItemId = null): void
    {
        if ($codes === []) {
            return;
        }

        if (count($codes) !== count(array_unique($codes))) {
            throw ValidationException::withMessages([
                'unit_codes' => 'Each physical unit needs a unique code.',
            ]);
        }

        if (!Schema::hasTable('item_units')) {
            return;
        }

        $conflictQuery = DB::table('item_units')->whereIn('unit_code', $codes);

        if (!is_null($ignoreItemId)) {
            $conflictQuery->where('item_id', '<>', $ignoreItemId);
        }

        $conflict = $conflictQuery->value('unit_code');

        if (!is_null($conflict)) {
            throw ValidationException::withMessages([
                'unit_codes' => "Unit code \"{$conflict}\" is already assigned to another item.",
            ]);
        }
    }

    /** @param  list<string>  $reservedCodes */
    private static function generateUniqueTemporaryCode(int $itemId, int $unitNumber, array $reservedCodes = []): string
    {
        $reserved = array_fill_keys($reservedCodes, true);
        $candidate = ItemAsset::temporaryUnitCode($itemId, $unitNumber);
        $attempt = 0;

        while ($attempt < 1000) {
            if (!isset($reserved[$candidate]) && !self::unitCodeExists($candidate, $itemId)) {
                return $candidate;
            }

            $attempt++;
            $candidate = ItemAsset::temporaryUnitCode($itemId, $unitNumber) . '-' . $attempt;
        }

        return ItemAsset::temporaryUnitCode($itemId, $unitNumber) . '-' . uniqid();
    }

    private static function unitCodeExists(string $code, ?int $ignoreItemId = null): bool
    {
        if (!Schema::hasTable('item_units')) {
            return false;
        }

        $query = DB::table('item_units')->where('unit_code', $code);

        if (!is_null($ignoreItemId)) {
            $query->where('item_id', '<>', $ignoreItemId);
        }

        return $query->exists();
    }

    /** @return list<string> */
    public static function loadUnitCodesForItem(int $itemId): array
    {
        if (!Schema::hasTable('item_units')) {
            return [];
        }

        return DB::table('item_units')
            ->where('item_id', $itemId)
            ->where('status', '<>', 'retired')
            ->orderBy('unit_number')
            ->pluck('unit_code')
            ->map(fn ($code) => (string) $code)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, list<string>>
     */
    public static function loadUnitCodesGroupedByItem(array $itemIds): array
    {
        if (!Schema::hasTable('item_units') || $itemIds === []) {
            return [];
        }

        $grouped = [];

        foreach ($itemIds as $itemId) {
            $grouped[(int) $itemId] = [];
        }

        $rows = DB::table('item_units')
            ->whereIn('item_id', $itemIds)
            ->where('status', '<>', 'retired')
            ->orderBy('item_id')
            ->orderBy('unit_number')
            ->get(['item_id', 'unit_code']);

        foreach ($rows as $row) {
            $itemId = (int) $row->item_id;
            $grouped[$itemId][] = (string) $row->unit_code;
        }

        return $grouped;
    }

    public static function defaultBackfillUnitCode(int $itemId, int $unitNumber, int $totalUnits): string
    {
        $base = ItemAsset::fallbackCode($itemId);

        if ($totalUnits <= 1) {
            return $base;
        }

        return $base . '-' . str_pad((string) max(1, $unitNumber), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Create or align item_units rows whenever an item is created or updated.
     * This is the runtime equivalent of `php artisan items:sync-units` for a single item.
     *
     * @param  array<int, string|null>  $rawUnitCodes
     * @return list<string>
     */
    public static function ensureUnitsForItem(
        int $itemId,
        array $rawUnitCodes = [],
        ?int $totalCount = null,
        ?int $inUseCount = null,
        ?string $status = null,
        bool $ensureAtLeastOneUnit = false,
    ): array {
        if (!Schema::hasTable('items') || !Schema::hasTable('item_units')) {
            return [];
        }

        $item = DB::table('items')->where('item_id', $itemId)->first();

        if (!$item) {
            return [];
        }

        $total = max(0, $totalCount ?? (int) ($item->quantity_total ?? 0));

        if ($ensureAtLeastOneUnit && $total === 0) {
            $total = 1;
        }

        $inUse = max(0, min($total, $inUseCount ?? (int) ($item->quantity_in_use ?? 0)));
        $resolvedStatus = $status ?? ((bool) ($item->maintenance_status ?? false) ? 'maintenance' : 'good');

        if ($rawUnitCodes === [] && $total > 0) {
            $existingCodes = self::loadUnitCodesForItem($itemId);
            $rawUnitCodes = array_pad($existingCodes, $total, null);
        }

        $unitCodes = self::resolveUnitCodes($rawUnitCodes, $total, $itemId, $itemId);
        self::syncForItem($itemId, $total, $inUse, $resolvedStatus, $unitCodes);

        return $unitCodes;
    }

    /**
     * Ensure every item has item_units rows and statuses aligned with items.quantity_* fields.
     *
     * @return array<string, mixed>
     */
    public static function backfillMissingUnitsForAllItems(bool $dryRun = false): array
    {
        if (!Schema::hasTable('items') || !Schema::hasTable('item_units')) {
            return [
                'items_scanned' => 0,
                'items_updated' => 0,
                'units_created' => 0,
                'details' => [],
            ];
        }

        $stats = [
            'items_scanned' => 0,
            'items_updated' => 0,
            'units_created' => 0,
            'details' => [],
        ];

        $items = DB::table('items')->orderBy('item_id')->get();

        foreach ($items as $item) {
            $itemId = (int) $item->item_id;
            $stats['items_scanned']++;

            $total = max(0, (int) ($item->quantity_total ?? 0));
            if ($total === 0) {
                $total = 1;
            }

            $existingCodes = self::loadUnitCodesForItem($itemId);
            $missingUnits = max(0, $total - count($existingCodes));

            if ($missingUnits > 0 || count($existingCodes) !== $total) {
                $stats['items_updated']++;
                $stats['units_created'] += $missingUnits;
                $stats['details'][] = [
                    'item_id' => $itemId,
                    'item_name' => (string) ($item->item_name ?? ''),
                    'quantity_total' => $total,
                    'existing_units' => count($existingCodes),
                    'units_created' => $missingUnits,
                ];
            }

            if (!$dryRun) {
                self::ensureUnitsForItem(
                    $itemId,
                    [],
                    $total,
                    (int) ($item->quantity_in_use ?? 0),
                    (bool) ($item->maintenance_status ?? false) ? 'maintenance' : 'good',
                    ensureAtLeastOneUnit: true,
                );
            }
        }

        return $stats;
    }

    public static function catalogItemCode(int $itemId, array $unitCodes): string
    {
        if (count($unitCodes) === 1) {
            return ItemAsset::normalizeCode((string) $unitCodes[0]);
        }

        return ItemAsset::fallbackCode($itemId);
    }

    public static function listAssetLabel(array $unitCodes, int $itemId): string
    {
        $unitCodes = array_values(array_filter(array_map(
            fn ($code) => ItemAsset::normalizeCode((string) $code),
            $unitCodes
        )));

        if ($unitCodes === []) {
            return ItemAsset::fallbackCode($itemId);
        }

        if (count($unitCodes) === 1) {
            return $unitCodes[0];
        }

        $first = $unitCodes[0];
        $temporaryCount = count(array_filter($unitCodes, fn ($code) => ItemAsset::isTemporaryCode($code)));

        if ($temporaryCount === count($unitCodes)) {
            return ItemAsset::fallbackCode($itemId) . ' (' . count($unitCodes) . ' temp units)';
        }

        return $first . ' (+' . (count($unitCodes) - 1) . ' units)';
    }

    /**
     * @param  list<string>  $unitCodes
     */
    public static function syncForItem(int $itemId, int $totalCount, int $inUseCount, string $status, array $unitCodes): void
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
            $unitCode = ItemAsset::normalizeCode((string) ($unitCodes[$unitNumber - 1] ?? ''));

            if ($unitCode === '') {
                $unitCode = self::generateUniqueTemporaryCode($itemId, $unitNumber);
            }

            if (!isset($existingByNumber[$unitNumber])) {
                DB::table('item_units')->insert([
                    'item_id' => $itemId,
                    'unit_number' => $unitNumber,
                    'unit_code' => $unitCode,
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

            $updatePayload = [
                'status' => $unitStatus,
                'last_maintenance_at' => $maintenanceAt,
                'updated_at' => now(),
            ];

            if ($unitNumber <= $total) {
                $unitCode = ItemAsset::normalizeCode((string) ($unitCodes[$unitNumber - 1] ?? ''));
                if ($unitCode !== '') {
                    $updatePayload['unit_code'] = $unitCode;
                }
            }

            DB::table('item_units')
                ->where('unit_id', (int) $unit->unit_id)
                ->update($updatePayload);
        }
    }
}
