<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DashboardCacheService
{
    /**
     * Cache TTL in minutes (5 minutes is reasonable for dashboard)
     */
    private const CACHE_TTL = 5;

    /**
     * Get all dashboard data with caching
     */
    public static function getDashboardData(int $userId, int $officeId): array
    {
        $cacheKey = "dashboard.data.user.{$userId}.office.{$officeId}";

        return Cache::remember($cacheKey, self::CACHE_TTL * 60, function () use ($userId, $officeId) {
            return [
                'stats' => self::getStats(),
                'quickReports' => self::getQuickReports(),
                'upcomingRequests' => self::getUpcomingRequests(),
                'tasks' => self::getTasks($officeId),
                'dailyHighlights' => self::getDailyHighlights(),
            ];
        });
    }

    /**
     * Get cached schema information
     */
    private static function hasTable(string $table): bool
    {
        return Cache::remember("schema.table.{$table}", 3600, function () use ($table) {
            return Schema::hasTable($table);
        });
    }

    /**
     * Get cached column information
     */
    private static function hasColumn(string $table, string $column): bool
    {
        return Cache::remember("schema.column.{$table}.{$column}", 3600, function () use ($table, $column) {
            return Schema::hasColumn($table, $column);
        });
    }

    /**
     * Get all stats with single optimized query
     */
    private static function getStats(): array
    {
        $stats = [
            'total_requests' => 0,
            'borrowed' => 0,
            'available' => 0,
            'maintenance' => 0,
        ];

        if (!self::hasTable('reservations') && !self::hasTable('item_units') && !self::hasTable('items') && !self::hasTable('rooms')) {
            return $stats;
        }

        // Combine all stats queries into minimal set
        if (self::hasTable('reservations')) {
            $stats['total_requests'] = (int) DB::table('reservations')
                ->whereNotIn(DB::raw("LOWER(COALESCE(overall_status, ''))"), ['approved', 'rejected', 'cancelled', 'canceled', 'expired'])
                ->count();
        }

        if (self::hasTable('item_units')) {
            $unitStats = DB::table('item_units')
                ->selectRaw("SUM(CASE WHEN status = 'in_use' THEN 1 ELSE 0 END) as borrowed")
                ->selectRaw("SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available")
                ->selectRaw("SUM(CASE WHEN status IN ('maintenance', 'damaged') THEN 1 ELSE 0 END) as maintenance")
                ->first();

            $stats['borrowed'] = (int) ($unitStats->borrowed ?? 0);
            $stats['available'] = (int) ($unitStats->available ?? 0);
            $stats['maintenance'] = (int) ($unitStats->maintenance ?? 0);
        } elseif (self::hasTable('items')) {
            $itemStats = DB::table('items')
                ->selectRaw('SUM(COALESCE(quantity_in_use, 0)) as borrowed')
                ->selectRaw('SUM(GREATEST(COALESCE(quantity_total, 0) - COALESCE(quantity_in_use, 0), 0)) as available')
                ->selectRaw('SUM(CASE WHEN maintenance_status = true THEN 1 ELSE 0 END) as maintenance')
                ->first();

            $stats['borrowed'] = (int) ($itemStats->borrowed ?? 0);
            $stats['available'] = (int) ($itemStats->available ?? 0);
            $stats['maintenance'] = (int) ($itemStats->maintenance ?? 0);
        }

        if (self::hasTable('rooms')) {
            $roomStats = DB::table('rooms')
                ->selectRaw('SUM(CASE WHEN maintenance_status = true THEN 1 ELSE 0 END) as maintenance')
                ->first();

            $stats['maintenance'] += (int) ($roomStats->maintenance ?? 0);
        }

        return $stats;
    }

    /**
     * Get quick reports (limit to 6)
     */
    private static function getQuickReports(int $limit = 6): array
    {
        if (self::hasTable('reservation_issues')) {
            $query = DB::table('reservation_issues as issues')
                ->leftJoin('users as users', 'users.user_id', '=', 'issues.user_id')
                ->leftJoin('reservations as reservations', 'reservations.reservation_id', '=', 'issues.reservation_id')
                ->select([
                    'issues.issue_id',
                    'issues.description',
                    'issues.reported_by',
                    'issues.image_url',
                    'issues.status',
                    'issues.created_at',
                    'reservations.activity_name',
                    'users.first_name',
                    'users.middle_initial',
                    'users.last_name',
                    'users.suffix',
                    'users.full_name as reporter_full_name',
                    'users.username as reporter_username',
                ])
                ->orderByDesc('issues.created_at')
                ->limit($limit);

            return $query->get()
                ->map(function ($row) {
                    $statusRaw = strtolower(trim((string) ($row->status ?? 'pending')));
                    $isSolved = in_array($statusRaw, ['solved', 'resolved', 'fixed', 'closed', 'done', 'dismissed'], true);

                    $description = trim((string) ($row->description ?? ''));
                    $itemLabel = 'Reported Issue';
                    if (preg_match('/reported items?:\s*(.+)$/im', $description, $matches)) {
                        $itemLabel = trim((string) $matches[1]);
                    } elseif (trim((string) ($row->activity_name ?? '')) !== '') {
                        $itemLabel = trim((string) $row->activity_name);
                    } elseif ($description !== '') {
                        $itemLabel = strtok($description, "\n") ?: $description;
                    }

                    $hasImage = trim((string) ($row->image_url ?? '')) !== '';

                    return [
                        'item' => $itemLabel,
                        'reported_by' => \App\Models\User::formatDisplayName($row)
                            ?: trim((string) ($row->reported_by ?? $row->reporter_username ?? 'Unknown')),
                        'attachment_label' => $hasImage ? '1 Attachment' : 'No attachment',
                        'status_label' => $isSolved ? 'Solved' : 'Pending',
                        'status_class' => $isSolved ? 'solved' : 'pending',
                    ];
                })
                ->values()
                ->all();
        }

        if (!self::hasTable('reports')) {
            return [];
        }

        $query = DB::table('reports as reports')
            ->leftJoin('users as users', 'users.user_id', '=', 'reports.user_id')
            ->leftJoin('items as items', 'items.item_id', '=', 'reports.item_id')
            ->leftJoin('rooms as rooms', 'rooms.room_id', '=', 'reports.room_id')
            ->select([
                'reports.report_id',
                'reports.report_info',
                'users.first_name',
                'users.middle_initial',
                'users.last_name',
                'users.suffix',
                'users.full_name as reporter_full_name',
                'users.username as reporter_username',
                'items.item_name',
                'rooms.room_number',
            ]);

        if (self::hasColumn('reports', 'status')) {
            $query->addSelect('reports.status');
        }

        if (self::hasColumn('reports', 'attachment_count')) {
            $query->addSelect('reports.attachment_count');
        }

        if (self::hasColumn('reports', 'generated_at')) {
            $query->orderByDesc('reports.generated_at');
        } else {
            $query->orderByDesc('reports.created_at');
        }

        return $query->limit($limit)->get()
            ->map(function ($row) {
                $statusRaw = strtolower(trim((string) ($row->status ?? 'pending')));
                $isSolved = in_array($statusRaw, ['solved', 'resolved', 'fixed', 'closed', 'done'], true);

                $attachmentLabel = 'No attachment';
                if (isset($row->attachment_count)) {
                    $count = max(0, (int) ($row->attachment_count ?? 0));
                    $attachmentLabel = $count === 0 ? 'No attachment' : ($count === 1 ? '1 Attachment' : "$count Attachments");
                }

                $itemLabel = trim((string) ($row->item_name ?? ''));
                if ($itemLabel === '') {
                    $roomNumber = trim((string) ($row->room_number ?? ''));
                    $itemLabel = $roomNumber !== '' ? ('Room ' . $roomNumber) : 'General Report';
                }

                return [
                    'item' => $itemLabel,
                    'reported_by' => \App\Models\User::formatDisplayName($row),
                    'attachment_label' => $attachmentLabel,
                    'status_label' => $isSolved ? 'Solved' : 'Pending',
                    'status_class' => $isSolved ? 'solved' : 'pending',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Get open reservation requests for the PF dashboard (all active workflows).
     */
    private static function getUpcomingRequests(int $limit = 50): array
    {
        if (!self::hasTable('reservations')) {
            return [];
        }

        $rows = DB::table('reservations as reservations')
            ->leftJoin('users as users', 'users.user_id', '=', 'reservations.user_id')
            ->select([
                'reservations.reservation_id',
                'reservations.activity_name',
                'reservations.created_at',
                'reservations.overall_status',
                'users.first_name',
                'users.middle_initial',
                'users.last_name',
                'users.suffix',
                'users.full_name as requester_full_name',
                'users.username as requester_username',
            ])
            ->whereNotIn(DB::raw("LOWER(COALESCE(reservations.overall_status, ''))"), ['approved', 'rejected', 'returned', 'damaged', 'cancelled', 'canceled', 'expired'])
            ->whereRaw("LOWER(COALESCE(reservations.overall_status, '')) NOT LIKE ?", ['cancel%'])
            ->orderByDesc('reservations.created_at')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $reservationIds = $rows->pluck('reservation_id')->map(fn ($id) => (int) $id)->all();
        $resourceSummaryMap = self::getReservationResourceSummaryMap($reservationIds);

        return $rows
            ->map(function ($row) use ($resourceSummaryMap) {
                $createdAt = Carbon::parse($row->created_at);
                $requester = \App\Models\User::formatDisplayName($row);
                $resources = $resourceSummaryMap[(int) $row->reservation_id] ?? 'No resources listed';
                $status = trim((string) ($row->overall_status ?? ''));
                $statusSuffix = $status !== '' ? ' | Status: ' . str_replace('_', ' ', $status) : '';

                return [
                    'time_label' => $createdAt->format('F j, g:i A'),
                    'title' => (string) ($row->activity_name ?? 'Untitled Request'),
                    'subtitle' => 'Requester: ' . $requester . ' | Resources: ' . $resources . $statusSuffix,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Get resource summary for reservations
     */
    private static function getReservationResourceSummaryMap(array $reservationIds): array
    {
        if (empty($reservationIds) || !self::hasTable('reservation_details')) {
            return [];
        }

        $resourceRows = DB::table('reservation_details as details')
            ->leftJoin('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
            ->leftJoin('rooms as rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
            ->leftJoin('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->leftJoin('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->whereIn('details.reservation_id', $reservationIds)
            ->select([
                'details.reservation_id',
                'details.quantity',
                'rooms.room_number',
                'items.item_name',
            ])
            ->get();

        $summaryMap = [];

        foreach ($resourceRows as $resourceRow) {
            $isRoom = !is_null($resourceRow->room_number);
            $label = $isRoom ? ('Room ' . $resourceRow->room_number) : trim((string) ($resourceRow->item_name ?? ''));

            if ($label === '') {
                continue;
            }

            $quantity = max(1, (int) ($resourceRow->quantity ?? 1));
            $summaryMap[(int) $resourceRow->reservation_id][] = $quantity > 1 ? ($quantity . ' x ' . $label) : $label;
        }

        foreach ($summaryMap as $reservationId => $labels) {
            $summaryMap[$reservationId] = implode(', ', $labels);
        }

        return $summaryMap;
    }

    /**
     * Get tasks for office
     */
    private static function getTasks(int $officeId): array
    {
        $pendingFinalApprovals = 0;
        if (self::hasTable('reservation_approvals') && self::hasColumn('reservation_approvals', 'office_id')) {
            $pendingFinalApprovals = (int) DB::table('reservation_approvals as approvals')
                ->join('reservations as reservations', 'reservations.reservation_id', '=', 'approvals.reservation_id')
                ->where('approvals.office_id', $officeId)
                ->whereNull('approvals.approved_at')
                ->whereNotIn(DB::raw("LOWER(COALESCE(reservations.overall_status, ''))"), ['approved', 'rejected', 'cancelled', 'canceled', 'expired'])
                ->whereRaw("LOWER(COALESCE(reservations.overall_status, '')) NOT LIKE ?", ['cancel%'])
                ->count();
        }

        $reviewDamagedItems = 0;
        if (self::hasTable('item_units') && self::hasColumn('item_units', 'status')) {
            $reviewDamagedItems = (int) DB::table('item_units')
                ->whereRaw("LOWER(COALESCE(status, '')) = 'damaged'")
                ->count();
        } elseif (self::hasTable('items') && self::hasColumn('items', 'maintenance_status')) {
            $reviewDamagedItems = (int) DB::table('items')
                ->whereRaw('maintenance_status IS TRUE')
                ->count();
        }

        $needRepair = 0;
        if (self::hasTable('item_units') && self::hasColumn('item_units', 'status')) {
            $needRepair += (int) DB::table('item_units')
                ->whereRaw("LOWER(COALESCE(status, '')) = 'maintenance'")
                ->count();
        }

        if (self::hasTable('rooms') && self::hasColumn('rooms', 'maintenance_status')) {
            $needRepair += (int) DB::table('rooms')
                ->whereRaw('maintenance_status IS TRUE')
                ->count();
        }

        return [
            'pending_final_approvals' => $pendingFinalApprovals,
            'review_damaged_items' => $reviewDamagedItems,
            'need_repair' => $needRepair,
        ];
    }

    /**
     * Get daily highlights
     */
    private static function getDailyHighlights(): array
    {
        $resolvedToday = 0;
        if (self::hasTable('maintenance') && self::hasColumn('maintenance', 'date_resolved')) {
            $resolvedToday = (int) DB::table('maintenance')
                ->whereDate('date_resolved', now()->toDateString())
                ->count();
        }

        // If no maintenance records, check reservation issues / legacy reports marked solved today.
        if ($resolvedToday === 0 && self::hasTable('reservation_issues') && self::hasColumn('reservation_issues', 'status')) {
            $resolvedToday = (int) DB::table('reservation_issues')
                ->whereRaw("LOWER(COALESCE(status, '')) IN ('solved', 'resolved', 'fixed', 'closed', 'done', 'dismissed')")
                ->whereDate('created_at', now()->toDateString())
                ->count();
        }

        if ($resolvedToday === 0 && self::hasTable('reports') && self::hasColumn('reports', 'status') && self::hasColumn('reports', 'updated_at')) {
            $resolvedToday = (int) DB::table('reports')
                ->whereRaw("LOWER(COALESCE(status, '')) IN ('solved', 'resolved', 'fixed', 'closed', 'done')")
                ->whereDate('updated_at', now()->toDateString())
                ->count();
        }

        $pendingReports = 0;
        if (self::hasTable('reservation_issues')) {
            $pendingReports = (int) DB::table('reservation_issues')
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhereRaw("LOWER(COALESCE(status, '')) NOT IN ('resolved', 'solved', 'fixed', 'closed', 'done', 'dismissed')");
                })
                ->count();
        } elseif (self::hasTable('reports')) {
            if (self::hasColumn('reports', 'status')) {
                $pendingReports = (int) DB::table('reports')
                    ->whereRaw("LOWER(COALESCE(status, 'pending')) NOT IN ('solved', 'resolved', 'fixed', 'closed', 'done')")
                    ->count();
            } else {
                $pendingReports = (int) DB::table('reports')->count();
            }
        }

        $roomsUtilized = 0;
        if (self::hasTable('rooms') && self::hasColumn('rooms', 'date_reserved')) {
            $roomsUtilized = (int) DB::table('rooms')
                ->whereNotNull('date_reserved')
                ->whereDate('date_reserved', now()->toDateString())
                ->count();
        }

        // Fallback: Check reservations for today
        if ($roomsUtilized === 0 && self::hasTable('reservations')) {
            $roomsUtilized = (int) DB::table('reservations')
                ->whereDate('created_at', now()->toDateString())
                ->count();
        }

        $equipmentChecked = 0;
        if (self::hasTable('item_units')) {
            $equipmentChecked = (int) DB::table('item_units')
                ->whereDate('updated_at', now()->toDateString())
                ->count();
        } elseif (self::hasTable('items')) {
            $equipmentChecked = (int) DB::table('items')
                ->whereDate('updated_at', now()->toDateString())
                ->count();
        }

        return [
            'resolved_today' => $resolvedToday,
            'pending_reports' => $pendingReports,
            'rooms_utilized' => $roomsUtilized,
            'equipment_checked' => $equipmentChecked,
        ];
    }

    /**
     * Clear cache for dashboard
     */
    public static function clearCache(int $userId, int $officeId): void
    {
        $cacheKey = "dashboard.data.user.{$userId}.office.{$officeId}";
        Cache::forget($cacheKey);
    }

    /**
     * Clear all dashboard caches
     */
    public static function clearAllCaches(): void
    {
        Cache::forget('schema.*');
    }
}
