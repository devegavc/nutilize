<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
            <button class="request-tab" type="button" data-request-tab="pending">Pending</button>
          </div>
          <div class="request-date-row">
            <span class="today">Today</span>
            <span class="date">{{ now()->format('F j, Y') }}</span>
          </div>
        </section>

        <section class="request-list-wrap">
          @php
            $allRequests = $finalRequests->concat($pendingRequests);
          @endphp

          @forelse($allRequests as $requestData)
            @php
              $reservation = $requestData['reservation'];
              $user = $reservation->user;
              $requesterName = $user->full_name ?? $user->username ?? 'User';
              $displayPhone = $user->phone_number ?? $user->contact_number ?? 'N/A';
              $cssVisibilityClass = $requestData['tab'] === 'final' ? 'final-only' : 'pending-only';
            @endphp
            <article class="request-item {{ $cssVisibilityClass }} {{ $requestData['decision_status_class'] }}" data-requester="{{ $requesterName }}">
              <div class="request-main-col">
                <div class="request-row-title">
                  <strong>Current Request</strong>
                  <span>#NU-{{ str_pad((string) $reservation->reservation_id, 6, '0', STR_PAD_LEFT) }}</span>
                  <span class="status-dots">
                    @for($i = 1; $i <= 5; $i++)
                      <i class="bi {{ $i <= $requestData['approved_steps'] ? 'bi-check-circle-fill' : 'bi-circle-fill' }}"></i>
                    @endfor
                  </span>
                </div>
                <p class="request-owner">{{ $requesterName }}</p>
                <p class="request-phone">{{ $displayPhone }}</p>
                <div class="request-event-row">
                  <strong>Event Name</strong>
                  <span>{{ $reservation->activity_name ?? 'Untitled Activity' }}</span>
                </div>
                <div class="request-meta-row">
                  <span>Date: {{ optional($reservation->created_at)->format('d/m/Y') ?? 'N/A' }}</span>
                  <span>Time: {{ optional($reservation->created_at)->format('g:i A') ?? 'N/A' }}</span>
                </div>
              </div>

              <div class="request-side-event"><strong>Event Name</strong> {{ $reservation->activity_name ?? 'Untitled Activity' }}</div>

              <div class="request-resource-col">
                <h3>Requested resources</h3>
                <div class="resource-grid">
                  @forelse($requestData['resources'] as $resource)
                    <span><i class="bi {{ $resource['icon'] }}"></i> {{ $resource['quantity'] }} x {{ $resource['label'] }}</span>
                  @empty
                    <span><i class="bi bi-box-seam"></i> No resources listed</span>
                  @endforelse
                </div>
                <div class="request-action-stack">
                  <button class="approve-btn" type="button" data-reservation-id="{{ $reservation->reservation_id }}" data-final-action="approve">Approve</button>
                  <button class="reject-btn" type="button" data-reservation-id="{{ $reservation->reservation_id }}" data-final-action="reject">Reject</button>
                </div>
                <div class="request-decision" aria-live="polite">
                  <p class="request-decision-name">
                    {{ $requesterName }}'s request
                    {{ $requestData['decision_badge'] === 'Pending' ? 'is pending' : 'has been ' . strtolower($requestData['decision_badge']) }}
                  </p>
                  <p class="request-decision-text"></p>
                  <span class="request-decision-badge">{{ $requestData['decision_badge'] }}</span>
                </div>
              </div>
            </article>
          @empty
            <article class="request-item final-only" data-requester="User">
              <div class="request-main-col">
                <div class="request-row-title">
                  <strong>No Requests Yet</strong>
                </div>
                <p class="request-owner">No reservation records found in the database.</p>
                <p class="request-phone">Please submit a reservation to see entries here.</p>
              </div>
            </article>
          @endforelse
        </section>
      </section>
    </section>
  </main>

  <script src="/js/dashboard.js"></script>
</body>
</html>

