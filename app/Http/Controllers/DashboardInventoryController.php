<?php

namespace App\Http\Controllers;

use App\Services\DashboardInventoryCacheService;
use App\Services\ItemUnitService;
use App\Support\ItemAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class DashboardInventoryController extends Controller
{
    private ?array $equipmentCategoriesCache = null;

    public function index()
    {
        return view('dashboard-inventory', DashboardInventoryCacheService::getInventoryData());
    }

    public function analytics()
    {
        return view('dashboard-inventory-analytics', DashboardInventoryCacheService::getAnalyticsData());
    }

    private function buildInventoryDashboardData(): array
    {
        $facilityCount = Schema::hasTable('rooms') ? DB::table('rooms')->count() : 0;
        $equipmentCount = Schema::hasTable('items') ? DB::table('items')->count() : 0;

        $maintenanceAndReportCount = 0;
        if (Schema::hasTable('maintenance')) {
            $maintenanceAndReportCount += DB::table('maintenance')->count();
        }
        if (Schema::hasTable('reports')) {
            $maintenanceAndReportCount += DB::table('reports')->count();
        }

        $mostRequestedItems = $this->buildTopRequestedItems(10, true);

        return [
            'facilityCount' => $facilityCount,
            'equipmentCount' => $equipmentCount,
            'maintenanceAndReportCount' => $maintenanceAndReportCount,
            'mostRequestedItems' => $mostRequestedItems,
        ];
    }

    private function buildAnalyticsDashboardData(): array
    {
        $yearLabels = [];
        $yearCounts = [];

        $endMonth = now()->startOfMonth();
        $startMonth = (clone $endMonth)->subMonths(11);

        $countsByMonth = [];

        if (Schema::hasTable('reservations')) {
            $approvedReservationRows = DB::table('reservations')
                ->whereRaw("LOWER(COALESCE(overall_status, '')) = ?", ['approved'])
                ->whereBetween('created_at', [
                    (clone $startMonth)->startOfMonth(),
                    (clone $endMonth)->endOfMonth(),
                ])
                ->select('created_at')
                ->get();

            foreach ($approvedReservationRows as $row) {
                if (empty($row->created_at)) {
                    continue;
                }

                $monthKey = date('Y-m', strtotime((string) $row->created_at));
                $countsByMonth[$monthKey] = ($countsByMonth[$monthKey] ?? 0) + 1;
            }
        }

        $cursorMonth = clone $startMonth;
        while ($cursorMonth <= $endMonth) {
            $monthKey = $cursorMonth->format('Y-m');
            $yearLabels[] = $cursorMonth->format('M');
            $yearCounts[] = $countsByMonth[$monthKey] ?? 0;
            $cursorMonth->addMonth();
        }

        $maxYearCount = max(1, max($yearCounts));
        $trendBars = array_map(
            fn (int $count) => min(100, (int) round(($count / $maxYearCount) * 100)),
            $yearCounts
        );

        $totalBorrowers = Schema::hasTable('reservations')
            ? (int) DB::table('reservations')
                ->whereRaw("LOWER(COALESCE(overall_status, '')) = ?", ['approved'])
                ->distinct('user_id')
                ->count('user_id')
            : 0;

        $engagementCount = Schema::hasTable('reservation_details')
            ? (int) DB::table('reservation_details as details')
                ->join('reservations as reservations', 'reservations.reservation_id', '=', 'details.reservation_id')
                ->whereRaw("LOWER(COALESCE(reservations.overall_status, '')) = ?", ['approved'])
                ->sum('details.quantity')
            : 0;

        $newUsers = Schema::hasTable('users')
            ? (int) DB::table('users')->where('created_at', '>=', now()->subDays(30))->count()
            : 0;

        $previousBorrowers = 0;
        $previousEngagement = 0;
        $previousNewUsers = 0;

        if (Schema::hasTable('reservations')) {
            $previousBorrowers = (int) DB::table('reservations')
                ->whereRaw("LOWER(COALESCE(overall_status, '')) = ?", ['approved'])
                ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
                ->distinct('user_id')
                ->count('user_id');
        }

        if (Schema::hasTable('reservation_details')) {
            $previousEngagement = (int) DB::table('reservation_details as details')
                ->join('reservations as reservations', 'reservations.reservation_id', '=', 'details.reservation_id')
                ->whereRaw("LOWER(COALESCE(reservations.overall_status, '')) = ?", ['approved'])
                ->whereBetween('reservations.created_at', [now()->subDays(60), now()->subDays(30)])
                ->sum('details.quantity');
        }

        if (Schema::hasTable('users')) {
            $previousNewUsers = (int) DB::table('users')
                ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
                ->count();
        }

        return [
            'yearLabels' => $yearLabels,
            'trendBars' => $trendBars,
            'totalBorrowers' => $totalBorrowers,
            'borrowersGrowth' => $this->percentChange($previousBorrowers, $totalBorrowers),
            'engagementCount' => $engagementCount,
            'engagementGrowth' => $this->percentChange($previousEngagement, $engagementCount),
            'newUsers' => $newUsers,
            'newUsersGrowth' => $this->percentChange($previousNewUsers, $newUsers),
            'topItems' => $this->buildTopRequestedItems(5, true),
        ];
    }

    private function buildTopRequestedItems(int $limit, bool $approvedOnly = false): array
    {
        if (!Schema::hasTable('reservation_details')) {
            return [];
        }

        $hasLegacyCategoryColumn = Schema::hasColumn('items', 'category');
        $usesCategoryLookup = Schema::hasTable('item_categories') && Schema::hasColumn('items', 'category_id');

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
            ->selectRaw('COALESCE(SUM(details.quantity), 0) as usage_count');

        if ($approvedOnly) {
            $query->whereRaw("LOWER(COALESCE(reservations.overall_status, '')) = ?", ['approved']);
        }

        if ($usesCategoryLookup) {
            $query->leftJoin('item_categories as categories', 'categories.category_id', '=', 'items.category_id')
                ->addSelect([
                    'categories.category_key as category_key',
                    'categories.display_name as category_display',
                ])
                ->groupBy([
                    'items.item_id',
                    'items.item_name',
                    'owners.owner_name',
                    'categories.category_key',
                    'categories.display_name',
                ]);

            if ($hasLegacyCategoryColumn) {
                $query->addSelect(DB::raw('items.category as legacy_category'));
                $query->groupBy('items.category');
            }
        } else {
            $query->addSelect([
                DB::raw($hasLegacyCategoryColumn ? 'items.category as legacy_category' : 'NULL as legacy_category'),
                DB::raw($hasLegacyCategoryColumn ? 'items.category as category_display' : 'NULL as category_display'),
                DB::raw('NULL as category_key'),
            ])
            ->groupBy([
                'items.item_id',
                'items.item_name',
                'owners.owner_name',
            ]);

            if ($hasLegacyCategoryColumn) {
                $query->groupBy('items.category');
            }
        }

        $rows = $query
            ->orderByDesc('usage_count')
            ->orderBy('items.item_name')
            ->limit($limit)
            ->get();

        $maxUsage = max(1, (int) $rows->max('usage_count'));

        $unitCodesByItem = ItemUnitService::loadUnitCodesGroupedByItem(
            $rows->pluck('item_id')->map(fn ($id) => (int) $id)->all()
        );

        return $rows->map(function ($row) use ($maxUsage, $unitCodesByItem) {
            $itemId = (int) $row->item_id;
            $usageCount = max(0, (int) ($row->usage_count ?? 0));
            $unitCodes = $unitCodesByItem[$itemId] ?? [];
            $normalizedCategory = $this->normalizeCategory((string) ($row->category_key ?? $row->legacy_category ?? ''));
            $resolvedCategory = trim((string) ($row->category_display ?? ''));

            if ($resolvedCategory === '') {
                $resolvedCategory = $normalizedCategory !== ''
                    ? ucwords(str_replace('_', ' ', $normalizedCategory))
                    : 'Uncategorized';
            }

            $ownerName = trim((string) ($row->owner_name ?? ''));

            return [
                'asset_id' => ItemUnitService::listAssetLabel($unitCodes, $itemId),
                'item_name' => (string) ($row->item_name ?? 'Unnamed Item'),
                'location' => $ownerName !== '' ? $ownerName : $this->locationFromCategory($normalizedCategory),
                'category' => $resolvedCategory,
                'usage_percent' => min(100, (int) round(($usageCount / $maxUsage) * 100)),
            ];
        })->all();
    }

    private function percentChange(int $previous, int $current): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

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
        $rowsByTab = [
            'maintenance' => [],
            'damaged' => [],
            'reported' => [],
        ];

        if (!Schema::hasTable('item_units')) {
            return view('dashboard-maintenance', [
                'maintenanceRowsByTab' => $rowsByTab,
            ]);
        }
        $unitQuery = DB::table('item_units')
            ->leftJoin('items', 'items.item_id', '=', 'item_units.item_id')
            ->select([
                'item_units.unit_id',
                'item_units.unit_code',
                'item_units.status',
                'item_units.condition_notes',
                'item_units.last_maintenance_at',
                'item_units.updated_at as unit_updated_at',
                'item_units.created_at as unit_created_at',
                'items.item_name',
            ])
            ->whereIn('item_units.status', ['maintenance', 'damaged']);

        if (Schema::hasColumn('items', 'category')) {
            $unitQuery->addSelect('items.category');
        } elseif (Schema::hasColumn('items', 'category_id') && Schema::hasTable('item_categories')) {
            $unitQuery->leftJoin('item_categories as categories', 'categories.category_id', '=', 'items.category_id')
                ->addSelect([
                    'categories.category_key as category_key',
                    'categories.display_name as category_display',
                ]);
        }

        $unitsNeedingAttention = $unitQuery
            ->orderByDesc('item_units.updated_at')
            ->get();

        foreach ($unitsNeedingAttention as $unit) {
            $category = $this->normalizeCategory((string) ($unit->category ?? $unit->category_key ?? $unit->category_display ?? 'multimedia'));
            $isDamaged = strtolower((string) $unit->status) === 'damaged';
            $tab = $isDamaged ? 'damaged' : 'maintenance';

            $dateSource = $unit->last_maintenance_at ?? $unit->unit_updated_at ?? $unit->unit_created_at;
            $dateLabel = $dateSource ? date('d/m/Y', strtotime((string) $dateSource)) : date('d/m/Y');

            $rowsByTab[$tab][] = [
                'row_type' => 'unit',
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

        if (Schema::hasTable('reports')) {
            $reportSelect = [
                'reports.report_id',
                'reports.report_info',
                'reports.generated_at',
                'reports.created_at',
                'reports.item_id',
                'reports.room_id',
                'items.item_name',
                'rooms.room_number',
                'users.full_name as reporter_full_name',
                'users.first_name as reporter_first_name',
                'users.last_name as reporter_last_name',
                'users.username as reporter_username',
            ];

            if (Schema::hasColumn('reports', 'description')) {
                $reportSelect[] = 'reports.description';
            }
            if (Schema::hasColumn('reports', 'proof_image_url')) {
                $reportSelect[] = 'reports.proof_image_url';
            }
            if (Schema::hasColumn('reports', 'reservation_id')) {
                $reportSelect[] = 'reports.reservation_id';
            }

            $reportRows = DB::table('reports as reports')
                ->leftJoin('items as items', 'items.item_id', '=', 'reports.item_id')
                ->leftJoin('rooms as rooms', 'rooms.room_id', '=', 'reports.room_id')
                ->leftJoin('users as users', 'users.user_id', '=', 'reports.user_id')
                ->select($reportSelect)
                ->orderByDesc('reports.generated_at')
                ->get();

            foreach ($reportRows as $report) {
                $reportText = trim((string) ($report->report_info ?? ''));
                $itemLabel = trim((string) ($report->item_name ?? ''));

                if ($itemLabel === '') {
                    $roomNumber = trim((string) ($report->room_number ?? ''));
                    $itemLabel = $roomNumber !== '' ? ('Room ' . $roomNumber) : 'Reported Issue';
                }

                $reporterName = trim((string) ($report->reporter_full_name ?? ''));
                if ($reporterName === '') {
                    $first = trim((string) ($report->reporter_first_name ?? ''));
                    $last  = trim((string) ($report->reporter_last_name ?? ''));
                    $reporterName = trim("$first $last");
                }
                if ($reporterName === '') {
                    $reporterName = trim((string) ($report->reporter_username ?? 'Unknown'));
                }

                $description = trim((string) ($report->description ?? ''));
                if ($description === '') {
                    // Fall back to parsing desc= from report_info
                    if (preg_match('/desc=([^|]+)/', $reportText, $m)) {
                        $description = trim($m[1]);
                    }
                }

                $proofUrl = trim((string) ($report->proof_image_url ?? ''));
                // Only pass the URL if it looks like an actual http URL (Supabase); skip local device paths
                if ($proofUrl !== '' && !str_starts_with($proofUrl, 'http')) {
                    $proofUrl = '';
                }

                // Convert Supabase storage URL to local proxy URL so private bucket images load correctly
                if ($proofUrl !== '') {
                    $filename = basename(parse_url($proofUrl, PHP_URL_PATH));
                    if ($filename !== '') {
                        $proofUrl = route('dashboard.storage.proof', ['filename' => $filename]);
                    }
                }

                $dateValue = $report->generated_at ?? $report->created_at;
                $linkedUnitId = 0;

                if (!empty($report->item_id) && Schema::hasTable('item_units')) {
                    $linkedUnitId = (int) (DB::table('item_units')
                        ->where('item_id', (int) $report->item_id)
                        ->whereIn('status', ['damaged', 'maintenance', 'in_use'])
                        ->orderByRaw("CASE status WHEN 'damaged' THEN 1 WHEN 'maintenance' THEN 2 WHEN 'in_use' THEN 3 ELSE 4 END")
                        ->value('unit_id') ?? 0);
                }

                $rowsByTab['reported'][] = [
                    'row_type' => $linkedUnitId > 0 ? 'unit' : 'report',
                    'unit_id' => $linkedUnitId,
                    'report_id' => (int) $report->report_id,
                    'id' => 'report_' . (int) $report->report_id,
                    'item' => $itemLabel,
                    'count' => '1',
                    'date' => $dateValue ? date('d/m/Y', strtotime((string) $dateValue)) : date('d/m/Y'),
                    'status' => 'Reported',
                    'statusClass' => 'reported',
                    'location' => strpos($itemLabel, 'Room ') === 0 ? 'Room' : 'Reported',
                    'reason' => $description !== '' ? $description : ($reportText !== '' ? $reportText : 'Reported issue'),
                    'reporter' => $reporterName,
                    'description' => $description,
                    'proof_image_url' => $proofUrl,
                ];
            }
        }

        if (Schema::hasTable('maintenance') && Schema::hasTable('rooms')) {
            $roomMaintenanceRows = DB::table('maintenance as maintenance')
                ->join('rooms as rooms', 'rooms.room_id', '=', 'maintenance.room_id')
                ->whereNotNull('maintenance.room_id')
                ->whereNull('maintenance.date_resolved')
                ->select([
                    'maintenance.maintenance_id',
                    'maintenance.room_id',
                    'rooms.room_number',
                    'maintenance.issue_description',
                    'maintenance.updated_at',
                    'maintenance.created_at',
                ])
                ->distinct()
                ->get();

            foreach ($roomMaintenanceRows as $row) {
                $dateSource = $row->updated_at ?? $row->created_at;
                $dateLabel = $dateSource ? date('d/m/Y', strtotime((string) $dateSource)) : date('d/m/Y');

                $rowsByTab['damaged'][] = [
                    'row_type' => 'room',
                    'unit_id' => 0,
                    'room_id' => (int) ($row->room_id ?? 0),
                    'maintenance_id' => (int) $row->maintenance_id,
                    'id' => 'room_' . (int) $row->maintenance_id,
                    'item' => 'Room ' . trim((string) ($row->room_number ?? '')),
                    'count' => '1',
                    'date' => $dateLabel,
                    'status' => 'Damaged',
                    'statusClass' => 'damaged',
                    'location' => 'Room',
                    'reason' => (string) ($row->issue_description ?? 'Damaged room'),
                ];
            }
        } elseif (Schema::hasTable('rooms')) {
            $roomRows = DB::table('rooms')
                ->where('maintenance_status', true)
                ->get(['room_id', 'room_number', 'updated_at', 'created_at']);

            foreach ($roomRows as $room) {
                $dateSource = $room->updated_at ?? $room->created_at;
                $dateLabel = $dateSource ? date('d/m/Y', strtotime((string) $dateSource)) : date('d/m/Y');

                $rowsByTab['maintenance'][] = [
                    'row_type' => 'room',
                    'unit_id' => 0,
                    'room_id' => (int) $room->room_id,
                    'maintenance_id' => 0,
                    'id' => 'room_' . (int) $room->room_id,
                    'item' => 'Room ' . trim((string) ($room->room_number ?? '')),
                    'count' => '1',
                    'date' => $dateLabel,
                    'status' => 'Maintenance',
                    'statusClass' => 'maintenance',
                    'location' => 'Room',
                    'reason' => 'Requires maintenance',
                ];
            }
        }

        return view('dashboard-maintenance', [
            'maintenanceRowsByTab' => $rowsByTab,
        ]);
    }

    public function updateMaintenanceUnit(Request $request, int $unitId): JsonResponse
    {
        if (!Schema::hasTable('item_units')) {
            return response()->json([
                'error' => 'Unit tracking table is not available.',
            ], 422);
        }

        $validated = $request->validate([
            'assessment' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:maintenance,damaged,fixed'],
        ]);

        $unit = DB::table('item_units')
            ->join('items', 'items.item_id', '=', 'item_units.item_id')
            ->where('item_units.unit_id', $unitId)
            ->select([
                'item_units.unit_id',
                'item_units.item_id',
                'item_units.unit_code',
                'items.item_name',
                'items.category',
            ])
            ->first();

        if (!$unit) {
            return response()->json([
                'error' => 'Item unit not found.',
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
            ->update([
                'quantity_total' => $totalActive,
                'quantity_in_use' => $inUseCount,
                'maintenance_status' => DB::raw($issueCount > 0 ? 'true' : 'false'),
                'availability_status' => DB::raw(($inUseCount <= 0 && $issueCount <= 0) ? 'true' : 'false'),
                'updated_at' => now(),
            ]);

        if ($targetStatus === 'available' && Schema::hasTable('maintenance')) {
            DB::table('maintenance')
                ->where('item_id', (int) $unit->item_id)
                ->whereNull('date_resolved')
                ->update([
                    'date_resolved' => now()->toDateString(),
                    'action_taken' => $validated['assessment'],
                    'updated_at' => now(),
                ]);
        }

        $normalizedCategory = $this->normalizeCategory((string) ($unit->category ?? 'multimedia'));
        $isDamaged = $targetStatus === 'damaged';

        return response()->json([
            'success' => true,
            'unit' => [
                'row_type' => 'unit',
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

    public function updateMaintenanceRoom(Request $request, int $roomId): JsonResponse
    {
        if (!Schema::hasTable('rooms')) {
            return response()->json(['error' => 'Rooms table is not available.'], 422);
        }

        $validated = $request->validate([
            'assessment' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:maintenance,damaged,fixed'],
        ]);

        $room = DB::table('rooms')->where('room_id', $roomId)->first();

        if (!$room) {
            return response()->json(['error' => 'Room not found.'], 404);
        }

        $isFixed = $validated['status'] === 'fixed';

        DB::table('rooms')
            ->where('room_id', $roomId)
            ->update([
                'maintenance_status' => $isFixed ? false : true,
                'availability_status' => $isFixed ? true : false,
                'date_reserved' => null,
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('maintenance')) {
            DB::table('maintenance')
                ->where('room_id', $roomId)
                ->whereNull('date_resolved')
                ->update([
                    'date_resolved' => $isFixed ? now()->toDateString() : null,
                    'action_taken' => $validated['assessment'],
                    'updated_at' => now(),
                ]);
        }

        return response()->json([
            'success' => true,
            'room' => [
                'row_type' => 'room',
                'room_id' => $roomId,
                'id' => 'room_' . $roomId,
                'item' => 'Room ' . trim((string) ($room->room_number ?? '')),
                'count' => '1',
                'date' => date('d/m/Y'),
                'status' => $isFixed ? 'Fixed' : ($validated['status'] === 'damaged' ? 'Damaged' : 'Maintenance'),
                'statusClass' => $isFixed ? 'maintenance' : ($validated['status'] === 'damaged' ? 'damaged' : 'maintenance'),
                'location' => 'Room',
                'reason' => $validated['assessment'],
                'resolved' => $isFixed,
            ],
        ]);
    }

    public function dismissMaintenanceReport(Request $request, int $reportId): JsonResponse
    {
        if (!Schema::hasTable('reports')) {
            return response()->json(['error' => 'Reports table is not available.'], 422);
        }

        $validated = $request->validate([
            'assessment' => ['required', 'string', 'max:255'],
        ]);

        $deleted = DB::table('reports')->where('report_id', $reportId)->delete();

        if (!$deleted) {
            return response()->json(['error' => 'Report not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'report' => [
                'row_type' => 'report',
                'report_id' => $reportId,
                'resolved' => true,
            ],
        ]);
    }

    public function equipments()
    {
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
                'items.availability_status',
                'item_owners.owner_name',
            ])
            ->orderBy('items.item_id')
            ->get()
            ->map(function ($row) {
                $category = $this->normalizeCategory((string) ($row->category_key ?? ''));
                $totalCount = max(0, (int) ($row->quantity_total ?? 0));
                $inUseCount = max(0, min($totalCount, (int) ($row->quantity_in_use ?? 0)));
                $maintenanceCount = (bool) $row->maintenance_status ? 1 : 0;
                $unitCodes = ItemUnitService::loadUnitCodesForItem((int) $row->item_id);

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
                    'asset_id' => ItemUnitService::listAssetLabel($unitCodes, (int) $row->item_id),
                    'unit_codes' => $unitCodes,
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
            'equipmentCategories' => $equipmentCategories,
            'defaultEquipmentCategory' => $equipmentCategories[0]['key'] ?? '',
        ]);
    }

    public function storeEquipmentCategory(Request $request): JsonResponse
    {
        if (!Schema::hasTable('item_categories')) {
            return response()->json([
                'error' => 'Item category table is not available yet. Please run migrations first.',
            ], 422);
        }

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:100'],
            'category_key' => ['nullable', 'string', 'max:64'],
        ]);

        $displayName = trim((string) $validated['display_name']);
        $categoryKey = $this->normalizeCategoryKey((string) ($validated['category_key'] ?? $displayName));

        if ($categoryKey === '') {
            return response()->json([
                'error' => 'Category key is invalid. Use letters and numbers.',
            ], 422);
        }

        $existing = DB::table('item_categories')
            ->where('category_key', $categoryKey)
            ->first();

        if ($existing) {
            DB::table('item_categories')
                ->where('category_key', $categoryKey)
                ->update([
                    'display_name' => $displayName,
                    'is_active' => DB::raw('true'),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('item_categories')->insert([
                'category_key' => $categoryKey,
                'display_name' => $displayName,
                'is_active' => DB::raw('true'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->equipmentCategoriesCache = null;
        $categories = $this->getEquipmentCategories();

        return response()->json([
            'success' => true,
            'category' => [
                'id' => $existing ? (int) $existing->category_id : (int) DB::table('item_categories')->where('category_key', $categoryKey)->value('category_id'),
                'key' => $categoryKey,
                'label' => $displayName,
            ],
            'categories' => $categories,
        ]);
    }

    public function destroyEquipmentCategory(int $categoryId): JsonResponse
    {
        if (!Schema::hasTable('item_categories')) {
            return response()->json([
                'error' => 'Item category table is not available yet.',
            ], 422);
        }

        $category = DB::table('item_categories')
            ->where('category_id', $categoryId)
            ->first(['category_id', 'category_key', 'display_name', 'is_active']);

        if (!$category) {
            return response()->json([
                'error' => 'Category not found.',
            ], 404);
        }

        $activeCount = (int) DB::table('item_categories')
            ->whereRaw('is_active = true')
            ->count();

        if ((bool) $category->is_active && $activeCount <= 1) {
            return response()->json([
                'error' => 'At least one active category is required.',
            ], 422);
        }

        if (Schema::hasTable('items') && Schema::hasColumn('items', 'category_id')) {
            $linkedItems = (int) DB::table('items')
                ->where('category_id', (int) $category->category_id)
                ->count();

            if ($linkedItems > 0) {
                return response()->json([
                    'error' => 'Cannot delete this category because it is assigned to existing items.',
                ], 422);
            }
        }

        DB::table('item_categories')
            ->where('category_id', (int) $category->category_id)
            ->update([
                'is_active' => DB::raw('false'),
                'updated_at' => now(),
            ]);

        $this->equipmentCategoriesCache = null;
        $categories = $this->getEquipmentCategories();

        return response()->json([
            'success' => true,
            'removed_category_key' => (string) $category->category_key,
            'categories' => $categories,
        ]);
    }

    public function updateEquipmentCategory(Request $request, int $categoryId): JsonResponse
    {
        if (!Schema::hasTable('item_categories')) {
            return response()->json([
                'error' => 'Item category table is not available yet.',
            ], 422);
        }

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:100'],
        ]);

        $category = DB::table('item_categories')
            ->where('category_id', $categoryId)
            ->first(['category_id', 'category_key', 'display_name']);

        if (!$category) {
            return response()->json([
                'error' => 'Category not found.',
            ], 404);
        }

        DB::table('item_categories')
            ->where('category_id', $categoryId)
            ->update([
                'display_name' => trim((string) $validated['display_name']),
                'updated_at' => now(),
            ]);

        $this->equipmentCategoriesCache = null;
        $categories = $this->getEquipmentCategories();

        return response()->json([
            'success' => true,
            'category' => [
                'id' => (int) $category->category_id,
                'key' => (string) $category->category_key,
                'label' => trim((string) $validated['display_name']),
            ],
            'categories' => $categories,
        ]);
    }

    public function updateEquipment(Request $request, int $itemId): JsonResponse
    {
        $categoryKeys = $this->getEquipmentCategoryKeys();

        if (empty($categoryKeys)) {
            return response()->json([
                'error' => 'No equipment categories configured yet. Add a category first.',
            ], 422);
        }

        $validated = $request->validate([
            'unit_codes' => ['nullable', 'array'],
            'unit_codes.*' => ['nullable', 'string', 'max:64'],
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

        $item = DB::table('items')->where('item_id', $itemId)->first();

        if (!$item) {
            return response()->json([
                'error' => 'Equipment item not found.',
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

        $unitCodes = DB::transaction(function () use ($itemId, $itemUpdatePayload, $validated) {
            DB::table('items')
                ->where('item_id', $itemId)
                ->update($itemUpdatePayload);

            return ItemUnitService::ensureUnitsForItem(
                $itemId,
                $validated['unit_codes'] ?? [],
                (int) $validated['total_count'],
                (int) $validated['in_use'],
                $validated['status'],
            );
        });

        DashboardInventoryCacheService::clearCache();

        $normalizedCategory = $this->normalizeCategory($databaseCategory);
        return response()->json([
            'success' => true,
            'item' => [
                'item_id' => $itemId,
                'asset_id' => ItemUnitService::listAssetLabel($unitCodes, $itemId),
                'unit_codes' => $unitCodes,
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
        $categoryKeys = $this->getEquipmentCategoryKeys();

        if (empty($categoryKeys)) {
            return response()->json([
                'error' => 'No equipment categories configured yet. Add a category first.',
            ], 422);
        }

        $validated = $request->validate([
            'unit_codes' => ['nullable', 'array'],
            'unit_codes.*' => ['nullable', 'string', 'max:64'],
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

        $ownerId = DB::table('item_owners')->orderBy('owner_id')->value('owner_id');

        if (!$ownerId) {
            return response()->json([
                'error' => 'No item owner found. Please create an owner first.',
            ], 422);
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

        // Keep PostgreSQL sequence aligned with existing data to avoid duplicate key errors.
        DB::statement("SELECT setval(pg_get_serial_sequence('items', 'item_id'), COALESCE(MAX(item_id), 0) + 1, false) FROM items");

        $itemInsertPayload = [
            'owner_id' => (int) $ownerId,
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

        $itemId = DB::transaction(function () use ($itemInsertPayload, $validated) {
            $itemId = DB::table('items')->insertGetId($itemInsertPayload, 'item_id');

            ItemUnitService::ensureUnitsForItem(
                (int) $itemId,
                $validated['unit_codes'] ?? [],
                (int) $validated['total_count'],
                (int) $validated['in_use'],
                $validated['status'],
            );

            return $itemId;
        });

        $unitCodes = ItemUnitService::loadUnitCodesForItem((int) $itemId);

        DashboardInventoryCacheService::clearCache();

        $normalizedCategory = $this->normalizeCategory($databaseCategory);

        return response()->json([
            'success' => true,
            'item' => [
                'item_id' => (int) $itemId,
                'asset_id' => ItemUnitService::listAssetLabel($unitCodes, (int) $itemId),
                'unit_codes' => $unitCodes,
                'category' => $normalizedCategory,
                'item_name' => $validated['item_name'],
                'total_count' => (int) $validated['total_count'],
                'in_use' => (int) $validated['in_use'],
                'status_key' => $validated['status'],
                'status_label' => $this->statusLabel($validated['status']),
            ],
        ]);
    }

    public function destroyEquipment(int $itemId): JsonResponse
    {
        $item = DB::table('items')
            ->where('item_id', $itemId)
            ->first(['item_id']);

        if (!$item) {
            return response()->json([
                'error' => 'Equipment item not found.',
            ], 404);
        }

        if (Schema::hasTable('item_units')) {
            DB::table('item_units')
                ->where('item_id', $itemId)
                ->delete();
        }

        DB::table('items')
            ->where('item_id', $itemId)
            ->delete();

        DashboardInventoryCacheService::clearCache();

        return response()->json([
            'success' => true,
            'deleted_item_id' => (int) $itemId,
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

    public function proxyProofImage(string $filename)
    {
        $serviceKey = env('SUPABASE_SERVICE_ROLE_KEY', '');
        $supabaseUrl = env('SUPABASE_URL', 'https://uszlgigsuseomkwmqwan.supabase.co');

        if (empty($serviceKey)) {
            abort(503, 'Storage proxy not configured.');
        }

        // Only allow safe filenames (alphanumeric, dash, underscore, dot)
        if (!preg_match('/^[\w\-\.]+$/', $filename)) {
            abort(400, 'Invalid filename.');
        }

        $url = rtrim($supabaseUrl, '/') . '/storage/v1/object/assets/issues/' . rawurlencode($filename);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $serviceKey,
        ])->get($url);

        if (!$response->successful()) {
            abort(404, 'Image not found.');
        }

        $contentType = $response->header('Content-Type') ?? 'image/jpeg';

        return response($response->body(), 200)
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'private, max-age=3600');
    }
}

