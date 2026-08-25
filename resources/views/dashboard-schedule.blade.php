<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Schedule</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-schedule.css?v={{ filemtime(public_path('css/db-schedule.css')) }}" />
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
      @include('partials.dashboard-navbar')

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
            @php
              [$selectedYear, $selectedMonth] = explode('-', $monthKey);
              $selectedYear = (int) $selectedYear;
              $selectedMonth = (int) $selectedMonth;
              $monthReservationCount = collect($calendarCells)->sum(fn ($cell) => (int) ($cell['request_count'] ?? 0));
              $isCurrentMonth = $monthKey === now()->format('Y-m');
              $todayDay = (int) now()->day;
            @endphp
            <header class="schedule-month-row">
              <button class="month-nav-btn" type="button" aria-label="Previous month" onclick="window.location.href='{{ $previousMonthUrl }}'">
                <i class="bi bi-chevron-left"></i>
              </button>
              <div class="schedule-month-heading">
                <h1>
                  <button
                    class="calendar-month-title"
                    id="schedule-month-title"
                    type="button"
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-controls="schedule-month-picker"
                  >
                    <span class="calendar-month-title-text">{{ $monthLabel }}</span>
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                  </button>
                </h1>
                <p class="schedule-month-summary" id="schedule-month-summary">{{ $monthReservationCount }} {{ $monthReservationCount === 1 ? 'reservation' : 'reservations' }} scheduled</p>
                <div class="schedule-month-picker" id="schedule-month-picker" hidden>
                  <p class="schedule-month-picker-label">Jump to month</p>
                  <div class="schedule-month-picker-fields">
                    <label class="schedule-month-field" for="schedule-month-select">
                      <span>Month</span>
                      <select id="schedule-month-select" aria-label="Select month">
                        @foreach ([1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'] as $monthNumber => $monthName)
                          <option value="{{ str_pad((string) $monthNumber, 2, '0', STR_PAD_LEFT) }}" {{ $selectedMonth === $monthNumber ? 'selected' : '' }}>{{ $monthName }}</option>
                        @endforeach
                      </select>
                    </label>
                    <label class="schedule-month-field" for="schedule-year-select">
                      <span>Year</span>
                      <select id="schedule-year-select" aria-label="Select year">
                        @for ($year = $selectedYear - 3; $year <= $selectedYear + 3; $year++)
                          <option value="{{ $year }}" {{ $selectedYear === $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endfor
                      </select>
                    </label>
                  </div>
                </div>
              </div>
              <button class="month-nav-btn" type="button" aria-label="Next month" onclick="window.location.href='{{ $nextMonthUrl }}'">
                <i class="bi bi-chevron-right"></i>
              </button>
            </header>

            <div class="schedule-calendar-toolbar">
              <button class="schedule-today-btn" id="schedule-today-btn" type="button">Today</button>

              <div class="schedule-legend" aria-label="Calendar legend">
                <span class="schedule-legend-item">
                  <span class="schedule-legend-dot is-reservation" aria-hidden="true"></span>
                  Has Reservations
                </span>
                <span class="schedule-legend-item">
                  <span class="schedule-legend-dot is-approved" aria-hidden="true"></span>
                  Fully Approved
                </span>
                <span class="schedule-legend-item">
                  <span class="schedule-legend-dot is-empty" aria-hidden="true"></span>
                  No Reservations
                </span>
              </div>
            </div>

            <section class="calendar-grid-wrap">
              <div class="calendar-grid">
                <span class="day-label">Sun</span>
                <span class="day-label">Mon</span>
                <span class="day-label">Tue</span>
                <span class="day-label">Wed</span>
                <span class="day-label">Thu</span>
                <span class="day-label">Fri</span>
                <span class="day-label">Sat</span>

                @foreach ($calendarCells as $cell)
                  @if (!empty($cell['blank']))
                    <span class="day day-empty" aria-hidden="true"></span>
                  @else
                    <span
                      class="day{{ !empty($cell['marked']) ? ' marked' : '' }}{{ $isCurrentMonth && (int) $cell['day'] === $todayDay ? ' today' : '' }}"
                      data-day="{{ $cell['day'] }}"
                      data-request-count="{{ $cell['request_count'] }}"
                      title="{{ $cell['request_count'] > 0 ? $cell['request_count'] . ' approved request(s)' : 'No approved requests' }}"
                    >
                      <span class="day-number">{{ $cell['day'] }}</span>
                      <span class="day-indicators" aria-hidden="true">
                        @for ($dotIndex = 0; $dotIndex < min(3, (int) $cell['request_count']); $dotIndex++)
                          <span class="day-indicator"></span>
                        @endfor
                      </span>
                    </span>
                  @endif
                @endforeach
              </div>
            </section>
          </article>
        </section>

        <section class="schedule-inline-panel" id="schedule-inline-panel" hidden aria-live="polite">
          <div class="schedule-inline-content">
            <div class="schedule-inline-table-wrap">
              <div class="schedule-inline-table-title">Selected Date Details</div>
              <div class="schedule-inline-date-summary">
                <div class="schedule-inline-date-heading" id="schedule-inline-date">Select a highlighted date to see approved requests and details below.</div>
                <div class="schedule-inline-date-stats" id="schedule-inline-date-stats" hidden>
                  <span class="schedule-inline-date-stat" id="schedule-inline-stat-reservations">0 Reservations</span>
                  <span class="schedule-inline-date-stat" id="schedule-inline-stat-resources">0 Resources</span>
                  <span class="schedule-inline-date-stat" id="schedule-inline-stat-approved">0 Fully Approved</span>
                </div>
              </div>
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

              <div class="schedule-inline-detail-groups">
                <div class="schedule-inline-detail-group">
                  <span class="schedule-inline-detail-group-label">Requester</span>
                  <span class="schedule-inline-detail-group-value" id="schedule-inline-detail-requester">-</span>
                </div>

                <div class="schedule-inline-detail-group">
                  <span class="schedule-inline-detail-group-label">Activity</span>
                  <span class="schedule-inline-detail-group-value" id="schedule-inline-detail-activity">-</span>
                </div>

                <div class="schedule-inline-detail-group">
                  <span class="schedule-inline-detail-group-label">Date &amp; Time</span>
                  <div class="schedule-inline-detail-group-stack">
                    <span class="schedule-inline-detail-group-value" id="schedule-inline-detail-requested-on">-</span>
                    <span class="schedule-inline-detail-group-value" id="schedule-inline-detail-requested-time">-</span>
                  </div>
                </div>

                <div class="schedule-inline-detail-group">
                  <span class="schedule-inline-detail-group-label">Reservation</span>
                  <span class="schedule-inline-detail-group-value" id="schedule-inline-detail-reservation-code">-</span>
                </div>

                <div class="schedule-inline-detail-group">
                  <span class="schedule-inline-detail-group-label">Status</span>
                  <span id="schedule-inline-detail-status">-</span>
                </div>

                <div class="schedule-inline-detail-group">
                  <span class="schedule-inline-detail-group-label">Resources</span>
                  <div class="schedule-inline-extra-list" id="schedule-inline-detail-resources">
                    <div>No resource details available.</div>
                  </div>
                </div>

                <div class="schedule-inline-detail-group">
                  <span class="schedule-inline-detail-group-label">Approval Trail</span>
                  <div class="schedule-inline-extra-list" id="schedule-inline-detail-approvals">
                    <div>No approval trail available.</div>
                  </div>
                </div>
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
      <header class="schedule-modal-header">
        <div class="schedule-modal-header-icon"><i class="bi bi-calendar-check"></i></div>
        <div class="schedule-modal-header-text">
          <span class="schedule-modal-header-label">Approved Requests</span>
          <span class="schedule-modal-date" id="schedule-modal-date">--</span>
        </div>
        <button class="schedule-modal-close" data-close-schedule-modal="true" aria-label="Close">
          <i class="bi bi-x-lg"></i>
        </button>
      </header>
      <div class="schedule-modal-body">
        <div class="schedule-modal-table-wrap">
          <table class="schedule-modal-table">
            <thead>
              <tr>
                <th>Reservation ID</th>
                <th>Requester</th>
                <th>Resources</th>
                <th></th>
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
  <script>
    // #region agent log
    fetch('http://127.0.0.1:7591/ingest/35e57a72-783b-42fe-bb4e-563f8b0a56b3',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'e19b10'},body:JSON.stringify({sessionId:'e19b10',location:'dashboard-schedule.blade.php',message:'schedule page rendered',data:@json($_agentDebug ?? ['page'=>'schedule','missing'=>true]),timestamp:Date.now(),hypothesisId:'E',runId:'pre-fix'})}).catch(function(){});
    // #endregion
  </script>
</body>
</html>

