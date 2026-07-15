<?php

namespace App\Services;

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
        $cacheKey = 'dashboard.inventory.data';

        return Cache::remember($cacheKey, self::CACHE_TTL * 60, function () {
            return [
                'facilityCount' => self::getFacilityCount(),
                'equipmentCount' => self::getEquipmentCount(),
                'maintenanceAndReportCount' => self::getMaintenanceAndReportCount(),
                'mostRequestedItems' => self::getTopRequestedItems(10, true),
            ];
        });
    }

    /**
     * Get inventory analytics data with caching
     */
    public static function getAnalyticsData(): array
    {
        $cacheKey = 'dashboard.inventory.analytics';

        return Cache::remember($cacheKey, self::CACHE_TTL * 60, function () {
            return [
                'yearLabels' => self::getYearLabels(),
                'trendBars' => self::getTrendBars(),
                'totalBorrowers' => self::getTotalBorrowers(),
                'borrowersGrowth' => self::getBorrowersGrowth(),
                'engagementCount' => self::getEngagementCount(),
                'engagementGrowth' => self::getEngagementGrowth(),
                'newUsers' => self::getNewUsers(),
                'newUsersGrowth' => self::getNewUsersGrowth(),
                'topItems' => self::getTopRequestedItems(5, true),
            ];
        });
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

    private static function getYearLabels(): array
    {
        $endMonth = now()->startOfMonth();
        $startMonth = (clone $endMonth)->subMonths(11);
        $labels = [];

        $cursorMonth = clone $startMonth;
        while ($cursorMonth <= $endMonth) {
            $labels[] = $cursorMonth->format('M');
            $cursorMonth->addMonth();
        }

        return $labels;
    }

    private static function getTrendBars(): array
    {
        if (!Schema::hasTable('reservations')) {
            return array_fill(0, 12, 0);
        }

        $endMonth = now()->startOfMonth();
        $startMonth = (clone $endMonth)->subMonths(11);
        $countsByMonth = [];

        $rows = DB::table('reservations')
            ->whereRaw("LOWER(COALESCE(overall_status, '')) = ?", ['approved'])
            ->whereBetween('created_at', [
                (clone $startMonth)->startOfMonth(),
                (clone $endMonth)->endOfMonth(),
            ])
            ->select('created_at')
            ->get();

        foreach ($rows as $row) {
            $monthKey = date('Y-m', strtotime((string) $row->created_at));
            $countsByMonth[$monthKey] = ($countsByMonth[$monthKey] ?? 0) + 1;
        }

        $yearCounts = [];
        $cursorMonth = clone $startMonth;
        while ($cursorMonth <= $endMonth) {
            $monthKey = $cursorMonth->format('Y-m');
            $yearCounts[] = $countsByMonth[$monthKey] ?? 0;
            $cursorMonth->addMonth();
        }

        $maxYearCount = max(1, max($yearCounts));
        return array_map(
            fn (int $count) => min(100, (int) round(($count / $maxYearCount) * 100)),
            $yearCounts
        );
    }

    private static function getTotalBorrowers(): int
    {
        if (!Schema::hasTable('reservations')) {
            return 0;
        }

        return (int) DB::table('reservations')
            ->whereRaw("LOWER(COALESCE(overall_status, '')) = ?", ['approved'])
            ->distinct('user_id')
            ->count('user_id');
    }

    private static function getBorrowersGrowth(): float
    {
        if (!Schema::hasTable('reservations')) {
            return 0.0;
        }

        $current = self::getTotalBorrowers();
        $previous = (int) DB::table('reservations')
            ->whereRaw("LOWER(COALESCE(overall_status, '')) = ?", ['approved'])
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->distinct('user_id')
            ->count('user_id');

        return self::percentChange($previous, $current);
    }

    private static function getEngagementCount(): int
    {
        if (!Schema::hasTable('reservation_details')) {
            return 0;
        }

        return (int) DB::table('reservation_details as details')
            ->join('reservations as reservations', 'reservations.reservation_id', '=', 'details.reservation_id')
            ->whereRaw("LOWER(COALESCE(reservations.overall_status, '')) = ?", ['approved'])
            ->sum('details.quantity') ?? 0;
    }

    private static function getEngagementGrowth(): float
    {
        if (!Schema::hasTable('reservation_details')) {
            return 0.0;
        }

        $current = self::getEngagementCount();
        $previous = (int) DB::table('reservation_details as details')
            ->join('reservations as reservations', 'reservations.reservation_id', '=', 'details.reservation_id')
            ->whereRaw("LOWER(COALESCE(reservations.overall_status, '')) = ?", ['approved'])
            ->whereBetween('reservations.created_at', [now()->subDays(60), now()->subDays(30)])
            ->sum('details.quantity') ?? 0;

        return self::percentChange($previous, $current);
    }

    private static function getNewUsers(): int
    {
        return Schema::hasTable('users')
            ? (int) DB::table('users')->where('created_at', '>=', now()->subDays(30))->count()
            : 0;
    }

    private static function getNewUsersGrowth(): float
    {
        if (!Schema::hasTable('users')) {
            return 0.0;
        }

        $current = self::getNewUsers();
        $previous = (int) DB::table('users')
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->count();

        return self::percentChange($previous, $current);
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
            ->groupBy(['items.item_id', 'items.item_name', 'owners.owner_name']);

        if ($approvedOnly) {
            $query->whereRaw("LOWER(COALESCE(reservations.overall_status, '')) = ?", ['approved']);
        }

        $rows = $query
            ->orderByDesc('usage_count')
            ->orderBy('items.item_name')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $maxUsage = max(1, (int) $rows->max('usage_count'));

        return $rows->map(function ($row) use ($maxUsage) {
            $usageCount = max(0, (int) ($row->usage_count ?? 0));

            return [
                'asset_id' => '#ITEM-' . str_pad((string) $row->item_id, 4, '0', STR_PAD_LEFT),
                'item_name' => (string) ($row->item_name ?? 'Unnamed Item'),
                'location' => trim((string) ($row->owner_name ?? 'Storage')),
                'category' => 'Equipment',
                'usage_percent' => min(100, (int) round(($usageCount / $maxUsage) * 100)),
            ];
        })->all();
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
        Cache::forget('dashboard.inventory.analytics');
    }
}
