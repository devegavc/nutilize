<?php

namespace App\Services;

use App\Support\ItemAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns reservation history into actionable inventory decisions:
 * what to buy more of, what is sitting idle, and where demand is concentrated.
 */
class InventoryInsightsService
{
    private const LOOKBACK_DAYS = 90;

    /** When borrowed units reach this share of owned stock, suggest buying 1 spare. */
    private const HIGH_DEMAND_RATIO = 0.8;

    private const CANCELLED_STATUSES = ['cancelled', 'canceled'];

    private const REJECTED_STATUSES = ['rejected', 'declined', 'denied'];

    private static ?array $reservationColumns = null;

    public static function build(int $lookbackDays = self::LOOKBACK_DAYS): array
    {
        $empty = self::emptyPayload($lookbackDays);

        if (!Schema::hasTable('items') || !Schema::hasTable('reservation_details')) {
            return $empty;
        }

        $since = now()->subDays($lookbackDays);
        $items = self::loadItems();

        if ($items === []) {
            return $empty;
        }

        $demand = self::loadItemDemand($since);
        $unavailableUnits = self::loadUnavailableUnitCounts();
        $maintenanceCounts = self::loadMaintenanceCounts($since);

        $recommendations = [];
        $idleStock = [];
        $categoryDemand = [];

        foreach ($items as $itemId => $item) {
            $stats = $demand[$itemId] ?? null;
            $profile = self::buildItemProfile($item, $stats, (int) ($unavailableUnits[$itemId] ?? 0));

            $categoryKey = $profile['category'];
            $categoryDemand[$categoryKey] ??= ['category' => $categoryKey, 'demand_qty' => 0, 'requests' => 0, 'stock' => 0];
            $categoryDemand[$categoryKey]['demand_qty'] += $profile['units_borrowed'];
            $categoryDemand[$categoryKey]['requests'] += $profile['times_borrowed'];
            $categoryDemand[$categoryKey]['stock'] += $profile['stock'];

            if ($profile['suggested_qty'] > 0) {
                $recommendations[] = $profile;
            } elseif (self::isIdle($profile)) {
                $idleStock[] = $profile;
            }
        }

        usort($recommendations, static function (array $a, array $b): int {
            return [$b['priority_rank'], $b['gap'], $b['units_borrowed']]
                <=> [$a['priority_rank'], $a['gap'], $a['units_borrowed']];
        });

        usort($idleStock, static fn (array $a, array $b): int => $b['stock'] <=> $a['stock']);

        $categoryDemand = array_values($categoryDemand);
        usort($categoryDemand, static fn (array $a, array $b): int => $b['demand_qty'] <=> $a['demand_qty']);

        return [
            'lookbackDays' => $lookbackDays,
            'restockRecommendations' => $recommendations,
            'restockSummary' => self::summarise($recommendations, $idleStock),
            'idleStock' => array_slice($idleStock, 0, 8),
            'categoryDemand' => array_slice($categoryDemand, 0, 8),
            'peakPeriods' => self::loadPeakPeriods($since),
            'maintenanceWatch' => self::buildMaintenanceWatch($items, $unavailableUnits, $maintenanceCounts),
            'fulfillmentStats' => self::loadFulfillmentStats($since),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function loadItems(): array
    {
        $query = DB::table('items')->leftJoin('item_owners as owners', 'owners.owner_id', '=', 'items.owner_id');

        $columns = [
            'items.item_id',
            'items.item_name',
            'items.quantity_total',
            'items.quantity_in_use',
            'items.maintenance_status',
            'owners.owner_name',
        ];

        if (Schema::hasTable('item_categories') && Schema::hasColumn('items', 'category_id')) {
            $query->leftJoin('item_categories as cats', 'cats.category_id', '=', 'items.category_id');
            $columns[] = 'cats.display_name as category_name';
        }

        $items = [];

        foreach ($query->select($columns)->get() as $row) {
            $items[(int) $row->item_id] = [
                'item_id' => (int) $row->item_id,
                'item_name' => trim((string) ($row->item_name ?? '')) ?: 'Unnamed Item',
                'category' => trim((string) ($row->category_name ?? '')) ?: 'Uncategorised',
                'location' => trim((string) ($row->owner_name ?? '')) ?: 'Storage',
                'stock' => max(0, (int) ($row->quantity_total ?? 0)),
                'in_use' => max(0, (int) ($row->quantity_in_use ?? 0)),
                'flagged_maintenance' => (bool) ($row->maintenance_status ?? false),
            ];
        }

        $unitCodesByItem = ItemUnitService::loadUnitCodesGroupedByItem(array_keys($items));

        foreach ($items as $itemId => &$item) {
            $unitCodes = $unitCodesByItem[$itemId] ?? [];
            $item['asset_id'] = ItemUnitService::listAssetLabel($unitCodes, $itemId);
        }
        unset($item);

        return $items;
    }

    /**
     * Aggregate per-item demand, including the peak number of units needed at the same moment.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function loadItemDemand(\DateTimeInterface $since): array
    {
        if (!Schema::hasTable('reservation_items') || !Schema::hasTable('reservations')) {
            return [];
        }

        $select = [
            'items.item_id',
            'details.quantity',
            'reservations.reservation_id',
            'reservations.overall_status',
            'reservations.created_at',
        ];

        $rows = DB::table('reservation_details as details')
            ->join('reservations', 'reservations.reservation_id', '=', 'details.reservation_id')
            ->join('reservation_items as bookedItems', 'bookedItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items', 'items.item_id', '=', 'bookedItems.item_id')
            ->whereNotNull('details.reservation_items_id')
            ->where('reservations.created_at', '>=', $since)
            ->select($select)
            ->get();

        $midpoint = now()->subDays((int) round(self::LOOKBACK_DAYS / 2))->getTimestamp();
        $demand = [];

        foreach ($rows as $row) {
            $itemId = (int) $row->item_id;
            $quantity = max(1, (int) ($row->quantity ?? 1));
            $status = strtolower(trim((string) ($row->overall_status ?? '')));

            $demand[$itemId] ??= [
                'requests' => [],
                'demand_qty' => 0,
                'cancelled_qty' => 0,
                'rejected_qty' => 0,
                'recent_qty' => 0,
                'earlier_qty' => 0,
            ];

            if (in_array($status, self::CANCELLED_STATUSES, true)) {
                $demand[$itemId]['cancelled_qty'] += $quantity;
                continue;
            }

            if (in_array($status, self::REJECTED_STATUSES, true)) {
                $demand[$itemId]['rejected_qty'] += $quantity;
                continue;
            }

            $demand[$itemId]['requests'][(int) $row->reservation_id] = true;
            $demand[$itemId]['demand_qty'] += $quantity;

            $createdAt = strtotime((string) ($row->created_at ?? '')) ?: 0;
            if ($createdAt >= $midpoint) {
                $demand[$itemId]['recent_qty'] += $quantity;
            } else {
                $demand[$itemId]['earlier_qty'] += $quantity;
            }
        }

        foreach ($demand as $itemId => $stats) {
            $demand[$itemId]['requests'] = count($stats['requests']);
        }

        return $demand;
    }

    /**
     * Simple restock rule used on the analytics page:
     *   shortage  → (demand − stock) + 1 spare unit
     *   high use  → add 1 spare when everything is out or demand is very high
     *
     * @return array{
     *     suggested_qty:int,
     *     gap:int,
     *     formula:string,
     *     reason:string,
     *     priority:string,
     *     priority_rank:int,
     *     priority_label:string,
     *     demand_vs_stock_percent:int
     * }
     */
    public static function calculateRestockSuggestion(
        int $stock,
        int $inUse,
        int $timesBorrowed,
        int $unitsBorrowed
    ): array {
        $stock = max(0, $stock);
        $inUse = max(0, $inUse);
        $timesBorrowed = max(0, $timesBorrowed);
        $unitsBorrowed = max(0, $unitsBorrowed);

        $demandLevel = max($unitsBorrowed, $inUse);
        $gap = max(0, $demandLevel - $stock);

        $suggestedQty = 0;
        $formula = '';
        $reason = '';
        $priority = 'low';
        $priorityRank = 1;
        $priorityLabel = 'Low';

        if ($gap > 0) {
            $suggestedQty = $gap + 1;

            if ($inUse > $stock) {
                $formula = sprintf('%d out − %d owned + 1 spare', $inUse, $stock);
                $reason = sprintf('%d unit(s) are out but you only own %d.', $inUse, $stock);
                $priority = $inUse >= ($stock * 2) ? 'critical' : 'high';
            } else {
                $formula = sprintf('%d borrowed − %d owned + 1 spare', $unitsBorrowed, $stock);
                $reason = sprintf('%d units were borrowed recently but you only have %d.', $unitsBorrowed, $stock);
                $priority = $gap >= $stock ? 'critical' : 'high';
            }

            $priorityRank = $priority === 'critical' ? 4 : 3;
            $priorityLabel = ucfirst($priority);
        } elseif ($stock > 0 && $inUse >= $stock) {
            $suggestedQty = 1;
            $formula = 'All units out + 1 spare';
            $reason = sprintf('All %d unit(s) are borrowed right now — none left for the next request.', $stock);
            $priority = 'medium';
            $priorityRank = 2;
            $priorityLabel = 'Medium';
        } elseif ($stock > 0 && $unitsBorrowed >= (int) ceil($stock * self::HIGH_DEMAND_RATIO)) {
            $suggestedQty = 1;
            $formula = sprintf('%d borrowed of %d owned + 1 spare', $unitsBorrowed, $stock);
            $reason = sprintf('High demand: %d units borrowed with only %d owned.', $unitsBorrowed, $stock);
            $priority = 'medium';
            $priorityRank = 2;
            $priorityLabel = 'Medium';
        } elseif ($stock > 0 && $timesBorrowed > $stock) {
            $suggestedQty = 1;
            $formula = sprintf('%d bookings for %d unit(s) + 1 spare', $timesBorrowed, $stock);
            $reason = sprintf('Booked %d times but only %d unit(s) available — users may have to wait.', $timesBorrowed, $stock);
            $priority = 'medium';
            $priorityRank = 2;
            $priorityLabel = 'Medium';
        }

        $demandVsStockPercent = $stock > 0
            ? min(200, (int) round(($demandLevel / $stock) * 100))
            : ($demandLevel > 0 ? 200 : 0);

        return [
            'suggested_qty' => $suggestedQty,
            'gap' => $gap,
            'formula' => $formula,
            'reason' => $reason,
            'priority' => $priority,
            'priority_rank' => $priorityRank,
            'priority_label' => $priorityLabel,
            'demand_vs_stock_percent' => $demandVsStockPercent,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>|null  $stats
     * @return array<string, mixed>
     */
    private static function buildItemProfile(array $item, ?array $stats, int $unavailableUnits): array
    {
        $stock = $item['stock'];
        $serviceable = max(0, $stock - $unavailableUnits);

        $timesBorrowed = (int) ($stats['requests'] ?? 0);
        $unitsBorrowed = (int) ($stats['demand_qty'] ?? 0);
        $cancelledQty = (int) ($stats['cancelled_qty'] ?? 0);
        $rejectedQty = (int) ($stats['rejected_qty'] ?? 0);
        $recentQty = (int) ($stats['recent_qty'] ?? 0);
        $earlierQty = (int) ($stats['earlier_qty'] ?? 0);

        $suggestion = self::calculateRestockSuggestion(
            $serviceable,
            (int) $item['in_use'],
            $timesBorrowed,
            $unitsBorrowed,
        );

        return [
            'item_id' => $item['item_id'],
            'asset_id' => $item['asset_id'] ?? ItemAsset::fallbackCode((int) $item['item_id']),
            'item_name' => $item['item_name'],
            'category' => $item['category'],
            'location' => $item['location'],
            'stock' => $stock,
            'serviceable' => $serviceable,
            'unavailable_units' => $unavailableUnits,
            'in_use' => $item['in_use'],
            'times_borrowed' => $timesBorrowed,
            'units_borrowed' => $unitsBorrowed,
            'cancelled_qty' => $cancelledQty,
            'rejected_qty' => $rejectedQty,
            'gap' => $suggestion['gap'],
            'suggested_qty' => $suggestion['suggested_qty'],
            'suggestion_formula' => $suggestion['formula'],
            'demand_vs_stock_percent' => $suggestion['demand_vs_stock_percent'],
            'demand_change_percent' => self::percentChange($earlierQty, $recentQty),
            'over_allocated' => $item['in_use'] > $stock,
            'priority' => $suggestion['priority'],
            'priority_rank' => $suggestion['priority_rank'],
            'priority_label' => $suggestion['priority_label'],
            'reason' => $suggestion['reason'],
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private static function isIdle(array $profile): bool
    {
        return $profile['stock'] > 0
            && $profile['times_borrowed'] === 0
            && $profile['in_use'] === 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $recommendations
     * @param  array<int, array<string, mixed>>  $idleStock
     * @return array<string, int>
     */
    private static function summarise(array $recommendations, array $idleStock): array
    {
        $critical = 0;
        $high = 0;
        $unitsToProcure = 0;
        $unmetUnits = 0;

        foreach ($recommendations as $recommendation) {
            $critical += $recommendation['priority'] === 'critical' ? 1 : 0;
            $high += $recommendation['priority'] === 'high' ? 1 : 0;
            $unitsToProcure += $recommendation['suggested_qty'];
            $unmetUnits += $recommendation['gap'];
        }

        return [
            'critical' => $critical,
            'high' => $high,
            'items_needing_action' => count($recommendations),
            'units_to_procure' => $unitsToProcure,
            'unmet_units' => $unmetUnits,
            'idle_items' => count($idleStock),
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function loadUnavailableUnitCounts(): array
    {
        if (!Schema::hasTable('item_units')) {
            return [];
        }

        return DB::table('item_units')
            ->whereIn(DB::raw('LOWER(TRIM(status))'), ['maintenance', 'damaged', 'retired'])
            ->groupBy('item_id')
            ->selectRaw('item_id, COUNT(*) as unavailable')
            ->pluck('unavailable', 'item_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private static function loadMaintenanceCounts(\DateTimeInterface $since): array
    {
        if (!Schema::hasTable('maintenance')) {
            return [];
        }

        return DB::table('maintenance')
            ->whereNotNull('item_id')
            ->where('created_at', '>=', $since)
            ->groupBy('item_id')
            ->selectRaw('item_id, COUNT(*) as incidents')
            ->pluck('incidents', 'item_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, int>  $unavailableUnits
     * @param  array<int, int>  $maintenanceCounts
     * @return array<int, array<string, mixed>>
     */
    private static function buildMaintenanceWatch(array $items, array $unavailableUnits, array $maintenanceCounts): array
    {
        $watch = [];

        foreach ($items as $itemId => $item) {
            $unavailable = (int) ($unavailableUnits[$itemId] ?? 0);
            $incidents = (int) ($maintenanceCounts[$itemId] ?? 0);

            if ($unavailable === 0 && $incidents === 0 && !$item['flagged_maintenance']) {
                continue;
            }

            $downtimeShare = $item['stock'] > 0 ? $unavailable / $item['stock'] : 0.0;

            $watch[] = [
                'item_id' => $itemId,
                'asset_id' => $item['asset_id'] ?? ItemAsset::fallbackCode($itemId),
                'item_name' => $item['item_name'],
                'category' => $item['category'],
                'stock' => $item['stock'],
                'unavailable_units' => $unavailable,
                'incidents' => $incidents,
                'downtime_percent' => (int) round($downtimeShare * 100),
                'action' => $downtimeShare >= 0.5 || $incidents >= 3
                    ? 'Consider replacing — repeated downtime'
                    : 'Schedule repair to restore capacity',
            ];
        }

        usort($watch, static function (array $a, array $b): int {
            return [$b['incidents'], $b['unavailable_units']] <=> [$a['incidents'], $a['unavailable_units']];
        });

        return array_slice($watch, 0, 8);
    }

    /**
     * Busiest weekdays plus the heaviest upcoming activity dates.
     *
     * @return array<string, mixed>
     */
    private static function loadPeakPeriods(\DateTimeInterface $since): array
    {
        $empty = ['weekdays' => [], 'busiest_dates' => []];

        if (!Schema::hasTable('reservations')) {
            return $empty;
        }

        $dateColumn = self::reservationColumn('Date_of_Activity');

        $select = ['reservations.reservation_id', 'reservations.overall_status', 'reservations.created_at'];
        if ($dateColumn !== null) {
            $select[] = 'reservations.' . $dateColumn;
        }

        $rows = DB::table('reservations')
            ->where('created_at', '>=', $since)
            ->select($select)
            ->get();

        $weekdayCounts = array_fill(0, 7, 0);
        $dateCounts = [];

        foreach ($rows as $row) {
            $status = strtolower(trim((string) ($row->overall_status ?? '')));
            if (in_array($status, self::CANCELLED_STATUSES, true)) {
                continue;
            }

            $raw = $dateColumn !== null ? (string) ($row->{$dateColumn} ?? '') : '';
            $timestamp = strtotime($raw) ?: strtotime((string) ($row->created_at ?? ''));

            if ($timestamp === false) {
                continue;
            }

            $weekdayCounts[(int) date('w', $timestamp)]++;
            $dateKey = date('Y-m-d', $timestamp);
            $dateCounts[$dateKey] = ($dateCounts[$dateKey] ?? 0) + 1;
        }

        $maxWeekday = max(1, max($weekdayCounts));
        $weekdays = [];
        foreach ($weekdayCounts as $index => $count) {
            $weekdays[] = [
                'label' => date('D', strtotime("Sunday +{$index} days")),
                'count' => $count,
                'percent' => (int) round(($count / $maxWeekday) * 100),
            ];
        }

        arsort($dateCounts);
        $busiestDates = [];
        foreach (array_slice($dateCounts, 0, 5, true) as $date => $count) {
            $busiestDates[] = [
                'date' => date('M j, Y', strtotime($date)),
                'count' => $count,
            ];
        }

        return ['weekdays' => $weekdays, 'busiest_dates' => $busiestDates];
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadFulfillmentStats(\DateTimeInterface $since): array
    {
        $stats = ['approved' => 0, 'pending' => 0, 'cancelled' => 0, 'rejected' => 0, 'total' => 0, 'cancellation_rate' => 0.0];

        if (!Schema::hasTable('reservations')) {
            return $stats;
        }

        $rows = DB::table('reservations')
            ->where('created_at', '>=', $since)
            ->selectRaw("LOWER(TRIM(COALESCE(overall_status, ''))) as status, COUNT(*) as total")
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            $status = (string) $row->status;
            $count = (int) $row->total;
            $stats['total'] += $count;

            if (in_array($status, self::CANCELLED_STATUSES, true)) {
                $stats['cancelled'] += $count;
            } elseif (in_array($status, self::REJECTED_STATUSES, true)) {
                $stats['rejected'] += $count;
            } elseif (in_array($status, ['approved', 'returned'], true)) {
                $stats['approved'] += $count;
            } else {
                $stats['pending'] += $count;
            }
        }

        if ($stats['total'] > 0) {
            $stats['cancellation_rate'] = round((($stats['cancelled'] + $stats['rejected']) / $stats['total']) * 100, 1);
        }

        return $stats;
    }

    /**
     * Reservation columns were added with mixed casing, so resolve the real name once.
     */
    private static function reservationColumn(string $preferred): ?string
    {
        if (self::$reservationColumns === null) {
            self::$reservationColumns = Schema::hasTable('reservations')
                ? Schema::getColumnListing('reservations')
                : [];
        }

        foreach (self::$reservationColumns as $column) {
            if (strcasecmp($column, $preferred) === 0) {
                return $column;
            }
        }

        return null;
    }

    private static function percentChange(int $previous, int $current): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyPayload(int $lookbackDays): array
    {
        return [
            'lookbackDays' => $lookbackDays,
            'restockRecommendations' => [],
            'restockSummary' => [
                'critical' => 0,
                'high' => 0,
                'items_needing_action' => 0,
                'units_to_procure' => 0,
                'unmet_units' => 0,
                'idle_items' => 0,
            ],
            'idleStock' => [],
            'categoryDemand' => [],
            'peakPeriods' => ['weekdays' => [], 'busiest_dates' => []],
            'maintenanceWatch' => [],
            'fulfillmentStats' => [
                'approved' => 0,
                'pending' => 0,
                'cancelled' => 0,
                'rejected' => 0,
                'total' => 0,
                'cancellation_rate' => 0.0,
            ],
        ];
    }
}
