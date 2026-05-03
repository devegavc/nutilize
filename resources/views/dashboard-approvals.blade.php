@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header --}}
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0">{{ $isPfAdmin ? 'Final Approval' : 'Request Approvals' }}</h1>
            <p class="text-muted">{{ $isPfAdmin ? 'Review and finalize reservation requests from all other office approvals' : 'Manage and approve facility reservation requests' }}</p>
        </div>
        <div class="col-md-6 text-end">
            <button id="approval-reload-button" type="button" class="btn btn-outline-primary me-2">
                <i class="bi bi-arrow-clockwise"></i> Reload
            </button>
            <span
                class="badge bg-warning"
                id="approval-summary-badge"
                data-total-count="{{ $pendingApprovals->total() }}"
                data-summary-label="{{ $isPfAdmin ? 'Final Requests' : 'Pending' }}"
            >{{ $pendingApprovals->total() }} {{ $isPfAdmin ? 'Final Requests' : 'Pending' }}</span>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                {{ $isPfAdmin ? 'Final Requests' : 'Pending Approvals' }} ({{ $pendingApprovals->total() }})
            </button>
        </li>
        @if($isPfAdmin)
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="return-tab" data-bs-toggle="tab" data-bs-target="#return" type="button" role="tab" aria-controls="return" aria-selected="false">
                    Waiting Return ({{ $returnApprovals->total() }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button" role="tab" aria-controls="rejected" aria-selected="false">
                    Rejected ({{ $rejectedApprovals ? $rejectedApprovals->total() : 0 }})
                </button>
            </li>
        @endif
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
                                            <h5 class="card-title mb-0">{{ $approval->reservation->activity_name ?? 'Unnamed Activity' }}</h5>
                                            <small class="text-muted">Reservation ID: #{{ $approval->reservation->reservation_id }}</small>
                                        </div>
                                        <div class="col-auto">
                                            <span class="badge bg-warning">{{ $isPfAdmin ? 'FINAL REVIEW' : 'PENDING' }}</span>
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
                                    <p class="mb-2">
                                        <strong>Submitted:</strong>
                                        {{ $approval->reservation->created_at->format('M d, Y H:i A') }}
                                    </p>

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
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="{{ $isPfAdmin ? route('request.final.approve', ['reservationId' => $approval->reservation->reservation_id]) : route('approval.approve', ['approvalId' => $approval->approval_id]) }}" class="flex-grow-1">
                                            @csrf
                                            @method('PATCH')
                                            <button type="button"
                                                    class="btn btn-success w-100 confirm-action-btn"
                                                    data-confirm-title="Confirm Approval"
                                                    data-confirm-message="Are you sure you want to approve this reservation request? This action cannot be undone."
                                                    data-confirm-text="Approve">
                                                <i class="bi bi-check-circle"></i> {{ $isPfAdmin ? 'Final Approve' : 'Approve' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ $isPfAdmin ? route('request.final.reject', ['reservationId' => $approval->reservation->reservation_id]) : route('approval.reject', ['approvalId' => $approval->approval_id]) }}" class="flex-grow-1">
                                            @csrf
                                            @method('PATCH')
                                            <button type="button"
                                                    class="btn btn-danger w-100 confirm-action-btn"
                                                    data-confirm-title="Confirm Rejection"
                                                    data-confirm-message="Are you sure you want to reject this reservation request? This action cannot be undone."
                                                    data-confirm-text="Reject"
                                                    data-confirm-variant="danger">
                                                <i class="bi bi-x-circle"></i> {{ $isPfAdmin ? 'Final Reject' : 'Reject' }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

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

        @if($isPfAdmin)
            <div class="tab-pane fade" id="return" role="tabpanel" aria-labelledby="return-tab">
                @if($returnApprovals->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Activity Name</th>
                                    <th>Requested by</th>
                                    <th>Approved Date</th>
                                    <th>Return Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="return-history-body">
                                @foreach($returnApprovals as $approval)
                                    <tr>
                                        <td>
                                            <strong>{{ $approval->reservation->activity_name ?? 'Unnamed' }}</strong>
                                            <br>
                                            <small class="text-muted">#{{ $approval->reservation->reservation_id }}</small>
                                        </td>
                                        <td>{{ $approval->reservation->user->full_name ?? $approval->reservation->user->username }}</td>
                                        <td>{{ $approval->approved_at->format('M d, Y H:i A') }}</td>
                                        <td><span class="badge bg-primary">Waiting Return</span></td>
                                        <td>
                                                <div class="d-flex flex-column gap-2">
                                                <form method="POST" action="{{ route('request.final.return', ['reservationId' => $approval->reservation->reservation_id]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="btn btn-success rounded-pill fw-bold px-3 py-2 w-100 confirm-action-btn"
                                                            type="button"
                                                            data-confirm-title="Confirm Return"
                                                            data-confirm-message="Mark this request as returned in good condition? This action cannot be undone."
                                                            data-confirm-text="Return">
                                                        Returned
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('request.final.damaged', ['reservationId' => $approval->reservation->reservation_id]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="btn btn-danger rounded-pill fw-bold px-3 py-2 w-100 confirm-action-btn"
                                                            type="button"
                                                            data-confirm-title="Confirm Damage"
                                                            data-confirm-message="Mark this request as damaged? This action cannot be undone."
                                                            data-confirm-text="Damage"
                                                            data-confirm-variant="danger">
                                                        Damaged
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <nav aria-label="Page navigation">
                        {{ $returnApprovals->links() }}
                    </nav>
                @else
                    <div class="alert alert-info" role="alert">
                        <i class="bi bi-info-circle"></i>
                        No fully approved requests waiting for return yet.
                    </div>
                @endif
            </div>

            <div class="tab-pane fade" id="rejected" role="tabpanel" aria-labelledby="rejected-tab">
                @if($rejectedApprovals->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Activity Name</th>
                                    <th>Requested by</th>
                                    <th>Rejected By Office</th>
                                    <th>Rejection Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rejectedApprovals as $approval)
                                    <tr>
                                        <td>
                                            <strong>{{ $approval->reservation->activity_name ?? 'Unnamed' }}</strong>
                                            <br>
                                            <small class="text-muted">#{{ $approval->reservation->reservation_id }}</small>
                                        </td>
                                        <td>{{ $approval->reservation->user->full_name ?? $approval->reservation->user->username }}</td>
                                        <td>
                                            @if($approval->office)
                                                <strong>{{ $approval->office->office_name ?? 'Unknown Office' }}</strong>
                                            @else
                                                Unknown Office
                                            @endif
                                        </td>
                                        <td>{{ $approval->approved_at->format('M d, Y H:i A') }}</td>
                                        <td><span class="badge bg-danger">Rejected</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <nav aria-label="Page navigation">
                        {{ $rejectedApprovals->links() }}
                    </nav>
                @else
                    <div class="alert alert-info" role="alert">
                        <i class="bi bi-info-circle"></i>
                        No rejected requests yet.
                    </div>
                @endif
            </div>
        @endif

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

<div class="inventory-confirm-modal" id="inventory-confirm-modal" aria-hidden="true">
    <div class="inventory-confirm-overlay" data-close-inventory-confirm="true"></div>
    <article class="inventory-confirm-card" role="dialog" aria-modal="true" aria-labelledby="inventory-confirm-title">
      <header class="inventory-confirm-head">
        <h2 id="inventory-confirm-title">Confirm Action</h2>
      </header>
      <div class="inventory-confirm-body">
        <p id="inventory-confirm-message">Are you sure you want to perform this action? This action cannot be undone.</p>
      </div>
      <div class="inventory-confirm-actions">
        <button type="button" class="inventory-confirm-btn cancel" id="inventory-confirm-cancel">Cancel</button>
        <button type="button" class="inventory-confirm-btn confirm" id="inventory-confirm-submit">Confirm</button>
      </div>
    </article>
</div>

<script src="/js/dashboard.js"></script>
@endsection
