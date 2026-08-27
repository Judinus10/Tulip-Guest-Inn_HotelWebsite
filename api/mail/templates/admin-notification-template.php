<?php
/** Administrator stay reminder and operational notification email templates. */

declare(strict_types=1);

function reminder_email_format_date(string $date): string
{
    $date = trim($date);
    if ($date === '') {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d F Y', $ts) : $date;
}

function reminder_email_admin_dashboard_url(): string
{
    $base = defined('ADMIN_APP_URL') && trim((string) ADMIN_APP_URL) !== ''
        ? trim((string) ADMIN_APP_URL)
        : (defined('APP_BASE_URL') ? trim((string) APP_BASE_URL) : '');

    return $base !== '' ? rtrim($base, '/') : '';
}

function reminder_email_shell(string $title, string $content, string $preheader = '', string $sideTitle = 'Daily Room Report', string $sideSubTitle = ''): string
{
    $brand = email_brand_name();
    $year = date('Y');
    $websiteUrl = email_public_url();

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . email_safe($title) . ' - ' . email_safe($brand) . '</title>
<style>
body{margin:0!important;padding:0!important;background:#ffffff!important;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}
table{border-spacing:0;mso-table-lspace:0pt;mso-table-rspace:0pt;}
img{border:0;display:inline-block;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;}
a{text-decoration:none;}
.daily-card{width:624px;max-width:624px;}
.daily-header{padding:22px 26px 16px;}
.daily-main{padding:18px 26px 0;}
.daily-help-items td{white-space:nowrap;}
.daily-footer{padding:22px 20px;}
.daily-mobile-time{display:none;}

@media only screen and (max-width:720px){
  .daily-card{width:100%!important;max-width:100%!important;}
  .daily-outer{padding:18px 12px!important;}
  .daily-header{padding:22px 24px 16px!important;}
  .daily-main{padding:18px 24px 0!important;}
  .daily-room-number{display:none!important;}
  .daily-help-items td{display:block!important;width:100%!important;padding:5px 0!important;white-space:normal!important;}
  .daily-summary-copy{font-size:12px!important;}
}

@media only screen and (max-width:520px){
  .daily-outer{padding:0!important;}
  .daily-card{border-left:0!important;border-right:0!important;}
  .daily-header{padding:20px 18px 15px!important;text-align:left!important;}
  .daily-header-left{display:block!important;width:100%!important;text-align:left!important;}
  .daily-header-right{display:none!important;}
  .daily-brand{font-size:19px!important;}
  .daily-tagline{font-size:12px!important;margin-top:6px!important;}
  .daily-main{padding:15px 12px 0!important;}
  .daily-hero-icon-cell{width:48px!important;padding-right:12px!important;vertical-align:top!important;}
  .daily-hero-circle{width:46px!important;height:46px!important;}
  .daily-title{font-size:17px!important;line-height:1.25!important;margin-bottom:4px!important;}
  .daily-date{font-size:13px!important;}
  .daily-intro{font-size:12px!important;line-height:1.55!important;margin-top:8px!important;}
  .daily-section-title{font-size:13px!important;margin:20px 0 9px!important;}
  .daily-table th{padding:8px 6px!important;font-size:9px!important;line-height:1.2!important;}
  .daily-table td{padding:9px 6px!important;font-size:9px!important;line-height:1.4!important;}
  .daily-desktop-time{display:none!important;}
  .daily-mobile-time{display:inline!important;}
  .daily-desktop-label{display:none!important;}
  .daily-mobile-label{display:inline!important;}
  .daily-total-cell{padding:10px 12px!important;font-size:12px!important;}
  .daily-summary-cell{padding:11px 12px!important;}
  .daily-summary-title{font-size:12px!important;}
  .daily-summary-copy{font-size:9px!important;line-height:1.7!important;white-space:nowrap!important;}
  .daily-help{padding:16px 0 18px!important;}
  .daily-help-title{font-size:13px!important;}
  .daily-help-items td{font-size:12px!important;}
  .daily-footer{padding:18px 20px!important;font-size:12px!important;line-height:1.6!important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#071230;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . email_safe($preheader) . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="daily-outer" style="width:100%;background:#ffffff;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="daily-card" style="width:624px;max-width:624px;background:#ffffff;border:1px solid #dfe3ea;box-shadow:0 8px 26px rgba(15,28,55,.06);">
<tr>
<td class="daily-header" style="padding:22px 26px 16px;background:#ffffff;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td class="daily-header-left" align="left">
<div class="daily-brand" style="font-size:20px;line-height:1.25;font-weight:800;color:#071230;">' . email_safe($brand) . '</div>
<div class="daily-tagline" style="margin-top:7px;font-size:14px;line-height:1.4;color:#37415b;">A Clean and Comfortable Stay</div>
</td>
<td class="daily-header-right" align="right" style="font-size:12px;"><a href="' . email_safe($websiteUrl) . '" style="color:#034fbd;">View in browser</a></td>
</tr></table>
</td>
</tr>
<tr><td style="padding:0 26px;"><div style="height:1px;background:#dfe3ea;font-size:0;line-height:0;">&nbsp;</div></td></tr>
<tr><td class="daily-main" style="padding:18px 26px 0;background:#ffffff;">' . $content . '
<div class="daily-help" style="margin-top:24px;padding:22px 0 24px;border-top:1px solid #dfe3ea;">
<div class="daily-help-title" style="margin:0 0 14px;color:#071230;font-size:13px;line-height:1.35;font-weight:800;">Need help?</div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="daily-help-items" style="width:100%;"><tr>
<td style="padding-right:24px;color:#071230;font-size:12px;line-height:1.5;">' . booking_email_icon('phone', 16) . '&nbsp;&nbsp;' . email_safe(email_contact_phone()) . '</td>
<td style="padding-right:24px;color:#071230;font-size:12px;line-height:1.5;">' . booking_email_icon('mail', 16) . '&nbsp;&nbsp;' . email_safe(email_contact_email()) . '</td>
<td style="color:#071230;font-size:12px;line-height:1.5;">' . booking_email_icon('web', 16) . '&nbsp;&nbsp;<a href="' . email_safe($websiteUrl) . '" style="color:#071230;">' . email_safe(email_contact_website()) . '</a></td>
</tr></table>
</div>
</td></tr>
<tr><td class="daily-footer" align="center" style="padding:22px 20px;background:#f6f7fa;border-top:1px solid #e3e6ec;color:#4a536b;font-size:12px;line-height:1.5;">&copy; ' . $year . ' ' . email_safe($brand) . '. All rights reserved.</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
}

function reminder_email_help_block(): string
{
    return '';
}

function reminder_email_summary_panel(string $reminderDate, string $forDate, int $total, string $label): string
{
    return '';
}

function reminder_email_value(array $booking, array $keys, string $fallback = '-'): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $booking)) {
            $value = trim((string) $booking[$key]);
            if ($value !== '') {
                return $value;
            }
        }
    }

    return $fallback;
}

