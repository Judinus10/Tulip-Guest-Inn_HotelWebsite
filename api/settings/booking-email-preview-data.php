<?php
declare(strict_types=1);

/** Illustrative data only. Preview never reads a customer's booking or creates a bill token. */
function booking_email_preview_data(string $scenario): array
{
    if (!in_array($scenario, ['single', 'group'], true)) throw new InvalidArgumentException('Choose a single-room or multiple-room sample.');
    $checkIn = new DateTimeImmutable('+14 days');
    $rooms = [['booking_id' => 1, 'room_name' => 'Sample double room', 'room_number' => '10',
        'adults' => 2, 'children' => 1, 'children_ages' => [6]]];
    if ($scenario === 'group') $rooms[] = ['booking_id' => 2, 'room_name' => 'Sample family room', 'room_number' => '12',
        'adults' => 1, 'children' => 1, 'children_ages' => [0]];
    return [
        'booking_no' => $scenario === 'group' ? 'MB-SAMPLE' : 'BK-SAMPLE',
        'full_name' => 'Sample Guest', 'check_in_date' => $checkIn->format('Y-m-d'),
        'check_out_date' => $checkIn->modify('+2 days')->format('Y-m-d'),
        'room_assignments' => $rooms, ...booking_guest_totals($rooms),
        'amount' => $scenario === 'group' ? 32000 : 16000, 'currency' => 'LKR',
        'payment_method' => 'Cash on arrival', 'payment_status' => 'Payment Pending',
    ];
}

function booking_email_admin_preview_data(string $type, string $scenario): array
{
    $booking = booking_email_preview_data($scenario);
    $booking['email'] = 'guest@example.test'; $booking['phone'] = '+94 77 000 0000';
    $booking['status'] = $type === 'admin_payment_received' ? 'Confirmed' : 'Pending';
    $booking['payment_status'] = match($type) { 'admin_payment_received'=>'Paid', 'admin_payment_failed'=>'Failed', default=>'Payment Pending' };
    $booking['payment_method'] = $type === 'admin_new_booking' ? 'Cash on arrival' : 'PayHere';
    $booking['payment_amount'] = $booking['amount']; $booking['payment_currency'] = $booking['currency'];
    $booking['transaction_id'] = 'SAMPLE-TRANSACTION';
    return $booking;
}

/** Match preview facts to the selected event; optional online examples never modify real bookings. */
function booking_email_event_preview_data(string $type, string $scenario): array
{
    $online = str_ends_with($scenario, '_online');
    $base = $online ? substr($scenario, 0, -7) : $scenario;
    $booking = str_starts_with($type, 'admin_') ? booking_email_admin_preview_data($type, $base) : booking_email_preview_data($base);
    $booking['status'] = $type === 'confirmed' ? 'Confirmed' : 'Pending';
    $booking['payment_method'] = $online ? 'PayHere' : 'Cash on arrival';
    $booking['payment_status'] = $online && $type === 'confirmed' ? 'Paid' : 'Payment Pending';
    if (in_array($type, ['payment_pending','admin_payment_pending','payment_failed','admin_payment_failed','payment_received','admin_payment_received','expired','admin_booking_expired'], true)) {
        $booking['payment_method'] = 'PayHere';
        $booking['payment_status'] = match($type) {
            'payment_failed','admin_payment_failed'=>'Failed',
            'payment_received','admin_payment_received'=>'Paid',
            'expired','admin_booking_expired'=>'Cancelled', default=>'Payment Pending',
        };
    }
    if (in_array($type, ['expired','admin_booking_expired'], true)) $booking['status'] = 'Expired';
    if (in_array($type, ['cancelled','admin_booking_cancelled'], true)) $booking['status'] = 'Cancelled';
    if ($type === 'admin_payment_received') $booking['status'] = 'Confirmed';
    if ($type === 'payment_received') $booking['payment_notice'] = 'Your payment has been recorded. Your reservation is awaiting confirmation.';
    if ($type === 'confirmed' && !$online) $booking['payment_notice'] = 'Payment is due on arrival. No online payment is required for this payment method.';
    $booking['payment_amount'] = $booking['amount']; $booking['payment_currency'] = $booking['currency'];
    return $booking;
}
