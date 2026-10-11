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
  <link rel="stylesheet" href="/css/auth.css?v=20261012">
</head>
<body class="login-page privacy-page">

  <div class="page-content">
    <div class="login-card mx-auto">
      <div class="login-card-inner">
        <div class="brand-area text-center">
          <img src="/img/nutilize_wordmark.png?v={{ filemtime(public_path('img/nutilize_wordmark.png')) }}"
               alt="NUtilize"
               class="brand-logo"
               width="168"
               height="56">
          <p class="brand-subtitle">
            Campus Resource & Reservation Management System
          </p>
          <span class="login-heading-rule" aria-hidden="true"></span>
          <h1 class="login-heading">Data Privacy Notice</h1>
          <p class="privacy-act">Republic Act No. 10173</p>
        </div>

        <div class="privacy-notice" tabindex="0">
          <p>
            We value your privacy and are committed to protecting your personal information. This notice explains how NUtilize collects, uses, and safeguards your data. By creating an account, you acknowledge that your information will be handled for the purposes described here, in line with the Data Privacy Act of 2012 (Republic Act No. 10173).
          </p>
          <p>
            This notice applies to students and other NU Lipa users of NUtilize, the room and item reservation system. It describes what this app collects, why it is processed, how it is protected, who can see it, and your rights under RA 10173.
          </p>

          <h2>Personal Data We Collect</h2>
          <p>
            When you register and use NUtilize, we collect your name, NU email address, contact number, role, and academic program. We also keep the password you set, the email verification used to create your account, and the reservation details you submit, including the room or item, date and time, purpose, and the approval or rejection history of that request.
          </p>
          <p>
            NUtilize does not collect grades, enrollment records, employment or payroll records, health information, or government ID numbers.
          </p>

          <h2>Why We Process Your Personal Data</h2>
          <p>
            Your information is used only for this reservation service: to create and secure your account, to confirm that registration uses an NU Lipa email, to receive and route requests to the offices assigned to approve them, to show you the status of those requests, and to keep rooms and items from being double-booked.
          </p>

          <h2>How NUtilize Uses Your Data</h2>
          <p>
            You enter your details on the registration and reservation screens. NUtilize uses them to create your account, limit what you can see after you sign in, process room and item requests, update their status, and notify you when a request is approved, rejected, or still waiting on an office.
          </p>

          <h2>Sharing and Disclosure</h2>
          <p>
            NUtilize does not sell your personal data. Your account details and reservation requests are available to you, and a request is shared with the offices assigned to review and approve it. It is not shared for marketing or for purposes outside this reservation service.
          </p>

          <h2>Retention</h2>
          <p>
            Account and reservation records are kept for as long as they are needed to operate NUtilize and to keep a record of requests and their results. These records are not official academic credentials, such as a transcript of records.
          </p>

          <h2>Security Measures</h2>
          <p>
            Access is limited to your account and to the offices assigned to a request. Your password is used only to sign in. Approval history stays with the reservation so the status of a request can be reviewed.
          </p>

          <h2>Your Rights</h2>
          <p>
            Under RA 10173, you have the right to be informed, to access your personal data, to object, to correct inaccurate information, to suspend or withdraw processing where the law allows, to request erasure or blocking, and to file a complaint with the National Privacy Commission. Checking the box means you have read and understood this notice.
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
            <span>I have read and understood this Data Privacy Notice.</span>
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
