<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
        $resourceMap = $this->buildResourceMap($reservationIds);

        $latestRows = $reservations->map(function (Reservation $reservation) use ($resourceMap) {
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
                'sort_ts' => $endDate->timestamp,
            ];
        })->sortByDesc('sort_ts')->values()->map(function (array $row) {
            unset($row['sort_ts']);

            return $row;
        })->all();

        $historyRowsByTab = [
            'latest' => $latestRows,
            'oldest' => array_values(array_reverse($latestRows)),
            'damaged' => array_values(array_filter($latestRows, fn (array $row) => ($row['raw_status'] ?? '') === 'damaged')),
        ];

        return view('dashboard-history', [
            'historyRowsByTab' => $historyRowsByTab,
        ]);
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
