<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Manage Users</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-inventory.css" />
  <style>
    .password-field-wrap {
      position: relative;
      display: flex;
      align-items: stretch;
    }

    .password-field-wrap .facilities-input {
      padding-right: 44px;
    }

    .password-toggle-btn {
      position: absolute;
      top: 50%;
      right: 10px;
      transform: translateY(-50%);
      border: 0;
      background: transparent;
      color: #38479c;
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    .password-toggle-btn:hover,
    .password-toggle-btn:focus {
      background: rgba(56, 71, 156, 0.08);
      color: #26367b;
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

          $adminRoles = ['admin', 'pf_admin', 'pc_admin'];
          $itemOwners = $users->filter(fn ($user) => ItemOwnerService::isItemOwnerUser($user));
          $admins = $users->filter(function ($user) use ($adminRoles) {
              return in_array(strtolower((string) $user->role), $adminRoles, true)
                  && !ItemOwnerService::isItemOwnerUser($user);
          });
          $approvers = $users->filter(function ($user) use ($adminRoles) {
              return !in_array(strtolower((string) $user->role), $adminRoles, true)
                  && !ItemOwnerService::isItemOwnerUser($user);
          });
          $displayRole = function ($user) {
              return ItemOwnerService::isItemOwnerUser($user) ? 'item_owner' : strtolower((string) $user->role);
          };
          $resolveUserStatus = function ($user): string {
              $rawStatus = strtolower(trim((string) ($user->status ?? $user->account_status ?? '')));

              if ($rawStatus !== '') {
                  return in_array($rawStatus, ['inactive', 'disabled', 'suspended', 'blocked'], true) ? 'inactive' : 'active';
              }

              if (isset($user->is_active)) {
                  return (bool) $user->is_active ? 'active' : 'inactive';
              }

              if (isset($user->active)) {
                  return (bool) $user->active ? 'active' : 'inactive';
              }

              return 'active';
          };
          $formatUserCount = fn (int $count) => $count . ' ' . ($count === 1 ? 'user' : 'users');
          $activeUsersCount = $users->filter(fn ($user) => $resolveUserStatus($user) === 'active')->count();
          $resolveUserName = fn ($user) => $user->displayName();
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
            <span class="users-summary-icon"><i class="bi bi-box-seam"></i></span>
            <div class="users-summary-copy">
              <p class="users-summary-value">{{ $itemOwners->count() }}</p>
              <p class="users-summary-label">Item Owners</p>
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
                    @php
                      $userStatus = $resolveUserStatus($user);
                      $userOffice = $user->office?->department_name ?? 'No Office';
                      $userName = $resolveUserName($user);
                    @endphp
                    <tr
                      class="admin-row"
                      data-user-id="{{ $user->user_id }}"
                      data-user-username="{{ $user->username }}"
                      data-user-full-name="{{ $user->full_name }}"
                      data-user-name="{{ $userName }}"
                      data-user-email="{{ $user->email }}"
                      data-user-office-name="{{ strtolower((string) $userOffice) }}"
                      data-user-role="{{ $displayRole($user) }}"
                      data-user-status="{{ $userStatus }}"
                      data-user-created-at="{{ $user->created_at ? $user->created_at->timestamp : 0 }}"
                      data-user-office-id="{{ $user->office_id }}"
                    >
                      <td><span class="user-cell-text" title="{{ $user->username }}">{{ $user->username }}</span></td>
                      <td><span class="user-cell-text" title="{{ $userName }}">{{ $userName }}</span></td>
                      <td><span class="user-cell-text" title="{{ $user->email }}">{{ $user->email }}</span></td>
                      <td><span class="role-badge admin-badge">{{ strtoupper($user->role) }}</span></td>
                      <td><span class="user-cell-text" title="{{ $userOffice }}">{{ $userOffice }}</span></td>
                      <td><span class="status-badge {{ $userStatus === 'active' ? 'is-active' : 'is-inactive' }}">{{ ucfirst($userStatus) }}</span></td>
                      <td class="table-actions-cell">
                        <button class="table-edit-btn user-edit-btn" type="button">Edit</button>
                        @if(auth()->user()->user_id !== $user->user_id)
                          <form method="POST" action="{{ route('dashboard.users.destroy', ['userId' => $user->user_id]) }}" class="inline-action-form" onsubmit="return confirm('Delete this user?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="table-delete-btn">Delete</button>
                          </form>
                        @endif
                      </td>
                    </tr>
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
                    @php
                      $userStatus = $resolveUserStatus($user);
                      $userOffice = $user->office?->department_name ?? 'Item Owner';
                      $userName = $resolveUserName($user);
                    @endphp
                    <tr
                      class="admin-row"
                      data-user-id="{{ $user->user_id }}"
                      data-user-username="{{ $user->username }}"
                      data-user-full-name="{{ $user->full_name }}"
                      data-user-name="{{ $userName }}"
                      data-user-email="{{ $user->email }}"
                      data-user-office-name="{{ strtolower((string) $userOffice) }}"
                      data-user-role="{{ $displayRole($user) }}"
                      data-user-status="{{ $userStatus }}"
                      data-user-created-at="{{ $user->created_at ? $user->created_at->timestamp : 0 }}"
                      data-user-office-id="{{ $user->office_id }}"
                    >
                      <td><span class="user-cell-text" title="{{ $user->username }}">{{ $user->username }}</span></td>
                      <td><span class="user-cell-text" title="{{ $userName }}">{{ $userName }}</span></td>
                      <td><span class="user-cell-text" title="{{ $user->email }}">{{ $user->email }}</span></td>
                      <td><span class="role-badge item-owner-badge">ITEM OWNER</span></td>
                      <td><span class="user-cell-text" title="{{ $userOffice }}">{{ $userOffice }}</span></td>
                      <td><span class="status-badge {{ $userStatus === 'active' ? 'is-active' : 'is-inactive' }}">{{ ucfirst($userStatus) }}</span></td>
                      <td class="table-actions-cell">
                        <button class="table-edit-btn user-edit-btn" type="button">Edit</button>
                        @if(auth()->user()->user_id !== $user->user_id)
                          <form method="POST" action="{{ route('dashboard.users.destroy', ['userId' => $user->user_id]) }}" class="inline-action-form" onsubmit="return confirm('Delete this item owner?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="table-delete-btn">Delete</button>
                          </form>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                @endif

                <!-- Approvers Section -->
                @if($approvers->count() > 0)
                  <tr class="category-header-row">
                    <td colspan="7">
                      <div class="category-header">
                        <i class="bi bi-person-check"></i>
                        <span>Users</span>
                        <span class="category-count" data-group-count>{{ $formatUserCount($approvers->count()) }}</span>
                      </div>
                    </td>
                  </tr>
                  @foreach($approvers as $user)
                    @php
                      $userStatus = $resolveUserStatus($user);
                      $userOffice = $user->office?->department_name ?? 'No Office';
                      $userName = $resolveUserName($user);
                    @endphp
                    <tr
                      class="approver-row"
                      data-user-id="{{ $user->user_id }}"
                      data-user-username="{{ $user->username }}"
                      data-user-full-name="{{ $user->full_name }}"
                      data-user-name="{{ $userName }}"
                      data-user-email="{{ $user->email }}"
                      data-user-office-name="{{ strtolower((string) $userOffice) }}"
                      data-user-role="{{ $displayRole($user) }}"
                      data-user-status="{{ $userStatus }}"
                      data-user-created-at="{{ $user->created_at ? $user->created_at->timestamp : 0 }}"
                      data-user-office-id="{{ $user->office_id }}"
                    >
                      <td><span class="user-cell-text" title="{{ $user->username }}">{{ $user->username }}</span></td>
                      <td><span class="user-cell-text" title="{{ $userName }}">{{ $userName }}</span></td>
                      <td><span class="user-cell-text" title="{{ $user->email }}">{{ $user->email }}</span></td>
                      <td><span class="role-badge approver-badge">{{ strtoupper($user->role) }}</span></td>
                      <td><span class="user-cell-text" title="{{ $userOffice }}">{{ $userOffice }}</span></td>
                      <td><span class="status-badge {{ $userStatus === 'active' ? 'is-active' : 'is-inactive' }}">{{ ucfirst($userStatus) }}</span></td>
                      <td class="table-actions-cell">
                        <button class="table-edit-btn user-edit-btn" type="button">Edit</button>
                        @if(auth()->user()->user_id !== $user->user_id)
                          <form method="POST" action="{{ route('dashboard.users.destroy', ['userId' => $user->user_id]) }}" class="inline-action-form" onsubmit="return confirm('Delete this user?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="table-delete-btn">Delete</button>
                          </form>
                        @endif
                      </td>
                    </tr>
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
    <article class="facilities-modal-card" role="dialog" aria-modal="true" aria-labelledby="user-modal-title">
      <div class="facilities-modal-top"></div>
      <div class="facilities-modal-body">
        <h2 id="user-modal-title">Add User</h2>

        <form id="user-form" method="POST" action="{{ route('dashboard.users.store') }}">
          @csrf
          <input type="hidden" name="_method" id="user-form-method" value="POST" />
          <input type="hidden" name="user_id" id="user-id" />

          <label class="facilities-field-label" for="user-username">Username</label>
          <input id="user-username" class="facilities-input" name="username" type="text" placeholder="Username" required />

          <label class="facilities-field-label" for="user-full-name">Full Name</label>
          <input id="user-full-name" class="facilities-input" name="full_name" type="text" placeholder="Full Name" />

          <label class="facilities-field-label" for="user-email">Email</label>
          <input id="user-email" class="facilities-input" name="email" type="email" placeholder="Email address" required />

          <label class="facilities-field-label" for="user-role">Role</label>
          <select id="user-role" class="facilities-input facilities-select" name="role" required>
            <option value="user">USER</option>
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

          <div class="facilities-modal-actions">
            <button type="button" class="facilities-action-btn cancel" id="user-cancel-btn">Cancel</button>
            <button type="submit" class="facilities-action-btn submit" id="user-save-btn">Save User</button>
          </div>
        </form>
      </div>
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

          return ['admin', 'pf_admin', 'pc_admin'].includes(rowRole);
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
      }
    }
  </script>

  <script src="/js/dashboard.js"></script>
</body>
</html>
