<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Office History</title>

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
        <input id="dashboard-search" type="text" placeholder="Search history records" />
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

      <section class="content-card office-archive-card">
        <h1 class="section-title">OFFICE HISTORY DASHBOARD</h1>
        <p class="office-subtitle">Approval and rejection transaction history for your office.</p>

        <section class="office-archive-overview" aria-label="History summary">
          <article class="office-archive-tile">
            <span class="office-archive-icon"><i class="bi bi-archive-fill"></i></span>
            <div>
              <p class="office-archive-value">{{ $totalTransactions ?? 0 }}</p>
              <p class="office-archive-label">Total Transactions</p>
            </div>
          </article>
          <article class="office-archive-tile">
            <span class="office-archive-icon"><i class="bi bi-trash3-fill"></i></span>
            <div>
              <p class="office-archive-value">{{ $approvedCount ?? 0 }}</p>
              <p class="office-archive-label">Approved</p>
            </div>
          </article>
          <article class="office-archive-tile">
            <span class="office-archive-icon"><i class="bi bi-shield-check"></i></span>
            <div>
              <p class="office-archive-value">{{ $rejectedCount ?? 0 }}</p>
              <p class="office-archive-label">Rejected</p>
            </div>
          </article>
          <article class="office-archive-tile">
            <span class="office-archive-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
            <div>
              <p class="office-archive-value">{{ $todayCount ?? 0 }}</p>
              <p class="office-archive-label">Processed Today</p>
            </div>
          </article>
        </section>

        <section class="office-archive-history-card" aria-label="Office approval transaction history table">
          <header class="office-archive-history-head">
            <h2>Approval Transaction History</h2>
            <div class="office-archive-legend">
              <span class="office-archive-legend-approved">Approved</span>
              <span class="office-archive-legend-rejected">Rejected</span>
            </div>
          </header>

          <form method="GET" action="{{ route('office.history') }}" class="office-history-filters">
            <div class="office-history-filter-group office-history-filter-group-decision">
              <label for="decision">Decision</label>
              @php
                $decisionValue = strtolower((string) ($selectedDecision ?? 'all'));
                $decisionLabelMap = [
                  'all' => 'All',
                  'approved' => 'Approved',
                  'rejected' => 'Rejected',
                ];
                $decisionLabel = $decisionLabelMap[$decisionValue] ?? 'All';
              @endphp
              <div class="office-history-decision js-history-decision" data-initial-value="{{ $decisionValue }}">
                <input id="decision" type="hidden" name="decision" value="{{ $decisionValue }}" />
                <button type="button" class="office-history-decision-trigger" aria-haspopup="listbox" aria-expanded="false" aria-controls="office-history-decision-menu">
                  <span class="office-history-decision-value">{{ $decisionLabel }}</span>
                  <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </button>
                <div id="office-history-decision-menu" class="office-history-decision-menu" role="listbox" aria-label="Decision options">
                  <button type="button" class="office-history-decision-option" role="option" data-value="all">All</button>
                  <button type="button" class="office-history-decision-option" role="option" data-value="approved">Approved</button>
                  <button type="button" class="office-history-decision-option" role="option" data-value="rejected">Rejected</button>
                </div>
              </div>
            </div>

            <div class="office-history-filter-group office-history-filter-group-from">
              <label for="from_date">From</label>
              <div class="office-history-date-field">
                <input id="from_date" type="text" name="from_date" class="office-history-filter-control js-history-date" value="{{ $selectedFromDate ?? '' }}" placeholder="mm/dd/yyyy" autocomplete="off" />
                <span class="office-history-date-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
              </div>
            </div>

            <div class="office-history-filter-group office-history-filter-group-to">
              <label for="to_date">To</label>
              <div class="office-history-date-field">
                <input id="to_date" type="text" name="to_date" class="office-history-filter-control js-history-date" value="{{ $selectedToDate ?? '' }}" placeholder="mm/dd/yyyy" autocomplete="off" />
                <span class="office-history-date-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
              </div>
            </div>

            <div class="office-history-filter-actions">
              <button type="submit" class="office-history-filter-submit">Apply Filters</button>
              <a href="{{ route('office.history') }}" class="office-history-filter-clear">Clear</a>
            </div>
          </form>

          <div class="table-wrap office-archive-wrap">
            <table class="office-archive-table">
              <thead>
                <tr>
                  <th>Request ID</th>
                  <th>Requested By</th>
                  <th>Resource</th>
                  <th>Processed At</th>
                  <th>Processed By</th>
                  <th>Activity</th>
                  <th>Decision</th>
                </tr>
              </thead>
              <tbody>
                @forelse(($historyRows ?? []) as $record)
                  <tr>
                    <td>{{ $record['request_id'] }}</td>
                    <td>{{ $record['requested_by'] }}</td>
                    <td>{{ $record['resource'] }}</td>
                    <td>{{ $record['processed_at'] }}</td>
                    <td>{{ $record['processed_by'] }}</td>
                    <td>{{ $record['reason'] }}</td>
                    <td>
                      @php
                        $decisionClass = strtolower($record['decision']) === 'approved' ? 'approved' : 'rejected';
                      @endphp
                      <span class="archive-status {{ $decisionClass }}">{{ $record['decision'] }}</span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7">No approval transactions yet for this office.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="office-request-pagination">
            {{ ($historyRows ?? null)?->links() }}
          </div>
        </section>
      </section>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (!window.flatpickr) {
        return;
      }

      const today = new Date();
      const fromInput = document.getElementById('from_date');
      const toInput = document.getElementById('to_date');

      const fromPicker = fromInput
        ? flatpickr(fromInput, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'm/d/Y',
            allowInput: false,
            disableMobile: true,
            maxDate: today,
          })
        : null;

      const toPicker = toInput
        ? flatpickr(toInput, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'm/d/Y',
            allowInput: false,
            disableMobile: true,
            maxDate: today,
          })
        : null;

      if (fromPicker && toPicker) {
        const syncToPickerMinDate = function () {
          const selectedFromDate = fromPicker.selectedDates[0] || null;
          toPicker.set('minDate', selectedFromDate);

          const selectedToDate = toPicker.selectedDates[0] || null;
          if (selectedFromDate && selectedToDate && selectedToDate < selectedFromDate) {
            toPicker.clear();
          }
        };

        syncToPickerMinDate();

        fromPicker.config.onChange.push(function () {
          syncToPickerMinDate();
        });
      }

      const decisionPicker = document.querySelector('.js-history-decision');

      if (decisionPicker) {
        const hiddenInput = decisionPicker.querySelector('input[name="decision"]');
        const trigger = decisionPicker.querySelector('.office-history-decision-trigger');
        const valueLabel = decisionPicker.querySelector('.office-history-decision-value');
        const options = decisionPicker.querySelectorAll('.office-history-decision-option');

        const setValue = function (value, label) {
          if (hiddenInput) {
            hiddenInput.value = value;
          }

          if (valueLabel) {
            valueLabel.textContent = label;
          }

          options.forEach(function (option) {
            option.classList.toggle('is-selected', option.dataset.value === value);
            option.setAttribute('aria-selected', option.dataset.value === value ? 'true' : 'false');
          });
        };

        const openMenu = function () {
          decisionPicker.classList.add('is-open');
          if (trigger) {
            trigger.setAttribute('aria-expanded', 'true');
          }
        };

        const closeMenu = function () {
          decisionPicker.classList.remove('is-open');
          if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
          }
        };

        if (trigger) {
          trigger.addEventListener('click', function () {
            if (decisionPicker.classList.contains('is-open')) {
              closeMenu();
            } else {
              openMenu();
            }
          });
        }

        options.forEach(function (option) {
          option.addEventListener('click', function () {
            setValue(option.dataset.value || 'all', option.textContent || 'All');
            closeMenu();
          });
        });

        document.addEventListener('click', function (event) {
          if (!decisionPicker.contains(event.target)) {
            closeMenu();
          }
        });

        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') {
            closeMenu();
          }
        });

        setValue(decisionPicker.dataset.initialValue || 'all', valueLabel ? valueLabel.textContent : 'All');
      }
    });
  </script>
  <script src="/js/dashboard.js"></script>
</body>
</html>
