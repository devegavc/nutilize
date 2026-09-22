<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DashboardHistoryController extends Controller
{
    /** @var list<string> */
    private const COMPLETED_STATUSES = [
        'returned',
        'damaged',
        'rejected',
        'expired',
        'cancelled',
        'canceled',
    ];

    public function index()
    {
        $historyRows = $this->loadHistoryRows();

        return view('dashboard-history', [
            'historyRows' => $historyRows,
            'historyCounts' => $this->historyCounts($historyRows),
        ]);
    }

    public function sendReport(Request $request)
    {
        $email = trim((string) ($request->user()?->email ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send history report. Please try again.',
            ], 422);
        }

        $category = $this->normalizeCategory($request->input('category'));
        $sort = $this->normalizeSort($request->input('sort'));
        $from = $this->normalizeDate($request->input('from'));
        $to = $this->normalizeDate($request->input('to'));

        $rows = $this->filterHistoryRows($this->loadHistoryRows(), $category, $sort, $from, $to);
        $subject = $this->reportTitle($category, $from, $to);

        try {
            Mail::html(
                $this->reportHtml($rows, $subject),
                function ($message) use ($email, $subject) {
                    $message->to($email)->subject($subject);
                }
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send history report. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'History report sent successfully.',
        ]);
    }

    private function loadHistoryRows(): array
    {
        $reservations = Reservation::query()
            ->with(['user', 'approvals'])
            ->where(function ($query) {
                foreach (self::COMPLETED_STATUSES as $status) {
                    $query->orWhereRaw('LOWER(COALESCE(overall_status, \'\')) = ?', [$status]);
                }
            })
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        $reservationIds = $reservations->pluck('reservation_id')->map(fn ($id) => (int) $id)->all();

        return $this->mapHistoryRows($reservations, $this->buildResourceMap($reservationIds));
    }

    private function mapHistoryRows($reservations, array $resourceMap): array
    {
        return $reservations->map(function (Reservation $reservation) use ($resourceMap) {
            $status = strtolower((string) $reservation->overall_status);
            $statusLabel = match (true) {
                $status === 'returned' => 'Returned',
                $status === 'damaged' => 'Damaged',
                $status === 'rejected' => 'Rejected',
                $status === 'expired' => 'Expired',
                str_starts_with($status, 'cancel') => 'Cancelled',
                default => ucfirst($status),
            };

            $startDate = $this->resolveActivityStartDate($reservation);
            $endDate = $this->resolveHistoryEndDate($reservation, $status);

            return [
                'id' => '#RES-' . str_pad((string) $reservation->reservation_id, 4, '0', STR_PAD_LEFT),
                'user' => trim((string) ($reservation->user?->full_name ?? $reservation->user?->username ?? 'Unknown user')),
                'date' => $startDate->format('m/d/Y') . ' - ' . $endDate->format('m/d/Y'),
                'item' => $resourceMap[(int) $reservation->reservation_id] ?? 'No resource details',
                'status' => $statusLabel,
                'raw_status' => $status,
                'category' => $status === 'damaged' ? 'damaged' : 'lending',
                'filter_date' => $endDate->format('Y-m-d'),
                'sort_ts' => $endDate->timestamp,
            ];
        })->sortByDesc('sort_ts')->values()->all();
    }

    private function historyCounts(array $rows): array
    {
        $damaged = count(array_filter($rows, fn (array $row) => ($row['category'] ?? '') === 'damaged'));

        return [
            'all' => count($rows),
            'lending' => count($rows) - $damaged,
            'damaged' => $damaged,
        ];
    }

    private function filterHistoryRows(array $rows, string $category, string $sort, ?string $from, ?string $to): array
    {
        $filtered = array_values(array_filter($rows, function (array $row) use ($category, $from, $to) {
            if ($category !== 'all' && ($row['category'] ?? 'lending') !== $category) {
                return false;
            }

            $date = (string) ($row['filter_date'] ?? '');
            if (($from || $to) && $date === '') {
                return false;
            }

            if ($from && $date < $from) {
                return false;
            }

            if ($to && $date > $to) {
                return false;
            }

            if ($from && $to && $from > $to) {
                return false;
            }

            return true;
        }));

        usort($filtered, function (array $left, array $right) use ($sort) {
            $comparison = ((int) ($left['sort_ts'] ?? 0)) <=> ((int) ($right['sort_ts'] ?? 0));

            return $sort === 'oldest' ? $comparison : -$comparison;
        });

        return $filtered;
    }

    private function normalizeCategory(mixed $value): string
    {
        $category = strtolower(trim((string) $value));

        return in_array($category, ['lending', 'damaged'], true) ? $category : 'all';
    }

    private function normalizeSort(mixed $value): string
    {
        return strtolower(trim((string) $value)) === 'oldest' ? 'oldest' : 'latest';
    }

    private function normalizeDate(mixed $value): ?string
    {
        $date = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function reportTitle(string $category, ?string $from, ?string $to): string
    {
        $title = $category === 'damaged' ? 'Damaged History' : 'Lending History';
        if (!$from && !$to) {
            return $title;
        }

        $fromLabel = $from ? Carbon::createFromFormat('Y-m-d', $from)->format('F j, Y') : 'the beginning';
        $toLabel = $to ? Carbon::createFromFormat('Y-m-d', $to)->format('F j, Y') : 'today';

        return $title . ' — ' . $fromLabel . ' to ' . $toLabel;
    }

    private function reportHtml(array $rows, string $title): string
    {
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>'
                . '<td>' . e($row['id'] ?? '') . '</td>'
                . '<td>' . e($row['user'] ?? '') . '</td>'
                . '<td>' . e($row['date'] ?? '') . '</td>'
                . '<td>' . e($row['item'] ?? '') . '</td>'
                . '<td>' . e($row['status'] ?? '') . '</td>'
                . '</tr>';
        }

        if ($body === '') {
            $body = '<tr><td colspan="5">No history records found.</td></tr>';
        }

        return '<h1>' . e($title) . '</h1>'
            . '<p>' . count($rows) . ' record' . (count($rows) === 1 ? '' : 's') . '</p>'
            . '<table border="1" cellpadding="6" cellspacing="0">'
            . '<thead><tr><th>Lending ID</th><th>User Name</th><th>Date</th><th>Item Borrowed</th><th>Item Status</th></tr></thead>'
            . '<tbody>' . $body . '</tbody></table>';
    }

    private function resolveActivityStartDate(Reservation $reservation): Carbon
    {
        $candidates = [
            $reservation->start_of_activity,
            $reservation->Start_of_activity,
            $reservation->date_of_activity,
            $reservation->Date_of_Activity,
            $reservation->created_at,
        ];

        foreach ($candidates as $candidate) {
            if (!is_null($candidate)) {
                return Carbon::parse($candidate);
            }
        }

        return Carbon::parse($reservation->created_at);
    }

    private function resolveHistoryEndDate(Reservation $reservation, string $status): Carbon
    {
        $matchingApproval = $reservation->approvals
            ->filter(fn ($approval) => strtolower((string) ($approval->status ?? '')) === $status)
            ->pluck('approved_at')
            ->filter()
            ->map(fn ($value) => Carbon::parse($value))
            ->sortByDesc(fn (Carbon $date) => $date->timestamp)
            ->first();

        if ($matchingApproval instanceof Carbon) {
            return $matchingApproval;
        }

        $latestApprovalDate = $reservation->approvals
            ->pluck('approved_at')
            ->filter()
            ->map(fn ($value) => Carbon::parse($value))
            ->sortByDesc(fn (Carbon $date) => $date->timestamp)
            ->first();

        if ($latestApprovalDate instanceof Carbon) {
            return $latestApprovalDate;
        }

        if (!is_null($reservation->updated_at)) {
            return Carbon::parse($reservation->updated_at);
        }

        return $this->resolveActivityStartDate($reservation);
    }

    private function buildResourceMap(array $reservationIds): array
    {
        if (empty($reservationIds)) {
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

        $resourceMap = [];

        foreach ($resourceRows as $row) {
            $isRoom = !is_null($row->room_number);
            $resourceName = $isRoom
                ? 'Room ' . $row->room_number
                : (string) ($row->item_name ?? 'Resource');
            $quantity = max(1, (int) $row->quantity);
            $label = $quantity > 1 ? ($quantity . ' x ' . $resourceName) : $resourceName;

            $resourceMap[(int) $row->reservation_id][] = $label;
        }

        return collect($resourceMap)
            ->map(fn (array $labels) => implode(', ', $labels))
            ->all();
    }
}
