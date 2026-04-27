<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Profile</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-profile.css" />
</head>
<body>
  @php
    $authUser = auth()->user();
    $fullName = trim((string) ($authUser->full_name ?? $authUser->username ?? ''));
    $nameParts = preg_split('/\s+/', $fullName) ?: [];
    $firstName = $authUser->first_name ?? ($nameParts[0] ?? '');
    $lastName = $authUser->last_name ?? (count($nameParts) > 1 ? $nameParts[count($nameParts) - 1] : '');
    $middleInitial = $authUser->middle_initial ?? '';

    $authUserPayload = [
      'id' => $authUser->user_id ?? null,
      'username' => $authUser->username ?? 'User',
      'email' => $authUser->email ?? '',
      'first_name' => $authUser->first_name ?? null,
      'middle_initial' => $authUser->middle_initial ?? null,
      'last_name' => $authUser->last_name ?? null,
      'full_name' => $authUser->full_name ?? $authUser->username ?? 'User',
      'role' => $authUser->role ?? 'user',
      'suffix' => $authUser->suffix ?? '',
      'contact_number' => $authUser->contact_number ?? '',
      'phone_number' => $authUser->phone_number ?? '',
      'profile_update_url' => route('dashboard.profile.update'),
    ];

    $profileNavComponent = method_exists($authUser, 'isPhysicalFacilitiesAdmin') && $authUser->isPhysicalFacilitiesAdmin()
      ? '/components/navbar.html'
      : '/components/navbar-office.html';
  @endphp
  <script>
    window.authUser = @json($authUserPayload);
    window.dashboardNavComponent = @json($profileNavComponent);
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

      <section class="content-card profile-content-card">
        <section class="profile-top-card">
          <div>
            <h1>Profile</h1>
            <p>Manage your administrator account</p>
          </div>
          <button class="profile-edit-btn" type="button">Edit Profile</button>
        </section>

        <section class="profile-grid">
          <div class="profile-left-col">
            <article class="profile-card">
              <h2>Personal Information</h2>
              <div class="profile-personal-body">
                <div class="profile-avatar" id="profile-avatar" aria-hidden="true">
                  <img id="profile-avatar-image" class="profile-avatar-image" alt="Profile avatar" />
                  <i class="bi bi-person-fill profile-avatar-icon"></i>
                </div>

                <div class="profile-fields">
                  <label for="profile-first-name">First Name</label>
                  <input id="profile-first-name" type="text" value="{{ $firstName !== '' ? $firstName : ($authUser->username ?? '') }}" readonly />

                  <label for="profile-middle-name">Middle Initial</label>
                  <input id="profile-middle-name" type="text" value="{{ $middleInitial }}" readonly />

                  <label for="profile-last-name">Last Name</label>
                  <input id="profile-last-name" type="text" value="{{ $lastName }}" readonly />

                  <label for="profile-suffix">Suffix</label>
                  <input id="profile-suffix" type="text" value="{{ $authUser->suffix ?? 'Not Set' }}" readonly />
                </div>
              </div>
            </article>

            <article class="profile-card">
              <h3 class="profile-admin-head">Administrator Information</h3>
              <div class="profile-admin-grid">
                <label for="profile-admin-id">Admin ID</label>
                <label for="profile-email">Email</label>
                <input id="profile-admin-id" type="text" value="{{ $authUser->user_id ?? '' }}" readonly />
                <input id="profile-email" type="text" value="{{ $authUser->email ?? '' }}" readonly />

                <label for="profile-contact">Contact Number</label>
                <label for="profile-phone">Phone Number</label>
                <input id="profile-contact" type="text" value="{{ $authUser->contact_number ?? 'Not Set' }}" readonly />
                <input id="profile-phone" type="text" value="{{ $authUser->phone_number ?? 'Not Set' }}" readonly />
              </div>
            </article>
          </div>

          <article class="profile-card profile-log-card">
            <h2>Admin Activity Log</h2>
            <div class="profile-log-wrap">
              <table class="profile-log-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>31/10/2024</td>
                    <td>Approve request</td>
                    <td>Schedule</td>
                    <td><span class="profile-log-status">Success</span></td>
                  </tr>
                  <tr>
                    <td>02/04/2023</td>
                    <td>Updated inventory</td>
                    <td>Inventory</td>
                    <td><span class="profile-log-status">Success</span></td>
                  </tr>
                  <tr>
                    <td>16/10/2025</td>
                    <td>Added new user</td>
                    <td>Account</td>
                    <td><span class="profile-log-status">Success</span></td>
                  </tr>
                  <tr>
                    <td>22/11/2019</td>
                    <td>Added new user</td>
                    <td>Account</td>
                    <td><span class="profile-log-status">Success</span></td>
                  </tr>
                  <tr>
                    <td>08/02/2026</td>
                    <td>Updated rooms</td>
                    <td>Rooms</td>
                    <td><span class="profile-log-status">Success</span></td>
                  </tr>
                  <tr>
                    <td>31/03/2025</td>
                    <td>Approved request</td>
                    <td>Schedule</td>
                    <td><span class="profile-log-status">Success</span></td>
                  </tr>
                  <tr>
                    <td>31/03/2025</td>
                    <td>Updated schedule</td>
                    <td>Schedule</td>
                    <td><span class="profile-log-status">Success</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </article>
        </section>
      </section>
    </section>
  </main>

  <section class="profile-edit-modal" id="profile-edit-modal" aria-hidden="true">
    <div class="profile-edit-overlay" data-close-profile-modal="true"></div>
    <article class="profile-edit-card" role="dialog" aria-modal="true" aria-labelledby="profile-edit-title">
      <div class="profile-edit-top"></div>
      <div class="profile-edit-body">
        <h2 id="profile-edit-title">Edit Personal Information</h2>

        <div class="profile-edit-avatar-wrap">
          <div class="profile-edit-avatar" id="profile-edit-avatar" role="button" tabindex="0" aria-label="Upload profile picture">
            <img id="profile-edit-avatar-image" class="profile-edit-avatar-image" alt="Avatar preview" />
            <i class="bi bi-person-fill profile-edit-avatar-icon"></i>
          </div>
          <input id="profile-avatar-upload" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" hidden />
          <button type="button" class="profile-edit-upload-btn" id="profile-edit-upload-btn">Upload Photo</button>
        </div>

        <div class="profile-edit-main-grid">
          <section class="profile-edit-column">
            <h3 class="profile-edit-section-title">Personal Information</h3>

            <label class="profile-edit-label" for="profile-modal-first-name">First Name</label>
            <input id="profile-modal-first-name" class="profile-edit-input" type="text" />

            <label class="profile-edit-label" for="profile-modal-middle-name">Middle Name</label>
            <input id="profile-modal-middle-name" class="profile-edit-input" type="text" />

            <label class="profile-edit-label" for="profile-modal-last-name">Last Name</label>
            <input id="profile-modal-last-name" class="profile-edit-input" type="text" />

            <label class="profile-edit-label" for="profile-modal-suffix">Suffix</label>
            <input id="profile-modal-suffix" class="profile-edit-input" type="text" />
          </section>

          <section class="profile-edit-column">
            <h3 class="profile-edit-section-title">Administrator Information</h3>

            <label class="profile-edit-label" for="profile-modal-admin-id">Admin ID</label>
            <input id="profile-modal-admin-id" class="profile-edit-input profile-edit-input-readonly" type="text" readonly />

            <label class="profile-edit-label" for="profile-modal-email">Email</label>
            <input id="profile-modal-email" class="profile-edit-input" type="text" />

            <label class="profile-edit-label" for="profile-modal-contact">Contact Number</label>
            <input id="profile-modal-contact" class="profile-edit-input" type="text" />

            <label class="profile-edit-label" for="profile-modal-phone">Phone Number</label>
            <input id="profile-modal-phone" class="profile-edit-input" type="text" />
          </section>
        </div>

        <div class="profile-edit-actions">
          <button type="button" class="profile-edit-btn-secondary" id="profile-edit-cancel-btn">Cancel</button>
          <button type="button" class="profile-edit-btn-primary" id="profile-edit-save-btn">Save Profile</button>
        </div>
      </div>
    </article>
  </section>

  <script src="/js/dashboard.js"></script>
</body>
</html>

