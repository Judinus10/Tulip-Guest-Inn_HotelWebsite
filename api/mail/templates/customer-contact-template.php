<?php
/** Customer contact acknowledgement email template and shared contact rendering helpers. */

declare(strict_types=1);

function contact_details_html(string $name, string $email, string $phone, string $subject, string $message): string
{
    return email_info_table([
        'Name' => $name,
        'Email' => $email,
        'Phone' => $phone,
        'Subject' => $subject,
        'Message' => $message,
    ]);
}

function contact_email_icon(string $icon): string
{
    $iconHtml = booking_email_icon($icon);

    return '<td width="58" style="width:58px;vertical-align:top;padding:0 18px 0 0;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="46" height="46" style="width:46px;height:46px;border-radius:999px;background:#f7f4ef;border:1px solid #d8c9b8;">
            <tr>
                <td align="center" valign="middle" style="width:46px;height:46px;text-align:center;color:#987b58;font-size:18px;line-height:1;">' . $iconHtml . '</td>
            </tr>
        </table>
    </td>';
}

function contact_email_shell(string $title, string $content, string $preheader = ''): string
{
    $brand = email_brand_name();
    $year = date('Y');
    $preheaderHtml = $preheader !== ''
        ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . email_safe($preheader) . '</div>'
        : '';

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . email_safe($title) . '</title>' . email_icon_font_css() . '
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#111111;">
' . $preheaderHtml . '
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background:#ffffff;margin:0;padding:22px 10px;">
<tr>
<td align="center">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;max-width:760px;background:#ffffff;border-radius:7px;border:1px solid #eee8e1;box-shadow:0 16px 38px rgba(86,61,35,.13);overflow:hidden;">
<tr>
<td align="left" style="padding:29px 38px 19px;background:#ffffff;text-align:left;">
    <div style="font-family:Arial,Helvetica,sans-serif;font-size:38px;line-height:.95;color:#987b58;font-weight:700;letter-spacing:5px;text-transform:uppercase;">TULIP</div>
    <div style="margin-top:8px;font-size:17px;line-height:1;color:#987b58;font-weight:700;letter-spacing:4px;text-transform:uppercase;">GUEST HOUSE</div>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:13px 0 0;">
        <tr>
            <td style="width:34px;border-top:1px solid #b89166;font-size:0;line-height:0;">&nbsp;</td>
            <td style="padding:0 12px;color:#987b58;font-size:15px;line-height:1.2;white-space:nowrap;">Comfortable Guest House</td>
            <td style="width:34px;border-top:1px solid #b89166;font-size:0;line-height:0;">&nbsp;</td>
        </tr>
    </table>
</td>
</tr>
<tr>
<td style="height:2px;background:#987b58;font-size:0;line-height:0;">&nbsp;</td>
</tr>
<tr>
<td style="padding:35px 38px 29px;background:#ffffff;">
' . $content . '
</td>
</tr>
<tr>
<td style="padding:0 38px;background:#ffffff;">
    <div style="border-top:1px solid #d8d0c8;font-size:0;line-height:0;">&nbsp;</div>
</td>
</tr>
<tr>
<td align="center" style="padding:20px 28px 24px;background:#ffffff;">
    <p style="margin:0;color:#111111;font-size:16px;line-height:1.45;">Thank you for choosing ' . email_safe($brand) . '.<br>We look forward to serving you.</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:13px 0 0;">
        <tr>
            <td style="width:44px;border-top:1px solid #b89166;font-size:0;line-height:0;">&nbsp;</td>
            <td style="padding:0 12px;color:#987b58;font-size:18px;line-height:1;">' . booking_email_icon('check') . '</td>
            <td style="width:44px;border-top:1px solid #b89166;font-size:0;line-height:0;">&nbsp;</td>
        </tr>
    </table>
    <p style="margin:11px 0 0;color:#111111;font-size:14px;line-height:1.4;">&copy; ' . $year . ' ' . email_safe($brand) . '. All rights reserved.</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>';
}

function contact_reference_pill(string $ref): string
{
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 19px;">
        <tr>
            <td style="border-radius:999px;background:#987b58;padding:13px 21px;color:#ffffff;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.02em;">
                REFERENCE ID
                <span style="display:inline-block;margin:0 24px;color:#d7b07c;font-weight:400;">|</span>
                <span style="font-size:18px;letter-spacing:.04em;">' . email_safe($ref) . '</span>
            </td>
        </tr>
    </table>';
}

