<?php

namespace App\Services;

use App\Support\ItemAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ItemUnitService
{
    private const UNIT_UPSERT_CHUNK = 100;

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
        $needsTemporary = [];

        for ($index = 0; $index < $expectedCount; $index++) {
            $provided = ItemAsset::normalizeCode((string) ($rawCodes[$index] ?? ''));
            $existing = ItemAsset::normalizeCode((string) ($existingCodes[$index] ?? ''));

            if ($provided !== '') {
                $resolved[$index] = $provided;
                continue;
            }

            if ($existing !== '') {
                $resolved[$index] = $existing;
                continue;
            }

            $needsTemporary[$index] = $index + 1;
            $resolved[$index] = '';
        }

        if ($needsTemporary !== []) {
            $taken = array_fill_keys(array_filter($resolved), true);
            $generated = self::generateUniqueTemporaryCodes($itemId, $needsTemporary, $taken, $ignoreItemId);

            foreach ($generated as $index => $code) {
                $resolved[$index] = $code;
            }
        }

        $resolved = array_values($resolved);
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

    /**
     * Generate many temporary codes with a single conflict lookup instead of one DB hit per unit.
     *
     * @param  array<int, int>  $unitNumbersByIndex  index => unit_number
     * @param  array<string, true>  $reserved
     * @return array<int, string>
     */
    private static function generateUniqueTemporaryCodes(
        int $itemId,
        array $unitNumbersByIndex,
        array $reserved,
        ?int $ignoreItemId = null,
    ): array {
        $candidates = [];

        foreach ($unitNumbersByIndex as $index => $unitNumber) {
            $candidates[$index] = ItemAsset::temporaryUnitCode($itemId, $unitNumber);
        }

        $taken = $reserved;
        foreach (self::existingUnitCodesAmong(array_values($candidates), $ignoreItemId) as $code) {
            $taken[$code] = true;
        }

        $generated = [];

        foreach ($unitNumbersByIndex as $index => $unitNumber) {
            $candidate = ItemAsset::temporaryUnitCode($itemId, $unitNumber);
            $attempt = 0;

            while ($attempt < 1000 && isset($taken[$candidate])) {
                $attempt++;
                $candidate = ItemAsset::temporaryUnitCode($itemId, $unitNumber) . '-' . $attempt;
            }

            if (isset($taken[$candidate])) {
                $candidate = ItemAsset::temporaryUnitCode($itemId, $unitNumber) . '-' . uniqid();
            }

            $taken[$candidate] = true;
            $generated[$index] = $candidate;
        }

        return $generated;
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private static function existingUnitCodesAmong(array $codes, ?int $ignoreItemId = null): array
    {
        if (!Schema::hasTable('item_units') || $codes === []) {
            return [];
        }

        $query = DB::table('item_units')->whereIn('unit_code', $codes);

        if (!is_null($ignoreItemId)) {
            $query->where('item_id', '<>', $ignoreItemId);
        }

        return $query
            ->pluck('unit_code')
            ->map(fn ($code) => (string) $code)
            ->all();
    }

    /** @param  list<string>  $reservedCodes */
    private static function generateUniqueTemporaryCode(int $itemId, int $unitNumber, array $reservedCodes = []): string
    {
        $generated = self::generateUniqueTemporaryCodes(
            $itemId,
            [0 => $unitNumber],
            array_fill_keys($reservedCodes, true),
            null,
        );

        return $generated[0];
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
     * Codes keyed by zero-based index for unit_number 1..$upToUnitNumber.
     * Includes retired rows so raising quantity again reuses the same physical codes.
     *
     * @return list<string|null>
     */
    private static function loadUnitCodesByNumber(int $itemId, int $upToUnitNumber): array
    {
        if (!Schema::hasTable('item_units') || $upToUnitNumber <= 0) {
            return [];
        }

        $codes = array_fill(0, $upToUnitNumber, null);

        $rows = DB::table('item_units')
            ->where('item_id', $itemId)
            ->where('unit_number', '<=', $upToUnitNumber)
            ->orderBy('unit_number')
            ->get(['unit_number', 'unit_code']);

        foreach ($rows as $row) {
            $index = ((int) $row->unit_number) - 1;
            if ($index >= 0 && $index < $upToUnitNumber) {
                $codes[$index] = (string) $row->unit_code;
            }
        }

        return $codes;
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

        if ($total > 0) {
            // Prefer codes already on file (including retired slots) so raising quantity
            // reactivates the same physical units instead of inventing new codes.
            $existingByNumber = self::loadUnitCodesByNumber($itemId, $total);

            if ($rawUnitCodes === []) {
                $rawUnitCodes = $existingByNumber;
            } else {
                for ($index = 0; $index < $total; $index++) {
                    $provided = ItemAsset::normalizeCode((string) ($rawUnitCodes[$index] ?? ''));
                    if ($provided === '' && !empty($existingByNumber[$index])) {
                        $rawUnitCodes[$index] = $existingByNumber[$index];
                    }
                }
            }
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
     * Align item_units with the requested quantity using bulk upserts.
     *
     * Rules for units inside the active quantity (1..total):
     * - New or previously retired units become `available` (borrowable again).
     * - Units a PF admin marked `maintenance` / `damaged` keep that status.
     * - `in_use` is assigned only to units that are not under repair/damage.
     *
     * Units beyond the active quantity stay/become `retired` (not borrowable).
     *
     * @param  list<string>  $unitCodes
     */
    public static function syncForItem(int $itemId, int $totalCount, int $inUseCount, string $status, array $unitCodes): void
    {
        if (!Schema::hasTable('item_units')) {
            return;
        }

        $total = max(0, $totalCount);
        $inUseTarget = max(0, min($total, $inUseCount));
        $itemLevelIssue = $status === 'maintenance' ? 'maintenance' : ($status === 'damaged' ? 'damaged' : null);
        $now = now();

        if ($total === 0) {
            // No active stock — remove physical unit rows rather than leaving a pile of
            // `retired` leftovers that look like broken inventory in the database.
            DB::table('item_units')->where('item_id', $itemId)->delete();

            return;
        }

        $existing = DB::table('item_units')
            ->where('item_id', $itemId)
            ->get(['unit_id', 'unit_number', 'unit_code', 'status', 'condition_notes', 'last_maintenance_at']);

        $existingByNumber = [];
        foreach ($existing as $unit) {
            $existingByNumber[(int) $unit->unit_number] = $unit;
        }

        // Preserve per-unit maintenance/damaged only when the equipment form is not
        // explicitly setting the item back to Good. Choosing Good means PF cleared it.
        $preservedIssueByNumber = [];
        $clearIssues = strtolower($status) === 'good';

        if (!$clearIssues) {
            for ($unitNumber = 1; $unitNumber <= $total; $unitNumber++) {
                $currentStatus = strtolower((string) ($existingByNumber[$unitNumber]->status ?? ''));

                if (in_array($currentStatus, ['maintenance', 'damaged'], true)) {
                    $preservedIssueByNumber[$unitNumber] = $currentStatus;
                }
            }

            // Item-level maintenance/damaged from the form tags unit 1 when needed.
            if ($itemLevelIssue !== null && !isset($preservedIssueByNumber[1])) {
                $preservedIssueByNumber[1] = $itemLevelIssue;
            }
        } elseif ($itemLevelIssue !== null) {
            $preservedIssueByNumber[1] = $itemLevelIssue;
        }

        $borrowableNumbers = [];
        for ($unitNumber = 1; $unitNumber <= $total; $unitNumber++) {
            if (!isset($preservedIssueByNumber[$unitNumber])) {
                $borrowableNumbers[] = $unitNumber;
            }
        }

        $inUseNumbers = array_slice($borrowableNumbers, 0, $inUseTarget);
        $inUseNumberSet = array_fill_keys($inUseNumbers, true);

        $upsertRows = [];

        for ($unitNumber = 1; $unitNumber <= $total; $unitNumber++) {
            $current = $existingByNumber[$unitNumber] ?? null;

            $unitCode = ItemAsset::normalizeCode((string) ($unitCodes[$unitNumber - 1] ?? ''));
            if ($unitCode === '') {
                $unitCode = ItemAsset::normalizeCode((string) ($current->unit_code ?? ''));
            }
            if ($unitCode === '') {
                $unitCode = ItemAsset::temporaryUnitCode($itemId, $unitNumber);
            }

            $unitStatus = 'available';
            $maintenanceAt = $current->last_maintenance_at ?? null;
            $conditionNotes = $current->condition_notes ?? null;

            if (isset($preservedIssueByNumber[$unitNumber])) {
                $unitStatus = $preservedIssueByNumber[$unitNumber];
                if ($unitStatus === 'maintenance' && is_null($maintenanceAt)) {
                    $maintenanceAt = $now;
                }
            } elseif (isset($inUseNumberSet[$unitNumber])) {
                $unitStatus = 'in_use';
                $maintenanceAt = null;
            } else {
                // New units, reactivated retired units, and freed stock are borrowable.
                $unitStatus = 'available';
                $maintenanceAt = null;
            }

            if (
                $current
                && (string) $current->unit_code === $unitCode
                && strtolower((string) $current->status) === $unitStatus
            ) {
                continue;
            }

            $upsertRows[] = [
                'item_id' => $itemId,
                'unit_number' => $unitNumber,
                'unit_code' => $unitCode,
                'status' => $unitStatus,
                'condition_notes' => $conditionNotes,
                'last_maintenance_at' => $maintenanceAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($upsertRows, self::UNIT_UPSERT_CHUNK) as $chunk) {
            DB::table('item_units')->upsert(
                $chunk,
                ['item_id', 'unit_number'],
                ['unit_code', 'status', 'condition_notes', 'last_maintenance_at', 'updated_at']
            );
        }

        // Excess slots are removed. Raising quantity later creates fresh `available` units.
        // Keeping hundreds of `retired` rows made the table look like stock was still broken.
        DB::table('item_units')
            ->where('item_id', $itemId)
            ->where('unit_number', '>', $total)
            ->delete();
    }
}
