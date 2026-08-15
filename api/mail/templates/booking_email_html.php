<?php
declare(strict_types=1);

function booking_email_html(string $state, array $booking, array $payment = [], bool $admin = false, string $extraButton = '', array $extraRows = [], string $customHeading = '', string $customMessage = '', string $customBadge = ''): string
{
    if ($admin) {
        return booking_admin_notification_email_html($booking, $payment, $extraButton, $extraRows, $customHeading, $customMessage);
    }

    if (in_array(strtolower(trim($state)), ['received', 'pending', 'confirmed', 'paid'], true)) {
        return booking_customer_confirmation_email_html($booking, $payment, $extraButton, $extraRows, $customHeading, $customMessage, $state);
    }

    $cfg = booking_email_status_config($state);
    [$heading, $message, $badge, $icon, $badgeColor, $badgeBg] = $cfg;

    if ($admin) {
        $heading = $heading . ': ' . booking_reference($booking);
        $message = $message . ' Review the details below.';
        $badge = match (strtolower($state)) {
            'confirmed', 'paid' => 'Payment Received',
            'pending', 'received' => 'Payment Pending',
            'failed' => 'Payment Failed',
            'expired' => 'Booking Expired',
            'cancelled' => 'Booking Cancelled',
            default => 'Booking Updated',
        };
    }

    if ($customHeading !== '') {
        $heading = $customHeading;
    }
    if ($customMessage !== '') {
        $message = $customMessage;
    }
    if ($customBadge !== '') {
        $badge = $customBadge;
    }

    $guestName = $admin ? ($booking['full_name'] ?? $booking['guest_name'] ?? 'Guest') : ($booking['full_name'] ?? 'Guest');
    $amount = format_money_amount((float) ($payment['amount'] ?? $booking['amount'] ?? 0));
    $paymentMethod = $payment['method'] ?? $payment['payment_method'] ?? $booking['payment_method'] ?? ($payment ? 'PayHere' : '-');
    $transaction = $payment['transaction_id'] ?? $payment['payment_id'] ?? $payment['order_id'] ?? '';
    $dates = trim((string) ($booking['check_in_date'] ?? '') . ' - ' . (string) ($booking['check_out_date'] ?? ''));

    $stayRows = [
        'Room Type' => $booking['room_name'] ?? '',
        'Check-in' => $booking['check_in_date'] ?? '',
        'Check-out' => $booking['check_out_date'] ?? '',
        'Guests' => !empty($booking['guests']) ? ((string) $booking['guests'] . ' Guests') : '',
    ];

    if ($admin) {
        $stayRows = array_merge([
            'Guest Name' => booking_guest_name($booking),
            'Booked By' => $booking['full_name'] ?? $booking['guest_name'] ?? '',
            'Phone' => $booking['phone'] ?? '',
            'Email' => $booking['email'] ?? '',
        ], $stayRows);
    }

    $paymentRows = [
        'Payment Method' => $paymentMethod,
        'Transaction ID' => $transaction,
        'Payment Status' => status_label_for_email($booking['payment_status'] ?? $payment['status'] ?? $state),
    ];

    if ($extraRows) {
        $paymentRows = array_merge($paymentRows, $extraRows);
    }

    $content = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>
        <td style="width:170px;vertical-align:top;text-align:center;padding-top:8px;">
            <div style="width:92px;height:92px;border-radius:50%;border:10px solid #f7f4ef;background:#987b58;color:#ffffff;font-size:13px;line-height:92px;text-align:center;margin:0 auto;font-weight:800;">' . booking_email_icon($icon, 32) . '</div>
        </td>
        <td style="vertical-align:top;padding-left:18px;">
            <h1 style="margin:0 0 16px;color:#2a190b;font-size:30px;line-height:1.15;font-weight:800;">' . email_safe($heading) . '</h1>
            <p style="margin:0 0 8px;font-size:15px;color:#111;">Hi ' . email_safe((string) $guestName) . ',</p>
            <p style="margin:0 0 16px;font-size:15px;line-height:1.55;color:#111;">' . email_safe($message) . '</p>
            <div style="display:inline-block;border-radius:22px;background:' . $badgeBg . ';color:' . $badgeColor . ';font-size:13px;font-weight:800;padding:9px 16px;">' . booking_email_icon($icon) . ' &nbsp;' . email_safe($badge) . '</div>
        </td>
    </tr></table>';

    $content .= booking_email_reference_panel($booking);
    $content .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 -10px;"><tr>'
        . booking_email_info_box('Stay Details', 'bed', $stayRows)
        . booking_email_info_box('Payment Summary', 'wallet', $paymentRows, $amount)
        . '</tr></table>';

    if ($extraButton !== '') {
        $content .= '<div style="text-align:center;margin:10px 0 0;">' . $extraButton . '</div>';
    }

    return booking_email_shell($content, $message);
}
