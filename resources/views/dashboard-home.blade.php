<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Home</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-home.css" />
</head>
<body>
  <header class="top-header">
    <div class="top-header-inner toolbar-card">
      <img src="/img/nutilize_logo.png" alt="NU-TILIZE" class="toolbar-logo" />

      <div class="search-wrap">
        <i class="bi bi-search"></i>
        <input id="dashboard-search" type="text" placeholder="Search" />
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

      <section class="content-card">
        <script>
          window.authUser = {
            id: {{ auth()->user()->user_id ?? 'null' }},
            username: '{{ auth()->user()->username ?? 'User' }}',
            email: '{{ auth()->user()->email ?? '' }}',
            full_name: '{{ auth()->user()->full_name ?? auth()->user()->username ?? 'User' }}',
            role: '{{ auth()->user()->role ?? 'user' }}'
          };
        </script>
        <h1 class="section-title">PHYSICAL FACILITIES DASHBOARD</h1>

        <section class="stats-grid">
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-files"></i></span>
            <div>
              <p class="stat-number">{{ str_pad((string) ($stats['total_requests'] ?? 0), 2, '0', STR_PAD_LEFT) }}</p>
              <p class="stat-label">Total Request</p>
            </div>
          </article>
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-wallet2"></i></span>
            <div>
              <p class="stat-number">{{ str_pad((string) ($stats['borrowed'] ?? 0), 2, '0', STR_PAD_LEFT) }}</p>
              <p class="stat-label">Borrowed</p>
            </div>
          </article>
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-check-circle-fill"></i></span>
            <div>
              <p class="stat-number">{{ str_pad((string) ($stats['available'] ?? 0), 2, '0', STR_PAD_LEFT) }}</p>
              <p class="stat-label">Available</p>
            </div>
          </article>
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-wrench-adjustable-circle"></i></span>
            <div>
              <p class="stat-number">{{ str_pad((string) ($stats['maintenance'] ?? 0), 2, '0', STR_PAD_LEFT) }}</p>
              <p class="stat-label">Maintenance</p>
            </div>
          </article>
        </section>

        <section class="middle-grid">
          <article class="quick-view">
            <div class="quick-view-header"><i class="bi bi-exclamation-circle-fill"></i> Report Quick View</div>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Reported by:</th>
                    <th>Attachment</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody id="report-table-body">
                  @forelse(($quickReports ?? []) as $report)
                    <tr>
                      <td>{{ $report['item'] }}</td>
                      <td>{{ $report['reported_by'] }}</td>
                      <td>{{ $report['attachment_label'] }}</td>
                      <td><span class="badge {{ $report['status_class'] }}">{{ $report['status_label'] }}</span></td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="4">No reports found.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </article>

          <article class="tasks-panel">
            <h2>Tasks To Acomplish</h2>
            <p class="tasks-sub">(This week)</p>
            <ul>
              <li><i class="bi bi-record-circle"></i> Pending Final Approvals (7)</li>
              <li><i class="bi bi-file-earmark-lock2-fill"></i> Review Damaged Items (3)</li>
              <li><i class="bi bi-tools"></i> Need of repair (2)</li>
            </ul>
          </article>
        </section>

        <section class="home-extra-grid">
          <article class="extra-card upcoming-card">
            <h3><i class="bi bi-calendar2-event-fill"></i> Upcoming Requests</h3>
            <ul>
              @forelse(($upcomingRequests ?? []) as $request)
                <li>
                  <span>{{ $request['time_label'] }}</span>
                  <strong>{{ $request['title'] }}</strong>
                  <small>{{ $request['subtitle'] }}</small>
                </li>
              @empty
                <li>
                  <span>{{ now()->format('F j') }}</span>
                  <strong>No requests submitted today</strong>
                  <small>New requests created today will appear here first.</small>
                </li>
              @endforelse
            </ul>
          </article>

          <article class="extra-card highlights-card">
            <h3><i class="bi bi-stars"></i> Daily Highlights</h3>
            <div class="highlights-grid">
              <div>
                <p class="highlight-label">Resolved Today</p>
                <p class="highlight-value">{{ $dailyHighlights['resolved_today'] ?? 0 }}</p>
              </div>
              <div>
                <p class="highlight-label">Pending Reports</p>
                <p class="highlight-value">{{ $dailyHighlights['pending_reports'] ?? 0 }}</p>
              </div>
              <div>
                <p class="highlight-label">Rooms Utilized</p>
                <p class="highlight-value">{{ $dailyHighlights['rooms_utilized'] ?? 0 }}</p>
              </div>
              <div>
                <p class="highlight-label">Equipment Checked</p>
                <p class="highlight-value">{{ $dailyHighlights['equipment_checked'] ?? 0 }}</p>
              </div>
            </div>
          </article>
        </section>
      </section>
    </section>
  </main>

  <script src="/js/dashboard.js"></script>
</body>
</html>

