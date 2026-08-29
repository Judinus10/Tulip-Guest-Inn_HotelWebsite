<?php
/** Customer booking email template and shared booking rendering helpers. */

declare(strict_types=1);

function booking_email_icon(string $icon, int $size = 24): string
{
    $validIcons = array_keys(email_icon_file_map());
    $name = in_array($icon, $validIcons, true) ? $icon : 'info';
    $size = max(14, min(36, $size));

    /*
     * Use real high-resolution PNG files embedded with CID.
     * This is more reliable in Outlook than SVG, icon fonts, emoji,
     * CSS masks, or text-based symbols.
     */
    return '<img src="cid:jebal-email-icon-' . email_safe($name) . '"'
        . ' width="' . $size . '" height="' . $size . '" alt=""'
        . ' style="display:inline-block;width:' . $size . 'px;height:' . $size . 'px;'
        . 'max-width:' . $size . 'px;max-height:' . $size . 'px;'
        . 'border:0;outline:none;text-decoration:none;'
        . 'vertical-align:middle;line-height:' . $size . 'px;-ms-interpolation-mode:bicubic;">';
}

function booking_email_status_config(string $status): array
{
    $key = strtolower(trim($status));

    $map = [
        'confirmed' => ['Booking Confirmed!', 'Your booking has been confirmed. Payment can be made when you arrive.', 'Payment: Pay on Arrival', 'check', '#0f7a24', '#e9f9ea'],
        'paid' => ['Booking Confirmed!', 'Your booking and payment were successful. We look forward to welcoming you.', 'Payment Status: Paid', 'check', '#0f7a24', '#e9f9ea'],
        'pending' => ['Booking Received', 'We received your booking details. Your booking is waiting for payment confirmation.', 'Payment Status: Pending', 'calendar', '#987b58', '#ffffff'],
        'received' => ['Booking Request Received', 'Your booking request is awaiting confirmation from the property. Payment can be made when you arrive.', 'Payment: Pay on Arrival', 'calendar', '#987b58', '#ffffff'],
        'failed' => ['Payment Failed', 'Your payment could not be completed. You can retry payment if the room is still available.', 'Payment Status: Failed', 'close', '#b42318', '#fff1f1'],
        'expired' => ['Booking Hold Expired', 'Your booking hold expired because payment was not completed within the allowed time.', 'Booking Status: Expired', 'alert', '#b42318', '#fff1f1'],
        'cancelled' => ['Booking Cancelled', 'Your booking has been cancelled. Contact us if this was unexpected.', 'Booking Status: Cancelled', 'close', '#b42318', '#fff1f1'],
        'updated' => ['Booking Updated', 'Your booking details have been updated.', 'Booking Status: Updated', 'info', '#987b58', '#ffffff'],
        'admin' => ['Booking Notification', 'A booking update was received from the website.', 'Hotel Notification', 'info', '#987b58', '#ffffff'],
    ];

    return $map[$key] ?? $map['updated'];
}

function booking_email_company_block(): string
{
    return '<div style="background:#ffffff;border-top:1px solid #eadfd2;border-bottom:1px solid #eadfd2;padding:22px 34px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            <tr>
                <td style="width:43%;vertical-align:middle;padding:0 24px 0 0;">
                    <table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                        <tr>
                            <td style="width:58px;height:58px;border-radius:50%;background:#f7f4ef;text-align:center;vertical-align:middle;color:#987b58;font-size:28px;font-weight:700;">' . booking_email_icon('headset', 24) . '</td>
                            <td style="padding-left:18px;">
                                <div style="font-size:18px;font-weight:800;color:#111;line-height:1.2;">Need help?</div>
                                <div style="font-size:14px;color:#111;margin-top:4px;">We\'re here for you.</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width:1px;background:#d7c8b9;"></td>
                <td style="vertical-align:middle;padding-left:34px;color:#333;font-size:14px;line-height:1.8;">
                    <div><span style="color:#987b58;width:24px;display:inline-block;">' . booking_email_icon('mail') . '</span> ' . email_safe(email_contact_email()) . '</div>
                    <div><span style="color:#987b58;width:24px;display:inline-block;">' . booking_email_icon('phone') . '</span> ' . email_safe(email_contact_phone()) . '</div>
                    <div><span style="color:#987b58;width:24px;display:inline-block;">' . booking_email_icon('web') . '</span> ' . email_safe(email_contact_website()) . '</div>
                    <div style="margin-top:5px;line-height:1.5;"><span style="color:#987b58;width:24px;display:inline-block;vertical-align:top;">' . booking_email_icon('location') . '</span> <span style="display:inline-block;">' . nl2br(email_safe(email_contact_address())) . '</span></div>
                </td>
            </tr>
        </table>
    </div>';
}

