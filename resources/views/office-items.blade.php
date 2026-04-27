<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Manage Items</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-inventory.css" />
  <style>
    .office-items-subtitle {
      margin: 10px 22px 0;
      color: #4b567f;
      font-size: 0.95rem;
      font-weight: 500;
      line-height: 1.3;
    }
  </style>
</head>
<body>
  <script>
    window.authUser = {
      id: {{ auth()->user()->user_id ?? 'null' }},
      username: '{{ auth()->user()->username ?? 'User' }}',
      email: '{{ auth()->user()->email ?? '' }}',
      full_name: '{{ auth()->user()->full_name ?? auth()->user()->username ?? 'User' }}',
      role: '{{ auth()->user()->role ?? 'user' }}',
      office_name: '{{ auth()->user()?->office?->department_name ?? 'Office' }}',
      office_short_code: '{{ auth()->user()?->office?->short_code ?? '' }}'
    };
    window.dashboardNavComponent = '/components/navbar-office.html';
    window.equipmentEndpointBase = '/dashboard/office/items';
    window.equipmentCategories = @json($equipmentCategories ?? []);
    window.defaultEquipmentCategory = '{{ $defaultEquipmentCategory ?? '' }}';
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

      <section class="content-card facilities-content-card">
        <h1 class="section-title">MANAGE OFFICE ITEMS</h1>
        <p class="office-items-subtitle">Manage equipment assigned to {{ auth()->user()?->office?->department_name ?? 'your office' }}</p>

        <section class="facilities-filter-row">
          <div class="facilities-tab-group" role="tablist" aria-label="Equipment category">
            @foreach(($equipmentCategories ?? []) as $index => $category)
              <button class="facilities-tab {{ $index === 0 ? 'active' : '' }}" type="button" data-equipment-tab="{{ $category['key'] }}">{{ $category['label'] }}</button>
            @endforeach
          </div>

          <div class="facilities-inline-search">
            <i class="bi bi-search"></i>
            <input id="equipment-inline-search" type="text" placeholder="Search" />
          </div>

          <button class="facilities-add-btn" id="equipment-add-btn" type="button">Add Item</button>
        </section>

        <section class="inventory-grid facilities-grid">
          <div class="table-wrap">
            <table class="inventory-table">
              <thead>
                <tr>
                  <th><i class="bi bi-credit-card-2-front-fill"></i> Asset ID</th>
                  <th>Item Name</th>
                  <th>Total Count</th>
                  <th>In Use</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="equipment-table-body">
                @forelse(($equipmentRows ?? []) as $equipment)
                <tr data-equipment-row="{{ $equipment['category'] }}" data-item-id="{{ $equipment['item_id'] }}">
                  <td>{{ $equipment['asset_id'] }}</td>
                  <td>{{ $equipment['item_name'] }}</td>
                  <td>{{ $equipment['total_count'] }}</td>
                  <td>{{ $equipment['in_use'] }}</td>
                  <td><span class="status-pill {{ $equipment['status_key'] }}">{{ $equipment['status_label'] }}</span></td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                @empty
                <tr data-equipment-row="{{ $defaultEquipmentCategory ?? '' }}">
                  <td colspan="6">No items found for your office yet.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </section>
  </main>

  <section class="facilities-modal" id="equipment-edit-modal" aria-hidden="true">
    <div class="facilities-modal-overlay" data-close-equipment-modal="true"></div>
    <article class="facilities-modal-card" role="dialog" aria-modal="true" aria-labelledby="equipment-modal-title">
      <div class="facilities-modal-top"></div>
      <div class="facilities-modal-body">
        <h2 id="equipment-modal-title">Add Item</h2>

        <label class="facilities-field-label" for="equipment-item-name">Item Name</label>
        <input id="equipment-item-name" class="facilities-input" type="text" placeholder="Item Name" />

        <div class="facilities-inline-fields">
          <div>
            <label class="facilities-field-label" for="equipment-category">Category</label>
            <select id="equipment-category" class="facilities-input facilities-select">
              <option value="" selected disabled>Select Category</option>
              @foreach(($equipmentCategories ?? []) as $category)
                <option value="{{ $category['key'] }}">{{ $category['label'] }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="facilities-field-label" for="equipment-total-count">Total Count</label>
            <input id="equipment-total-count" class="facilities-input" type="number" min="0" value="1" />
          </div>
        </div>

        <div class="facilities-inline-fields">
          <div>
            <label class="facilities-field-label" for="equipment-in-use">In Use</label>
            <input id="equipment-in-use" class="facilities-input" type="number" min="0" value="0" />
          </div>
          <div>
            <label class="facilities-field-label" for="equipment-status">Status</label>
            <select id="equipment-status" class="facilities-input facilities-select">
              <option value="" selected disabled>Select Status</option>
              <option value="good">Good</option>
              <option value="maintenance">Maintenance</option>
              <option value="damaged">Damaged</option>
            </select>
          </div>
        </div>

        <label class="facilities-field-label" for="equipment-upload-input">Upload Item</label>
        <div class="facilities-upload-row">
          <div class="facilities-upload-text">
            <i class="bi bi-upload"></i>
            <div class="facilities-upload-meta">
              <span id="equipment-upload-name">No file selected</span>
              <small class="facilities-upload-hint">JPG,PNG, up to 5MB</small>
            </div>
          </div>
          <input id="equipment-upload-input" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" hidden />
          <button type="button" class="facilities-upload-btn" id="equipment-upload-btn">Add Item</button>
        </div>

        <label class="facilities-field-label" for="equipment-description">Description</label>
        <textarea id="equipment-description" class="facilities-input facilities-textarea" placeholder="Description"></textarea>

        <div class="facilities-modal-actions">
          <button type="button" class="facilities-action-btn delete" id="equipment-delete-btn" hidden>Delete</button>
          <button type="button" class="facilities-action-btn cancel" id="equipment-cancel-btn">Cancel</button>
          <button type="button" class="facilities-action-btn submit" id="equipment-save-btn">Add Item</button>
        </div>
      </div>
    </article>
  </section>

  <script src="/js/dashboard.js"></script>
</body>
</html>
