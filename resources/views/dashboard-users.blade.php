<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Manage Users</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-inventory.css?v={{ filemtime(public_path('css/db-inventory.css')) }}" />
  <style>
    .password-field-wrap {
      position: relative;
      margin-bottom: 12px;
    }

    .password-field-wrap .facilities-input {
      padding-right: 44px;
      margin-bottom: 0;
    }

    .password-toggle-btn {
      position: absolute;
      top: 50%;
      right: 8px;
      transform: translateY(-50%);
      border: 0;
      background: transparent;
      color: #8a93ad;
      width: 28px;
      height: 28px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    .password-toggle-btn:hover,
    .password-toggle-btn:focus {
      background: #e8edff;
      color: #2f3f88;
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
      role: '{{ auth()->user()->role ?? 'user' }}'
    };
    window.userStoreEndpoint = '{{ route('dashboard.users.store') }}';
    window.userEndpointBase = '{{ url('/dashboard/users') }}';
    window.itemOwnerOfficeId = @json($itemOwnerOfficeId);
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

      <section class="content-card request-content-card user-management-card">
        <div class="dashboard-page-header-top request-head">
          <h1 class="section-title">Manage Users</h1>
        </div>

        @if(session('success'))
          <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
          <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @if($errors->any())
          <div class="alert alert-error">
            <ul>
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        @php
          use App\Services\ItemOwnerService;
          use App\Services\UserAccountStatusService;

          $adminRoles = ['admin', 'pf_admin', 'pc_admin'];
          $itemOwners = $users->filter(fn ($user) => ItemOwnerService::isItemOwnerUser($user))->values();
          $admins = $users->filter(function ($user) use ($adminRoles) {
              return in_array(strtolower((string) $user->role), $adminRoles, true)
                  && !ItemOwnerService::isItemOwnerUser($user);
          })->values();
          $mobilePool = $users->filter(function ($user) use ($adminRoles) {
              return !in_array(strtolower((string) $user->role), $adminRoles, true)
                  && !ItemOwnerService::isItemOwnerUser($user);
          });
          $facultyUsers = $mobilePool->filter(fn ($user) => strtolower((string) $user->role) === 'faculty')->values();
          $studentUsers = $mobilePool->filter(fn ($user) => strtolower((string) $user->role) !== 'faculty')->values();
          $formatUserCount = fn (int $count) => $count . ' ' . ($count === 1 ? 'user' : 'users');
          $activeUsersCount = $users->filter(fn ($user) => UserAccountStatusService::isActive($user))->count();
          $resolveUserName = fn ($user) => $user->displayName();
          $resolveUserOffice = function ($user): string {
              $officeName = trim((string) ($user->office?->department_name ?? ''));
              if ($officeName !== '') {
                  return $officeName;
              }

              $programOfficeName = trim((string) ($user->academicProgram?->office?->department_name ?? ''));
              if ($programOfficeName !== '') {
                  return $programOfficeName;
              }

              $programName = trim((string) ($user->academicProgram?->name ?? ''));
              if ($programName !== '') {
                  return $programName;
              }

              return 'No Office';
          };
        @endphp

        <section class="users-summary-grid" aria-label="User summary">
          <article class="users-summary-card">
            <span class="users-summary-icon"><i class="bi bi-people-fill"></i></span>
            <div class="users-summary-copy">
              <p class="users-summary-value">{{ $users->count() }}</p>
              <p class="users-summary-label">Total Users</p>
            </div>
          </article>
          <article class="users-summary-card">
            <span class="users-summary-icon"><i class="bi bi-shield-lock-fill"></i></span>
            <div class="users-summary-copy">
              <p class="users-summary-value">{{ $admins->count() }}</p>
              <p class="users-summary-label">Admins</p>
            </div>
          </article>
          <article class="users-summary-card">
            <span class="users-summary-icon"><i class="bi bi-mortarboard-fill"></i></span>
            <div class="users-summary-copy">
              <p class="users-summary-value">{{ $facultyUsers->count() }}</p>
              <p class="users-summary-label">Faculties</p>
            </div>
          </article>
          <article class="users-summary-card">
            <span class="users-summary-icon"><i class="bi bi-person-check-fill"></i></span>
            <div class="users-summary-copy">
              <p class="users-summary-value">{{ $activeUsersCount }}</p>
              <p class="users-summary-label">Active Users</p>
            </div>
          </article>
        </section>

        <p class="users-policy-note">
          Users and faculties stay active for {{ \App\Services\UserAccountStatusService::INACTIVITY_WEEKS }} weeks from activation. When that window ends they become inactive. Activating an account resets the timer to zero and starts a new {{ \App\Services\UserAccountStatusService::INACTIVITY_WEEKS }}-week period. PF admins can activate or deactivate accounts at any time.
        </p>

        <section class="users-tools-bar" aria-label="User table filters">
          <div class="users-search-wrap">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input id="users-search-input" type="search" placeholder="Search username, name, email, or office" autocomplete="off" />
          </div>

          <div class="users-filter-group">
            <div class="users-filter-wrap">
              <label for="users-role-filter">Role</label>
              <select id="users-role-filter">
                <option value="all">All Roles</option>
                <option value="admin">Admin</option>
                <option value="item_owner">Item Owner</option>
                <option value="faculty">Faculty</option>
                <option value="student">Student</option>
              </select>
            </div>

            <div class="users-filter-wrap">
              <label for="users-status-filter">Status</label>
              <select id="users-status-filter">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>

            <div class="users-filter-wrap">
              <label for="users-sort-select">Sort</label>
              <select id="users-sort-select">
                <option value="name-asc">Name A-Z</option>
                <option value="name-desc">Name Z-A</option>
                <option value="newest">Newest</option>
                <option value="oldest">Oldest</option>
              </select>
            </div>

            <button class="users-reset-btn" id="users-reset-filters" type="button">Reset</button>
          </div>

          <button class="facilities-add-btn" id="user-add-btn" type="button"><span class="btn-icon">+</span> Add User</button>
        </section>

        <section class="inventory-grid facilities-grid">
          <div class="table-wrap">
            <table class="inventory-table users-table">
              <thead>
                <tr>
                  <th><i class="bi bi-person-lines-fill"></i> Username</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Office</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="users-table-body">
                @if($admins->count() > 0)
                  <tr class="category-header-row">
                    <td colspan="7">
                      <div class="category-header">
                        <i class="bi bi-shield-lock"></i>
                        <span>Admins</span>
                        <span class="category-count" data-group-count>{{ $formatUserCount($admins->count()) }}</span>
                      </div>
                    </td>
                  </tr>
                  @foreach($admins as $user)
                    @include('partials.dashboard-user-row', [
                      'user' => $user,
                      'resolveUserOffice' => $resolveUserOffice,
                      'resolveUserName' => $resolveUserName,
                      'rowClass' => 'admin-row',
                      'roleBadgeClass' => 'admin-badge',
                      'roleLabel' => strtoupper((string) $user->role),
                      'deleteConfirm' => 'Delete this user?',
                    ])
                  @endforeach
                @endif

                @if($itemOwners->count() > 0)
                  <tr class="category-header-row">
                    <td colspan="7">
                      <div class="category-header">
                        <i class="bi bi-box-seam"></i>
                        <span>Item Owners</span>
                        <span class="category-count" data-group-count>{{ $formatUserCount($itemOwners->count()) }}</span>
                      </div>
                    </td>
                  </tr>
                  @foreach($itemOwners as $user)
                    @include('partials.dashboard-user-row', [
                      'user' => $user,
                      'resolveUserOffice' => $resolveUserOffice,
                      'resolveUserName' => $resolveUserName,
                      'rowClass' => 'admin-row',
                      'roleBadgeClass' => 'item-owner-badge',
                      'roleLabel' => 'ITEM OWNER',
                      'deleteConfirm' => 'Delete this item owner?',
                    ])
                  @endforeach
                @endif

                @if($facultyUsers->count() > 0)
                  <tr class="category-header-row" data-group="faculty">
                    <td colspan="7">
                      <div class="category-header">
                        <i class="bi bi-mortarboard"></i>
                        <span>Faculties</span>
                        <span class="category-count" data-group-count>{{ $formatUserCount($facultyUsers->count()) }}</span>
                      </div>
                    </td>
                  </tr>
                  @foreach($facultyUsers as $user)
                    @include('partials.dashboard-user-row', [
                      'user' => $user,
                      'resolveUserOffice' => $resolveUserOffice,
                      'resolveUserName' => $resolveUserName,
                      'rowClass' => 'faculty-row',
                      'roleBadgeClass' => 'faculty-badge',
                      'roleLabel' => 'FACULTY',
                      'deleteConfirm' => 'Delete this faculty account?',
                    ])
                  @endforeach
                @endif

                @if($studentUsers->count() > 0)
                  <tr class="category-header-row" data-group="users">
                    <td colspan="7">
                      <div class="category-header">
                        <i class="bi bi-people"></i>
                        <span>Users</span>
                        <span class="category-count" data-group-count>{{ $formatUserCount($studentUsers->count()) }}</span>
                      </div>
                    </td>
                  </tr>
                  @foreach($studentUsers as $user)
                    @include('partials.dashboard-user-row', [
                      'user' => $user,
                      'resolveUserOffice' => $resolveUserOffice,
                      'resolveUserName' => $resolveUserName,
                      'rowClass' => 'approver-row',
                      'roleBadgeClass' => 'approver-badge',
                      'roleLabel' => strtoupper((string) $user->role),
                      'deleteConfirm' => 'Delete this user?',
                    ])
                  @endforeach
                @endif

                @if($users->isEmpty())
                  <tr class="users-empty-state-row" data-empty-fallback>
                    <td colspan="7">
                      <div class="users-empty-state-content">
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <strong>No users found</strong>
                        <span>Try adjusting your search or filters.</span>
                      </div>
                    </td>
                  </tr>
                @endif

                <tr id="users-empty-state-row" class="users-empty-state-row" hidden>
                  <td colspan="7">
                    <div class="users-empty-state-content">
                      <i class="bi bi-search" aria-hidden="true"></i>
                      <strong>No users found</strong>
                      <span>Try adjusting your search or filters.</span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </section>
  </main>

  <section class="facilities-modal" id="user-modal" aria-hidden="true">
    <div class="facilities-modal-overlay" data-close-modal="true"></div>
    <article class="facilities-modal-card equipment-form-modal-card" role="dialog" aria-modal="true" aria-labelledby="user-modal-title">
      <header class="equipment-form-modal-head">
        <h2 id="user-modal-title">Add User</h2>
      </header>

      <form id="user-form" class="equipment-form-modal-form" method="POST" action="{{ route('dashboard.users.store') }}">
        @csrf
        <input type="hidden" name="_method" id="user-form-method" value="POST" />
        <input type="hidden" name="user_id" id="user-id" />

        <div class="facilities-modal-body equipment-form-modal-body">
          <section class="equipment-form-section">
            <label class="facilities-field-label" for="user-username">Username</label>
            <input id="user-username" class="facilities-input" name="username" type="text" placeholder="Username" required />

            <label class="facilities-field-label" for="user-full-name">Full Name</label>
            <input id="user-full-name" class="facilities-input" name="full_name" type="text" placeholder="Full Name" />

            <label class="facilities-field-label" for="user-email">Email</label>
            <input id="user-email" class="facilities-input" name="email" type="email" placeholder="Email address" required />
          </section>

          <section class="equipment-form-section">
            <label class="facilities-field-label" for="user-role">Role</label>
            <select id="user-role" class="facilities-input facilities-select" name="role" required>
              <option value="user">USER</option>
              <option value="faculty">FACULTY</option>
              <option value="admin">ADMIN</option>
              <option value="item_owner">ITEM OWNER</option>
            </select>

            <label class="facilities-field-label" for="user-office">Office</label>
            <select id="user-office" class="facilities-input facilities-select" name="office_id">
              <option value="">No Office</option>
              @foreach($offices as $office)
                <option value="{{ $office->office_id }}">{{ $office->department_name }}</option>
              @endforeach
            </select>
            <small class="facilities-input-note" id="item-owner-office-note" style="display:none;">
              Item owners are automatically assigned to the Item Owner office and can manage their own equipment inventory.
            </small>
          </section>

          <section class="equipment-form-section">
            <label class="facilities-field-label" for="user-password">Password</label>
            <div class="password-field-wrap">
              <input id="user-password" class="facilities-input" name="password" type="password" placeholder="Password" autocomplete="new-password" />
              <button type="button" class="password-toggle-btn" data-password-target="user-password" aria-label="Show password" aria-pressed="false">
                <i class="bi bi-eye"></i>
              </button>
            </div>

            <label class="facilities-field-label" for="user-password-confirmation">Confirm Password</label>
            <div class="password-field-wrap">
              <input id="user-password-confirmation" class="facilities-input" name="password_confirmation" type="password" placeholder="Re-enter password" autocomplete="new-password" />
              <button type="button" class="password-toggle-btn" data-password-target="user-password-confirmation" aria-label="Show confirm password" aria-pressed="false">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <small class="facilities-input-note" id="user-password-note">Leave both password fields blank when editing an existing user to keep the current password.</small>
          </section>
        </div>

        <footer class="equipment-form-modal-footer">
          <button type="button" class="facilities-action-btn cancel" id="user-cancel-btn">Cancel</button>
          <button type="submit" class="facilities-action-btn submit" id="user-save-btn">Save User</button>
        </footer>
      </form>
    </article>
  </section>

  <script>
    const userAddBtn = document.getElementById('user-add-btn');
    const itemOwnerAddBtn = document.getElementById('item-owner-add-btn');
    const userModal = document.getElementById('user-modal');
    const userForm = document.getElementById('user-form');
    const userFormMethod = document.getElementById('user-form-method');
    const userModalTitle = document.getElementById('user-modal-title');
    const userCancelBtn = document.getElementById('user-cancel-btn');
    const userIdInput = document.getElementById('user-id');
    const userUsernameInput = document.getElementById('user-username');
    const userFullNameInput = document.getElementById('user-full-name');
    const userEmailInput = document.getElementById('user-email');
    const userRoleInput = document.getElementById('user-role');
    const userOfficeInput = document.getElementById('user-office');
    const userPasswordInput = document.getElementById('user-password');
    const userPasswordConfirmInput = document.getElementById('user-password-confirmation');
    const userPasswordNote = document.getElementById('user-password-note');
    const itemOwnerOfficeNote = document.getElementById('item-owner-office-note');
    const itemOwnerOfficeId = window.itemOwnerOfficeId ? String(window.itemOwnerOfficeId) : '';
    const usersTableBody = document.getElementById('users-table-body');
    const usersSearchInput = document.getElementById('users-search-input');
    const usersRoleFilter = document.getElementById('users-role-filter');
    const usersStatusFilter = document.getElementById('users-status-filter');
    const usersSortSelect = document.getElementById('users-sort-select');
    const usersResetFiltersBtn = document.getElementById('users-reset-filters');
    const usersResultsCount = document.getElementById('users-results-count');
    const usersEmptyStateRow = document.getElementById('users-empty-state-row');

    const editButtons = document.querySelectorAll('.user-edit-btn');
    const modalOverlay = userModal.querySelector('[data-close-modal]');

    const openUserModal = () => {
      userModal.setAttribute('aria-hidden', 'false');
      userModal.classList.add('is-open');
    };

    const closeUserModal = () => {
      userModal.setAttribute('aria-hidden', 'true');
      userModal.classList.remove('is-open');
    };

    const syncRoleOfficeFields = () => {
      const isItemOwner = userRoleInput.value === 'item_owner';

      if (itemOwnerOfficeNote) {
        itemOwnerOfficeNote.style.display = isItemOwner ? 'block' : 'none';
      }

      if (isItemOwner && itemOwnerOfficeId) {
        userOfficeInput.value = itemOwnerOfficeId;
        userOfficeInput.disabled = true;
      } else {
        userOfficeInput.disabled = false;
      }
    };

    const syncPasswordFields = (isCreate = true) => {
      userPasswordInput.required = isCreate;
      userPasswordConfirmInput.required = isCreate;

      if (userPasswordNote) {
        userPasswordNote.textContent = isCreate
          ? 'Enter the password twice to confirm it was typed correctly.'
          : 'Leave both password fields blank to keep the current password, or enter a new password twice to change it.';
      }
    };

    const resetModal = (presetRole = 'user') => {
      userForm.action = window.userStoreEndpoint;
      userFormMethod.value = 'POST';
      userModalTitle.textContent = presetRole === 'item_owner' ? 'Add Item Owner' : 'Add User';
      userIdInput.value = '';
      userUsernameInput.value = '';
      userFullNameInput.value = '';
      userEmailInput.value = '';
      userRoleInput.value = presetRole;
      userOfficeInput.value = presetRole === 'item_owner' ? itemOwnerOfficeId : '';
      userPasswordInput.value = '';
      userPasswordConfirmInput.value = '';
      userPasswordInput.type = 'password';
      userPasswordConfirmInput.type = 'password';
      syncPasswordFields(true);
      syncRoleOfficeFields();
    };

    const populateModal = (row) => {
      const userId = row.dataset.userId;
      const username = row.dataset.userUsername;
      const fullName = row.dataset.userFullName || row.dataset.userName;
      const email = row.dataset.userEmail;
      const role = row.dataset.userRole;
      const officeId = row.dataset.userOfficeId;

      userForm.action = `${window.userEndpointBase}/${userId}`;
      userFormMethod.value = 'PATCH';
      userIdInput.value = userId;
      userUsernameInput.value = username;
      userFullNameInput.value = fullName;
      userEmailInput.value = email;
      userRoleInput.value = role;
      userOfficeInput.value = officeId || '';
      userPasswordInput.value = '';
      userPasswordConfirmInput.value = '';
      userPasswordInput.type = 'password';
      userPasswordConfirmInput.type = 'password';
      syncPasswordFields(false);
      userModalTitle.textContent = role === 'item_owner' ? 'Edit Item Owner' : 'Edit User';
      syncRoleOfficeFields();
      openUserModal();
    };

    if (userAddBtn) {
      userAddBtn.addEventListener('click', () => {
        resetModal('user');
        openUserModal();
      });
    }

    if (itemOwnerAddBtn) {
      itemOwnerAddBtn.addEventListener('click', () => {
        resetModal('item_owner');
        openUserModal();
      });
    }

    if (userRoleInput) {
      userRoleInput.addEventListener('change', syncRoleOfficeFields);
    }

    if (userForm) {
      userForm.addEventListener('submit', (event) => {
        userOfficeInput.disabled = false;

        const isCreate = userFormMethod.value === 'POST';
        const passwordValue = userPasswordInput.value.trim();
        const confirmValue = userPasswordConfirmInput.value.trim();

        if (isCreate && (passwordValue === '' || confirmValue === '')) {
          event.preventDefault();
          window.alert('Please enter and confirm the password.');
          return;
        }

        if (!isCreate && passwordValue !== '' && confirmValue === '') {
          event.preventDefault();
          window.alert('Please confirm the new password.');
          return;
        }

        if (passwordValue !== '' && passwordValue !== confirmValue) {
          event.preventDefault();
          window.alert('Passwords do not match. Please try again.');
        }
      });
    }

    document.querySelectorAll('.password-toggle-btn').forEach((button) => {
      button.addEventListener('click', () => {
        const targetId = button.getAttribute('data-password-target');
        const input = targetId ? document.getElementById(targetId) : null;
        const icon = button.querySelector('i');

        if (!input || !icon) {
          return;
        }

        const showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        button.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
        button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
        icon.classList.toggle('bi-eye', !showPassword);
        icon.classList.toggle('bi-eye-slash', showPassword);
      });
    });

    if (userCancelBtn) {
      userCancelBtn.addEventListener('click', closeUserModal);
    }

    if (modalOverlay) {
      modalOverlay.addEventListener('click', closeUserModal);
    }

    editButtons.forEach((button) => {
      button.addEventListener('click', (event) => {
        const row = event.target.closest('tr');
        if (!row || !row.dataset.userId) {
          return;
        }
        populateModal(row);
      });
    });

    if (usersTableBody) {
      const userRows = Array.from(usersTableBody.querySelectorAll('tr[data-user-id]'));
      const categoryRows = Array.from(usersTableBody.querySelectorAll('tr.category-header-row'));

      if (userRows.length > 0 && categoryRows.length > 0) {
        const collator = new Intl.Collator(undefined, { sensitivity: 'base', numeric: true });
        const groupedSections = categoryRows.map((headerRow) => {
          const rows = [];
          let pointer = headerRow.nextElementSibling;

          while (pointer && !pointer.classList.contains('category-header-row')) {
            if (pointer instanceof HTMLTableRowElement && pointer.dataset.userId) {
              rows.push(pointer);
            }

            pointer = pointer.nextElementSibling;
          }

          return {
            headerRow,
            rows,
            countLabel: headerRow.querySelector('[data-group-count]'),
            totalCount: rows.length,
          };
        });

        const normalizeText = (value) => String(value || '').toLowerCase().trim();
        const formatCount = (value) => `${value} ${value === 1 ? 'user' : 'users'}`;

        const roleFilterMatch = (rowRole, selectedRole) => {
          if (selectedRole === 'all') {
            return true;
          }

          if (selectedRole === 'item_owner') {
            return rowRole === 'item_owner';
          }

          if (selectedRole === 'admin') {
            return ['admin', 'pf_admin', 'pc_admin'].includes(rowRole);
          }

          if (selectedRole === 'faculty') {
            return rowRole === 'faculty';
          }

          if (selectedRole === 'student') {
            return rowRole === 'student' || rowRole === 'user';
          }

          return rowRole === selectedRole;
        };

        const compareRows = (left, right, sortMode) => {
          const leftName = normalizeText(left.dataset.userName)
            || normalizeText(left.dataset.userFullName)
            || normalizeText(left.dataset.userUsername);
          const rightName = normalizeText(right.dataset.userName)
            || normalizeText(right.dataset.userFullName)
            || normalizeText(right.dataset.userUsername);
          const leftCreatedAt = Number.parseInt(left.dataset.userCreatedAt || '0', 10) || 0;
          const rightCreatedAt = Number.parseInt(right.dataset.userCreatedAt || '0', 10) || 0;

          if (sortMode === 'name-desc') {
            return collator.compare(rightName, leftName);
          }

          if (sortMode === 'newest') {
            return rightCreatedAt - leftCreatedAt;
          }

          if (sortMode === 'oldest') {
            return leftCreatedAt - rightCreatedAt;
          }

          return collator.compare(leftName, rightName);
        };

        const applyUsersFilters = () => {
          const searchTerm = normalizeText(usersSearchInput?.value);
          const selectedRole = normalizeText(usersRoleFilter?.value) || 'all';
          const selectedStatus = normalizeText(usersStatusFilter?.value) || 'all';
          const sortMode = normalizeText(usersSortSelect?.value) || 'name-asc';
          let visibleUserCount = 0;

          groupedSections.forEach((section) => {
            section.rows.forEach((row) => {
              const rowRole = normalizeText(row.dataset.userRole);
              const rowStatus = normalizeText(row.dataset.userStatus) || 'active';
              const rowOffice = normalizeText(row.dataset.userOfficeName);
              const haystack = [
                normalizeText(row.dataset.userUsername),
                normalizeText(row.dataset.userName),
                normalizeText(row.dataset.userFullName),
                normalizeText(row.dataset.userEmail),
                rowOffice,
              ].join(' ');

              const matchesSearch = searchTerm === '' || haystack.includes(searchTerm);
              const matchesRole = roleFilterMatch(rowRole, selectedRole);
              const matchesStatus = selectedStatus === 'all' || rowStatus === selectedStatus;
              const isVisible = matchesSearch && matchesRole && matchesStatus;

              row.hidden = !isVisible;

              if (isVisible) {
                visibleUserCount += 1;
              }
            });

            const visibleInSection = section.rows.filter((row) => !row.hidden).length;
            section.headerRow.hidden = visibleInSection === 0;

            if (section.countLabel) {
              section.countLabel.textContent = formatCount(visibleInSection);
            }

            section.rows.sort((left, right) => compareRows(left, right, sortMode));
          });

          if (usersResultsCount) {
            usersResultsCount.textContent = `${visibleUserCount} ${visibleUserCount === 1 ? 'user' : 'users'} shown`;
          }

          if (usersEmptyStateRow) {
            usersEmptyStateRow.hidden = visibleUserCount !== 0;
          }

          const fallbackRow = usersTableBody.querySelector('[data-empty-fallback]');
          if (fallbackRow instanceof HTMLTableRowElement) {
            fallbackRow.hidden = true;
          }

          const fragment = document.createDocumentFragment();
          groupedSections.forEach((section) => {
            fragment.appendChild(section.headerRow);
            section.rows.forEach((row) => fragment.appendChild(row));
          });

          if (usersEmptyStateRow) {
            fragment.appendChild(usersEmptyStateRow);
          }

          usersTableBody.appendChild(fragment);
        };

        usersSearchInput?.addEventListener('input', applyUsersFilters);
        usersRoleFilter?.addEventListener('change', applyUsersFilters);
        usersStatusFilter?.addEventListener('change', applyUsersFilters);
        usersSortSelect?.addEventListener('change', applyUsersFilters);

        usersResetFiltersBtn?.addEventListener('click', () => {
          if (usersSearchInput) {
            usersSearchInput.value = '';
          }

          if (usersRoleFilter) {
            usersRoleFilter.value = 'all';
          }

          if (usersStatusFilter) {
            usersStatusFilter.value = 'all';
          }

          if (usersSortSelect) {
            usersSortSelect.value = 'name-asc';
          }

          applyUsersFilters();
        });

        applyUsersFilters();

        // #region agent log
        fetch('http://127.0.0.1:7591/ingest/35e57a72-783b-42fe-bb4e-563f8b0a56b3',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'e19b10'},body:JSON.stringify({sessionId:'e19b10',runId:'post-fix',hypothesisId:'A,B',location:'dashboard-users.blade.php:init',message:'users dashboard status rendering',data:{facultyHeaders:document.querySelectorAll('[data-group="faculty"]').length,userHeaders:document.querySelectorAll('[data-group="users"]').length,managedRows:document.querySelectorAll('[data-user-status-managed="1"]').length,unmanagedRows:document.querySelectorAll('[data-user-status-managed="0"]').length,adminRowsWithoutToggle:Array.from(document.querySelectorAll('.admin-row')).filter((r)=>!r.querySelector('.table-status-btn')).length,sampleActiveDurations:Array.from(document.querySelectorAll('.user-status-duration')).slice(0,5).map((el)=>el.textContent.trim()),naCells:document.querySelectorAll('.user-status-na').length},timestamp:Date.now()})}).catch(()=>{});
        // #endregion
      }
    }
  </script>

  <script src="/js/dashboard.js?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
  <script>
    (() => {
      const confirmFn = typeof window.showAppConfirm === 'function' ? window.showAppConfirm : null;
      const table = document.getElementById('users-table-body');
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

      const showUsersToast = (message, type = 'success') => {
        let host = document.getElementById('users-ajax-toast');
        if (!(host instanceof HTMLElement)) {
          host = document.createElement('div');
          host.id = 'users-ajax-toast';
          host.className = 'alert';
          const card = document.querySelector('.user-management-card .dashboard-page-header-top');
          if (card?.parentElement) {
            card.insertAdjacentElement('afterend', host);
          } else {
            document.body.prepend(host);
          }
        }

        host.className = `alert alert-${type === 'error' ? 'error' : 'success'}`;
        host.textContent = message;
        window.clearTimeout(host._hideTimer);
        host._hideTimer = window.setTimeout(() => {
          host.remove();
        }, 3200);
      };

      const refreshActiveSummary = () => {
        const cards = Array.from(document.querySelectorAll('.users-summary-card'));
        const activeCard = cards.find((card) => {
          const label = card.querySelector('.users-summary-label');
          return label && /active users/i.test(label.textContent || '');
        });
        if (!activeCard) {
          return;
        }

        const value = activeCard.querySelector('.users-summary-value');
        if (!(value instanceof HTMLElement)) {
          return;
        }

        const activeCount = document.querySelectorAll('.users-table tbody tr[data-user-status="active"]').length;
        value.textContent = String(activeCount);
      };

      const updateStatusRow = (row, payload) => {
        const isActive = !!payload.isActive;
        const status = isActive ? 'active' : 'inactive';
        const duration = payload.durationLabel || (isActive ? 'Active for 0 seconds' : 'Inactive for 0 seconds');

        row.dataset.userStatus = status;

        // #region agent log
        fetch('http://127.0.0.1:7591/ingest/35e57a72-783b-42fe-bb4e-563f8b0a56b3',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'e19b10'},body:JSON.stringify({sessionId:'e19b10',runId:'post-fix',hypothesisId:'W14',location:'dashboard-users.blade.php:updateStatusRow',message:'row status updated after activate/deactivate',data:{userId:row.dataset.userId||null,isActive,duration,buttonWillBe:isActive?'Deactivate':'Activate'},timestamp:Date.now()})}).catch(()=>{});
        // #endregion

        const statusCell = row.children[5];
        if (statusCell instanceof HTMLElement) {
          statusCell.innerHTML = `
            <div class="user-status-cell">
              <span class="status-badge ${isActive ? 'is-active' : 'is-inactive'}">${isActive ? 'Active' : 'Inactive'}</span>
              <span class="user-status-duration" title="${duration}">${duration}</span>
            </div>
          `;
        }

        const statusForm = row.querySelector('form[data-user-confirm-form="status"]');
        if (statusForm instanceof HTMLFormElement) {
          statusForm.setAttribute('data-confirm-title', isActive ? 'Deactivate account' : 'Activate account');
          statusForm.setAttribute(
            'data-confirm-message',
            isActive
              ? 'Are you sure you want to deactivate this account?'
              : 'Are you sure you want to activate this account?'
          );
          statusForm.setAttribute(
            'data-confirm-note',
            isActive ? 'They will not be able to sign in until reactivated.' : ''
          );
          statusForm.setAttribute('data-confirm-text', isActive ? 'Deactivate' : 'Activate');
          statusForm.setAttribute('data-confirm-variant', isActive ? 'danger' : 'success');

          const button = statusForm.querySelector('.table-status-btn');
          if (button instanceof HTMLButtonElement) {
            button.classList.toggle('is-deactivate', isActive);
            button.classList.toggle('is-activate', !isActive);
            button.title = isActive ? 'Mark inactive' : 'Mark active';
            button.textContent = isActive ? 'Deactivate' : 'Activate';
          }
        }

        refreshActiveSummary();
      };

      const removeUserRow = (row) => {
        let header = row.previousElementSibling;
        while (header instanceof HTMLTableRowElement && !header.classList.contains('category-header-row')) {
          header = header.previousElementSibling;
        }

        row.remove();

        if (!(header instanceof HTMLTableRowElement) || !header.classList.contains('category-header-row')) {
          refreshActiveSummary();
          return;
        }

        let count = 0;
        let cursor = header.nextElementSibling;
        while (
          cursor instanceof HTMLTableRowElement
          && !cursor.classList.contains('category-header-row')
          && !cursor.classList.contains('users-empty-state-row')
          && cursor.hasAttribute('data-user-id')
        ) {
          if (!cursor.hidden) {
            count += 1;
          }
          cursor = cursor.nextElementSibling;
        }

        if (count === 0) {
          header.remove();
        } else {
          const countLabel = header.querySelector('[data-group-count]');
          if (countLabel) {
            countLabel.textContent = `${count} ${count === 1 ? 'user' : 'users'}`;
          }
        }

        refreshActiveSummary();
      };

      const submitUserAction = async (form, kind) => {
        const methodInput = form.querySelector('input[name="_method"]');
        const method = (methodInput instanceof HTMLInputElement ? methodInput.value : form.method || 'POST').toUpperCase();
        const body = new FormData(form);

        const response = await fetch(form.action, {
          method: method === 'GET' ? 'GET' : 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
          },
          body,
          credentials: 'same-origin',
        });

        let payload = null;
        try {
          payload = await response.json();
        } catch (_error) {
          payload = null;
        }

        // #region agent log
        fetch('http://127.0.0.1:7591/ingest/35e57a72-783b-42fe-bb4e-563f8b0a56b3',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'e19b10'},body:JSON.stringify({sessionId:'e19b10',runId:'ajax-verify',hypothesisId:'AJAX1,AJAX2',location:'dashboard-users.blade.php:ajaxResult',message:'ajax user action result',data:{kind,ok:!!payload?.ok,status:response.status,pageReloaded:false,userId:payload?.userId||null,isActive:payload?.isActive??null},timestamp:Date.now()})}).catch(()=>{});
        // #endregion

        if (!response.ok || !payload?.ok) {
          throw new Error(payload?.message || 'Request failed.');
        }

        const row = form.closest('tr');
        if (!(row instanceof HTMLTableRowElement)) {
          return payload;
        }

        if (kind === 'delete') {
          removeUserRow(row);
        } else if (kind === 'status') {
          updateStatusRow(row, payload);
        }

        return payload;
      };

      // #region agent log
      const actionsTh = document.querySelector('.user-management-card .users-table thead th:last-child');
      const actionsTd = document.querySelector('.user-management-card .users-table tbody td.table-actions-cell');
      const thRect = actionsTh ? actionsTh.getBoundingClientRect() : null;
      const tdRect = actionsTd ? actionsTd.getBoundingClientRect() : null;
      fetch('http://127.0.0.1:7591/ingest/35e57a72-783b-42fe-bb4e-563f8b0a56b3',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'e19b10'},body:JSON.stringify({sessionId:'e19b10',runId:'ajax-verify',hypothesisId:'H1',location:'dashboard-users.blade.php:headerMeasure',message:'actions header vs cell width',data:{hasConfirmFn:!!confirmFn,thWidth:thRect?Math.round(thRect.width):null,tdWidth:tdRect?Math.round(tdRect.width):null,widthDelta:thRect&&tdRect?Math.round(tdRect.width-thRect.width):null,thRight:thRect?Math.round(thRect.right):null,tdRight:tdRect?Math.round(tdRect.right):null},timestamp:Date.now()})}).catch(()=>{});
      // #endregion

      if (!(table instanceof HTMLElement)) {
        return;
      }

      table.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-user-confirm-form')) {
          return;
        }

        event.preventDefault();

        const title = form.getAttribute('data-confirm-title') || 'Confirm';
        const message = form.getAttribute('data-confirm-message') || 'Are you sure?';
        const note = form.getAttribute('data-confirm-note') || '';
        const confirmText = form.getAttribute('data-confirm-text') || 'Confirm';
        const variant = form.getAttribute('data-confirm-variant') || 'danger';
        const kind = form.getAttribute('data-user-confirm-form') || 'unknown';

        // #region agent log
        fetch('http://127.0.0.1:7591/ingest/35e57a72-783b-42fe-bb4e-563f8b0a56b3',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'e19b10'},body:JSON.stringify({sessionId:'e19b10',runId:'ajax-verify',hypothesisId:'AJAX1,AJAX2',location:'dashboard-users.blade.php:confirmSubmit',message:'user action confirm intercepted',data:{kind,title,hasConfirmFn:!!confirmFn,willUseAjax:true},timestamp:Date.now()})}).catch(()=>{});
        // #endregion

        const ask = confirmFn
          ? confirmFn(message, {
              title,
              confirmText,
              cancelText: 'Cancel',
              variant,
              dangerNote: note,
            })
          : Promise.resolve(window.confirm([message, note].filter(Boolean).join('\n')));

        ask.then(async (confirmed) => {
          // #region agent log
          fetch('http://127.0.0.1:7591/ingest/35e57a72-783b-42fe-bb4e-563f8b0a56b3',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'e19b10'},body:JSON.stringify({sessionId:'e19b10',runId:'ajax-verify',hypothesisId:'AJAX1,AJAX2',location:'dashboard-users.blade.php:confirmResult',message:'user action confirm result',data:{kind,confirmed},timestamp:Date.now()})}).catch(()=>{});
          // #endregion

          if (!confirmed) {
            return;
          }

          try {
            const payload = await submitUserAction(form, kind);
            showUsersToast(payload.message || 'Updated successfully.');
          } catch (error) {
            showUsersToast(error instanceof Error ? error.message : 'Request failed.', 'error');
          }
        });
      });
    })();
  </script>
</body>
</html>
