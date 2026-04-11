<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\ReservationApproval;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        
        if (!$user->isOfficeApprover()) {
            return redirect('/dashboard/home')->with('error', 'Unauthorized access.');
        }

        $pendingQuery = ReservationApproval::where('office_id', $user->office_id)
            ->whereNull('approved_at')
            ->with(['reservation.user', 'reservation.approvals', 'reservation.details']);

        if ($user->isPhysicalFacilitiesAdmin()) {
            $pendingQuery->whereDoesntHave('reservation.approvals', function ($query) use ($user) {
                $query->where('office_id', '!=', $user->office_id)
                    ->where(function ($subQuery) {
                        $subQuery->where('status', '!=', 'approved')
                            ->orWhereNull('approved_at');
                    });
            });
        }

        $pendingApprovals = $pendingQuery
            ->orderByDesc('created_at')
            ->paginate(10);

        $approvedApprovals = ReservationApproval::where('office_id', $user->office_id)
            ->whereNotNull('approved_at')
            ->with(['reservation.user'])
            ->orderByDesc('approved_at')
            ->paginate(10);

        return view('dashboard-approvals', [
            'pendingApprovals' => $pendingApprovals,
            'approvedApprovals' => $approvedApprovals,
            'authUser' => $user,
        ]);
    }

    public function approve($approvalId)
    {
        $user = Auth::user();
        
        if (!$user->isOfficeApprover()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $approval = ReservationApproval::findOrFail($approvalId);

        if ($approval->office_id !== $user->office_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($user->isPhysicalFacilitiesAdmin() && !$this->isReadyForFinalPhysicalFacilitiesApproval($approval->reservation, $user->office_id)) {
            return response()->json([
                'error' => 'This request is not yet ready for final Physical Facilities approval.',
            ], 422);
        }

        $approval->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // Update the overall reservation status if all office approvals are done
        $this->updateReservationStatus($approval->reservation_id);

        return response()->json([
            'success' => true,
            'message' => 'Request approved successfully.',
            'approval' => $approval,
        ]);
    }

    public function reject($approvalId)
    {
        $user = Auth::user();
        
        if (!$user->isOfficeApprover()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $approval = ReservationApproval::findOrFail($approvalId);

        if ($approval->office_id !== $user->office_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($user->isPhysicalFacilitiesAdmin() && !$this->isReadyForFinalPhysicalFacilitiesApproval($approval->reservation, $user->office_id)) {
            return response()->json([
                'error' => 'This request is not yet ready for final Physical Facilities decision.',
            ], 422);
        }

        $approval->update([
            'status' => 'rejected',
            'approved_at' => now(),
        ]);

        // Update the overall reservation status
        $this->updateReservationStatus($approval->reservation_id);

        return response()->json([
            'success' => true,
            'message' => 'Request rejected.',
            'approval' => $approval,
        ]);
    }

    private function updateReservationStatus($reservationId)
    {
        $reservation = Reservation::findOrFail($reservationId);
        $approvals = ReservationApproval::where('reservation_id', $reservationId)->get();
        $physicalFacilitiesOfficeId = $this->getPhysicalFacilitiesOfficeId();

        $anyRejected = $approvals->some(fn($a) => $a->status === 'rejected');

        if (is_null($physicalFacilitiesOfficeId)) {
            $allApproved = $approvals->every(fn($a) => $a->status === 'approved' && !is_null($a->approved_at));

            if ($anyRejected) {
                $reservation->update(['overall_status' => 'rejected']);
            } elseif ($allApproved) {
                $reservation->update(['overall_status' => 'approved']);
            } else {
                $reservation->update(['overall_status' => 'pending_office_approvals']);
            }

            return;
        }

        $pfApproval = $approvals->firstWhere('office_id', $physicalFacilitiesOfficeId);
        $allNonPfApproved = $approvals
            ->where('office_id', '!=', $physicalFacilitiesOfficeId)
            ->every(fn($a) => $a->status === 'approved' && !is_null($a->approved_at));

        if ($anyRejected) {
            $reservation->update(['overall_status' => 'rejected']);
        } elseif ($pfApproval && $pfApproval->status === 'approved' && $pfApproval->approved_at) {
            $reservation->update(['overall_status' => 'approved']);
        } elseif ($allNonPfApproved) {
            $reservation->update(['overall_status' => 'awaiting_physical_facilities']);
        } else {
            $reservation->update(['overall_status' => 'pending_office_approvals']);
        }
    }

    private function isReadyForFinalPhysicalFacilitiesApproval(Reservation $reservation, int $physicalFacilitiesOfficeId): bool
    {
        $reservation->loadMissing('approvals');

        $nonPfApprovals = $reservation->approvals->where('office_id', '!=', $physicalFacilitiesOfficeId);

        if ($nonPfApprovals->isEmpty()) {
            return true;
        }

        $anyRejected = $nonPfApprovals->contains(fn($approval) => $approval->status === 'rejected');
        $allApproved = $nonPfApprovals->every(
            fn($approval) => $approval->status === 'approved' && !is_null($approval->approved_at)
        );

        return !$anyRejected && $allApproved;
    }

    private function getPhysicalFacilitiesOfficeId(): ?int
    {
        $office = Office::whereRaw('LOWER(department_name) = ?', ['physical facilities'])->first();

        return $office?->office_id;
    }
}
