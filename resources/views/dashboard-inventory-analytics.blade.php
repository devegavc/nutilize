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

      <section class="content-card analytics-content-card">
        <div class="insights-header">
          <div>
            <h1 class="section-title">INSIGHTS DASHBOARD</h1>
            <p class="insights-subtitle">
              Demand and stock analysis over the last {{ $lookbackDays }} days
            </p>
          </div>
        </div>

        {{-- Procurement signal strip --}}
        <section class="insight-kpi-strip">
          <article class="insight-kpi {{ $restockSummary['critical'] > 0 ? 'is-critical' : '' }}">
            <span class="insight-kpi-icon"><i class="bi bi-exclamation-octagon-fill"></i></span>
            <div>
              <strong>{{ $restockSummary['critical'] }}</strong>
              <span>Critical shortages</span>
            </div>
          </article>

          <article class="insight-kpi {{ $restockSummary['units_to_procure'] > 0 ? 'is-action' : '' }}">
            <span class="insight-kpi-icon"><i class="bi bi-cart-plus-fill"></i></span>
            <div>
              <strong>+{{ $restockSummary['units_to_procure'] }}</strong>
              <span>Units recommended to buy</span>
            </div>
          </article>

          <article class="insight-kpi">
            <span class="insight-kpi-icon"><i class="bi bi-x-circle-fill"></i></span>
            <div>
              <strong>{{ $restockSummary['unmet_units'] }}</strong>
              <span>Units short of demand</span>
            </div>
          </article>

          <article class="insight-kpi">
            <span class="insight-kpi-icon"><i class="bi bi-box-seam"></i></span>
            <div>
              <strong>{{ $restockSummary['idle_items'] }}</strong>
              <span>Idle items in storage</span>
            </div>
          </article>

          <article class="insight-kpi">
            <span class="insight-kpi-icon"><i class="bi bi-arrow-return-left"></i></span>
            <div>
              <strong>{{ $fulfillmentStats['cancellation_rate'] }}%</strong>
              <span>Cancelled or rejected</span>
            </div>
          </article>
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
        <section class="inventory-grid analytics-table-grid insight-section">
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
                  <tr>
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

        {{-- Borrowing leaderboard --}}
        <section class="analytics-top-row">
          <article class="analytics-chart-card" aria-label="Top Items Distribution Pie Chart">
            <h3 class="insight-card-title">Share of Borrowed Units</h3>
            <div style="max-width: 360px; margin: 0 auto;">
              <canvas id="topItemsChart"></canvas>
            </div>
          </article>

          <article class="analytics-kpi-card">
            <p>
              <span>Total Borrowers</span>
              <strong>{{ number_format($totalBorrowers) }}</strong>
              <em class="{{ $borrowersGrowth >= 0 ? 'up' : 'down' }}">
                {{ $borrowersGrowth > 0 ? '+' : '' }}{{ number_format($borrowersGrowth, 1) }}%
              </em>
            </p>
            <p>
              <span>Engagement Rates</span>
              <strong>{{ number_format($engagementCount) }}</strong>
              <em class="{{ $engagementGrowth >= 0 ? 'up' : 'down' }}">
                {{ $engagementGrowth > 0 ? '+' : '' }}{{ number_format($engagementGrowth, 1) }}%
              </em>
            </p>
            <p>
              <span>Current Users</span>
              <strong>{{ number_format($newUsers) }}</strong>
              <em class="{{ $newUsersGrowth >= 0 ? 'up' : 'down' }}">
                {{ $newUsersGrowth > 0 ? '+' : '' }}{{ number_format($newUsersGrowth, 1) }}%
              </em>
            </p>
          </article>
        </section>

        <section class="inventory-grid analytics-table-grid insight-section">
          <div class="inventory-grid-head analytics-grid-head insight-head">
            <div>
              <h2>Top Leading Borrowed Items</h2>
              <p>Turnover shows how many times each owned unit was booked</p>
            </div>
          </div>
          <div class="table-wrap">
            <table class="inventory-table analytics-table">
              <thead>
                <tr>
                  <th>Item Name</th>
                  <th>Location</th>
                  <th>ID</th>
                  <th>Bookings</th>
                  <th>Units Borrowed</th>
                  <th>Turnover per Unit</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($topItems as $item)
                  <tr>
                    <td>{{ $item['item_name'] }}</td>
                    <td>{{ $item['location'] }}</td>
                    <td class="asset-id">{{ $item['asset_id'] }}</td>
                    <td>{{ $item['booking_count'] }}</td>
                    <td><strong>{{ $item['usage_count'] }}</strong></td>
                    <td>
                      @if (!is_null($item['turnover']))
                        <span class="turnover-value {{ $item['turnover'] >= 1 ? 'hot' : '' }}">
                          {{ number_format($item['turnover'], 1) }}x
                        </span>
                      @else
                        <span class="restock-sub">No stock on record</span>
                      @endif
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

        {{-- Demand context --}}
        <section class="insight-split">
          <article class="insight-panel">
            <h3 class="insight-card-title">Demand by Category</h3>
            @if (count($categoryDemand) > 0)
              <div class="insight-chart-frame">
                <canvas id="categoryDemandChart"></canvas>
              </div>
            @else
              <p class="insight-empty-inline">No category demand recorded yet.</p>
            @endif
          </article>

          <article class="insight-panel">
            <h3 class="insight-card-title">Busiest Booking Days</h3>
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
          </article>
        </section>

        {{-- Cost-saving and reliability signals --}}
        <section class="insight-split">
          <article class="insight-panel">
            <h3 class="insight-card-title">Idle Stock</h3>
            <p class="insight-panel-hint">Owned but never requested — avoid buying more of these</p>
            @if (count($idleStock) > 0)
              <table class="inventory-table analytics-table compact-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Units Idle</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($idleStock as $item)
                    <tr>
                      <td>{{ $item['item_name'] }}</td>
                      <td>{{ $item['category'] }}</td>
                      <td><strong>{{ $item['stock'] }}</strong></td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            @else
              <p class="insight-empty-inline">Every item has seen activity — no dead stock.</p>
            @endif
          </article>

          <article class="insight-panel">
            <h3 class="insight-card-title">Maintenance Watch</h3>
            <p class="insight-panel-hint">Downtime reduces usable stock and drives shortages</p>
            @if (count($maintenanceWatch) > 0)
              <table class="inventory-table analytics-table compact-table">
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
            @else
              <p class="insight-empty-inline">No items are currently out of service.</p>
            @endif
          </article>
        </section>
      </section>
    </section>
  </main>

  <script>
    (function () {
      const palette = [
        '#4457ab', '#4ECDC4', '#F4B70A', '#FF6B6B', '#7C6BD6',
        '#45B7D1', '#52B788', '#F77F00', '#BB8FCE', '#85C1E2'
      ];

      const topItems = @json($topItems);
      const topItemsCanvas = document.getElementById('topItemsChart');

      if (topItemsCanvas && topItems.length > 0) {
        new Chart(topItemsCanvas.getContext('2d'), {
          type: 'doughnut',
          data: {
            labels: topItems.map((item) => item.item_name),
            datasets: [{
              data: topItems.map((item) => item.usage_count),
              backgroundColor: palette.slice(0, topItems.length),
              borderColor: '#fff',
              borderWidth: 2
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '52%',
            plugins: {
              legend: {
                position: 'bottom',
                labels: { font: { size: 11, weight: '500' }, padding: 12, usePointStyle: true }
              },
              tooltip: {
                callbacks: {
                  label: (context) => `${context.label}: ${context.parsed} unit(s) borrowed`
                }
              }
            }
          }
        });
      } else if (topItemsCanvas) {
        topItemsCanvas.insertAdjacentHTML(
          'afterend',
          '<p class="insight-empty-inline">No borrowing activity to chart yet.</p>'
        );
        topItemsCanvas.remove();
      }

      const categoryDemand = @json($categoryDemand);
      const categoryCanvas = document.getElementById('categoryDemandChart');

      if (categoryCanvas && categoryDemand.length > 0) {
        new Chart(categoryCanvas.getContext('2d'), {
          type: 'bar',
          data: {
            labels: categoryDemand.map((row) => row.category),
            datasets: [
              {
                label: 'Units requested',
                data: categoryDemand.map((row) => row.demand_qty),
                backgroundColor: '#4457ab',
                borderRadius: 6
              },
              {
                label: 'Units owned',
                data: categoryDemand.map((row) => row.stock),
                backgroundColor: '#c7d0ea',
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
              legend: { position: 'bottom', labels: { usePointStyle: true, font: { size: 11 } } }
            }
          }
        });
      }
    })();
  </script>

  <script src="/js/dashboard.js"></script>
</body>
</html>
