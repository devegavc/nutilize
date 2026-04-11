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
                  <th>Item Name</th>
                  <th>Clasification</th>
                  <th>Total Count</th>
                  <th>Location</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="facilities-table-body">
                <tr>
                  <td>#74fAy51</td>
                  <td>Room 501</td>
                  <td>Facilities</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#X9D2k8A</td>
                  <td>Room 502</td>
                  <td>Facilities</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#4fHqWZ7</td>
                  <td>Room 503</td>
                  <td>Facilities</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#R8A3xM9</td>
                  <td>Room 504</td>
                  <td>Facilities</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#3Fq8Dk2</td>
                  <td>Room 505</td>
                  <td>Facilities</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#9A7MzxQ</td>
                  <td>Room 506</td>
                  <td>Facilities</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#W5DkF8R</td>
                  <td>Room 507</td>
                  <td>Facilities</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#X2q9A7M</td>
                  <td>Room 508</td>
                  <td>Facilities</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#P7Lm2Qx</td>
                  <td>Room 509</td>
                  <td>Facilities</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#K1Dz9Rv</td>
                  <td>Room 510</td>
                  <td>Facilities</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#N6Qw4By</td>
                  <td>Room 511</td>
                  <td>Facilities</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#T3Va8Hp</td>
                  <td>Room 512</td>
                  <td>Facilities</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#B4Yk7Jn</td>
                  <td>Room 630</td>
                  <td>Facilities/Computer Lab</td>
                  <td>1</td>
                  <td>Sixth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#H8Ms3Qc</td>
                  <td>Room 631</td>
                  <td>Facilities/Computer Lab</td>
                  <td>1</td>
                  <td>Sixth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#J2Rx5Wu</td>
                  <td>Room 632</td>
                  <td>Facilities/Computer Lab</td>
                  <td>1</td>
                  <td>Sixth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#Q9Nt6Zm</td>
                  <td>Room 633</td>
                  <td>Facilities/Computer Lab</td>
                  <td>1</td>
                  <td>Sixth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#V5Cg1Ls</td>
                  <td>Room 615</td>
                  <td>Facilities/Clinical Lab</td>
                  <td>1</td>
                  <td>Sixth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#M7Pb4Xe</td>
                  <td>Room 616</td>
                  <td>Facilities/Chem Lab</td>
                  <td>1</td>
                  <td>Sixth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#D1Kw8Tf</td>
                  <td>Room 617</td>
                  <td>Facilities/Chem Lab</td>
                  <td>1</td>
                  <td>Sixth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#L3Yq2An</td>
                  <td>Room 618</td>
                  <td>Facilities/Chem Lab</td>
                  <td>1</td>
                  <td>Sixth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#Z8Hp6Md</td>
                  <td>Gym</td>
                  <td>Gymnasium</td>
                  <td>1</td>
                  <td>Sixth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#C3Vn1Sk</td>
                  <td>AVR</td>
                  <td>Events Place</td>
                  <td>1</td>
                  <td>Sixth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#A6Ry4Lp</td>
                  <td>Library</td>
                  <td>Library</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#F2Gt9Nv</td>
                  <td>Canteen</td>
                  <td>Canteen</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#P4Kx8Te</td>
                  <td>Student Lounge</td>
                  <td>Lounge</td>
                  <td>1</td>
                  <td>Fifth Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
                <tr>
                  <td>#U1Wm3Qd</td>
                  <td>Ground</td>
                  <td>Ground</td>
                  <td>1</td>
                  <td>Ground Floor</td>
                  <td><button class="table-edit-btn" type="button">Edit</button></td>
                </tr>
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
        <h2 id="facilities-modal-title">Add Item</h2>

        <label class="facilities-field-label" for="facility-item-name">Item/facility Name</label>
        <input id="facility-item-name" class="facilities-input" type="text" placeholder="Item Name" />

        <div class="facilities-inline-fields">
          <div>
            <label class="facilities-field-label" for="facility-category">Category</label>
            <select id="facility-category" class="facilities-input facilities-select">
              <option value="" selected disabled>Select Category</option>
              <option value="Facilities">Facilities</option>
              <option value="Facilities/Computer Lab">Facilities/Computer Lab</option>
              <option value="Facilities/Clinical Lab">Facilities/Clinical Lab</option>
              <option value="Facilities/Chem Lab">Facilities/Chem Lab</option>
              <option value="Gymnasium">Gymnasium</option>
              <option value="Events Place">Events Place</option>
              <option value="Library">Library</option>
              <option value="Canteen">Canteen</option>
              <option value="Lounge">Lounge</option>
            </select>
          </div>
          <div>
            <label class="facilities-field-label" for="facility-quantity">Quantity</label>
            <div class="facilities-qty-wrap">
              <button type="button" class="facilities-qty-btn" id="facility-qty-minus">-</button>
              <input id="facility-quantity" class="facilities-qty-input" type="number" min="0" value="1" />
              <button type="button" class="facilities-qty-btn" id="facility-qty-plus">+</button>
            </div>
          </div>
        </div>

        <label class="facilities-field-label" for="facility-location">Location</label>
        <select id="facility-location" class="facilities-input facilities-select">
          <option value="" selected disabled>Select Location</option>
          <option value="Ground Floor">Ground Floor</option>
          <option value="Fifth Floor">Fifth Floor</option>
          <option value="Sixth Floor">Sixth Floor</option>
          <option value="Storage A">Storage A</option>
          <option value="Storage B">Storage B</option>
          <option value="Storage C">Storage C</option>
        </select>

        <label class="facilities-field-label" for="facility-upload-input">Upload Item</label>
        <div class="facilities-upload-row">
          <div class="facilities-upload-text">
            <i class="bi bi-upload"></i>
            <div class="facilities-upload-meta">
              <span id="facility-upload-name">No file selected</span>
              <small class="facilities-upload-hint">JPG,PNG, up to 5MB</small>
            </div>
          </div>
          <input id="facility-upload-input" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" hidden />
          <button type="button" class="facilities-upload-btn" id="facility-upload-btn">Add Item</button>
        </div>

        <label class="facilities-field-label" for="facility-description">Description</label>
        <textarea id="facility-description" class="facilities-input facilities-textarea" placeholder="Description"></textarea>

        <div class="facilities-modal-actions">
          <button type="button" class="facilities-action-btn cancel" id="facility-cancel-btn">Cancel</button>
          <button type="button" class="facilities-action-btn submit" id="facility-save-btn">Add Item</button>
        </div>
      </div>
    </article>
  </section>

  <script src="/js/dashboard.js"></script>
</body>
</html>