function reminder_email_datetime(array $booking, string $type): string
{
    $keys = $type === 'check-out'
        ? ['check_out_datetime', 'check_out_time', 'checkout_time', 'check_out_date']
        : ['check_in_datetime', 'check_in_time', 'checkin_time', 'check_in_date'];

    $value = reminder_email_value($booking, $keys, '-');
    if ($value === '-') {
        return $value;
    }

    $timestamp = strtotime($value);
    if (!$timestamp) {
        return $value;
    }

    $hasTime = preg_match('/\d{1,2}:\d{2}/', $value) === 1;
    return $hasTime ? date('d M Y, h:i A', $timestamp) : date('d M Y', $timestamp);
}

function reminder_email_mobile_datetime(array $booking, string $type): string
{
    $value = reminder_email_datetime($booking, $type);
    $timestamp = strtotime($value);
    if (!$timestamp) {
        return $value;
    }

    return date('d M,', $timestamp) . '<br>' . date('h:i A', $timestamp);
}

function reminder_email_nights(array $booking): string
{
    $explicit = reminder_email_value($booking, ['nights', 'number_of_nights', 'stay_nights'], '');
    if ($explicit !== '') {
        return $explicit;
    }

    $checkIn = reminder_email_value($booking, ['check_in_date'], '');
    $checkOut = reminder_email_value($booking, ['check_out_date'], '');
    if ($checkIn !== '' && $checkOut !== '') {
        $start = strtotime($checkIn);
        $end = strtotime($checkOut);
        if ($start && $end && $end >= $start) {
            return (string) max(1, (int) round(($end - $start) / 86400));
        }
    }

    return '-';
}

