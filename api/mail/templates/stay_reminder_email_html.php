<?php
declare(strict_types=1);

function stay_reminder_email_html(string $date, array $checkIns, array $checkOuts): string
{
    $checkInCount = count($checkIns);
    $checkOutCount = count($checkOuts);
    $occupied = $checkInCount + $checkOutCount;
    $summaryDate = date('l, d F Y', strtotime($date) ?: time());

    $newBookings = [];
    foreach (array_slice(array_merge($checkIns, $checkOuts), 0, 3) as $booking) {
        $newBookings[] = (string) ($booking['booking_no'] ?? ('BK-' . str_pad((string) ($booking['id'] ?? 0), 5, '0', STR_PAD_LEFT)));
    }
    $newBookingBody = $newBookings === [] ? 'No new booking updates.' : '• Booking ID: ' . implode('<br>• Booking ID: ', array_map('email_safe', $newBookings));

    $paymentTotal = 0.0;
    foreach (array_merge($checkIns, $checkOuts) as $booking) { $paymentTotal += (float) ($booking['amount'] ?? 0); }
    $paymentBody = '<strong style="font-size:18px;color:#071529;">' . email_safe(format_money_amount($paymentTotal)) . '</strong><br><span style="color:#09285b;font-weight:700;">View Payment Reports →</span>';

    $content = '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#071529;"><strong>Dear Admin,</strong></p>
    <p style="margin:0 0 18px;font-size:14px;line-height:1.65;color:#071529;">Here is your daily overview of bookings, guest arrivals, departures and other important updates.</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 -7px 4px;"><tr>' .
        reminder_email_metric_card('user', (string) $checkInCount, 'Today\'s Check-ins') .
        reminder_email_metric_card('open', (string) $checkOutCount, 'Today\'s Check-outs') .
        reminder_email_metric_card('bed', (string) $occupied, 'Rooms Occupied') .
        reminder_email_metric_card('wallet', $occupied > 0 ? 'Active' : '0%', 'Today\'s Occupancy') .
    '</tr></table>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr>
      <td class="rem-two-col" style="width:50%;padding:0 10px 16px 0;vertical-align:top;">' . reminder_email_booking_table('Today\'s Check-ins (' . $checkInCount . ')', $checkIns, 'check_in_date', 'No check-ins scheduled.') . '</td>
      <td class="rem-two-col" style="width:50%;padding:0 0 16px 10px;vertical-align:top;">' . reminder_email_booking_table('Today\'s Check-outs (' . $checkOutCount . ')', $checkOuts, 'check_out_date', 'No check-outs scheduled.') . '</td>
    </tr></table>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 -7px;" class="rem-small-grid"><tr>' .
      reminder_email_small_panel('New Bookings (' . count($newBookings) . ')', $newBookingBody, 'calendar') .
      reminder_email_small_panel('Payment Received', $paymentBody, 'wallet') .
      reminder_email_small_panel('Pending Enquiries (0)', '<span style="color:#64748b;">No pending enquiries included in this reminder.</span>', 'mail') .
    '</tr></table>' . reminder_email_action_block();

    return reminder_email_shell('Good Morning, Admin!', $content, 'Daily operations summary for Tulip Guest Inn.', 'Daily Operations Summary', $summaryDate);
}