function booking_email_shell(string $content, string $preheader = ''): string
{
    $brand = email_safe(email_brand_name());
    $year = date('Y');
    $date = date('d F Y');
    $time = date('h:i A');

    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $brand . '</title>' . email_icon_font_css() . '</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#111;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . email_safe($preheader) . '</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#ffffff;padding:24px 0;"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="760" cellspacing="0" cellpadding="0" style="width:760px;max-width:100%;border-collapse:collapse;background:#ffffff;border-radius:6px;box-shadow:0 14px 38px rgba(20,20,20,.08);overflow:hidden;">
<tr><td style="padding:28px 34px 20px;border-bottom:2px solid #987b58;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>
        <td align="left" style="text-align:left;">
            <div style="font-family:Arial,Helvetica,sans-serif;font-size:30px;letter-spacing:7px;color:#987b58;font-weight:700;line-height:1;">TULIP</div>
            <div style="font-size:14px;letter-spacing:5px;color:#987b58;font-weight:700;margin-top:6px;">GUEST HOUSE</div>
            <div style="font-size:14px;color:#987b58;margin-top:10px;"><span style="display:inline-block;width:58px;border-top:1px solid #c9a77d;vertical-align:middle;margin-right:12px;"></span>Comfortable Guest House<span style="display:inline-block;width:58px;border-top:1px solid #c9a77d;vertical-align:middle;margin-left:12px;"></span></div>
        </td>
        <td align="right" style="width:150px;color:#333;font-size:13px;line-height:1.45;vertical-align:top;">' . booking_email_icon('calendar') . ' &nbsp;' . email_safe($date) . '<br><span style="padding-left:28px;">' . email_safe($time) . '</span></td>
    </tr></table>
</td></tr>
<tr><td style="padding:34px 58px 26px;">' . $content . '</td></tr>
<tr><td>' . booking_email_company_block() . '</td></tr>
<tr><td style="padding:20px 30px 24px;text-align:center;border-top:1px solid #eee;color:#111;font-size:14px;line-height:1.5;">
    <div>Thank you for choosing Tulip Guest Inn.</div>
    <div style="margin:10px auto;color:#987b58;"><span style="display:inline-block;width:34px;border-top:1px solid #c9a77d;vertical-align:middle;margin-right:10px;"></span><span style="display:inline-block;width:34px;border-top:1px solid #c9a77d;vertical-align:middle;margin-left:10px;"></span></div>
    <div style="color:#333;">&copy; ' . $year . ' Tulip Guest Inn. All rights reserved.</div>
</td></tr>
</table>
</td></tr></table>
</body></html>';
}

function booking_email_reference_panel(array $booking): string
{
    $ref = booking_reference($booking);
    $invoice = booking_invoice_number($booking);

    if ($invoice === '') {
        $invoice = '-';
    }

    return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:22px 0 16px;border:1px solid #eadfd2;border-radius:8px;background:#ffffff;overflow:hidden;">
        <tr>
            <td align="center" style="width:50%;padding:18px 12px;color:#666;font-size:13px;">Booking Reference<br><strong style="display:block;margin-top:8px;color:#987b58;font-size:18px;letter-spacing:.3px;">' . email_safe($ref) . '</strong></td>
            <td style="width:1px;background:#d7c8b9;"></td>
            <td align="center" style="width:50%;padding:18px 12px;color:#666;font-size:13px;">Invoice Number<br><strong style="display:block;margin-top:8px;color:#987b58;font-size:18px;letter-spacing:.3px;">' . email_safe($invoice) . '</strong></td>
        </tr>
    </table>';
}

function booking_email_info_box(string $title, string $icon, array $rows, string $highlight = ''): string
{
    $body = '';
    foreach ($rows as $label => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $body .= '<tr><td style="padding:8px 0;color:#444;font-size:14px;">' . email_safe((string) $label) . '</td><td align="right" style="padding:8px 0;color:#111;font-size:14px;font-weight:700;">' . email_safe((string) $value) . '</td></tr>';
    }

    return '<td width="50%" style="width:50%;vertical-align:top;padding:0 10px 14px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #eadfd2;border-radius:8px;background:#fff;overflow:hidden;">
            <tr><td style="padding:20px 20px 8px;">
                <table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="width:38px;height:38px;border-radius:50%;background:#f7f4ef;text-align:center;color:#987b58;font-size:20px;">' . booking_email_icon($icon) . '</td><td style="padding-left:12px;font-size:18px;font-weight:800;color:#111;">' . email_safe($title) . '</td></tr></table>
                ' . ($highlight !== '' ? '<div style="margin-top:18px;font-size:20px;font-weight:800;color:#987b58;">' . email_safe($highlight) . '</div>' : '') . '
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin-top:12px;border-top:1px solid #eadfd2;">' . $body . '</table>
            </td></tr>
        </table>
    </td>';
}

