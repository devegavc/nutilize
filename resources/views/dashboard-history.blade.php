<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | History</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-inventory.css?v={{ filemtime(public_path('css/db-inventory.css')) }}" />
</head>
<body>
  <script>
    window.authUser = {
      id: {{ auth()->user()->user_id ?? 'null' }},
      username: '{{ auth()->user()->username ?? 'User' }}',
      email: '{{ auth()->user()->email ?? '' }}',
      full_name: '{{ auth()->user()->full_name ?? auth()->user()->username ?? 'User' }}',
      role: '{{ auth()->user()->role ?? 'user' }}'
    };
    window.historyRows = @json($historyRows ?? []);
    window.historyEmailEndpoint = @json(route('dashboard.history.email'));
  </script>
  <header class="top-header">
    <div class="top-header-inner toolbar-card">
      <img src="/img/nutilize_logo.png" alt="NU-TILIZE" class="toolbar-logo" />

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
      @include('partials.dashboard-navbar')

      <section class="content-card history-content-card">
        <h1 class="section-title">LENDING HISTORY</h1>
        <p class="history-print-range" id="history-print-range" hidden></p>

        <section class="stats-grid inventory-stats-grid history-summary-grid" aria-label="History summary">
          <article class="stat-card inventory-stat-card history-summary-card">
            <span class="stat-icon"><i class="bi bi-clock-history"></i></span>
            <div>
              <p class="stat-number">{{ $historyCounts['all'] ?? 0 }}</p>
              <p class="stat-label">All</p>
            </div>
          </article>
          <article class="stat-card inventory-stat-card history-summary-card">
            <span class="stat-icon"><i class="bi bi-box-seam"></i></span>
            <div>
              <p class="stat-number">{{ $historyCounts['lending'] ?? 0 }}</p>
              <p class="stat-label">Lending</p>
            </div>
          </article>
          <article class="stat-card inventory-stat-card history-summary-card">
            <span class="stat-icon"><i class="bi bi-exclamation-triangle"></i></span>
            <div>
              <p class="stat-number">{{ $historyCounts['damaged'] ?? 0 }}</p>
              <p class="stat-label">Damaged</p>
            </div>
          </article>
        </section>

        <section class="history-category-row" aria-label="History category">
          <p class="history-category-label">Category</p>
          <div class="history-tab-group" role="tablist">
            <button class="history-category-btn is-active" type="button" role="tab" data-history-category="all" aria-pressed="true">All</button>
            <button class="history-category-btn" type="button" role="tab" data-history-category="lending" aria-pressed="false">Lending</button>
            <button class="history-category-btn" type="button" role="tab" data-history-category="damaged" aria-pressed="false">Damaged</button>
          </div>
        </section>

        <section class="history-tools-row">
          <div class="history-tools-filters">
            <label class="history-field" for="history-sort">
              Sort by
              <select id="history-sort">
                <option value="latest" selected>Latest</option>
                <option value="oldest">Oldest</option>
              </select>
            </label>
            <label class="history-field" for="history-date-from">
              From
              <input id="history-date-from" type="date" />
            </label>
            <label class="history-field" for="history-date-to">
              To
              <input id="history-date-to" type="date" />
            </label>
            <button class="history-reset-btn" id="history-filter-reset" type="button">Reset</button>
          </div>
          <div class="history-head-actions">
            <button class="history-print-btn" id="history-print-btn" type="button">
              <i class="bi bi-printer-fill"></i> Print File
            </button>
            <button class="history-email-btn" id="history-email-btn" type="button">
              <i class="bi bi-envelope-fill"></i> Send to Email
            </button>
          </div>
        </section>

        <section class="inventory-grid history-grid">
          <div class="table-wrap">
            <table class="inventory-table history-table">
              <thead>
                <tr>
                  <th><i class="bi bi-credit-card-2-front-fill"></i> Lending ID</th>
                  <th><i class="bi bi-person-workspace"></i> User Name</th>
                  <th><i class="bi bi-calendar3"></i> Date</th>
                  <th><i class="bi bi-pc-display-horizontal"></i> Item Borrowed</th>
                  <th><i class="bi bi-archive-fill"></i> Item Status</th>
                </tr>
              </thead>
              <tbody id="history-table-body" aria-busy="true">
                <tr class="nutilize-sr-only"><td colspan="5">Loading data...</td></tr>
                <tr class="nutilize-table-skeleton"><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td></tr>
                <tr class="nutilize-table-skeleton"><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td></tr>
                <tr class="nutilize-table-skeleton"><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td></tr>
                <tr class="nutilize-table-skeleton"><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td></tr>
                <tr class="nutilize-table-skeleton"><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td></tr>
                <tr class="nutilize-table-skeleton"><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td></tr>
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </section>
  </main>

  <script src="/js/dashboard.js?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
</body>
</html>

