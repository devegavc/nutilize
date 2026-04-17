<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Office Home</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
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
    window.dashboardNavComponent = '/components/navbar-office.html';
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
        <h1 class="section-title">OFFICE APPROVAL DASHBOARD</h1>
        <p class="office-subtitle">{{ auth()->user()?->office?->department_name ?? 'Student Development Office' }}</p>

        <section class="stats-grid" aria-label="Request summary">
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-hourglass-split"></i></span>
            <div>
              <p class="stat-number">12</p>
              <p class="stat-label">Pending Request</p>
            </div>
          </article>
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-x-circle"></i></span>
            <div>
              <p class="stat-number">5</p>
              <p class="stat-label">Canceled Request</p>
            </div>
          </article>
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-check-circle"></i></span>
            <div>
              <p class="stat-number">8</p>
              <p class="stat-label">Approved</p>
            </div>
          </article>
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-inboxes-fill"></i></span>
            <div>
              <p class="stat-number">25</p>
              <p class="stat-label">Total Request</p>
            </div>
          </article>
        </section>

        <section class="middle-grid">
          <article class="quick-view">
            <div class="quick-view-header">
              <div class="quick-view-header-main">
                <i class="bi bi-journal-check"></i>
                <span>Reservations</span>
              </div>
              <div class="quick-view-controls">
                <div class="quick-control-group">
                  <label for="quick-view-date">Date:</label>
                  <div class="quick-control-shell is-date">
                    <i class="bi bi-calendar2-event"></i>
                    <input id="quick-view-date" type="text" value="{{ now()->toDateString() }}" data-default-date="{{ now()->toDateString() }}" />
                  </div>
                </div>

                <div class="quick-control-group">
                  <label for="quick-view-sort">Sort by:</label>
                  <div class="quick-control-shell is-sort">
                    <i class="bi bi-funnel"></i>
                    <input id="quick-view-sort" type="hidden" value="all" />
                    <button id="quick-view-sort-trigger" class="quick-sort-trigger" type="button" aria-haspopup="listbox" aria-expanded="false" aria-controls="quick-view-sort-menu">
                      <span class="quick-sort-label">All</span>
                    </button>
                    <span class="quick-control-chevron"><i class="bi bi-chevron-down"></i></span>
                    <div id="quick-view-sort-menu" class="quick-sort-menu" role="listbox" aria-label="Sort reservations">
                      <button type="button" class="quick-sort-option is-active" data-sort-value="all" role="option" aria-selected="true">All</button>
                      <button type="button" class="quick-sort-option" data-sort-value="approve" role="option">Approve</button>
                      <button type="button" class="quick-sort-option" data-sort-value="rejected" role="option">Rejected</button>
                      <button type="button" class="quick-sort-option" data-sort-value="pending" role="option">Pending</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="table-wrap">
              <table>
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
                  <tr class="office-reservation-row" data-request-name="Menesis, Archie" data-request-title="Faculty Meeting" data-request-date="April 2, 2025" data-request-time="1:00 PM - 3:00 PM" data-request-attendance="10 People" data-request-resource="Room 607" data-request-chairs="20" data-request-tables="5">
                    <td>2021-181217</td>
                    <td>Pastrana, John Paul</td>
                    <td>Rm 607</td>
                    <td>Faculty Meeting</td>
                    <td><span class="badge pending">Pending</span></td>
                  </tr>
                  <tr class="office-reservation-row" data-request-name="Cumpas, Josh Andrew" data-request-title="Film Showing" data-request-date="April 3, 2025" data-request-time="9:00 AM - 11:00 AM" data-request-attendance="35 People" data-request-resource="TV #03" data-request-chairs="30" data-request-tables="2">
                    <td>2022-171602</td>
                    <td>Cumpas, Josh Andrew</td>
                    <td>TV #03</td>
                    <td>Film Showing</td>
                    <td><span class="badge pending">Pending</span></td>
                  </tr>
                  <tr class="office-reservation-row" data-request-name="Cumpas, Josh Andrew" data-request-title="Film Showing" data-request-date="April 3, 2025" data-request-time="1:00 PM - 3:00 PM" data-request-attendance="42 People" data-request-resource="Projector #09" data-request-chairs="40" data-request-tables="4">
                    <td>2023-192003</td>
                    <td>Cumpas, Josh Andrew</td>
                    <td>Projector #09</td>
                    <td>Film Showing</td>
                    <td><span class="badge pending">Pending</span></td>
                  </tr>
                  <tr class="office-reservation-row" data-request-name="Nadal, Kitchie" data-request-title="Group Study" data-request-date="April 4, 2025" data-request-time="10:00 AM - 12:00 PM" data-request-attendance="12 People" data-request-resource="Room 508" data-request-chairs="12" data-request-tables="2">
                    <td>2021-10027</td>
                    <td>Nadal, Kitchie</td>
                    <td>Rm 508</td>
                    <td>Group Study</td>
                    <td><span class="badge pending">Pending</span></td>
                  </tr>
                  <tr class="office-reservation-row" data-request-name="Velasquez, Regine" data-request-title="Flag Ceremony" data-request-date="April 5, 2025" data-request-time="7:00 AM - 8:30 AM" data-request-attendance="120 People" data-request-resource="Speaker #32" data-request-chairs="0" data-request-tables="0">
                    <td>2024-101611</td>
                    <td>Velasquez, Regine</td>
                    <td>Speaker #32</td>
                    <td>Flag Ceremony</td>
                    <td><span class="badge solved">Approve</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </article>
        </section>
      </section>
    </section>
  </main>

  <section class="office-request-modal" id="office-request-modal" aria-hidden="true">
    <div class="office-request-modal-overlay" data-close-office-request-modal="true"></div>
    <article class="office-request-modal-card" role="dialog" aria-modal="true" aria-labelledby="office-request-modal-title">
      <div class="office-request-modal-body">
        <h2 id="office-request-modal-title">More Information</h2>

        <div class="office-request-grid">
          <span>Name:</span>
          <span id="office-request-name"></span>

          <span>Title of Activity:</span>
          <span id="office-request-title"></span>

          <span>Date(s) of Activity:</span>
          <span id="office-request-date"></span>

          <span>Time of Activity:</span>
          <span id="office-request-time"></span>

          <span>Expected Attendance:</span>
          <span id="office-request-attendance"></span>

          <span>Type of Resource:</span>
          <span id="office-request-resource"></span>
        </div>

        <div class="office-request-extra-title">Other Resources Requested:</div>
        <div class="office-request-extra-list">
          <div><i class="bi bi-chair"></i> <strong>Arm Chairs:</strong> <span id="office-request-chairs"></span></div>
          <div><i class="bi bi-table"></i> <strong>Tables:</strong> <span id="office-request-tables"></span></div>
        </div>

        <label class="office-request-note-label" for="office-request-note">Additional notes <span>(optional)</span></label>
        <textarea id="office-request-note" rows="3" placeholder="Write notes here..."></textarea>

        <div class="office-request-actions">
          <button class="office-modal-btn cancel" id="office-request-cancel" type="button">Cancel</button>
          <div class="office-request-primary-actions">
            <button class="office-modal-btn reject" id="office-request-reject" type="button">Reject</button>
            <button class="office-modal-btn approve" id="office-request-approve" type="button">Approve</button>
          </div>
        </div>
      </div>
    </article>
  </section>

  <section class="office-approve-confirm-modal" id="office-approve-confirm-modal" aria-hidden="true">
    <div class="office-approve-confirm-overlay" data-close-office-approve-confirm="true"></div>
    <article class="office-approve-confirm-card" role="dialog" aria-modal="true" aria-labelledby="office-approve-confirm-title">
      <header class="office-approve-confirm-head">
        <h2 id="office-approve-confirm-title">Confirm Approval</h2>
      </header>
      <div class="office-approve-confirm-body">
        <p>Are you sure you want to approve this reservation request?<br>This action cannot be undone.</p>
      </div>
      <footer class="office-approve-confirm-actions">
        <button type="button" class="office-modal-btn cancel" id="office-approve-confirm-cancel">Cancel</button>
        <button type="button" class="office-modal-btn approve" id="office-approve-confirm-approve">Approve</button>
      </footer>
    </article>
  </section>

  <section class="office-approve-feedback-modal" id="office-approve-feedback-modal" aria-hidden="true">
    <div class="office-approve-feedback-overlay"></div>
    <article class="office-approve-feedback-card" role="dialog" aria-modal="true" aria-labelledby="office-approve-feedback-title">
      <header class="office-approve-feedback-head">
        <h2 id="office-approve-feedback-title">Feedback</h2>
      </header>
      <div class="office-approve-feedback-body">
        <div class="office-approve-feedback-icon"><i class="bi bi-check-lg"></i></div>
        <h3>Request Approved</h3>
        <p>The request has been successfully approved.</p>
        <button type="button" class="office-approve-feedback-finish" id="office-approve-feedback-finish">Finish</button>
      </div>
    </article>
  </section>

  <section class="office-reject-reason-modal" id="office-reject-reason-modal" aria-hidden="true">
    <div class="office-reject-reason-overlay" data-close-office-reject-reason="true"></div>
    <article class="office-reject-reason-card" role="dialog" aria-modal="true" aria-labelledby="office-reject-reason-title">
      <header class="office-reject-reason-head">
        <h2 id="office-reject-reason-title">Reject Reservation</h2>
      </header>
      <div class="office-reject-reason-body">
        <p class="office-reject-reason-label">Reason for rejection: <span>(required)</span></p>
        <div class="office-reject-reason-options" role="listbox" aria-label="Reject reason choices">
          <button type="button" class="office-reject-reason-option" data-reject-reason="Room unavailable">Room unavailable</button>
          <button type="button" class="office-reject-reason-option" data-reject-reason="Time conflict">Time conflict</button>
          <button type="button" class="office-reject-reason-option" data-reject-reason="Insufficient resources">Insufficient resources</button>
          <button type="button" class="office-reject-reason-option" data-reject-reason="Others">Others</button>
        </div>
        <div class="office-reject-other-wrap" id="office-reject-other-wrap" hidden>
          <input type="text" id="office-reject-other-input" placeholder="Enter other reasons here" maxlength="120" />
        </div>
      </div>
      <footer class="office-reject-reason-actions">
        <button type="button" class="office-modal-btn cancel" id="office-reject-reason-cancel">Cancel</button>
        <button type="button" class="office-modal-btn reject" id="office-reject-reason-confirm" disabled>Reject</button>
      </footer>
    </article>
  </section>

  <section class="office-reject-feedback-modal" id="office-reject-feedback-modal" aria-hidden="true">
    <div class="office-reject-feedback-overlay"></div>
    <article class="office-reject-feedback-card" role="dialog" aria-modal="true" aria-labelledby="office-reject-feedback-title">
      <header class="office-reject-feedback-head">
        <h2 id="office-reject-feedback-title">Feedback</h2>
      </header>
      <div class="office-reject-feedback-body">
        <div class="office-reject-feedback-icon"><i class="bi bi-x-lg"></i></div>
        <h3>Request Rejected</h3>
        <p>You have successfully rejected the request</p>
        <button type="button" class="office-reject-feedback-finish" id="office-reject-feedback-finish">Finish</button>
      </div>
    </article>
  </section>

  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="/js/dashboard.js"></script>
</body>
</html>
