<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
        <p class="office-subtitle">Summary of requests and history of all records</p>

        <section class="office-request-summary-grid" aria-label="Request summaries">
          <article class="office-request-summary-tile">
            <span class="office-request-summary-icon"><i class="bi bi-inboxes-fill"></i></span>
            <div>
              <p class="office-request-summary-value">{{ $totalRequests }}</p>
              <p class="office-request-summary-label">Total Requests</p>
            </div>
          </article>
          <article class="office-request-summary-tile">
            <span class="office-request-summary-icon"><i class="bi bi-hourglass-split"></i></span>
            <div>
              <p class="office-request-summary-value">{{ $pendingRequests }}</p>
              <p class="office-request-summary-label">Pending</p>
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

        <section class="office-request-history-card" aria-label="Request history table">
          <header class="office-request-history-head">
            <h2>Request History (All)</h2>
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
                </tr>
              </thead>
              <tbody id="office-request-history-body">
                @forelse($requests as $request)
                  @php
                    $status = strtolower((string) ($request->overall_status ?? 'pending'));
                    $badgeClass = $status === 'approved' ? 'solved' : ($status === 'rejected' ? 'rejected' : 'pending');
                    $badgeText = $status === 'approved' ? 'Approved' : ($status === 'rejected' ? 'Rejected' : 'Pending');
                  @endphp
                  <tr>
                    <td>#{{ $request->reservation_id }}</td>
                    <td>{{ $request->user?->full_name ?? $request->user?->username ?? 'Unknown' }}</td>
                    <td>{{ $request->activity_name }}</td>
                    <td>{{ optional($request->created_at)->format('M d, Y h:i A') }}</td>
                    <td><span class="badge {{ $badgeClass }}">{{ $badgeText }}</span></td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5">No request history found.</td>
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

  <script src="/js/dashboard.js"></script>
</body>
</html>
