@php
  $authUser = auth()->user();
  $useOfficeNav = $useOfficeNav
    ?? (request()->is('dashboard/office*')
      || (isset($isPfAdmin) ? !$isPfAdmin : !($authUser?->isPhysicalFacilitiesAdmin() ?? false)));

  $currentUsername = strtolower((string) ($authUser->username ?? ''));
  $currentOfficeCode = strtolower((string) ($authUser?->office?->short_code ?? ''));
  $currentRole = strtolower((string) ($authUser->role ?? ''));
  $isIoAdmin = ($authUser && \App\Services\ItemOwnerService::isItemOwnerUser($authUser))
    || $currentUsername === 'io_admin'
    || $currentOfficeCode === 'io'
    || ($currentRole === 'admin' && $currentOfficeCode === 'io');
  $isPcAdmin = $currentRole === 'pc_admin';
  $isPfAdminUser = in_array($currentRole, ['admin', 'pf_admin'], true);
@endphp

<div id="navbar-container" data-nav-server-rendered="1">
  @if ($useOfficeNav)
    <nav class="side-nav" aria-label="Office navigation">
      <a class="nav-item" data-nav="home" href="/dashboard/office/home">
        <i class="bi bi-house-door-fill"></i>
        <span>Home</span>
      </a>

      @if ($isPcAdmin)
        <a class="nav-item" data-nav="users" data-visible-for="pc-admin" href="/dashboard/office/users">
          <i class="bi bi-people-fill"></i>
          <span>Users</span>
        </a>
      @endif

      @if ($isIoAdmin)
        <a class="nav-item" data-nav="manage-items" data-visible-for="io-admin" href="/dashboard/office/items">
          <i class="bi bi-box-seam"></i>
          <span>Manage Items</span>
        </a>

        <a class="nav-item" data-nav="manage-maintenance" data-visible-for="io-admin" href="/dashboard/office/items/maintenance">
          <i class="bi bi-wrench-adjustable-circle"></i>
          <span>Item Maintenance</span>
        </a>
      @endif

      <a class="nav-item" data-nav="history" href="/dashboard/office/history">
        <i class="bi bi-archive"></i>
        <span>History</span>
      </a>
    </nav>
  @else
    <nav class="side-nav" aria-label="Dashboard navigation">
      <a class="nav-item" data-nav="home" href="/dashboard/home">
        <i class="bi bi-house-door-fill"></i>
        <span>Home</span>
      </a>

      <a class="nav-item" data-nav="inventory" href="/dashboard/inventory">
        <i class="bi bi-box-seam"></i>
        <span>Inventory</span>
      </a>

      <div class="nav-submenu">
        <a class="nav-subitem" data-subnav="facilities" href="/dashboard/inventory/facilities">
          <i class="bi bi-building"></i>
          <span>Facilities</span>
        </a>
        <a class="nav-subitem" data-subnav="equipments" href="/dashboard/inventory/equipments">
          <i class="bi bi-tools"></i>
          <span>Equipment</span>
        </a>
        <a class="nav-subitem" data-subnav="analytics" href="/dashboard/inventory/analytics">
          <i class="bi bi-bar-chart-fill"></i>
          <span>Insights</span>
        </a>
      </div>

      <a class="nav-item" data-nav="schedule" href="/dashboard/schedule">
        <i class="bi bi-calendar3"></i>
        <span>Schedule</span>
      </a>

      <a class="nav-item" data-nav="requests" href="/dashboard/request">
        <i class="bi bi-journal-check"></i>
        <span>Requests</span>
      </a>

      @if ($isPfAdminUser)
        <a class="nav-item" data-nav="users" data-visible-for="pf-admin" href="/dashboard/users">
          <i class="bi bi-people-fill"></i>
          <span>Users</span>
        </a>
      @endif

      <a class="nav-item" data-nav="history" href="/dashboard/history">
        <i class="bi bi-clock-history"></i>
        <span>History</span>
      </a>

      <a class="nav-item" data-nav="maintenance" href="/dashboard/maintenance">
        <i class="bi bi-wrench-adjustable-circle"></i>
        <span>Maintenance</span>
      </a>
    </nav>
  @endif
</div>
