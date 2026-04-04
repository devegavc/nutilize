<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NUtilize | Schedule</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-schedule.css" />
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

      <section class="content-card schedule-content-card">
        <h1 class="section-title">SCHEDULE DASHBOARD</h1>

        <section class="schedule-layout">
          <article class="schedule-filter-card">
            <button class="schedule-filter-btn active" type="button" data-schedule-filter="all">All</button>
            <button class="schedule-filter-btn" type="button" data-schedule-filter="rooms">Rooms</button>
            <button class="schedule-filter-btn" type="button" data-schedule-filter="tv">TV</button>
            <button class="schedule-filter-btn" type="button" data-schedule-filter="speaker">Speaker</button>
            <button class="schedule-filter-btn" type="button" data-schedule-filter="furniture">Furniture</button>
          </article>

          <article class="schedule-calendar-card">
            <header class="schedule-month-row">
              <button class="month-nav-btn" type="button" aria-label="Previous month">
                <i class="bi bi-chevron-left"></i>
              </button>
              <h1>FEBRUARY 2026</h1>
              <button class="month-nav-btn" type="button" aria-label="Next month">
                <i class="bi bi-chevron-right"></i>
              </button>
            </header>

            <section class="calendar-grid-wrap">
              <div class="calendar-grid">
                <span class="day-label">Sunday</span>
                <span class="day-label">Monday</span>
                <span class="day-label">Tuesday</span>
                <span class="day-label">Wednesday</span>
                <span class="day-label">Thursday</span>
                <span class="day-label">Friday</span>
                <span class="day-label">Saturday</span>

                <span class="day" data-day="1">1</span>
                <span class="day" data-day="2">2</span>
                <span class="day" data-day="3">3</span>
                <span class="day marked" data-day="4">4</span>
                <span class="day marked" data-day="5">5</span>
                <span class="day" data-day="6">6</span>
                <span class="day marked" data-day="7">7</span>

                <span class="day" data-day="8">8</span>
                <span class="day" data-day="9">9</span>
                <span class="day marked" data-day="10">10</span>
                <span class="day" data-day="11">11</span>
                <span class="day marked" data-day="12">12</span>
                <span class="day marked" data-day="13">13</span>
                <span class="day" data-day="14">14</span>

                <span class="day" data-day="15">15</span>
                <span class="day marked" data-day="16">16</span>
                <span class="day" data-day="17">17</span>
                <span class="day marked" data-day="18">18</span>
                <span class="day" data-day="19">19</span>
                <span class="day" data-day="20">20</span>
                <span class="day marked" data-day="21">21</span>

                <span class="day" data-day="22">22</span>
                <span class="day" data-day="23">23</span>
                <span class="day" data-day="24">24</span>
                <span class="day" data-day="25">25</span>
                <span class="day" data-day="26">26</span>
                <span class="day marked" data-day="27">27</span>
                <span class="day" data-day="28">28</span>
              </div>
            </section>
          </article>
        </section>

        <section class="schedule-inline-panel" id="schedule-inline-panel" aria-live="polite">
          <div class="schedule-inline-content">
            <div class="schedule-inline-table-wrap">
              <div class="schedule-inline-table-title">Selected Date Details</div>
              <div class="schedule-inline-table-meta" id="schedule-inline-date">Select a highlighted date to see requests and details below.</div>
              <table class="schedule-inline-table">
                <thead>
                  <tr>
                    <th>Student ID</th>
                    <th>Date Requested</th>
                    <th>Resource</th>
                  </tr>
                </thead>
                <tbody id="schedule-inline-request-body">
                  <tr>
                    <td colspan="3">No date selected yet.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <article class="schedule-inline-detail-card" id="schedule-inline-detail-card">
              <h3>Request Information</h3>

              <div class="schedule-inline-detail-grid">
                <span>Name:</span>
                <span id="schedule-inline-detail-name">-</span>

                <span>Title of Activity:</span>
                <span id="schedule-inline-detail-title">-</span>

                <span>Date(s) of Activity:</span>
                <span id="schedule-inline-detail-date">-</span>

                <span>Time of Activity:</span>
                <span id="schedule-inline-detail-time">-</span>

                <span>Expected Attendance:</span>
                <span id="schedule-inline-detail-attendance">-</span>

                <span>Type of Resource:</span>
                <span id="schedule-inline-detail-resource">-</span>
              </div>

              <div class="schedule-inline-extra-title">Other Resources Requested:</div>
              <div class="schedule-inline-extra-list">
                <div><i class="bi bi-chair"></i> Arm Chairs: <span id="schedule-inline-detail-chairs">0</span></div>
                <div><i class="bi bi-table"></i> Tables: <span id="schedule-inline-detail-tables">0</span></div>
              </div>
            </article>
          </div>
        </section>
      </section>
    </section>
  </main>

  <section class="schedule-modal" id="schedule-request-modal" aria-hidden="true">
    <div class="schedule-modal-overlay" data-close-schedule-modal="true"></div>
    <article class="schedule-modal-card" role="dialog" aria-modal="true" aria-labelledby="schedule-modal-date">
      <div class="schedule-modal-top"></div>
      <div class="schedule-modal-body">
        <div class="schedule-modal-date" id="schedule-modal-date">Date Requested: --</div>
        <div class="schedule-modal-table-wrap">
          <table class="schedule-modal-table">
            <thead>
              <tr>
                <th>Student ID</th>
                <th>Date Requested</th>
                <th>Resource</th>
                <th>View</th>
              </tr>
            </thead>
            <tbody id="schedule-request-body"></tbody>
          </table>
        </div>
      </div>
    </article>
  </section>

  <section class="schedule-detail-modal" id="schedule-detail-modal" aria-hidden="true">
    <div class="schedule-detail-overlay" data-close-schedule-detail="true"></div>
    <article class="schedule-detail-card" role="dialog" aria-modal="true" aria-labelledby="schedule-detail-title">
      <div class="schedule-detail-body">
        <h2 id="schedule-detail-title">Request Information</h2>

        <div class="schedule-detail-grid">
          <span>Name:</span>
          <span id="schedule-detail-name"></span>

          <span>Title of Activity:</span>
          <span id="schedule-detail-title-activity"></span>

          <span>Date(s) of Activity:</span>
          <span id="schedule-detail-date"></span>

          <span>Time of Activity:</span>
          <span id="schedule-detail-time"></span>

          <span>Expected Attendance:</span>
          <span id="schedule-detail-attendance"></span>

          <span>Type of Resource:</span>
          <span id="schedule-detail-resource"></span>
        </div>

        <div class="schedule-detail-extra-title">Other Resources Requested:</div>
        <div class="schedule-detail-extra-list">
          <div><i class="bi bi-chair"></i> Arm Chairs: <span id="schedule-detail-chairs">0</span></div>
          <div><i class="bi bi-table"></i> Tables: <span id="schedule-detail-tables">0</span></div>
        </div>

        <div class="schedule-detail-actions">
          <button type="button" class="schedule-detail-cancel" id="schedule-detail-cancel">Cancel</button>
        </div>
      </div>
    </article>
  </section>

  <script src="/js/dashboard.js"></script>
</body>
</html>

