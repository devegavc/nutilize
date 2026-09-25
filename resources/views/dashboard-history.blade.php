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
      id: @json(auth()->user()->user_id),
      username: @json(auth()->user()->username ?? 'User'),
      email: @json(auth()->user()->email ?? ''),
      full_name: @json(auth()->user()->full_name ?? auth()->user()->username ?? 'User'),
      role: @json(auth()->user()->role ?? 'user')
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

        <section class="history-toolbar" aria-label="History filters">
          <div class="history-toolbar-group history-category-row">
            <span class="history-category-label" id="history-category-label">Category</span>
            <div class="history-tab-group" role="tablist" aria-labelledby="history-category-label">
              <button class="history-category-btn is-active" type="button" role="tab" data-history-category="all" aria-pressed="true">All</button>
              <button class="history-category-btn" type="button" role="tab" data-history-category="lending" aria-pressed="false">Lending</button>
              <button class="history-category-btn" type="button" role="tab" data-history-category="damaged" aria-pressed="false">Damaged</button>
            </div>
          </div>

          <label class="history-toolbar-group history-field history-sort-field" for="history-sort">
            <span>Sort by</span>
            <select id="history-sort">
              <option value="latest" selected>Latest</option>
              <option value="oldest">Oldest</option>
            </select>
          </label>

          <div class="history-toolbar-group history-date-range">
            <span class="history-category-label">Date</span>
            <input id="history-date-from" type="date" aria-label="From date" />
            <span class="history-date-arrow" aria-hidden="true">→</span>
            <input id="history-date-to" type="date" aria-label="To date" />
          </div>

          <button class="history-reset-btn" id="history-filter-reset" type="button">Reset</button>

          <div class="history-head-actions">
            <button class="history-copy-btn" id="history-copy-btn" type="button">
              <i class="bi bi-share"></i> Send a Copy
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

  <div class="history-copy-modal" id="history-copy-modal" aria-hidden="true">
    <div class="history-copy-overlay" data-close-history-copy="true"></div>
    <article class="history-copy-card" role="dialog" aria-modal="true" aria-labelledby="history-copy-title">
      <header class="history-copy-head">
        <h2 id="history-copy-title">Send a Copy</h2>
        <p>Choose how you want to export this history report.</p>
      </header>
      <div class="history-copy-options">
        <button class="history-copy-option" id="history-print-btn" type="button">
          <span class="history-copy-option-icon"><i class="bi bi-printer-fill"></i></span>
          <span class="history-copy-option-copy">
            <strong>Print File</strong>
            <span>Print the currently filtered history records.</span>
          </span>
        </button>
        <button class="history-copy-option" id="history-email-btn" type="button">
          <span class="history-copy-option-icon"><i class="bi bi-envelope-fill"></i></span>
          <span class="history-copy-option-copy">
            <strong>Send to Email</strong>
            <span>Send the currently filtered history records to an email address.</span>
          </span>
        </button>
      </div>
      <footer class="history-copy-foot">
        <button class="history-copy-cancel" id="history-copy-cancel" type="button">Cancel</button>
      </footer>
    </article>
  </div>

  <script src="/js/dashboard.js?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
</body>
</html>

