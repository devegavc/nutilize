@php
  $allRequests = $finalRequests->concat($returnRequests ?? collect())->concat($rejectedRequests ?? collect())->concat($pendingRequests);
@endphp

@forelse($allRequests as $requestData)
  @php
    $reservation = $requestData['reservation'];
    $user = $reservation->user;
    $requesterName = $user->full_name ?? $user->username ?? 'User';
    $displayPhone = $user->phone_number ?? $user->contact_number ?? 'N/A';
    $cssVisibilityClass = $requestData['tab'] === 'final'
      ? 'final-only'
      : ($requestData['tab'] === 'return' ? 'return-only' : ($requestData['tab'] === 'rejected' ? 'rejected-only' : 'pending-only'));
    $isReturnedForRevision = $requestData['tab'] === 'rejected';
    $displayBadge = $requestData['decision_badge'] === 'Rejected'
      ? 'Returned for Revision'
      : $requestData['decision_badge'];
    $returnRemarks = $isReturnedForRevision
      ? (collect($reservation->approvals ?? [])
          ->filter(fn ($approval) => strtolower((string) ($approval->status ?? '')) === 'rejected')
          ->map(fn ($approval) => trim((string) ($approval->rejection_reason ?? '')))
          ->first(fn ($remark) => $remark !== '') ?? '')
      : '';
  @endphp
  <article class="request-item {{ $cssVisibilityClass }} {{ $requestData['decision_status_class'] }}" data-requester="{{ $requesterName }}">
    <div class="request-row-title">
      <strong>Current Request</strong>
      <span>#NU-{{ str_pad((string) $reservation->reservation_id, 6, '0', STR_PAD_LEFT) }}</span>
      <span class="status-dots">
        @foreach($requestData['workflow_steps'] as $step)
          <i class="bi {{ $step['icon_class'] ?? 'bi-building' }} {{ $step['dot_class'] }}" title="{{ $step['office_code'] }} - {{ $step['office_name'] }}"></i>
        @endforeach
      </span>
    </div>

    <section class="request-progress-block">
      <h3 class="request-block-title">Approval progress</h3>
      <div class="status-timeline" style="--timeline-steps: {{ max(count($requestData['workflow_steps']), 1) }};" aria-hidden="true">
        @foreach($requestData['workflow_steps'] as $step)
          <div class="status-step {{ $step['dot_class'] }}">
            <span class="status-step-node">
              <i class="bi {{ $step['icon_class'] ?? 'bi-building' }}"></i>
            </span>
            <span class="status-step-label">{{ $step['stage_label'] ?? $step['office_name'] }}</span>
          </div>
        @endforeach
      </div>
      @if($requestData['tab'] === 'pending' && !empty($requestData['current_stage_label']))
        <p class="request-current-stage">Currently at: <strong>{{ $requestData['current_stage_label'] }}</strong></p>
      @endif
    </section>

    <div class="request-main-col">
      <h3 class="request-block-title">Request details</h3>
      <p class="request-owner">{{ $requesterName }}</p>
      @if(!empty($user->email))
        <p class="request-email">{{ $user->email }}</p>
      @endif
      <p class="request-phone">{{ $displayPhone }}</p>
      <div class="request-event-row">
        <strong>Event Name</strong>
        <span>{{ $reservation->activity_name ?? 'Untitled Activity' }}</span>
      </div>
      <div class="request-meta-row">
        <span>Date: {{ optional($reservation->Start_of_activity ?? $reservation->Date_of_Activity)->format('d/m/Y') ?? 'N/A' }}</span>
        <span>Time: {{ optional($reservation->Start_of_activity ?? $reservation->Date_of_Activity)->format('g:i A') ?? 'N/A' }}</span>
      </div>
    </div>

    <div class="request-side-event"><strong>Event Name</strong> {{ $reservation->activity_name ?? 'Untitled Activity' }}</div>

    <div class="request-resource-col">
      <h3 class="request-block-title">Requested resources</h3>
      <div class="resource-grid">
        @forelse($requestData['resources'] as $resource)
          <span><i class="bi {{ $resource['icon'] }}"></i> {{ $resource['quantity'] }} x {{ $resource['label'] }}</span>
        @empty
          <span><i class="bi bi-box-seam"></i> No resources listed</span>
        @endforelse
      </div>
      <div class="request-action-stack">
        @if($requestData['tab'] === 'return')
          <button class="return-btn confirm-action-btn" type="button" data-reservation-id="{{ $reservation->reservation_id }}" data-return-action="returned"
                  data-confirm-title="Confirm Return"
                  data-confirm-message="Mark this request as returned in good condition? This action cannot be undone."
                  data-confirm-text="Return">
            Returned
          </button>
          <button class="damage-btn confirm-action-btn" type="button" data-reservation-id="{{ $reservation->reservation_id }}" data-return-action="damaged"
                  data-confirm-title="Confirm Damage"
                  data-confirm-message="Mark this request as damaged? This action cannot be undone."
                  data-confirm-text="Damage"
                  data-confirm-variant="danger">
            Damaged
          </button>
        @elseif($requestData['tab'] === 'final')
          <button class="approve-btn confirm-action-btn" type="button" data-reservation-id="{{ $reservation->reservation_id }}" data-final-action="approve"
                  data-confirm-title="Confirm Approval"
                  data-confirm-message="Are you sure you want to approve this reservation request? This action cannot be undone."
                  data-confirm-text="Approve">
            Approve
          </button>
          <button class="reject-btn confirm-action-btn" type="button" data-reservation-id="{{ $reservation->reservation_id }}" data-final-action="reject"
                  data-confirm-title="Confirm Return for Revision"
                  data-confirm-message="Are you sure you want to return this reservation request for revision? This action cannot be undone."
                  data-confirm-text="Return for Revision"
                  data-confirm-variant="danger">
            Return for Revision
          </button>
        @endif
      </div>
      <div class="request-decision" aria-live="polite">
        <p class="request-decision-name">
          {{ $requesterName }}'s request
          {{ $displayBadge === 'Pending' ? 'is pending' : ($displayBadge === 'Waiting Return' ? 'is waiting for return' : 'has been ' . strtolower($displayBadge)) }}
        </p>
        <p class="request-decision-text"></p>
        <span class="request-decision-badge">{{ $displayBadge }}</span>
      </div>
    </div>

    @if($isReturnedForRevision)
      <section class="request-remarks">
        <h3 class="request-remarks-title">Return Remarks</h3>
        <p class="request-remarks-text">
          @if($returnRemarks !== '')
            &ldquo;{{ $returnRemarks }}&rdquo;
          @else
            No remarks were recorded for this return.
          @endif
        </p>
      </section>
    @endif
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
