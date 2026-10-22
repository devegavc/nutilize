<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\FacultyApprovalService;
use App\Services\ReservationApprovalWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FacultyApprovalController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user || !$user->isFaculty()) {
            return redirect('/home')->with('error', 'Unauthorized access.');
        }

        return view('faculty-requests', [
            'requests' => FacultyApprovalService::openRequestsForFaculty($user),
            'authUser' => $user,
        ]);
    }

    public function approve(Request $request, int $reservationId)
    {
        $user = $request->user();
        if (!$user || !$user->isFaculty() || !FacultyApprovalService::facultyMatchesReservation($user, $reservationId)) {
            return redirect()->route('faculty.requests')->with('error', 'You cannot approve this request.');
        }

        if (!FacultyApprovalService::ready()) {
            return redirect()->route('faculty.requests')->with('error', 'Faculty approval is not available yet.');
        }

        Reservation::query()
            ->where('reservation_id', $reservationId)
            ->whereNull('faculty_approved_at')
            ->update([
                'faculty_approved_by_user_id' => (int) $user->user_id,
                'faculty_approved_at' => now(),
                'faculty_rejected_at' => null,
                'faculty_rejection_reason' => null,
                'updated_at' => now(),
            ]);

        DB::table('reservation_approvals')
            ->where('reservation_id', $reservationId)
            ->whereRaw("LOWER(COALESCE(status, '')) = 'awaiting_faculty'")
            ->update([
                'status' => 'pending',
                'approved_at' => null,
                'updated_at' => now(),
            ]);

        FacultyApprovalService::forget();

        return redirect()->route('faculty.requests')->with('success', 'Request approved. It can now move to the program chair or General Education.');
    }

    public function reject(Request $request, int $reservationId)
    {
        $user = $request->user();
        if (!$user || !$user->isFaculty() || !FacultyApprovalService::facultyMatchesReservation($user, $reservationId)) {
            return redirect()->route('faculty.requests')->with('error', 'You cannot reject this request.');
        }

        $reason = trim((string) $request->input('rejection_reason', ''));
        if ($reason === '') {
            return redirect()->route('faculty.requests')->with('error', 'A rejection reason is required.');
        }

        Reservation::query()
            ->where('reservation_id', $reservationId)
            ->whereNull('faculty_approved_at')
            ->update([
                'faculty_approved_by_user_id' => (int) $user->user_id,
                'faculty_rejected_at' => now(),
                'faculty_rejection_reason' => mb_substr($reason, 0, 500),
                'overall_status' => 'rejected',
                'updated_at' => now(),
            ]);

        ReservationApprovalWorkflowService::closePendingApprovals($reservationId, 'rejected');
        FacultyApprovalService::forget();

        return redirect()->route('faculty.requests')->with('success', 'Request rejected. It will not continue to the next office.');
    }
}
