<?php

namespace App\Http\Controllers;

use App\Models\Reservation;

class OfficeRequestController extends Controller
{
    public function index()
    {
        $requests = Reservation::with('user')
            ->orderByDesc('created_at')
            ->paginate(10);

        $allStatuses = Reservation::query()
            ->pluck('overall_status')
            ->map(fn ($status) => strtolower((string) $status));

        $totalRequests = $allStatuses->count();
        $approvedRequests = $allStatuses->filter(fn ($status) => $status === 'approved')->count();
        $rejectedRequests = $allStatuses->filter(fn ($status) => $status === 'rejected')->count();
        $pendingRequests = max(0, $totalRequests - $approvedRequests - $rejectedRequests);

        return view('office-requests', [
            'requests' => $requests,
            'totalRequests' => $totalRequests,
            'pendingRequests' => $pendingRequests,
            'approvedRequests' => $approvedRequests,
            'rejectedRequests' => $rejectedRequests,
        ]);
    }
}
