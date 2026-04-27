<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Inventory Insights</title>

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
  </script>
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

      <section class="content-card analytics-content-card">
        <h1 class="section-title">INSIGHTS DASHBOARD</h1>

        <section class="analytics-top-row">
          <article class="analytics-chart-card" aria-label="Borrow trend chart">
            <div class="chart-grid-lines"></div>
            <div class="chart-bars" aria-hidden="true">
              @foreach ($trendBars as $bar)
                <span style="width: {{ $bar }}%"></span>
              @endforeach
            </div>
            <div class="chart-years" aria-hidden="true">
              @foreach ($yearLabels as $yearLabel)
                <span>{{ $yearLabel }}</span>
              @endforeach
            </div>
          </article>

          <article class="analytics-kpi-card">
            <p>
              <span>Total Borrowers</span>
              <strong>{{ number_format($totalBorrowers) }}</strong>
              <em class="{{ $borrowersGrowth >= 0 ? 'up' : 'down' }}">
                {{ $borrowersGrowth > 0 ? '+' : '' }}{{ number_format($borrowersGrowth, 1) }}%
              </em>
            </p>
            <p>
              <span>Engagement Rates</span>
              <strong>{{ number_format($engagementCount) }}</strong>
              <em class="{{ $engagementGrowth >= 0 ? 'up' : 'down' }}">
                {{ $engagementGrowth > 0 ? '+' : '' }}{{ number_format($engagementGrowth, 1) }}%
              </em>
            </p>
            <p>
              <span>New Users</span>
              <strong>{{ number_format($newUsers) }}</strong>
              <em class="{{ $newUsersGrowth >= 0 ? 'up' : 'down' }}">
                {{ $newUsersGrowth > 0 ? '+' : '' }}{{ number_format($newUsersGrowth, 1) }}%
              </em>
            </p>
          </article>
        </section>

        <section class="inventory-grid analytics-table-grid">
          <div class="inventory-grid-head analytics-grid-head">
            <h2>Top Leading Borrowed items</h2>
          </div>
          <div class="table-wrap">
            <table class="inventory-table analytics-table">
              <thead>
                <tr>
                  <th>Item Name</th>
                  <th>Category</th>
                  <th>Location</th>
                  <th>ID</th>
                  <th>Usage Percentage</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($topItems as $item)
                  <tr>
                    <td>{{ $item['item_name'] }}</td>
                    <td>{{ $item['category'] }}</td>
                    <td>{{ $item['location'] }}</td>
                    <td class="asset-id">{{ $item['asset_id'] }}</td>
                    <td><span class="freq-bar"><span style="width: {{ $item['usage_percent'] }}%"></span></span></td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5">No borrowed items found yet.</td>
                  </tr>
                @endforelse
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
