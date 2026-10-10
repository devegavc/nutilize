<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Feedback</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-inventory.css?v={{ filemtime(public_path('css/db-inventory.css')) }}" />
</head>
<body class="feedback-page">
  <script>
    window.authUser = {
      id: @json(auth()->user()->user_id),
      username: @json(auth()->user()->username ?? 'User'),
      email: @json(auth()->user()->email ?? ''),
      full_name: @json(auth()->user()->full_name ?? auth()->user()->username ?? 'User'),
      role: @json(auth()->user()->role ?? 'user')
    };
  </script>
  <header class="top-header">
    <div class="top-header-inner toolbar-card">
      <img src="/img/nutilize_wordmark.png?v={{ filemtime(public_path('img/nutilize_wordmark.png')) }}" alt="NU-TILIZE" class="toolbar-logo" />
      @include('partials.office-user-guide')
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

      <section class="content-card feedback-content-card">
        <header class="feedback-head">
          <h1 class="section-title">USER FEEDBACK</h1>
          <p>Satisfaction after a completed reservation, and the short survey about using NUtilize.</p>
        </header>

        <section class="stats-grid inventory-stats-grid feedback-summary-grid" aria-label="Feedback summary">
          <article class="stat-card inventory-stat-card">
            <span class="stat-icon"><i class="bi bi-emoji-smile-fill"></i></span>
            <div>
              <p class="stat-number feedback-score"><span>{{ $experience['average'] === null ? '—' : number_format($experience['average'], 1) }}</span><span class="feedback-score-max">/{{ $scoreMax }}</span></p>
              <p class="stat-label">Experience rating</p>
            </div>
          </article>
          <article class="stat-card inventory-stat-card">
            <span class="stat-icon"><i class="bi bi-star-fill"></i></span>
            <div>
              <p class="stat-number">{{ $experience['count'] }}</p>
              <p class="stat-label">Ratings received</p>
            </div>
          </article>
          <article class="stat-card inventory-stat-card">
            <span class="stat-icon"><i class="bi bi-clipboard-check-fill"></i></span>
            <div>
              <p class="stat-number feedback-score"><span>{{ $survey['average'] === null ? '—' : number_format($survey['average'], 1) }}</span><span class="feedback-score-max">/{{ $scoreMax }}</span></p>
              <p class="stat-label">Survey average</p>
            </div>
          </article>
          <article class="stat-card inventory-stat-card">
            <span class="stat-icon"><i class="bi bi-chat-left-text-fill"></i></span>
            <div>
              <p class="stat-number">{{ $survey['count'] }}</p>
              <p class="stat-label">Survey responses</p>
            </div>
          </article>
        </section>

        <section class="feedback-panels">
          <article class="feedback-panel">
            <h2>Survey</h2>
            <p class="feedback-panel-hint">Average score for each question, out of {{ $scoreMax }}.</p>
            @if ($survey['count'] === 0)
              <p class="feedback-empty">No survey responses yet.</p>
            @else
              <ul class="feedback-meter-list">
                @foreach ($survey['questions'] as $question)
                  @php
                    $percent = $question['average'] === null ? 0 : (int) round(($question['average'] / $scoreMax) * 100);
                  @endphp
                  <li>
                    <div class="feedback-meter-label">
                      <span>{{ $question['label'] }}</span>
                      <strong>{{ number_format($question['average'], 1) }}</strong>
                    </div>
                    <div class="feedback-meter" role="meter" aria-valuemin="0" aria-valuemax="{{ $scoreMax }}" aria-valuenow="{{ $question['average'] }}" aria-label="{{ $question['label'] }}">
                      <span style="width: {{ $percent }}%"></span>
                    </div>
                  </li>
                @endforeach
              </ul>
            @endif
          </article>

          <article class="feedback-panel">
            <h2>Experience ratings</h2>
            <p class="feedback-panel-hint">How many people gave each score after their reservation.</p>
            @if ($experience['count'] === 0)
              <p class="feedback-empty">No experience ratings yet.</p>
            @else
              <ul class="feedback-distribution">
                @foreach (array_reverse($experience['distribution'], true) as $score => $count)
                  @php
                    $percent = $experience['count'] === 0 ? 0 : (int) round(($count / $experience['count']) * 100);
                  @endphp
                  <li>
                    <span class="feedback-distribution-score">{{ $score }}</span>
                    <div class="feedback-meter" aria-hidden="true">
                      <span style="width: {{ $percent }}%"></span>
                    </div>
                    <span class="feedback-distribution-count">{{ $count }}</span>
                  </li>
                @endforeach
              </ul>
            @endif
          </article>
        </section>

        <section class="feedback-table-card">
          <h2>Survey responses</h2>
          <div class="table-wrap">
            <table class="history-table feedback-table">
              <thead>
                <tr>
                  <th>Submitted</th>
                  <th>Respondent</th>
                  <th>Navigation</th>
                  <th>Reservation</th>
                  <th>Response</th>
                  <th>Information</th>
                  <th>Comment</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($surveys as $surveyRow)
                  <tr>
                    <td>{{ $surveyRow['submitted_at'] }}</td>
                    <td>{{ $surveyRow['respondent'] }}</td>
                    <td>{{ $surveyRow['scores']['navigation'] }}</td>
                    <td>{{ $surveyRow['scores']['reservation'] }}</td>
                    <td>{{ $surveyRow['scores']['responsiveness'] }}</td>
                    <td>{{ $surveyRow['scores']['information'] }}</td>
                    <td class="feedback-comment">{{ $surveyRow['comment'] !== '' ? $surveyRow['comment'] : 'No additional comment' }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7">No survey responses yet.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </section>

        <section class="feedback-table-card">
          <h2>Reservation ratings</h2>
          <div class="table-wrap">
            <table class="history-table feedback-table">
              <thead>
                <tr>
                  <th>Submitted</th>
                  <th>Reservation</th>
                  <th>Activity</th>
                  <th>Respondent</th>
                  <th>Rating</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($ratings as $rating)
                  <tr>
                    <td>{{ $rating['submitted_at'] }}</td>
                    <td>#{{ $rating['reservation_id'] }}</td>
                    <td>{{ $rating['activity'] }}</td>
                    <td>{{ $rating['respondent'] }}</td>
                    <td>{{ $rating['score'] }} / {{ $scoreMax }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5">No experience ratings yet.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </section>
  </main>

  <script src="/js/dashboard.js?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
</body>
</html>
