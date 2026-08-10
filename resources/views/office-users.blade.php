<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Program Users</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-inventory.css" />
  <link rel="stylesheet" href="/css/office.css" />
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
      office_short_code: '{{ auth()->user()?->office?->short_code ?? '' }}',
      is_item_owner: @json(auth()->user()?->isItemOwnerAdmin() ?? false)
    };
    window.dashboardNavComponent = '/components/navbar-office.html';
    window.userStoreEndpoint = '{{ route('office.users.store') }}';
    window.userEndpointBase = '{{ url('/dashboard/office/users') }}';
  </script>

  <header class="top-header">
    <div class="top-header-inner toolbar-card">
      <img src="/img/nutilize_logo.png" alt="NU-TILIZE" class="toolbar-logo" />

      <div class="search-wrap">
        <i class="bi bi-search"></i>
        <input id="dashboard-search" type="text" placeholder="Search students" />
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

      <section class="content-card office-users-card">
        <div class="dashboard-page-header-top">
          <h1 class="section-title">Users</h1>
        </div>
        <div class="dashboard-page-header-bottom">
          <p class="section-subtitle">Manage {{ $programName }} accounts.</p>
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
            <table class="inventory-table users-table">
              <thead>
                <tr>
                  <th><i class="bi bi-person-lines-fill"></i> Username</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Program</th>
                  <th>Joined</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="users-table-body">
                @php
                  $programChair = $users->first(fn ($user) => strtolower((string) $user->role) === 'pc_admin');
                  $students = $users->filter(fn ($user) => strtolower((string) $user->role) !== 'pc_admin');
                @endphp

                @if($programChair)
                  <tr class="category-header-row">
                    <td colspan="7">
                      <div class="category-header">
                        <i class="bi bi-shield-lock"></i>
                        <span>Program Chair</span>
                      </div>
                    </td>
                  </tr>
                  <tr
                    class="admin-row {{ (int) $programChair->user_id === $currentUserId ? 'is-current-user' : '' }}"
                    data-user-id="{{ $programChair->user_id }}"
                    data-user-username="{{ $programChair->username }}"
                    data-user-full_name="{{ $programChair->full_name }}"
                    data-user-email="{{ $programChair->email }}"
                  >
                    <td>
                      {{ $programChair->username }}
                      @if((int) $programChair->user_id === $currentUserId)
                        <span class="role-badge admin-badge" style="margin-left:6px;">You</span>
                      @endif
                    </td>
                    <td>{{ $programChair->full_name ?? '—' }}</td>
                    <td>{{ $programChair->email }}</td>
                    <td><span class="role-badge admin-badge">PROGRAM CHAIR</span></td>
                    <td>{{ $programName }}</td>
                    <td>{{ $programChair->created_at ? $programChair->created_at->format('M d, Y') : 'N/A' }}</td>
                    <td class="table-actions-cell">
                      <button class="table-edit-btn user-edit-btn" type="button">Edit</button>
                    </td>
                  </tr>
                @endif

                @if($students->count() > 0)
                  <tr class="category-header-row">
                    <td colspan="7">
                      <div class="category-header">
                        <i class="bi bi-person-check"></i>
                        <span>Students</span>
                      </div>
                    </td>
                  </tr>
                  @foreach($students as $user)
                    <tr
                      data-user-id="{{ $user->user_id }}"
                      data-user-username="{{ $user->username }}"
                      data-user-full_name="{{ $user->full_name }}"
                      data-user-email="{{ $user->email }}"
                    >
                      <td>{{ $user->username }}</td>
                      <td>{{ $user->full_name ?? '—' }}</td>
                      <td>{{ $user->email }}</td>
                      <td><span class="role-badge approver-badge">STUDENT</span></td>
                      <td>{{ $programName }}</td>
                      <td>{{ $user->created_at ? $user->created_at->format('M d, Y') : 'N/A' }}</td>
                      <td class="table-actions-cell">
                        <button class="table-edit-btn user-edit-btn" type="button">Edit</button>
                        @if($currentUserId !== $user->user_id)
                          <form method="POST" action="{{ route('office.users.destroy', ['userId' => $user->user_id]) }}" class="inline-action-form" onsubmit="return confirm('Delete this student account?');">
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
                    <td colspan="7">No accounts for this program yet.</td>
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
        <p class="section-subtitle" style="margin-top:0;">New accounts are added to {{ $programName }}.</p>

        <form id="user-form" method="POST" action="{{ route('office.users.store') }}">
          @csrf
          <input type="hidden" name="_method" id="user-form-method" value="POST" />
          <input type="hidden" name="user_id" id="user-id" />
          <input type="hidden" name="program_id" value="{{ $programId }}" />

          <label class="facilities-field-label" for="user-username">Username</label>
          <input id="user-username" class="facilities-input" name="username" type="text" placeholder="Username" required />

          <label class="facilities-field-label" for="user-full-name">Full Name</label>
          <input id="user-full-name" class="facilities-input" name="full_name" type="text" placeholder="Full Name" />

          <label class="facilities-field-label" for="user-email">Email</label>
          <input id="user-email" class="facilities-input" name="email" type="email" placeholder="Email address" required />

          <label class="facilities-field-label" for="user-password">Password</label>
          <input id="user-password" class="facilities-input" name="password" type="password" placeholder="Password" />
          <small class="facilities-input-note">Leave blank when editing to keep the current password.</small>

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
      userPasswordInput.required = true;
      userPasswordInput.value = '';
    };

    const populateModal = (row) => {
      userForm.action = `${window.userEndpointBase}/${row.dataset.userId}`;
      userFormMethod.value = 'PATCH';
      userModalTitle.textContent = 'Edit User';
      userIdInput.value = row.dataset.userId;
      userUsernameInput.value = row.dataset.userUsername;
      userFullNameInput.value = row.dataset.userFullName;
      userEmailInput.value = row.dataset.userEmail;
      userPasswordInput.required = false;
      userPasswordInput.value = '';
      openUserModal();
    };

    userAddBtn?.addEventListener('click', () => {
      resetModal();
      openUserModal();
    });

    userCancelBtn?.addEventListener('click', closeUserModal);
    modalOverlay?.addEventListener('click', closeUserModal);

    editButtons.forEach((button) => {
      button.addEventListener('click', (event) => {
        const row = event.target.closest('tr');
        if (!row?.dataset.userId) {
          return;
        }
        populateModal(row);
      });
    });
  </script>

  <script src="/js/dashboard.js"></script>
</body>
</html>
