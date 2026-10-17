<section id="equipment-demand" class="equipment-demand-panel" data-analytics-panel="equipment" aria-label="Equipment demand">
  <div class="equipment-demand-toolbar">
    <div class="equipment-demand-modes" role="tablist" aria-label="Demand period">
      <button type="button" class="equipment-demand-mode" data-demand-mode="month" role="tab">Monthly</button>
      <button type="button" class="equipment-demand-mode" data-demand-mode="term" role="tab">Per term</button>
    </div>

    <div class="equipment-demand-filters" data-demand-controls="month">
      <label for="equipment-demand-month">Month</label>
      <select id="equipment-demand-month" aria-label="Equipment demand month"></select>
      <select id="equipment-demand-year" aria-label="Equipment demand year"></select>
    </div>

    <div class="equipment-demand-filters" data-demand-controls="term" hidden>
      <label for="equipment-demand-academic-year">Academic year</label>
      <select id="equipment-demand-academic-year" aria-label="Academic year"></select>
      <label for="equipment-demand-term">Term</label>
      <select id="equipment-demand-term" aria-label="Academic term"></select>
      <span id="equipment-demand-term-length" class="equipment-demand-term-length"></span>
      <button type="button" class="equipment-demand-text-btn" id="equipment-demand-add-term">Add academic year</button>
      <button type="button" class="equipment-demand-text-btn" id="equipment-demand-edit-term">Edit term</button>
    </div>
  </div>

  <form id="equipment-term-form" class="equipment-term-form" hidden>
    <h2 id="equipment-term-form-title">Add academic year</h2>
    <p id="equipment-term-form-help">This creates 1st, 2nd, and 3rd Term and keeps the same match for every year. You only adjust dates when the calendar moves.</p>
    <div class="equipment-term-fields" data-year-fields>
      <label>Academic year
        <input type="text" name="academic_year" maxlength="9" required placeholder="2027-2028" autocomplete="off" />
      </label>
    </div>
    <div data-date-fields hidden>
      <p id="equipment-term-date-note" class="equipment-demand-note"></p>
      <div class="equipment-term-fields">
        <label>Starts
          <input type="date" name="starts_on" />
        </label>
        <label>Ends
          <input type="date" name="ends_on" />
        </label>
      </div>
      <p class="equipment-demand-note">Leave a date empty when the exact day is not known yet. The break before the next term stays inside the term that just ended.</p>
    </div>
    <div class="equipment-term-actions">
      <button type="submit" class="equipment-demand-text-btn is-primary" id="equipment-term-submit">Save</button>
      <button type="button" class="equipment-demand-text-btn" id="equipment-term-cancel">Cancel</button>
    </div>
    <p id="equipment-term-form-error" class="equipment-demand-error" hidden></p>
  </form>

  <div id="equipment-demand-loading" class="insights-loading-skeleton equipment-demand-loading" role="status" aria-live="polite" aria-busy="true" aria-label="Loading equipment demand">
    <div class="ils-kpis">
      <div class="ils-card ils-kpi"><i class="ils-icon"></i><div><b></b><span class="ils-line ils-w-56 ils-thin"></span></div></div>
      <div class="ils-card ils-kpi"><i class="ils-icon"></i><div><b></b><span class="ils-line ils-w-56 ils-thin"></span></div></div>
      <div class="ils-card ils-kpi"><i class="ils-icon"></i><div><b></b><span class="ils-line ils-w-56 ils-thin"></span></div></div>
      <div class="ils-card ils-kpi"><i class="ils-icon"></i><div><b></b><span class="ils-line ils-w-56 ils-thin"></span></div></div>
    </div>
    <div class="ils-card ils-table">
      <div class="ils-table-head"></div>
      <div class="ils-table-row"></div>
      <div class="ils-table-row"></div>
      <div class="ils-table-row"></div>
    </div>
  </div>

  <div id="equipment-demand-error" class="equipment-demand-banner" role="alert" hidden>
    <p id="equipment-demand-error-text">The equipment report could not be loaded.</p>
    <button type="button" class="equipment-demand-text-btn" id="equipment-demand-retry">Retry</button>
  </div>

  <div id="equipment-demand-empty" class="equipment-demand-empty" hidden>
    <h2 id="equipment-demand-empty-title">No equipment demand for this period</h2>
    <p id="equipment-demand-empty-copy"></p>
  </div>

  <div id="equipment-demand-results" hidden>
    <p id="equipment-demand-period-note" class="equipment-demand-note"></p>

    <section class="insight-kpi-strip equipment-demand-kpis" aria-label="Equipment demand summary">
      <div class="insight-kpi equipment-demand-kpi">
        <span class="insight-kpi-icon"><i class="bi bi-calendar2-check-fill"></i></span>
        <div class="insight-kpi-body">
          <strong id="equipment-summary-reservations">—</strong>
          <span class="insight-kpi-label">Approved equipment reservations</span>
          <em class="insight-kpi-hint">Distinct approved bookings with equipment</em>
        </div>
      </div>
      <div class="insight-kpi equipment-demand-kpi">
        <span class="insight-kpi-icon"><i class="bi bi-box-seam"></i></span>
        <div class="insight-kpi-body">
          <strong id="equipment-summary-types">—</strong>
          <span class="insight-kpi-label">Equipment types</span>
          <em class="insight-kpi-hint">Distinct items in those reservations</em>
        </div>
      </div>
      <div class="insight-kpi equipment-demand-kpi">
        <span class="insight-kpi-icon"><i class="bi bi-check2-square"></i></span>
        <div class="insight-kpi-body">
          <strong id="equipment-summary-returned">—</strong>
          <span class="insight-kpi-label">Marked returned</span>
          <em class="insight-kpi-hint" id="equipment-summary-returned-hint">Checkout quantity is not recorded</em>
        </div>
      </div>
      <div class="insight-kpi equipment-demand-kpi">
        <span class="insight-kpi-icon"><i class="bi bi-trophy"></i></span>
        <div class="insight-kpi-body">
          <strong id="equipment-summary-top">—</strong>
          <span class="insight-kpi-label">Most frequently reserved</span>
          <em class="insight-kpi-hint" id="equipment-summary-top-hint">By distinct approved reservations</em>
        </div>
      </div>
    </section>

    <section class="inventory-grid analytics-table-grid insight-section equipment-demand-section">
      <div class="inventory-grid-head analytics-grid-head insight-head">
        <div>
          <h2>Most in-demand items</h2>
          <p>Approved demand is the planning basis. Current quantity is the number of serviceable units on hand.</p>
        </div>
        <div class="equipment-demand-rank" role="group" aria-label="Rank items">
          <button type="button" class="equipment-demand-text-btn" data-demand-rank="reservations">By reservations</button>
          <button type="button" class="equipment-demand-text-btn" data-demand-rank="quantity">By approved quantity</button>
        </div>
      </div>
      <div class="table-wrap">
        <table class="inventory-table analytics-table">
          <thead>
            <tr>
              <th>Item</th>
              <th>Approved reservations</th>
              <th>Approved quantity</th>
              <th>Current quantity</th>
            </tr>
          </thead>
          <tbody id="equipment-demand-items"></tbody>
        </table>
      </div>
    </section>

    <section class="insight-panel equipment-demand-chart-panel">
      <div class="equipment-demand-chart-head">
        <div>
          <h2 class="insight-card-title" id="equipment-demand-chart-title">Weekly demand</h2>
          <p class="insight-panel-hint" id="equipment-weekly-note"></p>
        </div>
        <label class="equipment-demand-item-filter">Item
          <select id="equipment-weekly-item" aria-label="Item for weekly quantity"></select>
        </label>
      </div>
      <p class="insight-panel-hint" id="equipment-weekly-unit"></p>
      <div class="insight-chart-frame equipment-weekly-frame">
        <canvas id="equipment-weekly-chart" aria-label="Weekly equipment demand"></canvas>
      </div>
    </section>

    <section class="inventory-grid analytics-table-grid insight-section equipment-demand-section">
      <div class="inventory-grid-head analytics-grid-head insight-head">
        <div>
          <h2>Peak demand and current stock</h2>
          <p>Peak is the most equipment scheduled at the same time. Stock is current serviceable units, not a historical balance.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table class="inventory-table analytics-table">
          <thead>
            <tr>
              <th>Item</th>
              <th>Peak simultaneous quantity</th>
              <th>Current serviceable stock</th>
              <th>Comparison</th>
            </tr>
          </thead>
          <tbody id="equipment-demand-availability"></tbody>
        </table>
      </div>
    </section>

    <section class="insight-panel equipment-demand-insights">
      <h2 class="insight-card-title">What the figures show</h2>
      <ul id="equipment-demand-insights"></ul>
    </section>
  </div>

  <section id="equipment-planning" class="equipment-demand-section inventory-grid analytics-table-grid insight-section" hidden>
    <div class="inventory-grid-head analytics-grid-head insight-head">
      <div>
        <h2>Planning for the next matching term</h2>
        <p id="equipment-planning-message"></p>
      </div>
    </div>
    <p id="equipment-planning-target" class="equipment-demand-note"></p>
    <p id="equipment-planning-references" class="equipment-demand-note"></p>
    <p id="equipment-planning-detail" class="equipment-demand-note"></p>
    <div id="equipment-planning-visual" class="equipment-planning-visual"></div>
    <div class="table-wrap" id="equipment-planning-table">
      <table class="inventory-table analytics-table">
        <thead>
          <tr>
            <th>Item</th>
            <th>Historical reservations</th>
            <th>Historical peak</th>
            <th>Current serviceable stock</th>
            <th>Historical planning gap</th>
            <th>Confirmed peak</th>
              <th>Confirmed booking gap</th>
            </tr>
        </thead>
        <tbody id="equipment-planning-items">        </tbody>
      </table>
    </div>
    <details class="equipment-demand-definitions">
      <summary>How this planning figure is calculated</summary>
      <p id="equipment-planning-basis"></p>
      <p id="equipment-planning-unmet"></p>
    </details>
  </section>

  <details class="equipment-demand-definitions">
    <summary>How these figures are counted</summary>
    <ul id="equipment-demand-definition-list"></ul>
  </details>
</section>

<script type="application/json" id="equipment-demand-config">
{!! json_encode([
    'dashboardUrl' => route('dashboard.inventory.analytics.equipment'),
    'storeYearUrl' => route('dashboard.academic-years.store'),
    'updateTermUrl' => url('/inventory/academic-terms/__ID__'),
    'monthKey' => $monthKey,
    'csrf' => csrf_token(),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}
</script>
