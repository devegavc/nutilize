<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Maintenance</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-inventory.css?v={{ filemtime(public_path('css/db-inventory.css')) }}" />
</head>
<body>
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

      <section class="content-card maintenance-content-card pf-maintenance-page">
        <h1 class="section-title">MAINTENANCE DASHBOARD</h1>

        <section class="maintenance-filter-row">
          <div class="maintenance-tab-group pf-maintenance-tabs" role="tablist" aria-label="Maintenance status">
            <button class="maintenance-tab is-active active" type="button" role="tab" data-maintenance-tab="all" aria-pressed="true">All</button>
            <button class="maintenance-tab" type="button" role="tab" data-maintenance-tab="maintenance" aria-pressed="false">Maintenance</button>
            <button class="maintenance-tab" type="button" role="tab" data-maintenance-tab="damaged" aria-pressed="false">Damaged</button>
            <button class="maintenance-tab" type="button" role="tab" data-maintenance-tab="reported" aria-pressed="false">Reported</button>
            <button class="maintenance-tab" type="button" role="tab" data-maintenance-tab="addressed" aria-pressed="false">Addressed</button>
          </div>

          <div class="maintenance-head-actions">
            <button class="history-copy-btn" id="maintenance-copy-btn" type="button">
              <i class="bi bi-share"></i> Send a Copy
            </button>
          </div>
        </section>

        <section class="inventory-grid maintenance-grid">
          <div class="table-wrap">
            <table class="inventory-table maintenance-table pf-maintenance-table is-showing-category-status">
              <thead>
                <tr>
                  <th><i class="bi bi-credit-card-2-front-fill"></i> Asset ID</th>
                  <th>Item Name</th>
                  <th>Reported By</th>
                  <th>Count</th>
                  <th>Date</th>
                  <th class="maintenance-status-head">Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="maintenance-table-body" aria-busy="true">
                <tr class="nutilize-sr-only"><td colspan="7">Loading data...</td></tr>
                <tr class="nutilize-table-skeleton"><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td></tr>
                <tr class="nutilize-table-skeleton"><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td></tr>
                <tr class="nutilize-table-skeleton"><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td></tr>
                <tr class="nutilize-table-skeleton"><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td></tr>
                <tr class="nutilize-table-skeleton"><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td></tr>
                <tr class="nutilize-table-skeleton"><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td><td><span class="nps-line"></span></td></tr>
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </section>
  </main>

  <section class="maintenance-eval-modal" id="maintenance-eval-modal" aria-hidden="true">
    <div class="maintenance-eval-overlay" data-close-maintenance-eval="true"></div>
    <article class="maintenance-eval-card" role="dialog" aria-modal="true" aria-labelledby="maintenance-eval-title">
      <header class="maintenance-eval-header">
        <div>
          <p class="maintenance-eval-kicker">Issue review</p>
          <h2 id="maintenance-eval-title">Maintenance Evaluation</h2>
        </div>
        <button type="button" class="maintenance-eval-close" data-close-maintenance-eval="true" aria-label="Close">
          <i class="bi bi-x-lg"></i>
        </button>
      </header>

      <div class="maintenance-eval-fields">
        <div class="maintenance-eval-field">
          <span class="maintenance-eval-label">Name of Item</span>
          <div class="maintenance-eval-value" id="maintenance-eval-item-name">-</div>
        </div>
        <div class="maintenance-eval-field">
          <span class="maintenance-eval-label">Asset ID</span>
          <div class="maintenance-eval-value" id="maintenance-eval-asset-id">-</div>
        </div>
        <div class="maintenance-eval-field">
          <span class="maintenance-eval-label">Reported By</span>
          <div class="maintenance-eval-value" id="maintenance-eval-reporter">-</div>
        </div>
        <div class="maintenance-eval-field">
          <span class="maintenance-eval-label">Description</span>
          <div class="maintenance-eval-value" id="maintenance-eval-description">-</div>
        </div>
        <div class="maintenance-eval-field">
          <span class="maintenance-eval-label">Reported Items</span>
          <div class="maintenance-eval-reported" id="maintenance-eval-reported-items">-</div>
        </div>
      </div>

      <div class="maintenance-eval-proof" id="maintenance-eval-proof-wrap">
        <div class="maintenance-eval-proof-head">
          <span class="maintenance-eval-proof-label">Attached proof</span>
          <a id="maintenance-eval-proof-link" class="maintenance-eval-proof-open" href="#" target="_blank" rel="noopener noreferrer">Open full image</a>
        </div>
        <div class="maintenance-eval-proof-frame">
          <img id="maintenance-eval-proof-img" src="" alt="Proof of report" />
          <div class="maintenance-eval-proof-fallback" id="maintenance-eval-proof-fallback" hidden>
            <i class="bi bi-image"></i>
            <p>Proof image unavailable</p>
          </div>
        </div>
      </div>

      <div class="maintenance-eval-fields maintenance-eval-fields-follow">
        <div class="maintenance-eval-field" id="maintenance-eval-assessment-field">
          <label class="maintenance-eval-label" for="maintenance-assessment-input">Assessment (Optional)</label>
          <textarea id="maintenance-assessment-input" rows="3" placeholder="Add notes if needed..."></textarea>
        </div>
        <div class="maintenance-eval-field" id="maintenance-eval-status-field" hidden>
          <label class="maintenance-eval-label" for="maintenance-status-select">Status</label>
          <select id="maintenance-status-select">
            <option value="">Choose one</option>
            <option value="maintenance">Maintenance</option>
            <option value="damaged">Damaged</option>
            <option value="good">Good</option>
          </select>
        </div>
      </div>

      <div class="maintenance-eval-actions">
        <button type="button" class="maintenance-modal-btn" id="maintenance-eval-back-btn">Back</button>
        <button type="button" class="maintenance-modal-btn" id="maintenance-eval-settle-btn">Settle</button>
      </div>
    </article>
  </section>

  <div class="history-copy-modal" id="maintenance-copy-modal" aria-hidden="true">
    <div class="history-copy-overlay" data-close-maintenance-copy="true"></div>
    <article class="history-copy-card maintenance-copy-card" role="dialog" aria-modal="true" aria-labelledby="maintenance-copy-title">
      <header class="history-copy-head">
        <h2 id="maintenance-copy-title">Send a Copy</h2>
        <p>Choose how you want to receive the current maintenance report.</p>
      </header>
      <div class="history-copy-options">
        <button class="history-copy-option" id="maintenance-copy-print" type="button">
          <span class="history-copy-option-icon"><i class="bi bi-printer-fill"></i></span>
          <span class="history-copy-option-copy">
            <strong>Print File</strong>
          </span>
        </button>
        <button class="history-copy-option" id="maintenance-copy-email" type="button">
          <span class="history-copy-option-icon"><i class="bi bi-envelope-fill"></i></span>
          <span class="history-copy-option-copy">
            <strong>Send to Email</strong>
          </span>
        </button>
      </div>
      <footer class="history-copy-foot">
        <button class="history-copy-cancel" id="maintenance-copy-cancel" type="button">Cancel</button>
      </footer>
    </article>
  </div>

  <script>
    window.maintenanceRowsByTab = @json($maintenanceRowsByTab ?? ['maintenance' => [], 'damaged' => [], 'reported' => []]);
    // #region agent log
    fetch('http://127.0.0.1:7591/ingest/35e57a72-783b-42fe-bb4e-563f8b0a56b3',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'3877d0'},body:JSON.stringify({sessionId:'3877d0',hypothesisId:'C',location:'dashboard-maintenance.blade.php:payload',message:'maintenance payload in browser',data:{tab:new URLSearchParams(location.search).get('tab'),maintenance:(window.maintenanceRowsByTab.maintenance||[]).length,damaged:(window.maintenanceRowsByTab.damaged||[]).length,reported:(window.maintenanceRowsByTab.reported||[]).length,addressed:(window.maintenanceRowsByTab.addressed||[]).length},timestamp:Date.now(),runId:'pre-fix'})}).catch(()=>{});
    window.addEventListener('error',function(event){fetch('http://127.0.0.1:7591/ingest/35e57a72-783b-42fe-bb4e-563f8b0a56b3',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'3877d0'},body:JSON.stringify({sessionId:'3877d0',hypothesisId:'C',location:'dashboard-maintenance.blade.php:error',message:'page script error',data:{error:String(event.message||''),source:String(event.filename||''),line:event.lineno||0},timestamp:Date.now(),runId:'pre-fix'})}).catch(()=>{});});
    // #endregion
    window.maintenanceUnitsEndpointBase = '{{ url('/maintenance/units') }}';
    window.maintenanceRoomsEndpointBase = '{{ url('/maintenance/rooms') }}';
    window.maintenanceReportsEndpointBase = '{{ url('/maintenance/reports') }}';
  </script>
  <script src="/js/dashboard.js?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
</body>
</html>

