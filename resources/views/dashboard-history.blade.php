<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | History</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-inventory.css" />
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
    window.historyRowsByTab = @json($historyRowsByTab ?? ['latest' => [], 'oldest' => [], 'damaged' => []]);
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

        <section class="history-head-row">
          <div>
            <p><i class="bi bi-clock-history"></i> Lending Details</p>
          </div>
        </section>

        <section class="history-filter-row">
          <div class="history-tab-group">
            <button class="history-tab active" type="button" data-history-tab="latest">Latest</button>
            <button class="history-tab" type="button" data-history-tab="oldest">Oldest</button>
            <button class="history-tab" type="button" data-history-tab="damaged">Damaged</button>
          </div>
          <div class="history-head-actions">
            <button class="history-print-btn" type="button" onclick="window.print()">
              <i class="bi bi-printer-fill"></i> Print File
            </button>
            <button
              class="history-email-btn"
              type="button"
              onclick="window.location.href='mailto:?subject=NU-TILIZE%20Lending%20History&body=Please%20review%20the%20latest%20lending%20history%20report.'"
            >
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

  <script src="/js/dashboard.js"></script>
</body>
</html>

