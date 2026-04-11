<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Inventory Analytics</title>

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
        <h1 class="section-title">ANALYTICS DASHBOARD</h1>

        <section class="analytics-top-row">
          <article class="analytics-chart-card" aria-label="Borrow trend chart">
            <div class="chart-grid-lines"></div>
            <div class="chart-bars" aria-hidden="true">
              <span style="width: 49%"></span>
              <span style="width: 58%"></span>
              <span style="width: 82%"></span>
            </div>
            <div class="chart-years" aria-hidden="true">
              <span>2019</span>
              <span>2020</span>
              <span>2021</span>
              <span>2022</span>
              <span>2023</span>
              <span>2024</span>
              <span>2025</span>
              <span>2026</span>
            </div>
          </article>

          <article class="analytics-kpi-card">
            <p><span>Total Borrowers</span><strong>4,413</strong><em class="up">+4.8%</em></p>
            <p><span>Engagement Rates</span><strong>13,304</strong><em class="down">-2.8%</em></p>
            <p><span>New Users</span><strong>141</strong><em class="up">+7%</em></p>
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
                <tr>
                  <td>HDMI</td>
                  <td>Electronic</td>
                  <td>Storage A</td>
                  <td class="asset-id">#gThr31</td>
                  <td><span class="freq-bar"><span style="width: 90%"></span></span></td>
                </tr>
                <tr>
                  <td>TV</td>
                  <td>Electronic</td>
                  <td>Storage A</td>
                  <td class="asset-id">#gThr31</td>
                  <td><span class="freq-bar"><span style="width: 76%"></span></span></td>
                </tr>
                <tr>
                  <td>AVR</td>
                  <td>Events Place</td>
                  <td>6th Floor</td>
                  <td class="asset-id">#gThr31</td>
                  <td><span class="freq-bar"><span style="width: 50%"></span></span></td>
                </tr>
                <tr>
                  <td>Speaker</td>
                  <td>Electronic</td>
                  <td>Storage B</td>
                  <td class="asset-id">#gThr31</td>
                  <td><span class="freq-bar"><span style="width: 31%"></span></span></td>
                </tr>
                <tr>
                  <td>Podium</td>
                  <td>Utility</td>
                  <td>Storage C</td>
                  <td class="asset-id">#gThr31</td>
                  <td><span class="freq-bar"><span style="width: 12%"></span></span></td>
                </tr>
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
