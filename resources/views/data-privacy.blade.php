<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
  <title>NUtilize | Data Privacy Notice</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/css/auth.css?v=privacy-1">
</head>
<body class="login-page privacy-page">

  <div class="page-content">
    <div class="login-card mx-auto">
      <div class="login-card-inner">
        <div class="brand-area text-center">
          <img src="/img/nutilize_wordmark.png?v={{ filemtime(public_path('img/nutilize_wordmark.png')) }}"
               alt="NUtilize"
               class="brand-logo">
          <p class="brand-subtitle">
            Campus Resource & Reservation Management System
          </p>
          <span class="login-heading-rule" aria-hidden="true"></span>
          <h1 class="login-heading">Data Privacy Notice</h1>
        </div>

        <div class="privacy-notice" tabindex="0">
          <p>
            NUtilize is the campus system of National University Lipa for facilities, equipment, reservations, approvals, schedules, and maintenance. This notice explains how the system handles personal data before you sign in.
          </p>

          <h2>What NUtilize collects</h2>
          <ul>
            <li>Your name, email address, and username</li>
            <li>Your role and the office or program tied to your account</li>
            <li>Reservation and request details you submit or approve</li>
            <li>Messages, feedback, and maintenance records created in the system</li>
            <li>Sign-in activity used to protect your account</li>
          </ul>

          <h2>Why it is used</h2>
          <ul>
            <li>To confirm who you are and keep your account secure</li>
            <li>To process facility, equipment, and reservation requests</li>
            <li>To route approvals, schedules, and maintenance work to the right office</li>
            <li>To send notices about your account or a request you are part of</li>
          </ul>

          <h2>Who can see it</h2>
          <p>
            Authorized NUtilize administrators and the office handling a request can see the records they need to do that work. NUtilize does not sell personal data. Information is shared outside the campus only when National University policy or the law requires it.
          </p>

          <h2>Your choices</h2>
          <p>
            Under the Data Privacy Act of 2012, you may ask to access or correct your personal data, or to object to processing where the law allows it. Contact Physical Facilities, the office that administers NUtilize, for these requests. You can leave this page and return to the home page if you do not agree.
          </p>
        </div>

        <form id="privacyForm" class="privacy-form" action="{{ route('data-privacy.accept') }}" method="POST">
          @csrf

          @if ($errors->any())
            <div class="alert alert-danger" role="alert">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <label class="privacy-agree" for="privacyAgreement">
            <input class="login-remember-input"
                   type="checkbox"
                   name="privacy_agreement"
                   value="1"
                   id="privacyAgreement"
                   @checked(old('privacy_agreement'))>
            <span>I have read the NUtilize Data Privacy Notice and I agree to the collection and use of my personal data as described above.</span>
          </label>

          <button type="submit" class="btn btn-login w-100" id="privacyContinue" disabled>
            Continue to Login
          </button>
        </form>

        <a href="{{ route('index') }}" class="privacy-back">Back to home</a>
      </div>
    </div>
  </div>

  <script>
    (function () {
      const agreement = document.getElementById('privacyAgreement');
      const continueButton = document.getElementById('privacyContinue');
      const form = document.getElementById('privacyForm');

      if (!agreement || !continueButton || !form) {
        return;
      }

      function syncContinue() {
        continueButton.disabled = !agreement.checked;
      }

      agreement.addEventListener('change', syncContinue);
      form.addEventListener('submit', function (event) {
        if (!agreement.checked) {
          event.preventDefault();
          syncContinue();
        }
      });
      syncContinue();
    })();
  </script>
</body>
</html>
