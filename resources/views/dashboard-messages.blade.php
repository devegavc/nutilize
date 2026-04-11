<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Messages</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-messages.css" />
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

      <section class="content-card messages-content-card">
        <section class="messages-grid">
          <aside class="messages-list-panel">
            <h1>Messages</h1>
            <div class="messages-list-wrap">
              <button class="message-contact active" type="button" data-message-contact="Dela Cruz, Jon"><i class="bi bi-person-fill"></i> Dela Cruz, Jon</button>
              <button class="message-contact" type="button" data-message-contact="Santos, Ivan"><i class="bi bi-person-fill"></i> Santos, Ivan</button>
              <button class="message-contact" type="button" data-message-contact="Rivera, Martin"><i class="bi bi-person-fill"></i> Rivera, Martin</button>
              <button class="message-contact" type="button" data-message-contact="Gonzales, Pat"><i class="bi bi-person-fill"></i> Gonzales, Pat</button>
              <button class="message-contact" type="button" data-message-contact="Tan, Maricar"><i class="bi bi-person-fill"></i> Tan, Maricar</button>
              <button class="message-contact" type="button" data-message-contact="Ramirez, Carla"><i class="bi bi-person-fill"></i> Ramirez, Carla</button>
              <button class="message-contact" type="button" data-message-contact="Custudio, Van"><i class="bi bi-person-fill"></i> Custudio, Van</button>
              <button class="message-contact" type="button" data-message-contact="De Vega, Val"><i class="bi bi-person-fill"></i> De Vega, Val</button>
            </div>
          </aside>

          <section class="messages-chat-panel">
            <header class="messages-chat-head">
              <i class="bi bi-person-circle"></i>
              <span id="message-current-name">Gonzales, Pat</span>
            </header>

            <section class="messages-chat-body" id="message-thread-wrap">
              <p class="messages-empty" id="message-empty-state">No chat here yet...</p>
              <div class="message-thread" id="message-thread" aria-live="polite"></div>
            </section>

            <form class="messages-input-row" id="message-form">
              <button class="message-send-btn" id="message-send-btn" type="submit" aria-label="Send"><i class="bi bi-send-fill"></i></button>
              <input id="message-input" type="text" placeholder="Type Chat here..." />
            </form>
          </section>
        </section>
      </section>
    </section>
  </main>

  <script src="/js/dashboard.js"></script>
</body>
</html>

