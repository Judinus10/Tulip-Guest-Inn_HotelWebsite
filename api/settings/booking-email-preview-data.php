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
