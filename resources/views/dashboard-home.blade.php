<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>NUtilize | Home</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-home.css" />
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
    window.quickReportDetails = @json($quickReports ?? []);
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
      <div id="navbar-container"></div>

      <section class="content-card home-dashboard-card">
        <h1 class="section-title">PHYSICAL FACILITIES DASHBOARD</h1>

        <section class="stats-grid">
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-files"></i></span>
            <div>
              <p class="stat-number">{{ str_pad((string) ($stats['total_requests'] ?? 0), 2, '0', STR_PAD_LEFT) }}</p>
              <p class="stat-label">Total Request</p>
            </div>
          </article>
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-wallet2"></i></span>
            <div>
              <p class="stat-number">{{ str_pad((string) ($stats['borrowed'] ?? 0), 2, '0', STR_PAD_LEFT) }}</p>
              <p class="stat-label">Borrowed</p>
            </div>
          </article>
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-check-circle-fill"></i></span>
            <div>
              <p class="stat-number">{{ str_pad((string) ($stats['available'] ?? 0), 2, '0', STR_PAD_LEFT) }}</p>
              <p class="stat-label">Available</p>
            </div>
          </article>
          <article class="stat-card">
            <span class="stat-icon"><i class="bi bi-wrench-adjustable-circle"></i></span>
            <div>
              <p class="stat-number">{{ str_pad((string) ($stats['maintenance'] ?? 0), 2, '0', STR_PAD_LEFT) }}</p>
              <p class="stat-label">Maintenance</p>
            </div>
          </article>
        </section>

        <section class="middle-grid">
          <article class="quick-view">
            <div class="quick-view-header"><i class="bi bi-exclamation-circle-fill"></i> Report Quick View</div>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Reported by:</th>
                    <th>Attachment</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="report-table-body">
                  @forelse(($quickReports ?? []) as $report)
                    <tr>
                      <td>{{ $report['item'] }}</td>
                      <td>{{ $report['reported_by'] }}</td>
                      <td>{{ $report['attachment_label'] }}</td>
                      <td><span class="badge {{ $report['status_class'] }}">{{ $report['status_label'] }}</span></td>
                      <td>
                        <button
                          type="button"
                          class="quick-report-details-btn"
                          data-quick-report-details
                          data-report-id="{{ $report['id'] ?? '' }}"
                          aria-label="View details for {{ $report['item'] }}"
                        >
                          Details
                        </button>
                      </td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="5">No reports found.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </article>

          <div class="home-right-stack">
            <article class="tasks-panel">
              <div class="tasks-panel-head">
                <h2>Tasks To Accomplish</h2>
                <p class="tasks-sub">Open items that need action now</p>
              </div>
              <ul class="tasks-list">
                <li>
                  <a class="task-link" href="{{ $tasks['pending_final_url'] ?? '/dashboard/request' }}">
                    <span class="task-icon"><i class="bi bi-inboxes-fill"></i></span>
                    <span class="task-copy">
                      <strong>Pending Final Approvals</strong>
                      <small>Requests waiting for PF decision</small>
                    </span>
                    <span class="task-count {{ ((int) ($tasks['pending_final_approvals'] ?? 0)) > 0 ? 'is-alert' : '' }}">{{ $tasks['pending_final_approvals'] ?? 0 }}</span>
                  </a>
                </li>
                <li>
                  <a class="task-link" href="{{ $tasks['review_damaged_url'] ?? '/dashboard/maintenance?tab=damaged' }}">
                    <span class="task-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
                    <span class="task-copy">
                      <strong>Review Damaged Items</strong>
                      <small>Units marked damaged</small>
                    </span>
                    <span class="task-count {{ ((int) ($tasks['review_damaged_items'] ?? 0)) > 0 ? 'is-alert' : '' }}">{{ $tasks['review_damaged_items'] ?? 0 }}</span>
                  </a>
                </li>
                <li>
                  <a class="task-link" href="{{ $tasks['need_repair_url'] ?? '/dashboard/maintenance?tab=maintenance' }}">
                    <span class="task-icon"><i class="bi bi-tools"></i></span>
                    <span class="task-copy">
                      <strong>Need Repair</strong>
                      <small>Units and rooms under maintenance</small>
                    </span>
                    <span class="task-count {{ ((int) ($tasks['need_repair'] ?? 0)) > 0 ? 'is-alert' : '' }}">{{ $tasks['need_repair'] ?? 0 }}</span>
                  </a>
                </li>
              </ul>
            </article>

            <button class="home-announcement-btn" type="button" data-open-announcements aria-haspopup="dialog" aria-controls="announcements-modal">
              <span class="home-announcement-icon"><i class="bi bi-megaphone-fill"></i></span>
              <span class="home-announcement-copy">
                <strong>Announcement</strong>
                <small>Post updates for offices and borrowers</small>
              </span>
              <i class="bi bi-chevron-right home-announcement-chevron"></i>
            </button>
          </div>
        </section>

        <section class="home-extra-grid">
          <article class="extra-card upcoming-card">
            <h3><i class="bi bi-calendar2-event-fill"></i> Upcoming Requests</h3>
            <ul>
              @forelse(($upcomingRequests ?? []) as $request)
                <li>
                  <span>{{ $request['time_label'] }}</span>
                  <strong>{{ $request['title'] }}</strong>
                  <small>{{ $request['subtitle'] }}</small>
                </li>
              @empty
                <li>
                  <span>{{ now()->format('F j') }}</span>
                  <strong>No open requests</strong>
                  <small>Active reservation requests across all offices will appear here.</small>
                </li>
              @endforelse
            </ul>
          </article>

          <article class="extra-card highlights-card">
            <h3><i class="bi bi-stars"></i> Daily Highlights</h3>
            <div class="highlights-grid">
              <div>
                <p class="highlight-label">Resolved Today</p>
                <p class="highlight-value">{{ $dailyHighlights['resolved_today'] ?? 0 }}</p>
              </div>
              <div>
                <p class="highlight-label">Pending Reports</p>
                <p class="highlight-value">{{ $dailyHighlights['pending_reports'] ?? 0 }}</p>
              </div>
              <div>
                <p class="highlight-label">Rooms Utilized</p>
                <p class="highlight-value">{{ $dailyHighlights['rooms_utilized'] ?? 0 }}</p>
              </div>
              <div>
                <p class="highlight-label">Equipment Checked</p>
                <p class="highlight-value">{{ $dailyHighlights['equipment_checked'] ?? 0 }}</p>
              </div>
            </div>
          </article>
        </section>
      </section>
    </section>
  </main>

  <div
    id="announcements-modal"
    class="announcements-modal{{ !empty($openAnnouncementsModal) ? ' is-open' : '' }}"
    aria-hidden="{{ !empty($openAnnouncementsModal) ? 'false' : 'true' }}"
  >
    <div class="announcements-modal-overlay" data-close-announcements></div>
    <section class="announcements-modal-card" role="dialog" aria-modal="true" aria-labelledby="announcements-modal-title">
      <header class="announcements-modal-head">
        <div>
          <p class="announcements-modal-kicker">Physical Facilities</p>
          <h2 id="announcements-modal-title">Announcements</h2>
        </div>
        <button class="announcements-modal-close" type="button" data-close-announcements aria-label="Close announcements">
          <i class="bi bi-x-lg"></i>
        </button>
      </header>

      @if(session('success'))
        <p class="announcement-flash is-success" data-announcement-flash>{{ session('success') }}</p>
      @endif
      @if(session('error'))
        <p class="announcement-flash is-error" data-announcement-flash>{{ session('error') }}</p>
      @endif

      <div class="announcement-layout">
        <article class="announcement-composer">
          <h3 data-announcement-composer-title><i class="bi bi-megaphone-fill"></i> <span>New Announcement</span></h3>
          <p data-announcement-composer-hint>Post updates for offices and borrowers.</p>

          <form
            method="POST"
            action="{{ route('dashboard.announcements.store') }}"
            class="announcement-form"
            data-announcement-form
            data-store-action="{{ route('dashboard.announcements.store') }}"
            data-update-action="{{ route('dashboard.announcements.update', ['announcementId' => '__ID__']) }}"
            data-default-announcer="{{ old('announcer_name', auth()->user()?->displayName() ?: (auth()->user()?->username ?? '')) }}"
          >
            @csrf
            <label for="announcement-announcer">Announced by</label>
            <input
              id="announcement-announcer"
              name="announcer_name"
              type="text"
              maxlength="180"
              value="{{ old('announcer_name', auth()->user()?->displayName() ?: (auth()->user()?->username ?? '')) }}"
              placeholder="Your name"
              required
              @disabled(!($announcementsTableReady ?? false))
            />

            <label for="announcement-title">Title</label>
            <input
              id="announcement-title"
              name="title"
              type="text"
              maxlength="180"
              value="{{ old('title') }}"
              placeholder="Example: Facility closure this Friday"
              required
              @disabled(!($announcementsTableReady ?? false))
            />

            <label for="announcement-body">Message</label>
            <textarea
              id="announcement-body"
              name="body"
              rows="5"
              maxlength="5000"
              placeholder="Write the announcement details here..."
              required
              @disabled(!($announcementsTableReady ?? false))
            >{{ old('body') }}</textarea>

            @error('announcer_name')
              <p class="announcement-error">{{ $message }}</p>
            @enderror
            @error('title')
              <p class="announcement-error">{{ $message }}</p>
            @enderror
            @error('body')
              <p class="announcement-error">{{ $message }}</p>
            @enderror

            <div class="announcement-form-actions">
              <button class="announcement-submit" type="submit" data-announcement-submit @disabled(!($announcementsTableReady ?? false))>
                <i class="bi bi-send-fill"></i> <span>Publish</span>
              </button>
              <button class="announcement-cancel" type="button" data-announcement-cancel hidden>
                Cancel
              </button>
            </div>
          </form>
        </article>

        <article class="announcement-feed">
          <div class="announcement-feed-head">
            <h3>Published</h3>
          </div>

          <div class="announcement-card-list">
            @forelse(($announcements ?? []) as $announcement)
              @php
                $announcedAt = $announcement->published_at ?? $announcement->created_at;
              @endphp
              <article class="announcement-card">
                <div class="announcement-card-top">
                  <strong>{{ $announcement->title }}</strong>
                  <div class="announcement-card-actions">
                    <button
                      class="announcement-edit"
                      type="button"
                      title="Edit announcement"
                      data-edit-announcement
                      data-id="{{ $announcement->announcement_id }}"
                      data-title="{{ $announcement->title }}"
                      data-body="{{ $announcement->body }}"
                      data-announcer="{{ $announcement->announcerLabel() }}"
                    >
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <form method="POST" action="{{ route('dashboard.announcements.destroy', $announcement->announcement_id) }}">
                      @csrf
                      @method('DELETE')
                      <button class="announcement-delete" type="submit" title="Remove announcement">
                        <i class="bi bi-trash3"></i>
                      </button>
                    </form>
                  </div>
                </div>
                <p class="announcement-card-body">{{ $announcement->body }}</p>
                <div class="announcement-card-meta">
                  <span>
                    <i class="bi bi-person-fill"></i>
                    Announced by {{ $announcement->announcerLabel() }}
                  </span>
                  <span>
                    <i class="bi bi-clock-fill"></i>
                    {{ optional($announcedAt)?->timezone('Asia/Manila')->format('M j, Y · g:i A') }}
                  </span>
                </div>
              </article>
            @empty
              <div class="announcement-empty">
                <strong>No announcements yet</strong>
                <span>Use the form to publish the first one.</span>
              </div>
            @endforelse
          </div>
        </article>
      </div>
    </section>
  </div>

  <script>
    (function initAnnouncementsModal() {
      const modal = document.getElementById('announcements-modal');
      if (!(modal instanceof HTMLElement)) {
        return;
      }

      const setOpen = (isOpen) => {
        modal.classList.toggle('is-open', isOpen);
        modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
      };

      document.querySelectorAll('[data-open-announcements]').forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          setOpen(true);
        });
      });

      document.querySelectorAll('[data-close-announcements]').forEach((node) => {
        node.addEventListener('click', (event) => {
          event.preventDefault();
          setOpen(false);
        });
      });

      const form = modal.querySelector('[data-announcement-form]');
      const announcerInput = document.getElementById('announcement-announcer');
      const titleInput = document.getElementById('announcement-title');
      const bodyInput = document.getElementById('announcement-body');
      const cancelButton = modal.querySelector('[data-announcement-cancel]');
      const submitButton = modal.querySelector('[data-announcement-submit]');
      const composerTitle = modal.querySelector('[data-announcement-composer-title] span');
      const composerHint = modal.querySelector('[data-announcement-composer-hint]');
      let methodInput = null;

      const setEditing = (announcement) => {
        if (!(form instanceof HTMLFormElement)) {
          return;
        }

        if (announcement) {
          if (!(methodInput instanceof HTMLInputElement)) {
            methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            form.appendChild(methodInput);
          }

          methodInput.value = 'PATCH';
          form.action = String(form.dataset.updateAction || '').replace('__ID__', encodeURIComponent(announcement.id));
          if (announcerInput) announcerInput.value = announcement.announcer;
          if (titleInput) titleInput.value = announcement.title;
          if (bodyInput) bodyInput.value = announcement.body;
          if (composerTitle) composerTitle.textContent = 'Edit Announcement';
          if (composerHint) composerHint.textContent = 'Update this published announcement.';
          if (submitButton) {
            submitButton.innerHTML = '<i class="bi bi-check2-circle"></i> <span>Save changes</span>';
          }
          if (cancelButton) cancelButton.hidden = false;
          titleInput?.focus();
          return;
        }

        if (methodInput instanceof HTMLInputElement) {
          methodInput.remove();
          methodInput = null;
        }

        form.action = form.dataset.storeAction || form.action;
        if (announcerInput) announcerInput.value = form.dataset.defaultAnnouncer || '';
        if (titleInput) titleInput.value = '';
        if (bodyInput) bodyInput.value = '';
        if (composerTitle) composerTitle.textContent = 'New Announcement';
        if (composerHint) composerHint.textContent = 'Post updates for offices and borrowers.';
        if (submitButton) {
          submitButton.innerHTML = '<i class="bi bi-send-fill"></i> <span>Publish</span>';
        }
        if (cancelButton) cancelButton.hidden = true;
      };

      modal.querySelectorAll('[data-edit-announcement]').forEach((button) => {
        button.addEventListener('click', () => {
          setEditing({
            id: button.getAttribute('data-id') || '',
            title: button.getAttribute('data-title') || '',
            body: button.getAttribute('data-body') || '',
            announcer: button.getAttribute('data-announcer') || '',
          });
        });
      });

      cancelButton?.addEventListener('click', () => setEditing(null));

      document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !modal.classList.contains('is-open')) {
          return;
        }

        if (cancelButton && !cancelButton.hidden) {
          event.preventDefault();
          event.stopImmediatePropagation();
          setEditing(null);
          return;
        }

        setOpen(false);
      }, true);

      modal.querySelectorAll('[data-announcement-flash]').forEach((flash) => {
        window.setTimeout(() => {
          flash.classList.add('is-hiding');
          window.setTimeout(() => flash.remove(), 400);
        }, 2800);
      });
    })();
  </script>
  <script src="/js/dashboard.js"></script>
</body>
</html>

