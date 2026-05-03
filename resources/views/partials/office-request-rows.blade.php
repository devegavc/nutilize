@forelse($requests as $request)
  @php
    $status = strtolower((string) ($request->status ?? 'pending'));
    $badgeClass = $status === 'approved' ? 'solved' : ($status === 'rejected' ? 'rejected' : 'pending');
    $badgeText = $status === 'approved' ? 'Approved' : ($status === 'rejected' ? 'Rejected' : 'Pending');
    $reservation = $request->reservation;
    $eventDate = optional($reservation?->Start_of_activity ?? $reservation?->Date_of_Activity)->format('M d, Y') ?: 'N/A';
  @endphp
  <tr data-request-date="{{ $eventDate !== 'N/A' ? $eventDate : '' }}">
    <td>#{{ $request->reservation_id }}</td>
    <td>{{ $reservation?->user?->full_name ?? $reservation?->user?->username ?? 'Unknown' }}</td>
    <td>{{ $reservation?->activity_name ?? 'N/A' }}</td>
    <td>{{ $eventDate }}</td>
    <td>{{ optional($reservation?->created_at)->format('M d, Y h:i A') }}</td>
    <td><span class="badge {{ $badgeClass }}">{{ $badgeText }}</span></td>
    <td>
      @if(is_null($request->approved_at) && $status === 'pending')
        <div style="display:flex; gap:8px; justify-content:center;">
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
        <span style="color:#6a728f;">-</span>
      @endif
    </td>
  </tr>
@empty
  <tr>
    <td colspan="7">No actionable requests found for your office.</td>
  </tr>
@endforelse
