@php
  $allRequests = $finalRequests->concat($returnRequests ?? collect())->concat($pendingRequests);
@endphp

@forelse($allRequests as $requestData)
  @php
    $reservation = $requestData['reservation'];
    $user = $reservation->user;
    $requesterName = $user->full_name ?? $user->username ?? 'User';
    $displayPhone = $user->phone_number ?? $user->contact_number ?? 'N/A';
    $cssVisibilityClass = $requestData['tab'] === 'final'
      ? 'final-only'
      : ($requestData['tab'] === 'return' ? 'return-only' : 'pending-only');
  @endphp
  <article class="request-item {{ $cssVisibilityClass }} {{ $requestData['decision_status_class'] }}" data-requester="{{ $requesterName }}">
    <div class="request-main-col">
      <div class="request-row-title">
        <strong>Current Request</strong>
        <span>#NU-{{ str_pad((string) $reservation->reservation_id, 6, '0', STR_PAD_LEFT) }}</span>
        <span class="status-dots">
          @foreach($requestData['workflow_steps'] as $step)
            <i class="bi {{ $step['icon_class'] ?? 'bi-building' }} {{ $step['dot_class'] }}" title="{{ $step['office_code'] }} - {{ $step['office_name'] }}"></i>
          @endforeach
        </span>
      </div>
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
      <p class="request-owner">{{ $requesterName }}</p>
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
      <h3>Requested resources</h3>
      <div class="resource-grid">
        @forelse($requestData['resources'] as $resource)
          <span><i class="bi {{ $resource['icon'] }}"></i> {{ $resource['quantity'] }} x {{ $resource['label'] }}</span>
        @empty
          <span><i class="bi bi-box-seam"></i> No resources listed</span>
        @endforelse
      </div>
      <div class="request-action-stack">
        @if($requestData['tab'] === 'return')
          <button class="return-btn" type="button" data-reservation-id="{{ $reservation->reservation_id }}" data-return-action="returned">Returned</button>
          <button class="damage-btn" type="button" data-reservation-id="{{ $reservation->reservation_id }}" data-return-action="damaged">Damaged</button>
        @else
          <button class="approve-btn" type="button" data-reservation-id="{{ $reservation->reservation_id }}" data-final-action="approve">Approve</button>
          <button class="reject-btn" type="button" data-reservation-id="{{ $reservation->reservation_id }}" data-final-action="reject">Reject</button>
        @endif
      </div>
      <div class="request-decision" aria-live="polite">
        <p class="request-decision-name">
          {{ $requesterName }}'s request
          {{ $requestData['decision_badge'] === 'Pending' ? 'is pending' : ($requestData['decision_badge'] === 'Waiting Return' ? 'is waiting for return' : 'has been ' . strtolower($requestData['decision_badge'])) }}
        </p>
        <p class="request-decision-text"></p>
        <span class="request-decision-badge">{{ $requestData['decision_badge'] }}</span>
      </div>
    </div>
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

@if(isset($requestPagination) && $requestPagination->hasPages())
  <div style="margin-top: 1rem; display: flex; justify-content: center;">
    {{ $requestPagination->links() }}
  </div>
@endif
