<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Office Archieve</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
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
      office_name: '{{ auth()->user()?->office?->department_name ?? 'Office' }}'
    };
    window.dashboardNavComponent = '/components/navbar-office.html';
  </script>

  @php
    $archivedRecords = [
      [
        'request_id' => 'REQ-2026-0417-001',
        'requested_by' => 'Alyssa Mae Cruz',
        'resource' => 'Room 607',
        'deleted_at' => 'Apr 15, 2026 10:30 AM',
        'deleted_by' => 'Office Staff - SDO',
        'reason' => 'Duplicate request entry',
        'retention' => 'Under Retention',
      ],
      [
        'request_id' => 'REQ-2026-0415-013',
        'requested_by' => 'Jasper Dela Torre',
        'resource' => 'Projector #09',
        'deleted_at' => 'Apr 14, 2026 04:12 PM',
        'deleted_by' => 'Office Admin - Registrar',
        'reason' => 'Canceled by requester',
        'retention' => 'Soft Deleted',
      ],
      [
        'request_id' => 'REQ-2026-0412-027',
        'requested_by' => 'Regine Velasquez',
        'resource' => 'Speaker #32',
        'deleted_at' => 'Apr 13, 2026 09:05 AM',
        'deleted_by' => 'Office Staff - PFMO',
        'reason' => 'Conflict with facility maintenance',
        'retention' => 'Soft Deleted',
      ],
      [
        'request_id' => 'REQ-2026-0407-041',
        'requested_by' => 'Kenneth Lim',
        'resource' => 'Lab PC Set A',
        'deleted_at' => 'Apr 09, 2026 11:22 AM',
        'deleted_by' => 'System Auto-Cleanup',
        'reason' => 'Expired draft beyond retention window',
        'retention' => 'Purge Ready',
      ],
      [
        'request_id' => 'REQ-2026-0402-055',
        'requested_by' => 'Maria Santos',
        'resource' => 'Room 503',
        'deleted_at' => 'Apr 05, 2026 02:01 PM',
        'deleted_by' => 'Office Admin - Library',
        'reason' => 'Invalid schedule information',
        'retention' => 'Under Retention',
      ],
      [
        'request_id' => 'REQ-2026-0329-061',
        'requested_by' => 'John Paul Pastrana',
        'resource' => 'TV #03',
        'deleted_at' => 'Apr 03, 2026 08:44 AM',
        'deleted_by' => 'Office Staff - SDO',
        'reason' => 'Merged with updated request',
        'retention' => 'Soft Deleted',
      ],
    ];

    $totalArchived = count($archivedRecords);
    $softDeletedCount = collect($archivedRecords)->where('retention', 'Soft Deleted')->count();
    $underRetentionCount = collect($archivedRecords)->where('retention', 'Under Retention')->count();
    $purgeReadyCount = collect($archivedRecords)->where('retention', 'Purge Ready')->count();
  @endphp

  <header class="top-header">
    <div class="top-header-inner toolbar-card">
      <img src="/img/nutilize_logo.png" alt="NU-TILIZE" class="toolbar-logo" />

      <div class="search-wrap">
        <i class="bi bi-search"></i>
        <input id="dashboard-search" type="text" placeholder="Search archived records" />
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

      <section class="content-card office-archive-card">
        <h1 class="section-title">OFFICE ARCHIEVE DASHBOARD</h1>
        <p class="office-subtitle">Soft-deleted request logs (dummy UI data only)</p>

        <section class="office-archive-overview" aria-label="Archive summary">
          <article class="office-archive-tile">
            <span class="office-archive-icon"><i class="bi bi-archive-fill"></i></span>
            <div>
              <p class="office-archive-value">{{ $totalArchived }}</p>
              <p class="office-archive-label">Total Archived</p>
            </div>
          </article>
          <article class="office-archive-tile">
            <span class="office-archive-icon"><i class="bi bi-trash3-fill"></i></span>
            <div>
              <p class="office-archive-value">{{ $softDeletedCount }}</p>
              <p class="office-archive-label">Soft Deleted</p>
            </div>
          </article>
          <article class="office-archive-tile">
            <span class="office-archive-icon"><i class="bi bi-shield-check"></i></span>
            <div>
              <p class="office-archive-value">{{ $underRetentionCount }}</p>
              <p class="office-archive-label">Under Retention</p>
            </div>
          </article>
          <article class="office-archive-tile">
            <span class="office-archive-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
            <div>
              <p class="office-archive-value">{{ $purgeReadyCount }}</p>
              <p class="office-archive-label">Purge Ready</p>
            </div>
          </article>
        </section>

        <section class="office-archive-history-card" aria-label="Soft deleted records table">
          <header class="office-archive-history-head">
            <h2>Soft Deleted Records</h2>
            <div class="office-archive-legend">
              <span style="color:#9c4d00;">Soft Deleted</span>
              <span style="color:#2b4298;">Under Retention</span>
              <span style="color:#9a0f0f;">Purge Ready</span>
            </div>
          </header>

          <div class="table-wrap office-archive-wrap">
            <table class="office-archive-table">
              <thead>
                <tr>
                  <th>Request ID</th>
                  <th>Requested By</th>
                  <th>Resource</th>
                  <th>Deleted At</th>
                  <th>Deleted By</th>
                  <th>Reason</th>
                  <th>Retention State</th>
                </tr>
              </thead>
              <tbody>
                @foreach($archivedRecords as $record)
                  @php
                    $statusClass = strtolower(str_replace(' ', '-', $record['retention']));
                  @endphp
                  <tr>
                    <td>{{ $record['request_id'] }}</td>
                    <td>{{ $record['requested_by'] }}</td>
                    <td>{{ $record['resource'] }}</td>
                    <td>{{ $record['deleted_at'] }}</td>
                    <td>{{ $record['deleted_by'] }}</td>
                    <td>{{ $record['reason'] }}</td>
                    <td>
                      <span class="archive-status {{ $statusClass }}">{{ $record['retention'] }}</span>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <p class="office-archive-note">
            Demo UI only: these records are static placeholders for soft-delete archive behavior.
          </p>
        </section>
      </section>
    </section>
  </main>

  <script src="/js/dashboard.js"></script>
</body>
</html>
