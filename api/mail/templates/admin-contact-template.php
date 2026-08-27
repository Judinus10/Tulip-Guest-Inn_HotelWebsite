<?php
/** Administrator contact enquiry email template. */

declare(strict_types=1);

function contact_admin_email_html(string $name, string $email, string $phone, string $subject, string $message, string $ref): string
{
    $brand = email_brand_name();
    $year = date('Y');
    $contactPhone = email_contact_phone();
    $contactEmail = email_contact_email();
    $contactWebsite = email_contact_website();

    $adminUrl = defined('ADMIN_APP_URL') ? trim((string) ADMIN_APP_URL) : '';
    if ($adminUrl === '') {
        $adminUrl = rtrim(email_public_url(), '/') . '/admin';
    }

    $safeName = trim($name) !== '' ? trim($name) : '-';
    $safeEmail = trim($email) !== '' ? trim($email) : '-';
    $safePhone = trim($phone) !== '' ? trim($phone) : '-';
    $safeSubject = trim($subject) !== '' ? trim($subject) : '-';
    $safeMessage = trim($message) !== '' ? trim($message) : '-';

    $preheader = '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">A new contact message was received from the website.</div>';

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<title>New Contact Message Received</title>
' . email_icon_font_css() . '
<style>
    body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
    table,td{mso-table-lspace:0pt;mso-table-rspace:0pt}
    table{border-collapse:collapse!important}
    img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
    a{text-decoration:none}
    .email-shell{width:100%;max-width:760px}
    .outer-pad{padding:26px 20px}
    .header-pad{padding:27px 32px 24px}
    .content-pad{padding:22px 32px 29px}
    .hero-icon-cell{width:78px;padding-right:20px}
    .hero-icon{width:70px;height:70px}
    .detail-label{width:27%}
    .detail-colon{width:24px}
    .contact-inline td{white-space:nowrap}
    .desktop-only{display:block}
    @media only screen and (max-width:700px){
        .outer-pad{padding:18px 12px!important}
        .email-shell{max-width:560px!important}
        .header-pad{padding:24px 28px 21px!important}
        .content-pad{padding:20px 28px 27px!important}
        .hero-icon-cell{width:62px!important;padding-right:16px!important}
        .hero-icon{width:58px!important;height:58px!important}
        .hero-title{font-size:21px!important;line-height:1.25!important}
        .hero-copy{font-size:14px!important;line-height:1.55!important}
        .detail-card td{font-size:14px!important}
        .contact-inline{width:100%!important}
        .contact-inline tr{display:block!important}
        .contact-inline td{display:block!important;width:100%!important;padding:5px 0!important;white-space:normal!important}
    }
    @media only screen and (max-width:480px){
        .outer-pad{padding:0!important}
        .email-shell{max-width:320px!important;border-left:1px solid #dfe4ec!important;border-right:1px solid #dfe4ec!important}
        .header-pad{padding:24px 20px 20px!important}
        .header-browser{display:none!important}
        .brand-title{font-size:20px!important}
        .brand-subtitle{font-size:14px!important}
        .content-pad{padding:19px 18px 24px!important}
        .hero-table td{vertical-align:top!important}
        .hero-icon-cell{width:52px!important;padding-right:13px!important}
        .hero-icon{width:48px!important;height:48px!important}
        .hero-title{font-size:19px!important;line-height:1.23!important}
        .hero-copy{font-size:13px!important;line-height:1.62!important;margin-top:7px!important}
        .section-title{font-size:16px!important;margin-top:20px!important}
        .detail-card{border-radius:4px!important}
        .detail-row,.detail-row tbody,.detail-row tr,.detail-row td{display:block!important;width:100%!important;box-sizing:border-box!important}
        .detail-row{padding:9px 9px 0!important}
        .detail-label{padding:0!important;border:0!important;font-size:13px!important;line-height:1.35!important}
        .detail-colon{display:none!important}
        .detail-value{padding:2px 0 8px!important;border-bottom:0!important;font-size:13px!important;line-height:1.45!important}
        .message-value{line-height:1.5!important}
        .info-pad{padding:11px 12px!important}
        .info-icon-cell{width:26px!important;padding-right:8px!important}
        .info-title{font-size:14px!important}
        .info-copy{font-size:13px!important;line-height:1.55!important}
        .dashboard-button{display:block!important;width:100%!important;box-sizing:border-box!important;padding:11px 12px!important;font-size:14px!important}
        .contact-area{padding-top:14px!important}
        .contact-inline td{font-size:13px!important;padding:6px 0!important}
        .footer-pad{padding:21px 16px 24px!important}
        .footer-copy{font-size:13px!important;line-height:1.6!important}
    }
</style>
</head>
<body style="margin:0;padding:0;background:#f8f9fb;font-family:Arial,Helvetica,sans-serif;color:#071230;">
' . $preheader . '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#f8f9fb;">
<tr>
<td align="center" class="outer-pad">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="email-shell" style="width:100%;max-width:760px;background:#ffffff;border:1px solid #dfe4ec;">
<tr>
<td class="header-pad" style="padding:27px 32px 24px;background:#ffffff;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td align="left" style="vertical-align:top;">
<div class="brand-title" style="font-size:22px;line-height:1.2;font-weight:700;color:#071230;">Tulip Guest Inn</div>
<div class="brand-subtitle" style="margin-top:7px;font-size:14px;line-height:1.35;color:#3d4559;">A Clean and Comfortable Stay</div>
</td>
<td align="right" class="header-browser" style="vertical-align:top;padding-top:5px;font-size:12px;line-height:1.4;">
</td>
</tr>
</table>
<div style="height:1px;background:#dfe4ec;margin-top:22px;font-size:0;line-height:0;">&nbsp;</div>
</td>
</tr>
<tr>
<td class="content-pad" style="padding:22px 32px 29px;background:#ffffff;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="hero-table">
<tr>
<td class="hero-icon-cell" style="width:78px;padding-right:20px;vertical-align:top;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="hero-icon" style="width:70px;height:70px;border-radius:999px;background:#eef4fd;">
<tr><td align="center" valign="middle" style="color:#05215a;font-size:32px;line-height:1;">' . booking_email_icon('mail', 32) . '</td></tr>
</table>
</td>
<td style="vertical-align:middle;">
<div class="hero-title" style="font-size:24px;line-height:1.25;font-weight:700;color:#071230;">New Contact Message Received</div>
<div class="hero-copy" style="margin-top:13px;font-size:15px;line-height:1.55;color:#071230;">You have received a new message from your website contact form.<br>Please find the details below.</div>
</td>
</tr>
</table>

<div class="section-title" style="margin-top:30px;margin-bottom:14px;font-size:17px;line-height:1.3;font-weight:700;color:#071230;">Contact Details</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="detail-card" style="width:100%;border:1px solid #dfe4ec;border-radius:4px;background:#ffffff;overflow:hidden;">
<tr><td style="padding:0 14px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="detail-row" style="width:100%;">
<tr>
<td class="detail-label" style="width:27%;padding:12px 0;border-bottom:1px solid #e6e9ef;font-size:14px;line-height:1.45;font-weight:700;color:#071230;vertical-align:top;">Name</td>
<td class="detail-colon" style="width:24px;padding:12px 4px;border-bottom:1px solid #e6e9ef;font-size:14px;line-height:1.45;color:#071230;vertical-align:top;">:</td>
<td class="detail-value" style="padding:12px 0;border-bottom:1px solid #e6e9ef;font-size:14px;line-height:1.45;color:#071230;vertical-align:top;word-break:break-word;">' . email_safe($safeName) . '</td>
</tr>
<tr>
<td class="detail-label" style="width:27%;padding:12px 0;border-bottom:1px solid #e6e9ef;font-size:14px;line-height:1.45;font-weight:700;color:#071230;vertical-align:top;">Email</td>
<td class="detail-colon" style="width:24px;padding:12px 4px;border-bottom:1px solid #e6e9ef;font-size:14px;line-height:1.45;color:#071230;vertical-align:top;">:</td>
<td class="detail-value" style="padding:12px 0;border-bottom:1px solid #e6e9ef;font-size:14px;line-height:1.45;color:#071230;vertical-align:top;word-break:break-word;">' . email_safe($safeEmail) . '</td>
</tr>
<tr>
<td class="detail-label" style="width:27%;padding:12px 0;border-bottom:1px solid #e6e9ef;font-size:14px;line-height:1.45;font-weight:700;color:#071230;vertical-align:top;">Phone</td>
<td class="detail-colon" style="width:24px;padding:12px 4px;border-bottom:1px solid #e6e9ef;font-size:14px;line-height:1.45;color:#071230;vertical-align:top;">:</td>
<td class="detail-value" style="padding:12px 0;border-bottom:1px solid #e6e9ef;font-size:14px;line-height:1.45;color:#071230;vertical-align:top;word-break:break-word;">' . email_safe($safePhone) . '</td>
</tr>
<tr>
<td class="detail-label" style="width:27%;padding:12px 0;border-bottom:1px solid #e6e9ef;font-size:14px;line-height:1.45;font-weight:700;color:#071230;vertical-align:top;">Subject</td>
<td class="detail-colon" style="width:24px;padding:12px 4px;border-bottom:1px solid #e6e9ef;font-size:14px;line-height:1.45;color:#071230;vertical-align:top;">:</td>
<td class="detail-value" style="padding:12px 0;border-bottom:1px solid #e6e9ef;font-size:14px;line-height:1.45;color:#071230;vertical-align:top;word-break:break-word;">' . email_safe($safeSubject) . '</td>
</tr>
<tr>
<td class="detail-label" style="width:27%;padding:12px 0;font-size:14px;line-height:1.45;font-weight:700;color:#071230;vertical-align:top;">Message</td>
<td class="detail-colon" style="width:24px;padding:12px 4px;font-size:14px;line-height:1.45;color:#071230;vertical-align:top;">:</td>
<td class="detail-value message-value" style="padding:12px 0;font-size:14px;line-height:1.55;color:#071230;vertical-align:top;word-break:break-word;">' . nl2br(email_safe($safeMessage)) . '</td>
</tr>
</table>
</td></tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:20px;border:1px solid #cfe0fb;border-radius:4px;background:#f2f7ff;">
<tr>
<td class="info-pad" style="padding:15px 16px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td class="info-icon-cell" style="width:31px;padding-right:11px;vertical-align:top;color:#1460aa;font-size:22px;line-height:1;">' . booking_email_icon('info', 21) . '</td>
<td style="vertical-align:top;">
<div class="info-title" style="font-size:15px;line-height:1.35;font-weight:700;color:#071230;">Important Information</div>
<div class="info-copy" style="margin-top:7px;font-size:14px;line-height:1.5;color:#071230;">Please respond to the guest at your earliest convenience.</div>
</td>
</tr>
</table>
</td>
</tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:22px auto 0;">
<tr><td align="center" style="border-radius:4px;background:#061d4f;"><a class="dashboard-button" href="' . email_safe($adminUrl) . '" style="display:inline-block;padding:12px 34px;color:#ffffff;font-size:15px;line-height:1.2;font-weight:700;">View Message in Dashboard</a></td></tr>
</table>

<div style="height:1px;background:#dfe4ec;margin-top:30px;font-size:0;line-height:0;">&nbsp;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="contact-inline contact-area" style="width:100%;margin-top:21px;">
<tr>
<td style="padding-right:18px;font-size:14px;line-height:1.45;color:#071230;"><span style="display:inline-block;width:22px;color:#071230;font-size:17px;vertical-align:middle;">' . booking_email_icon('phone', 17) . '</span>' . email_safe($contactPhone) . '</td>
<td style="padding-right:18px;font-size:14px;line-height:1.45;color:#071230;"><span style="display:inline-block;width:24px;color:#071230;font-size:17px;vertical-align:middle;">' . booking_email_icon('mail', 17) . '</span>' . email_safe($contactEmail) . '</td>
<td style="font-size:14px;line-height:1.45;color:#071230;"><span style="display:inline-block;width:24px;color:#071230;font-size:17px;vertical-align:middle;">' . booking_email_icon('web', 17) . '</span>' . email_safe($contactWebsite) . '</td>
</tr>
</table>
</td>
</tr>
<tr>
<td class="footer-pad" align="center" style="padding:22px 20px 25px;background:#f7f8fb;border-top:1px solid #e2e6ed;">
<div class="footer-copy" style="font-size:13px;line-height:1.5;color:#4b5365;">&copy; ' . $year . ' ' . email_safe($brand) . '. All rights reserved.</div>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>';
}
