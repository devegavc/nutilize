<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Office History</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/dashboard.css?v={{ filemtime(public_path('css/dashboard.css')) }}" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
  <link rel="stylesheet" href="/css/office.css?v={{ filemtime(public_path('css/office.css')) }}" />
</head>
<body class="office-app">
  <script>
    window.authUser = {
      id: @json(auth()->user()->user_id),
      username: @json(auth()->user()->username ?? 'User'),
      email: @json(auth()->user()->email ?? ''),
      full_name: @json(auth()->user()->full_name ?? auth()->user()->username ?? 'User'),
      role: @json(auth()->user()->role ?? 'user'),
      office_name: @json(auth()->user()?->office?->department_name ?? 'Office'),
      office_short_code: @json(auth()->user()?->office?->short_code ?? ''),
      is_item_owner: @json(auth()->user()?->isItemOwnerAdmin() ?? false)
    };
    window.dashboardNavComponent = '/components/navbar-office.html';
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

          @php
            $historyTotal = 0;
            $historyServerPerPage = 20;
            $historyServerPage = 1;
            if (($historyRows ?? null) instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
              $historyTotal = $historyRows->total();
              $historyServerPerPage = $historyRows->perPage();
              $historyServerPage = $historyRows->currentPage();
            }
          @endphp
          <div
            class="office-request-pagination"
            id="office-history-pagination"
            data-total="{{ $historyTotal }}"
            data-server-per-page="{{ $historyServerPerPage }}"
            data-server-page="{{ $historyServerPage }}"
            @if($historyTotal <= 50) hidden @endif
          >
            @if($historyTotal > 0)
              {{ $historyRows->links() }}
            @endif
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
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const UI_PAGE_SIZE = 50;
      const pagination = document.getElementById('office-history-pagination');
      const tbody = document.querySelector('.office-archive-table tbody');

      if (!pagination || !tbody) {
        return;
      }

      const total = Number(pagination.dataset.total || 0);
      const serverPerPage = Number(pagination.dataset.serverPerPage || 20);
      const serverPage = Number(pagination.dataset.serverPage || 1);

      if (!total) {
        pagination.hidden = true;
        pagination.replaceChildren();
        return;
      }

      const dataRows = function (root) {
        return Array.from(root.querySelectorAll('tr')).filter(function (row) {
          return !row.querySelector('td[colspan]');
        });
      };

      const currentRows = dataRows(tbody).map(function (row) {
        return row.cloneNode(true);
      });

      const serverPageUrl = function (page) {
        const url = new URL(window.location.href);
        url.searchParams.delete('hp');
        if (page <= 1) {
          url.searchParams.delete('page');
        } else {
          url.searchParams.set('page', String(page));
        }
        return url.toString();
      };

      const uiPageUrl = function (page) {
        const url = new URL(window.location.href);
        url.searchParams.delete('page');
        if (page <= 1) {
          url.searchParams.delete('hp');
        } else {
          url.searchParams.set('hp', String(page));
        }
        return url.pathname + url.search;
      };

      const rowsForServerPage = async function (page) {
        if (page === serverPage) {
          return currentRows;
        }

        const response = await fetch(serverPageUrl(page), {
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) {
          throw new Error('Unable to load history page ' + page);
        }

        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const fetchedBody = doc.querySelector('.office-archive-table tbody');
        return fetchedBody ? dataRows(fetchedBody) : [];
      };

      const rowsForRange = async function (start, end) {
        const firstServerPage = Math.floor((start - 1) / serverPerPage) + 1;
        const lastServerPage = Math.floor((end - 1) / serverPerPage) + 1;
        const pages = [];

        for (let page = firstServerPage; page <= lastServerPage; page += 1) {
          pages.push(page);
        }

        const chunks = await Promise.all(pages.map(rowsForServerPage));
        const combined = chunks.flat();
        const offset = (start - 1) - ((firstServerPage - 1) * serverPerPage);
        return combined.slice(offset, offset + (end - start + 1));
      };

      const renderRows = function (rows) {
        tbody.replaceChildren.apply(tbody, rows.map(function (row) {
          return document.importNode(row, true);
        }));
      };

      const renderPager = function (start, end, uiPage, uiPages) {
        const previous = uiPage > 1
          ? '<a href="' + uiPageUrl(uiPage - 1) + '" rel="prev">&laquo; Previous</a>'
          : '<span aria-disabled="true">&laquo; Previous</span>';
        const next = uiPage < uiPages
          ? '<a href="' + uiPageUrl(uiPage + 1) + '" rel="next">Next &raquo;</a>'
          : '<span aria-disabled="true">Next &raquo;</span>';

        pagination.hidden = false;
        pagination.innerHTML = ''
          + '<nav role="navigation" aria-label="Pagination Navigation">'
          + '<div>' + previous + ' ' + next + '</div>'
          + '<p>Showing <span class="font-medium">' + start + '</span> to <span class="font-medium">' + end + '</span> of <span class="font-medium">' + total + '</span> results</p>'
          + '</nav>';
      };

      const uiPages = Math.ceil(total / UI_PAGE_SIZE);
      const requestedPage = Number(new URL(window.location.href).searchParams.get('hp') || 1);
      const uiPage = Math.min(Math.max(requestedPage, 1), uiPages);
      const start = total <= UI_PAGE_SIZE ? 1 : ((uiPage - 1) * UI_PAGE_SIZE) + 1;
      const end = total <= UI_PAGE_SIZE ? total : Math.min(uiPage * UI_PAGE_SIZE, total);

      rowsForRange(start, end).then(function (rows) {
        const expected = end - start + 1;

        if (rows.length < expected) {
          if (total <= UI_PAGE_SIZE) {
            pagination.hidden = false;
          }
          return;
        }

        renderRows(rows);

        if (total <= UI_PAGE_SIZE) {
          pagination.hidden = true;
          pagination.replaceChildren();
          return;
        }

        renderPager(start, end, uiPage, uiPages);
      }).catch(function () {
        if (total <= UI_PAGE_SIZE) {
          pagination.hidden = false;
        }
      });
    });
  </script>
  <script src="/js/dashboard.js?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
</body>
</html>
