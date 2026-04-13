<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />  <meta name="csrf-token" content="{{ csrf_token() }}" />  <title>NUtilize | Inventory Facilities</title>

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

      <section class="content-card facilities-content-card">
        <h1 class="section-title">FACILITIES INVENTORY</h1>

        <section class="facilities-filter-row">
          <div class="facilities-tab-group" role="tablist" aria-label="Inventory category">
            <button class="facilities-tab active" type="button" data-tab="rooms">Rooms</button>
            <button class="facilities-tab" type="button" data-tab="lab">Lab</button>
            <button class="facilities-tab" type="button" data-tab="others">Others</button>
          </div>

          <div class="facilities-inline-search">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Search" />
          </div>

          <button class="facilities-add-btn" id="facilities-add-btn" type="button">Add Facilities</button>
        </section>

        <section class="inventory-grid facilities-grid">
          <div class="table-wrap">
            <table class="inventory-table">
              <thead>
                <tr>
                  <th><i class="bi bi-credit-card-2-front-fill"></i> Asset ID</th>
                  <th>Room Number</th>
                  <th>Classification</th>
                  <th>Location</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="facilities-table-body">
                @forelse(($facilityRows ?? []) as $facility)
                <tr data-facility-id="{{ $facility['room_id'] }}" data-facility-category="{{ $facility['classification_key'] }}" data-facility-room-type="{{ $facility['room_type'] }}">
                  <td>{{ $facility['asset_id'] }}</td>
                  <td>{{ $facility['item_name'] }}</td>
                  <td>{{ $facility['classification'] }}</td>
                  <td>{{ $facility['location'] }}</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                @empty
                <tr>
                  <td colspan="5">No rooms found in the database.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </section>
  </main>

  <section class="facilities-modal" id="facilities-edit-modal" aria-hidden="true">
    <div class="facilities-modal-overlay" data-close-modal="true"></div>
    <article class="facilities-modal-card" role="dialog" aria-modal="true" aria-labelledby="facilities-modal-title">
      <div class="facilities-modal-top"></div>
      <div class="facilities-modal-body">
        <h2 id="facilities-modal-title">Add Room/Facility</h2>

        <label class="facilities-field-label" for="facility-item-name">Room/Facility Name</label>
        <input id="facility-item-name" class="facilities-input" type="text" placeholder="Room Number" />

        <div class="facilities-inline-fields">
          <div>
            <label class="facilities-field-label" for="facility-category">Room Type</label>
            <select id="facility-category" class="facilities-input facilities-select">
              <option value="" selected disabled>Select Room Type</option>
              <option value="rooms">Rooms</option>
              <option value="lab">Lab</option>
              <option value="others">Others</option>
            </select>
          </div>
        </div>

        <label class="facilities-field-label" for="facility-upload-input">Upload Room/Facility</label>
        <div class="facilities-upload-row">
          <div class="facilities-upload-text">
            <i class="bi bi-upload"></i>
            <div class="facilities-upload-meta">
              <span id="facility-upload-name">No file selected</span>
              <small class="facilities-upload-hint">JPG,PNG, up to 5MB</small>
            </div>
          </div>
          <input id="facility-upload-input" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" hidden />
          <button type="button" class="facilities-upload-btn" id="facility-upload-btn">Add Room/Facility</button>
        </div>

        <label class="facilities-field-label" for="facility-description">Description</label>
        <textarea id="facility-description" class="facilities-input facilities-textarea" placeholder="Description"></textarea>

        <div class="facilities-modal-actions">
          <button type="button" class="facilities-action-btn cancel" id="facility-cancel-btn">Cancel</button>
          <button type="button" class="facilities-action-btn submit" id="facility-save-btn">Add Room/Facility</button>
        </div>
      </div>
    </article>
  </section>

  <script src="/js/dashboard.js"></script>
</body>
</html>

