@php
  use App\Services\ItemOwnerService;
  use App\Services\UserAccountStatusService;

  $showsAccountStatus = UserAccountStatusService::isStatusManaged($user);
  $userStatus = $showsAccountStatus
      ? (UserAccountStatusService::isActive($user) ? 'active' : 'inactive')
      : 'n/a';
  $userOffice = $resolveUserOffice($user);
  $userName = $resolveUserName($user);
  $roleKey = ItemOwnerService::isItemOwnerUser($user) ? 'item_owner' : strtolower((string) $user->role);
  $statusDuration = $showsAccountStatus ? UserAccountStatusService::statusDurationLabel($user) : '';
  $rowClass = $rowClass ?? 'admin-row';
  $roleBadgeClass = $roleBadgeClass ?? 'admin-badge';
  $roleLabel = $roleLabel ?? strtoupper((string) $user->role);
  $deleteConfirm = $deleteConfirm ?? 'Delete this user?';
@endphp
<tr
  class="{{ $rowClass }}"
  data-user-id="{{ $user->user_id }}"
  data-user-username="{{ $user->username }}"
  data-user-full-name="{{ $user->full_name }}"
  data-user-name="{{ $userName }}"
  data-user-email="{{ $user->email }}"
  data-user-office-name="{{ strtolower((string) $userOffice) }}"
  data-user-role="{{ $roleKey }}"
  data-user-status="{{ $userStatus }}"
  data-user-status-managed="{{ $showsAccountStatus ? '1' : '0' }}"
  data-user-created-at="{{ $user->created_at ? $user->created_at->timestamp : 0 }}"
  data-user-office-id="{{ $user->office_id }}"
>
  <td><span class="user-cell-text" title="{{ $user->username }}">{{ $user->username }}</span></td>
  <td><span class="user-cell-text" title="{{ $userName }}">{{ $userName }}</span></td>
  <td><span class="user-cell-text" title="{{ $user->email }}">{{ $user->email }}</span></td>
  <td><span class="role-badge {{ $roleBadgeClass }}">{{ $roleLabel }}</span></td>
  <td><span class="user-cell-text" title="{{ $userOffice }}">{{ $userOffice }}</span></td>
  <td>
    @if($showsAccountStatus)
      <div class="user-status-cell">
        <span class="status-badge {{ $userStatus === 'active' ? 'is-active' : 'is-inactive' }}">{{ ucfirst($userStatus) }}</span>
        <span class="user-status-duration" title="{{ $statusDuration }}">{{ $statusDuration }}</span>
      </div>
    @else
      <span class="user-status-na">—</span>
    @endif
  </td>
  <td class="table-actions-cell">
    <button class="table-edit-btn user-edit-btn" type="button">Edit</button>
    @if(auth()->user()->user_id !== $user->user_id)
      @if($showsAccountStatus)
        <form method="POST" action="{{ route('dashboard.users.toggle-status', ['userId' => $user->user_id]) }}" class="inline-action-form">
          @csrf
          @method('PATCH')
          <button
            type="submit"
            class="table-status-btn {{ $userStatus === 'active' ? 'is-deactivate' : 'is-activate' }}"
            title="{{ $userStatus === 'active' ? 'Mark inactive' : 'Mark active' }}"
          >
            {{ $userStatus === 'active' ? 'Deactivate' : 'Activate' }}
          </button>
        </form>
      @endif
      <form method="POST" action="{{ route('dashboard.users.destroy', ['userId' => $user->user_id]) }}" class="inline-action-form" onsubmit="return confirm(@json($deleteConfirm));">
        @csrf
        @method('DELETE')
        <button type="submit" class="table-delete-btn">Delete</button>
      </form>
    @endif
  </td>
</tr>
