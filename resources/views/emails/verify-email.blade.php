<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify your email</title>
</head>
<body style="margin:0; padding:0; background:#F4F8F6;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F8F6;">
  <tr>
    <td align="center" style="padding:32px 16px;">

      <table role="presentation" width="420" cellpadding="0" cellspacing="0" style="max-width:420px; width:100%; background:#FFFFFF; border:1px solid #E1E9E5; border-radius:12px; overflow:hidden; font-family:Arial, Helvetica, sans-serif;">

        <tr>
          <td style="height:5px; line-height:5px; font-size:0; background:#063B32;">&nbsp;</td>
        </tr>

        <tr>
          <td style="padding:40px 32px 28px; text-align:center;">

            <table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:0 auto 20px;">
              <tr>
                <td width="52" height="52" align="center" valign="middle" style="width:52px; height:52px; background:#E8F6F2; border-radius:50%; font-size:22px; color:#087F68; font-family:Arial, sans-serif;">&#9993;</td>
              </tr>
            </table>

            <h1 style="font-family:Arial, Helvetica, sans-serif; font-size:22px; font-weight:bold; color:#063B32; margin:0 0 10px;">Confirm your email</h1>

            <p style="font-size:14px; line-height:1.6; color:#6B7772; margin:0 0 24px; font-family:Arial, Helvetica, sans-serif;">
              We sent a verification token to
              <a href="mailto:you@example.com" style="color:#17231F; font-weight:bold; text-decoration:none;">{{$email}}</a>.
              Select the button below to finish setting up your account.
            </p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#E8F6F2; border:1px solid #E1E9E5; border-radius:12px; margin-bottom:12px;">
              <tr>
                <td style="padding:14px 16px; font-family:'Courier New', Courier, monospace; font-size:13px; color:#063B32; word-break:break-all; text-align:center;">
                  {{ $token }}
                </td>
              </tr>
            </table>

            <p style="font-size:12px; color:#6B7772; margin:0 0 24px; font-family:Arial, Helvetica, sans-serif;">
              <span style="color:#F4B942;">&#9679;</span> Expires in 2 minutes
            </p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td align="center" style="background:#087F68; border-radius:12px;">
                  <a href="{{ url('api/auth/mail/confirm?token=' . $token) }}" style="display:block; padding:14px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; color:#FFFFFF; text-decoration:none;">Verify email</a>
                </td>
              </tr>
            </table>
            <p style="margin:14px 0 0; font-family:Arial, Helvetica, sans-serif; font-size:13px;">
                <a href="{{ url('api/auth/mail/resend?email=' . urlencode($email)) }}" style="color:#087F68; text-decoration:none;">
                    Didn't get it? Resend token
                </a>
            </p>

          </td>
        </tr>

      </table>

    </td>
  </tr>
</table>
</body>
</html>