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

      <section class="content-card request-content-card user-management-card">
        <div class="dashboard-page-header-top request-head">
          <h1 class="section-title">Manage Users</h1>
        </div>
        <div class="dashboard-page-header-bottom request-subhead">
          <p class="section-subtitle">Create, edit, and remove dashboard users, including item owners who manage their own equipment.</p>
          <div class="dashboard-page-actions">
            <button class="facilities-add-btn" id="user-add-btn" type="button"><span class="btn-icon">+</span> Add User</button>
            <button class="facilities-add-btn" id="item-owner-add-btn" type="button"><span class="btn-icon">+</span> Add Item Owner</button>
          </div>
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
                <!-- Admins Section -->
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
                @endphp

                @if($admins->count() > 0)
                  <tr class="category-header-row">
                    <td colspan="7">
                      <div class="category-header">
                        <i class="bi bi-shield-lock"></i>
                        <span>Admins</span>
                      </div>
                    </td>
                  </tr>
                  @foreach($admins as $user)
                    <tr
                      class="admin-row"
                      data-user-id="{{ $user->user_id }}"
                      data-user-username="{{ $user->username }}"
                      data-user-full_name="{{ $user->full_name }}"
                      data-user-email="{{ $user->email }}"
                      data-user-role="{{ $displayRole($user) }}"
                      data-user-office-id="{{ $user->office_id }}"
                    >
                      <td>{{ $user->username }}</td>
                      <td>{{ $user->full_name ?? '—' }}</td>
                      <td>{{ $user->email }}</td>
                      <td><span class="role-badge admin-badge">{{ strtoupper($user->role) }}</span></td>
                      <td>{{ $user->office?->department_name ?? 'No Office' }}</td>
                      <td>{{ $user->created_at ? $user->created_at->format('M d, Y') : 'N/A' }}</td>
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
                      </div>
                    </td>
                  </tr>
                  @foreach($itemOwners as $user)
                    <tr
                      class="admin-row"
                      data-user-id="{{ $user->user_id }}"
                      data-user-username="{{ $user->username }}"
                      data-user-full_name="{{ $user->full_name }}"
                      data-user-email="{{ $user->email }}"
                      data-user-role="{{ $displayRole($user) }}"
                      data-user-office-id="{{ $user->office_id }}"
                    >
                      <td>{{ $user->username }}</td>
                      <td>{{ $user->full_name ?? '—' }}</td>
                      <td>{{ $user->email }}</td>
                      <td><span class="role-badge approver-badge">ITEM OWNER</span></td>
                      <td>{{ $user->office?->department_name ?? 'Item Owner' }}</td>
                      <td>{{ $user->created_at ? $user->created_at->format('M d, Y') : 'N/A' }}</td>
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
                      </div>
                    </td>
                  </tr>
                  @foreach($approvers as $user)
                    <tr
                      class="approver-row"
                      data-user-id="{{ $user->user_id }}"
                      data-user-username="{{ $user->username }}"
                      data-user-full_name="{{ $user->full_name }}"
                      data-user-email="{{ $user->email }}"
                      data-user-role="{{ $displayRole($user) }}"
                      data-user-office-id="{{ $user->office_id }}"
                    >
                      <td>{{ $user->username }}</td>
                      <td>{{ $user->full_name ?? '—' }}</td>
                      <td>{{ $user->email }}</td>
                      <td><span class="role-badge approver-badge">{{ strtoupper($user->role) }}</span></td>
                      <td>{{ $user->office?->department_name ?? 'No Office' }}</td>
                      <td>{{ $user->created_at ? $user->created_at->format('M d, Y') : 'N/A' }}</td>
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
                  <tr>
                    <td colspan="7">No users available.</td>
                  </tr>
                @endif
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
      const fullName = row.dataset.userFullName;
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
  </script>

  <script src="/js/dashboard.js"></script>
</body>
</html>
