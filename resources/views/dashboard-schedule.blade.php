<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Schedule</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-schedule.css" />
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
    window.scheduleCalendarData = @json($scheduleCalendarData);
    window.scheduleMonthBaseUrl = '{{ route('dashboard.schedule') }}';
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
              <button class="month-nav-btn" type="button" aria-label="Previous month" onclick="window.location.href='{{ $previousMonthUrl }}'">
                <i class="bi bi-chevron-left"></i>
              </button>
              <h1>{{ $monthLabel }}</h1>
              <button class="month-nav-btn" type="button" aria-label="Next month" onclick="window.location.href='{{ $nextMonthUrl }}'">
                <i class="bi bi-chevron-right"></i>
              </button>
            </header>

            @php
              [$selectedYear, $selectedMonth] = explode('-', $monthKey);
              $selectedYear = (int) $selectedYear;
              $selectedMonth = (int) $selectedMonth;
            @endphp
            <div class="schedule-month-jump" aria-label="Jump to month">
              <label for="schedule-month-select">Jump to Month</label>
              <select id="schedule-month-select" aria-label="Select month">
                @foreach ([1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'] as $monthNumber => $monthName)
                  <option value="{{ str_pad((string) $monthNumber, 2, '0', STR_PAD_LEFT) }}" {{ $selectedMonth === $monthNumber ? 'selected' : '' }}>{{ $monthName }}</option>
                @endforeach
              </select>
              <select id="schedule-year-select" aria-label="Select year">
                @for ($year = $selectedYear - 3; $year <= $selectedYear + 3; $year++)
                  <option value="{{ $year }}" {{ $selectedYear === $year ? 'selected' : '' }}>{{ $year }}</option>
                @endfor
              </select>
            </div>

            <div class="schedule-legend">
              <span class="schedule-legend-dot"></span>
              Fully approved requests are highlighted in yellow.
            </div>

            <section class="calendar-grid-wrap">
              <div class="calendar-grid">
                <span class="day-label">Sunday</span>
                <span class="day-label">Monday</span>
                <span class="day-label">Tuesday</span>
                <span class="day-label">Wednesday</span>
                <span class="day-label">Thursday</span>
                <span class="day-label">Friday</span>
                <span class="day-label">Saturday</span>

                @foreach ($calendarCells as $cell)
                  @if (!empty($cell['blank']))
                    <span class="day day-empty" aria-hidden="true"></span>
                  @else
                    <span
                      class="day{{ !empty($cell['marked']) ? ' marked' : '' }}"
                      data-day="{{ $cell['day'] }}"
                      data-request-count="{{ $cell['request_count'] }}"
                      title="{{ $cell['request_count'] > 0 ? $cell['request_count'] . ' approved request(s)' : 'No approved requests' }}"
                    >
                      {{ $cell['day'] }}
                    </span>
                  @endif
                @endforeach
              </div>
            </section>
          </article>
        </section>

        <section class="schedule-inline-panel" id="schedule-inline-panel" aria-live="polite">
          <div class="schedule-inline-content">
            <div class="schedule-inline-table-wrap">
              <div class="schedule-inline-table-title">Selected Date Details</div>
              <div class="schedule-inline-table-meta" id="schedule-inline-date">Select a highlighted date to see approved requests and details below.</div>
              <table class="schedule-inline-table">
                <thead>
                  <tr>
                    <th>Reservation ID</th>
                    <th>Requester</th>
                    <th>Resources</th>
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
                <span>Requester:</span>
                <span id="schedule-inline-detail-requester">-</span>

                <span>Activity:</span>
                <span id="schedule-inline-detail-activity">-</span>

                <span>Date of Activity:</span>
                <span id="schedule-inline-detail-requested-on">-</span>

                <span>Requested Time:</span>
                <span id="schedule-inline-detail-requested-time">-</span>

                <span>Reservation ID:</span>
                <span id="schedule-inline-detail-reservation-code">-</span>

                <span>Status:</span>
                <span id="schedule-inline-detail-status">-</span>
              </div>

              <div class="schedule-inline-extra-title">Resources Requested:</div>
              <div class="schedule-inline-extra-list" id="schedule-inline-detail-resources">
                <div>No resource details available.</div>
              </div>

              <div class="schedule-inline-extra-title">Approval Trail:</div>
              <div class="schedule-inline-extra-list" id="schedule-inline-detail-approvals">
                <div>No approval trail available.</div>
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
        <div class="schedule-modal-date" id="schedule-modal-date">Date of Activity: --</div>
        <div class="schedule-modal-table-wrap">
          <table class="schedule-modal-table">
            <thead>
              <tr>
                <th>Reservation ID</th>
                <th>Requester</th>
                <th>Resources</th>
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
          <span>Requester:</span>
          <span id="schedule-detail-name"></span>

          <span>Activity:</span>
          <span id="schedule-detail-title-activity"></span>

          <span>Date of Activity:</span>
          <span id="schedule-detail-date"></span>

          <span>Requested Time:</span>
          <span id="schedule-detail-time"></span>

          <span>Reservation ID:</span>
          <span id="schedule-detail-attendance"></span>

          <span>Status:</span>
          <span id="schedule-detail-resource"></span>
        </div>

        <div class="schedule-detail-extra-title">Resources Requested:</div>
        <div class="schedule-detail-extra-list" id="schedule-detail-chairs">
          <div>No resource details available.</div>
        </div>

        <div class="schedule-detail-extra-title">Approval Trail:</div>
        <div class="schedule-detail-extra-list" id="schedule-detail-tables">
          <div>No approval trail available.</div>
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

