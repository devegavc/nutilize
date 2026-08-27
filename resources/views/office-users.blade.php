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

      <section class="content-card office-users-card user-management-card">
        <div class="dashboard-page-header-top">
          <h1 class="section-title">Users</h1>
        </div>
        <div class="dashboard-page-header-bottom">
          <p class="section-subtitle">Manage {{ $programName }} accounts.</p>
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
          $normalizeUserRole = fn ($user) => strtolower(trim((string) ($user->role ?? '')));
          $programChairUsers = $users->filter(fn ($user) => $normalizeUserRole($user) === 'pc_admin')->values();
          $facultyUsers = $users->filter(fn ($user) => $normalizeUserRole($user) === 'faculty')->values();
          $students = $users->filter(fn ($user) => !in_array($normalizeUserRole($user), ['pc_admin', 'faculty'], true))->values();
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
          $rowRoleValue = function ($user) use ($normalizeUserRole): string {
              $role = $normalizeUserRole($user);

              if ($role === 'pc_admin') {
                  return 'pc_admin';
              }

              if ($role === 'faculty') {
                  return 'faculty';
              }

              return 'student';
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
              <p class="users-summary-value">{{ $programChairUsers->count() }}</p>
              <p class="users-summary-label">Program Chairs</p>
            </div>
          </article>
          <article class="users-summary-card">
            <span class="users-summary-icon"><i class="bi bi-person-fill"></i></span>
            <div class="users-summary-copy">
              <p class="users-summary-value">{{ $students->count() }}</p>
              <p class="users-summary-label">Students</p>
            </div>
          </article>
          <article class="users-summary-card">
            <span class="users-summary-icon"><i class="bi bi-circle-fill"></i></span>
            <div class="users-summary-copy">
              <p class="users-summary-value">{{ $activeUsersCount }}</p>
              <p class="users-summary-label">Active Users</p>
            </div>
          </article>
        </section>

        <section class="users-tools-bar" aria-label="User table filters">
          <div class="users-search-wrap">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input id="users-search-input" type="search" placeholder="Search username, name, email, or program" autocomplete="off" />
          </div>

          <div class="users-filter-group">
            <div class="users-filter-wrap">
              <label for="users-role-filter">Role</label>
              <select id="users-role-filter">
                <option value="all">All Roles</option>
                <option value="pc_admin">Program Chair</option>
                <option value="faculty">Faculty</option>
                <option value="student">Student</option>
              </select>
            </div>

            <div class="users-filter-wrap">
              <label for="users-sort-select">Sort</label>
              <select id="users-sort-select">
                <option value="name-asc">Name A-Z</option>
                <option value="name-desc">Name Z-A</option>
                <option value="username-asc">Username A-Z</option>
                <option value="username-desc">Username Z-A</option>
                <option value="newest">Newest Joined</option>
                <option value="oldest">Oldest Joined</option>
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
                  <th>Program</th>
                  <th>Joined</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="users-table-body">
                @if($programChairUsers->count() > 0)
                  <tr class="category-header-row">
                    <td colspan="7">
                      <div class="category-header">
                        <i class="bi bi-shield-lock"></i>
                        <span>Program Chair</span>
                        <span class="category-count" data-group-count>{{ $formatUserCount($programChairUsers->count()) }}</span>
                      </div>
                    </td>
                  </tr>
                  @foreach($programChairUsers as $programChair)
                    @php
                      $programChairStatus = $resolveUserStatus($programChair);
                      $programChairName = $programChair->full_name ?? '—';
                    @endphp
                    <tr
                      class="admin-row {{ (int) $programChair->user_id === $currentUserId ? 'is-current-user' : '' }}"
                      data-user-id="{{ $programChair->user_id }}"
                      data-user-username="{{ $programChair->username }}"
                      data-user-full-name="{{ $programChair->full_name }}"
                      data-user-name="{{ $programChairName }}"
                      data-user-email="{{ $programChair->email }}"
                      data-user-role="{{ $rowRoleValue($programChair) }}"
                      data-user-status="{{ $programChairStatus }}"
                      data-user-program="{{ $programName }}"
                      data-user-created-at="{{ $programChair->created_at ? $programChair->created_at->timestamp : 0 }}"
                    >
                      <td class="user-username-cell">
                        <span class="user-cell-text" title="{{ $programChair->username }}">{{ $programChair->username }}</span>
                      </td>
                      <td><span class="user-cell-text" title="{{ $programChairName }}">{{ $programChairName }}</span></td>
                      <td><span class="user-cell-text" title="{{ $programChair->email }}">{{ $programChair->email }}</span></td>
                      <td class="user-role-cell"><span class="role-badge admin-badge">PROGRAM CHAIR</span></td>
                      <td><span class="user-cell-text" title="{{ $programName }}">{{ $programName }}</span></td>
                      <td><span class="user-cell-text">{{ $programChair->created_at ? $programChair->created_at->format('M d, Y') : 'N/A' }}</span></td>
                      <td class="table-actions-cell">
                        <button class="table-edit-btn user-edit-btn" type="button">Edit</button>
                      </td>
                    </tr>
                  @endforeach
                @endif

                @if($facultyUsers->count() > 0)
                  <tr class="category-header-row">
                    <td colspan="7">
                      <div class="category-header">
                        <i class="bi bi-people"></i>
                        <span>Faculty</span>
                        <span class="category-count" data-group-count>{{ $formatUserCount($facultyUsers->count()) }}</span>
                      </div>
                    </td>
                  </tr>
                  @foreach($facultyUsers as $user)
                    @php
                      $userStatus = $resolveUserStatus($user);
                      $userName = $user->full_name ?? '—';
                    @endphp
                    <tr
                      data-user-id="{{ $user->user_id }}"
                      data-user-username="{{ $user->username }}"
                      data-user-full-name="{{ $user->full_name }}"
                      data-user-name="{{ $userName }}"
                      data-user-email="{{ $user->email }}"
                      data-user-role="{{ $rowRoleValue($user) }}"
                      data-user-status="{{ $userStatus }}"
                      data-user-program="{{ $programName }}"
                      data-user-created-at="{{ $user->created_at ? $user->created_at->timestamp : 0 }}"
                    >
                      <td class="user-username-cell">
                        <span class="user-cell-text" title="{{ $user->username }}">{{ $user->username }}</span>
                      </td>
                      <td><span class="user-cell-text" title="{{ $userName }}">{{ $userName }}</span></td>
                      <td><span class="user-cell-text" title="{{ $user->email }}">{{ $user->email }}</span></td>
                      <td class="user-role-cell"><span class="role-badge approver-badge">FACULTY</span></td>
                      <td><span class="user-cell-text" title="{{ $programName }}">{{ $programName }}</span></td>
                      <td><span class="user-cell-text">{{ $user->created_at ? $user->created_at->format('M d, Y') : 'N/A' }}</span></td>
                      <td class="table-actions-cell">
                        <button class="table-edit-btn user-edit-btn" type="button">Edit</button>
                        @if($currentUserId !== $user->user_id)
                          <form method="POST" action="{{ route('office.users.destroy', ['userId' => $user->user_id]) }}" class="inline-action-form" onsubmit="return confirm('Delete this faculty account?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="table-delete-btn">Delete</button>
                          </form>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                @endif

                @if($students->count() > 0)
                  <tr class="category-header-row">
                    <td colspan="7">
                      <div class="category-header">
                        <i class="bi bi-person-check"></i>
                        <span>Mobile Users</span>
                        <span class="category-count" data-group-count>{{ $formatUserCount($students->count()) }}</span>
                      </div>
                    </td>
                  </tr>
                  @foreach($students as $user)
                    @php
                      $userStatus = $resolveUserStatus($user);
                      $userName = $user->full_name ?? '—';
                    @endphp
                    <tr
                      data-user-id="{{ $user->user_id }}"
                      data-user-username="{{ $user->username }}"
                      data-user-full-name="{{ $user->full_name }}"
                      data-user-name="{{ $userName }}"
                      data-user-email="{{ $user->email }}"
                      data-user-role="{{ $rowRoleValue($user) }}"
                      data-user-status="{{ $userStatus }}"
                      data-user-program="{{ $programName }}"
                      data-user-created-at="{{ $user->created_at ? $user->created_at->timestamp : 0 }}"
                    >
                      <td class="user-username-cell">
                        <span class="user-cell-text" title="{{ $user->username }}">{{ $user->username }}</span>
                      </td>
                      <td><span class="user-cell-text" title="{{ $userName }}">{{ $userName }}</span></td>
                      <td><span class="user-cell-text" title="{{ $user->email }}">{{ $user->email }}</span></td>
                      <td class="user-role-cell"><span class="role-badge approver-badge">STUDENT</span></td>
                      <td><span class="user-cell-text" title="{{ $programName }}">{{ $programName }}</span></td>
                      <td><span class="user-cell-text">{{ $user->created_at ? $user->created_at->format('M d, Y') : 'N/A' }}</span></td>
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
                  <tr class="users-empty-state-row" data-empty-fallback>
                    <td colspan="7">
                      <div class="users-empty-state-content">
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <strong>No accounts for this program yet.</strong>
                        <span>New accounts are added to {{ $programName }}.</span>
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
    <article class="facilities-modal-card equipment-form-modal-card is-compact" role="dialog" aria-modal="true" aria-labelledby="user-modal-title">
      <header class="equipment-form-modal-head">
        <div>
          <h2 id="user-modal-title">Add User</h2>
          <p class="equipment-form-modal-subtitle">New accounts are added to {{ $programName }}.</p>
        </div>
      </header>

      <form id="user-form" class="equipment-form-modal-form" method="POST" action="{{ route('office.users.store') }}">
        @csrf
        <input type="hidden" name="_method" id="user-form-method" value="POST" />
        <input type="hidden" name="user_id" id="user-id" />
        <input type="hidden" name="program_id" value="{{ $programId }}" />

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
            <label class="facilities-field-label" for="user-password">Password</label>
            <input id="user-password" class="facilities-input" name="password" type="password" placeholder="Password" />
            <small class="facilities-input-note">Leave blank when editing to keep the current password.</small>
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
    const usersTableBody = document.getElementById('users-table-body');
    const usersSearchInput = document.getElementById('users-search-input');
    const usersRoleFilter = document.getElementById('users-role-filter');
    const usersStatusFilter = document.getElementById('users-status-filter');
    const usersSortSelect = document.getElementById('users-sort-select');
    const usersResetFiltersBtn = document.getElementById('users-reset-filters');
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
      userFullNameInput.value = row.dataset.userFullName || '';
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

    if (usersTableBody) {
      const userRows = Array.from(usersTableBody.querySelectorAll('tr[data-user-id]'));
      const categoryRows = Array.from(usersTableBody.querySelectorAll('tr.category-header-row'));

      if (userRows.length > 0 && categoryRows.length > 0) {
        const collator = new Intl.Collator(undefined, { sensitivity: 'base', numeric: true });
        const groupedSections = categoryRows.map((headerRow) => {
          const rows = [];
          let pointer = headerRow.nextElementSibling;

          while (pointer && !pointer.classList.contains('category-header-row') && pointer.id !== 'users-empty-state-row') {
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

          if (selectedRole === 'pc_admin') {
            return rowRole === 'pc_admin';
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
          const leftUsername = normalizeText(left.dataset.userUsername);
          const rightUsername = normalizeText(right.dataset.userUsername);
          const leftCreatedAt = Number.parseInt(left.dataset.userCreatedAt || '0', 10) || 0;
          const rightCreatedAt = Number.parseInt(right.dataset.userCreatedAt || '0', 10) || 0;

          if (sortMode === 'name-desc') {
            return collator.compare(rightName, leftName);
          }

          if (sortMode === 'username-asc') {
            return collator.compare(leftUsername, rightUsername);
          }

          if (sortMode === 'username-desc') {
            return collator.compare(rightUsername, leftUsername);
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
              const haystack = [
                normalizeText(row.dataset.userUsername),
                normalizeText(row.dataset.userName),
                normalizeText(row.dataset.userFullName),
                normalizeText(row.dataset.userEmail),
                normalizeText(row.dataset.userProgram),
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
