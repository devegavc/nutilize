<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Office Archive</title>

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
        <input id="dashboard-search" type="text" placeholder="Search archived records" />
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

      <section class="content-card office-archive-card">
        <h1 class="section-title">OFFICE ARCHIVE DASHBOARD</h1>
        <p class="office-subtitle">Approval and rejection transaction history for your office.</p>

        <section class="office-archive-overview" aria-label="Archive summary">
          <article class="office-archive-tile">
            <span class="office-archive-icon"><i class="bi bi-archive-fill"></i></span>
            <div>
              <p class="office-archive-value">{{ $totalTransactions ?? 0 }}</p>
              <p class="office-archive-label">Total Transactions</p>
            </div>
          </article>
          <article class="office-archive-tile">
            <span class="office-archive-icon"><i class="bi bi-trash3-fill"></i></span>
            <div>
              <p class="office-archive-value">{{ $approvedCount ?? 0 }}</p>
              <p class="office-archive-label">Approved</p>
            </div>
          </article>
          <article class="office-archive-tile">
            <span class="office-archive-icon"><i class="bi bi-shield-check"></i></span>
            <div>
              <p class="office-archive-value">{{ $rejectedCount ?? 0 }}</p>
              <p class="office-archive-label">Rejected</p>
            </div>
          </article>
          <article class="office-archive-tile">
            <span class="office-archive-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
            <div>
              <p class="office-archive-value">{{ $todayCount ?? 0 }}</p>
              <p class="office-archive-label">Processed Today</p>
            </div>
          </article>
        </section>

        <section class="office-archive-history-card" aria-label="Office approval transaction history table">
          <header class="office-archive-history-head">
            <h2>Approval Transaction History</h2>
            <div class="office-archive-legend">
              <span style="color:#148a31;">Approved</span>
              <span style="color:#b02525;">Rejected</span>
            </div>
          </header>

          <form method="GET" action="{{ route('office.archive') }}" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end; margin: 8px 0 14px;">
            <div>
              <label for="decision" style="display:block; font-size:12px; margin-bottom:4px;">Decision</label>
              <select id="decision" name="decision" style="min-width:140px;">
                <option value="all" {{ ($selectedDecision ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                <option value="approved" {{ ($selectedDecision ?? 'all') === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="rejected" {{ ($selectedDecision ?? 'all') === 'rejected' ? 'selected' : '' }}>Rejected</option>
              </select>
            </div>

            <div>
              <label for="from_date" style="display:block; font-size:12px; margin-bottom:4px;">From</label>
              <input id="from_date" type="date" name="from_date" value="{{ $selectedFromDate ?? '' }}" />
            </div>

            <div>
              <label for="to_date" style="display:block; font-size:12px; margin-bottom:4px;">To</label>
              <input id="to_date" type="date" name="to_date" value="{{ $selectedToDate ?? '' }}" />
            </div>

            <button type="submit" class="btn-primary" style="padding:6px 14px;">Apply Filters</button>
            <a href="{{ route('office.archive') }}" style="padding:6px 12px; border:1px solid #c5cad4; border-radius:8px; text-decoration:none; color:#26374a;">Clear</a>
          </form>

          <div class="table-wrap office-archive-wrap">
            <table class="office-archive-table">
              <thead>
                <tr>
                  <th>Request ID</th>
                  <th>Requested By</th>
                  <th>Resource</th>
                  <th>Processed At</th>
                  <th>Processed By</th>
                  <th>Activity</th>
                  <th>Decision</th>
                </tr>
              </thead>
              <tbody>
                @forelse(($historyRows ?? []) as $record)
                  <tr>
                    <td>{{ $record['request_id'] }}</td>
                    <td>{{ $record['requested_by'] }}</td>
                    <td>{{ $record['resource'] }}</td>
                    <td>{{ $record['processed_at'] }}</td>
                    <td>{{ $record['processed_by'] }}</td>
                    <td>{{ $record['reason'] }}</td>
                    <td>
                      @php
                        $decisionClass = strtolower($record['decision']) === 'approved' ? 'under-retention' : 'purge-ready';
                      @endphp
                      <span class="archive-status {{ $decisionClass }}">{{ $record['decision'] }}</span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7">No approval transactions yet for this office.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="office-request-pagination">
            {{ ($historyRows ?? null)?->links() }}
          </div>
        </section>
      </section>
    </section>
  </main>

  <script src="/js/dashboard.js"></script>
</body>
</html>
