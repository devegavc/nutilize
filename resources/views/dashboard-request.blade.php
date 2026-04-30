<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Requests</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-requests.css" />
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

      <section class="content-card request-content-card">
        <h1 class="section-title">TEACHER REQUEST</h1>

        <section class="request-head">
          <div class="request-tabs" role="tablist" aria-label="Request status">
            <button class="request-tab active" type="button" data-request-tab="final">Final Approval</button>
            <button class="request-tab" type="button" data-request-tab="return">Waiting Return</button>
            <button class="request-tab" type="button" data-request-tab="pending">Pending</button>
          </div>
          <div class="request-date-row">
            <span class="today">Today</span>
            <span class="date">{{ now()->format('F j, Y') }}</span>
          </div>
        </section>

        <section class="request-list-wrap" id="request-list-wrap">
          @include('partials.dashboard-request-list')
        </section>
      </section>
    </section>
  </main>

  <script>
    window.requestListRefreshUrl = '{{ route('dashboard.request.list') }}';
  </script>

  <script src="/js/dashboard.js"></script>
</body>
</html>

