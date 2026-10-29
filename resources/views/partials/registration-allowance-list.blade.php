<div data-allowance-total="{{ $allowances->total() }}">
  <nav class="registration-allowance-filters" aria-label="Registration status">
    @foreach([
      'all' => 'All',
      'registered' => 'Registered',
      'unregistered' => 'Unregistered',
    ] as $status => $label)
      <a
        class="{{ $allowanceStatus === $status ? 'is-active' : '' }}"
        href="{{ route('dashboard.users', array_filter([
          'allowance_q' => $allowanceSearch !== '' ? $allowanceSearch : null,
          'allowance_status' => $status === 'all' ? null : $status,
        ])) }}"
      >{{ $label }} <span>{{ $allowanceCounts[$status] }}</span></a>
    @endforeach
  </nav>

  <form class="registration-allowance-search" method="GET" action="{{ route('dashboard.users') }}">
    <label class="visually-hidden" for="allowance-search">Find an email</label>
    <i class="bi bi-search" aria-hidden="true"></i>
    <input id="allowance-search" type="search" name="allowance_q" value="{{ $allowanceSearch }}" placeholder="Search the registration list" />
    @if($allowanceStatus !== 'all')
      <input type="hidden" name="allowance_status" value="{{ $allowanceStatus }}" />
    @endif
    <button class="users-reset-btn" type="submit">Search</button>
    @if($allowanceSearch !== '')
      <a class="users-reset-btn" href="{{ route('dashboard.users', $allowanceStatus === 'all' ? [] : ['allowance_status' => $allowanceStatus]) }}">Clear</a>
    @endif
  </form>

  <div class="table-wrap registration-allowance-table">
    <table class="inventory-table">
      <thead>
        <tr>
          <th>Email</th>
          <th>Role</th>
          <th>Account</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($allowances as $allowance)
          @php $hasAccount = $allowanceRegistered->contains($allowance->email); @endphp
          <tr>
            <td class="registration-allowance-email">{{ $allowance->email }}</td>
            <td>{{ \App\Services\RegistrationAllowanceService::roleLabel($allowance->role) }}</td>
            <td>
              <span class="registration-allowance-status {{ $hasAccount ? 'is-registered' : 'is-waiting' }}">
                {{ $hasAccount ? 'Registered' : 'Not registered' }}
              </span>
            </td>
            <td class="registration-allowance-action">
              @if(!$hasAccount)
                <form method="POST" action="{{ route('dashboard.registration-allowances.destroy', $allowance) }}">
                  @csrf
                  @method('DELETE')
                  @if($allowanceSearch !== '')
                    <input type="hidden" name="allowance_q" value="{{ $allowanceSearch }}" />
                  @endif
                  @if($allowances->currentPage() > 1)
                    <input type="hidden" name="allowance_page" value="{{ $allowances->currentPage() }}" />
                  @endif
                  @if($allowanceStatus !== 'all')
                    <input type="hidden" name="allowance_status" value="{{ $allowanceStatus }}" />
                  @endif
                  <button type="submit">Remove</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4">
              <div class="registration-allowance-empty">
                <i class="bi bi-inbox" aria-hidden="true"></i>
                <strong>{{ $allowanceSearch !== '' ? 'No matching email' : ($allowanceStatus === 'registered' ? 'No registered emails' : ($allowanceStatus === 'unregistered' ? 'No unregistered emails' : 'No emails yet')) }}</strong>
                <span>{{ $allowanceSearch !== '' ? 'Try another search.' : ($allowanceStatus === 'all' ? 'Upload a file or add the first address.' : 'Nothing is in this category.') }}</span>
              </div>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if($allowances->hasPages())
    <div class="registration-allowance-pages">
      @if($allowances->onFirstPage())
        <span>Previous</span>
      @else
        <a href="{{ $allowances->previousPageUrl() }}">Previous</a>
      @endif
      <span>Page {{ $allowances->currentPage() }} of {{ $allowances->lastPage() }}</span>
      @if($allowances->hasMorePages())
        <a href="{{ $allowances->nextPageUrl() }}">Next</a>
      @else
        <span>Next</span>
      @endif
    </div>
  @endif
</div>
