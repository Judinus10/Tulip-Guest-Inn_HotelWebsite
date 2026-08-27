<?php
/** Responsive administrator booking notification email template. */

declare(strict_types=1);

function admin_booking_status_config(string $state): array
{
    $state = strtolower(trim($state));

    return match ($state) {
        'paid', 'confirmed', 'success' => [
            'title' => 'Booking Confirmed',
            'message' => 'A booking has been confirmed successfully. Please review the details below.',
            'information' => 'Please review the booking and prepare for the guest\'s arrival.',
            'icon' => 'check',
        ],
        'pending' => [
            'title' => 'New Pay on Arrival Booking',
            'message' => 'A new Pay on Arrival booking request has been received. Please review and confirm availability.',
            'information' => 'Payment is due when the guest arrives. This Cash booking can be confirmed while payment remains pending.',
            'icon' => 'calendar',
        ],
        'failed' => [
            'title' => 'Booking Payment Failed',
            'message' => 'A booking payment attempt has failed. Please review the booking details below.',
            'information' => 'The booking is not confirmed until payment is completed successfully.',
            'icon' => 'alert',
        ],
        'cancelled' => [
            'title' => 'Booking Cancelled',
            'message' => 'A booking has been cancelled. Please review the details below.',
            'information' => 'Update room availability and any related operational records if required.',
            'icon' => 'close',
        ],
        'expired' => [
            'title' => 'Booking Hold Expired',
            'message' => 'A pending booking hold has expired. Please review the details below.',
            'information' => 'The room may now be released unless another active booking prevents it.',
            'icon' => 'time',
        ],
        default => [
            'title' => 'Booking Notification',
            'message' => 'There is an update to a booking. Please review the details below.',
            'information' => 'Please review the booking and take any required action.',
            'icon' => 'calendar',
        ],
    };
}

function admin_booking_email_html(string $state, array $booking, array $payment = [], array $extraRows = [], string $viewUrl = ''): string
{
    $cfg = admin_booking_status_config($state);
    $brand = email_brand_name();
    $bookingRef = booking_reference($booking);
    $guestName = booking_guest_name($booking);
    $guestCount = (int) ($booking['guests'] ?? 0);
    $guests = $guestCount > 0 ? $guestCount . ($guestCount === 1 ? ' Guest' : ' Guests') : '-';
    $roomType = trim((string) ($booking['room_name'] ?? $booking['room_type'] ?? '-'));
    $amount = format_money_amount((float) ($payment['amount'] ?? $booking['amount'] ?? 0));
    $phone = email_contact_phone();
    $email = email_contact_email();
    $websiteUrl = email_public_url();
    $websiteLabel = email_contact_website();
    $year = date('Y');

    $formatDate = static function (mixed $value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }
        $timestamp = strtotime($value);
        return $timestamp ? date('d F Y', $timestamp) : $value;
    };

    $details = [
        'Booking ID' => $bookingRef,
        'Guest Name' => $guestName,
        'Check-in' => $formatDate($booking['check_in_date'] ?? ''),
        'Check-out' => $formatDate($booking['check_out_date'] ?? ''),
        'Guests' => $guests,
        'Room Type' => $roomType,
        'Total Amount' => $amount,
    ];

    foreach ($extraRows as $label => $value) {
        $label = trim((string) $label);
        if ($label !== '') {
            $details[$label] = trim((string) $value) !== '' ? (string) $value : '-';
        }
    }

    $rows = '';
    $lastIndex = count($details) - 1;
    $index = 0;
    foreach ($details as $label => $value) {
        $border = $index < $lastIndex ? 'border-bottom:1px solid #e4e8ee;' : '';
        $rows .= '<tr>
            <td class="ab-label" style="width:31%;padding:10px 18px;color:#071230;font-size:14px;line-height:1.4;' . $border . '">' . email_safe($label) . '</td>
            <td class="ab-colon" style="width:28px;padding:10px 4px;color:#071230;font-size:14px;line-height:1.4;text-align:center;' . $border . '">:</td>
            <td class="ab-value" style="padding:10px 18px;color:#071230;font-size:14px;line-height:1.4;' . $border . '">' . email_safe((string) $value) . '</td>
        </tr>';
        $index++;
    }

    if ($viewUrl === '') {
        $adminBase = defined('ADMIN_APP_URL') && trim((string) ADMIN_APP_URL) !== ''
            ? rtrim(trim((string) ADMIN_APP_URL), '/')
            : rtrim($websiteUrl, '/');
        $viewUrl = $adminBase !== '' ? $adminBase . '/bookings' : '';
    }

    $button = $viewUrl !== '' ? '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" class="ab-button-table" style="margin:20px auto 30px;"><tr><td align="center" style="border-radius:4px;background:#071c50;"><a href="' . email_safe($viewUrl) . '" class="ab-button" style="display:inline-block;min-width:230px;padding:13px 26px;border-radius:4px;background:#071c50;color:#ffffff;font-size:14px;line-height:1.25;font-weight:800;text-align:center;text-decoration:none;">View Booking in Dashboard</a></td></tr></table>' : '';

    return '<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . email_safe($cfg['title']) . ' - ' . email_safe($brand) . '</title>
