<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Office Requests</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/office.css" />
</head>
<body>
  <script>
    window.authUser = {
      id: {{ auth()->user()->user_id ?? 'null' }},
      username: '{{ auth()->user()->username ?? 'User' }}',
      email: '{{ auth()->user()->email ?? '' }}',
      full_name: '{{ auth()->user()->full_name ?? auth()->user()->username ?? 'User' }}',
      role: '{{ auth()->user()->role ?? 'user' }}',
      office_name: '{{ auth()->user()?->office?->department_name ?? 'Office' }}'
    };
    window.dashboardNavComponent = '/components/navbar-office.html';
  </script>

  <header class="top-header">
    <div class="top-header-inner toolbar-card">
      <img src="/img/nutilize_logo.png" alt="NU-TILIZE" class="toolbar-logo" />

      <div class="search-wrap">
        <i class="bi bi-search"></i>
        <input id="dashboard-search" type="text" placeholder="Search requests" />
      </div>

      <button class="toolbar-icon" type="button" aria-label="Messages">
        <i class="bi bi-chat-fill"></i>
      </button>
      <button class="toolbar-icon" type="button" aria-label="Notifications">
        <i class="bi bi-bell-fill"></i>
      </button>
      <button class="profile-btn" type="button" aria-label="Profile">
        <i class="bi bi-person-circle"></i>
      </button>
    </div>
  </header>

  <main class="dashboard-shell">
    <section class="workspace-grid">
      <div id="navbar-container"></div>

      <section class="content-card office-requests-card">
        <h1 class="section-title">OFFICE REQUESTS DASHBOARD</h1>
        <p class="office-subtitle">Actionable queue for your office based on the approval sequence.</p>

        <section class="office-request-summary-grid" aria-label="Request summaries">
          <article class="office-request-summary-tile">
            <span class="office-request-summary-icon"><i class="bi bi-inboxes-fill"></i></span>
            <div>
              <p class="office-request-summary-value">{{ $totalRequests }}</p>
              <p class="office-request-summary-label">Actionable Requests</p>
            </div>
          </article>
          <article class="office-request-summary-tile">
            <span class="office-request-summary-icon"><i class="bi bi-hourglass-split"></i></span>
            <div>
              <p class="office-request-summary-value">{{ $pendingRequests }}</p>
              <p class="office-request-summary-label">Pending in Queue</p>
            </div>
          </article>
          <article class="office-request-summary-tile">
            <span class="office-request-summary-icon"><i class="bi bi-check-circle"></i></span>
            <div>
              <p class="office-request-summary-value">{{ $approvedRequests }}</p>
              <p class="office-request-summary-label">Approved</p>
            </div>
          </article>
          <article class="office-request-summary-tile">
            <span class="office-request-summary-icon"><i class="bi bi-x-circle"></i></span>
            <div>
              <p class="office-request-summary-value">{{ $rejectedRequests }}</p>
              <p class="office-request-summary-label">Rejected</p>
            </div>
          </article>
        </section>

        <section class="office-request-history-card" aria-label="Request queue table">
          <header class="office-request-history-head">
            <h2>Actionable Request Queue</h2>
          </header>
          <div class="table-wrap office-request-history-wrap">
            <table class="office-request-history-table">
              <thead>
                <tr>
                  <th>Reservation ID</th>
                  <th>Requested By</th>
                  <th>Activity</th>
                  <th>Submitted</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="office-request-history-body">
                @forelse($requests as $request)
                  @php
                    $status = strtolower((string) ($request->status ?? 'pending'));
                    $badgeClass = $status === 'approved' ? 'solved' : ($status === 'rejected' ? 'rejected' : 'pending');
                    $badgeText = $status === 'approved' ? 'Approved' : ($status === 'rejected' ? 'Rejected' : 'Pending');
                    $reservation = $request->reservation;
                  @endphp
                  <tr>
                    <td>#{{ $request->reservation_id }}</td>
                    <td>{{ $reservation?->user?->full_name ?? $reservation?->user?->username ?? 'Unknown' }}</td>
                    <td>{{ $reservation?->activity_name ?? 'N/A' }}</td>
                    <td>{{ optional($reservation?->created_at)->format('M d, Y h:i A') }}</td>
                    <td><span class="badge {{ $badgeClass }}">{{ $badgeText }}</span></td>
                    <td>
                      @if(is_null($request->approved_at) && $status === 'pending')
                        <div style="display:flex; gap:8px; justify-content:center;">
                          <button
                            type="button"
                            class="office-queue-action-btn office-queue-approve"
                            data-approval-id="{{ $request->approval_id }}"
                            data-action="approve"
                          >Approve</button>
                          <button
                            type="button"
                            class="office-queue-action-btn office-queue-reject"
                            data-approval-id="{{ $request->approval_id }}"
                            data-action="reject"
                          >Reject</button>
                        </div>
                      @else
                        <span style="color:#6a728f;">-</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6">No actionable requests found for your office.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          <div class="office-request-pagination">
            {{ $requests->links() }}
          </div>
        </section>
      </section>
    </section>
  </main>

  <div class="office-custom-confirm-modal" id="office-custom-confirm-modal" aria-hidden="true">
    <div class="office-custom-confirm-overlay" data-close-office-custom-confirm="true"></div>
    <article class="office-custom-confirm-card" role="dialog" aria-modal="true" aria-labelledby="office-custom-confirm-title">
      <header class="office-custom-confirm-head">
        <h2 id="office-custom-confirm-title">Confirm Approval</h2>
      </header>
      <div class="office-custom-confirm-body">
        <p id="office-custom-confirm-message">Are you sure you want to approve this request? This action cannot be undone.</p>
      </div>
      <div class="office-custom-confirm-actions">
        <button type="button" class="office-custom-confirm-btn cancel" id="office-custom-confirm-cancel">Cancel</button>
        <button type="button" class="office-custom-confirm-btn approve" id="office-custom-confirm-submit">Approve</button>
      </div>
    </article>
  </div>

  <script>
    window.officeApprovalRoutes = {
      approve: '{{ route('approval.approve', ['approvalId' => '__APPROVAL_ID__']) }}',
      reject: '{{ route('approval.reject', ['approvalId' => '__APPROVAL_ID__']) }}'
    };
  </script>
  <script src="/js/dashboard.js"></script>
  <script>
    (function () {
      const buttons = document.querySelectorAll('.office-queue-action-btn');
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const confirmModal = document.getElementById('office-custom-confirm-modal');
      const confirmTitle = document.getElementById('office-custom-confirm-title');
      const confirmMessage = document.getElementById('office-custom-confirm-message');
      const confirmCancel = document.getElementById('office-custom-confirm-cancel');
      const confirmSubmit = document.getElementById('office-custom-confirm-submit');

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

          if (target instanceof HTMLElement && target.dataset.closeOfficeCustomConfirm === 'true') {
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

      const resolveUrl = (action, approvalId) => {
        const template = window.officeApprovalRoutes?.[action] || '';
        return template.replace('__APPROVAL_ID__', String(approvalId));
      };

      const setButtonsDisabled = (state) => {
        buttons.forEach((button) => {
          button.disabled = state;
        });
      };

      buttons.forEach((button) => {
        button.addEventListener('click', async () => {
          const approvalId = button.getAttribute('data-approval-id');
          const action = button.getAttribute('data-action');

          if (!approvalId || !action) {
            return;
          }

          const confirmed = await openConfirm({
            title: action === 'approve' ? 'Confirm Approval' : 'Confirm Rejection',
            message: `Are you sure you want to ${action} this request? This action cannot be undone.`,
            confirmText: action === 'approve' ? 'Approve' : 'Reject',
            mode: action,
          });

          if (!confirmed) {
            return;
          }

          setButtonsDisabled(true);

          try {
            const response = await fetch(resolveUrl(action, approvalId), {
              method: 'PATCH',
              headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
              },
              body: JSON.stringify({}),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
              showAppNotice(payload.error || 'Action failed. Please try again.');
              setButtonsDisabled(false);
              return;
            }

            window.location.reload();
          } catch (_error) {
            showAppNotice('Request failed. Please check your connection and try again.');
            setButtonsDisabled(false);
          }
        });
      });
    })();
  </script>

  <style>
    .office-queue-action-btn {
      border: 1px solid transparent;
      border-radius: 8px;
      padding: 5px 10px;
      font-size: 0.85rem;
      font-weight: 700;
      cursor: pointer;
      transition: filter 0.15s ease, transform 0.15s ease;
    }

    .office-queue-action-btn:hover {
      filter: brightness(0.97);
      transform: translateY(-1px);
    }

    .office-queue-action-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .office-queue-approve {
      border-color: #0b8a0b;
      background: #0b8a0b;
      color: #fff;
    }

    .office-queue-reject {
      border-color: #d01d1d;
      background: #fff;
      color: #d01d1d;
    }

    .office-custom-confirm-modal {
      position: fixed;
      inset: 0;
      z-index: 1300;
      display: none;
    }

    .office-custom-confirm-modal.is-open {
      display: grid;
      place-items: center;
      padding: 18px;
    }

    .office-custom-confirm-overlay {
      position: absolute;
      inset: 0;
      background: rgba(18, 22, 34, 0.45);
      backdrop-filter: blur(1px);
    }

    .office-custom-confirm-card {
      position: relative;
      width: min(500px, 90vw);
      background: #ffffff;
      border: 1px solid #d7ddea;
      border-radius: 12px;
      box-shadow: 0 14px 30px rgba(20, 26, 48, 0.22);
      overflow: hidden;
    }

    .office-custom-confirm-card::before {
      content: "";
      position: absolute;
      inset: 0 auto auto 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, #2b8a3e 0%, #46a35d 100%);
    }

    .office-custom-confirm-modal[data-mode="reject"] .office-custom-confirm-card::before {
      background: linear-gradient(90deg, #d44545 0%, #ea6a6a 100%);
    }

    .office-custom-confirm-head {
      padding: 12px 16px 10px;
      border-bottom: 1px solid #e6eaf2;
      background: #fbfcff;
    }

    .office-custom-confirm-head h2 {
      margin: 0;
      font-size: 1.55rem;
      font-weight: 700;
      color: #257a35;
    }

    .office-custom-confirm-modal[data-mode="reject"] .office-custom-confirm-head h2 {
      color: #c53030;
    }

    .office-custom-confirm-body {
      padding: 14px 16px;
      border-bottom: 1px solid #e6eaf2;
      color: #2f3545;
      font-size: 1.02rem;
      line-height: 1.45;
    }

    .office-custom-confirm-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      padding: 12px 16px;
      background: #fbfcff;
    }

    .office-custom-confirm-btn {
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

    .office-custom-confirm-btn.approve {
      border-color: #158a31;
      background: #149031;
      color: #fff;
    }

    .office-custom-confirm-btn.reject {
      border-color: #cf3b3b;
      background: #cf3b3b;
      color: #fff;
    }

    .office-custom-confirm-btn.cancel {
      background: #eef1f7;
    }
  </style>
</body>
</html>