function contact_email_detail_card(array $rows): string
{
    $visibleRows = [];
    foreach ($rows as $row) {
        $label = (string) ($row['label'] ?? '');
        $value = (string) ($row['value'] ?? '');
        $icon = (string) ($row['icon'] ?? '•');

        if ($label === '' || $value === '') {
            continue;
        }

        $visibleRows[] = [
            'label' => $label,
            'value' => $value,
            'icon' => $icon,
        ];
    }

    if ($visibleRows === []) {
        return '';
    }

    $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;margin:0 0 20px;border:1px solid #ded6cf;border-radius:12px;border-collapse:separate;border-spacing:0;background:#ffffff;">';

    $count = count($visibleRows);
    foreach ($visibleRows as $index => $row) {
        $border = $index < $count - 1 ? 'border-bottom:1px solid #ded6cf;' : '';
        $html .= '<tr>
            <td style="padding:17px 20px;' . $border . '">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        ' . contact_email_icon($row['icon']) . '
                        <td style="vertical-align:middle;padding:0;color:#111111;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td width="36%" style="width:36%;padding:0 18px 0 0;color:#111111;font-size:15px;font-weight:700;line-height:1.4;">' . email_safe($row['label']) . '</td>
                                    <td width="1" style="width:1px;background:#d9d0c8;font-size:0;line-height:0;">&nbsp;</td>
                                    <td style="padding:0 0 0 35px;color:#111111;font-size:15px;font-weight:800;line-height:1.45;word-break:break-word;">' . nl2br(email_safe($row['value'])) . '</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>';
    }

    return $html . '</table>';
}

function contact_support_block(): string
{
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;margin:22px 0 25px;background:#ffffff;border-radius:12px;">
        <tr>
            <td style="padding:18px 20px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td width="43%" style="width:43%;vertical-align:middle;padding:0 20px 0 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="55" style="width:55px;vertical-align:middle;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="50" height="50" style="width:50px;height:50px;border-radius:999px;background:#987b58;">
                                            <tr><td align="center" valign="middle" style="color:#ffffff;font-size:20px;line-height:1;">' . booking_email_icon('headset', 24) . '</td></tr>
                                        </table>
                                    </td>
                                    <td style="padding-left:13px;vertical-align:middle;">
                                        <div style="font-size:17px;line-height:1.3;font-weight:800;color:#111111;">Need immediate assistance?</div>
                                        <div style="margin-top:4px;font-size:14px;line-height:1.4;color:#111111;">Our team is here to help you.</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td width="1" style="width:1px;background:#d6cdc3;font-size:0;line-height:0;">&nbsp;</td>
                        <td style="vertical-align:middle;padding-left:28px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="padding:4px 18px 4px 0;color:#111111;font-size:14px;line-height:1.4;white-space:nowrap;"><span style="color:#987b58;font-size:18px;">' . booking_email_icon('phone') . '</span>&nbsp;&nbsp;' . email_safe(email_contact_phone()) . '</td>
                                    <td style="padding:4px 0;color:#111111;font-size:14px;line-height:1.4;white-space:nowrap;"><span style="color:#987b58;font-size:18px;">' . booking_email_icon('web') . '</span>&nbsp;&nbsp;' . email_safe(email_contact_website()) . '</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 18px 4px 0;color:#111111;font-size:14px;line-height:1.4;white-space:nowrap;"><span style="color:#987b58;font-size:18px;">' . booking_email_icon('mail') . '</span>&nbsp;&nbsp;' . email_safe(email_contact_email()) . '</td>
                                    <td style="padding:4px 0;color:#111111;font-size:14px;line-height:1.4;vertical-align:top;"><span style="color:#987b58;font-size:18px;vertical-align:top;">' . booking_email_icon('location') . '</span>&nbsp;&nbsp;<span style="display:inline-block;">' . nl2br(email_safe(email_contact_address())) . '</span></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>';
}

