<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Maintenance</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-inventory.css" />
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

      <section class="content-card maintenance-content-card">
        <h1 class="section-title">MAINTENANCE DASHBOARD</h1>

        <section class="maintenance-filter-row">
          <div class="maintenance-tab-group" role="tablist" aria-label="Maintenance status">
            <button class="maintenance-tab active" type="button" data-maintenance-tab="maintenance">Maintenance</button>
            <button class="maintenance-tab" type="button" data-maintenance-tab="damaged">Damaged</button>
          </div>

          <div class="maintenance-inline-search">
            <i class="bi bi-search"></i>
            <input id="maintenance-inline-search" type="text" placeholder="Search" />
          </div>
        </section>

        <section class="inventory-grid maintenance-grid">
          <div class="table-wrap">
            <table class="inventory-table maintenance-table">
              <thead>
                <tr>
                  <th><i class="bi bi-credit-card-2-front-fill"></i> Asset ID</th>
                  <th>Item Name</th>
                  <th>Count</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th>Location</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="maintenance-table-body">
                <tr>
                  <td>#9985fht</td>
                  <td>Podium</td>
                  <td>1</td>
                  <td>31/03/2025</td>
                  <td><span class="maintenance-status maintenance">Maintenance</span></td>
                  <td>Storage A</td>
                  <td><button class="maintenance-action-btn" type="button">Address</button></td>
                </tr>
                <tr>
                  <td>#X9D2k8A</td>
                  <td>HDMI</td>
                  <td>3</td>
                  <td>30/03/2025</td>
                  <td><span class="maintenance-status maintenance">Maintenance</span></td>
                  <td>Storage B</td>
                  <td><button class="maintenance-action-btn" type="button">Address</button></td>
                </tr>
                <tr>
                  <td>#4fHqWZ7</td>
                  <td>Tripod</td>
                  <td>1</td>
                  <td>29/03/2025</td>
                  <td><span class="maintenance-status maintenance">Maintenance</span></td>
                  <td>Storage A</td>
                  <td><button class="maintenance-action-btn" type="button">Address</button></td>
                </tr>
                <tr>
                  <td>#R8A3xM9</td>
                  <td>Lapel Mics</td>
                  <td>2</td>
                  <td>28/03/2025</td>
                  <td><span class="maintenance-status maintenance">Maintenance</span></td>
                  <td>Storage A</td>
                  <td><button class="maintenance-action-btn" type="button">Address</button></td>
                </tr>
                <tr>
                  <td>#3Fq8Dk2</td>
                  <td>Speaker</td>
                  <td>1</td>
                  <td>27/03/2025</td>
                  <td><span class="maintenance-status maintenance">Maintenance</span></td>
                  <td>Storage A</td>
                  <td><button class="maintenance-action-btn" type="button">Address</button></td>
                </tr>
                <tr>
                  <td>#9A7MzxQ</td>
                  <td>Tripod</td>
                  <td>2</td>
                  <td>28/03/2025</td>
                  <td><span class="maintenance-status maintenance">Maintenance</span></td>
                  <td>Storage A</td>
                  <td><button class="maintenance-action-btn" type="button">Address</button></td>
                </tr>
                <tr>
                  <td>#W5DkF8R</td>
                  <td>Camera</td>
                  <td>1</td>
                  <td>26/03/2025</td>
                  <td><span class="maintenance-status maintenance">Maintenance</span></td>
                  <td>Storage A</td>
                  <td><button class="maintenance-action-btn" type="button">Address</button></td>
                </tr>
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
      <h2 id="maintenance-eval-title">Maintenance Evaluation</h2>

      <div class="maintenance-eval-grid">
        <span>Name of Item:</span>
        <span id="maintenance-eval-item-name">-</span>

        <span>Maintenance Reason:</span>
        <span id="maintenance-eval-reason">10x Used</span>
      </div>

      <div class="maintenance-eval-actions">
        <button type="button" class="maintenance-modal-btn" id="maintenance-eval-back-btn">Back</button>
        <button type="button" class="maintenance-modal-btn" id="maintenance-eval-settle-btn">Settle</button>
      </div>
    </article>
  </section>

  <section class="maintenance-form-modal" id="maintenance-form-modal" aria-hidden="true">
    <div class="maintenance-form-overlay" data-close-maintenance-form="true"></div>
    <article class="maintenance-form-card" role="dialog" aria-modal="true" aria-labelledby="maintenance-form-title">
      <h2 id="maintenance-form-title">Maintenance Evaluation</h2>

      <div class="maintenance-form-grid">
        <span>Name of Item:</span>
        <span id="maintenance-form-item-name">-</span>

        <label for="maintenance-assessment-input">Assessment:</label>
        <textarea id="maintenance-assessment-input" rows="3" placeholder="Input text here..."></textarea>

        <label for="maintenance-status-select">Status</label>
        <select id="maintenance-status-select">
          <option value="">Choose one</option>
          <option value="maintenance">Maintenance</option>
          <option value="damaged">Damaged</option>
          <option value="fixed">Fixed</option>
        </select>
      </div>

      <div class="maintenance-form-actions">
        <button type="button" class="maintenance-modal-btn" id="maintenance-form-submit-btn">Submit</button>
      </div>
    </article>
  </section>

  <script>
    window.maintenanceRowsByTab = @json($maintenanceRowsByTab ?? ['maintenance' => [], 'damaged' => []]);
  </script>
  <script src="/js/dashboard.js"></script>
</body>
</html>

