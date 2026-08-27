<?php
declare(strict_types=1);

function booking_customer_confirmation_email_html(array $booking, array $payment = [], string $extraButton = '', array $extraRows = [], string $customHeading = '', string $customMessage = '', string $state = 'confirmed'): string
{
    $state = strtolower(trim($state));
    $guestName = trim((string) ($booking['full_name'] ?? $booking['guest_name'] ?? 'Guest'));
    $roomName = trim((string) ($booking['room_name'] ?? 'Room'));
    $bookingRef = booking_reference($booking);
    $guestsCount = max(1, (int) ($booking['guests'] ?? 1));
    $checkIn = booking_email_format_date((string) ($booking['check_in_date'] ?? ''), true);
    $checkOut = booking_email_format_date((string) ($booking['check_out_date'] ?? ''), true);
    $amount = format_money_amount($payment['amount'] ?? $booking['amount'] ?? 0);

    $paymentMethod = trim((string) ($payment['method'] ?? $payment['payment_method'] ?? $booking['payment_method'] ?? 'PayHere'));
    $isCash = strcasecmp($paymentMethod, 'Cash') === 0;
    $methodLabel = $isCash ? 'Cash on Arrival' : ($paymentMethod !== '' ? $paymentMethod : 'PayHere');
    $paymentStatus = status_label_for_email($payment['status'] ?? $booking['payment_status'] ?? ($isCash ? 'Payment Pending' : 'Paid'));
    $isPaid = strcasecmp($paymentStatus, 'Paid') === 0 || in_array($state, ['paid', 'confirmed'], true);

    $heading = $customHeading !== '' ? $customHeading : ($isPaid ? 'Booking Confirmed!' : 'Booking Request Received');
    $message = $customMessage !== ''
        ? $customMessage
        : ($isCash
            ? 'Your booking request has been received. Payment will be collected at the property, and our team will confirm your booking after review.'
            : ($isPaid ? 'Thank you for choosing Tulip Guest Inn. We look forward to welcoming you!' : 'Your booking is waiting for secure online payment confirmation.'));

    $row = static function (string $icon, string $label, string $value, string $valueStyle = ''): string {
        if ($value === '') { return ''; }
        return '<tr><td style="width:34px;padding:7px 8px 7px 0;vertical-align:middle;">' . booking_email_icon($icon, 20) . '</td><td style="width:180px;padding:7px 10px;color:#17243a;font-size:14px;vertical-align:middle;">' . email_safe($label) . '</td><td style="width:12px;padding:7px 0;color:#17243a;font-size:14px;vertical-align:middle;">:</td><td style="padding:7px 0 7px 12px;color:#17243a;font-size:14px;vertical-align:middle;' . $valueStyle . '">' . email_safe($value) . '</td></tr>';
    };

    $details = $row('calendar', 'Booking ID', $bookingRef)
        . $row('bed', 'Room', $roomName)
        . $row('user', 'Guests', $guestsCount . ' ' . ($guestsCount === 1 ? 'Guest' : 'Guests'))
        . $row('calendar', 'Check-in', $checkIn)
        . $row('calendar', 'Check-out', $checkOut)
        . '<tr><td colspan="4" style="padding:8px 0;"><div style="height:1px;background:#e5e7eb;"></div></td></tr>'
        . $row('wallet', 'Total Amount', $amount, 'font-weight:800;')
        . $row('wallet', 'Payment Method', $methodLabel)
        . $row($isPaid ? 'check' : 'time', 'Payment Status', $paymentStatus, 'font-weight:800;color:' . ($isPaid ? '#247a3b' : '#c58b28') . ';');

    foreach ($extraRows as $label => $value) {
        $details .= $row('info', (string) $label, (string) $value);
    }

    $html = email_shell_start($heading, $message, 680);
    $html .= '<tr><td style="background:#06182a;border-bottom:4px solid #d5a23f;padding:28px;text-align:center;">' . email_tulip_logo_html(170, true) . '</td></tr>';
    $html .= '<tr><td class="pad" style="padding:36px 58px 26px;text-align:center;">'
        . '<div style="width:66px;height:66px;margin:0 auto 18px;border:2px solid #d5a23f;border-radius:50%;background:#fbf4e8;line-height:66px;">' . booking_email_icon($isPaid ? 'check' : 'calendar', 30) . '</div>'
        . '<h1 class="hero-title" style="margin:0;color:#071529;font-size:31px;line-height:1.2;">' . email_safe($heading) . '</h1>'
        . '<p style="margin:12px auto 0;max-width:520px;color:#536174;font-size:15px;line-height:1.55;">Hi ' . email_safe($guestName) . ',<br>' . email_safe($message) . '</p>'
        . '<div style="margin:26px 0 22px;border-top:1px solid #d5a23f;"></div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;border:1px solid #d9dde3;border-radius:8px;background:#ffffff;text-align:left;"><tr><td style="padding:20px 26px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">' . $details . '</table></td></tr></table>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:18px;border-collapse:separate;border-spacing:0;background:#fff8ed;border-radius:8px;text-align:left;"><tr><td style="width:48px;padding:18px 0 18px 20px;vertical-align:top;">' . booking_email_icon('mail', 24) . '</td><td style="padding:16px 20px;color:#253247;font-size:13px;line-height:1.65;">Please keep your Booking ID for reference.<br>If you have any questions, feel free to contact us.</td></tr></table>'
        . ($extraButton !== '' ? '<div style="margin-top:20px;">' . $extraButton . '</div>' : '')
        . '</td></tr>';
    $html .= '<tr><td style="background:#f7f7f7;padding:24px 28px;text-align:center;color:#17243a;font-size:13px;line-height:1.8;">'
        . '<strong style="display:block;font-size:17px;margin-bottom:6px;">Tulip Guest Inn</strong>'
        . booking_email_icon('phone', 14) . ' ' . email_safe(email_contact_phone()) . ' &nbsp;&nbsp; | &nbsp;&nbsp; '
        . booking_email_icon('mail', 14) . ' ' . email_safe(email_contact_email()) . '<br>'
        . booking_email_icon('location', 14) . ' ' . email_safe(email_contact_address())
        . '<div style="margin-top:14px;border-top:1px solid #d5a23f;padding-top:12px;color:#c58b28;font-family:Georgia,Times New Roman,serif;font-size:17px;font-style:italic;">We look forward to welcoming you!</div>'
        . '</td></tr>';
    return $html . email_shell_end();
}
