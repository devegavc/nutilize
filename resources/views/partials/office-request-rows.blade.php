@forelse($requests as $request)
  @php
    $status = strtolower(trim((string) ($request->status ?? 'pending')));
    $reservation = $request->reservation;
    $eventDate = optional($reservation?->Start_of_activity ?? $reservation?->Date_of_Activity)->format('M d, Y') ?: 'N/A';
    $showWaitingQueueContext = (bool) ($showWaitingQueueContext ?? false);
    $isActionable = !isset($actionableReservationIds)
      || in_array((int) $request->reservation_id, array_map('intval', $actionableReservationIds), true);
    $isOpenStep = is_null($request->approved_at) && !in_array($status, ['approved', 'rejected'], true);
    $waitingOnLabel = $waitingOnByReservation[(int) $request->reservation_id] ?? null;

    if ($isOpenStep && $isActionable) {
      $badgeClass = 'pending';
      $badgeText = 'Ready for review';
    } elseif ($isOpenStep && $showWaitingQueueContext) {
      $badgeClass = 'queue-waiting';
      $badgeText = 'In progress';
    } elseif ($isOpenStep) {
      $badgeClass = 'pending';
      $badgeText = 'Pending';
    } elseif ($status === 'approved') {
      $badgeClass = 'solved';
      $badgeText = 'Approved';
    } elseif ($status === 'rejected') {
      $badgeClass = 'rejected';
      $badgeText = 'Rejected';
    } else {
      $badgeClass = 'pending';
      $badgeText = 'Pending';
    }
  @endphp
  <tr data-request-date="{{ $eventDate !== 'N/A' ? $eventDate : '' }}">
    <td>#{{ $request->reservation_id }}</td>
    <td class="office-queue-requester">{{ $reservation?->user?->full_name ?? $reservation?->user?->username ?? 'Unknown' }}</td>
    <td class="office-queue-activity">{{ $reservation?->activity_name ?? 'N/A' }}</td>
    <td>{{ $eventDate }}</td>
    <td>{{ optional($reservation?->created_at)->format('M d, Y h:i A') }}</td>
    <td><span class="badge {{ $badgeClass }}">{{ $badgeText }}</span></td>
    <td class="office-queue-actions-cell">
      @if($isOpenStep)
        @if($isActionable)
          <div class="office-queue-action-group">
            <button
              type="button"
              class="office-queue-action-btn office-queue-approve"
              data-approval-id="{{ $request->approval_id }}"
              data-action="approve"
            >Approve</button>
            <button
              type="button"
              class="office-queue-action-btn office-queue-reject"
              data-approval-id="{{ $request->approval_id }}"
              data-action="reject"
            >Reject</button>
          </div>
        @else
          @if($showWaitingQueueContext)
            <span
              class="office-queue-waiting-tag"
              title="{{ $waitingOnLabel ? 'Waiting for ' . $waitingOnLabel . ' to approve first.' : 'Waiting for a previous office to approve first.' }}"
            >
              @if($waitingOnLabel)
                At {{ $waitingOnLabel }}
              @else
                Awaiting prior approval
              @endif
            </span>
          @else
            <span class="office-queue-empty-action">-</span>
          @endif
        @endif
      @else
        <span class="office-queue-empty-action">-</span>
      @endif
    </td>
  </tr>
@empty
  <tr>
    <td colspan="7">{{ ($showWaitingQueueContext ?? false) ? 'No requests in your program queue yet.' : 'No requests waiting for your approval right now.' }}</td>
  </tr>
@endforelse
