<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
        <h1 class="section-title">PHYSICAL FACILITIES DASHBOARD</h1>

        <section class="stats-grid">
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-files"></i></span>
            <div>
              <p class="stat-number">17</p>
              <p class="stat-label">Total Request</p>
            </div>
          </article>
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-wallet2"></i></span>
            <div>
              <p class="stat-number">09</p>
              <p class="stat-label">Borrowed</p>
            </div>
          </article>
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-check-circle-fill"></i></span>
            <div>
              <p class="stat-number">74</p>
              <p class="stat-label">Available</p>
            </div>
          </article>
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-wrench-adjustable-circle"></i></span>
            <div>
              <p class="stat-number">20</p>
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
                  <tr>
                    <td>Projector</td>
                    <td>Mr. Martin Espanoso</td>
                    <td>2 Images</td>
                    <td><span class="badge pending">Pending</span></td>
                  </tr>
                  <tr>
                    <td>Speaker</td>
                    <td>Mrs. Nina Tamaza</td>
                    <td>1 Video 2 Images</td>
                    <td><span class="badge pending">Pending</span></td>
                  </tr>
                  <tr>
                    <td>Microphone</td>
                    <td>Mr. Drei Punzalan</td>
                    <td>no attachment</td>
                    <td><span class="badge pending">Pending</span></td>
                  </tr>
                  <tr>
                    <td>Speaker</td>
                    <td>Mr. Jed Perez</td>
                    <td>2 Images</td>
                    <td><span class="badge solved">Solved</span></td>
                  </tr>
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
              <li>
                <span>March 23, 9:00 AM</span>
                <strong>Room 501 - Faculty Meeting</strong>
                <small>Requester: Archie Mesis | Resources: Projector, 30 Chairs</small>
              </li>
              <li>
                <span>March 23, 2:00 PM</span>
                <strong>Room 632 - PTA Briefing</strong>
                <small>Requester: Joel Enriquez | Resources: 1 TV, 20 Chairs</small>
              </li>
              <li>
                <span>March 24, 1:00 PM</span>
                <strong>AVR - Student Assembly</strong>
                <small>Requester: Jei Pastrana | Resources: Sound System, 50 Chairs</small>
              </li>
              <li>
                <span>March 24, 3:30 PM</span>
                <strong>Lab 204 - ICT Workshop</strong>
                <small>Requester: Ryan Mendoza | Resources: 15 PCs, HDMI Adapter</small>
              </li>
              <li>
                <span>March 25, 10:30 AM</span>
                <strong>Gym - Sports Briefing</strong>
                <small>Requester: Carlo Lim | Resources: PA System, 2 Tables</small>
              </li>
              <li>
                <span>March 26, 8:00 AM</span>
                <strong>Room 215 - Orientation Session</strong>
                <small>Requester: Faith Delgado | Resources: Whiteboard Kit, 25 Chairs</small>
              </li>
            </ul>
          </article>

          <article class="extra-card highlights-card">
            <h3><i class="bi bi-stars"></i> Daily Highlights</h3>
            <div class="highlights-grid">
              <div>
                <p class="highlight-label">Resolved Today</p>
                <p class="highlight-value">12</p>
              </div>
              <div>
                <p class="highlight-label">Pending Reports</p>
                <p class="highlight-value">5</p>
              </div>
              <div>
                <p class="highlight-label">Rooms Utilized</p>
                <p class="highlight-value">18</p>
              </div>
              <div>
                <p class="highlight-label">Equipment Checked</p>
                <p class="highlight-value">34</p>
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

