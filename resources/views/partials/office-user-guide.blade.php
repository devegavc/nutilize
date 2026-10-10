@php
  $guideUser = auth()->user();
  $guideRole = strtolower((string) ($guideUser->role ?? ''));
  $guideUsername = strtolower((string) ($guideUser->username ?? ''));
  $guideOfficeCode = strtolower((string) ($guideUser?->office?->short_code ?? ''));
  $guideIsPcAdmin = $guideRole === 'pc_admin';
  $guideIsIoAdmin = ($guideUser && \App\Services\ItemOwnerService::isItemOwnerUser($guideUser))
      || $guideUsername === 'io_admin'
      || $guideOfficeCode === 'io'
      || ($guideRole === 'admin' && $guideOfficeCode === 'io');

  $guideMenu = ['Home'];
  if ($guideIsPcAdmin) {
      $guideMenu[] = 'Users';
  }
  if ($guideIsIoAdmin) {
      $guideMenu[] = 'Manage Items';
      $guideMenu[] = 'Item Maintenance';
  }
  $guideMenu[] = 'History';

  $guideMenuCount = count($guideMenu);
  if ($guideMenuCount <= 1) {
      $guideMenuLabel = $guideMenu[0];
  } elseif ($guideMenuCount === 2) {
      $guideMenuLabel = $guideMenu[0].' and '.$guideMenu[1];
  } else {
      $guideMenuLabel = implode(', ', array_slice($guideMenu, 0, -1)).', and '.$guideMenu[$guideMenuCount - 1];
  }
@endphp

<link rel="stylesheet" href="/css/user-guide.css?v={{ filemtime(public_path('css/user-guide.css')) }}" />

<button
  class="toolbar-icon"
  type="button"
  id="office-user-guide-open"
  aria-label="User guide"
  aria-haspopup="dialog"
  aria-controls="office-user-guide"
  aria-expanded="false"
>
  <i class="bi bi-question-lg" aria-hidden="true"></i>
</button>

<div class="user-guide" id="office-user-guide" hidden>
  <button class="user-guide-backdrop" type="button" data-user-guide-close aria-label="Close user guide"></button>
  <section class="user-guide-panel" role="dialog" aria-modal="true" aria-labelledby="office-user-guide-title">
    <header class="user-guide-header">
      <button class="user-guide-back" type="button" data-user-guide-close aria-label="Close user guide">
        <i class="bi bi-chevron-left" aria-hidden="true"></i>
      </button>
      <h2 id="office-user-guide-title">User Guide</h2>
    </header>

    <div class="user-guide-body">
      <article class="user-guide-intro">
        <span class="user-guide-intro-icon" aria-hidden="true"><i class="bi bi-book"></i></span>
        <div>
          <p class="user-guide-intro-kicker">Office walkthrough</p>
          <h3>Your NUtilize guide</h3>
          <p>Review the requests that reach your office, decide when it is your turn, and keep a record of what you have already handled.</p>
        </div>
      </article>

      <p class="user-guide-kicker">How to use NUtilize</p>

      <ol class="user-guide-steps">
        <li class="user-guide-step">
          <span class="user-guide-step-icon" aria-hidden="true"><i class="bi bi-compass"></i></span>
          <div class="user-guide-step-card">
            <p class="user-guide-step-label">Step 01</p>
            <h3>Explore your office</h3>
            <p>Use the side menu to move between {{ $guideMenuLabel }}.</p>
            <span class="user-guide-place">Side menu</span>
          </div>
        </li>
        <li class="user-guide-step">
          <span class="user-guide-step-icon" aria-hidden="true"><i class="bi bi-grid"></i></span>
          <div class="user-guide-step-card">
            <p class="user-guide-step-label">Step 02</p>
            <h3>See what you can act on</h3>
            <p>Home splits the queue in two. Actionable requests are yours to decide. Pending in Queue is still with another office.</p>
            <span class="user-guide-place">Home</span>
          </div>
        </li>
        <li class="user-guide-step">
          <span class="user-guide-step-icon" aria-hidden="true"><i class="bi bi-clipboard-check"></i></span>
          <div class="user-guide-step-card">
            <p class="user-guide-step-label">Step 03</p>
            <h3>Approve or return it</h3>
            <p>Approve when the request is ready to move on. Reject it with a reason when something needs to change. A request can pass through more than one office before it is finished.</p>
            <span class="user-guide-place">Home queue</span>
          </div>
        </li>
        <li class="user-guide-step">
          <span class="user-guide-step-icon" aria-hidden="true"><i class="bi bi-clock-history"></i></span>
          <div class="user-guide-step-card">
            <p class="user-guide-step-label">Step 04</p>
            <h3>Check History</h3>
            <p>Open History to review the requests your office already approved or rejected, including what was processed today.</p>
            <span class="user-guide-place">History</span>
          </div>
        </li>
      </ol>

      <aside class="user-guide-note">
        <span class="user-guide-note-icon" aria-hidden="true"><i class="bi bi-signpost-split"></i></span>
        <p>If a request has not reached your office yet, leave it in the queue. It becomes actionable when the earlier offices are done.</p>
      </aside>
    </div>
  </section>
</div>

<script>
  (function () {
    var openButton = document.getElementById('office-user-guide-open');
    var panel = document.getElementById('office-user-guide');
    if (!openButton || !panel || openButton.getAttribute('data-bound') === '1') {
      return;
    }
    openButton.setAttribute('data-bound', '1');

    var backButton = panel.querySelector('.user-guide-back');
    var guideBody = panel.querySelector('.user-guide-body');

    function setOpen(isOpen) {
      panel.hidden = !isOpen;
      openButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      document.body.classList.toggle('user-guide-open', isOpen);
      if (isOpen && guideBody) {
        guideBody.scrollTop = 0;
      }
      if (isOpen && backButton) {
        backButton.focus();
      } else if (!isOpen) {
        openButton.focus();
      }
    }

    openButton.addEventListener('click', function () {
      setOpen(panel.hidden);
    });

    panel.addEventListener('click', function (event) {
      if (event.target.closest('[data-user-guide-close]')) {
        setOpen(false);
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !panel.hidden) {
        setOpen(false);
      }
    });
  })();
</script>