function customer_booking_email_html(array $booking, string $state = 'confirmed', array $payment = [], string $viewUrl = ''): string
{
    $brand = email_brand_name();
    $guestName = trim((string) ($booking['full_name'] ?? $booking['guest_name'] ?? 'Guest'));
    $bookingRef = booking_reference($booking);
    $guestCount = (int) ($booking['guests'] ?? 0);
    $guests = $guestCount > 0 ? $guestCount . ($guestCount === 1 ? ' Guest' : ' Guests') : '-';
    $roomType = trim((string) ($booking['room_name'] ?? $booking['room_type'] ?? '-'));
    $amount = format_money_amount((float) ($payment['amount'] ?? $booking['amount'] ?? 0));
    $paymentStatus = status_label_for_email((string) ($payment['status'] ?? $booking['payment_status'] ?? ''));
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

    $checkIn = $formatDate($booking['check_in_date'] ?? '');
    $checkOut = $formatDate($booking['check_out_date'] ?? '');

    if ($viewUrl === '') {
        $viewUrl = $websiteUrl;
    }

    $stateKey = strtolower(trim($state));
    $copyByState = [
        'confirmed' => [
            'subject' => 'Booking Confirmed',
            'preheader' => 'Your booking has been confirmed. We look forward to welcoming you.',
            'line1' => 'Thank you for choosing Tulip Guest Inn.',
            'line2' => 'Your booking has been confirmed. We look forward to welcoming you!',
            'info1' => 'You can modify or cancel your booking up to 24 hours before check-in.',
            'info2' => 'If you have any questions, feel free to contact us.',
        ],
        'paid' => [
            'subject' => 'Booking Confirmed',
            'preheader' => 'Your booking and payment have been confirmed.',
            'line1' => 'Thank you for choosing Tulip Guest Inn.',
            'line2' => 'Your booking and payment have been confirmed. We look forward to welcoming you!',
            'info1' => 'You can modify or cancel your booking up to 24 hours before check-in.',
            'info2' => 'If you have any questions, feel free to contact us.',
        ],
        'pending' => [
            'subject' => 'Booking Pending',
            'preheader' => 'Your booking is waiting for payment confirmation.',
            'line1' => 'Thank you for choosing Tulip Guest Inn.',
            'line2' => 'We received your booking. It is currently waiting for payment confirmation.',
            'info1' => 'Your room is not fully confirmed until the payment status is updated.',
            'info2' => 'If you have already paid or need help, please contact us.',
        ],
        'received' => [
            'subject' => 'Booking Request Received',
            'preheader' => 'Your booking request is awaiting property confirmation.',
            'line1' => 'Thank you for choosing Tulip Guest Inn.',
            'line2' => 'Your booking request has been received and is awaiting confirmation from the property.',
            'info1' => 'Payment can be made when you arrive. No online payment is required for this request.',
            'info2' => 'Please keep your booking ID for future reference.',
        ],
        'failed' => [
            'subject' => 'Payment Failed',
            'preheader' => 'Your payment could not be completed.',
            'line1' => 'We could not complete the payment for your booking.',
            'line2' => 'You can try again if the selected room is still available.',
            'info1' => 'A failed payment does not confirm or reserve the booking.',
            'info2' => 'Please contact us if money was deducted from your account.',
        ],
        'expired' => [
            'subject' => 'Booking Expired',
            'preheader' => 'Your booking hold has expired.',
            'line1' => 'Your booking hold has expired.',
            'line2' => 'The payment was not completed within the allowed time.',
            'info1' => 'You may create a new booking if the room is still available.',
            'info2' => 'Contact us if you need assistance.',
        ],
        'cancelled' => [
            'subject' => 'Booking Cancelled',
            'preheader' => 'Your booking has been cancelled.',
            'line1' => 'Your booking has been cancelled.',
            'line2' => 'Please contact us if this cancellation was unexpected.',
            'info1' => 'Any eligible refund will follow the applicable booking and payment terms.',
            'info2' => 'If you have any questions, feel free to contact us.',
        ],
        'updated' => [
            'subject' => 'Booking Updated',
            'preheader' => 'Your booking details have been updated.',
            'line1' => 'Your booking details have been updated.',
            'line2' => 'Please review the latest booking information below.',
            'info1' => 'Keep your booking ID for future reference.',
            'info2' => 'If any detail is incorrect, please contact us.',
        ],
    ];
    $copy = $copyByState[$stateKey] ?? $copyByState['updated'];

    $details = [
        'Booking ID' => $bookingRef,
        'Check-in' => $checkIn,
        'Check-out' => $checkOut,
        'Guests' => $guests,
        'Room Type' => $roomType,
        'Total Amount' => $amount,
        'Payment Status' => $paymentStatus,
    ];

    $rows = '';
    $lastIndex = count($details) - 1;
    $index = 0;
    foreach ($details as $label => $value) {
        $border = $index < $lastIndex ? 'border-bottom:1px solid #e4e8ee;' : '';
        $rows .= '<tr class="booking-customer-row">
            <td class="booking-customer-label" style="width:31%;padding:12px 18px;color:#071230;font-size:14px;line-height:1.4;' . $border . '">' . email_safe($label) . '</td>
            <td class="booking-customer-colon" style="width:28px;padding:12px 4px;color:#071230;font-size:14px;line-height:1.4;text-align:center;' . $border . '">:</td>
            <td class="booking-customer-value" style="padding:12px 18px;color:#071230;font-size:14px;line-height:1.4;' . $border . '">' . email_safe((string) $value) . '</td>
        </tr>';
        $index++;
    }

    // $button = $viewUrl !== ''
    //     ? '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" class="booking-customer-button-table" style="margin:24px auto 30px;">
    //         <tr><td align="center" style="border-radius:4px;background:#071c50;">
    //             <a href="' . email_safe($viewUrl) . '" class="booking-customer-button" style="display:inline-block;min-width:190px;padding:13px 26px;border-radius:4px;background:#071c50;color:#ffffff;font-size:14px;line-height:1.25;font-weight:800;text-align:center;text-decoration:none;">View Booking</a>
    //         </td></tr>
    //     </table>'
    //     : '';

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . email_safe($copy['subject']) . ' - ' . email_safe($brand) . '</title>
<style>
body{margin:0!important;padding:0!important;background:#ffffff!important;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}
table{border-spacing:0;mso-table-lspace:0pt;mso-table-rspace:0pt;}
img{border:0;display:inline-block;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;}
a{text-decoration:none;}
.booking-customer-card{width:648px;max-width:648px;}
.booking-customer-header{padding:24px 38px 18px;}
.booking-customer-main{padding:25px 38px 0;}
.booking-customer-help-items td{white-space:nowrap;}
.booking-customer-footer{padding:25px 20px;}

@media only screen and (max-width:720px){
  .booking-customer-card{width:100%!important;max-width:100%!important;}
  .booking-customer-outer{padding:18px 14px!important;}
  .booking-customer-header{padding:24px 28px 17px!important;}
  .booking-customer-main{padding:22px 28px 0!important;}
  .booking-customer-help-items td{display:block!important;width:100%!important;padding:5px 0!important;white-space:normal!important;}
}
@media only screen and (max-width:520px){
  .booking-customer-outer{padding:0!important;}
  .booking-customer-card{border-left:0!important;border-right:0!important;}
  .booking-customer-header{padding:22px 20px 17px!important;text-align:left!important;}
  .booking-customer-header-left{display:block!important;width:100%!important;text-align:left!important;}
  .booking-customer-header-right{display:none!important;}
  .booking-customer-brand{font-size:20px!important;}
  .booking-customer-tagline{font-size:13px!important;margin-top:8px!important;}
  .booking-customer-main{padding:21px 20px 0!important;}
  .booking-customer-greeting{font-size:16px!important;}
  .booking-customer-copy{font-size:13px!important;line-height:1.5!important;}
  .booking-customer-title{font-size:16px!important;margin-top:22px!important;margin-bottom:12px!important;}
  .booking-customer-label{width:52%!important;padding:10px 12px!important;font-size:13px!important;}
  .booking-customer-colon{display:none!important;width:0!important;padding:0!important;font-size:0!important;}
  .booking-customer-value{width:48%!important;padding:10px 12px!important;font-size:13px!important;text-align:left!important;}
  .booking-customer-info-cell{padding:12px 10px!important;}
  .booking-customer-info-icon{width:29px!important;vertical-align:top!important;}
  .booking-customer-info-title{font-size:14px!important;}
  .booking-customer-info-copy{font-size:13px!important;line-height:1.5!important;}
  .booking-customer-button-table{width:100%!important;margin:10px auto 20px!important;}
  .booking-customer-button-table td{width:100%!important;}
  .booking-customer-button{display:block!important;min-width:0!important;width:auto!important;padding:11px 14px!important;font-size:13px!important;}
  .booking-customer-help{padding:16px 0 20px!important;}
  .booking-customer-help-title{font-size:14px!important;}
  .booking-customer-help-items td{font-size:13px!important;}
  .booking-customer-footer{padding:18px 20px!important;font-size:12px!important;line-height:1.6!important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#071230;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . email_safe($copy['preheader']) . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="booking-customer-outer" style="width:100%;background:#ffffff;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="booking-customer-card" style="width:648px;max-width:648px;background:#ffffff;border:1px solid #dfe3ea;box-shadow:0 8px 26px rgba(15,28,55,.06);">
<tr><td class="booking-customer-header" style="padding:24px 38px 18px;background:#ffffff;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td class="booking-customer-header-left" align="left">
<div class="booking-customer-brand" style="font-size:21px;line-height:1.25;font-weight:800;color:#071230;">' . email_safe($brand) . '</div>
<div class="booking-customer-tagline" style="margin-top:7px;font-size:14px;line-height:1.4;color:#37415b;">A Clean and Comfortable Stay</div>
</td>
<td class="booking-customer-header-right" align="right" style="font-size:12px;"></td>
</tr></table>
</td></tr>
<tr><td style="padding:0 38px;"><div style="height:1px;background:#dfe3ea;font-size:0;line-height:0;">&nbsp;</div></td></tr>
<tr><td class="booking-customer-main" style="padding:25px 38px 0;background:#ffffff;">
<h1 class="booking-customer-greeting" style="margin:0 0 18px;color:#071230;font-size:17px;line-height:1.35;font-weight:800;">Hi ' . email_safe($guestName) . ',</h1>
<p class="booking-customer-copy" style="margin:0;color:#071230;font-size:14px;line-height:1.55;">' . email_safe($copy['line1']) . '<br>' . email_safe($copy['line2']) . '</p>
<h2 class="booking-customer-title" style="margin:26px 0 14px;color:#071230;font-size:17px;line-height:1.3;font-weight:800;">Booking Details</h2>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border:1px solid #dfe3ea;border-radius:5px;border-collapse:separate;overflow:hidden;background:#ffffff;">' . $rows . '</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:20px;border:1px solid #cfe0fb;border-radius:5px;background:#f3f7ff;">
<tr><td class="booking-customer-info-cell" style="padding:14px 16px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td class="booking-customer-info-icon" style="width:36px;vertical-align:top;"><div style="width:24px;height:24px;border-radius:50%;background:#1765bf;color:#ffffff;font-size:15px;line-height:24px;text-align:center;font-weight:800;">i</div></td>
<td>
<div class="booking-customer-info-title" style="color:#071230;font-size:14px;line-height:1.35;font-weight:800;">Important Information</div>
<div class="booking-customer-info-copy" style="margin-top:5px;color:#071230;font-size:14px;line-height:1.55;">' . email_safe($copy['info1']) . '<br>' . email_safe($copy['info2']) . '</div>
</td>
</tr></table>
</td></tr>
</table>
' . $button . '
<div class="booking-customer-help" style="padding:22px 0 24px;border-top:1px solid #dfe3ea;">
<div class="booking-customer-help-title" style="margin:0 0 14px;color:#071230;font-size:14px;line-height:1.35;font-weight:800;">Need help?</div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="booking-customer-help-items" style="width:100%;"><tr>
<td style="padding-right:24px;color:#071230;font-size:13px;line-height:1.5;">' . booking_email_icon('phone', 17) . '&nbsp;&nbsp;' . email_safe($phone) . '</td>
<td style="padding-right:24px;color:#071230;font-size:13px;line-height:1.5;">' . booking_email_icon('mail', 17) . '&nbsp;&nbsp;' . email_safe($email) . '</td>
<td style="color:#071230;font-size:13px;line-height:1.5;">' . booking_email_icon('web', 17) . '&nbsp;&nbsp;<a href="' . email_safe($websiteUrl) . '" style="color:#071230;">' . email_safe($websiteLabel) . '</a></td>
</tr></table>
</div>
</td></tr>
<tr><td class="booking-customer-footer" align="center" style="padding:25px 20px;background:#f6f7fa;border-top:1px solid #e3e6ec;color:#4a536b;font-size:13px;line-height:1.5;">&copy; ' . $year . ' ' . email_safe($brand) . '. All rights reserved.</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
}



function customer_booking_confirmation_email_html(array $booking, string $viewUrl = ''): string
{
    return customer_booking_email_html($booking, 'confirmed', [], $viewUrl);
}