function reminder_email_bookings_section(string $title, array $bookings, string $emptyText): string
{
    $isCheckout = stripos($title, 'out') !== false;
    $timeType = $isCheckout ? 'check-out' : 'check-in';
    $rows = '';

    foreach ($bookings as $booking) {
        $guest = reminder_email_value($booking, ['guest_name', 'full_name', 'staying_guest_name'], 'Guest');
        $roomType = reminder_email_value($booking, ['room_name', 'room_type'], '-');
        $roomNumber = reminder_email_value($booking, ['room_number', 'room_no', 'room_id'], '-');
        $desktopTime = reminder_email_datetime($booking, $timeType);
        $mobileTime = reminder_email_mobile_datetime($booking, $timeType);
        $nights = reminder_email_nights($booking);

        $rows .= '<tr>
            <td style="padding:10px 12px;border-bottom:1px solid #e4e8ee;color:#071230;font-size:12px;line-height:1.45;">' . email_safe($guest) . '</td>
            <td style="padding:10px 12px;border-bottom:1px solid #e4e8ee;color:#071230;font-size:12px;line-height:1.45;">' . email_safe($roomType) . '</td>
            <td class="daily-room-number" style="padding:10px 12px;border-bottom:1px solid #e4e8ee;color:#071230;font-size:12px;line-height:1.45;">' . email_safe($roomNumber) . '</td>
            <td style="padding:10px 12px;border-bottom:1px solid #e4e8ee;color:#071230;font-size:12px;line-height:1.45;"><span class="daily-desktop-time">' . email_safe($desktopTime) . '</span><span class="daily-mobile-time">' . $mobileTime . '</span></td>'
            . (!$isCheckout ? '<td style="padding:10px 12px;border-bottom:1px solid #e4e8ee;color:#071230;font-size:12px;line-height:1.45;text-align:center;">' . email_safe($nights) . '</td>' : '') .
        '</tr>';
    }

    $columns = $isCheckout ? 4 : 5;
    if ($rows === '') {
        $rows = '<tr><td colspan="' . $columns . '" style="padding:14px;color:#667085;font-size:12px;">' . email_safe($emptyText) . '</td></tr>';
    }

    return '<h2 class="daily-section-title" style="margin:26px 0 12px;color:#071230;font-size:15px;line-height:1.3;font-weight:800;">' . email_safe($title) . '</h2>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="daily-table" style="width:100%;border:1px solid #dbe2ec;border-radius:5px;border-collapse:separate;overflow:hidden;background:#ffffff;">
      <tr style="background:#edf4fd;">
        <th align="left" style="padding:10px 12px;color:#071230;font-size:11px;line-height:1.3;font-weight:800;">Guest Name</th>
        <th align="left" style="padding:10px 12px;color:#071230;font-size:11px;line-height:1.3;font-weight:800;">Room Type</th>
        <th align="left" class="daily-room-number" style="padding:10px 12px;color:#071230;font-size:11px;line-height:1.3;font-weight:800;">Room No.</th>
        <th align="left" style="padding:10px 12px;color:#071230;font-size:11px;line-height:1.3;font-weight:800;"><span class="daily-desktop-label">' . ($isCheckout ? 'Check-out Time' : 'Check-in Time') . '</span><span class="daily-mobile-label">Time</span></th>'
        . (!$isCheckout ? '<th align="center" style="padding:10px 12px;color:#071230;font-size:11px;line-height:1.3;font-weight:800;">Nights</th>' : '') .
      '</tr>' . $rows . '
    </table>';
}

function reminder_email_total_bar(string $label, int $count, string $tone): string
{
    $isGreen = $tone === 'green';
    $background = $isGreen ? '#f3fbf4' : '#fff9ef';
    $border = $isGreen ? '#d4ecd8' : '#f2dfb7';
    $color = $isGreen ? '#157a2e' : '#a66500';
    $icon = $isGreen ? 'user' : 'open';

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:14px;border:1px solid ' . $border . ';border-radius:5px;background:' . $background . ';">
      <tr><td class="daily-total-cell" style="padding:11px 16px;color:' . $color . ';font-size:13px;line-height:1.4;">'
      . booking_email_icon($icon, 20) . '&nbsp;&nbsp;' . email_safe($label) . ': <strong>' . $count . '</strong></td></tr>
    </table>';
}

