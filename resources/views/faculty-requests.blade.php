<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Faculty Approvals</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/office.css?v={{ filemtime(public_path('css/office.css')) }}" />
</head>
<body class="office-app">
  <header class="top-header">
    <div class="top-header-inner toolbar-card">
      <img src="/img/nutilize_wordmark.png?v={{ filemtime(public_path('img/nutilize_wordmark.png')) }}" alt="NU-TILIZE" class="toolbar-logo" />
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="toolbar-icon" type="submit" aria-label="Log out"><i class="bi bi-box-arrow-right"></i></button>
      </form>
    </div>
  </header>
  <main class="dashboard-shell">
    <section class="content-card office-requests-card" style="margin: 24px auto; max-width: 1100px;">
      <h1 class="section-title">FACULTY APPROVAL</h1>
      <p class="office-subtitle">Student requests stay here until you approve them. They do not move to the program chair or General Education before that.</p>
      @if(session('success'))
        <p class="office-subtitle">{{ session('success') }}</p>
      @endif
      @if(session('error'))
        <p class="office-subtitle">{{ session('error') }}</p>
      @endif
      <div class="table-wrap">
        <table class="office-request-history-table">
          <thead>
            <tr>
              <th>Reservation</th>
              <th>Student</th>
              <th>Activity</th>
              <th>Event date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($requests as $request)
              <tr>
                <td>#{{ $request->reservation_id }}</td>
                <td>{{ trim((string) (($request->first_name ?? '').' '.($request->last_name ?? ''))) ?: ($request->full_name ?: $request->username) }}</td>
                <td>{{ $request->activity_name ?: 'N/A' }}</td>
                <td>{{ $request->Date_of_Activity ? \Carbon\Carbon::parse($request->Date_of_Activity)->format('M d, Y') : 'N/A' }}</td>
                <td>
                  <form method="POST" action="{{ route('faculty.requests.approve', $request->reservation_id) }}" style="display:inline;">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="office-queue-action-btn office-queue-approve">Approve</button>
                  </form>
                  <form method="POST" action="{{ route('faculty.requests.reject', $request->reservation_id) }}" style="display:inline;">
                    @csrf
                    @method('PATCH')
                    <input name="rejection_reason" maxlength="500" placeholder="Rejection reason" required style="margin-right:8px;" />
                    <button type="submit" class="office-queue-action-btn office-queue-reject">Reject</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5">No student requests are waiting for your approval.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
