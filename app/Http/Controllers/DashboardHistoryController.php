<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardHistoryController extends Controller
{
    public function index()
    {
        $reservations = Reservation::query()
            ->with(['user', 'approvals'])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $reservationIds = $reservations->pluck('reservation_id')->map(fn ($id) => (int) $id)->all();
        $resourceMap = $this->buildResourceMap($reservationIds);

        $latestRows = $reservations->map(function (Reservation $reservation) use ($resourceMap) {
            $requestDate = Carbon::parse($reservation->created_at);
            $latestApprovalDate = $reservation->approvals
                ->pluck('approved_at')
                ->filter()
                ->map(fn ($value) => Carbon::parse($value))
                ->sortByDesc(fn (Carbon $date) => $date->timestamp)
                ->first();

            $endDate = $latestApprovalDate instanceof Carbon ? $latestApprovalDate : $requestDate;

            $status = strtolower((string) $reservation->overall_status);
            $statusLabel = match ($status) {
                'approved' => 'Returned',
                'rejected' => 'Rejected',
                default => 'Pending',
            };

            return [
                'id' => '#RES-' . str_pad((string) $reservation->reservation_id, 4, '0', STR_PAD_LEFT),
                'user' => trim((string) ($reservation->user?->full_name ?? $reservation->user?->username ?? 'Unknown user')),
                'date' => $requestDate->format('m/d/Y') . ' - ' . $endDate->format('m/d/Y'),
                'item' => $resourceMap[(int) $reservation->reservation_id] ?? 'No resource details',
                'status' => $statusLabel,
            ];
        })->values()->all();

        $historyRowsByTab = [
            'latest' => $latestRows,
            'oldest' => array_values(array_reverse($latestRows)),
            'damaged' => array_values(array_filter($latestRows, fn (array $row) => strtolower((string) ($row['status'] ?? '')) === 'damaged')),
        ];

        return view('dashboard-history', [
            'historyRowsByTab' => $historyRowsByTab,
        ]);
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
