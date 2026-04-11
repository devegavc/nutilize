<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Requests</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-requests.css" />
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

      <section class="content-card request-content-card">
        <h1 class="section-title">TEACHER REQUEST</h1>

        <section class="request-head">
          <div class="request-tabs" role="tablist" aria-label="Request status">
            <button class="request-tab active" type="button" data-request-tab="final">Final Approval</button>
            <button class="request-tab" type="button" data-request-tab="pending">Pending</button>
          </div>
          <div class="request-date-row">
            <span class="today">Today</span>
            <span class="date">March 8, 2025</span>
          </div>
        </section>

        <section class="request-list-wrap">
          <article class="request-item final-only" data-requester="Mr. Minesis">
            <div class="request-main-col">
              <div class="request-row-title">
                <strong>Current Request</strong>
                <span>#NU-2026-001</span>
                <span class="status-dots">
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                </span>
              </div>
              <p class="request-owner">Archie Mesis, School of Architecture Computing and Engineering</p>
              <p class="request-phone">0906 082 0723</p>
              <div class="request-event-row">
                <strong>Event Name</strong>
                <span>Mobile Legends Tournament</span>
              </div>
              <div class="request-meta-row">
                <span>Date: 21/02/2025</span>
                <span>Time: 7:00am - 4:30pm</span>
              </div>
            </div>

            <div class="request-side-event"><strong>Event Name</strong> Mobile Legends Tournament</div>

            <div class="request-resource-col">
              <h3>Requested resources</h3>
              <div class="resource-grid">
                <span><i class="bi bi-house-door-fill"></i> Room 501</span>
                <span><i class="bi bi-tv-fill"></i> 2 TV</span>
                <span><i class="bi bi-house-door-fill"></i> Room 502</span>
                <span><i class="bi bi-person-workspace"></i> 30 Chairs</span>
                <span><i class="bi bi-table"></i> 15 Table</span>
              </div>
              <div class="request-action-stack">
                <button class="approve-btn" type="button">Approve</button>
                <button class="reject-btn" type="button">Reject</button>
              </div>
              <div class="request-decision" aria-live="polite">
                <p class="request-decision-name">Mr. Minesis</p>
                <p class="request-decision-text">Has been</p>
                <span class="request-decision-badge">Approved</span>
              </div>
            </div>
          </article>

          <article class="request-item final-only" data-requester="Ms. Pastrana">
            <div class="request-main-col">
              <div class="request-row-title">
                <strong>Current Request</strong>
                <span>#NU-2026-002</span>
                <span class="status-dots">
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                </span>
              </div>
              <p class="request-owner">Jei Pastrana, School of Architecture Computing and Engineering</p>
              <p class="request-phone">0912 345 6789</p>
              <div class="request-event-row">
                <strong>Event Name</strong>
                <span>Event for IT</span>
              </div>
              <div class="request-meta-row">
                <span>Date: 9/02/2026</span>
                <span>Time: 8:00am - 3:00pm</span>
              </div>
            </div>
            <div class="request-side-event"><strong>Event Name</strong> Event for IT</div>

            <div class="request-resource-col">
              <h3>Requested resources</h3>
              <div class="resource-grid">
                <span><i class="bi bi-house-door-fill"></i> Room 527</span>
                <span><i class="bi bi-tv-fill"></i> 1 TV</span>
                <span><i class="bi bi-house-door-fill"></i> Room 528</span>
                <span><i class="bi bi-person-workspace"></i> 40 Chairs</span>
                <span><i class="bi bi-table"></i> 20 Table</span>
              </div>
              <div class="request-action-stack">
                <button class="approve-btn" type="button">Approve</button>
                <button class="reject-btn" type="button">Reject</button>
              </div>
              <div class="request-decision" aria-live="polite">
                <p class="request-decision-name">Ms. Pastrana</p>
                <p class="request-decision-text">Has been</p>
                <span class="request-decision-badge">Approved</span>
              </div>
            </div>
          </article>

          <article class="request-item final-only" data-requester="Mr. Enriquez">
            <div class="request-main-col">
              <div class="request-row-title">
                <strong>Current Request</strong>
                <span>#NU-2026-003</span>
                <span class="status-dots">
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                </span>
              </div>
              <p class="request-owner">Joel Enriquez, School of Architecture Computing and Engineering</p>
              <p class="request-phone">0998 765 4321</p>
              <div class="request-event-row">
                <strong>Event Name</strong>
                <span>PTA Meeting</span>
              </div>
              <div class="request-meta-row">
                <span>Date: 31/12/2025</span>
                <span>Time: 7:00am - 9:30am</span>
              </div>
            </div>

            <div class="request-side-event"><strong>Event Name</strong> PTA Meeting</div>

            <div class="request-resource-col">
              <h3>Requested resources</h3>
              <div class="resource-grid">
                <span><i class="bi bi-house-door-fill"></i> Room 632</span>
                <span><i class="bi bi-tv-fill"></i> 1 TV</span>
                <span><i class="bi bi-table"></i> 5 Table</span>
                <span><i class="bi bi-person-workspace"></i> 25 Chairs</span>
              </div>
              <div class="request-action-stack">
                <button class="approve-btn" type="button">Approve</button>
                <button class="reject-btn" type="button">Reject</button>
              </div>
              <div class="request-decision" aria-live="polite">
                <p class="request-decision-name">Mr. Enriquez</p>
                <p class="request-decision-text">Has been</p>
                <span class="request-decision-badge">Approved</span>
              </div>
            </div>
          </article>

          <article class="request-item pending-only" data-requester="Ms. Almeda">
            <div class="request-main-col">
              <div class="request-row-title">
                <strong>Current Request</strong>
                <span>#NU-2026-041</span>
                <span class="status-dots">
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-circle-fill"></i>
                  <i class="bi bi-circle-fill"></i>
                </span>
              </div>
              <p class="request-owner">Lara Almeda, Senior High School Department</p>
              <p class="request-phone">0917 441 1208</p>
              <div class="request-event-row">
                <strong>Event Name</strong>
                <span>Science Investigatory Defense</span>
              </div>
              <div class="request-meta-row">
                <span>Date: 14/03/2026</span>
                <span>Time: 9:00am - 1:00pm</span>
              </div>
            </div>

            <div class="request-side-event"><strong>Event Name</strong> Science Investigatory Defense</div>

            <div class="request-resource-col">
              <h3>Requested resources</h3>
              <div class="resource-grid">
                <span><i class="bi bi-house-door-fill"></i> Room 415</span>
                <span><i class="bi bi-tv-fill"></i> 1 TV</span>
                <span><i class="bi bi-person-workspace"></i> 35 Chairs</span>
                <span><i class="bi bi-table"></i> 12 Tables</span>
              </div>
              <div class="request-action-stack">
                <button class="approve-btn" type="button">Approve</button>
                <button class="reject-btn" type="button">Reject</button>
              </div>
              <div class="request-decision" aria-live="polite">
                <p class="request-decision-name">Ms. Almeda</p>
                <p class="request-decision-text">Has been</p>
                <span class="request-decision-badge">Approved</span>
              </div>
            </div>
          </article>

          <article class="request-item pending-only" data-requester="Mr. Dizon">
            <div class="request-main-col">
              <div class="request-row-title">
                <strong>Current Request</strong>
                <span>#NU-2026-044</span>
                <span class="status-dots">
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-circle-fill"></i>
                  <i class="bi bi-circle-fill"></i>
                  <i class="bi bi-circle-fill"></i>
                </span>
              </div>
              <p class="request-owner">Mark Dizon, College of Business and Accountancy</p>
              <p class="request-phone">0908 992 4331</p>
              <div class="request-event-row">
                <strong>Event Name</strong>
                <span>Startup Pitching Workshop</span>
              </div>
              <div class="request-meta-row">
                <span>Date: 18/03/2026</span>
                <span>Time: 10:00am - 5:00pm</span>
              </div>
            </div>

            <div class="request-side-event"><strong>Event Name</strong> Startup Pitching Workshop</div>

            <div class="request-resource-col">
              <h3>Requested resources</h3>
              <div class="resource-grid">
                <span><i class="bi bi-house-door-fill"></i> AVR Room</span>
                <span><i class="bi bi-tv-fill"></i> 2 TVs</span>
                <span><i class="bi bi-person-workspace"></i> 60 Chairs</span>
                <span><i class="bi bi-table"></i> 20 Tables</span>
              </div>
              <div class="request-action-stack">
                <button class="approve-btn" type="button">Approve</button>
                <button class="reject-btn" type="button">Reject</button>
              </div>
              <div class="request-decision" aria-live="polite">
                <p class="request-decision-name">Mr. Dizon</p>
                <p class="request-decision-text">Has been</p>
                <span class="request-decision-badge">Approved</span>
              </div>
            </div>
          </article>

          <article class="request-item pending-only" data-requester="Ms. Villafuerte">
            <div class="request-main-col">
              <div class="request-row-title">
                <strong>Current Request</strong>
                <span>#NU-2026-049</span>
                <span class="status-dots">
                  <i class="bi bi-check-circle-fill"></i>
                  <i class="bi bi-circle-fill"></i>
                  <i class="bi bi-circle-fill"></i>
                  <i class="bi bi-circle-fill"></i>
                  <i class="bi bi-circle-fill"></i>
                </span>
              </div>
              <p class="request-owner">Jasmine Villafuerte, School of Education</p>
              <p class="request-phone">0916 285 1130</p>
              <div class="request-event-row">
                <strong>Event Name</strong>
                <span>Demo Teaching Evaluation</span>
              </div>
              <div class="request-meta-row">
                <span>Date: 22/03/2026</span>
                <span>Time: 8:30am - 11:30am</span>
              </div>
            </div>

            <div class="request-side-event"><strong>Event Name</strong> Demo Teaching Evaluation</div>

            <div class="request-resource-col">
              <h3>Requested resources</h3>
              <div class="resource-grid">
                <span><i class="bi bi-house-door-fill"></i> Room 303</span>
                <span><i class="bi bi-tv-fill"></i> 1 TV</span>
                <span><i class="bi bi-person-workspace"></i> 28 Chairs</span>
                <span><i class="bi bi-table"></i> 10 Tables</span>
              </div>
              <div class="request-action-stack">
                <button class="approve-btn" type="button">Approve</button>
                <button class="reject-btn" type="button">Reject</button>
              </div>
              <div class="request-decision" aria-live="polite">
                <p class="request-decision-name">Ms. Villafuerte</p>
                <p class="request-decision-text">Has been</p>
                <span class="request-decision-badge">Approved</span>
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

