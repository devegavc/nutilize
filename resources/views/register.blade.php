<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>NUtilize | Register</title>

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
    <div class="container-fluid px-0"></div>
  </header>

  <!-- Main content area -->
  <div class="page-content">

    <!-- Register card -->
    <div class="login-card mx-auto">
      <div class="brand-area text-center">
        <img src="/img/nutilize_logo.png" 
             alt="NUTilize Logo" 
             class="brand-logo">
        <p class="brand-subtitle mt-2 mb-4">
          Campus Resource & Reservation Management System
        </p>
      </div>

      <form id="register-form" novalidate>
        <!-- Full Name -->
        <div class="mb-3">
          <div class="input-group input-group-lg">
            <span class="input-group-text">
              <i class="bi bi-person-vcard"></i>
            </span>
            <input type="text" 
                   class="form-control" 
                   placeholder="Enter your full name"
                   required>
          </div>
        </div>

        <!-- Email -->
        <div class="mb-3">
          <div class="input-group input-group-lg">
            <span class="input-group-text">
              <i class="bi bi-envelope"></i>
            </span>
            <input type="email" 
                   id="email"
                   class="form-control" 
                   placeholder="Enter your email"
                   required>
          </div>
          <p class="email-warning" id="email-warning" aria-live="polite"></p>
        </div>

        <!-- Username -->
        <div class="mb-3">
          <div class="input-group input-group-lg">
            <span class="input-group-text">
              <i class="bi bi-person"></i>
            </span>
            <input type="text" 
                   class="form-control" 
                   placeholder="Choose a username"
                   required>
          </div>
        </div>

        <!-- Password -->
        <div class="mb-3">
          <div class="input-group input-group-lg">
            <span class="input-group-text">
              <i class="bi bi-lock"></i>
            </span>
            <input type="password" 
                   id="password"
                   class="form-control" 
                   placeholder="Create a password"
                   required>
            <span class="input-group-text password-valid-indicator" id="password-valid-indicator" aria-hidden="true">
              <i class="bi bi-check-circle-fill"></i>
            </span>
          </div>

          <div class="password-helper" id="password-helper" aria-live="polite">
            <div class="password-strength">
              <div class="password-strength-bar" id="password-strength-bar"></div>
            </div>
            <p class="password-strength-text mb-2" id="password-strength-text">Password strength: Weak</p>
            <ul class="password-rules mb-0">
              <li id="rule-length">At least 8 characters</li>
              <li id="rule-upper">At least 1 uppercase letter</li>
              <li id="rule-lower">At least 1 lowercase letter</li>
              <li id="rule-number">At least 1 number</li>
              <li id="rule-special">At least 1 special character</li>
            </ul>
          </div>
        </div>

        <!-- Confirm Password -->
        <div class="mb-4">
          <div class="input-group input-group-lg">
            <span class="input-group-text">
              <i class="bi bi-shield-lock"></i>
            </span>
            <input type="password" 
                   id="confirm-password"
                   class="form-control" 
                   placeholder="Confirm your password"
                   required>
            <span class="input-group-text password-valid-indicator" id="confirm-password-valid-indicator" aria-hidden="true">
              <i class="bi bi-check-circle-fill"></i>
            </span>
          </div>
          <p class="confirm-password-warning" id="confirm-password-warning" aria-live="polite"></p>
        </div>

        <!-- Submit -->
        <button type="submit" class="btn btn-login w-100">
          Register
        </button>

        <p class="text-center mt-3 mb-0">
          Already have an account? <a href="/login">Login</a>
        </p>
      </form>
    </div>

  </div>

  <script src="/js/auth.js"></script>

</body>
</html>

