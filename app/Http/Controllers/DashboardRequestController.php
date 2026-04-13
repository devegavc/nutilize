<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationApproval;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardRequestController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (!$user || strtolower((string) $user->role) !== 'admin') {
            return redirect('/dashboard/home')->with('error', 'Unauthorized access.');
        }

        $isPfAdmin = $user->isPhysicalFacilitiesAdmin();
        $physicalFacilitiesOfficeId = $isPfAdmin ? $this->getPhysicalFacilitiesOfficeId() : null;

        $reservations = Reservation::query()
            ->with(['user', 'approvals'])
            ->orderByDesc('created_at')
            ->get();

        $resourceMap = $this->buildResourceMap($reservations->pluck('reservation_id')->all());

        $preparedRequests = $reservations->map(function (Reservation $reservation) use ($resourceMap, $physicalFacilitiesOfficeId) {
            $overallStatus = strtolower((string) $reservation->overall_status);
            $isFinal = in_array($overallStatus, ['approved', 'rejected'], true);

            $approvedSteps = min(5, $reservation->approvals->where('status', 'approved')->count());
            $resources = $resourceMap[$reservation->reservation_id] ?? [];
            $pfApproval = $physicalFacilitiesOfficeId
                ? $reservation->approvals->firstWhere('office_id', $physicalFacilitiesOfficeId)
                : null;
            $displayApprovedSteps = ($overallStatus === 'approved' || ($pfApproval && $pfApproval->status === 'approved'))
                ? 5
                : $approvedSteps;

            return [
                'reservation' => $reservation,
                'tab' => $isFinal ? 'final' : 'pending',
                'approved_steps' => $displayApprovedSteps,
                'resources' => $resources,
                'approval_id' => $pfApproval?->approval_id,
                'decision_badge' => !$isFinal ? 'Pending' : ($overallStatus === 'rejected' ? 'Rejected' : 'Approved'),
                'decision_status_class' => !$isFinal ? '' : ($overallStatus === 'rejected' ? 'is-rejected' : 'is-approved'),
            ];
        });

        return view('dashboard-request', [
            'finalRequests' => $preparedRequests->where('tab', 'final')->values(),
            'pendingRequests' => $preparedRequests->where('tab', 'pending')->values(),
            'isPfAdmin' => $isPfAdmin,
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
            $name = $isRoom ? ('Room ' . $row->room_number) : ($row->item_name ?? 'Resource');
            $quantity = max(1, (int) $row->quantity);

            $resourceMap[$row->reservation_id][] = [
                'label' => $name,
                'icon' => $isRoom ? 'bi-house-door-fill' : 'bi-box-seam',
                'quantity' => $quantity,
            ];
        }

        return $resourceMap;
    }

    private function getPhysicalFacilitiesOfficeId(): ?int
    {
        $office = DB::table('offices')
            ->whereRaw('LOWER(department_name) = ?', ['physical facilities'])
            ->first();

        return $office?->office_id;
    }
}
