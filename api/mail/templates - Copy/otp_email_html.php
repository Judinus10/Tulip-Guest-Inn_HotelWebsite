<?php
declare(strict_types=1);

function otp_email_html(string $title, string $otp, int $validMinutes = 1): string
{
    $safeTitle = email_safe($title);
    $safeOtp = preg_replace('/\D+/', '', (string) $otp);
    if ($safeOtp === '') {
        $safeOtp = email_safe($otp);
    }

    $digitsHtml = '';
    foreach (str_split((string) $safeOtp) as $digit) {
        $digitsHtml .= '<td class="otp-digit" style="padding:0 5px;">
            <div style="width:52px;height:58px;line-height:58px;border:1px solid #dfd3c5;border-radius:9px;background:#ffffff;color:#987b58;font-size:31px;font-weight:800;text-align:center;font-family:Arial,Helvetica,sans-serif;">' . email_safe($digit) . '</div>
        </td>';
    }

    $year = date('Y');
    $minutesText = (int) $validMinutes . ' minute' . ((int) $validMinutes === 1 ? '' : 's');

    return '<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . $safeTitle . '</title>' . email_icon_font_css() . '
<style>
@media only screen and (max-width: 620px) {
  .email-wrap { width: 100% !important; border-radius: 0 !important; }
  .email-pad { padding: 28px 18px !important; }
  .brand-left, .alert-right { display: block !important; width: 100% !important; text-align: center !important; }
  .alert-right { margin-top: 18px !important; }
  .alert-right table { margin: 0 auto !important; }
  .hero-icon { width: 86px !important; height: 86px !important; line-height: 86px !important; font-size: 42px !important; }
  .title { font-size: 23px !important; line-height: 29px !important; }
  .otp-box { padding: 12px 10px !important; }
  .otp-digit { padding: 0 3px !important; }
  .otp-digit div { width: 38px !important; height: 46px !important; line-height: 46px !important; font-size: 25px !important; }
  .security-table td, .help-table td { display: block !important; width: 100% !important; box-sizing: border-box !important; text-align: left !important; }
  .security-icon { padding: 18px 20px 0 !important; }
  .security-copy { padding: 10px 20px 18px !important; }
  .help-left, .help-right { padding: 8px 20px !important; }
  .help-divider { display: none !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#050505;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ffffff;margin:0;padding:24px 10px;">
    <tr>
      <td align="center">
        <table role="presentation" class="email-wrap" width="760" cellspacing="0" cellpadding="0" style="width:760px;max-width:760px;background:#ffffff;border:1px solid #e7ddd2;border-radius:5px;overflow:hidden;box-shadow:0 8px 28px rgba(39,24,8,.08);">
          <tr>
            <td style="background:#987b58;padding:24px 40px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                  <td class="brand-left" align="left" style="vertical-align:middle;">
                    <div style="font-family:Arial,Helvetica,sans-serif;color:#ffffff;font-size:30px;letter-spacing:5px;line-height:34px;">JEBAL</div>
                    <div style="color:#ffffff;font-size:10px;letter-spacing:5px;margin-top:3px;">GUEST HOUSE</div>
                    <div style="color:#f0c17c;font-size:11px;letter-spacing:1.5px;margin-top:10px;">— ADMIN VERIFICATION —</div>
                  </td>
                  <td class="alert-right" align="right" style="vertical-align:middle;">
                    <table role="presentation" cellspacing="0" cellpadding="0" align="right">
                      <tr>
                        <td style="width:52px;height:52px;border-radius:18px;background:linear-gradient(135deg,#c38a39,#987b58);color:#ffffff;text-align:center;font-size:11px;line-height:52px;">' . booking_email_icon('lock') . '</td>
                        <td style="padding-left:14px;color:#ffffff;text-align:left;">
                          <div style="font-weight:800;font-size:15px;line-height:21px;">Security Alert</div>
                          <div style="font-size:14px;line-height:20px;color:#ffffff;">Admin Verification</div>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td class="email-pad" style="padding:36px 68px 28px;background:#ffffff;">
              <div align="center">
                <div class="hero-icon" style="width:96px;height:96px;border-radius:50%;background:#f7f4ef;color:#987b58;text-align:center;line-height:96px;font-size:30px;margin:0 auto 22px;">' . booking_email_icon('mail') . '</div>
                <h1 class="title" style="margin:0 0 18px;font-size:29px;line-height:36px;font-weight:900;color:#050505;">Admin Verification Code</h1>
                <p style="margin:0 0 6px;font-size:16px;line-height:24px;color:#050505;">Hello Admin,</p>
                <p style="margin:0 auto 26px;max-width:430px;font-size:16px;line-height:25px;color:#050505;">Use the OTP code below to verify your identity and access the admin dashboard.</p>

                <table role="presentation" width="520" cellspacing="0" cellpadding="0" style="width:520px;max-width:100%;border:1px solid #e3d8cc;border-radius:9px;background:#ffffff;margin:0 auto 24px;">
                  <tr>
                    <td class="otp-box" align="center" style="padding:20px 18px 17px;">
                      <div style="font-size:16px;color:#050505;margin-bottom:16px;">Your OTP Code</div>
                      <table role="presentation" cellspacing="0" cellpadding="0" align="center" style="margin:0 auto;">
                        <tr>' . $digitsHtml . '</tr>
                      </table>
                      <p style="margin:22px 0 0;font-size:17px;line-height:24px;color:#050505;">This code will expire in <strong style="color:#987b58;">' . email_safe($minutesText) . '</strong>.</p>
                    </td>
                  </tr>
                </table>

                <table role="presentation" class="security-table" width="610" cellspacing="0" cellpadding="0" style="width:610px;max-width:100%;border:1px solid #e3d8cc;border-radius:9px;background:#ffffff;margin:0 auto 28px;">
                  <tr>
                    <td class="security-icon" width="70" align="center" style="padding:20px 10px 20px 24px;vertical-align:top;color:#987b58;font-size:30px;">' . booking_email_icon('security') . '</td>
                    <td class="security-copy" style="padding:20px 24px 20px 6px;text-align:left;">
                      <div style="font-size:16px;font-weight:800;color:#050505;margin-bottom:6px;">For your security</div>
                      <div style="font-size:14px;line-height:22px;color:#050505;">Do not share this code with anyone.<br>If you did not request this code, please ignore this email.</div>
                    </td>
                  </tr>
                </table>
              </div>

              <div style="height:1px;background:#e4d9cc;margin:0 0 28px;"></div>

              <table role="presentation" class="help-table" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                  <td class="help-left" width="44%" style="padding:8px 20px 8px 36px;vertical-align:middle;">
                    <table role="presentation" cellspacing="0" cellpadding="0">
                      <tr>
                        <td style="width:64px;height:64px;border-radius:50%;background:#f7f4ef;color:#987b58;text-align:center;line-height:64px;font-size:30px;">' . booking_email_icon('headset', 24) . '</td>
                        <td style="padding-left:18px;">
                          <div style="font-size:18px;font-weight:900;color:#050505;margin-bottom:4px;">Need help?</div>
                          <div style="font-size:14px;line-height:20px;color:#050505;">If you have any issues,<br>contact our support team.</div>
                        </td>
                      </tr>
                    </table>
                  </td>
                  <td class="help-divider" width="1" style="background:#e4d9cc;"></td>
                  <td class="help-right" style="padding:8px 10px 8px 42px;vertical-align:middle;">
                    <div style="font-size:15px;line-height:28px;color:#050505;"><span style="color:#987b58;">' . booking_email_icon('phone') . '</span>&nbsp;&nbsp; ' . email_safe(email_contact_phone()) . '</div>
                    <div style="font-size:15px;line-height:28px;color:#050505;"><span style="color:#987b58;">' . booking_email_icon('mail') . '</span>&nbsp;&nbsp; ' . email_safe(email_contact_email()) . '</div>
                    <div style="font-size:15px;line-height:28px;color:#050505;"><span style="color:#987b58;">' . booking_email_icon('web') . '</span>&nbsp;&nbsp; ' . email_safe(email_contact_website()) . '</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td align="center" style="background:#ffffff;border-top:1px solid #eee6dc;padding:21px 20px 25px;">
              <div style="font-size:15px;line-height:24px;color:#6b7280;">This is an automated email. Please do not reply.</div>
              <div style="font-size:15px;line-height:24px;color:#6b7280;">&copy; ' . $year . ' Tulip Guest Inn. All rights reserved.</div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}
