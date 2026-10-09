<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
  <title>NUtilize | {{ $state === 'complete' ? 'Password Created' : 'Create Your Password' }}</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/css/auth.css?v=login-7">
  <link rel="stylesheet" href="/css/account-setup.css?v=5">
</head>
<body class="login-page account-setup-page">
  <header class="top-header"></header>
  <div class="page-content">
    <div class="login-card mx-auto">
      <div class="login-card-inner">
        <div class="brand-area text-center">
          <img src="/img/nutilize_wordmark.png?v={{ filemtime(public_path('img/nutilize_wordmark.png')) }}" alt="NUtilize" class="brand-logo">
          <p class="brand-subtitle">Campus Resource &amp; Reservation Management System</p>
          <span class="login-heading-rule" aria-hidden="true"></span>
          @if ($state === 'complete')
            <h1 class="login-heading">Password created</h1>
          @else
            <h1 class="login-heading">Create Your Password</h1>
          @endif
        </div>

        @if ($state === 'complete')
          <p class="setup-copy">Your password has been created successfully.</p>
          <p class="setup-copy">You can now log in to your NUtilize account.</p>
          <a class="btn btn-login w-100" href="{{ route('login') }}">Go to Login</a>
        @elseif ($state === 'invalid')
          <div class="alert alert-danger" role="alert">
            This password setup link is invalid.
          </div>
          <a class="btn btn-login w-100" href="{{ route('login') }}">Go to Login</a>
        @elseif ($state === 'expired')
          <div class="alert alert-danger" role="alert">
            <p class="mb-2">This password setup link has expired.</p>
            <p class="mb-0">Please contact your administrator, Program Chair, or Physical Facilities office for a new setup link.</p>
          </div>
          <a class="btn btn-login w-100" href="{{ route('login') }}">Go to Login</a>
        @elseif ($state === 'used')
          <div class="alert alert-danger" role="alert">
            This password setup link has already been used.
          </div>
          <a class="btn btn-login w-100" href="{{ route('login') }}">Go to Login</a>
        @else
          <p class="setup-welcome">Welcome to NUtilize!</p>
          <p class="setup-copy">Your account has been created. Please create a password to activate your account.</p>

          <div class="setup-username">
            <span class="setup-label" id="setup-username-label">Username</span>
            <p class="setup-username-value" id="setup-username" aria-readonly="true">{{ $username }}</p>
          </div>

          @if ($errors->any())
            <div class="alert alert-danger" id="setup-errors" role="alert">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form id="setup-form" method="POST" action="{{ route('account.setup.store', ['token' => $token]) }}">
            @csrf
            <div class="login-field">
              <label class="setup-label" for="setup-password">New Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
                <input type="password"
                       id="setup-password"
                       name="password"
                       class="form-control @if ($errors->has('password')) is-invalid @endif"
                       autocomplete="new-password"
                       spellcheck="false"
                       required
                       aria-required="true"
                       aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                       aria-describedby="setup-password-rules{{ $errors->any() ? ' setup-errors' : '' }}">
                <button type="button" class="btn btn-password-toggle" data-target="setup-password" aria-label="Show password" aria-pressed="false" aria-controls="setup-password">
                  <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
              </div>
              <div class="password-helper show" id="setup-password-rules" aria-live="polite">
                <div class="password-strength" aria-hidden="true">
                  <div class="password-strength-bar" id="setup-strength-bar"></div>
                </div>
                <p class="password-strength-text" id="setup-strength-text">Password strength: Enter a password</p>
                <ul class="password-rules mb-0">
                  <li id="setup-rule-length">At least 8 characters</li>
                  <li id="setup-rule-upper">One uppercase letter</li>
                  <li id="setup-rule-lower">One lowercase letter</li>
                  <li id="setup-rule-number">One number</li>
                  <li id="setup-rule-special">One symbol</li>
                </ul>
              </div>
            </div>

            <div class="login-field">
              <label class="setup-label" for="setup-password-confirmation">Confirm Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
                <input type="password"
                       id="setup-password-confirmation"
                       name="password_confirmation"
                       class="form-control @if ($errors->has('password')) is-invalid @endif"
                       autocomplete="new-password"
                       spellcheck="false"
                       required
                       aria-required="true"
                       aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                       aria-describedby="setup-confirm-hint{{ $errors->any() ? ' setup-errors' : '' }}">
                <button type="button" class="btn btn-password-toggle" data-target="setup-password-confirmation" aria-label="Show password" aria-pressed="false" aria-controls="setup-password-confirmation">
                  <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
              </div>
              <p class="setup-confirm-hint" id="setup-confirm-hint">Re-enter the same password.</p>
            </div>

            <button type="submit" class="btn btn-login w-100" id="setup-submit">
              <span class="btn-login-content">
                <span class="login-spinner" aria-hidden="true"></span>
                <span class="btn-login-text">Create Password</span>
              </span>
            </button>
          </form>
        @endif
      </div>
    </div>
  </div>
  @if ($state === 'form')
    <script>
      (function () {
        const form = document.getElementById('setup-form');
        const passwordInput = document.getElementById('setup-password');
        const confirmInput = document.getElementById('setup-password-confirmation');
        const submitButton = document.getElementById('setup-submit');
        const submitText = submitButton.querySelector('.btn-login-text');
        const strengthBar = document.getElementById('setup-strength-bar');
        const strengthText = document.getElementById('setup-strength-text');
        const rules = [
          { element: document.getElementById('setup-rule-length'), test: (value) => value.length >= 8 },
          { element: document.getElementById('setup-rule-upper'), test: (value) => /[A-Z]/.test(value) },
          { element: document.getElementById('setup-rule-lower'), test: (value) => /[a-z]/.test(value) },
          { element: document.getElementById('setup-rule-number'), test: (value) => /\d/.test(value) },
          { element: document.getElementById('setup-rule-special'), test: (value) => /[^A-Za-z0-9]/.test(value) }
        ];

        function updateStrength() {
          const value = passwordInput.value;
          const passedCount = rules.reduce((count, rule) => {
            const passed = value.length > 0 && rule.test(value);
            rule.element.classList.toggle('passed', passed);
            return passed ? count + 1 : count;
          }, 0);
          const levels = ['weak', 'weak', 'fair', 'good', 'strong', 'strong'];
          const labels = {
            weak: 'Password strength: Weak',
            fair: 'Password strength: Fair',
            good: 'Password strength: Good',
            strong: 'Password strength: Strong'
          };
          if (value.length === 0) {
            strengthBar.className = 'password-strength-bar';
            strengthBar.style.width = '0';
            strengthText.textContent = 'Password strength: Enter a password';
            return;
          }
          const level = levels[passedCount];
          strengthBar.className = 'password-strength-bar ' + level;
          strengthBar.style.width = ((passedCount / 5) * 100) + '%';
          strengthText.textContent = labels[level];
        }

        document.querySelectorAll('.btn-password-toggle').forEach((button) => {
          button.addEventListener('click', () => {
            const input = document.getElementById(button.getAttribute('data-target'));
            const icon = button.querySelector('i');
            if (!input || !icon) {
              return;
            }
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            icon.classList.toggle('bi-eye', !show);
            icon.classList.toggle('bi-eye-slash', show);
          });
        });

        passwordInput.addEventListener('input', updateStrength);
        confirmInput.addEventListener('input', () => {
          const mismatch = confirmInput.value.length > 0 && confirmInput.value !== passwordInput.value;
          confirmInput.setAttribute('aria-invalid', mismatch ? 'true' : 'false');
        });

        form.addEventListener('submit', (event) => {
          if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
          }
          form.dataset.submitting = '1';
          submitButton.disabled = true;
          submitButton.classList.add('is-loading');
          submitButton.setAttribute('aria-busy', 'true');
          submitText.textContent = 'Creating password...';
        });

        updateStrength();
      })();
    </script>
  @endif
</body>
</html>
