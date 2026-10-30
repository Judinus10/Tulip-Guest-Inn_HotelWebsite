<?php
declare(strict_types=1);
require_once __DIR__ . '/booking-email-preview-data.php';

/** Illustrative stays only; this function never queries real guests. */
function tulip_daily_summary_sample(string $scenario = 'single'): array
{
    if (!in_array($scenario, ['single','no_arrivals','no_departures','empty'], true)) throw new InvalidArgumentException('Choose a sample daily summary.');
    $today = new DateTimeImmutable('today');
    $arrival = booking_email_preview_data('group');
    $arrival['booking_no'] = 'MB-SAMPLE'; $arrival['guest_name'] = 'Sample Arriving Guest';
    $arrival['guest_email'] = 'arrival@example.test'; $arrival['guest_phone'] = '+94 77 000 0000';
    $arrival['check_in_date'] = $today->format('Y-m-d'); $arrival['check_out_date'] = $today->modify('+2 days')->format('Y-m-d');
    $arrival['status'] = 'Confirmed';
    $departure = booking_email_preview_data('single');
    $departure['booking_no'] = 'BK-SAMPLE'; $departure['guest_name'] = 'Sample Departing Guest';
    $departure['guest_email'] = 'departure@example.test'; $departure['guest_phone'] = '+94 77 000 0001';
    $departure['check_in_date'] = $today->modify('-2 days')->format('Y-m-d'); $departure['check_out_date'] = $today->format('Y-m-d');
    $departure['status'] = 'Checked In'; $departure['payment_status'] = 'Paid';
    return ['date'=>$today->format('Y-m-d'), 'check_ins'=>in_array($scenario,['no_arrivals','empty'],true)?[]:[$arrival],
        'check_outs'=>in_array($scenario,['no_departures','empty'],true)?[]:[$departure]];
}