function reminder_email_action_block(): string
{
    return '';
}

function stay_reminder_email_html(string $date, array $checkIns, array $checkOuts): string
{
    $checkInCount = count($checkIns);
    $checkOutCount = count($checkOuts);

    $dateTimestamp = strtotime($date);
    $displayDate = $dateTimestamp
        ? date('d M Y', $dateTimestamp) . ' (' . date('l', $dateTimestamp) . ')'
        : $date;

    $occupiedRooms = [];
    foreach (array_merge($checkIns, $checkOuts) as $booking) {
        $roomKey = reminder_email_value($booking, ['room_number', 'room_no', 'room_id', 'room_name'], '');
        if ($roomKey !== '') {
            $occupiedRooms[$roomKey] = true;
        }
    }
    $occupiedCount = count($occupiedRooms);

    $content = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
      <td class="daily-hero-icon-cell" style="width:72px;padding-right:20px;vertical-align:middle;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="daily-hero-circle" style="width:62px;height:62px;border-radius:50%;background:#eef4ff;">
          <tr><td align="center" valign="middle">' . booking_email_icon('calendar', 30) . '</td></tr>
        </table>
      </td>
      <td style="vertical-align:middle;">
        <h1 class="daily-title" style="margin:0 0 5px;color:#071230;font-size:20px;line-height:1.25;font-weight:800;">Daily Room Report</h1>
        <div class="daily-date" style="color:#0759b8;font-size:14px;line-height:1.4;font-weight:800;">' . email_safe($displayDate) . '</div>
        <p class="daily-intro" style="margin:8px 0 0;color:#071230;font-size:13px;line-height:1.55;">Here is your daily summary of check-in and check-out for today.</p>
      </td>
    </tr></table>';

    $content .= reminder_email_bookings_section('Today\'s Check-ins', $checkIns, 'No check-ins scheduled for today.');
    $content .= reminder_email_total_bar('Total Check-ins Today', $checkInCount, 'green');
    $content .= reminder_email_bookings_section('Today\'s Check-outs', $checkOuts, 'No check-outs scheduled for today.');
    $content .= reminder_email_total_bar('Total Check-outs Today', $checkOutCount, 'orange');

    $content .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:18px;border:1px solid #cfe0fb;border-radius:5px;background:#f3f7ff;">
      <tr><td class="daily-summary-cell" style="padding:13px 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="width:32px;vertical-align:top;"><div style="width:20px;height:20px;border-radius:50%;background:#1765bf;color:#ffffff;font-size:13px;line-height:20px;text-align:center;font-weight:800;">i</div></td>
          <td>
            <div class="daily-summary-title" style="color:#071230;font-size:13px;line-height:1.35;font-weight:800;">Summary</div>
            <div class="daily-summary-copy" style="margin-top:8px;color:#071230;font-size:12px;line-height:1.6;">
              Check-ins: <span style="display:inline-block;margin:0 10px 0 5px;padding:2px 8px;border-radius:6px;background:#e4efff;color:#0759b8;font-weight:800;">' . $checkInCount . '</span>
              <span style="color:#a7afbd;">|</span>
              Check-outs: <span style="display:inline-block;margin:0 10px 0 5px;padding:2px 8px;border-radius:6px;background:#fff1d8;color:#a66500;font-weight:800;">' . $checkOutCount . '</span>
              <span style="color:#a7afbd;">|</span>
              Occupied: <span style="display:inline-block;margin-left:5px;padding:2px 8px;border-radius:6px;background:#e2f4e5;color:#157a2e;font-weight:800;">' . $occupiedCount . '</span>
            </div>
          </td>
        </tr></table>
      </td></tr>
    </table>';

    return reminder_email_shell(
        'Daily Room Report',
        $content,
        'Daily check-in and check-out summary for ' . $displayDate . '.',
        'Daily Room Report',
        $displayDate
    );
}

