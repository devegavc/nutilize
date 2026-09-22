<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Profile</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-profile.css?v={{ filemtime(public_path('css/db-profile.css')) }}" />
</head>
<body>
  @php
    $authUser = auth()->user();
    $fullName = trim((string) ($authUser->full_name ?? $authUser->username ?? ''));
    $firstName = trim((string) ($authUser->first_name ?? ''));
    $lastName = trim((string) ($authUser->last_name ?? ''));

    if ($firstName === '' || $lastName === '') {
        $nameParts = preg_split('/\s+/', $fullName) ?: [];
        $firstName = $firstName !== '' ? $firstName : ($nameParts[0] ?? '');
        $lastName = $lastName !== '' ? $lastName : (count($nameParts) > 1 ? $nameParts[count($nameParts) - 1] : '');
    }

    $middleInitial = $authUser->middle_initial ?? '';
    $shouldSelectProgram = method_exists($authUser, 'shouldSelectProgram') && $authUser->shouldSelectProgram();
    $programName = $authUser->academicProgram?->name ?? 'Not Set';

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
      'program_id' => $authUser->program_id ?? null,
      'should_select_program' => $shouldSelectProgram,
      'profile_update_url' => route('dashboard.profile.update'),
    ];

    $profileNavComponent = method_exists($authUser, 'isPhysicalFacilitiesAdmin') && $authUser->isPhysicalFacilitiesAdmin()
      ? '/components/navbar.html'
      : '/components/navbar-office.html';
  @endphp
  <script>
    window.authUser = @json($authUserPayload);
    window.academicPrograms = @json($programs ?? []);
    window.dashboardNavComponent = @json($profileNavComponent);
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

      <section class="content-card profile-content-card">
        <header class="profile-page-header">
          <h1 class="section-title">Profile</h1>
          <button class="profile-edit-btn" type="button">Edit Profile</button>
        </header>

        <section class="profile-photo-card" aria-label="Profile photo">
          <div class="profile-avatar" id="profile-avatar" aria-hidden="true">
            <img id="profile-avatar-image" class="profile-avatar-image" alt="Profile avatar" />
            <i class="bi bi-person-fill profile-avatar-icon"></i>
          </div>
        </section>

        <section class="profile-grid">
          <article class="profile-card">
            <h2>Personal Information</h2>
            <div class="profile-fields">
              <div class="profile-info-row">
                <label for="profile-first-name">First Name</label>
                <input id="profile-first-name" type="text" value="{{ $firstName !== '' ? $firstName : 'Not Set' }}" readonly tabindex="-1" />
              </div>

              <div class="profile-info-row">
                <label for="profile-middle-name">Middle Initial</label>
                <input id="profile-middle-name" type="text" value="{{ $middleInitial !== '' ? $middleInitial : 'Not Set' }}" readonly tabindex="-1" />
              </div>

              <div class="profile-info-row">
                <label for="profile-last-name">Last Name</label>
                <input id="profile-last-name" type="text" value="{{ $lastName !== '' ? $lastName : 'Not Set' }}" readonly tabindex="-1" />
              </div>

              <div class="profile-info-row">
                <label for="profile-suffix">Suffix</label>
                <input id="profile-suffix" type="text" value="{{ $authUser->suffix ?? 'Not Set' }}" readonly tabindex="-1" />
              </div>

              @if ($shouldSelectProgram)
                <div class="profile-info-row">
                  <label for="profile-program">Program</label>
                  <input id="profile-program" type="text" value="{{ $programName }}" readonly tabindex="-1" />
                </div>
              @endif
            </div>
          </article>

          <article class="profile-card">
            <h2>Administrator Information</h2>
            <div class="profile-fields">
              <div class="profile-info-row">
                <label for="profile-admin-id">Admin ID</label>
                <input id="profile-admin-id" type="text" value="{{ $authUser->user_id ?? '' }}" readonly tabindex="-1" />
              </div>
              <div class="profile-info-row">
                <label for="profile-email">Email</label>
                <input id="profile-email" type="text" value="{{ $authUser->email ?? '' }}" readonly tabindex="-1" />
              </div>
              <div class="profile-info-row">
                <label for="profile-contact">Contact Number</label>
                <input id="profile-contact" type="text" value="{{ $authUser->contact_number ?? 'Not Set' }}" readonly tabindex="-1" />
              </div>
              <div class="profile-info-row">
                <label for="profile-phone">Phone Number</label>
                <input id="profile-phone" type="text" value="{{ $authUser->phone_number ?? 'Not Set' }}" readonly tabindex="-1" />
              </div>
            </div>
          </article>
        </section>
      </section>
    </section>
  </main>

  <section class="profile-edit-modal" id="profile-edit-modal" aria-hidden="true">
    <div class="profile-edit-overlay"></div>
    <article class="profile-edit-card" role="dialog" aria-modal="true" aria-labelledby="profile-edit-title">
      <header class="profile-edit-header">
        <h2 id="profile-edit-title">Edit Personal Information</h2>
      </header>
      <div class="profile-edit-body">

        <div class="profile-edit-avatar-wrap">
          <div class="profile-edit-avatar" id="profile-edit-avatar" role="button" tabindex="0" aria-label="Upload profile picture">
            <img id="profile-edit-avatar-image" class="profile-edit-avatar-image" alt="Avatar preview" />
            <i class="bi bi-person-fill profile-edit-avatar-icon"></i>
          </div>
          <input id="profile-avatar-upload" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" hidden />
          <div class="profile-edit-photo-actions">
            <button type="button" class="profile-edit-upload-btn" id="profile-edit-upload-btn">Upload Photo</button>
            <button type="button" class="profile-edit-delete-btn" id="profile-delete-photo-btn" hidden>Delete Photo</button>
          </div>
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

            @if ($shouldSelectProgram)
              <label class="profile-edit-label" for="profile-modal-program-id">Program</label>
              <select id="profile-modal-program-id" class="profile-edit-input">
                <option value="">Select your program</option>
                @php
                  $programsBySchool = ($programs ?? collect())->groupBy('school_name');
                @endphp
                @foreach ($programsBySchool as $schoolName => $schoolPrograms)
                  <optgroup label="{{ $schoolName }}">
                    @foreach ($schoolPrograms as $program)
                      <option value="{{ $program->program_id }}" @selected((string) ($authUser->program_id ?? '') === (string) $program->program_id)>
                        {{ $program->name }}
                      </option>
                    @endforeach
                  </optgroup>
                @endforeach
              </select>
            @endif
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

  <section class="profile-delete-modal" id="profile-delete-modal" aria-hidden="true">
    <div class="profile-delete-overlay" data-close-profile-delete="true"></div>
    <article class="profile-delete-card" role="dialog" aria-modal="true" aria-labelledby="profile-delete-title">
      <h2 id="profile-delete-title">Delete Profile Photo?</h2>
      <p>Are you sure you want to remove your profile photo? This action cannot be undone.</p>
      <div class="profile-delete-actions">
        <button type="button" class="profile-delete-cancel" id="profile-delete-cancel" data-close-profile-delete="true">Cancel</button>
        <button type="button" class="profile-delete-confirm" id="profile-delete-confirm">Delete Photo</button>
      </div>
    </article>
  </section>

  <script src="/js/dashboard.js?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
</body>
</html>

