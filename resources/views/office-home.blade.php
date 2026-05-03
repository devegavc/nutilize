<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Office Home</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/dashboard.css" />
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
        <h1 class="section-title">OFFICE APPROVAL DASHBOARD</h1>
        <p class="office-subtitle">Pending approvals for your office only, based on sequence.</p>

        <section class="office-request-summary-grid" aria-label="Request summaries">
          <article class="office-request-summary-tile">
            <span class="office-request-summary-icon"><i class="bi bi-inboxes-fill"></i></span>
            <div>
              <p class="office-request-summary-value" id="office-summary-actionable">{{ $totalRequests }}</p>
              <p class="office-request-summary-label">Actionable Requests</p>
            </div>
          </article>
          <article class="office-request-summary-tile">
            <span class="office-request-summary-icon"><i class="bi bi-hourglass-split"></i></span>
            <div>
              <p class="office-request-summary-value" id="office-summary-pending">{{ $pendingRequests }}</p>
              <p class="office-request-summary-label">Pending in Queue</p>
            </div>
          </article>
          <article class="office-request-summary-tile">
            <span class="office-request-summary-icon"><i class="bi bi-check-circle"></i></span>
            <div>
              <p class="office-request-summary-value" id="office-summary-approved">{{ $approvedRequests }}</p>
              <p class="office-request-summary-label">Approved</p>
            </div>
          </article>
          <article class="office-request-summary-tile">
            <span class="office-request-summary-icon"><i class="bi bi-x-circle"></i></span>
            <div>
              <p class="office-request-summary-value" id="office-summary-rejected">{{ $rejectedRequests }}</p>
              <p class="office-request-summary-label">Rejected</p>
            </div>
          </article>
        </section>

        <section class="office-request-history-card" aria-label="Request queue table">
          <header class="office-request-history-head">
            <div class="office-request-history-head-inner">
              <div>
                <h2>Pending Request Approval Queue</h2>
                
              </div>
              <button id="office-queue-reload" type="button" class="office-queue-reload-btn">
                <span class="office-queue-reload-icon" aria-hidden="true"><i class="bi bi-arrow-clockwise"></i></span>
                <span class="office-queue-reload-text">Reload</span>
              </button>
            </div>
          </header>
          <div class="table-wrap office-request-history-wrap">
            <table class="office-request-history-table">
              <thead>
                <tr>
                  <th>Reservation ID</th>
                  <th>Requested By</th>
                  <th>Activity</th>
                  <th>Event Date</th>
                  <th>Submitted</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="office-request-history-body">
                @include('partials.office-request-rows', ['requests' => $requests])
              </tbody>
            </table>
          </div>
          <div class="office-request-pagination" id="office-request-pagination">
            {{ $requests->links() }}
          </div>
        </section>
      </section>
    </section>
  </main>

  <div class="office-action-confirm-modal" id="office-action-confirm-modal" aria-hidden="true">
    <div class="office-action-confirm-overlay" data-close-office-action-confirm="true"></div>
    <article class="office-action-confirm-card" role="dialog" aria-modal="true" aria-labelledby="office-action-confirm-title">
      <header class="office-action-confirm-head">
        <h2 id="office-action-confirm-title">Confirm Approval</h2>
      </header>
      <div class="office-action-confirm-body">
        <p id="office-action-confirm-message">Are you sure you want to approve this reservation request? This action cannot be undone.</p>
      </div>
      <div class="office-action-confirm-actions">
        <button type="button" class="office-modal-btn cancel" id="office-action-confirm-cancel">Cancel</button>
        <button type="button" class="office-modal-btn approve" id="office-action-confirm-submit">Approve</button>
      </div>
    </article>
  </div>

  <script>
    window.officeApprovalRoutes = {
      approve: '{{ route('approval.approve', ['approvalId' => '__APPROVAL_ID__']) }}',
      reject: '{{ route('approval.reject', ['approvalId' => '__APPROVAL_ID__']) }}'
    };
    window.officeQueueSnapshotUrl = '{{ route('office.requests.snapshot') }}';
  </script>
  <script src="/js/dashboard.js"></script>
  <script>
    (function () {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const queueBody = document.getElementById('office-request-history-body');
      const paginationWrap = document.getElementById('office-request-pagination');
      const actionConfirmModal = document.getElementById('office-action-confirm-modal');
      const actionConfirmTitle = document.getElementById('office-action-confirm-title');
      const actionConfirmMessage = document.getElementById('office-action-confirm-message');
      const actionConfirmCancel = document.getElementById('office-action-confirm-cancel');
      const actionConfirmSubmit = document.getElementById('office-action-confirm-submit');
      const actionConfirmCard = actionConfirmModal instanceof HTMLElement
        ? actionConfirmModal.querySelector('.office-action-confirm-card')
        : null;

      if (!(queueBody instanceof HTMLElement)) {
        return;
      }

      const summaryIds = [
        'office-summary-actionable',
        'office-summary-pending',
        'office-summary-approved',
        'office-summary-rejected',
      ];

      let isRefreshing = false;
      let isActing = false;
      let refreshVersion = 0;
      const refreshIntervalMs = 30000;

      const resolveUrl = (action, approvalId) => {
        const template = window.officeApprovalRoutes?.[action] || '';
        return template.replace('__APPROVAL_ID__', String(approvalId));
      };

      const closeActionConfirmModal = () => {
        if (!(actionConfirmModal instanceof HTMLElement)) {
          return;
        }

        actionConfirmModal.classList.remove('is-open');
        actionConfirmModal.setAttribute('aria-hidden', 'true');
      };

      const openActionConfirmModal = (action) => new Promise((resolve) => {
        if (!(actionConfirmModal instanceof HTMLElement)
          || !(actionConfirmTitle instanceof HTMLElement)
          || !(actionConfirmMessage instanceof HTMLElement)
          || !(actionConfirmCancel instanceof HTMLButtonElement)
          || !(actionConfirmSubmit instanceof HTMLButtonElement)) {
          resolve(true);
          return;
        }

        const isApprove = action === 'approve';
        actionConfirmTitle.textContent = isApprove ? 'Confirm Approval' : 'Confirm Rejection';
        actionConfirmMessage.textContent = isApprove
          ? 'Are you sure you want to approve this reservation request? This action cannot be undone.'
          : 'Are you sure you want to reject this reservation request? This action cannot be undone.';
        actionConfirmSubmit.textContent = isApprove ? 'Approve' : 'Reject';
        actionConfirmSubmit.classList.toggle('approve', isApprove);
        actionConfirmSubmit.classList.toggle('reject', !isApprove);

        if (actionConfirmCard instanceof HTMLElement) {
          actionConfirmCard.classList.toggle('is-reject', !isApprove);
        }

        actionConfirmModal.classList.add('is-open');
        actionConfirmModal.setAttribute('aria-hidden', 'false');

        const handleCancel = () => {
          teardown();
          closeActionConfirmModal();
          resolve(false);
        };

        const handleSubmit = () => {
          teardown();
          closeActionConfirmModal();
          resolve(true);
        };

        const handleBackdrop = (event) => {
          const target = event.target;
          if (target instanceof HTMLElement && target.dataset.closeOfficeActionConfirm === 'true') {
            handleCancel();
          }
        };

        const handleKeydown = (event) => {
          if (event.key === 'Escape') {
            handleCancel();
          }
        };

        const teardown = () => {
          actionConfirmCancel.removeEventListener('click', handleCancel);
          actionConfirmSubmit.removeEventListener('click', handleSubmit);
          actionConfirmModal.removeEventListener('click', handleBackdrop);
          document.removeEventListener('keydown', handleKeydown);
        };

        actionConfirmCancel.addEventListener('click', handleCancel);
        actionConfirmSubmit.addEventListener('click', handleSubmit);
        actionConfirmModal.addEventListener('click', handleBackdrop);
        document.addEventListener('keydown', handleKeydown);
      });

      const setButtonsDisabled = (state) => {
        document.querySelectorAll('.office-queue-action-btn').forEach((button) => {
          button.disabled = state;
        });
      };

      const showActionToast = (message, status) => {
        const existingToast = document.querySelector('.office-action-toast');
        if (existingToast instanceof HTMLElement) {
          existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.className = `office-action-toast ${status === 'approved' ? 'is-approved' : 'is-rejected'}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        window.setTimeout(() => {
          toast.classList.add('is-visible');
        }, 20);

        window.setTimeout(() => {
          toast.classList.remove('is-visible');
          window.setTimeout(() => toast.remove(), 180);
        }, 1800);
      };

      const bumpSummaryValue = (id, delta) => {
        const el = document.getElementById(id);
        if (!(el instanceof HTMLElement)) {
          return;
        }

        const current = Number.parseInt((el.textContent || '').replace(/[^\d-]/g, ''), 10);
        if (!Number.isFinite(current)) {
          return;
        }

        el.textContent = String(Math.max(0, current + delta));
      };

      const applyLocalQueueDecision = (button, action) => {
        if (!(button instanceof HTMLButtonElement)) {
          return;
        }

        const row = button.closest('tr');
        if (!(row instanceof HTMLTableRowElement)) {
          return;
        }

        row.remove();

        const hasRows = queueBody.querySelector('tr');
        if (!hasRows) {
          queueBody.innerHTML = '<tr><td colspan="6">No actionable requests found for your office.</td></tr>';
        }

        // Keep summary tiles responsive while server-side refresh runs.
        bumpSummaryValue('office-summary-actionable', -1);
        bumpSummaryValue('office-summary-pending', -1);
        if (action === 'approve') {
          bumpSummaryValue('office-summary-approved', 1);
        } else if (action === 'reject') {
          bumpSummaryValue('office-summary-rejected', 1);
        }
      };

      const getRefreshStatusEl = () => document.getElementById('office-request-refresh-status');

      const setReloadButtonState = (isLoading, isManual = false) => {
        if (!isManual) {
          return; // Only update button for manual refreshes
        }

        const reloadButton = document.getElementById('office-queue-reload');
        const icon = reloadButton?.querySelector('.office-queue-reload-icon');
        const text = reloadButton?.querySelector('.office-queue-reload-text');

        if (reloadButton instanceof HTMLButtonElement) {
          reloadButton.disabled = isLoading;
        }

        if (icon instanceof HTMLElement) {
          icon.classList.toggle('is-loading', isLoading);
        }

        if (text instanceof HTMLElement) {
          text.textContent = isLoading ? 'Refreshing...' : 'Reload';
        }
      };

      const updateRefreshStatus = () => {
        const statusEl = getRefreshStatusEl();
        if (!(statusEl instanceof HTMLElement)) {
          return;
        }
        const now = new Date();
        statusEl.textContent = `Last updated ${now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
      };

      const initOfficeQueueReloadButton = () => {
        const reloadButton = document.getElementById('office-queue-reload');
        if (!(reloadButton instanceof HTMLElement)) {
          return;
        }

        reloadButton.addEventListener('click', () => {
          softRefreshQueue(true); // Mark as manual
        });
      };

      const applyQueueSnapshotPayload = (payload) => {
        if (!payload || typeof payload !== 'object') {
          return;
        }

        if (typeof payload.rows_html === 'string') {
          queueBody.innerHTML = payload.rows_html;
        }

        if (paginationWrap instanceof HTMLElement && typeof payload.pagination_html === 'string') {
          paginationWrap.innerHTML = payload.pagination_html;
        }

        const summary = payload.summary && typeof payload.summary === 'object' ? payload.summary : {};
        const summaryMap = {
          'office-summary-actionable': summary.actionable,
          'office-summary-pending': summary.pending,
          'office-summary-approved': summary.approved,
          'office-summary-rejected': summary.rejected,
        };

        summaryIds.forEach((id) => {
          const current = document.getElementById(id);
          const nextValue = summaryMap[id];
          if (current instanceof HTMLElement && Number.isFinite(Number(nextValue))) {
            current.textContent = String(Math.max(0, Number(nextValue)));
          }
        });
      };

      const softRefreshQueue = async (isManual = false, forceWhileActing = false) => {
        if (isRefreshing || (isActing && !forceWhileActing)) {
          return;
        }

        isRefreshing = true;
        const runVersion = refreshVersion;
        setReloadButtonState(true, isManual);

        try {
          const snapshotUrl = (typeof window.officeQueueSnapshotUrl === 'string' && window.officeQueueSnapshotUrl)
            ? window.officeQueueSnapshotUrl
            : '/dashboard/office/requests/snapshot';

          const response = await fetch(snapshotUrl, {
            method: 'GET',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json',
              'Cache-Control': 'no-cache',
            },
            cache: 'no-store',
          });

          if (!response.ok) {
            return;
          }

          const payload = await response.json().catch(() => ({}));
          if (!payload.success) {
            return;
          }

          // Ignore stale refresh responses that started before a newer state mutation.
          if (runVersion !== refreshVersion) {
            return;
          }

          // Guard against applying background snapshots while an action is still in progress.
          if (isActing && !forceWhileActing) {
            return;
          }

          applyQueueSnapshotPayload(payload);
          updateRefreshStatus();
        } catch (_error) {
          // Silent fail: keep UI usable and try again on next interval.
        } finally {
          isRefreshing = false;
          setReloadButtonState(false, isManual);
        }
      };

      document.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
          return;
        }

        const button = target.closest('.office-queue-action-btn');
        if (!(button instanceof HTMLButtonElement)) {
          return;
        }

        const approvalId = button.getAttribute('data-approval-id');
        const action = button.getAttribute('data-action');

        if (!approvalId || !action) {
          return;
        }

        const confirmed = await openActionConfirmModal(action);

        if (!confirmed) {
          return;
        }

        isActing = true;
        refreshVersion += 1;
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
            return;
          }

          const actorName = window.authUser?.full_name || 'Admin';
          const decisionWord = action === 'approve' ? 'approved' : 'rejected';
          const fallbackMessage = `Request ${decisionWord} by ${actorName}.`;
          showActionToast(payload.message || fallbackMessage, decisionWord === 'approved' ? 'approved' : 'rejected');
          applyLocalQueueDecision(button, action);
          const statusEl = getRefreshStatusEl();
          if (statusEl instanceof HTMLElement) {
            statusEl.textContent = 'Syncing latest changes...';
          }

          softRefreshQueue(false, true);
        } catch (_error) {
          showAppNotice('Request failed. Please check your connection and try again.');
        } finally {
          isActing = false;
          setButtonsDisabled(false);
        }
      });

      document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
          softRefreshQueue();
        }
      });

      initOfficeQueueReloadButton();

      window.setInterval(() => {
        if (!document.hidden) {
          softRefreshQueue();
        }
      }, refreshIntervalMs);
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

    .office-queue-reload-btn {
      border: 1px solid rgba(15, 23, 42, 0.12);
      background: #fff;
      color: #0f172a;
      border-radius: 10px;
      padding: 0.65rem 0.9rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      cursor: pointer;
      transition: filter 0.15s ease, transform 0.15s ease;
    }

    .office-queue-reload-btn:hover {
      filter: brightness(0.95);
      transform: translateY(-1px);
    }

    .office-queue-reload-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: transform 0.3s ease;
    }

    .office-queue-reload-icon.is-loading {
      transform: rotate(90deg);
    }

    .office-request-refresh-status {
      display: block;
      margin-top: 0.25rem;
      color: #64748b;
      font-size: 0.88rem;
    }

    .office-request-history-head-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
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

    .office-action-toast {
      position: fixed;
      right: 24px;
      bottom: 24px;
      z-index: 9999;
      min-width: 240px;
      max-width: 360px;
      padding: 12px 14px;
      border-radius: 10px;
      color: #fff;
      font-weight: 700;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      opacity: 0;
      transform: translateY(10px);
      transition: opacity 0.18s ease, transform 0.18s ease;
    }

    .office-action-toast.is-visible {
      opacity: 1;
      transform: translateY(0);
    }

    .office-action-toast.is-approved {
      background: #0a8f3e;
    }

    .office-action-toast.is-rejected {
      background: #c92a2a;
    }

    .office-action-confirm-modal {
      position: fixed;
      inset: 0;
      z-index: 1100;
      display: none;
    }

    .office-action-confirm-modal.is-open {
      display: grid;
      place-items: center;
      padding: 18px;
    }

    .office-action-confirm-overlay {
      position: absolute;
      inset: 0;
      background: rgba(22, 27, 39, 0.46);
      backdrop-filter: blur(1px);
    }

    .office-action-confirm-card {
      position: relative;
      width: min(500px, 90vw);
      background: #ffffff;
      border: 1px solid #d5ddea;
      border-radius: 10px;
      box-shadow: 0 14px 30px rgba(18, 26, 51, 0.22);
      overflow: hidden;
    }

    .office-action-confirm-card.is-reject {
      border-color: #ebc9c9;
    }

    .office-action-confirm-head {
      border-bottom: 1px solid #e8ecf3;
      padding: 12px 16px;
      background: #fbfcff;
    }

    .office-action-confirm-head h2 {
      margin: 0;
      color: #167d2e;
      font-size: 1.65rem;
      font-weight: 700;
      line-height: 1.2;
    }

    .office-action-confirm-card.is-reject .office-action-confirm-head h2 {
      color: #c53030;
    }

    .office-action-confirm-body {
      padding: 14px 16px;
      border-bottom: 1px solid #e8ecf3;
      color: #2f3545;
      font-size: 1.08rem;
      line-height: 1.42;
      background: #ffffff;
    }

    .office-action-confirm-body p {
      margin: 0;
    }

    .office-action-confirm-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      padding: 10px 16px 12px;
      background: #fbfcff;
    }

    #office-action-confirm-cancel {
      min-width: 104px;
      height: 36px;
      border: 1px solid #c7cfde;
      background: #f1f4fa;
      color: #3b4458;
      font-weight: 600;
    }

    #office-action-confirm-submit {
      min-width: 118px;
      height: 36px;
      border-radius: 7px;
      font-weight: 700;
      box-shadow: none;
    }

    #office-action-confirm-submit.approve {
      border-color: #158a31;
      background: #149031;
    }

    #office-action-confirm-submit.reject {
      border-color: #d44545;
      background: #cf3b3b;
      color: #ffffff;
    }

    @media (max-width: 768px) {
      .office-action-confirm-head h2 {
        font-size: 1.35rem;
      }

      .office-action-confirm-body {
        font-size: 0.96rem;
      }
    }
  </style>
</body>
</html>
