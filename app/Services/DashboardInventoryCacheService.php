<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardInventoryCacheService
{
    private const CACHE_TTL = 10; // 10 minutes for inventory data

    /**
     * Get all inventory dashboard data with caching
     */
    public static function getInventoryData(): array
    {
        $cacheKey = 'dashboard.inventory.data.v2';

        $data = Cache::remember($cacheKey, self::CACHE_TTL * 60, function () {
            return [
                'facilityCount' => self::getFacilityCount(),
                'equipmentCount' => self::getEquipmentCount(),
                'maintenanceAndReportCount' => self::getMaintenanceAndReportCount(),
                'mostRequestedItems' => self::getTopRequestedItems(10, false),
            ];
        });

        // #region agent log
        $data['_agentDebug'] = self::agentFetchDebugSnapshot('inventory', [
            'facilityCount' => (int) ($data['facilityCount'] ?? 0),
            'equipmentCount' => (int) ($data['equipmentCount'] ?? 0),
            'mostRequestedCount' => count($data['mostRequestedItems'] ?? []),
        ]);
        // #endregion

        return $data;
    }

    /**
     * Get inventory analytics data with caching, scoped to a calendar month.
     */
    public static function getAnalyticsData(Carbon $selectedMonth, Carbon $compareMonth): array
    {
        $monthStart = $selectedMonth->copy()->startOfMonth();
        $monthEnd = $selectedMonth->copy()->endOfMonth();
        $compareStart = $compareMonth->copy()->startOfMonth();
        $compareEnd = $compareMonth->copy()->endOfMonth();
        $cacheKey = 'dashboard.inventory.analytics.v5.'
            . $monthStart->format('Y-m') . '.'
            . $compareStart->format('Y-m');

        $data = Cache::remember($cacheKey, self::CACHE_TTL * 60, function () use (
            $monthStart,
            $monthEnd,
            $compareStart,
            $compareEnd,
            $selectedMonth
        ) {
            $currentBookings = self::countApprovedBookings($monthStart, $monthEnd);
            $compareBookings = self::countApprovedBookings($compareStart, $compareEnd);

            $currentBorrowers = self::countDistinctBorrowers($monthStart, $monthEnd);
            $compareBorrowers = self::countDistinctBorrowers($compareStart, $compareEnd);

            $currentEngagement = self::sumEngagementUnits($monthStart, $monthEnd);
            $compareEngagement = self::sumEngagementUnits($compareStart, $compareEnd);

            $currentNewUsers = self::countNewUsers($monthStart, $monthEnd);
            $compareNewUsers = self::countNewUsers($compareStart, $compareEnd);

            [$yearLabels, $trendCounts] = self::getMonthlyTrend($selectedMonth);

            return array_merge([
                'periodLabel' => $monthStart->format('F Y'),
                'monthLabel' => $monthStart->format('F Y'),
                'monthKey' => $monthStart->format('Y-m'),
                'compareMonthLabel' => $compareStart->format('F Y'),
                'compareMonthKey' => $compareStart->format('Y-m'),
                'yearLabels' => $yearLabels,
                'trendCounts' => $trendCounts,
                'totalBorrowers' => $currentBorrowers,
                'borrowersDelta' => self::numericDelta($compareBorrowers, $currentBorrowers),
                'engagementCount' => $currentEngagement,
                'engagementDelta' => self::numericDelta($compareEngagement, $currentEngagement),
                'newUsers' => $currentNewUsers,
                'newUsersDelta' => self::numericDelta($compareNewUsers, $currentNewUsers),
                'approvedBookings' => $currentBookings,
                'bookingsDelta' => self::numericDelta($compareBookings, $currentBookings),
                'monthComparison' => [
                    'currentLabel' => $monthStart->format('M Y'),
                    'compareLabel' => $compareStart->format('M Y'),
                    'metrics' => [
                        [
                            'key' => 'bookings',
                            'label' => 'Approved Bookings',
                            'current' => $currentBookings,
                            'compare' => $compareBookings,
                            'delta' => self::numericDelta($compareBookings, $currentBookings),
                        ],
                        [
                            'key' => 'borrowers',
                            'label' => 'Unique Borrowers',
                            'current' => $currentBorrowers,
                            'compare' => $compareBorrowers,
                            'delta' => self::numericDelta($compareBorrowers, $currentBorrowers),
                        ],
                        [
                            'key' => 'units',
                            'label' => 'Units Borrowed',
                            'current' => $currentEngagement,
                            'compare' => $compareEngagement,
                            'delta' => self::numericDelta($compareEngagement, $currentEngagement),
                        ],
                        [
                            'key' => 'users',
                            'label' => 'New Users',
                            'current' => $currentNewUsers,
                            'compare' => $compareNewUsers,
                            'delta' => self::numericDelta($compareNewUsers, $currentNewUsers),
                        ],
                    ],
                ],
                'topItems' => self::getTopBorrowedItems(8, $monthStart, $monthEnd),
                'shareItems' => self::getShareBorrowedItems(8, $monthStart, $monthEnd),
                'topBorrowers' => self::getTopBorrowers(8, $monthStart, $monthEnd),
            ], InventoryInsightsService::build($monthStart, $monthEnd));
        });

        // #region agent log
        $data['_agentDebug'] = self::agentFetchDebugSnapshot('insights', [
            'monthKey' => $monthStart->format('Y-m'),
            'compareMonthKey' => $compareStart->format('Y-m'),
            'approvedBookings' => (int) ($data['approvedBookings'] ?? 0),
            'totalBorrowers' => (int) ($data['totalBorrowers'] ?? 0),
            'engagementCount' => (int) ($data['engagementCount'] ?? 0),
            'newUsers' => (int) ($data['newUsers'] ?? 0),
            'trendSum' => array_sum(array_map('intval', $data['trendCounts'] ?? [])),
            'topItemsCount' => count($data['topItems'] ?? []),
        ]);
        // #endregion

        $canGoNext = $monthStart->lt(now()->startOfMonth());
        $previousSelected = $monthStart->copy()->subMonth();
        $nextSelected = $monthStart->copy()->addMonth();

        return array_merge($data, [
            'previousMonthUrl' => route('dashboard.inventory.analytics', [
                'month' => $previousSelected->format('Y-m'),
                'compare' => $previousSelected->copy()->subMonth()->format('Y-m'),
            ]),
            'nextMonthUrl' => $canGoNext
                ? route('dashboard.inventory.analytics', [
                    'month' => $nextSelected->format('Y-m'),
                    'compare' => $monthStart->format('Y-m'),
                ])
                : null,
            'canGoNext' => $canGoNext,
        ]);
    }

    private static function getFacilityCount(): int
    {
        return Schema::hasTable('rooms') ? (int) DB::table('rooms')->count() : 0;
    }

    private static function getEquipmentCount(): int
    {
        return Schema::hasTable('items') ? (int) DB::table('items')->count() : 0;
    }

    private static function getMaintenanceAndReportCount(): int
    {
        $count = 0;
        if (Schema::hasTable('maintenance')) {
            $count += (int) DB::table('maintenance')->count();
        }
        if (Schema::hasTable('reports')) {
            $count += (int) DB::table('reports')->count();
        }

        return $count;
    }

    /**
     * Statuses that mean the booking completed the approval chain (or finished usage).
     * Insights used to count only exact "approved", which hid all "returned" history.
     *
     * @return array<int, string>
     */
    private static function completedBookingStatuses(): array
    {
        return ['approved', 'returned', 'damaged'];
    }

    private static function whereCompletedBookingStatus($query, string $column = 'overall_status')
    {
        $statuses = self::completedBookingStatuses();
        $placeholders = implode(', ', array_fill(0, count($statuses), '?'));

        return $query->whereRaw("LOWER(TRIM(COALESCE({$column}, ''))) IN ({$placeholders})", $statuses);
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, int>}
     */
    private static function getMonthlyTrend(Carbon $endMonth): array
    {
        $end = $endMonth->copy()->startOfMonth();
        $start = $end->copy()->subMonths(11);
        $labels = [];
        $counts = [];
        $countsByMonth = [];

        if (Schema::hasTable('reservations')) {
            $rows = self::whereCompletedBookingStatus(DB::table('reservations'))
                ->whereBetween('created_at', [$start->copy()->startOfMonth(), $end->copy()->endOfMonth()])
                ->select('created_at')
                ->get();

            foreach ($rows as $row) {
                $monthKey = date('Y-m', strtotime((string) $row->created_at));
                $countsByMonth[$monthKey] = ($countsByMonth[$monthKey] ?? 0) + 1;
            }
        }

        $cursorMonth = clone $start;
        while ($cursorMonth <= $end) {
            $monthKey = $cursorMonth->format('Y-m');
            $labels[] = $cursorMonth->format('M');
            $counts[] = $countsByMonth[$monthKey] ?? 0;
            $cursorMonth->addMonth();
        }

        return [$labels, $counts];
    }

    private static function countApprovedBookings(Carbon $start, Carbon $end): int
    {
        if (!Schema::hasTable('reservations')) {
            return 0;
        }

        return (int) self::whereCompletedBookingStatus(DB::table('reservations'))
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    private static function countDistinctBorrowers(Carbon $start, Carbon $end): int
    {
        if (!Schema::hasTable('reservations')) {
            return 0;
        }

        return (int) self::whereCompletedBookingStatus(DB::table('reservations'))
            ->whereBetween('created_at', [$start, $end])
            ->distinct('user_id')
            ->count('user_id');
    }

    private static function sumEngagementUnits(Carbon $start, Carbon $end): int
    {
        if (!Schema::hasTable('reservation_details')) {
            return 0;
        }

        return (int) (self::whereCompletedBookingStatus(
            DB::table('reservation_details as details')
                ->join('reservations as reservations', 'reservations.reservation_id', '=', 'details.reservation_id'),
            'reservations.overall_status'
        )
            ->whereBetween('reservations.created_at', [$start, $end])
            ->sum('details.quantity') ?? 0);
    }

    private static function countNewUsers(Carbon $start, Carbon $end): int
    {
        if (!Schema::hasTable('users')) {
            return 0;
        }

        return (int) DB::table('users')
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /**
     * Borrowing leaderboard for the insights page. Unlike the inventory widget this
     * spans the full analysis window and counts every booking that reserved stock,
     * not just the ones that finished the approval chain.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function getTopBorrowedItems(int $limit, Carbon $start, Carbon $end): array
    {
        if (!Schema::hasTable('reservation_details') || !Schema::hasTable('reservation_items')) {
            return [];
        }

        $rows = DB::table('reservation_details as details')
            ->join('reservations', 'reservations.reservation_id', '=', 'details.reservation_id')
            ->join('reservation_items as bookedItems', 'bookedItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items', 'items.item_id', '=', 'bookedItems.item_id')
            ->leftJoin('item_owners as owners', 'owners.owner_id', '=', 'items.owner_id')
            ->whereNotNull('details.reservation_items_id')
            ->whereBetween('reservations.created_at', [$start, $end])
            ->whereNotIn(DB::raw("LOWER(TRIM(COALESCE(reservations.overall_status, '')))"), ['cancelled', 'canceled'])
            ->select(['items.item_id', 'items.item_name', 'items.quantity_total', 'owners.owner_name'])
            ->selectRaw('COALESCE(SUM(details.quantity), 0) as usage_count')
            ->selectRaw('COUNT(DISTINCT reservations.reservation_id) as booking_count')
            ->selectRaw('COUNT(DISTINCT reservations.user_id) as borrower_count')
            ->groupBy(['items.item_id', 'items.item_name', 'items.quantity_total', 'owners.owner_name'])
            ->orderByDesc('usage_count')
            ->orderBy('items.item_name')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $unitCodesByItem = ItemUnitService::loadUnitCodesGroupedByItem(
            $rows->pluck('item_id')->map(fn ($id) => (int) $id)->all()
        );

        $maxUsage = max(1, (int) $rows->max('usage_count'));

        return $rows->map(function ($row) use ($maxUsage, $unitCodesByItem) {
            $itemId = (int) $row->item_id;
            $usageCount = max(0, (int) ($row->usage_count ?? 0));
            $stock = max(0, (int) ($row->quantity_total ?? 0));
            $unitCodes = $unitCodesByItem[$itemId] ?? [];

            return [
                'asset_id' => ItemUnitService::listAssetLabel($unitCodes, $itemId),
                'item_name' => (string) ($row->item_name ?? 'Unnamed Item'),
                'owner' => trim((string) ($row->owner_name ?? '')) ?: 'Unassigned',
                'location' => trim((string) ($row->owner_name ?? '')) ?: 'Unassigned',
                'category' => 'Equipment',
                'stock' => $stock,
                'booking_count' => (int) ($row->booking_count ?? 0),
                'borrower_count' => (int) ($row->borrower_count ?? 0),
                'usage_count' => $usageCount,
                'usage_percent' => min(100, (int) round(($usageCount / $maxUsage) * 100)),
            ];
        })->all();
    }

    /**
     * Share chart rows grouped by item name so duplicate names (e.g. two "Drone" records)
     * appear as one slice instead of a confusing double legend entry.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function getShareBorrowedItems(int $limit, Carbon $start, Carbon $end): array
    {
        $rows = self::getTopBorrowedItems(max($limit * 3, 24), $start, $end);
        if ($rows === []) {
            return [];
        }

        $merged = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['item_name'] ?? 'Unnamed Item')) ?: 'Unnamed Item';
            $key = strtolower($name);

            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'item_name' => $name,
                    'usage_count' => 0,
                    'booking_count' => 0,
                    'borrower_count' => 0,
                ];
            }

            $merged[$key]['usage_count'] += max(0, (int) ($row['usage_count'] ?? 0));
            $merged[$key]['booking_count'] += max(0, (int) ($row['booking_count'] ?? 0));
            $merged[$key]['borrower_count'] += max(0, (int) ($row['borrower_count'] ?? 0));
        }

        $merged = array_values($merged);
        usort($merged, static function (array $a, array $b): int {
            return [$b['usage_count'], $a['item_name']] <=> [$a['usage_count'], $b['item_name']];
        });

        $merged = array_slice($merged, 0, $limit);
        $totalUsage = max(1, array_sum(array_map(static fn (array $row): int => (int) $row['usage_count'], $merged)));
        $maxUsage = max(1, ...array_map(static fn (array $row): int => (int) $row['usage_count'], $merged));

        return array_map(static function (array $row) use ($maxUsage, $totalUsage): array {
            $usage = (int) $row['usage_count'];

            return [
                'item_name' => $row['item_name'],
                'usage_count' => $usage,
                'booking_count' => (int) $row['booking_count'],
                'borrower_count' => (int) $row['borrower_count'],
                'usage_percent' => min(100, (int) round(($usage / $maxUsage) * 100)),
                'share_percent' => round(($usage / $totalUsage) * 100, 1),
            ];
        }, $merged);
    }

    /**
     * Top borrowers (users) for the selected month, with the item they used most.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function getTopBorrowers(int $limit, Carbon $start, Carbon $end): array
    {
        if (!Schema::hasTable('reservations') || !Schema::hasTable('users')) {
            return [];
        }

        $hasDetails = Schema::hasTable('reservation_details') && Schema::hasTable('reservation_items');

        $borrowerRows = DB::table('reservations')
            ->join('users', 'users.user_id', '=', 'reservations.user_id')
            ->whereBetween('reservations.created_at', [$start, $end])
            ->whereNotIn(DB::raw("LOWER(TRIM(COALESCE(reservations.overall_status, '')))"), ['cancelled', 'canceled'])
            ->select([
                'users.user_id',
                'users.full_name',
                'users.first_name',
                'users.last_name',
                'users.username',
            ])
            ->selectRaw('COUNT(DISTINCT reservations.reservation_id) as booking_count')
            ->when($hasDetails, function ($query) {
                $query->leftJoin('reservation_details as details', 'details.reservation_id', '=', 'reservations.reservation_id')
                    ->selectRaw('COALESCE(SUM(details.quantity), 0) as units_borrowed');
            }, function ($query) {
                $query->selectRaw('0 as units_borrowed');
            })
            ->groupBy([
                'users.user_id',
                'users.full_name',
                'users.first_name',
                'users.last_name',
                'users.username',
            ])
            ->orderByDesc('units_borrowed')
            ->orderByDesc('booking_count')
            ->limit($limit)
            ->get();

        if ($borrowerRows->isEmpty()) {
            return [];
        }

        $userIds = $borrowerRows->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $topItemByUser = [];

        if ($hasDetails && $userIds !== []) {
            $itemRows = DB::table('reservation_details as details')
                ->join('reservations', 'reservations.reservation_id', '=', 'details.reservation_id')
                ->join('reservation_items as bookedItems', 'bookedItems.reservation_items_id', '=', 'details.reservation_items_id')
                ->join('items', 'items.item_id', '=', 'bookedItems.item_id')
                ->whereIn('reservations.user_id', $userIds)
                ->whereBetween('reservations.created_at', [$start, $end])
                ->whereNotNull('details.reservation_items_id')
                ->whereNotIn(DB::raw("LOWER(TRIM(COALESCE(reservations.overall_status, '')))"), ['cancelled', 'canceled'])
                ->select(['reservations.user_id', 'items.item_name'])
                ->selectRaw('COALESCE(SUM(details.quantity), 0) as usage_count')
                ->groupBy(['reservations.user_id', 'items.item_name'])
                ->orderByDesc('usage_count')
                ->get();

            foreach ($itemRows as $row) {
                $userId = (int) $row->user_id;
                if (isset($topItemByUser[$userId])) {
                    continue;
                }

                $topItemByUser[$userId] = trim((string) ($row->item_name ?? '')) ?: 'Item';
            }
        }

        $maxUnits = max(1, (int) $borrowerRows->max('units_borrowed'));

        return $borrowerRows->map(function ($row) use ($topItemByUser, $maxUnits) {
            $userId = (int) $row->user_id;
            $fullName = trim((string) ($row->full_name ?? ''));
            if ($fullName === '') {
                $fullName = trim(trim((string) ($row->first_name ?? '')) . ' ' . trim((string) ($row->last_name ?? '')));
            }
            if ($fullName === '') {
                $fullName = trim((string) ($row->username ?? '')) ?: 'Unknown user';
            }

            $unitsBorrowed = max(0, (int) ($row->units_borrowed ?? 0));

            return [
                'user_id' => $userId,
                'name' => $fullName,
                'booking_count' => (int) ($row->booking_count ?? 0),
                'units_borrowed' => $unitsBorrowed,
                'top_item' => $topItemByUser[$userId] ?? '—',
                'usage_percent' => min(100, (int) round(($unitsBorrowed / $maxUnits) * 100)),
            ];
        })->all();
    }

    private static function getTopRequestedItems(int $limit, bool $approvedOnly = false): array
    {
        if (!Schema::hasTable('reservation_details')) {
            return [];
        }

        $query = DB::table('reservation_details as details')
            ->join('reservations as reservations', 'reservations.reservation_id', '=', 'details.reservation_id')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->leftJoin('item_owners as owners', 'owners.owner_id', '=', 'items.owner_id')
            ->select([
                'items.item_id',
                'items.item_name',
                'owners.owner_name',
            ])
            ->selectRaw('COALESCE(SUM(details.quantity), 0) as usage_count')
            ->whereBetween('reservations.created_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->groupBy(['items.item_id', 'items.item_name', 'owners.owner_name']);

        if ($approvedOnly) {
            self::whereCompletedBookingStatus($query, 'reservations.overall_status');
        } else {
            $query->whereNotIn(
                DB::raw("LOWER(TRIM(COALESCE(reservations.overall_status, '')))"),
                ['cancelled', 'canceled', 'rejected']
            );
        }

        $rows = $query
            ->orderByDesc('usage_count')
            ->orderBy('items.item_name')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $unitCodesByItem = ItemUnitService::loadUnitCodesGroupedByItem(
            $rows->pluck('item_id')->map(fn ($id) => (int) $id)->all()
        );

        $maxUsage = max(1, (int) $rows->max('usage_count'));

        return $rows->map(function ($row) use ($maxUsage, $unitCodesByItem) {
            $itemId = (int) $row->item_id;
            $usageCount = max(0, (int) ($row->usage_count ?? 0));
            $unitCodes = $unitCodesByItem[$itemId] ?? [];

            return [
                'asset_id' => ItemUnitService::listAssetLabel($unitCodes, $itemId),
                'item_name' => (string) ($row->item_name ?? 'Unnamed Item'),
                'owner' => trim((string) ($row->owner_name ?? '')) ?: 'Unassigned',
                'location' => trim((string) ($row->owner_name ?? '')) ?: 'Unassigned',
                'category' => 'Equipment',
                'usage_count' => $usageCount,
                'usage_percent' => min(100, (int) round(($usageCount / $maxUsage) * 100)),
            ];
        })->all();
    }

    private static function numericDelta(int $baseline, int $current): int
    {
        return $current - $baseline;
    }

    private static function percentChange(int $previous, int $current): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Clear inventory cache
     */
    public static function clearCache(): void
    {
        Cache::forget('dashboard.inventory.data');
        Cache::forget('dashboard.inventory.data.v2');

        $cursor = now()->startOfMonth();
        for ($i = 0; $i < 24; $i++) {
            $monthKey = $cursor->format('Y-m');

            for ($j = 0; $j < 24; $j++) {
                $compareKey = $cursor->copy()->subMonths($j)->format('Y-m');
                Cache::forget('dashboard.inventory.analytics.' . $monthKey . '.' . $compareKey);
                Cache::forget('dashboard.inventory.analytics.v2.' . $monthKey . '.' . $compareKey);
                Cache::forget('dashboard.inventory.analytics.v3.' . $monthKey . '.' . $compareKey);
                Cache::forget('dashboard.inventory.analytics.v4.' . $monthKey . '.' . $compareKey);
                Cache::forget('dashboard.inventory.analytics.v5.' . $monthKey . '.' . $compareKey);
            }

            $cursor->subMonth();
        }
    }

    // #region agent log
    /**
     * @param  array<string, mixed>  $pageMetrics
     * @return array<string, mixed>
     */
    public static function agentFetchDebugSnapshot(string $page, array $pageMetrics = []): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $snapshot = [
            'page' => $page,
            'pageMetrics' => $pageMetrics,
            'month' => $monthStart->format('Y-m'),
            'reservationTotal' => 0,
            'statusCounts' => [],
            'approvedTotal' => 0,
            'approvedCreatedThisMonth' => 0,
            'openTotal' => 0,
            'mostRequestedLive' => 0,
            'mostRequestedAnyStatusThisMonth' => 0,
            'error' => null,
        ];

        try {
            if (Schema::hasTable('reservations')) {
                $snapshot['reservationTotal'] = (int) DB::table('reservations')->count();
                $snapshot['approvedTotal'] = (int) DB::table('reservations')
                ->whereRaw("LOWER(TRIM(COALESCE(overall_status, ''))) IN ('approved', 'returned', 'damaged')")
                ->count();
            $snapshot['approvedCreatedThisMonth'] = (int) DB::table('reservations')
                ->whereRaw("LOWER(TRIM(COALESCE(overall_status, ''))) IN ('approved', 'returned', 'damaged')")
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();
            $snapshot['approvedExactTotal'] = (int) DB::table('reservations')
                ->whereRaw("LOWER(COALESCE(overall_status, '')) = ?", ['approved'])
                ->count();
            $snapshot['returnedTotal'] = (int) DB::table('reservations')
                ->whereRaw("LOWER(COALESCE(overall_status, '')) = ?", ['returned'])
                ->count();
            $snapshot['openTotal'] = (int) DB::table('reservations')
                ->whereRaw(\App\Support\OpenReservationScope::rawPredicate('overall_status'))
                ->count();
            $statusRows = DB::table('reservations')
                ->selectRaw("LOWER(TRIM(COALESCE(overall_status, ''))) as status, COUNT(*) as total")
                ->groupByRaw("LOWER(TRIM(COALESCE(overall_status, '')))")
                ->orderByDesc('total')
                ->limit(12)
                ->get();
            foreach ($statusRows as $row) {
                $snapshot['statusCounts'][(string) ($row->status ?: '(empty)')] = (int) $row->total;
            }
        }

        if (Schema::hasTable('reservation_details')) {
            $snapshot['mostRequestedLive'] = count(self::getTopRequestedItems(10, true));
            $anyStatus = DB::table('reservation_details as details')
                ->join('reservations as reservations', 'reservations.reservation_id', '=', 'details.reservation_id')
                ->join('reservation_items as ri', 'ri.reservation_items_id', '=', 'details.reservation_items_id')
                ->whereBetween('reservations.created_at', [$monthStart, $monthEnd])
                ->whereNotNull('details.reservation_items_id')
                ->selectRaw('COUNT(DISTINCT ri.item_id) as total')
                ->value('total');
            $snapshot['mostRequestedAnyStatusThisMonth'] = (int) $anyStatus;
        }
        } catch (\Throwable $throwable) {
            $snapshot['error'] = substr($throwable->getMessage(), 0, 240);
        }

        try {
            file_put_contents(base_path('debug-e19b10.log'), json_encode([
                'sessionId' => 'e19b10',
                'timestamp' => (int) round(microtime(true) * 1000),
                'location' => 'DashboardInventoryCacheService.php:agentFetchDebugSnapshot',
                'message' => 'inventory/insights fetch snapshot',
                'data' => $snapshot,
                'hypothesisId' => 'B',
                'runId' => 'post-fix',
            ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
        }

        return $snapshot;
    }
    // #endregion
}
