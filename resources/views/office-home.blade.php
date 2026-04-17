<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Office Home</title>

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
    window.dashboardNavComponent = '/components/navbar.html';
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

      <section class="content-card office-home-card">
        <section class="office-heading">
          <h1>Campus Resource &amp; Reservation Management System</h1>
          <p>{{ auth()->user()?->office?->department_name ?? 'Student Development Office' }}</p>
        </section>

        <section class="office-stats-grid" aria-label="Request summary">
          <article class="office-stat-card">
            <span class="office-stat-icon pending"><i class="bi bi-clock-history"></i></span>
            <div>
              <p class="office-stat-value">12</p>
              <p class="office-stat-label">Pending Request</p>
            </div>
          </article>
          <article class="office-stat-card">
            <span class="office-stat-icon canceled"><i class="bi bi-x-lg"></i></span>
            <div>
              <p class="office-stat-value">5</p>
              <p class="office-stat-label">Canceled Request</p>
            </div>
          </article>
          <article class="office-stat-card">
            <span class="office-stat-icon approved"><i class="bi bi-check-lg"></i></span>
            <div>
              <p class="office-stat-value">8</p>
              <p class="office-stat-label">Approved</p>
            </div>
          </article>
        </section>

        <section class="office-table-card" aria-label="Reservations table">
          <header class="office-table-header">
            <h2>Reservations</h2>
            <div class="office-table-filters">
              <label>
                Date:
                <select>
                  <option selected>Today</option>
                  <option>This Week</option>
                  <option>This Month</option>
                </select>
              </label>
              <label>
                Sort by:
                <select>
                  <option selected>Approve</option>
                  <option>Pending</option>
                  <option>Canceled</option>
                </select>
              </label>
            </div>
          </header>

          <div class="table-wrap office-table-wrap">
            <table class="office-table">
              <thead>
                <tr>
                  <th>Student ID</th>
                  <th>Requested by</th>
                  <th>Resource</th>
                  <th>Activity</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>2021-181217</td>
                  <td>Pastrana, John Paul</td>
                  <td>Rm 607</td>
                  <td>Faculty Meeting</td>
                  <td><span class="badge pending">Pending</span></td>
                </tr>
                <tr>
                  <td>2022-171602</td>
                  <td>Cumpas, Josh Andrew</td>
                  <td>TV #03</td>
                  <td>Film Showing</td>
                  <td><span class="badge pending">Pending</span></td>
                </tr>
                <tr>
                  <td>2023-192003</td>
                  <td>Cumpas, Josh Andrew</td>
                  <td>Projector #09</td>
                  <td>Film Showing</td>
                  <td><span class="badge pending">Pending</span></td>
                </tr>
                <tr>
                  <td>2021-10027</td>
                  <td>Nadal, Kitchie</td>
                  <td>Rm 508</td>
                  <td>Group Study</td>
                  <td><span class="badge pending">Pending</span></td>
                </tr>
                <tr>
                  <td>2024-101611</td>
                  <td>Velasquez, Regine</td>
                  <td>Speaker #32</td>
                  <td>Flag Ceremony</td>
                  <td><span class="badge solved">Approve</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="office-bottom-grid">
          <article class="office-panel issues-panel">
            <h3>Most Common Issues <span>(This Week)</span></h3>
            <ul>
              <li><i class="bi bi-clock-history"></i> Time Conflict (3)</li>
              <li><i class="bi bi-columns-gap"></i> Insufficient Chair (2)</li>
              <li><i class="bi bi-exclamation-octagon"></i> Room unavailable (1)</li>
            </ul>
          </article>

          <article class="office-panel workload-panel">
            <h3>Today's Workload</h3>
            <div class="office-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="65">
              <div class="office-progress-fill"><span>65%</span></div>
            </div>
            <p>8 of 12 requests processed</p>
          </article>
        </section>
      </section>
    </section>
  </main>

  <script src="/js/dashboard.js"></script>
</body>
</html>
