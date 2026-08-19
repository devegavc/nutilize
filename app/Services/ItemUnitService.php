<?php

namespace App\Services;

use App\Support\ItemAsset;
use App\Support\OpenReservationScope;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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

    /**
     * Resolve display status from real unit rows.
     * Damaged/maintenance win; fully borrowed stock is NOT treated as damaged.
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, string> item_id => good|maintenance|damaged
     */
    public static function issueStatusByItem(array $itemIds): array
    {
        if (!Schema::hasTable('item_units') || $itemIds === []) {
            return [];
        }

        $rows = DB::table('item_units')
            ->whereIn('item_id', $itemIds)
            ->whereIn('status', ['damaged', 'maintenance'])
            ->select(['item_id', 'status'])
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $itemId = (int) $row->item_id;
            $status = strtolower((string) $row->status);

            if ($status === 'damaged') {
                $result[$itemId] = 'damaged';
                continue;
            }

            if ($status === 'maintenance' && ($result[$itemId] ?? null) !== 'damaged') {
                $result[$itemId] = 'maintenance';
            }
        }

        foreach ($itemIds as $itemId) {
            $result[(int) $itemId] = $result[(int) $itemId] ?? 'good';
        }

        return $result;
    }

    /**
     * Units currently borrowed — approved reservations whose activity falls on $onDate
     * (default: today in Asia/Manila). Future bookings stay available until that day.
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, array<int, int>> item_id => list of unit_ids
     */
    public static function borrowedUnitIdsByItem(array $itemIds, ?CarbonInterface $onDate = null): array
    {
        $onDate = $onDate
            ? Carbon::instance($onDate)->timezone('Asia/Manila')->startOfDay()
            : self::todayInManila();

        return self::reservedUnitIdsForDateWindow($itemIds, $onDate, $onDate);
    }

    /**
     * Units already assigned to reservations that overlap [$windowStart, $windowEnd].
     * Default: approved only (physical out / in_use). Pass $includeOpenRequests to also
     * block pending requests on that calendar day without marking the unit in_use.
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, array<int, int>> item_id => list of unit_ids
     */
    public static function reservedUnitIdsForDateWindow(
        array $itemIds,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
        ?int $exceptReservationId = null,
        bool $includeOpenRequests = false,
    ): array {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds), fn (int $id) => $id > 0)));
        $grouped = array_fill_keys($itemIds, []);

        if (
            $itemIds === []
            || !Schema::hasTable('reservation_item_units')
            || !Schema::hasTable('reservation_items')
            || !Schema::hasTable('reservation_details')
            || !Schema::hasTable('reservations')
        ) {
            return $grouped;
        }

        $startExpr = self::reservationStartTimestampSql('reservations');
        $endExpr = self::reservationEndTimestampSql('reservations');

        if ($startExpr === null) {
            return $grouped;
        }

        $query = DB::table('reservation_item_units as riu')
            ->join('reservation_items as ri', 'ri.reservation_items_id', '=', 'riu.reservation_items_id')
            ->join('reservation_details as rd', 'rd.reservation_items_id', '=', 'ri.reservation_items_id')
            ->join('reservations as reservations', 'reservations.reservation_id', '=', 'rd.reservation_id')
            ->whereIn('ri.item_id', $itemIds)
            ->whereRaw("DATE({$startExpr}) <= ?", [$windowEnd->toDateString()])
            ->whereRaw("DATE({$endExpr}) >= ?", [$windowStart->toDateString()])
            ->select(['ri.item_id', 'riu.unit_id']);

        if ($includeOpenRequests) {
            OpenReservationScope::apply($query, 'reservations.overall_status');
        } else {
            $query->whereRaw("LOWER(TRIM(COALESCE(reservations.overall_status, ''))) = 'approved'");
        }

        if (!is_null($exceptReservationId) && $exceptReservationId > 0) {
            $query->where('reservations.reservation_id', '<>', $exceptReservationId);
        }

        foreach ($query->get() as $row) {
            $itemId = (int) $row->item_id;
            $unitId = (int) $row->unit_id;

            if ($itemId <= 0 || $unitId <= 0) {
                continue;
            }

            $grouped[$itemId][] = $unitId;
        }

        foreach ($grouped as $itemId => $unitIds) {
            $grouped[$itemId] = array_values(array_unique($unitIds));
        }

        return $grouped;
    }

    /**
     * Pick physical units for a reservation without double-booking the same calendar day.
     *
     * @return array<int, int>
     */
    public static function pickUnitsForReservation(int $itemId, int $quantity, ?object $reservation = null): array
    {
        $quantity = max(1, $quantity);
        $itemId = (int) $itemId;

        if ($itemId <= 0 || !Schema::hasTable('item_units')) {
            return [];
        }

        $excludedUnitIds = [];

        if ($reservation) {
            [$windowStart, $windowEnd] = self::activityDateRange($reservation);
            $reservationId = (int) ($reservation->reservation_id ?? 0);

            if ($windowStart && $windowEnd) {
                try {
                    $excludedUnitIds = self::reservedUnitIdsForDateWindow(
                        [$itemId],
                        $windowStart,
                        $windowEnd,
                        $reservationId > 0 ? $reservationId : null,
                        true,
                    )[$itemId] ?? [];
                } catch (\Throwable $throwable) {
                    report($throwable);
                    $excludedUnitIds = [];
                }
            }
        }

        $query = DB::table('item_units as u')
            ->where('u.item_id', $itemId)
            ->whereIn('u.status', ['available', 'in_use']);

        if (Schema::hasTable('reservation_item_units')) {
            $query->leftJoinSub(
                DB::table('reservation_item_units')
                    ->select('unit_id', DB::raw('count(*) as usage_count'))
                    ->groupBy('unit_id'),
                'usage',
                'usage.unit_id',
                '=',
                'u.unit_id'
            )->orderByRaw('COALESCE(usage.usage_count, 0) ASC');
        }

        if ($excludedUnitIds !== []) {
            $query->whereNotIn('u.unit_id', $excludedUnitIds);
        }

        return $query
            ->orderBy('u.unit_number')
            ->limit($quantity)
            ->pluck('u.unit_id')
            ->map(fn ($unitId) => (int) $unitId)
            ->all();
    }

    /**
     * Flip unit status to in_use only for items that have a live borrow today,
     * or leftover in_use rows that should be released.
     *
     * @return array<int, int> item_id => in_use count
     */
    public static function reconcileLiveBorrowedUnits(): array
    {
        if (!Schema::hasTable('item_units')) {
            return [];
        }

        try {
            $itemIds = DB::table('item_units')
                ->where('status', 'in_use')
                ->pluck('item_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (
                Schema::hasTable('reservation_item_units')
                && Schema::hasTable('reservation_items')
                && Schema::hasTable('reservation_details')
                && Schema::hasTable('reservations')
            ) {
                $reservedItemIds = DB::table('reservation_item_units as riu')
                    ->join('reservation_items as ri', 'ri.reservation_items_id', '=', 'riu.reservation_items_id')
                    ->join('reservation_details as rd', 'rd.reservation_items_id', '=', 'ri.reservation_items_id')
                    ->join('reservations as reservations', 'reservations.reservation_id', '=', 'rd.reservation_id')
                    ->whereRaw("LOWER(TRIM(COALESCE(reservations.overall_status, ''))) = 'approved'")
                    ->pluck('ri.item_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $itemIds = array_merge($itemIds, $reservedItemIds);
            }

            return self::reconcileInUseForItems($itemIds);
        } catch (\Throwable $throwable) {
            report($throwable);

            return [];
        }
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public static function activityDateRange(object $reservation): array
    {
        $start = self::firstParseableDate($reservation, [
            'Date_of_Activity',
            'date_of_activity',
            'Start_of_activity',
            'start_of_activity',
        ]);
        $end = self::firstParseableDate($reservation, [
            'End_of_activity',
            'end_of_activity',
            'End_of_Activity',
        ]);

        if ($start && !$end) {
            $end = $start->copy();
        }

        if ($end && $start && $end->lt($start)) {
            $end = $start->copy();
        }

        return [$start?->copy()->startOfDay(), $end?->copy()->startOfDay()];
    }

    private static function firstParseableDate(object $reservation, array $keys): ?Carbon
    {
        foreach ($keys as $key) {
            $value = data_get($reservation, $key);

            if ($value instanceof CarbonInterface) {
                return Carbon::instance($value);
            }

            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value);
            }

            if (is_string($value) && trim($value) !== '') {
                try {
                    return Carbon::parse($value);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    public static function todayInManila(): Carbon
    {
        return Carbon::now('Asia/Manila')->startOfDay();
    }

    private static function reservationStartTimestampSql(string $table = 'reservations'): ?string
    {
        return self::coalesceTimestampSql($table, [
            'Date_of_Activity',
            'date_of_activity',
            'Start_of_activity',
            'start_of_activity',
        ]);
    }

    private static function reservationEndTimestampSql(string $table = 'reservations'): ?string
    {
        $endExpr = self::coalesceTimestampSql($table, [
            'End_of_activity',
            'end_of_activity',
            'End_of_Activity',
        ]);
        $startExpr = self::reservationStartTimestampSql($table);

        if ($endExpr && $startExpr) {
            return "COALESCE({$endExpr}, {$startExpr})";
        }

        return $endExpr ?? $startExpr;
    }

    /**
     * @param  list<string>  $columns
     */
    private static function coalesceTimestampSql(string $table, array $columns): ?string
    {
        $seen = [];
        $parts = [];

        foreach ($columns as $column) {
            $actual = self::resolveReservationColumn($column);
            if ($actual === null || isset($seen[$actual])) {
                continue;
            }

            $seen[$actual] = true;
            $quoted = '"' . str_replace('"', '""', $actual) . '"';
            $parts[] = "{$table}.{$quoted}";
        }

        if ($parts === []) {
            return null;
        }

        return count($parts) === 1 ? $parts[0] : ('COALESCE(' . implode(', ', $parts) . ')');
    }

    private static function resolveReservationColumn(string $wanted): ?string
    {
        static $listing = null;

        if ($listing === null) {
            $listing = Schema::hasTable('reservations')
                ? Schema::getColumnListing('reservations')
                : [];
        }

        foreach ($listing as $actual) {
            if (strcasecmp((string) $actual, $wanted) === 0) {
                return (string) $actual;
            }
        }

        return null;
    }

    /**
     * Count distinct units with an approved activity on $onDate (default: today, Manila).
     * Used for dashboard "Borrowed" so occupancy is by calendar day, not a global lock.
     */
    public static function borrowedUnitCountForDate(?CarbonInterface $onDate = null): int
    {
        $onDate = $onDate
            ? Carbon::instance($onDate)->timezone('Asia/Manila')->startOfDay()
            : self::todayInManila();

        if (
            !Schema::hasTable('reservation_item_units')
            || !Schema::hasTable('reservation_items')
            || !Schema::hasTable('reservation_details')
            || !Schema::hasTable('reservations')
        ) {
            return 0;
        }

        $startExpr = self::reservationStartTimestampSql('reservations');
        $endExpr = self::reservationEndTimestampSql('reservations');

        if ($startExpr === null) {
            return 0;
        }

        $query = DB::table('reservation_item_units as riu')
            ->join('reservation_items as ri', 'ri.reservation_items_id', '=', 'riu.reservation_items_id')
            ->join('reservation_details as rd', 'rd.reservation_items_id', '=', 'ri.reservation_items_id')
            ->join('reservations as reservations', 'reservations.reservation_id', '=', 'rd.reservation_id')
            ->whereRaw("LOWER(TRIM(COALESCE(reservations.overall_status, ''))) = 'approved'")
            ->whereRaw("DATE({$startExpr}) <= ?", [$onDate->toDateString()])
            ->whereRaw("DATE({$endExpr}) >= ?", [$onDate->toDateString()])
            ->selectRaw('COUNT(DISTINCT riu.unit_id) as total');

        if (Schema::hasTable('item_units')) {
            $query->join('item_units as u', 'u.unit_id', '=', 'riu.unit_id')
                ->whereNotIn('u.status', ['maintenance', 'damaged', 'retired']);
        }

        return (int) $query->value('total');
    }

    /**
     * Release leftover in_use units. Do not stamp units in_use for today's
     * activity — that global flag blocks requests for other days (e.g. Friday)
     * while the unit is only occupied today. Occupancy is stored on
     * items.quantity_in_use and enforced by reservation dates.
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, int> item_id => in_use count for today
     */
    public static function reconcileInUseForItems(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds), fn (int $id) => $id > 0)));
        $counts = array_fill_keys($itemIds, 0);

        if ($itemIds === [] || !Schema::hasTable('item_units') || !Schema::hasTable('items')) {
            return $counts;
        }

        try {
            $borrowedUnitIdsByItem = self::borrowedUnitIdsByItem($itemIds);
            $borrowedUnitIdSet = [];

            foreach ($borrowedUnitIdsByItem as $unitIds) {
                foreach ($unitIds as $unitId) {
                    $borrowedUnitIdSet[(int) $unitId] = true;
                }
            }

            $units = DB::table('item_units')
                ->whereIn('item_id', $itemIds)
                ->where('status', '<>', 'retired')
                ->get(['unit_id', 'item_id', 'status']);

            $now = now();
            $inUseByItem = array_fill_keys($itemIds, 0);
            $issueByItem = array_fill_keys($itemIds, 0);
            $releaseIds = [];

            foreach ($units as $unit) {
                $unitId = (int) $unit->unit_id;
                $itemId = (int) $unit->item_id;
                $status = strtolower((string) ($unit->status ?? ''));

                if (in_array($status, ['maintenance', 'damaged'], true)) {
                    $issueByItem[$itemId] = ($issueByItem[$itemId] ?? 0) + 1;
                    continue;
                }

                if (isset($borrowedUnitIdSet[$unitId])) {
                    $inUseByItem[$itemId] = ($inUseByItem[$itemId] ?? 0) + 1;
                }

                if ($status === 'in_use') {
                    $releaseIds[] = $unitId;
                }
            }

            if ($releaseIds !== []) {
                DB::table('item_units')
                    ->whereIn('unit_id', $releaseIds)
                    ->update([
                        'status' => 'available',
                        'updated_at' => $now,
                    ]);
            }

            foreach ($itemIds as $itemId) {
                $inUse = (int) ($inUseByItem[$itemId] ?? 0);
                $counts[$itemId] = $inUse;

                $issueCount = (int) ($issueByItem[$itemId] ?? 0);

                DB::table('items')
                    ->where('item_id', $itemId)
                    ->update([
                        'quantity_in_use' => $inUse,
                        'availability_status' => DB::raw($issueCount <= 0 ? 'true' : 'false'),
                        'updated_at' => $now,
                    ]);
            }

            return $counts;
        } catch (\Throwable $throwable) {
            report($throwable);

            return [];
        }
    }

    public static function inUseCountForItem(int $itemId): int
    {
        $counts = self::reconcileInUseForItems([$itemId]);

        return (int) ($counts[$itemId] ?? 0);
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
        self::reconcileInUseForItems([$itemId]);

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
     * - Borrowable units stay `available` here. `in_use` is applied afterwards
     *   by reconcileInUseForItems() only for approved activity happening today.
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
        $now = now();
        $itemLevelIssue = $status === 'maintenance' ? 'maintenance' : ($status === 'damaged' ? 'damaged' : null);

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
        // explicitly setting the item back to Good. Choosing Good means the issue is cleared.
        $preservedIssueByNumber = [];
        $normalizedStatus = strtolower($status);
        $clearIssues = $normalizedStatus === 'good';

        if (!$clearIssues) {
            for ($unitNumber = 1; $unitNumber <= $total; $unitNumber++) {
                $currentStatus = strtolower((string) ($existingByNumber[$unitNumber]->status ?? ''));

                if (in_array($currentStatus, ['maintenance', 'damaged'], true)) {
                    $preservedIssueByNumber[$unitNumber] = $currentStatus;
                }
            }

            // Item-level Damaged/Maintenance from Manage Items applies to every active unit
            // so those rows appear on the maintenance dashboard.
            if ($itemLevelIssue !== null) {
                for ($unitNumber = 1; $unitNumber <= $total; $unitNumber++) {
                    $preservedIssueByNumber[$unitNumber] = $itemLevelIssue;
                }
            }
        } elseif ($itemLevelIssue !== null) {
            $preservedIssueByNumber[1] = $itemLevelIssue;
        }

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
                if (in_array($unitStatus, ['maintenance', 'damaged'], true) && is_null($maintenanceAt)) {
                    $maintenanceAt = $now;
                }
            } else {
                // Occupancy is date-based. Do not stamp in_use from a quantity count.
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
