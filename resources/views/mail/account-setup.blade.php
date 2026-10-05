<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Set up your NUtilize password</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6fb;font-family:Arial,sans-serif;color:#1a2347;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
    <tr>
      <td align="center">
        <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:16px;padding:32px;">
          <tr>
            <td>
              <p style="margin:0 0 16px;font-size:16px;">Hello {{ $recipientName }},</p>
              <p style="margin:0 0 16px;font-size:16px;line-height:1.5;">
                Your NUtilize account has been created. Your username is <strong>{{ $username }}</strong>.
              </p>
              <p style="margin:0 0 16px;font-size:16px;line-height:1.5;">
                Create your own password using the link below. This link expires in {{ $expiresHours }} hours.
              </p>
              <p style="margin:0 0 24px;">
                <a href="{{ $setupUrl }}" style="display:inline-block;background:#38479c;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:700;">
                  Create your password
                </a>
              </p>
              <p style="margin:0 0 16px;font-size:14px;line-height:1.5;word-break:break-all;">
                If the button does not open, copy this address into your browser:<br />
                {{ $setupUrl }}
              </p>
              <p style="margin:0;font-size:14px;line-height:1.5;">
                Do not share this setup link. Anyone who opens it can set the password for this account.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
