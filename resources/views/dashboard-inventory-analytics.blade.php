<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Analytics</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-inventory.css?v={{ filemtime(public_path('css/db-inventory.css')) }}" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="page-insights">
  <script>
    try {
      if (sessionStorage.getItem('nutilize.analyticsView') === 'rooms') {
        document.body.classList.add('analytics-view-rooms');
      }
    } catch (error) {}
  </script>
  <script>
    window.authUser = {
      id: @json(auth()->user()->user_id),
      username: @json(auth()->user()->username ?? 'User'),
      email: @json(auth()->user()->email ?? ''),
      full_name: @json(auth()->user()->full_name ?? auth()->user()->username ?? 'User'),
      role: @json(auth()->user()->role ?? 'user')
    };
  </script>
  <header class="top-header">
    <div class="top-header-inner toolbar-card">
      <img src="/img/nutilize_wordmark.png?v={{ filemtime(public_path('img/nutilize_wordmark.png')) }}" alt="NU-TILIZE" class="toolbar-logo" />

      @include('partials.office-user-guide')

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
            <h1 class="section-title">ANALYTICS</h1>
            <div class="insights-view-switch" role="tablist" aria-label="Analytics report">
              <button
                type="button"
                class="insights-view-tab"
                role="tab"
                id="analytics-view-equipment"
                data-analytics-view="equipment"
                aria-selected="true"
              >Equipment</button>
              <button
                type="button"
                class="insights-view-tab"
                role="tab"
                id="analytics-view-rooms"
                data-analytics-view="rooms"
                aria-selected="false"
              >Rooms</button>
            </div>
          </div>

          @php
            [$selectedYear, $selectedMonthNum] = explode('-', $monthKey);
            $selectedYear = (int) $selectedYear;
            $selectedMonthNum = (int) $selectedMonthNum;
          @endphp
          <div class="insights-month-jump" data-analytics-panel="rooms" aria-label="Jump to month">
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

        @php
          $roomPayload = (isset($roomInsights) && is_array($roomInsights)) ? $roomInsights : [];
          $roomSummaryPayload = (isset($roomSummary) && is_array($roomSummary)) ? $roomSummary : [];
          if (isset($roomPayload['summary']) && is_array($roomPayload['summary'])) {
              $roomSummaryPayload = array_merge($roomSummaryPayload, $roomPayload['summary']);
          }

          $roomInsightsReady = $roomPayload !== []
              || $roomSummaryPayload !== []
              || isset($roomBookings)
              || isset($roomsUsed)
              || isset($roomsUnderMaintenance)
              || isset($underMaintenance)
              || isset($mostUsedRooms)
              || isset($roomUsage)
              || isset($roomShareItems)
              || isset($roomShare)
              || isset($roomTypeDemand)
              || isset($typeDemand)
              || isset($idleRooms)
              || isset($roomCondition)
              || isset($roomTrendCounts)
              || isset($roomMonthComparison);

          $roomValue = function (array $sources) {
              foreach ($sources as $value) {
                  if ($value !== null && $value !== '') {
                      return $value;
                  }
              }

              return null;
          };

          $roomList = function (array $candidates): array {
              foreach ($candidates as $value) {
                  if (is_array($value)) {
                      return array_values($value);
                  }
              }

              return [];
          };

          $roomText = function (array $row, array $keys, string $fallback = '—'): string {
              foreach ($keys as $key) {
                  $value = trim((string) ($row[$key] ?? ''));
                  if ($value !== '') {
                      return $value;
                  }
              }

              return $fallback;
          };

          $roomNumber = function (array $row, array $keys) {
              foreach ($keys as $key) {
                  if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                      return $row[$key];
                  }
              }

              return null;
          };

          $roomBookingsValue = $roomValue([
              $roomBookings ?? null,
              $roomPayload['roomBookings'] ?? null,
              $roomPayload['room_bookings'] ?? null,
              $roomPayload['bookings'] ?? null,
              $roomSummaryPayload['roomBookings'] ?? null,
              $roomSummaryPayload['room_bookings'] ?? null,
              $roomSummaryPayload['bookings'] ?? null,
          ]);

          $roomsUsedValue = $roomValue([
              $roomsUsed ?? null,
              $roomPayload['roomsUsed'] ?? null,
              $roomPayload['rooms_used'] ?? null,
              $roomSummaryPayload['roomsUsed'] ?? null,
              $roomSummaryPayload['rooms_used'] ?? null,
          ]);

          $roomsMaintenanceValue = $roomValue([
              $roomsUnderMaintenance ?? null,
              $underMaintenance ?? null,
              $roomPayload['roomsUnderMaintenance'] ?? null,
              $roomPayload['underMaintenance'] ?? null,
              $roomPayload['under_maintenance'] ?? null,
              $roomSummaryPayload['roomsUnderMaintenance'] ?? null,
              $roomSummaryPayload['underMaintenance'] ?? null,
              $roomSummaryPayload['under_maintenance'] ?? null,
          ]);

          $roomShareRows = $roomList([
              $roomShareItems ?? null,
              $roomShare ?? null,
              $roomPayload['roomShareItems'] ?? null,
              $roomPayload['shareItems'] ?? null,
              $roomPayload['roomShare'] ?? null,
              $roomPayload['share'] ?? null,
          ]);

          $roomTypeRows = $roomList([
              $roomTypeDemand ?? null,
              $typeDemand ?? null,
              $demandByType ?? null,
              $roomPayload['roomTypeDemand'] ?? null,
              $roomPayload['typeDemand'] ?? null,
              $roomPayload['demandByType'] ?? null,
          ]);

          $mostUsedRoomRows = $roomList([
              $mostUsedRooms ?? null,
              $roomUsage ?? null,
              $roomPayload['mostUsedRooms'] ?? null,
              $roomPayload['roomUsage'] ?? null,
              $roomPayload['usage'] ?? null,
          ]);

          $idleRoomRows = $roomList([
              $idleRooms ?? null,
              $roomPayload['idleRooms'] ?? null,
              $roomPayload['idle'] ?? null,
          ]);

          $roomConditionRows = $roomList([
              $roomCondition ?? null,
              $roomPayload['roomCondition'] ?? null,
              $roomPayload['condition'] ?? null,
          ]);

          $idleRoomCountValue = $roomValue([
              $idleRoomCount ?? null,
              $roomPayload['idleRoomCount'] ?? null,
              $roomPayload['idle_room_count'] ?? null,
              $roomSummaryPayload['idleRoomCount'] ?? null,
              $roomSummaryPayload['idle_rooms'] ?? null,
              $roomSummaryPayload['idleRooms'] ?? null,
          ]);

          if ($idleRoomCountValue === null && $roomInsightsReady) {
              $idleRoomCountValue = count($idleRoomRows);
          }

          $roomShareNormalized = [];
          foreach ($roomShareRows as $row) {
              if (! is_array($row)) {
                  continue;
              }

              $count = $roomNumber($row, ['booking_count', 'bookings', 'usage_count', 'count']);
              $roomShareNormalized[] = [
                  'label' => $roomText($row, ['room_number', 'room_name', 'name', 'label', 'item_name'], 'Room'),
                  'count' => $count === null ? 0 : (int) $count,
                  'share_percent' => $roomNumber($row, ['share_percent', 'share']),
              ];
          }

          $roomShareTotal = array_sum(array_column($roomShareNormalized, 'count'));
          foreach ($roomShareNormalized as &$roomShareRow) {
              if ($roomShareRow['share_percent'] === null) {
                  $roomShareRow['share_percent'] = $roomShareTotal > 0
                      ? round(($roomShareRow['count'] / $roomShareTotal) * 100, 1)
                      : 0;
              } else {
                  $roomShareRow['share_percent'] = round((float) $roomShareRow['share_percent'], 1);
              }
          }
          unset($roomShareRow);

          $roomTypeNormalized = [];
          foreach ($roomTypeRows as $row) {
              if (! is_array($row)) {
                  continue;
              }

              $count = $roomNumber($row, ['booking_count', 'bookings', 'demand_qty', 'count']);
              $roomTypeNormalized[] = [
                  'room_type' => $roomText($row, ['room_type', 'type', 'category', 'label'], 'Unspecified'),
                  'count' => $count === null ? 0 : (int) $count,
                  'share_percent' => $roomNumber($row, ['share_percent', 'share']),
              ];
          }

          $roomTypeCounts = array_column($roomTypeNormalized, 'count');
          $roomTypeMax = max(1, $roomTypeCounts === [] ? 0 : max($roomTypeCounts));
          $roomTypeSum = array_sum($roomTypeCounts);
          foreach ($roomTypeNormalized as &$roomTypeRow) {
              if ($roomTypeRow['share_percent'] === null) {
                  $roomTypeRow['share_percent'] = $roomTypeSum > 0
                      ? round(($roomTypeRow['count'] / $roomTypeSum) * 100, 1)
                      : 0;
              } else {
                  $roomTypeRow['share_percent'] = round((float) $roomTypeRow['share_percent'], 1);
              }
          }
          unset($roomTypeRow);

          $roomComparisonPayload = null;
          if (isset($roomMonthComparison) && is_array($roomMonthComparison) && ! empty($roomMonthComparison['metrics'])) {
              $roomComparisonPayload = $roomMonthComparison;
          } elseif (isset($roomPayload['monthComparison']) && is_array($roomPayload['monthComparison']) && ! empty($roomPayload['monthComparison']['metrics'])) {
              $roomComparisonPayload = $roomPayload['monthComparison'];
          } else {
              $embeddedRoomMetrics = array_values(array_filter(
                  $monthComparison['metrics'] ?? [],
                  static fn ($row) => in_array($row['key'] ?? '', ['room_bookings', 'roomBookings', 'rooms'], true)
              ));

              if ($embeddedRoomMetrics !== []) {
                  $roomComparisonPayload = [
                      'currentLabel' => $monthComparison['currentLabel'] ?? '',
                      'compareLabel' => $monthComparison['compareLabel'] ?? '',
                      'metrics' => $embeddedRoomMetrics,
                  ];
              }
          }

          $roomCompareBookingsValue = $roomValue([
              $roomCompareBookings ?? null,
              $roomPayload['compareBookings'] ?? null,
              $roomPayload['compare_bookings'] ?? null,
          ]);

          if ($roomComparisonPayload === null && $roomBookingsValue !== null && $roomCompareBookingsValue !== null) {
              $roomComparisonPayload = [
                  'currentLabel' => $monthComparison['currentLabel'] ?? '',
                  'compareLabel' => $monthComparison['compareLabel'] ?? '',
                  'metrics' => [[
                      'key' => 'room_bookings',
                      'label' => 'Room Bookings',
                      'current' => (int) $roomBookingsValue,
                      'compare' => (int) $roomCompareBookingsValue,
                  ]],
              ];
          }

          $roomTrendPayload = null;
          if (isset($roomTrendCounts) && is_array($roomTrendCounts)) {
              $roomTrendPayload = array_values($roomTrendCounts);
          } elseif (isset($roomPayload['trendCounts']) && is_array($roomPayload['trendCounts'])) {
              $roomTrendPayload = array_values($roomPayload['trendCounts']);
          } elseif (isset($roomPayload['roomTrendCounts']) && is_array($roomPayload['roomTrendCounts'])) {
              $roomTrendPayload = array_values($roomPayload['roomTrendCounts']);
          }

          $formatRoomKpi = function ($value): string {
              if ($value === null || $value === '') {
                  return '—';
              }

              return number_format((int) $value);
          };

          $formatRoomCount = function ($value): string {
              if ($value === null || $value === '') {
                  return '—';
              }

              return number_format((int) $value);
          };

          $formatRoomOccupancy = function ($value): string {
              if ($value === null || $value === '') {
                  return '—';
              }

              if (is_string($value) && str_contains($value, '%')) {
                  return $value;
              }

              if (! is_numeric($value)) {
                  return '—';
              }

              $number = min(100, max(0, (float) $value));
              $decimals = abs($number - round($number)) < 0.05 ? 0 : 1;

              return number_format($number, $decimals).'%';
          };

          $roomConditionClass = function (string $condition): string {
              $normalized = strtolower(trim($condition));
              if (str_contains($normalized, 'maintenance')) {
                  return 'maintenance';
              }
              if (str_contains($normalized, 'unavailable')) {
                  return 'unavailable';
              }

              return 'available';
          };

          $roomUnavailableCopy = 'Room usage is not available for this month.';
          $sharePalette = ['#3a4f9c', '#2bb3a8', '#e09a00', '#e85a5a', '#6a58c4', '#2f9fc2', '#3fa06c', '#e07000'];
        @endphp

        @include('partials.equipment-demand-analytics')

        {{-- Rooms stay month-scoped. Equipment demand is loaded separately. --}}
        <section class="insight-charts-row" data-analytics-panel="rooms">
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
              <p id="monthComparisonEmpty" class="insight-empty-inline analytics-chart-empty" hidden>Room booking comparison is not available for this month.</p>
            </div>
          </article>

          <article class="insight-panel">
            <h3 class="insight-card-title" id="monthlyTrendTitle">
              <span class="analytics-equipment-copy">12-Month Booking Trend</span>
              <span class="analytics-rooms-copy">12-Month Room Booking Trend</span>
            </h3>
            <p class="insight-panel-hint" id="monthlyTrendHint">
              <span class="analytics-equipment-copy">Approved reservations ending {{ $monthLabel }}</span>
              <span class="analytics-rooms-copy">Room bookings ending {{ $monthLabel }}</span>
            </p>
            <div class="insight-chart-frame insight-trend-frame">
              <canvas id="monthlyTrendChart"></canvas>
              <p id="monthlyTrendEmpty" class="insight-empty-inline analytics-chart-empty" hidden>Room booking trend is not available for this month.</p>
            </div>
          </article>
        </section>


        <section class="insight-kpi-strip" data-analytics-panel="rooms" aria-label="Room usage">
          <div class="insight-kpi insight-kpi-gap">
            <span class="insight-kpi-icon"><i class="bi bi-calendar2-check-fill"></i></span>
            <div class="insight-kpi-body">
              <strong>{{ $formatRoomKpi($roomBookingsValue) }}</strong>
              <span class="insight-kpi-label">Room bookings</span>
              <em class="insight-kpi-hint">Reservations that include a room</em>
            </div>
          </div>

          <div class="insight-kpi">
            <span class="insight-kpi-icon"><i class="bi bi-door-open-fill"></i></span>
            <div class="insight-kpi-body">
              <strong>{{ $formatRoomKpi($roomsUsedValue) }}</strong>
              <span class="insight-kpi-label">Rooms used</span>
              <em class="insight-kpi-hint">Distinct rooms booked</em>
            </div>
          </div>

          <div class="insight-kpi insight-kpi-critical {{ is_numeric($roomsMaintenanceValue) && (int) $roomsMaintenanceValue > 0 ? 'is-critical' : '' }}">
            <span class="insight-kpi-icon"><i class="bi bi-tools"></i></span>
            <div class="insight-kpi-body">
              <strong>{{ $formatRoomKpi($roomsMaintenanceValue) }}</strong>
              <span class="insight-kpi-label">Under maintenance</span>
              <em class="insight-kpi-hint">Flagged rooms or open jobs</em>
            </div>
          </div>

          <div class="insight-kpi insight-kpi-idle">
            <span class="insight-kpi-icon"><i class="bi bi-building-slash"></i></span>
            <div class="insight-kpi-body">
              <strong>{{ $formatRoomKpi($idleRoomCountValue) }}</strong>
              <span class="insight-kpi-label">Idle rooms</span>
              <em class="insight-kpi-hint">No bookings this month</em>
            </div>
          </div>
        </section>


        <section class="analytics-top-row room-analytics-top" data-analytics-panel="rooms">
          <article class="analytics-chart-card borrowed-share-card room-share-card" aria-label="Share of room bookings">
            <h3 class="insight-card-title">Share of Room Bookings</h3>
            <p class="insight-panel-hint">Top rooms booked in {{ $monthLabel }}</p>

            @if (! $roomInsightsReady)
              <p class="insight-empty-inline">{{ $roomUnavailableCopy }}</p>
            @elseif (count($roomShareNormalized) > 0)
              <div class="borrowed-share-layout">
                <div class="borrowed-share-chart-wrap">
                  <canvas id="roomShareChart"></canvas>
                </div>

                <ul class="borrowed-share-list">
                  @foreach ($roomShareNormalized as $index => $room)
                    @php $swatch = $sharePalette[$index % count($sharePalette)]; @endphp
                    <li class="borrowed-share-row">
                      <span class="borrowed-share-swatch" data-swatch="{{ $swatch }}" aria-hidden="true"></span>
                      <div class="borrowed-share-copy">
                        <span class="borrowed-share-name" title="{{ $room['label'] }}">{{ $room['label'] }}</span>
                        <span class="borrowed-share-meta">{{ number_format((float) $room['share_percent'], 1) }}% of room bookings</span>
                      </div>
                      <strong class="borrowed-share-count">{{ number_format($room['count']) }}</strong>
                    </li>
                  @endforeach
                </ul>
              </div>
            @else
              <p class="insight-empty-inline">No room bookings to show yet.</p>
            @endif
          </article>

          <article class="analytics-category-card" aria-label="Demand by room type">
            <h3 class="insight-card-title">Demand by Room Type</h3>
            <p class="insight-panel-hint">Where room bookings landed in {{ $monthLabel }}</p>

            @if (! $roomInsightsReady)
              <p class="insight-empty-inline">{{ $roomUnavailableCopy }}</p>
            @elseif (count($roomTypeNormalized) > 0)
              <ul class="analytics-category-list">
                @foreach ($roomTypeNormalized as $roomType)
                  @php
                    $typeCount = (int) $roomType['count'];
                    $typeBarPercent = (int) round(($typeCount / $roomTypeMax) * 100);
                  @endphp
                  <li class="analytics-category-row">
                    <div class="analytics-category-head">
                      <strong class="analytics-category-name" title="{{ $roomType['room_type'] }}">{{ $roomType['room_type'] }}</strong>
                      <span class="analytics-category-count">{{ number_format($typeCount) }}</span>
                    </div>
                    <div class="analytics-category-bar" aria-hidden="true">
                      <span class="analytics-category-fill" data-bar-width="{{ $typeBarPercent }}"></span>
                    </div>
                    <div class="analytics-category-meta">
                      <span>{{ number_format((float) $roomType['share_percent'], 1) }}% of room bookings</span>
                    </div>
                  </li>
                @endforeach
              </ul>
            @else
              <p class="insight-empty-inline">No room type demand in this month yet.</p>
            @endif
          </article>
        </section>


        <section class="inventory-grid analytics-table-grid insight-section" data-analytics-panel="rooms">
          <div class="inventory-grid-head analytics-grid-head insight-head">
            <div>
              <h2>Most Used Rooms</h2>
              <p>Bookings, activity days, occupancy, and requesters in {{ $monthLabel }}</p>
            </div>
          </div>
          <div class="table-wrap">
            <table class="inventory-table analytics-table most-used-rooms-table">
              <thead>
                <tr>
                  <th>Room Number</th>
                  <th>Type</th>
                  <th>Bookings</th>
                  <th>Activity Days</th>
                  <th>Occupancy</th>
                  <th>Requesters</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($mostUsedRoomRows as $room)
                  @if (is_array($room))
                    <tr>
                      <td>{{ $roomText($room, ['room_number', 'room_name', 'name', 'label'], 'Room') }}</td>
                      <td>{{ $roomText($room, ['room_type', 'type', 'category'], 'Unspecified') }}</td>
                      <td><strong>{{ $formatRoomCount($roomNumber($room, ['bookings', 'booking_count', 'usage_count'])) }}</strong></td>
                      <td>{{ $formatRoomCount($roomNumber($room, ['activity_days', 'activityDays'])) }}</td>
                      <td>{{ $formatRoomOccupancy($roomNumber($room, ['occupancy', 'occupancy_percent', 'occupancyPercent'])) }}</td>
                      <td>{{ $formatRoomCount($roomNumber($room, ['requesters', 'requester_count', 'requesterCount'])) }}</td>
                    </tr>
                  @endif
                @empty
                  <tr>
                    <td colspan="6" class="insight-empty">
                      {{ $roomInsightsReady ? 'No rooms were booked in this period yet.' : $roomUnavailableCopy }}
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </section>


        <section class="insight-split" data-analytics-panel="rooms">
          <article class="insight-panel" aria-label="Idle rooms">
            <h3 class="insight-card-title">Idle Rooms</h3>
            <p class="insight-panel-hint">Rooms with no bookings in {{ $monthLabel }}</p>
            @if (! $roomInsightsReady)
              <p class="insight-empty-inline">{{ $roomUnavailableCopy }}</p>
            @elseif (count($idleRoomRows) > 0)
              <div class="table-wrap">
                <table class="inventory-table analytics-table idle-rooms-table">
                  <thead>
                    <tr>
                      <th>Room</th>
                      <th>Type</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($idleRoomRows as $room)
                      @if (is_array($room))
                        <tr>
                          <td>{{ $roomText($room, ['room_number', 'room_name', 'name', 'label'], 'Room') }}</td>
                          <td>{{ $roomText($room, ['room_type', 'type', 'category'], 'Unspecified') }}</td>
                        </tr>
                      @endif
                    @endforeach
                  </tbody>
                </table>
              </div>
            @else
              <p class="insight-empty-inline">Every room was booked this month.</p>
            @endif
          </article>

          <article class="insight-panel" aria-label="Room maintenance and condition">
            <h3 class="insight-card-title">Maintenance and Condition</h3>
            <p class="insight-panel-hint">Open jobs, incidents this month, and what to repair</p>
            @if (! $roomInsightsReady)
              <p class="insight-empty-inline">{{ $roomUnavailableCopy }}</p>
            @elseif (count($roomConditionRows) > 0)
              <div class="table-wrap">
                <table class="inventory-table analytics-table room-condition-table">
                  <thead>
                    <tr>
                      <th>Room</th>
                      <th>Condition</th>
                      <th>Open Jobs</th>
                      <th>Incidents This Month</th>
                      <th>Suggested Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($roomConditionRows as $room)
                      @if (is_array($room))
                        @php
                          $conditionLabel = $roomText($room, ['condition', 'status'], 'Available');
                          $incidentValue = $roomNumber($room, ['incidents', 'incident_count', 'incidents_this_month']);
                          $openJobsValue = $roomNumber($room, ['open_jobs', 'openJobs']);
                          $actionLabel = $roomText($room, ['action', 'suggested_action', 'suggestedAction'], '—');
                        @endphp
                        <tr>
                          <td>{{ $roomText($room, ['room_number', 'room_name', 'name', 'label'], 'Room') }}</td>
                          <td>
                            <span class="priority-pill {{ $roomConditionClass($conditionLabel) }}">{{ $conditionLabel }}</span>
                          </td>
                          <td>{{ $formatRoomCount($openJobsValue) }}</td>
                          <td>{{ $formatRoomCount($incidentValue) }}</td>
                          <td>{{ $actionLabel }}</td>
                        </tr>
                      @endif
                    @endforeach
                  </tbody>
                </table>
              </div>
            @else
              <p class="insight-empty-inline">Nothing to report for room condition this month.</p>
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
      const roomComparison = @json($roomComparisonPayload);
      const roomTrendCounts = @json($roomTrendPayload);
      const roomShareItems = @json($roomShareNormalized);
      const comparisonCanvas = document.getElementById('monthComparisonChart');
      const comparisonEmpty = document.getElementById('monthComparisonEmpty');
      const yearLabels = @json($yearLabels);
      const trendCounts = @json($trendCounts);
      const trendCanvas = document.getElementById('monthlyTrendChart');
      const trendEmpty = document.getElementById('monthlyTrendEmpty');
      const sharePalette = ['#3a4f9c', '#2bb3a8', '#e09a00', '#e85a5a', '#6a58c4', '#2f9fc2', '#3fa06c', '#e07000'];
      const shareItems = @json($shareItems ?? $topItems);
      const topItemsCanvas = document.getElementById('topItemsChart');
      const roomShareCanvas = document.getElementById('roomShareChart');
      const roomMetricKeys = new Set(['room_bookings', 'roomBookings', 'rooms']);
      const equipmentMetrics = (monthComparison.metrics || []).filter((row) => !roomMetricKeys.has(row.key));
      let comparisonChart = null;
      let trendChart = null;
      let topItemsChart = null;
      let roomShareChart = null;

      const barChartOptions = {
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
      };

      const lineChartOptions = {
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
      };

      const doughnutOptions = (unitLabel) => ({
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
              label: (context) => ` ${context.parsed} ${unitLabel}`
            }
          }
        }
      });

      const setChartVisibility = (canvas, emptyEl, visible) => {
        if (canvas) canvas.hidden = !visible;
        if (emptyEl) emptyEl.hidden = visible;
      };

      const renderComparison = (view) => {
        if (!comparisonCanvas) return;

        const rooms = view === 'rooms';
        const metrics = rooms
          ? (roomComparison && Array.isArray(roomComparison.metrics) ? roomComparison.metrics : [])
          : equipmentMetrics;

        if (metrics.length === 0) {
          if (rooms) setChartVisibility(comparisonCanvas, comparisonEmpty, false);
          return;
        }

        setChartVisibility(comparisonCanvas, comparisonEmpty, true);
        const labels = metrics.map((row) => row.label);
        const currentData = metrics.map((row) => Number(row.current) || 0);
        const compareData = metrics.map((row) => Number(row.compare) || 0);
        const currentLabel = (rooms && roomComparison && roomComparison.currentLabel) || monthComparison.currentLabel;
        const compareLabel = (rooms && roomComparison && roomComparison.compareLabel) || monthComparison.compareLabel;

        if (!comparisonChart) {
          comparisonChart = new Chart(comparisonCanvas.getContext('2d'), {
            type: 'bar',
            data: {
              labels,
              datasets: [
                { label: compareLabel, data: compareData, backgroundColor: '#c7d0ea', borderRadius: 6 },
                { label: currentLabel, data: currentData, backgroundColor: '#4457ab', borderRadius: 6 }
              ]
            },
            options: barChartOptions
          });
        } else {
          comparisonChart.data.labels = labels;
          comparisonChart.data.datasets[0].label = compareLabel;
          comparisonChart.data.datasets[0].data = compareData;
          comparisonChart.data.datasets[1].label = currentLabel;
          comparisonChart.data.datasets[1].data = currentData;
          comparisonChart.update();
        }

        requestAnimationFrame(() => comparisonChart && comparisonChart.resize());
      };

      const renderTrend = (view) => {
        if (!trendCanvas) return;

        const rooms = view === 'rooms';
        const counts = rooms ? roomTrendCounts : trendCounts;
        const available = Array.isArray(counts) && counts.length > 0 && yearLabels.length > 0;

        if (!available) {
          if (rooms) setChartVisibility(trendCanvas, trendEmpty, false);
          return;
        }

        setChartVisibility(trendCanvas, trendEmpty, true);
        const label = rooms ? 'Room bookings' : 'Approved bookings';

        if (!trendChart) {
          trendChart = new Chart(trendCanvas.getContext('2d'), {
            type: 'line',
            data: {
              labels: yearLabels,
              datasets: [{
                label,
                data: counts,
                borderColor: '#4457ab',
                backgroundColor: 'rgba(68, 87, 171, 0.12)',
                fill: true,
                tension: 0.35,
                pointRadius: 4,
                pointBackgroundColor: '#4457ab'
              }]
            },
            options: lineChartOptions
          });
        } else {
          trendChart.data.labels = yearLabels;
          trendChart.data.datasets[0].label = label;
          trendChart.data.datasets[0].data = counts;
          trendChart.update();
        }

        requestAnimationFrame(() => trendChart && trendChart.resize());
      };

      const ensureTopItemsChart = () => {
        if (!topItemsCanvas || shareItems.length === 0) return;
        if (topItemsChart) {
          topItemsChart.resize();
          return;
        }

        topItemsChart = new Chart(topItemsCanvas.getContext('2d'), {
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
          options: doughnutOptions('unit(s)')
        });
      };

      const ensureRoomShareChart = () => {
        if (!roomShareCanvas || roomShareItems.length === 0) return;
        if (roomShareChart) {
          roomShareChart.resize();
          return;
        }

        roomShareChart = new Chart(roomShareCanvas.getContext('2d'), {
          type: 'doughnut',
          data: {
            labels: roomShareItems.map((item) => item.label),
            datasets: [{
              data: roomShareItems.map((item) => item.count),
              backgroundColor: sharePalette.slice(0, roomShareItems.length),
              borderColor: sharePalette.slice(0, roomShareItems.length),
              borderWidth: 0,
              hoverBorderWidth: 0,
              hoverOffset: 4
            }]
          },
          options: doughnutOptions('booking(s)')
        });
      };

      const activeAnalyticsView = () => (
        document.body.classList.contains('analytics-view-rooms') ? 'rooms' : 'equipment'
      );

      const startingView = activeAnalyticsView();
      renderComparison(startingView);
      renderTrend(startingView);

      const monthSelect = document.getElementById('analytics-month-select');
      const yearSelect = document.getElementById('analytics-year-select');
      const compareMonthSelect = document.getElementById('analytics-compare-month');
      const compareYearSelect = document.getElementById('analytics-compare-year');

      const buildAnalyticsUrl = (monthKey, compareKey) => {
        const baseUrl = window.analyticsMonthBaseUrl || '/inventory/analytics';
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

      const viewTabs = Array.from(document.querySelectorAll('.insights-view-tab'));

      const applyAnalyticsView = (view, persist) => {
        const rooms = view === 'rooms';
        document.body.classList.toggle('analytics-view-rooms', rooms);
        viewTabs.forEach((tab) => {
          const selected = tab.dataset.analyticsView === view;
          tab.setAttribute('aria-selected', selected ? 'true' : 'false');
          tab.tabIndex = selected ? 0 : -1;
        });
        document.dispatchEvent(new CustomEvent('analytics-view-changed', { detail: { view } }));
        renderComparison(view);
        renderTrend(view);

        if (rooms) {
          clearHighlights();
          requestAnimationFrame(() => ensureRoomShareChart());
        } else {
          requestAnimationFrame(() => ensureTopItemsChart());
        }

        if (persist) {
          try {
            sessionStorage.setItem('nutilize.analyticsView', view);
          } catch (error) {}
        }
      };

      viewTabs.forEach((tab) => {
        const selected = tab.dataset.analyticsView === startingView;
        tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        tab.tabIndex = selected ? 0 : -1;
        tab.addEventListener('click', () => {
          const view = tab.dataset.analyticsView === 'rooms' ? 'rooms' : 'equipment';
          if (view === activeAnalyticsView()) return;
          applyAnalyticsView(view, true);
        });
      });

      const viewSwitch = document.querySelector('.insights-view-switch');
      if (viewSwitch) {
        viewSwitch.addEventListener('keydown', (event) => {
          if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
          event.preventDefault();
          const view = event.key === 'ArrowRight' ? 'rooms' : 'equipment';
          applyAnalyticsView(view, true);
          document.getElementById(view === 'rooms' ? 'analytics-view-rooms' : 'analytics-view-equipment')?.focus();
        });
      }

      if (startingView === 'rooms') {
        requestAnimationFrame(() => ensureRoomShareChart());
      } else {
        ensureTopItemsChart();
      }
    })();
  </script>

  <script src="/js/equipment-demand.js?v={{ filemtime(public_path('js/equipment-demand.js')) }}"></script>
  <script src="/js/dashboard.js?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
</body>
</html>