<style>
body{margin:0!important;padding:0!important;background:#fff!important;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}table{border-spacing:0;mso-table-lspace:0;mso-table-rspace:0}img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}a{text-decoration:none}.ab-card{width:664px;max-width:664px}.ab-header{padding:24px 34px 18px}.ab-main{padding:22px 34px 0}.ab-icon-cell{width:74px;padding-right:20px;vertical-align:middle}.ab-contact td{white-space:nowrap}.ab-footer{padding:24px 20px}
@media only screen and (max-width:720px){.ab-card{width:100%!important;max-width:100%!important}.ab-outer{padding:18px 14px!important}.ab-header{padding:24px 28px 17px!important}.ab-main{padding:22px 28px 0!important}.ab-contact td{display:block!important;width:100%!important;padding:5px 0!important;white-space:normal!important}}
@media only screen and (max-width:520px){.ab-outer{padding:0!important}.ab-card{border-left:0!important;border-right:0!important}.ab-header{padding:21px 20px 16px!important}.ab-header-left{display:block!important;width:100%!important}.ab-header-right{display:none!important}.ab-brand{font-size:19px!important}.ab-tagline{font-size:13px!important;margin-top:6px!important}.ab-main{padding:17px 20px 0!important}.ab-icon-cell{width:46px!important;padding-right:13px!important;vertical-align:top!important}.ab-icon-circle{width:44px!important;height:44px!important}.ab-title{font-size:18px!important;line-height:1.25!important;margin-bottom:8px!important}.ab-copy{font-size:12.5px!important;line-height:1.55!important}.ab-details-title{font-size:15px!important;margin-top:23px!important;margin-bottom:12px!important}.ab-label{width:52%!important;padding:9px 12px!important;font-size:12.5px!important}.ab-colon{display:none!important;width:0!important;padding:0!important;font-size:0!important}.ab-value{width:48%!important;padding:9px 12px!important;font-size:12.5px!important}.ab-info-cell{padding:12px!important}.ab-info-icon{width:29px!important}.ab-info-title{font-size:14px!important}.ab-info-copy{font-size:13px!important;line-height:1.5!important}.ab-button-table{width:100%!important;margin:15px auto 23px!important}.ab-button-table td{width:100%!important}.ab-button{display:block!important;min-width:0!important;width:auto!important;padding:11px 14px!important;font-size:13px!important}.ab-help{padding:17px 0 18px!important}.ab-contact td{font-size:13px!important}.ab-footer{padding:18px 20px!important;font-size:12px!important;line-height:1.6!important}}
</style></head>
<body style="margin:0;padding:0;background:#fff;font-family:Arial,Helvetica,sans-serif;color:#071230;"><div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . email_safe($cfg['message']) . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="ab-outer" style="width:100%;background:#fff;padding:24px 12px;"><tr><td align="center">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="ab-card" style="width:664px;max-width:664px;background:#fff;border:1px solid #dfe3ea;box-shadow:0 8px 26px rgba(15,28,55,.06);">
<tr><td class="ab-header" style="padding:24px 34px 18px;background:#fff;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td class="ab-header-left" align="left"><div class="ab-brand" style="font-size:21px;line-height:1.25;font-weight:800;color:#071230;">' . email_safe($brand) . '</div><div class="ab-tagline" style="margin-top:7px;font-size:14px;line-height:1.4;color:#37415b;">A Clean and Comfortable Stay</div></td><td class="ab-header-right" align="right" style="font-size:12px;"><a href="' . email_safe($websiteUrl) . '" style="color:#034fbd;">View in browser</a></td></tr></table></td></tr>
<tr><td style="padding:0 34px;"><div style="height:1px;background:#dfe3ea;font-size:0;line-height:0;">&nbsp;</div></td></tr>
<tr><td class="ab-main" style="padding:22px 34px 0;background:#fff;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td class="ab-icon-cell" style="width:74px;padding-right:20px;vertical-align:middle;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" class="ab-icon-circle" style="width:68px;height:68px;border-radius:50%;background:#eef4ff;"><tr><td align="center" valign="middle">' . booking_email_icon((string) $cfg['icon'], 32) . '</td></tr></table></td><td><h1 class="ab-title" style="margin:0 0 12px;color:#071230;font-size:24px;line-height:1.2;font-weight:800;">' . email_safe($cfg['title']) . '</h1><p class="ab-copy" style="margin:0;color:#071230;font-size:14px;line-height:1.55;">' . email_safe($cfg['message']) . '</p></td></tr></table>
<h2 class="ab-details-title" style="margin:28px 0 14px;color:#071230;font-size:17px;line-height:1.3;font-weight:800;">Booking Details</h2><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border:1px solid #dfe3ea;border-radius:5px;border-collapse:separate;overflow:hidden;background:#fff;">' . $rows . '</table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:20px;border:1px solid #cfe0fb;border-radius:5px;background:#f3f7ff;"><tr><td class="ab-info-cell" style="padding:14px 16px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td class="ab-info-icon" style="width:36px;vertical-align:top;"><div style="width:24px;height:24px;border-radius:50%;background:#1765bf;color:#fff;font-size:15px;line-height:24px;text-align:center;font-weight:800;">i</div></td><td><div class="ab-info-title" style="color:#071230;font-size:14px;line-height:1.35;font-weight:800;">Important Information</div><div class="ab-info-copy" style="margin-top:5px;color:#071230;font-size:14px;line-height:1.5;">' . email_safe($cfg['information']) . '</div></td></tr></table></td></tr></table>' . $button . '
<div class="ab-help" style="padding:22px 0 24px;border-top:1px solid #dfe3ea;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" class="ab-contact" style="width:100%;"><tr><td style="padding-right:24px;color:#071230;font-size:13px;line-height:1.5;">' . booking_email_icon('phone', 17) . '&nbsp;&nbsp;' . email_safe($phone) . '</td><td style="padding-right:24px;color:#071230;font-size:13px;line-height:1.5;">' . booking_email_icon('mail', 17) . '&nbsp;&nbsp;' . email_safe($email) . '</td><td style="color:#071230;font-size:13px;line-height:1.5;">' . booking_email_icon('web', 17) . '&nbsp;&nbsp;<a href="' . email_safe($websiteUrl) . '" style="color:#071230;">' . email_safe($websiteLabel) . '</a></td></tr></table></div></td></tr>
<tr><td class="ab-footer" align="center" style="padding:24px 20px;background:#f6f7fa;border-top:1px solid #e3e6ec;color:#4a536b;font-size:13px;line-height:1.5;">&copy; ' . $year . ' ' . email_safe($brand) . '. All rights reserved.</td></tr></table></td></tr></table></body></html>';
}

/** Backward-compatible wrapper for older callers. */
function admin_new_booking_email_html(array $booking, string $viewUrl = ''): string
{
    return admin_booking_email_html('pending', $booking, [], [], $viewUrl);
}
