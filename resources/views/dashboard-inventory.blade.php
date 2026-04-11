<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
              <p class="stat-number">67</p>
              <p class="stat-label">Facilities</p>
            </div>
          </article>
          <article class="stat-card inventory-stat-card">
            <span class="stat-icon"><i class="bi bi-person-workspace"></i></span>
            <div>
              <p class="stat-number">208</p>
              <p class="stat-label">Equipments</p>
            </div>
          </article>
          <article class="stat-card inventory-stat-card">
            <span class="stat-icon"><i class="bi bi-files"></i></span>
            <div>
              <p class="stat-number">44</p>
              <p class="stat-label">Maintenance &amp; Report</p>
            </div>
          </article>
        </section>

        <section class="inventory-grid">
          <div class="inventory-grid-head">
            <h2><i class="bi bi-bar-chart-line-fill"></i> Most Requested Items</h2>
            <button type="button" onclick="window.location.href='/dashboard/inventory/analytics'">View Analytics</button>
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
                <tr>
                  <td>#94fDy52</td>
                  <td>Speaker</td>
                  <td>AVR</td>
                  <td>Electronic</td>
                  <td><span class="freq-bar"><span style="width:56%"></span></span></td>
                </tr>
                <tr>
                  <td>#65aDy57</td>
                  <td>Ball</td>
                  <td>GYM</td>
                  <td>Utility</td>
                  <td><span class="freq-bar"><span style="width:88%"></span></span></td>
                </tr>
                <tr>
                  <td>#42aAa13</td>
                  <td>HDMI</td>
                  <td>Storage A</td>
                  <td>Electronic</td>
                  <td><span class="freq-bar"><span style="width:80%"></span></span></td>
                </tr>
                <tr>
                  <td>#12rBy62</td>
                  <td>Remote</td>
                  <td>Storage B</td>
                  <td>Electronic</td>
                  <td><span class="freq-bar"><span style="width:93%"></span></span></td>
                </tr>
                <tr>
                  <td>#31kLm72</td>
                  <td>Extension Cord</td>
                  <td>Storage C</td>
                  <td>Electronic</td>
                  <td><span class="freq-bar"><span style="width:47%"></span></span></td>
                </tr>
                <tr>
                  <td>#58vPx19</td>
                  <td>Volleyball Net</td>
                  <td>GYM</td>
                  <td>Utility</td>
                  <td><span class="freq-bar"><span style="width:69%"></span></span></td>
                </tr>
                <tr>
                  <td>#77qWe34</td>
                  <td>Tripod Stand</td>
                  <td>AVR</td>
                  <td>Electronic</td>
                  <td><span class="freq-bar"><span style="width:61%"></span></span></td>
                </tr>
                <tr>
                  <td>#22nRt88</td>
                  <td>Portable Fan</td>
                  <td>Storage D</td>
                  <td>Appliance</td>
                  <td><span class="freq-bar"><span style="width:52%"></span></span></td>
                </tr>
                <tr>
                  <td>#90bYu43</td>
                  <td>Wireless Mic</td>
                  <td>AVR</td>
                  <td>Electronic</td>
                  <td><span class="freq-bar"><span style="width:86%"></span></span></td>
                </tr>
                <tr>
                  <td>#46mAs27</td>
                  <td>Foldable Table</td>
                  <td>Storage A</td>
                  <td>Furniture</td>
                  <td><span class="freq-bar"><span style="width:58%"></span></span></td>
                </tr>
                <tr>
                  <td>#17uLp30</td>
                  <td>Whiteboard Marker Set</td>
                  <td>Room 204</td>
                  <td>School Supply</td>
                  <td><span class="freq-bar"><span style="width:41%"></span></span></td>
                </tr>
                <tr>
                  <td>#88jKt14</td>
                  <td>Projector Screen</td>
                  <td>AVR</td>
                  <td>Electronic</td>
                  <td><span class="freq-bar"><span style="width:76%"></span></span></td>
                </tr>
                <tr>
                  <td>#63fRs95</td>
                  <td>Sound Mixer</td>
                  <td>Audio Booth</td>
                  <td>Electronic</td>
                  <td><span class="freq-bar"><span style="width:67%"></span></span></td>
                </tr>
                <tr>
                  <td>#09gNm41</td>
                  <td>Badminton Racket</td>
                  <td>GYM</td>
                  <td>Sports</td>
                  <td><span class="freq-bar"><span style="width:54%"></span></span></td>
                </tr>
                <tr>
                  <td>#52pQa22</td>
                  <td>Laptop Charger</td>
                  <td>IT Room</td>
                  <td>Electronic</td>
                  <td><span class="freq-bar"><span style="width:72%"></span></span></td>
                </tr>
                <tr>
                  <td>#34xCd17</td>
                  <td>Podium Stand</td>
                  <td>Main Hall</td>
                  <td>Furniture</td>
                  <td><span class="freq-bar"><span style="width:49%"></span></span></td>
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

