<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user || !$user->isPhysicalFacilitiesAdmin()) {
            return redirect('/dashboard/home')->with('error', 'Unauthorized access.');
        }

        $selectedMonth = $this->resolveMonth($request->query('month'));
        $monthStart = $selectedMonth->copy()->startOfMonth();
        $monthEnd = $selectedMonth->copy()->endOfMonth();

        $scheduleRows = $this->buildApprovedReservationScheduleRows($monthStart, $monthEnd);
        $reservationIds = $scheduleRows->pluck('reservation_id')->map(fn ($reservationId) => (int) $reservationId)->all();

        $reservations = Reservation::query()
            ->with(['user', 'approvals.office', 'approvals.approver'])
            ->whereIn('reservation_id', $reservationIds)
            ->get()
            ->keyBy('reservation_id');

        $resourceMap = $this->buildResourceMap($reservationIds);

        $scheduleByDay = [];
        $markedDays = [
            'all' => [],
            'rooms' => [],
            'tv' => [],
            'speaker' => [],
            'furniture' => [],
        ];

        foreach ($scheduleRows as $row) {
            $reservation = $reservations->get((int) $row->reservation_id);

            if (is_null($reservation)) {
                continue;
            }

            $scheduleDate = Carbon::parse($row->schedule_date);
            $day = (int) $scheduleDate->day;
            $resources = $resourceMap[$reservation->reservation_id] ?? [];
            $categorizedRequest = $this->formatReservationForSchedule($reservation, $resources, $scheduleDate);

            $scheduleByDay[$day][] = $categorizedRequest;
            $markedDays['all'][$day] = true;

            foreach ($categorizedRequest['categories'] as $category) {
                if (array_key_exists($category, $markedDays)) {
                    $markedDays[$category][$day] = true;
                }
            }
        }

        ksort($scheduleByDay);

        $calendarCells = [];
        $leadingBlankCount = (int) $monthStart->dayOfWeek;

        for ($blankIndex = 0; $blankIndex < $leadingBlankCount; $blankIndex++) {
            $calendarCells[] = [
                'blank' => true,
            ];
        }

        for ($day = 1; $day <= $monthStart->daysInMonth; $day++) {
            $calendarCells[] = [
                'blank' => false,
                'day' => $day,
                'request_count' => count($scheduleByDay[$day] ?? []),
                'marked' => !empty($scheduleByDay[$day] ?? []),
            ];
        }

        $firstMarkedDay = !empty($scheduleByDay) ? (int) array_key_first($scheduleByDay) : null;

        return view('dashboard-schedule', [
            'monthLabel' => $monthStart->format('F Y'),
            'monthKey' => $monthStart->format('Y-m'),
            'previousMonthUrl' => route('dashboard.schedule', ['month' => $monthStart->copy()->subMonth()->format('Y-m')]),
            'nextMonthUrl' => route('dashboard.schedule', ['month' => $monthStart->copy()->addMonth()->format('Y-m')]),
            'calendarCells' => $calendarCells,
            'scheduleCalendarData' => [
                'monthKey' => $monthStart->format('Y-m'),
                'monthLabel' => $monthStart->format('F Y'),
                'markedDays' => $this->normalizeMarkedDays($markedDays),
                'requestData' => $scheduleByDay,
                'defaultDay' => $firstMarkedDay,
            ],
        ]);
    }

    private function buildApprovedReservationScheduleRows(Carbon $monthStart, Carbon $monthEnd)
    {
        $scheduleDateExpression = $this->scheduleDateExpressionForQuery();

        return DB::table('reservations as reservations')
            ->leftJoin('reservation_details as details', 'details.reservation_id', '=', 'reservations.reservation_id')
            ->leftJoin('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
            ->leftJoin('rooms as rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
            ->leftJoin('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->leftJoin('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->whereRaw("LOWER(COALESCE(reservations.overall_status, '')) = ?", ['approved'])
            ->select(['reservations.reservation_id'])
            ->selectRaw($scheduleDateExpression . ' as schedule_date')
            ->groupBy('reservations.reservation_id')
            ->havingRaw(
                $scheduleDateExpression . ' BETWEEN ? AND ?',
                [$monthStart->toDateString(), $monthEnd->toDateString()]
            )
            ->orderByRaw($scheduleDateExpression)
            ->get();
    }

    private function scheduleDateExpressionForQuery(): string
    {
        if (Schema::hasColumn('reservations', 'Date_of_Activity')) {
            if (Schema::hasColumn('reservations', 'Start_of_activity')) {
                return "COALESCE(MIN(\"reservations\".\"Date_of_Activity\"::date), MIN(\"reservations\".\"Start_of_activity\"::date), MAX(COALESCE(rooms.date_reserved, items.date_reserved)), MIN(reservations.created_at::date))";
            }

            return "COALESCE(MIN(\"reservations\".\"Date_of_Activity\"::date), MAX(COALESCE(rooms.date_reserved, items.date_reserved)), MIN(reservations.created_at::date))";
        }

        if (Schema::hasColumn('reservations', 'Start_of_activity')) {
            return "COALESCE(MIN(\"reservations\".\"Start_of_activity\"::date), MAX(COALESCE(rooms.date_reserved, items.date_reserved)), MIN(reservations.created_at::date))";
        }

        return "COALESCE(MAX(COALESCE(rooms.date_reserved, items.date_reserved)), MIN(reservations.created_at::date))";
    }

    private function resolveMonth(?string $monthValue): Carbon
    {
        if (is_string($monthValue) && preg_match('/^\d{4}-\d{2}$/', $monthValue) === 1) {
            try {
                return Carbon::createFromFormat('Y-m', $monthValue)->startOfMonth();
            } catch (\Throwable) {
                // Fall back to the current month when the query string is malformed.
            }
        }

        return now()->startOfMonth();
    }

    private function buildResourceMap(array $reservationIds): array
    {
        if (empty($reservationIds)) {
            return [];
        }

        $hasLegacyCategoryColumn = Schema::hasColumn('items', 'category');
        $usesCategoryLookup = Schema::hasTable('item_categories') && Schema::hasColumn('items', 'category_id');

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
                'rooms.date_reserved as room_date_reserved',
                'items.item_name',
                'items.date_reserved as item_date_reserved',
            ])
            ->addSelect(DB::raw($hasLegacyCategoryColumn ? 'items.category as legacy_category' : 'NULL as legacy_category'));

        if ($usesCategoryLookup) {
            $resourceRows->leftJoin('item_categories as categories', 'categories.category_id', '=', 'items.category_id')
                ->addSelect([
                    'categories.category_key as category_key',
                    'categories.display_name as category_display',
                ]);
        } else {
            $resourceRows->addSelect([
                DB::raw('NULL as category_key'),
                DB::raw('NULL as category_display'),
            ]);
        }

        $resourceRows = $resourceRows
            ->get();

        $resourceMap = [];

        foreach ($resourceRows as $row) {
            $isRoom = !is_null($row->room_number);
            $resourceLabel = $isRoom
                ? ('Room ' . $row->room_number)
                : (string) ($row->item_name ?? 'Resource');
            $resourceCategory = $isRoom
                ? 'rooms'
                : $this->normalizeResourceCategory((string) ($row->category_key ?? $row->legacy_category ?? $row->category_display ?? $resourceLabel));
            $quantity = max(1, (int) $row->quantity);
            $scheduledDate = $isRoom ? $row->room_date_reserved : $row->item_date_reserved;

            $resourceMap[$row->reservation_id][] = [
                'label' => $resourceLabel,
                'icon' => $isRoom ? 'bi-house-door-fill' : $this->resourceIconForCategory($resourceCategory, $resourceLabel),
                'quantity' => $quantity,
                'category' => $resourceCategory,
                'scheduled_date' => $scheduledDate,
            ];
        }

        return $resourceMap;
    }

    private function formatReservationForSchedule(Reservation $reservation, array $resources, Carbon $scheduleDate): array
    {
        $createdAt = Carbon::parse($reservation->created_at);
        $requesterName = trim((string) ($reservation->user?->full_name ?? $reservation->user?->username ?? 'Unknown requester'));
        $requesterId = (string) ($reservation->user?->username ?? $reservation->user?->user_id ?? $reservation->reservation_id);

        $resourceSummary = collect($resources)
            ->map(fn (array $resource) => $resource['quantity'] > 1
                ? $resource['quantity'] . ' x ' . $resource['label']
                : $resource['label'])
            ->implode(', ');

        $categories = collect($resources)
            ->pluck('category')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($categories)) {
            $categories = ['furniture'];
        }

        return [
            'reservation_id' => (int) $reservation->reservation_id,
            'reservation_code' => '#RES-' . str_pad((string) $reservation->reservation_id, 4, '0', STR_PAD_LEFT),
            'requester_name' => $requesterName,
            'requester_id' => $requesterId,
            'activity_name' => (string) $reservation->activity_name,
            'scheduled_on' => $scheduleDate->format('F j, Y'),
            'scheduled_date_key' => $scheduleDate->format('Y-m-d'),
            'requested_on' => $createdAt->format('F j, Y'),
            'requested_time' => $createdAt->format('g:i A'),
            'status_label' => 'Fully Approved',
            'status_class' => 'is-approved',
            'resource_summary' => $resourceSummary !== '' ? $resourceSummary : 'No resource details available',
            'resources' => $resources,
            'categories' => $categories,
            'approval_steps' => $this->formatApprovalTrail($reservation),
        ];
    }

    private function formatApprovalTrail(Reservation $reservation): array
    {
        return $reservation->approvals
            ->sortBy(fn ($approval) => $approval->office?->order_sequence ?? $approval->office_id)
            ->map(function ($approval) {
                $officeLabel = trim((string) ($approval->office?->short_code ?? $approval->office?->department_name ?? 'Office'));

                return [
                    'office' => $officeLabel !== '' ? $officeLabel : 'Office',
                    'status' => ucfirst((string) ($approval->status ?? 'approved')),
                    'approved_at' => !is_null($approval->approved_at)
                        ? Carbon::parse($approval->approved_at)->format('M j, Y')
                        : '-',
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeMarkedDays(array $markedDays): array
    {
        $normalized = [];

        foreach ($markedDays as $category => $days) {
            $normalized[$category] = array_values(array_unique(array_map('intval', array_keys($days))));
            sort($normalized[$category]);
        }

        return $normalized;
    }

    private function normalizeResourceCategory(string $value): string
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return 'furniture';
        }

        if (str_contains($normalized, 'room') || preg_match('/^\d+$/', $normalized) === 1) {
            return 'rooms';
        }

        if (str_contains($normalized, 'tv') || str_contains($normalized, 'monitor') || str_contains($normalized, 'projector') || str_contains($normalized, 'screen')) {
            return 'tv';
        }

        if (str_contains($normalized, 'speaker') || str_contains($normalized, 'mic') || str_contains($normalized, 'audio') || str_contains($normalized, 'sound')) {
            return 'speaker';
        }

        return 'furniture';
    }

    private function resourceIconForCategory(string $category, string $label): string
    {
        return match ($category) {
            'rooms' => 'bi-building',
            'tv' => 'bi-tv',
            'speaker' => 'bi-speaker-fill',
            default => str_contains(strtolower($label), 'table') ? 'bi-table' : 'bi-easel-fill',
        };
    }
}