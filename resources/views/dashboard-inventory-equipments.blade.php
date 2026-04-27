<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />  <title>NUtilize | Inventory Equipment</title>

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
    window.equipmentCategoryCreateEndpoint = '{{ route('dashboard.inventory.equipment-categories.store') }}';
    window.equipmentCategoryUpdateEndpointBase = '{{ url('/dashboard/inventory/equipment-categories') }}';
    window.equipmentCategoryDeleteEndpointBase = '{{ url('/dashboard/inventory/equipment-categories') }}';
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
        <h1 class="section-title">EQUIPMENT INVENTORY</h1>

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

          <div class="facilities-action-group">
            <button class="facilities-add-category-btn" id="equipment-add-category-btn" type="button">Add Category</button>
            <button class="facilities-add-btn" id="equipment-add-btn" type="button">Add Equipment</button>
          </div>
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
                  <td colspan="6">No equipment records found in the database.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </section>
  </main>

  <div class="inventory-confirm-modal" id="inventory-confirm-modal" aria-hidden="true">
    <div class="inventory-confirm-overlay" data-close-inventory-confirm="true"></div>
    <article class="inventory-confirm-card" role="dialog" aria-modal="true" aria-labelledby="inventory-confirm-title">
      <header class="inventory-confirm-head">
        <h2 id="inventory-confirm-title">Confirm Delete</h2>
      </header>
      <div class="inventory-confirm-body">
        <p id="inventory-confirm-message">Are you sure you want to delete this item? This cannot be undone.</p>
      </div>
      <div class="inventory-confirm-actions">
        <button type="button" class="inventory-confirm-btn cancel" id="inventory-confirm-cancel">Cancel</button>
        <button type="button" class="inventory-confirm-btn delete" id="inventory-confirm-submit">Delete</button>
      </div>
    </article>
  </div>

  <section class="facilities-modal" id="equipment-edit-modal" aria-hidden="true">
    <div class="facilities-modal-overlay" data-close-equipment-modal="true"></div>
    <article class="facilities-modal-card" role="dialog" aria-modal="true" aria-labelledby="equipment-modal-title">
      <div class="facilities-modal-top"></div>
      <div class="facilities-modal-body">
        <h2 id="equipment-modal-title">Add Equipment</h2>

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

  <section class="facilities-modal" id="equipment-category-modal" aria-hidden="true">
    <div class="facilities-modal-overlay" data-close-equipment-category-modal="true"></div>
    <article class="facilities-modal-card equipment-category-modal-card" role="dialog" aria-modal="true" aria-labelledby="equipment-category-modal-title">
      <div class="facilities-modal-top"></div>
      <div class="facilities-modal-body">
        <h2 id="equipment-category-modal-title">Add Category</h2>

        <label class="facilities-field-label" for="equipment-category-name-input">Category Name</label>
        <input id="equipment-category-name-input" class="facilities-input" type="text" placeholder="e.g. Audio-Visual" maxlength="100" />

        <p class="equipment-category-list-title">Existing Categories</p>
        <ul id="equipment-category-list" class="equipment-category-list" aria-live="polite">
          @forelse(($equipmentCategories ?? []) as $category)
            <li class="equipment-category-list-item" data-category-id="{{ $category['id'] }}" data-category-key="{{ $category['key'] }}">
              <span class="equipment-category-list-label">{{ $category['label'] }}</span>
              <div class="equipment-category-list-actions">
                <button type="button" class="equipment-category-rename-btn" data-rename-equipment-category-id="{{ $category['id'] }}">Rename</button>
                <button type="button" class="equipment-category-delete-btn" data-delete-equipment-category-id="{{ $category['id'] }}" data-delete-equipment-category-key="{{ $category['key'] }}">Delete</button>
              </div>
            </li>
          @empty
            <li class="equipment-category-list-empty">No categories yet.</li>
          @endforelse
        </ul>

        <div class="facilities-modal-actions">
          <button type="button" class="facilities-action-btn cancel" id="equipment-category-cancel-btn">Cancel</button>
          <button type="button" class="facilities-action-btn submit" id="equipment-category-save-btn">Add Category</button>
        </div>
      </div>
    </article>
  </section>

  <script src="/js/dashboard.js"></script>
</body>
</html>