function contact_customer_email_html(string $name, string $email, string $phone, string $subject, string $message, string $ref = ''): string
{
    $safeName = trim($name) !== '' ? trim($name) : 'Guest';
    $year = date('Y');
    $brand = email_brand_name();
    $websiteUrl = email_public_url();
    $websiteLabel = email_contact_website();
    $contactPhone = email_contact_phone();
    $contactEmail = email_contact_email();

    $detailRows = [
        'Name' => $safeName,
        'Email' => $email,
        'Phone' => $phone,
        'Subject' => $subject,
        'Message' => $message,
    ];

    $detailsHtml = '';
    foreach ($detailRows as $label => $value) {
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }

        $detailsHtml .= '<tr class="contact-detail-row">
            <td class="contact-detail-label" style="width:27%;padding:12px 16px 12px 18px;border-bottom:1px solid #e6e9ef;color:#071230;font-size:14px;line-height:1.45;font-weight:700;vertical-align:top;">' . email_safe((string) $label) . '</td>
            <td class="contact-detail-colon" style="width:24px;padding:12px 5px;border-bottom:1px solid #e6e9ef;color:#071230;font-size:14px;line-height:1.45;vertical-align:top;">:</td>
            <td class="contact-detail-value" style="padding:12px 18px;border-bottom:1px solid #e6e9ef;color:#071230;font-size:14px;line-height:1.55;vertical-align:top;word-break:break-word;">' . nl2br(email_safe($value)) . '</td>
        </tr>';
    }

    $preheader = '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">We received your message and will reply as soon as possible.</div>';

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>We received your message</title>
<style>
  body{margin:0!important;padding:0!important;background:#ffffff!important;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}
  table{border-spacing:0;mso-table-lspace:0pt;mso-table-rspace:0pt;}
  img{border:0;display:block;outline:none;text-decoration:none;}
  a{text-decoration:none;}
  .contact-card{width:680px;max-width:680px;}
  .contact-main{padding:28px 34px 0;}
  .contact-header{padding:25px 34px 18px;}
  .contact-brand-title{font-size:21px;}
  .contact-brand-subtitle{font-size:14px;}
  .contact-hero-icon-cell{width:82px;padding-right:22px;vertical-align:middle;}
  .contact-hero-copy{text-align:left;vertical-align:middle;}
  .contact-hero-title{font-size:25px;}
  .contact-details-title{font-size:17px;}
  .contact-help-items td{white-space:nowrap;}
  .contact-footer{padding:25px 20px;}

  @media only screen and (max-width:720px){
    .contact-card{width:100%!important;max-width:100%!important;}
    .contact-outer{padding:18px 14px!important;}
    .contact-main{padding:26px 28px 0!important;}
    .contact-header{padding:24px 28px 17px!important;}
    .contact-help-items td{display:block!important;width:100%!important;padding:5px 0!important;white-space:normal!important;}
    .contact-help-divider{display:none!important;}
  }

  @media only screen and (max-width:520px){
    .contact-outer{padding:0!important;}
    .contact-card{border-left:0!important;border-right:0!important;border-radius:0!important;}
    .contact-header{padding:23px 20px 17px!important;text-align:center!important;}
    .contact-header-left,.contact-header-right{display:block!important;width:100%!important;text-align:center!important;}
    .contact-header-right{display:none!important;}
    .contact-brand-title{font-size:20px!important;}
    .contact-brand-subtitle{font-size:13px!important;margin-top:8px!important;}
    .contact-main{padding:18px 20px 0!important;}
    .contact-hero-icon-cell,.contact-hero-copy{display:block!important;width:100%!important;padding:0!important;text-align:center!important;}
    .contact-hero-icon{margin:0 auto 18px!important;width:64px!important;height:64px!important;}
    .contact-hero-check{font-size:29px!important;line-height:58px!important;width:58px!important;height:58px!important;}
    .contact-hero-title{font-size:21px!important;margin:0 0 10px!important;}
    .contact-hero-text{font-size:14px!important;line-height:1.5!important;}
    .contact-details-wrap{margin-top:28px!important;}
    .contact-details-title{font-size:16px!important;margin-bottom:10px!important;}
    .contact-detail-row{display:block!important;padding:10px 12px!important;border-bottom:0!important;}
    .contact-detail-label,.contact-detail-colon,.contact-detail-value{display:block!important;width:100%!important;box-sizing:border-box!important;border:0!important;padding:0!important;}
    .contact-detail-label{font-size:13px!important;line-height:1.35!important;margin-bottom:2px!important;}
    .contact-detail-colon{display:none!important;}
    .contact-detail-value{font-size:13px!important;line-height:1.45!important;margin-bottom:6px!important;}
    .contact-next{margin-top:12px!important;}
    .contact-next-cell{padding:11px 12px!important;}
    .contact-next-icon{width:28px!important;vertical-align:top!important;}
    .contact-next-title{font-size:14px!important;}
    .contact-next-text{font-size:13px!important;line-height:1.45!important;}
    .contact-help{padding:18px 0 20px!important;}
    .contact-help-title{font-size:14px!important;}
    .contact-help-items td{font-size:13px!important;}
    .contact-footer{padding:18px 20px!important;font-size:12px!important;line-height:1.6!important;}
  }
</style>
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#071230;">
' . $preheader . '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="contact-outer" style="width:100%;background:#ffffff;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="contact-card" style="width:680px;max-width:680px;background:#ffffff;border:1px solid #dfe3ea;box-shadow:0 8px 26px rgba(15,28,55,.06);">
<tr>
<td class="contact-header" style="padding:25px 34px 18px;background:#ffffff;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
      <td class="contact-header-left" align="left" style="vertical-align:middle;">
        <div class="contact-brand-title" style="font-size:21px;line-height:1.25;font-weight:800;color:#071230;">' . email_safe($brand) . '</div>
        <div class="contact-brand-subtitle" style="margin-top:7px;font-size:14px;line-height:1.4;color:#37415b;">A Clean and Comfortable Stay</div>
      </td>
      <td class="contact-header-right" align="right" style="vertical-align:middle;font-size:12px;line-height:1.4;">
        
      </td>
    </tr>
  </table>
</td>
</tr>
<tr><td style="padding:0 34px;"><div style="height:1px;background:#dfe3ea;font-size:0;line-height:0;">&nbsp;</div></td></tr>
<tr>
<td class="contact-main" style="padding:28px 34px 0;background:#ffffff;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
      <td class="contact-hero-icon-cell" style="width:82px;padding-right:22px;vertical-align:middle;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="contact-hero-icon" style="width:74px;height:74px;border-radius:50%;background:#eef8ef;">
          <tr><td align="center" valign="middle">
            <div class="contact-hero-check" style="width:60px;height:60px;border-radius:50%;color:#23883c;font-size:31px;font-weight:700;line-height:60px;text-align:center;">&#10003;</div>
          </td></tr>
        </table>
      </td>
      <td class="contact-hero-copy" style="vertical-align:middle;text-align:left;">
        <h1 class="contact-hero-title" style="margin:0 0 12px;color:#071230;font-size:25px;line-height:1.2;font-weight:800;">Thank You!</h1>
        <p class="contact-hero-text" style="margin:0;color:#071230;font-size:15px;line-height:1.55;">We have received your message.<br>Our team will get back to you as soon as possible.</p>
      </td>
    </tr>
  </table>

  <div class="contact-details-wrap" style="margin-top:36px;">
    <h2 class="contact-details-title" style="margin:0 0 14px;color:#071230;font-size:17px;line-height:1.3;font-weight:800;">Your Message Details</h2>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border:1px solid #dfe3ea;border-radius:5px;border-collapse:separate;overflow:hidden;background:#ffffff;">' . $detailsHtml . '</table>
  </div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="contact-next" style="width:100%;margin-top:24px;border:1px solid #cfe0fb;border-radius:5px;background:#f3f7ff;">
    <tr>
      <td class="contact-next-cell" style="padding:14px 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td class="contact-next-icon" style="width:36px;vertical-align:middle;">
              <div style="width:24px;height:24px;border-radius:50%;background:#1765bf;color:#ffffff;font-size:15px;line-height:24px;text-align:center;font-weight:800;">i</div>
            </td>
            <td style="vertical-align:middle;">
              <div class="contact-next-title" style="color:#071230;font-size:14px;line-height:1.35;font-weight:800;">What\'s Next?</div>
              <div class="contact-next-text" style="margin-top:5px;color:#071230;font-size:14px;line-height:1.45;">We will review your message and respond to you at the earliest.</div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <div class="contact-help" style="margin-top:24px;padding:22px 0 24px;border-top:1px solid #dfe3ea;">
    <div class="contact-help-title" style="margin:0 0 14px;color:#071230;font-size:14px;line-height:1.35;font-weight:800;">Need help?</div>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="contact-help-items" style="width:100%;">
      <tr>
        <td style="padding-right:24px;color:#071230;font-size:13px;line-height:1.5;">' . booking_email_icon('phone', 17) . '&nbsp;&nbsp;' . email_safe($contactPhone) . '</td>
        <td style="padding-right:24px;color:#071230;font-size:13px;line-height:1.5;">' . booking_email_icon('mail', 17) . '&nbsp;&nbsp;' . email_safe($contactEmail) . '</td>
        <td style="color:#071230;font-size:13px;line-height:1.5;">' . booking_email_icon('web', 17) . '&nbsp;&nbsp;<a href="' . email_safe($websiteUrl) . '" style="color:#071230;text-decoration:none;">' . email_safe($websiteLabel) . '</a></td>
      </tr>
    </table>
  </div>
</td>
</tr>
<tr>
<td class="contact-footer" align="center" style="padding:25px 20px;background:#f6f7fa;border-top:1px solid #e3e6ec;color:#4a536b;font-size:13px;line-height:1.5;text-align:center;">&copy; ' . $year . ' ' . email_safe($brand) . '. All rights reserved.</td>
</tr>
</table>
</td></tr>
</table>
</body>
</html>';
}
