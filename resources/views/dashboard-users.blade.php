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

      <section class="content-card">
        <div class="dashboard-page-header-top">
          <h1 class="section-title">Manage Users</h1>
        </div>
        <div class="dashboard-page-header-bottom">
          <p class="section-subtitle">Create, edit, and remove users for the Physical Facilities dashboard.</p>
          <button class="facilities-add-btn" id="user-add-btn" type="button"><span class="btn-icon">+</span> Add User</button>
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
            <table class="inventory-table">
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
                  $admins = $users->filter(fn($u) => in_array($u->role, ['admin', 'pf_admin']));
                  $approvers = $users->filter(fn($u) => !in_array($u->role, ['admin', 'pf_admin']));
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
                      data-user-role="{{ $user->role }}"
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
                      data-user-role="{{ $user->role }}"
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
          </select>

          <label class="facilities-field-label" for="user-office">Office</label>
          <select id="user-office" class="facilities-input facilities-select" name="office_id">
            <option value="">No Office</option>
            @foreach($offices as $office)
              <option value="{{ $office->office_id }}">{{ $office->department_name }}</option>
            @endforeach
          </select>

          <label class="facilities-field-label" for="user-password">Password</label>
          <input id="user-password" class="facilities-input" name="password" type="password" placeholder="Password" />
          <small class="facilities-input-note">Leave blank when editing an existing user to keep the current password.</small>

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

    const resetModal = () => {
      userForm.action = window.userStoreEndpoint;
      userFormMethod.value = 'POST';
      userModalTitle.textContent = 'Add User';
      userIdInput.value = '';
      userUsernameInput.value = '';
      userFullNameInput.value = '';
      userEmailInput.value = '';
      userRoleInput.value = 'user';
      userOfficeInput.value = '';
      userPasswordInput.value = '';
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
      userModalTitle.textContent = 'Edit User';
      userIdInput.value = userId;
      userUsernameInput.value = username;
      userFullNameInput.value = fullName;
      userEmailInput.value = email;
      userRoleInput.value = role;
      userOfficeInput.value = officeId || '';
      userPasswordInput.value = '';
      openUserModal();
    };

    if (userAddBtn) {
      userAddBtn.addEventListener('click', () => {
        resetModal();
        openUserModal();
      });
    }

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
