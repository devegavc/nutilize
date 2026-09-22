<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<title>NUtilize | Login</title>

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" 
        rel="stylesheet" 
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" 
        crossorigin="anonymous">

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <!-- Custom styles -->
  <link rel="stylesheet" href="/css/auth.css">
</head>
<body>

  <!-- Top header bar -->
  <header class="top-header">
    <div class="container-fluid px-0">
      <!-- empty or can add small text/logo if needed later -->
    </div>
  </header>

  <!-- Main content area -->
  <div class="page-content">

    <!-- Login card -->
    <div class="login-card mx-auto">
      <div class="brand-area text-center">
        <img src="/img/nutilize_logo.png" 
             alt="NUTilize Logo" 
             class="brand-logo">
        <p class="brand-subtitle mt-2 mb-0">
          Campus Resource & Reservation Management System
        </p>
        <h1 class="login-heading">
          Sign in to your <span class="login-heading-admin">Admin</span> account
        </h1>
      </div>

      <form id="loginForm" action="{{ route('login.authenticate') }}" method="POST">
        @csrf

        @if (session('status'))
          <div class="alert alert-success" role="alert">
            {{ session('status') }}
          </div>
        @endif

        @if ($errors->any())
          <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <!-- Email or Username -->
        <div class="mb-3">
          <label class="visually-hidden" for="loginIdentifier">Email or username</label>
          <div class="input-group input-group-lg">
            <span class="input-group-text">
              <i class="bi bi-person" aria-hidden="true"></i>
            </span>
            <input type="text" 
                   id="loginIdentifier"
                   name="username"
                   class="form-control" 
                   placeholder="Enter your email or username"
                   value="{{ old('username') }}"
                   autocomplete="username"
                   spellcheck="false"
                   autocapitalize="none"
                   required>
          </div>
        </div>

        <!-- Password -->
        <div class="mb-3">
          <label class="visually-hidden" for="loginPassword">Password</label>
          <div class="input-group input-group-lg">
            <span class="input-group-text">
              <i class="bi bi-lock" aria-hidden="true"></i>
            </span>
            <input type="password" 
                   id="loginPassword"
                   name="password"
                   class="form-control" 
                   placeholder="Enter your password"
                   autocomplete="current-password"
                   required>
            <button type="button"
                    class="btn btn-password-toggle"
                    id="toggleLoginPassword"
                    aria-label="Show password"
                    aria-controls="loginPassword"
                    aria-pressed="false">
              <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div class="login-options">
          <div class="form-check login-remember">
            <input class="form-check-input"
                   type="checkbox"
                   id="rememberMe">
            <label class="form-check-label" for="rememberMe">
              Remember me
            </label>
          </div>

          <button type="button"
                  class="forgot-password-link"
                  id="forgotPasswordBtn"
                  aria-expanded="false"
                  aria-controls="forgot-password-help">
            Forgot Password?
          </button>
        </div>

        <div id="forgot-password-help"
             class="forgot-password-help"
             role="region"
             aria-live="polite"
             aria-labelledby="forgotPasswordBtn"
             tabindex="-1"
             hidden>
          Password reset is handled by Physical Facilities. Please contact an administrator for assistance.
        </div>

        <!-- Submit -->
        <button type="submit" class="btn btn-login w-100" id="loginSubmitBtn" aria-label="Sign In">
          <span class="btn-login-content">
            <span class="btn-login-idle">Sign In</span>
            <span class="btn-login-busy" aria-hidden="true">
              <span class="login-spinner"></span>
              Signing in...
            </span>
          </span>
        </button>

      </form>
    </div>

  </div>

  <script>
    (function () {
      const REMEMBER_FLAG_KEY = 'nutilize.adminLogin.rememberMe';
      const REMEMBER_IDENTIFIER_KEY = 'nutilize.adminLogin.identifier';

      const form = document.getElementById('loginForm');
      const identifierInput = document.getElementById('loginIdentifier');
      const passwordInput = document.getElementById('loginPassword');
      const toggleButton = document.getElementById('toggleLoginPassword');
      const rememberMe = document.getElementById('rememberMe');
      const forgotPasswordBtn = document.getElementById('forgotPasswordBtn');
      const forgotPasswordHelp = document.getElementById('forgot-password-help');
      const submitButton = document.getElementById('loginSubmitBtn');
      let isSubmitting = false;

      function readStoredIdentifier() {
        try {
          return window.localStorage.getItem(REMEMBER_IDENTIFIER_KEY) || '';
        } catch (error) {
          return '';
        }
      }

      function readRememberPreference() {
        try {
          return window.localStorage.getItem(REMEMBER_FLAG_KEY) === '1';
        } catch (error) {
          return false;
        }
      }

      function persistRememberedIdentifier(identifier) {
        try {
          window.localStorage.setItem(REMEMBER_FLAG_KEY, '1');
          window.localStorage.setItem(REMEMBER_IDENTIFIER_KEY, identifier);
        } catch (error) {
          // Ignore storage failures; session auth is unchanged.
        }
      }

      function clearRememberedIdentifier() {
        try {
          window.localStorage.removeItem(REMEMBER_FLAG_KEY);
          window.localStorage.removeItem(REMEMBER_IDENTIFIER_KEY);
        } catch (error) {
          // Ignore storage failures; session auth is unchanged.
        }
      }

      function setLoginLoading(isLoading) {
        if (!submitButton) {
          return;
        }

        submitButton.disabled = isLoading;
        submitButton.classList.toggle('is-loading', isLoading);
        submitButton.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        submitButton.setAttribute('aria-label', isLoading ? 'Signing in' : 'Sign In');
      }

      if (rememberMe && identifierInput) {
        const shouldRemember = readRememberPreference();
        rememberMe.checked = shouldRemember;

        if (shouldRemember && !identifierInput.value) {
          identifierInput.value = readStoredIdentifier();
        }
      }

      if (toggleButton && passwordInput) {
        const toggleIcon = toggleButton.querySelector('i');

        toggleButton.addEventListener('click', function () {
          const isPassword = passwordInput.type === 'password';

          passwordInput.type = isPassword ? 'text' : 'password';
          toggleButton.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
          toggleButton.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');

          if (toggleIcon) {
            toggleIcon.classList.toggle('bi-eye', !isPassword);
            toggleIcon.classList.toggle('bi-eye-slash', isPassword);
          }
        });
      }

      if (forgotPasswordBtn && forgotPasswordHelp) {
        forgotPasswordBtn.addEventListener('click', function () {
          const isOpen = !forgotPasswordHelp.hasAttribute('hidden');

          if (isOpen) {
            forgotPasswordHelp.setAttribute('hidden', '');
            forgotPasswordBtn.setAttribute('aria-expanded', 'false');
            forgotPasswordBtn.focus();
            return;
          }

          forgotPasswordHelp.removeAttribute('hidden');
          forgotPasswordBtn.setAttribute('aria-expanded', 'true');
          forgotPasswordHelp.focus();
        });
      }

      if (form && submitButton) {
        form.addEventListener('submit', function (event) {
          if (isSubmitting) {
            event.preventDefault();
            return;
          }

          if (rememberMe && identifierInput) {
            const identifier = identifierInput.value.trim();

            if (rememberMe.checked && identifier) {
              persistRememberedIdentifier(identifier);
            } else {
              clearRememberedIdentifier();
            }
          }

          isSubmitting = true;
          setLoginLoading(true);
        });
      }

      window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
          isSubmitting = false;
          setLoginLoading(false);
        }
      });
    })();
  </script>

</body>
</html>
