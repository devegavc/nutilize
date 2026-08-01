<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns reservation history into actionable inventory decisions:
 * what to buy more of, what is sitting idle, and where demand is concentrated.
 */
class InventoryInsightsService
{
    private const LOOKBACK_DAYS = 90;

    /** Extra units kept on top of peak demand so a single new booking does not cause a shortage. */
    private const SAFETY_BUFFER_RATIO = 0.2;

    /** Utilisation at which an item is considered to have no practical headroom left. */
    private const TIGHT_UTILISATION = 0.8;

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
            $categoryDemand[$categoryKey]['demand_qty'] += $profile['demand_qty'];
            $categoryDemand[$categoryKey]['requests'] += $profile['requests'];
            $categoryDemand[$categoryKey]['stock'] += $profile['stock'];

            if ($profile['suggested_qty'] > 0) {
                $recommendations[] = $profile;
            } elseif (self::isIdle($profile)) {
                $idleStock[] = $profile;
            }
        }

        usort($recommendations, static function (array $a, array $b): int {
            return [$b['priority_rank'], $b['shortfall'], $b['demand_qty']]
                <=> [$a['priority_rank'], $a['shortfall'], $a['demand_qty']];
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

        $dateColumn = self::reservationColumn('Date_of_Activity');
        $startColumn = self::reservationColumn('Start_of_activity');
        $endColumn = self::reservationColumn('End_of_Activity');

        $select = [
            'items.item_id',
            'details.quantity',
            'reservations.reservation_id',
            'reservations.overall_status',
            'reservations.created_at',
        ];

        foreach ([$dateColumn, $startColumn, $endColumn] as $column) {
            if ($column !== null) {
                $select[] = 'reservations.' . $column;
            }
        }

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
                'intervals' => [],
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

            $window = self::resolveBookingWindow($row, $dateColumn, $startColumn, $endColumn);
            if ($window !== null) {
                $demand[$itemId]['intervals'][] = [$window[0], $window[1], $quantity];
            }
        }

        foreach ($demand as $itemId => $stats) {
            $demand[$itemId]['requests'] = count($stats['requests']);
            $demand[$itemId]['peak_concurrent'] = self::peakConcurrentQuantity($stats['intervals']);
            unset($demand[$itemId]['intervals']);
        }

        return $demand;
    }

    /**
     * Highest quantity of a single item reserved at any overlapping moment.
     * This is what a purchase decision hinges on: two bookings of one unit each
     * only require a second unit when their time windows actually overlap.
     *
     * @param  array<int, array{0:int,1:int,2:int}>  $intervals
     */
    public static function peakConcurrentQuantity(array $intervals): int
    {
        if ($intervals === []) {
            return 0;
        }

        $events = [];
        foreach ($intervals as [$start, $end, $quantity]) {
            $events[] = [$start, $quantity];
            $events[] = [$end, -$quantity];
        }

        // Releases are applied before pickups at the same timestamp so back-to-back
        // bookings are not counted as a conflict.
        usort($events, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $running = 0;
        $peak = 0;

        foreach ($events as [, $delta]) {
            $running += $delta;
            $peak = max($peak, $running);
        }

        return $peak;
    }

    /**
     * @return array{0:int,1:int}|null
     */
    private static function resolveBookingWindow(object $row, ?string $dateColumn, ?string $startColumn, ?string $endColumn): ?array
    {
        $start = $startColumn !== null ? strtotime((string) ($row->{$startColumn} ?? '')) : false;
        $end = $endColumn !== null ? strtotime((string) ($row->{$endColumn} ?? '')) : false;

        if ($start !== false && $end !== false && $end > $start) {
            return [$start, $end];
        }

        $activityDate = $dateColumn !== null ? strtotime((string) ($row->{$dateColumn} ?? '')) : false;
        if ($activityDate === false) {
            $activityDate = strtotime((string) ($row->created_at ?? ''));
        }

        if ($activityDate === false) {
            return null;
        }

        $dayStart = strtotime('midnight', $activityDate);

        return [$dayStart, $dayStart + 86400];
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

        $requests = (int) ($stats['requests'] ?? 0);
        $demandQty = (int) ($stats['demand_qty'] ?? 0);
        $cancelledQty = (int) ($stats['cancelled_qty'] ?? 0);
        $rejectedQty = (int) ($stats['rejected_qty'] ?? 0);
        $recentQty = (int) ($stats['recent_qty'] ?? 0);
        $earlierQty = (int) ($stats['earlier_qty'] ?? 0);
        $peakConcurrent = (int) ($stats['peak_concurrent'] ?? 0);

        // A stock ledger showing more units out than owned is itself proof of a shortage.
        $pressure = max($peakConcurrent, $item['in_use']);
        $shortfall = max(0, $pressure - $serviceable);

        $utilisation = $serviceable > 0
            ? $pressure / $serviceable
            : ($pressure > 0 ? 1.5 : 0.0);

        $suggested = 0;
        if ($shortfall > 0) {
            $suggested = $shortfall + max(1, (int) ceil($pressure * self::SAFETY_BUFFER_RATIO));
        } elseif ($pressure > 0 && $utilisation >= self::TIGHT_UTILISATION) {
            $suggested = max(1, (int) ceil($pressure * self::SAFETY_BUFFER_RATIO));
        }

        [$priority, $priorityRank, $priorityLabel] = self::classifyPriority($shortfall, $utilisation, $serviceable, $pressure);
        $pressureSource = $peakConcurrent >= $item['in_use'] && $peakConcurrent > 0 ? 'bookings' : 'ledger';

        return [
            'item_id' => $item['item_id'],
            'asset_id' => '#ITEM-' . str_pad((string) $item['item_id'], 4, '0', STR_PAD_LEFT),
            'item_name' => $item['item_name'],
            'category' => $item['category'],
            'location' => $item['location'],
            'stock' => $stock,
            'serviceable' => $serviceable,
            'unavailable_units' => $unavailableUnits,
            'in_use' => $item['in_use'],
            'requests' => $requests,
            'demand_qty' => $demandQty,
            'cancelled_qty' => $cancelledQty,
            'rejected_qty' => $rejectedQty,
            'peak_concurrent' => $peakConcurrent,
            'pressure' => $pressure,
            'shortfall' => $shortfall,
            'suggested_qty' => $suggested,
            'utilisation_percent' => (int) round(min(2.0, $utilisation) * 100),
            'demand_change_percent' => self::percentChange($earlierQty, $recentQty),
            'over_allocated' => $item['in_use'] > $stock,
            'pressure_source' => $pressureSource,
            'priority' => $priority,
            'priority_rank' => $priorityRank,
            'priority_label' => $priorityLabel,
            'reason' => self::explain(
                $item,
                $peakConcurrent,
                $shortfall,
                $serviceable,
                $utilisation,
                $requests,
                $unavailableUnits,
                $pressureSource
            ),
        ];
    }

    /**
     * @return array{0:string,1:int,2:string}
     */
    private static function classifyPriority(int $shortfall, float $utilisation, int $serviceable, int $pressure): array
    {
        if ($pressure > 0 && $serviceable <= 0) {
            return ['critical', 4, 'Critical'];
        }

        if ($shortfall > 0 && $utilisation >= 1.5) {
            return ['critical', 4, 'Critical'];
        }

        if ($shortfall > 0) {
            return ['high', 3, 'High'];
        }

        if ($utilisation >= self::TIGHT_UTILISATION) {
            return ['medium', 2, 'Medium'];
        }

        return ['low', 1, 'Low'];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function explain(
        array $item,
        int $peakConcurrent,
        int $shortfall,
        int $serviceable,
        float $utilisation,
        int $requests,
        int $unavailableUnits,
        string $pressureSource
    ): string {
        $parts = [];

        if ($shortfall > 0) {
            $parts[] = $pressureSource === 'bookings'
                ? sprintf(
                    'Overlapping bookings need %d unit%s at once but only %d %s on hand — short by %d.',
                    $peakConcurrent,
                    $peakConcurrent === 1 ? '' : 's',
                    $serviceable,
                    $serviceable === 1 ? 'is' : 'are',
                    $shortfall
                )
                : sprintf(
                    '%d unit%s recorded as out against %d owned — short by %d.',
                    $item['in_use'],
                    $item['in_use'] === 1 ? '' : 's',
                    $item['stock'],
                    $shortfall
                );
        } elseif ($utilisation >= self::TIGHT_UTILISATION) {
            $parts[] = sprintf(
                'Running at %d%% of capacity, leaving no spare unit for the next request.',
                (int) round($utilisation * 100)
            );
        }

        if ($unavailableUnits > 0) {
            $parts[] = sprintf(
                '%d unit%s out of service for maintenance.',
                $unavailableUnits,
                $unavailableUnits === 1 ? '' : 's'
            );
        }

        if ($requests > 0) {
            $parts[] = sprintf('%d booking%s in the period.', $requests, $requests === 1 ? '' : 's');
        }

        return $parts === [] ? 'Stock is keeping up with demand.' : implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private static function isIdle(array $profile): bool
    {
        return $profile['stock'] > 0
            && $profile['requests'] === 0
            && $profile['in_use'] === 0
            && $profile['pressure'] === 0;
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
        $unmetRequests = 0;

        foreach ($recommendations as $recommendation) {
            $critical += $recommendation['priority'] === 'critical' ? 1 : 0;
            $high += $recommendation['priority'] === 'high' ? 1 : 0;
            $unitsToProcure += $recommendation['suggested_qty'];
            $unmetRequests += $recommendation['shortfall'];
        }

        return [
            'critical' => $critical,
            'high' => $high,
            'items_needing_action' => count($recommendations),
            'units_to_procure' => $unitsToProcure,
            'unmet_units' => $unmetRequests,
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
                'asset_id' => '#ITEM-' . str_pad((string) $itemId, 4, '0', STR_PAD_LEFT),
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
