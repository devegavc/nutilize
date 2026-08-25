<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Inventory Insights</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-inventory.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="page-insights">
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

      <section class="content-card analytics-content-card">
        <div class="dashboard-page-header-top insights-header-top">
          <div class="insights-header-copy">
            <h1 class="section-title">INSIGHTS DASHBOARD</h1>
          </div>

          @php
            [$selectedYear, $selectedMonthNum] = explode('-', $monthKey);
            $selectedYear = (int) $selectedYear;
            $selectedMonthNum = (int) $selectedMonthNum;
          @endphp
          <div class="insights-month-jump" aria-label="Jump to month">
            <label for="analytics-month-select">Jump to</label>
            <select id="analytics-month-select" aria-label="Select month">
              @foreach ([1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'] as $monthNumber => $monthName)
                <option value="{{ str_pad((string) $monthNumber, 2, '0', STR_PAD_LEFT) }}" {{ $selectedMonthNum === $monthNumber ? 'selected' : '' }}>{{ $monthName }}</option>
              @endforeach
            </select>
            <select id="analytics-year-select" aria-label="Select year">
              @for ($year = $selectedYear - 3; $year <= $selectedYear + 1; $year++)
                <option value="{{ $year }}" {{ $selectedYear === $year ? 'selected' : '' }}>{{ $year }}</option>
              @endfor
            </select>
          </div>
        </div>

        {{-- Month comparison + trend side by side --}}
        <section class="insight-charts-row">
          <article class="insight-panel insight-mom-chart-panel">
            <div class="insight-mom-head">
              <div>
                <h2 class="insight-card-title">Comparing Months</h2>
                <p class="insight-panel-hint">{{ $monthComparison['currentLabel'] }} vs {{ $monthComparison['compareLabel'] }}</p>
              </div>

              @php
                [$compareYear, $compareMonthNum] = explode('-', $compareMonthKey);
                $compareYear = (int) $compareYear;
                $compareMonthNum = (int) $compareMonthNum;
              @endphp
              <div class="insights-compare-picker" aria-label="Select month to compare with">
                <span class="insights-compare-label">Compare with</span>
                <select id="analytics-compare-month" aria-label="Compare month">
                  @foreach ([1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'] as $monthNumber => $monthName)
                    <option value="{{ str_pad((string) $monthNumber, 2, '0', STR_PAD_LEFT) }}" {{ $compareMonthNum === $monthNumber ? 'selected' : '' }}>{{ $monthName }}</option>
                  @endforeach
                </select>
                <select id="analytics-compare-year" aria-label="Compare year">
                  @for ($year = $compareYear - 3; $year <= $compareYear + 1; $year++)
                    <option value="{{ $year }}" {{ $compareYear === $year ? 'selected' : '' }}>{{ $year }}</option>
                  @endfor
                </select>
              </div>
            </div>
            <div class="insight-chart-frame insight-mom-frame">
              <canvas id="monthComparisonChart"></canvas>
            </div>
          </article>

          <article class="insight-panel">
            <h3 class="insight-card-title">12-Month Booking Trend</h3>
            <p class="insight-panel-hint">Approved reservations ending {{ $monthLabel }}</p>
            <div class="insight-chart-frame insight-trend-frame">
              <canvas id="monthlyTrendChart"></canvas>
            </div>
          </article>
        </section>

        {{-- Procurement signal strip --}}
        <section class="insight-kpi-strip" aria-label="Procurement signals">
          <button
            type="button"
            class="insight-kpi insight-kpi-critical {{ $restockSummary['critical'] > 0 ? 'is-critical' : '' }}"
            data-insight-filter="critical"
            aria-pressed="false"
          >
            <span class="insight-kpi-icon"><i class="bi bi-exclamation-octagon-fill"></i></span>
            <div class="insight-kpi-body">
              <strong>{{ $restockSummary['critical'] }}</strong>
              <span class="insight-kpi-label">Critical shortages</span>
              <em class="insight-kpi-hint">{{ $restockSummary['critical_hint'] ?? 'none right now' }}</em>
            </div>
          </button>

          <button
            type="button"
            class="insight-kpi insight-kpi-procure {{ $restockSummary['units_to_procure'] > 0 ? 'is-action' : '' }}"
            data-insight-filter="procure"
            aria-pressed="false"
          >
            <span class="insight-kpi-icon"><i class="bi bi-cart-plus-fill"></i></span>
            <div class="insight-kpi-body">
              <strong>+{{ $restockSummary['units_to_procure'] }}</strong>
              <span class="insight-kpi-label">Buy plan (units)</span>
              <em class="insight-kpi-hint">{{ $restockSummary['procure_hint'] ?? '' }}</em>
            </div>
          </button>

          <button
            type="button"
            class="insight-kpi insight-kpi-gap"
            data-insight-filter="unmet"
            aria-pressed="false"
          >
            <span class="insight-kpi-icon"><i class="bi bi-clipboard-data"></i></span>
            <div class="insight-kpi-body">
              <strong>{{ $restockSummary['unmet_units'] }}</strong>
              <span class="insight-kpi-label">Shortage gap</span>
              <em class="insight-kpi-hint">{{ $restockSummary['unmet_hint'] ?? '' }}</em>
            </div>
          </button>

          <button
            type="button"
            class="insight-kpi insight-kpi-idle"
            data-insight-filter="idle"
            aria-pressed="false"
          >
            <span class="insight-kpi-icon"><i class="bi bi-box-seam"></i></span>
            <div class="insight-kpi-body">
              <strong>{{ $restockSummary['idle_items'] }}</strong>
              <span class="insight-kpi-label">Idle stock items</span>
              <em class="insight-kpi-hint">{{ $restockSummary['idle_hint'] ?? '' }}</em>
            </div>
          </button>
        </section>

        {{-- How the buy suggestion works --}}
        <section class="insight-how-it-works">
          <h3><i class="bi bi-lightbulb-fill"></i> How buy suggestions work</h3>
          <ol>
            <li><strong>Shortage</strong> — more units are out or were borrowed than you own → buy the difference <em>plus 1 spare</em>.</li>
            <li><strong>All units out</strong> — every unit is currently borrowed → buy 1 spare for the next request.</li>
            <li><strong>High demand</strong> — borrowed units reached 80%+ of stock, or bookings exceed stock → buy 1 spare.</li>
          </ol>
        </section>

        {{-- Headline feature: what to restock --}}
        <section id="insight-restock-section" class="inventory-grid analytics-table-grid insight-section" data-insight-target="restock">
          <div class="inventory-grid-head analytics-grid-head insight-head">
            <div>
              <h2>Restock Recommendations</h2>
              <p>Items where borrowing demand exceeds what you have on hand</p>
            </div>
            @if ($restockSummary['items_needing_action'] > 0)
              <span class="insight-head-badge">{{ $restockSummary['items_needing_action'] }} need attention</span>
            @endif
          </div>
          <div class="table-wrap">
            <table class="inventory-table analytics-table restock-table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>On Hand</th>
                  <th>Times Borrowed</th>
                  <th>Units Borrowed</th>
                  <th>Currently Out</th>
                  <th>Recommendation</th>
                  <th>Priority</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($restockRecommendations as $item)
                  <tr
                    data-insight-row="restock"
                    data-priority="{{ $item['priority'] }}"
                    data-suggested="{{ $item['suggested_qty'] }}"
                    data-gap="{{ $item['gap'] }}"
                  >
                    <td>
                      <div class="restock-item-name">{{ $item['item_name'] }}</div>
                      <div class="restock-item-meta">
                        <span class="asset-id">{{ $item['asset_id'] }}</span>
                        <span>{{ $item['category'] }}</span>
                      </div>
                      <div class="restock-reason">{{ $item['reason'] }}</div>
                    </td>
                    <td>
                      <strong>{{ $item['serviceable'] }}</strong>
                      @if ($item['unavailable_units'] > 0)
                        <span class="restock-sub">{{ $item['unavailable_units'] }} in maintenance</span>
                      @endif
                    </td>
                    <td><strong>{{ $item['times_borrowed'] }}</strong></td>
                    <td><strong>{{ $item['units_borrowed'] }}</strong></td>
                    <td>
                      <strong>{{ $item['in_use'] }}</strong>
                      @if ($item['over_allocated'])
                        <span class="restock-sub over-allocated">exceeds stock</span>
                      @endif
                    </td>
                    <td>
                      @if ($item['suggested_qty'] > 0)
                        <span class="restock-suggestion">
                          <i class="bi bi-plus-circle-fill"></i>
                          Buy {{ $item['suggested_qty'] }} more
                        </span>
                        @if ($item['suggestion_formula'])
                          <span class="restock-formula">{{ $item['suggestion_formula'] }}</span>
                        @endif
                      @else
                        <span class="restock-ok">Adequate</span>
                      @endif
                    </td>
                    <td>
                      <span class="priority-pill {{ $item['priority'] }}">{{ $item['priority_label'] }}</span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7" class="insight-empty">
                      <i class="bi bi-check-circle"></i>
                      Every item currently has enough stock to cover demand.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </section>

        {{-- Borrowed units: doughnut + color-matched list --}}
        <section class="analytics-top-row">
          <article class="analytics-chart-card borrowed-share-card" aria-label="Share of Borrowed Units">
            <h3 class="insight-card-title">Share of Borrowed Units</h3>
            <p class="insight-panel-hint">Top items borrowed in {{ $monthLabel }}</p>

            @php
              $sharePalette = ['#3a4f9c', '#2bb3a8', '#e09a00', '#e85a5a', '#6a58c4', '#2f9fc2', '#3fa06c', '#e07000'];
            @endphp

            @if (count($shareItems ?? $topItems) > 0)
              @php $shareRows = $shareItems ?? $topItems; @endphp
              <div class="borrowed-share-layout">
                <div class="borrowed-share-chart-wrap">
                  <canvas id="topItemsChart"></canvas>
                </div>

                <ul class="borrowed-share-list">
                  @foreach ($shareRows as $index => $item)
                    @php $swatch = $sharePalette[$index % count($sharePalette)]; @endphp
                    <li class="borrowed-share-row">
                      <span class="borrowed-share-swatch" style="background: {{ $swatch }}" aria-hidden="true"></span>
                      <div class="borrowed-share-copy">
                        <span class="borrowed-share-name" title="{{ $item['item_name'] }}">{{ $item['item_name'] }}</span>
                        <span class="borrowed-share-meta">{{ number_format($item['share_percent'] ?? 0, 1) }}% of borrowed units</span>
                      </div>
                      <strong class="borrowed-share-count">{{ number_format($item['usage_count']) }}</strong>
                    </li>
                  @endforeach
                </ul>
              </div>
            @else
              <p class="insight-empty-inline">No borrowing activity to show yet.</p>
            @endif
          </article>

          <article class="analytics-kpi-card" aria-label="Month comparison metrics">
            <p>
              <span>Approved Bookings</span>
              <strong>{{ number_format($approvedBookings) }}</strong>
              <em class="{{ $bookingsDelta >= 0 ? 'up' : 'down' }}">
                @if ($bookingsDelta > 0)↑ @elseif ($bookingsDelta < 0)↓ @endif{{ $bookingsDelta > 0 ? '+' : '' }}{{ number_format($bookingsDelta) }}
              </em>
            </p>
            <p>
              <span>Unique Borrowers</span>
              <strong>{{ number_format($totalBorrowers) }}</strong>
              <em class="{{ $borrowersDelta >= 0 ? 'up' : 'down' }}">
                @if ($borrowersDelta > 0)↑ @elseif ($borrowersDelta < 0)↓ @endif{{ $borrowersDelta > 0 ? '+' : '' }}{{ number_format($borrowersDelta) }}
              </em>
            </p>
            <p>
              <span>Units Borrowed</span>
              <strong>{{ number_format($engagementCount) }}</strong>
              <em class="{{ $engagementDelta >= 0 ? 'up' : 'down' }}">
                @if ($engagementDelta > 0)↑ @elseif ($engagementDelta < 0)↓ @endif{{ $engagementDelta > 0 ? '+' : '' }}{{ number_format($engagementDelta) }}
              </em>
            </p>
            <p>
              <span>New Users</span>
              <strong>{{ number_format($newUsers) }}</strong>
              <em class="{{ $newUsersDelta >= 0 ? 'up' : 'down' }}">
                @if ($newUsersDelta > 0)↑ @elseif ($newUsersDelta < 0)↓ @endif{{ $newUsersDelta > 0 ? '+' : '' }}{{ number_format($newUsersDelta) }}
              </em>
            </p>
          </article>

          @php
            $categoryRows = collect($categoryDemand ?? [])
              ->filter(fn ($row) => (int) ($row['demand_qty'] ?? 0) > 0)
              ->take(5)
              ->values();
            $categoryDemandMax = max(1, (int) $categoryRows->max('demand_qty'));
            $categoryDemandTotal = max(
              1,
              (int) collect($categoryDemand ?? [])->sum('demand_qty')
            );
          @endphp

          <article class="analytics-category-card" aria-label="Demand by category">
            <h3 class="insight-card-title">Demand by Category</h3>
            <p class="insight-panel-hint">Where units were borrowed in {{ $monthLabel }}</p>

            @if ($categoryRows->isNotEmpty())
              <ul class="analytics-category-list">
                @foreach ($categoryRows as $category)
                  @php
                    $demandQty = (int) ($category['demand_qty'] ?? 0);
                    $barPercent = (int) round(($demandQty / $categoryDemandMax) * 100);
                    $sharePercent = round(($demandQty / $categoryDemandTotal) * 100, 1);
                  @endphp
                  <li class="analytics-category-row">
                    <div class="analytics-category-head">
                      <strong class="analytics-category-name" title="{{ $category['category'] }}">{{ $category['category'] }}</strong>
                      <span class="analytics-category-count">{{ number_format($demandQty) }}</span>
                    </div>
                    <div class="analytics-category-bar" aria-hidden="true">
                      <span class="analytics-category-fill" style="width: {{ $barPercent }}%"></span>
                    </div>
                    <div class="analytics-category-meta">
                      <span>{{ $sharePercent }}% of borrowed units</span>
                      <span>{{ number_format((int) ($category['stock'] ?? 0)) }} in stock</span>
                    </div>
                  </li>
                @endforeach
              </ul>
            @else
              <p class="insight-empty-inline">No category demand in this month yet.</p>
            @endif
          </article>
        </section>

        <section class="inventory-grid analytics-table-grid insight-section">
          <div class="inventory-grid-head analytics-grid-head insight-head">
            <div>
              <h2>Top Leading Borrowed Items</h2>
              <p>Most requested items this month, and how many people borrowed each one</p>
            </div>
          </div>
          <div class="table-wrap">
            <table class="inventory-table analytics-table leading-items-table">
              <thead>
                <tr>
                  <th>Item Name</th>
                  <th>Owner</th>
                  <th>ID</th>
                  <th>Bookings</th>
                  <th>Units Borrowed</th>
                  <th>People Who Borrowed</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($topItems as $item)
                  <tr>
                    <td>{{ $item['item_name'] }}</td>
                    <td>{{ $item['owner'] ?? $item['location'] ?? '—' }}</td>
                    <td class="asset-id">{{ $item['asset_id'] }}</td>
                    <td>{{ $item['booking_count'] }}</td>
                    <td><strong>{{ $item['usage_count'] }}</strong></td>
                    <td>
                      <strong>{{ number_format($item['borrower_count'] ?? 0) }}</strong>
                      <span class="restock-sub">
                        {{ ($item['borrower_count'] ?? 0) === 1 ? 'person' : 'people' }}
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="insight-empty">No borrowed items in this period yet.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </section>

        {{-- Demand context: busiest days + top borrowers in one card --}}
        <section class="insight-activity-section">
          <article class="insight-panel insight-activity-card">
            <div class="insight-activity-head">
              <div>
                <h3 class="insight-card-title">Borrowing Activity</h3>
                <p class="insight-panel-hint">Busiest days and the people driving demand in {{ $monthLabel }}</p>
              </div>
            </div>

            <div class="insight-activity-grid">
              <div class="insight-activity-col">
                <h4 class="insight-subhead">Busiest booking days</h4>
                @if (count($peakPeriods['weekdays']) > 0)
                  <ul class="weekday-list">
                    @foreach ($peakPeriods['weekdays'] as $day)
                      <li>
                        <span class="weekday-label">{{ $day['label'] }}</span>
                        <span class="weekday-bar">
                          <span class="weekday-fill" style="width: {{ $day['percent'] }}%"></span>
                        </span>
                        <span class="weekday-count">{{ $day['count'] }}</span>
                      </li>
                    @endforeach
                  </ul>
                @else
                  <p class="insight-empty-inline">Not enough booking history yet.</p>
                @endif

                @if (count($peakPeriods['busiest_dates']) > 0)
                  <h4 class="insight-subhead">Heaviest activity dates</h4>
                  <ul class="busy-date-list">
                    @foreach ($peakPeriods['busiest_dates'] as $date)
                      <li>
                        <span>{{ $date['date'] }}</span>
                        <strong>{{ $date['count'] }} booking{{ $date['count'] === 1 ? '' : 's' }}</strong>
                      </li>
                    @endforeach
                  </ul>
                @endif
              </div>

              <div class="insight-activity-col">
                <h4 class="insight-subhead">Top borrowers</h4>
                @if (count($topBorrowers ?? []) > 0)
                  <ul class="top-borrower-list">
                    @foreach ($topBorrowers as $index => $borrower)
                      <li class="top-borrower-row">
                        <span class="top-borrower-rank">{{ $index + 1 }}</span>
                        <div class="top-borrower-main">
                          <div class="top-borrower-name-row">
                            <span class="top-borrower-name">{{ $borrower['name'] }}</span>
                            <strong class="top-borrower-units">{{ number_format($borrower['units_borrowed']) }} units</strong>
                          </div>
                          <div class="top-borrower-meta">
                            <span>{{ $borrower['booking_count'] }} booking{{ $borrower['booking_count'] === 1 ? '' : 's' }}</span>
                            <span class="top-borrower-item">Most used: {{ $borrower['top_item'] }}</span>
                          </div>
                          <div class="top-borrower-bar" aria-hidden="true">
                            <span class="top-borrower-fill" style="width: {{ $borrower['usage_percent'] }}%"></span>
                          </div>
                        </div>
                      </li>
                    @endforeach
                  </ul>
                @else
                  <p class="insight-empty-inline">No borrower activity in this month yet.</p>
                @endif
              </div>
            </div>
          </article>
        </section>

        {{-- Cost-saving and reliability signals --}}
        <section class="insight-split">
          <article id="insight-idle-section" class="insight-panel" data-insight-target="idle">
            <h3 class="insight-card-title">Idle Stock</h3>
            <p class="insight-panel-hint">Owned but never requested — avoid buying more of these</p>
            @if (count($idleStock) > 0)
              <div class="table-wrap">
              <table class="inventory-table analytics-table idle-stock-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Units Idle</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($idleStock as $item)
                    <tr data-insight-row="idle">
                      <td>{{ $item['item_name'] }}</td>
                      <td>{{ $item['category'] }}</td>
                      <td><strong>{{ $item['stock'] }}</strong></td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
              </div>
            @else
              <p class="insight-empty-inline">Every item has seen activity — no dead stock.</p>
            @endif
          </article>

          <article class="insight-panel">
            <h3 class="insight-card-title">Maintenance Watch</h3>
            <p class="insight-panel-hint">Downtime reduces usable stock and drives shortages</p>
            @if (count($maintenanceWatch) > 0)
              <div class="table-wrap">
              <table class="inventory-table analytics-table maintenance-watch-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Down</th>
                    <th>Suggested Action</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($maintenanceWatch as $item)
                    <tr>
                      <td>{{ $item['item_name'] }}</td>
                      <td>
                        <strong>{{ $item['unavailable_units'] }}</strong>
                        <span class="restock-sub">{{ $item['downtime_percent'] }}% of stock</span>
                      </td>
                      <td>{{ $item['action'] }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
              </div>
            @else
              <p class="insight-empty-inline">No items are currently out of service.</p>
            @endif
          </article>
        </section>
      </section>
    </section>
  </main>

  <script>
    window.analyticsMonthBaseUrl = '{{ route('dashboard.inventory.analytics') }}';
    window.analyticsMonthKey = @json($monthKey);
    window.analyticsCompareMonthKey = @json($compareMonthKey);
  </script>

  <script>
    (function () {
      const monthComparison = @json($monthComparison);
      const comparisonCanvas = document.getElementById('monthComparisonChart');

      if (comparisonCanvas && monthComparison.metrics.length > 0) {
        const labels = monthComparison.metrics.map((row) => row.label);
        const currentData = monthComparison.metrics.map((row) => row.current);
        const compareData = monthComparison.metrics.map((row) => row.compare);

        new Chart(comparisonCanvas.getContext('2d'), {
          type: 'bar',
          data: {
            labels,
            datasets: [
              {
                label: monthComparison.compareLabel,
                data: compareData,
                backgroundColor: '#c7d0ea',
                borderRadius: 6
              },
              {
                label: monthComparison.currentLabel,
                data: currentData,
                backgroundColor: '#4457ab',
                borderRadius: 6
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: { grid: { display: false }, ticks: { font: { size: 10 } } },
              y: { beginAtZero: true, ticks: { precision: 0 } }
            },
            plugins: {
              legend: {
                position: 'top',
                align: 'end',
                labels: { usePointStyle: true, boxWidth: 8, padding: 14, font: { size: 11 } }
              }
            },
            layout: {
              padding: { top: 2, right: 6, bottom: 2, left: 2 }
            }
          }
        });
      }

      const yearLabels = @json($yearLabels);
      const trendCounts = @json($trendCounts);
      const trendCanvas = document.getElementById('monthlyTrendChart');

      if (trendCanvas && yearLabels.length > 0) {
        new Chart(trendCanvas.getContext('2d'), {
          type: 'line',
          data: {
            labels: yearLabels,
            datasets: [{
              label: 'Approved bookings',
              data: trendCounts,
              borderColor: '#4457ab',
              backgroundColor: 'rgba(68, 87, 171, 0.12)',
              fill: true,
              tension: 0.35,
              pointRadius: 4,
              pointBackgroundColor: '#4457ab'
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: { grid: { display: false } },
              y: { beginAtZero: true, ticks: { precision: 0 } }
            },
            plugins: {
              legend: { display: false }
            },
            layout: {
              padding: { top: 6, right: 8, bottom: 4, left: 2 }
            }
          }
        });
      }

      const sharePalette = ['#3a4f9c', '#2bb3a8', '#e09a00', '#e85a5a', '#6a58c4', '#2f9fc2', '#3fa06c', '#e07000'];
      const shareItems = @json($shareItems ?? $topItems);
      const topItemsCanvas = document.getElementById('topItemsChart');

      if (topItemsCanvas && shareItems.length > 0) {
        new Chart(topItemsCanvas.getContext('2d'), {
          type: 'doughnut',
          data: {
            labels: shareItems.map((item) => item.item_name),
            datasets: [{
              data: shareItems.map((item) => item.usage_count),
              backgroundColor: sharePalette.slice(0, shareItems.length),
              borderColor: sharePalette.slice(0, shareItems.length),
              borderWidth: 0,
              hoverBorderWidth: 0,
              hoverOffset: 4
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '48%',
            plugins: {
              legend: { display: false },
              tooltip: {
                backgroundColor: '#1f2a5a',
                titleColor: '#fff',
                bodyColor: '#fff',
                displayColors: true,
                boxPadding: 4,
                callbacks: {
                  title: (items) => items[0]?.label || '',
                  label: (context) => ` ${context.parsed} unit(s)`
                }
              }
            }
          }
        });
      }

      const monthSelect = document.getElementById('analytics-month-select');
      const yearSelect = document.getElementById('analytics-year-select');
      const compareMonthSelect = document.getElementById('analytics-compare-month');
      const compareYearSelect = document.getElementById('analytics-compare-year');

      const buildAnalyticsUrl = (monthKey, compareKey) => {
        const baseUrl = window.analyticsMonthBaseUrl || '/dashboard/inventory/analytics';
        const params = new URLSearchParams({ month: monthKey });

        if (compareKey && compareKey !== monthKey) {
          params.set('compare', compareKey);
        }

        return `${baseUrl}?${params.toString()}`;
      };

      const previousMonthKey = (monthKey) => {
        const [year, month] = monthKey.split('-').map(Number);
        const date = new Date(year, month - 2, 1);
        const prevYear = date.getFullYear();
        const prevMonth = String(date.getMonth() + 1).padStart(2, '0');
        return `${prevYear}-${prevMonth}`;
      };

      const goToAnalytics = (url) => {
        if (typeof window.navigateWithInsightsSkeleton === 'function') {
          window.navigateWithInsightsSkeleton(url);
          return;
        }

        window.location.assign(url);
      };

      if (monthSelect && yearSelect) {
        const openSelectedMonth = () => {
          const monthKey = `${yearSelect.value}-${monthSelect.value}`;
          // Always pair the selected month with the month before it.
          goToAnalytics(buildAnalyticsUrl(monthKey, previousMonthKey(monthKey)));
        };

        monthSelect.addEventListener('change', openSelectedMonth);
        yearSelect.addEventListener('change', openSelectedMonth);
      }

      if (compareMonthSelect && compareYearSelect) {
        compareMonthSelect.addEventListener('change', () => {
          const compareKey = `${compareYearSelect.value}-${compareMonthSelect.value}`;
          goToAnalytics(buildAnalyticsUrl(window.analyticsMonthKey, compareKey));
        });

        compareYearSelect.addEventListener('change', () => {
          const compareKey = `${compareYearSelect.value}-${compareMonthSelect.value}`;
          goToAnalytics(buildAnalyticsUrl(window.analyticsMonthKey, compareKey));
        });
      }

      const filterButtons = Array.from(document.querySelectorAll('[data-insight-filter]'));
      const restockSection = document.getElementById('insight-restock-section');
      const idleSection = document.getElementById('insight-idle-section');
      const restockRows = Array.from(document.querySelectorAll('[data-insight-row="restock"]'));
      const idleRows = Array.from(document.querySelectorAll('[data-insight-row="idle"]'));
      let activeFilter = null;

      const clearHighlights = () => {
        document.body.classList.remove('insight-filtering');
        filterButtons.forEach((btn) => {
          btn.classList.remove('is-active');
          btn.setAttribute('aria-pressed', 'false');
        });
        restockRows.forEach((row) => row.classList.remove('is-highlighted', 'is-dimmed'));
        idleRows.forEach((row) => row.classList.remove('is-highlighted', 'is-dimmed'));
        if (restockSection) restockSection.classList.remove('is-spotlight');
        if (idleSection) idleSection.classList.remove('is-spotlight');
        activeFilter = null;
      };

      const applyFilter = (filter) => {
        if (activeFilter === filter) {
          clearHighlights();
          return;
        }

        clearHighlights();
        activeFilter = filter;
        document.body.classList.add('insight-filtering');

        const activeBtn = filterButtons.find((btn) => btn.dataset.insightFilter === filter);
        if (activeBtn) {
          activeBtn.classList.add('is-active');
          activeBtn.setAttribute('aria-pressed', 'true');
        }

        if (filter === 'idle') {
          if (idleSection) idleSection.classList.add('is-spotlight');
          idleRows.forEach((row) => row.classList.add('is-highlighted'));
          restockRows.forEach((row) => row.classList.add('is-dimmed'));
          idleSection?.scrollIntoView({ behavior: 'smooth', block: 'center' });
          return;
        }

        if (restockSection) restockSection.classList.add('is-spotlight');
        idleRows.forEach((row) => row.classList.add('is-dimmed'));

        restockRows.forEach((row) => {
          const priority = row.dataset.priority || '';
          const suggested = Number(row.dataset.suggested || 0);
          const gap = Number(row.dataset.gap || 0);
          let match = false;

          if (filter === 'critical') match = priority === 'critical';
          if (filter === 'procure') match = suggested > 0;
          if (filter === 'unmet') match = gap > 0;

          row.classList.add(match ? 'is-highlighted' : 'is-dimmed');
        });

        restockSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      };

      filterButtons.forEach((btn) => {
        btn.addEventListener('click', () => applyFilter(btn.dataset.insightFilter));
      });
    })();
  </script>

  <script src="/js/dashboard.js"></script>
</body>
</html>
