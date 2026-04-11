<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />  <meta name="csrf-token" content="{{ csrf_token() }}" />  <title>NUtilize | Inventory Equipment</title>

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
        <h1 class="section-title">EQUIPMENT INVENTORY</h1>

        <section class="facilities-filter-row">
          <div class="facilities-tab-group" role="tablist" aria-label="Equipment category">
            <button class="facilities-tab active" type="button" data-equipment-tab="multimedia">Multimedia</button>
            <button class="facilities-tab" type="button" data-equipment-tab="electronics">Electronics</button>
            <button class="facilities-tab" type="button" data-equipment-tab="utility">Utility</button>
          </div>

          <div class="facilities-inline-search">
            <i class="bi bi-search"></i>
            <input id="equipment-inline-search" type="text" placeholder="Search" />
          </div>

          <button class="facilities-add-btn" id="equipment-add-btn" type="button">Add Equipment</button>
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
                  <th>Location</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="equipment-table-body">
                <tr data-equipment-row="multimedia">
                  <td>#9985fht</td>
                  <td>Projectors</td>
                  <td>6</td>
                  <td>4</td>
                  <td><span class="status-pill good">Good</span></td>
                  <td>Storage A</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="multimedia">
                  <td>#X9D2k8A</td>
                  <td>Wireless Mic</td>
                  <td>8</td>
                  <td>2</td>
                  <td><span class="status-pill good">Good</span></td>
                  <td>Storage A</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="multimedia">
                  <td>#4fHqWZ7</td>
                  <td>Wired Mic</td>
                  <td>4</td>
                  <td>1</td>
                  <td><span class="status-pill good">Good</span></td>
                  <td>Storage A</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="multimedia">
                  <td>#R8A3xM9</td>
                  <td>Lapel Mics</td>
                  <td>13</td>
                  <td>1</td>
                  <td><span class="status-pill good">Good</span></td>
                  <td>Storage A</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="multimedia">
                  <td>#3Fq8Dk2</td>
                  <td>Speaker</td>
                  <td>4</td>
                  <td>3</td>
                  <td><span class="status-pill good">Good</span></td>
                  <td>Storage A</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="multimedia">
                  <td>#9A7MzxQ</td>
                  <td>Tripod</td>
                  <td>3</td>
                  <td>2</td>
                  <td><span class="status-pill maintenance">Maintenance</span></td>
                  <td>Storage A</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="multimedia">
                  <td>#W5DkF8R</td>
                  <td>Camera</td>
                  <td>5</td>
                  <td>1</td>
                  <td><span class="status-pill good">Good</span></td>
                  <td>Storage A</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="electronics">
                  <td>#gThr31</td>
                  <td>HDMI</td>
                  <td>15</td>
                  <td>4</td>
                  <td><span class="status-pill good">Good</span></td>
                  <td>Storage B</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="electronics">
                  <td>#VhT1lJk</td>
                  <td>Wireless Clickers</td>
                  <td>5</td>
                  <td>2</td>
                  <td><span class="status-pill good">Good</span></td>
                  <td>Storage B</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="electronics">
                  <td>#78jgFhI</td>
                  <td>Extension Cords</td>
                  <td>9</td>
                  <td>8</td>
                  <td><span class="status-pill good">Good</span></td>
                  <td>Storage B</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="electronics">
                  <td>#HjsDr44</td>
                  <td>Power Strip</td>
                  <td>10</td>
                  <td>7</td>
                  <td><span class="status-pill good">Good</span></td>
                  <td>Storage B</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="electronics">
                  <td>#Ty667qI</td>
                  <td>Port Dongles</td>
                  <td>1</td>
                  <td>0</td>
                  <td><span class="status-pill damaged">Damaged</span></td>
                  <td>Storage B</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="utility">
                  <td>#Yu667B</td>
                  <td>Triangular Tables</td>
                  <td>50</td>
                  <td>33</td>
                  <td><span class="status-pill good">Good</span></td>
                  <td>Storage C</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="utility">
                  <td>#4CfftyZ</td>
                  <td>Monoblock Chairs</td>
                  <td>145</td>
                  <td>71</td>
                  <td><span class="status-pill good">Good</span></td>
                  <td>Storage C</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="utility">
                  <td>#KklbVG</td>
                  <td>Industrial Fans</td>
                  <td>15</td>
                  <td>8</td>
                  <td><span class="status-pill good">Good</span></td>
                  <td>Storage C</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr data-equipment-row="utility">
                  <td>#KTVpl5</td>
                  <td>Podiums</td>
                  <td>1</td>
                  <td>0</td>
                  <td><span class="status-pill good">Good</span></td>
                  <td>Storage C</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
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
        <h2 id="equipment-modal-title">Add Equipment</h2>

        <label class="facilities-field-label" for="equipment-item-name">Item Name</label>
        <input id="equipment-item-name" class="facilities-input" type="text" placeholder="Item Name" />

        <div class="facilities-inline-fields">
          <div>
            <label class="facilities-field-label" for="equipment-category">Category</label>
            <select id="equipment-category" class="facilities-input facilities-select">
              <option value="" selected disabled>Select Category</option>
              <option value="multimedia">Multimedia</option>
              <option value="electronics">Electronics</option>
              <option value="utility">Utility</option>
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

        <label class="facilities-field-label" for="equipment-location">Location</label>
        <select id="equipment-location" class="facilities-input facilities-select">
          <option value="" selected disabled>Select Location</option>
          <option value="Storage A">Storage A</option>
          <option value="Storage B">Storage B</option>
          <option value="Storage C">Storage C</option>
        </select>

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
          <button type="button" class="facilities-action-btn cancel" id="equipment-cancel-btn">Cancel</button>
          <button type="button" class="facilities-action-btn submit" id="equipment-save-btn">Add Item</button>
        </div>
      </div>
    </article>
  </section>

  <script src="/js/dashboard.js"></script>
</body>
</html>

