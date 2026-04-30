<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DashboardHomeController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (!$user || !$user->isPhysicalFacilitiesAdmin()) {
            return redirect('/dashboard/office/home')->with('error', 'Unauthorized access.');
        }

        return view('dashboard-home', [
            'stats' => $this->buildStats(),
            'quickReports' => $this->buildQuickReports(),
            'upcomingRequests' => $this->buildUpcomingRequestsForToday(),
            'dailyHighlights' => $this->buildDailyHighlights(),
        ]);
    }

    private function buildStats(): array
    {
        $stats = [
            'total_requests' => 0,
            'borrowed' => 0,
            'available' => 0,
            'maintenance' => 0,
        ];

        if (Schema::hasTable('reservations')) {
            $stats['total_requests'] = (int) DB::table('reservations')
                ->whereNotIn(DB::raw("LOWER(COALESCE(overall_status, ''))"), ['approved', 'rejected'])
                ->count();
        }

        if (Schema::hasTable('item_units')) {
            $unitStats = DB::table('item_units')
                ->selectRaw("SUM(CASE WHEN status = 'in_use' THEN 1 ELSE 0 END) as borrowed")
                ->selectRaw("SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available")
                ->selectRaw("SUM(CASE WHEN status IN ('maintenance', 'damaged') THEN 1 ELSE 0 END) as maintenance")
                ->first();

            $stats['borrowed'] = (int) ($unitStats->borrowed ?? 0);
            $stats['available'] = (int) ($unitStats->available ?? 0);
            $stats['maintenance'] = (int) ($unitStats->maintenance ?? 0);
        } elseif (Schema::hasTable('items')) {
            $itemStats = DB::table('items')
                ->selectRaw('SUM(COALESCE(quantity_in_use, 0)) as borrowed')
                ->selectRaw('SUM(GREATEST(COALESCE(quantity_total, 0) - COALESCE(quantity_in_use, 0), 0)) as available')
                ->selectRaw('SUM(CASE WHEN maintenance_status = true THEN 1 ELSE 0 END) as maintenance')
                ->first();

            $stats['borrowed'] = (int) ($itemStats->borrowed ?? 0);
            $stats['available'] = (int) ($itemStats->available ?? 0);
            $stats['maintenance'] = (int) ($itemStats->maintenance ?? 0);
        }

        if (Schema::hasTable('rooms')) {
            $roomStats = DB::table('rooms')
                ->selectRaw('SUM(CASE WHEN maintenance_status = true THEN 1 ELSE 0 END) as maintenance')
                ->first();

            $stats['maintenance'] += (int) ($roomStats->maintenance ?? 0);
        }

        return $stats;
    }

    private function buildQuickReports(int $limit = 6): array
    {
        if (!Schema::hasTable('reports')) {
            return [];
        }

        $hasStatusColumn = Schema::hasColumn('reports', 'status');
        $hasAttachmentCountColumn = Schema::hasColumn('reports', 'attachment_count');
        $hasAttachmentColumn = Schema::hasColumn('reports', 'attachment');
        $hasGeneratedAtColumn = Schema::hasColumn('reports', 'generated_at');

        $query = DB::table('reports as reports')
            ->leftJoin('users as users', 'users.user_id', '=', 'reports.user_id')
            ->leftJoin('items as items', 'items.item_id', '=', 'reports.item_id')
            ->leftJoin('rooms as rooms', 'rooms.room_id', '=', 'reports.room_id')
            ->select([
                'reports.report_id',
                'reports.report_info',
                'users.full_name as reporter_full_name',
                'users.username as reporter_username',
                'items.item_name',
                'rooms.room_number',
            ]);

        if ($hasStatusColumn) {
            $query->addSelect('reports.status');
        }

        if ($hasAttachmentCountColumn) {
            $query->addSelect('reports.attachment_count');
        }

        if ($hasAttachmentColumn) {
            $query->addSelect('reports.attachment');
        }

        if ($hasGeneratedAtColumn) {
            $query->addSelect('reports.generated_at')->orderByDesc('reports.generated_at');
        } else {
            $query->orderByDesc('reports.created_at');
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(function ($row) use ($hasStatusColumn, $hasAttachmentCountColumn, $hasAttachmentColumn) {
                $statusRaw = $hasStatusColumn ? strtolower(trim((string) ($row->status ?? 'pending'))) : 'pending';
                $isSolved = in_array($statusRaw, ['solved', 'resolved', 'fixed', 'closed', 'done'], true);

                $attachmentLabel = 'No attachment';

                if ($hasAttachmentCountColumn) {
                    $count = max(0, (int) ($row->attachment_count ?? 0));
                    if ($count === 1) {
                        $attachmentLabel = '1 Attachment';
                    } elseif ($count > 1) {
                        $attachmentLabel = $count . ' Attachments';
                    }
                } elseif ($hasAttachmentColumn) {
                    $attachmentValue = trim((string) ($row->attachment ?? ''));
                    if ($attachmentValue !== '') {
                        $attachmentLabel = $attachmentValue;
                    }
                }

                $itemLabel = trim((string) ($row->item_name ?? ''));
                if ($itemLabel === '') {
                    $roomNumber = trim((string) ($row->room_number ?? ''));
                    $itemLabel = $roomNumber !== '' ? ('Room ' . $roomNumber) : 'General Report';
                }

                return [
                    'item' => $itemLabel,
                    'reported_by' => trim((string) ($row->reporter_full_name ?? $row->reporter_username ?? 'Unknown')),
                    'attachment_label' => $attachmentLabel,
                    'status_label' => $isSolved ? 'Solved' : 'Pending',
                    'status_class' => $isSolved ? 'solved' : 'pending',
                ];
            })
            ->values()
            ->all();
    }

    private function buildUpcomingRequestsForToday(int $limit = 6): array
    {
        if (!Schema::hasTable('reservations')) {
            return [];
        }

        $today = now()->toDateString();

        $rows = DB::table('reservations as reservations')
            ->leftJoin('users as users', 'users.user_id', '=', 'reservations.user_id')
            ->select([
                'reservations.reservation_id',
                'reservations.activity_name',
                'reservations.created_at',
                'users.full_name as requester_full_name',
                'users.username as requester_username',
            ])
            ->whereDate('reservations.created_at', $today)
            ->orderByDesc('reservations.created_at')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $reservationIds = $rows->pluck('reservation_id')->map(fn ($id) => (int) $id)->all();
        $resourceSummaryMap = $this->buildReservationResourceSummaryMap($reservationIds);

        return $rows
            ->map(function ($row) use ($resourceSummaryMap) {
                $createdAt = Carbon::parse($row->created_at);
                $requester = trim((string) ($row->requester_full_name ?? $row->requester_username ?? 'Unknown'));
                $resources = $resourceSummaryMap[(int) $row->reservation_id] ?? 'No resources listed';

                return [
                    'time_label' => $createdAt->format('F j, g:i A'),
                    'title' => (string) ($row->activity_name ?? 'Untitled Request'),
                    'subtitle' => 'Requester: ' . $requester . ' | Resources: ' . $resources,
                ];
            })
            ->values()
            ->all();
    }

    private function buildReservationResourceSummaryMap(array $reservationIds): array
    {
        if (empty($reservationIds) || !Schema::hasTable('reservation_details')) {
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
            $label = $isRoom
                ? ('Room ' . $resourceRow->room_number)
                : trim((string) ($resourceRow->item_name ?? ''));

            if ($label === '') {
                continue;
            }

            $quantity = max(1, (int) ($resourceRow->quantity ?? 1));
            $summaryMap[(int) $resourceRow->reservation_id][] = $quantity > 1
                ? ($quantity . ' x ' . $label)
                : $label;
        }

        foreach ($summaryMap as $reservationId => $labels) {
            $summaryMap[$reservationId] = implode(', ', $labels);
        }

        return $summaryMap;
    }

    private function buildDailyHighlights(): array
    {
        $resolvedToday = 0;
        if (Schema::hasTable('maintenance') && Schema::hasColumn('maintenance', 'date_resolved')) {
            $resolvedToday = (int) DB::table('maintenance')
                ->whereDate('date_resolved', now()->toDateString())
                ->count();
        }

        $pendingReports = 0;
        if (Schema::hasTable('reports')) {
            if (Schema::hasColumn('reports', 'status')) {
                $pendingReports = (int) DB::table('reports')
                    ->whereRaw("LOWER(COALESCE(status, 'pending')) NOT IN ('solved', 'resolved', 'fixed', 'closed', 'done')")
                    ->count();
            } else {
                $pendingReports = (int) DB::table('reports')->count();
            }
        }

        $roomsUtilized = 0;
        if (Schema::hasTable('rooms')) {
            $roomsUtilized = (int) DB::table('rooms')
                ->whereNotNull('date_reserved')
                ->whereDate('date_reserved', now()->toDateString())
                ->count();
        }

        $equipmentChecked = 0;
        if (Schema::hasTable('item_units')) {
            $equipmentChecked = (int) DB::table('item_units')
                ->whereDate('updated_at', now()->toDateString())
                ->count();
        } elseif (Schema::hasTable('items')) {
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
}