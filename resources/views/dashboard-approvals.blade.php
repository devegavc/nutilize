@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header --}}
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0">Request Approvals</h1>
            <p class="text-muted">Manage and approve facility reservation requests</p>
        </div>
        <div class="col-md-6 text-end">
            <span class="badge bg-warning">{{ $pendingApprovals->total() }} Pending</span>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                Pending Approvals ({{ $pendingApprovals->total() }})
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
                Approval History
            </button>
        </li>
    </ul>

    {{-- Tab Content --}}
    <div class="tab-content">
        {{-- Pending Approvals Tab --}}
        <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
            @if($pendingApprovals->count() > 0)
                <div class="row">
                    @foreach($pendingApprovals as $approval)
                        <div class="col-lg-6 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-light border-bottom">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h5 class="card-title mb-0">
                                                {{ $approval->reservation->activity_name ?? 'Unnamed Activity' }}
                                            </h5>
                                            <small class="text-muted">
                                                Reservation ID: #{{ $approval->reservation->reservation_id }}
                                            </small>
                                        </div>
                                        <div class="col-auto">
                                            <span class="badge bg-warning">PENDING</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <p class="mb-2">
                                        <strong>Requested by:</strong>
                                        {{ $approval->reservation->user->full_name ?? $approval->reservation->user->username }}
                                    </p>
                                    <p class="mb-2">
                                        <strong>Email:</strong>
                                        {{ $approval->reservation->user->email }}
                                    </p>
                                    <p class="mb-3">
                                        <strong>Submitted:</strong>
                                        {{ $approval->reservation->created_at->format('M d, Y H:i A') }}
                                    </p>

                                    {{-- Request Details --}}
                                    @if($approval->reservation->details->count() > 0)
                                        <div class="bg-light p-3 rounded mb-3">
                                            <strong>Items/Rooms Requested:</strong>
                                            <ul class="mb-0 mt-2">
                                                @foreach($approval->reservation->details as $detail)
                                                    <li class="mb-1">
                                                        @if($detail->reservation_rooms_id)
                                                            Room
                                                        @else
                                                            Item
                                                        @endif
                                                        (Qty: {{ $detail->quantity }})
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>

                                <div class="card-footer bg-light">
                                    <div class="btn-group w-100" role="group">
                                        <button type="button" 
                                                class="btn btn-success flex-grow-1 approve-btn"
                                                data-approval-id="{{ $approval->approval_id }}"
                                                data-approval-action="approve">
                                            <i class="bi bi-check-circle"></i> Approve
                                        </button>
                                        <button type="button" 
                                                class="btn btn-danger flex-grow-1 approve-btn"
                                                data-approval-id="{{ $approval->approval_id }}"
                                                data-approval-action="reject">
                                            <i class="bi bi-x-circle"></i> Reject
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                <nav aria-label="Page navigation">
                    {{ $pendingApprovals->links() }}
                </nav>
            @else
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle"></i>
                    No pending approvals at the moment. All requests have been reviewed!
                </div>
            @endif
        </div>

        {{-- Approval History Tab --}}
        <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
            @if($approvedApprovals->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Activity Name</th>
                                <th>Requested by</th>
                                <th>Status</th>
                                <th>Approved Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($approvedApprovals as $approval)
                                <tr>
                                    <td>
                                        <strong>{{ $approval->reservation->activity_name ?? 'Unnamed' }}</strong>
                                        <br>
                                        <small class="text-muted">#{{ $approval->reservation->reservation_id }}</small>
                                    </td>
                                    <td>{{ $approval->reservation->user->full_name ?? $approval->reservation->user->username }}</td>
                                    <td>
                                        @if($approval->status === 'approved')
                                            <span class="badge bg-success">Approved</span>
                                        @else
                                            <span class="badge bg-danger">Rejected</span>
                                        @endif
                                    </td>
                                    <td>{{ $approval->approved_at->format('M d, Y H:i A') }}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary view-details-btn"
                                                data-reservation-id="{{ $approval->reservation->reservation_id }}">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <nav aria-label="Page navigation">
                    {{ $approvedApprovals->links() }}
                </nav>
            @else
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle"></i>
                    No approval history yet.
                </div>
            @endif
        </div>
    </div>
</div>

{{-- CSRF Token --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- Auth User Data --}}
<script>
    window.authUser = {
        user_id: {{ $authUser->user_id }},
        full_name: "{{ $authUser->full_name ?? $authUser->username }}",
        office_id: {{ $authUser->office_id }},
        role: "{{ $authUser->role }}",
    };
</script>

<style>
    .card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1) !important;
    }
    .btn-group > .btn {
        border-radius: 0;
    }
    .btn-group > .btn:first-child {
        border-top-left-radius: 0.25rem;
        border-bottom-left-radius: 0.25rem;
    }
    .btn-group > .btn:last-child {
        border-top-right-radius: 0.25rem;
        border-bottom-right-radius: 0.25rem;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle approval and rejection buttons
    const approveButtons = document.querySelectorAll('.approve-btn');
    approveButtons.forEach(button => {
        button.addEventListener('click', function() {
            const approvalId = this.dataset.approvalId;
            const action = this.dataset.approvalAction;
            const actionUrl = action === 'approve' 
                ? `/dashboard/approval/${approvalId}/approve`
                : `/dashboard/approval/${approvalId}/reject`;

            if (confirm(`Are you sure you want to ${action} this request?`)) {
                fetch(actionUrl, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                    },
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing your request.');
                });
            }
        });
    });

    // View details button
    const viewDetailsButtons = document.querySelectorAll('.view-details-btn');
    viewDetailsButtons.forEach(button => {
        button.addEventListener('click', function() {
            const reservationId = this.dataset.reservationId;
            alert('Viewing details for reservation #' + reservationId);
            // You can implement a modal or navigate to a details page
        });
    });
});
</script>
@endsection
