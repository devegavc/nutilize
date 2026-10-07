<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Confirm your new NUtilize email</title>
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
                A request was made to change the email address on your NUtilize account.
              </p>
              <p style="margin:0 0 16px;font-size:16px;line-height:1.5;">
                Enter this code in your profile to confirm the new address. This code expires in {{ $expiresMinutes }} minutes.
              </p>
              <p style="margin:0 0 24px;font-size:32px;letter-spacing:8px;font-weight:700;color:#38479c;">
                {{ $code }}
              </p>
              <p style="margin:0;font-size:14px;line-height:1.5;">
                Do not share this code. Anyone who has it can confirm an email change for this account. If you did not ask for this change, you can ignore this email.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
