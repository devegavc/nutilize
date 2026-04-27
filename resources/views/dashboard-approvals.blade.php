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
            <span class="badge bg-warning">{{ $pendingApprovals->total() }} {{ $isPfAdmin ? 'Final Requests' : 'Pending' }}</span>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                {{ $isPfAdmin ? 'Final Requests' : 'Pending Approvals' }} ({{ $pendingApprovals->total() }})
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
                Approval History
            </button>
        </li>
    </ul>


            <div class="custom-confirm-modal" id="custom-confirm-modal" aria-hidden="true">
                <div class="custom-confirm-overlay" data-close-custom-confirm="true"></div>
                <article class="custom-confirm-card" role="dialog" aria-modal="true" aria-labelledby="custom-confirm-title">
                    <header class="custom-confirm-head">
                        <h2 id="custom-confirm-title">Confirm Action</h2>
                    </header>
                    <div class="custom-confirm-body">
                        <p id="custom-confirm-message">Are you sure?</p>
                    </div>
                    <div class="custom-confirm-actions">
                        <button type="button" class="custom-confirm-btn cancel" id="custom-confirm-cancel">Cancel</button>
                        <button type="button" class="custom-confirm-btn approve" id="custom-confirm-submit">Continue</button>
                    </div>
                </article>
            </div>
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

                .custom-confirm-modal {
                    position: fixed;
                    inset: 0;
                    z-index: 1300;
                    display: none;
                }

                .custom-confirm-modal.is-open {
                    display: grid;
                    place-items: center;
                    padding: 18px;
                }

                .custom-confirm-overlay {
                    position: absolute;
                    inset: 0;
                    background: rgba(18, 22, 34, 0.45);
                    backdrop-filter: blur(1px);
                }

                .custom-confirm-card {
                    position: relative;
                    width: min(500px, 90vw);
                    background: #ffffff;
                    border: 1px solid #d7ddea;
                    border-radius: 12px;
                    box-shadow: 0 14px 30px rgba(20, 26, 48, 0.22);
                    overflow: hidden;
                }

                .custom-confirm-card::before {
                    content: "";
                    position: absolute;
                    inset: 0 auto auto 0;
                    width: 100%;
                    height: 4px;
                    background: linear-gradient(90deg, #2b8a3e 0%, #46a35d 100%);
                }

                .custom-confirm-modal[data-mode="reject"] .custom-confirm-card::before {
                    background: linear-gradient(90deg, #d44545 0%, #ea6a6a 100%);
                }

                .custom-confirm-head {
                    padding: 12px 16px 10px;
                    border-bottom: 1px solid #e6eaf2;
                    background: #fbfcff;
                }

                .custom-confirm-head h2 {
                    margin: 0;
                    font-size: 1.55rem;
                    font-weight: 700;
                    color: #257a35;
                }

                .custom-confirm-modal[data-mode="reject"] .custom-confirm-head h2 {
                    color: #c53030;
                }

                .custom-confirm-body {
                    padding: 14px 16px;
                    border-bottom: 1px solid #e6eaf2;
                    color: #2f3545;
                    font-size: 1.02rem;
                    line-height: 1.45;
                }

                .custom-confirm-actions {
                    display: flex;
                    justify-content: flex-end;
                    gap: 10px;
                    padding: 12px 16px;
                    background: #fbfcff;
                }

                .custom-confirm-btn {
                    min-width: 104px;
                    height: 36px;
                    border-radius: 8px;
                    border: 1px solid #c7cfde;
                    background: #f1f4fa;
                    color: #3b4458;
                    font-size: 0.92rem;
                    font-weight: 600;
                    cursor: pointer;
                }

                .custom-confirm-btn.approve {
                    border-color: #158a31;
                    background: #149031;
                    color: #fff;
                }

                .custom-confirm-btn.reject {
                    border-color: #cf3b3b;
                    background: #cf3b3b;
                    color: #fff;
                }

                .custom-confirm-btn.cancel {
                    background: #eef1f7;
                }
                                            <span class="badge bg-warning">{{ $isPfAdmin ? 'FINAL REVIEW' : 'PENDING' }}</span>
                                        </div>
                                    </div>
                                </div>
                const confirmModal = document.getElementById('custom-confirm-modal');
                const confirmTitle = document.getElementById('custom-confirm-title');
                const confirmMessage = document.getElementById('custom-confirm-message');
                const confirmCancel = document.getElementById('custom-confirm-cancel');
                const confirmSubmit = document.getElementById('custom-confirm-submit');

                let resolveConfirm = null;

                const closeConfirm = () => {
                    if (!(confirmModal instanceof HTMLElement)) {
                        return;
                    }

                    confirmModal.classList.remove('is-open');
                    confirmModal.setAttribute('aria-hidden', 'true');
                    confirmModal.dataset.mode = '';
                    resolveConfirm = null;
                };

                const openConfirm = ({ title, message, confirmText, mode }) => {
                    if (!(confirmModal instanceof HTMLElement)
                        || !(confirmTitle instanceof HTMLElement)
                        || !(confirmMessage instanceof HTMLElement)
                        || !(confirmCancel instanceof HTMLButtonElement)
                        || !(confirmSubmit instanceof HTMLButtonElement)) {
                        return Promise.resolve(true);
                    }

                    confirmTitle.textContent = title;
                    confirmMessage.textContent = message;
                    confirmSubmit.textContent = confirmText;
                    confirmModal.dataset.mode = mode;
                    confirmSubmit.classList.toggle('approve', mode === 'approve');
                    confirmSubmit.classList.toggle('reject', mode === 'reject');
                    confirmModal.classList.add('is-open');
                    confirmModal.setAttribute('aria-hidden', 'false');

                    return new Promise((resolve) => {
                        resolveConfirm = resolve;
                    });
                };

                const finishConfirm = (value) => {
                    if (typeof resolveConfirm === 'function') {
                        resolveConfirm(value);
                    }

                    closeConfirm();
                };

                if (confirmModal instanceof HTMLElement) {
                    confirmModal.addEventListener('click', (event) => {
                        const target = event.target;

                        if (target instanceof HTMLElement && target.dataset.closeCustomConfirm === 'true') {
                            finishConfirm(false);
                        }
                    });
                }

                if (confirmCancel instanceof HTMLButtonElement) {
                    confirmCancel.addEventListener('click', () => finishConfirm(false));
                }

                if (confirmSubmit instanceof HTMLButtonElement) {
                    confirmSubmit.addEventListener('click', () => finishConfirm(true));
                }

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && confirmModal instanceof HTMLElement && confirmModal.classList.contains('is-open')) {
                        finishConfirm(false);
                    }
                });


                                <div class="card-body">
                                    <p class="mb-2">
                                        <strong>Requested by:</strong>
                                        {{ $approval->reservation->user->full_name ?? $approval->reservation->user->username }}
                                    </p>
                                    <p class="mb-2">
                                        <strong>Email:</strong>
                                        {{ $approval->reservation->user->email }}
                                    </p>
                        const confirmed = await openConfirm({
                            title: action === 'approve' ? 'Confirm Approval' : 'Confirm Rejection',
                            message: `Are you sure you want to ${action} this request? This action cannot be undone.`,
                            confirmText: action === 'approve' ? 'Approve' : 'Reject',
                            mode: action,
                        });

                        if (confirmed) {
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
                                            <i class="bi bi-check-circle"></i> {{ $isPfAdmin ? 'Final Approve' : 'Approve' }}
                                        </button>
                                        <button type="button" 
                                                class="btn btn-danger flex-grow-1 approve-btn"
                                                data-approval-id="{{ $approval->approval_id }}"
                                                data-approval-action="reject">
                                            <i class="bi bi-x-circle"></i> {{ $isPfAdmin ? 'Final Reject' : 'Reject' }}
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
    <div class="custom-confirm-modal" id="custom-confirm-modal" aria-hidden="true">
        <div class="custom-confirm-overlay" data-close-custom-confirm="true"></div>
        <article class="custom-confirm-card" role="dialog" aria-modal="true" aria-labelledby="custom-confirm-title">
            <header class="custom-confirm-head">
                <h2 id="custom-confirm-title">Confirm Action</h2>
            </header>
            <div class="custom-confirm-body">
                <p id="custom-confirm-message">Are you sure?</p>
            </div>
            <div class="custom-confirm-actions">
                <button type="button" class="custom-confirm-btn cancel" id="custom-confirm-cancel">Cancel</button>
                <button type="button" class="custom-confirm-btn approve" id="custom-confirm-submit">Continue</button>
            </div>
        </article>
    </div>

    const confirmModal = document.getElementById('custom-confirm-modal');
    const confirmTitle = document.getElementById('custom-confirm-title');
    const confirmMessage = document.getElementById('custom-confirm-message');
    const confirmCancel = document.getElementById('custom-confirm-cancel');
    const confirmSubmit = document.getElementById('custom-confirm-submit');

    let resolveConfirm = null;

    const closeConfirm = () => {
        if (!(confirmModal instanceof HTMLElement)) {
            return;
        }

        confirmModal.classList.remove('is-open');
        confirmModal.setAttribute('aria-hidden', 'true');
        confirmModal.dataset.mode = '';
        resolveConfirm = null;
    };

    const openConfirm = ({ title, message, confirmText, mode }) => {
        if (!(confirmModal instanceof HTMLElement)
            || !(confirmTitle instanceof HTMLElement)
            || !(confirmMessage instanceof HTMLElement)
            || !(confirmCancel instanceof HTMLButtonElement)
            || !(confirmSubmit instanceof HTMLButtonElement)) {
            return Promise.resolve(true);
        }

        confirmTitle.textContent = title;
        confirmMessage.textContent = message;
        confirmSubmit.textContent = confirmText;
        confirmModal.dataset.mode = mode;
        confirmSubmit.classList.toggle('approve', mode === 'approve');
        confirmSubmit.classList.toggle('reject', mode === 'reject');
        confirmModal.classList.add('is-open');
        confirmModal.setAttribute('aria-hidden', 'false');

        return new Promise((resolve) => {
            resolveConfirm = resolve;
        });
    };

    const finishConfirm = (value) => {
        if (typeof resolveConfirm === 'function') {
            resolveConfirm(value);
        }

        closeConfirm();
    };

    if (confirmModal instanceof HTMLElement) {
        confirmModal.addEventListener('click', (event) => {
            const target = event.target;

            if (target instanceof HTMLElement && target.dataset.closeCustomConfirm === 'true') {
                finishConfirm(false);
            }
        });
    }

    if (confirmCancel instanceof HTMLButtonElement) {
        confirmCancel.addEventListener('click', () => finishConfirm(false));
    }

    if (confirmSubmit instanceof HTMLButtonElement) {
        confirmSubmit.addEventListener('click', () => finishConfirm(true));
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && confirmModal instanceof HTMLElement && confirmModal.classList.contains('is-open')) {
            finishConfirm(false);
        }
    });

    // Handle approval and rejection buttons
    const approveButtons = document.querySelectorAll('.approve-btn');
    approveButtons.forEach(button => {
        button.addEventListener('click', async function() {
            const approvalId = this.dataset.approvalId;
            const action = this.dataset.approvalAction;
            const actionUrl = action === 'approve' 
                ? `/dashboard/approval/${approvalId}/approve`
                : `/dashboard/approval/${approvalId}/reject`;

            const confirmed = await openConfirm({
                title: action === 'approve' ? 'Confirm Approval' : 'Confirm Rejection',
                message: `Are you sure you want to ${action} this request? This action cannot be undone.`,
                confirmText: action === 'approve' ? 'Approve' : 'Reject',
                mode: action,
            });

            if (confirmed) {
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
                        showAppNotice(data.message);
                        location.reload();
                    } else {
                        showAppNotice('Error: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAppNotice('An error occurred while processing your request.');
                });
            }
        });
    });

    // View details button
    const viewDetailsButtons = document.querySelectorAll('.view-details-btn');
    viewDetailsButtons.forEach(button => {
        button.addEventListener('click', function() {
            const reservationId = this.dataset.reservationId;
            showAppNotice('Viewing details for reservation #' + reservationId);
            // You can implement a modal or navigate to a details page
        });
    });
});
</style>

    .custom-confirm-modal {
        position: fixed;
        inset: 0;
        z-index: 1300;
        display: none;
    }

    .custom-confirm-modal.is-open {
        display: grid;
        place-items: center;
        padding: 18px;
    }

    .custom-confirm-overlay {
        position: absolute;
        inset: 0;
        background: rgba(18, 22, 34, 0.45);
        backdrop-filter: blur(1px);
    }

    .custom-confirm-card {
        position: relative;
        width: min(500px, 90vw);
        background: #ffffff;
        border: 1px solid #d7ddea;
        border-radius: 12px;
        box-shadow: 0 14px 30px rgba(20, 26, 48, 0.22);
        overflow: hidden;
    }

    .custom-confirm-card::before {
        content: "";
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #2b8a3e 0%, #46a35d 100%);
    }

    .custom-confirm-modal[data-mode="reject"] .custom-confirm-card::before {
        background: linear-gradient(90deg, #d44545 0%, #ea6a6a 100%);
    }

    .custom-confirm-head {
        padding: 12px 16px 10px;
        border-bottom: 1px solid #e6eaf2;
        background: #fbfcff;
    }

    .custom-confirm-head h2 {
        margin: 0;
        font-size: 1.55rem;
        font-weight: 700;
        color: #257a35;
    }

    .custom-confirm-modal[data-mode="reject"] .custom-confirm-head h2 {
        color: #c53030;
    }

    .custom-confirm-body {
        padding: 14px 16px;
        border-bottom: 1px solid #e6eaf2;
        color: #2f3545;
        font-size: 1.02rem;
        line-height: 1.45;
    }

    .custom-confirm-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 12px 16px;
        background: #fbfcff;
    }

    .custom-confirm-btn {
        min-width: 104px;
        height: 36px;
        border-radius: 8px;
        border: 1px solid #c7cfde;
        background: #f1f4fa;
        color: #3b4458;
        font-size: 0.92rem;
        font-weight: 600;
        cursor: pointer;
    }

    .custom-confirm-btn.approve {
        border-color: #158a31;
        background: #149031;
        color: #fff;
    }

    .custom-confirm-btn.reject {
        border-color: #cf3b3b;
        background: #cf3b3b;
        color: #fff;
    }

    .custom-confirm-btn.cancel {
        background: #eef1f7;
    }
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
                        showAppNotice(data.message);
                        location.reload();
                    } else {
                        showAppNotice('Error: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAppNotice('An error occurred while processing your request.');
                });
            }
        });
    });

    // View details button
    const viewDetailsButtons = document.querySelectorAll('.view-details-btn');
    viewDetailsButtons.forEach(button => {
        button.addEventListener('click', function() {
            const reservationId = this.dataset.reservationId;
            showAppNotice('Viewing details for reservation #' + reservationId);
            // You can implement a modal or navigate to a details page
        });
    });
});
</script>
@endsection
