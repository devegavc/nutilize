<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Inventory</title>

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

      <section class="content-card inventory-dashboard-card">
        <h1 class="section-title">INVENTORY DASHBOARD</h1>

        <section class="stats-grid inventory-stats-grid">
          <article class="stat-card inventory-stat-card">
            <span class="stat-icon"><i class="bi bi-buildings"></i></span>
            <div>
              <p class="stat-number">{{ $facilityCount }}</p>
              <p class="stat-label">Facilities</p>
            </div>
          </article>
          <article class="stat-card inventory-stat-card">
            <span class="stat-icon"><i class="bi bi-person-workspace"></i></span>
            <div>
              <p class="stat-number">{{ $equipmentCount }}</p>
              <p class="stat-label">Equipments</p>
            </div>
          </article>
          <article class="stat-card inventory-stat-card">
            <span class="stat-icon"><i class="bi bi-files"></i></span>
            <div>
              <p class="stat-number">{{ $maintenanceAndReportCount }}</p>
              <p class="stat-label">Maintenance &amp; Report</p>
            </div>
          </article>
        </section>

        <section class="inventory-grid">
          <div class="inventory-grid-head">
            <h2><i class="bi bi-bar-chart-line-fill"></i> Most Requested Items</h2>
            <button type="button" onclick="window.location.href='/dashboard/inventory/analytics'">View Insights</button>
          </div>

          <div class="table-wrap">
            <table class="inventory-table">
              <thead>
                <tr>
                  <th>Asset ID</th>
                  <th>Item Name</th>
                  <th>Location</th>
                  <th>Category</th>
                  <th>Frequency Usage</th>
                </tr>
              </thead>
              <tbody id="inventory-table-body">
                @forelse ($mostRequestedItems as $item)
                  <tr>
                    <td>{{ $item['asset_id'] }}</td>
                    <td>{{ $item['item_name'] }}</td>
                    <td>{{ $item['location'] }}</td>
                    <td>{{ $item['category'] }}</td>
                    <td>
                      <span class="freq-bar">
                        <span style="width:{{ $item['usage_percent'] }}%"></span>
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5">No item request data yet.</td>
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

