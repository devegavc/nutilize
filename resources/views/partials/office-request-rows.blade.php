@forelse($requests as $request)
  @php
    $status = strtolower(trim((string) ($request->status ?? 'pending')));
    $reservation = $request->reservation;
    $eventDate = optional($reservation?->Start_of_activity ?? $reservation?->Date_of_Activity)->format('M d, Y') ?: 'N/A';
    $resourceSummary = $reservation?->resource_summary;
    $roomCount = (int) ($resourceSummary['room_count'] ?? 0);
    $itemCount = (int) ($resourceSummary['item_count'] ?? 0);
    $showResourceSummary = $roomCount > 1 || $itemCount > 1 || ($roomCount > 0 && $itemCount > 0);
    if (!empty($resourceSummary['date_label']) && $resourceSummary['date_label'] !== 'N/A') {
      $eventDate = $resourceSummary['date_label'];
    }
    $showWaitingQueueContext = (bool) ($showWaitingQueueContext ?? false);
    $isActionable = !isset($actionableReservationIds)
      || in_array((int) $request->reservation_id, array_map('intval', $actionableReservationIds), true);
    $isOpenStep = is_null($request->approved_at) && !in_array($status, ['approved', 'rejected'], true);
    $waitingOnLabel = $waitingOnByReservation[(int) $request->reservation_id] ?? null;
    $hasOutsideParticipants = $reservation?->hasOutsideParticipants() ?? false;

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
  <tr class="{{ $hasOutsideParticipants ? 'office-queue-has-outsider' : '' }}" data-request-date="{{ $eventDate !== 'N/A' ? $eventDate : '' }}" data-outside-participants="{{ $hasOutsideParticipants ? '1' : '0' }}">
    <td>#{{ $request->reservation_id }}</td>
    <td class="office-queue-requester">
      <span class="office-queue-requester-name">{{ $reservation?->user?->full_name ?? $reservation?->user?->username ?? 'Unknown' }}</span>
      <span class="office-queue-program">{{ $reservation?->user?->academicProgram?->name ?: 'No program' }}</span>
    </td>
    <td class="office-queue-activity">
      <span class="office-queue-activity-name">{{ $reservation?->activity_name ?? 'N/A' }}</span>
    </td>
    <td>
      <span class="office-queue-event-date">{{ $eventDate }}</span>
      @if($showResourceSummary)
        <span class="office-queue-resource-summary">
          @if($roomCount > 0)Rooms: {{ $roomCount }}@endif
          @if($roomCount > 0 && $itemCount > 0) · @endif
          @if($itemCount > 0)Equipment: {{ $itemCount }}@endif
        </span>
      @endif
    </td>
    <td>{{ optional($reservation?->created_at)->format('M d, Y h:i A') }}</td>
    <td>
      <div class="office-queue-status-stack">
        <span class="badge {{ $badgeClass }}">{{ $badgeText }}</span>
        @if(!empty($request->dean_queue_label))
          <span class="badge pending" title="{{ $request->dean_queue_label }}">{{ $request->dean_queue_label }}</span>
        @endif
        @if($hasOutsideParticipants)
          <span class="badge outsider office-queue-outsider-badge" title="This event includes outside participants">Outsider involved</span>
        @endif
      </div>
    </td>
    <td class="office-queue-actions-cell">
      <div class="office-queue-action-group">
        <button
          type="button"
          class="office-queue-action-btn office-queue-view"
          data-reservation-id="{{ (int) $request->reservation_id }}"
          data-approval-id="{{ (int) $request->approval_id }}"
          data-can-act="{{ ($isOpenStep && $isActionable) ? '1' : '0' }}"
        >Details</button>
        @if($isOpenStep)
          @if($isActionable)
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
            @endif
          @endif
        @endif
      </div>
    </td>
  </tr>
@empty
  <tr>
    <td colspan="7">{{ ($showWaitingQueueContext ?? false) ? 'No requests in your program queue yet.' : 'No requests waiting for your approval right now.' }}</td>
  </tr>
@endforelse
