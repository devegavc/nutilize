<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
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
        <p class="brand-subtitle mt-2 mb-5">
          Campus Resource & Reservation Management System
        </p>
      </div>

      <form action="/dashboard/home" method="get">
        <!-- Username -->
        <div class="mb-3">
          <div class="input-group input-group-lg">
            <span class="input-group-text">
              <i class="bi bi-person"></i>
            </span>
            <input type="text" 
                   class="form-control" 
                   placeholder="Enter your username"
                   required>
          </div>
        </div>

        <!-- Password -->
        <div class="mb-4">
          <div class="input-group input-group-lg">
            <span class="input-group-text">
              <i class="bi bi-lock"></i>
            </span>
            <input type="password" 
                   class="form-control" 
                   placeholder="Enter your password"
                   required>
          </div>
        </div>

        <!-- Submit -->
        <button type="submit" class="btn btn-login w-100">
          Login
        </button>

        <p class="text-center mt-3 mb-0">
          Don't have an account? <a href="/register">Create account</a>
        </p>
      </form>
    </div>

  </div>

</body>
</html>
