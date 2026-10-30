<?php
declare(strict_types=1);
require_once __DIR__ . '/booking-customizable-template.php';

/** Actual scheduled stays, grouped by reservation without multiplying group totals. */
function tulip_daily_summary_bookings(PDO $pdo, string $date, string $field): array
{
    tulip_daily_summary_date($date);
    if (!in_array($field, ['check_in_date', 'check_out_date'], true)) throw new InvalidArgumentException('Invalid stay date field.');
    $stmt = $pdo->prepare("SELECT b.*, COALESCE(r.room_number, '') AS room_number,
        COALESCE(NULLIF(g.booking_no, ''), CONCAT('BK-', LPAD(b.id, 5, '0'))) AS summary_reference
        FROM bookings b LEFT JOIN rooms r ON r.id=b.room_id LEFT JOIN booking_groups g ON g.id=b.booking_group_id
        WHERE b.$field=:stay_date
        AND LOWER(REPLACE(COALESCE(b.status, ''), ' ', '_')) NOT IN ('cancelled','canceled','no_show','checked_out','expired')
        AND LOWER(REPLACE(COALESCE(b.payment_status, ''), ' ', '_')) NOT IN ('cancelled','canceled','failed','refunded','expired')
        ORDER BY b.room_name ASC, b.is_group_primary DESC, b.id ASC");
    $stmt->execute([':stay_date'=>$date]);
    $reservations = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (int) ($row['booking_group_id'] ?? 0) > 0 ? 'group:' . $row['booking_group_id'] : 'booking:' . $row['id'];
        if (!isset($reservations[$key])) {
            $booking = $row;
            $booking['booking_no'] = $row['summary_reference'];
            $other = !empty($row['is_booking_for_other']);
            foreach (['name'=>'full_name','email'=>'email','phone'=>'phone'] as $suffix=>$fallback) {
                $booking['guest_'.$suffix] = $other && trim((string) ($row['staying_guest_'.$suffix] ?? '')) !== '' ? $row['staying_guest_'.$suffix] : $row[$fallback];
            }
            $booking['room_assignments'] = [];
            $booking['amount'] = 0.0;
            $reservations[$key] = $booking;
        }
        $reservations[$key]['amount'] += (float) $row['amount'];
        $reservations[$key]['room_assignments'][] = array_merge($row, booking_stored_party($row));
    }
    foreach ($reservations as &$booking) $booking = array_merge($booking, booking_guest_totals($booking['room_assignments']));
    unset($booking);
    return array_values($reservations);
}

function tulip_daily_summary_date(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Choose a valid summary date.');
    return $date->format('d M Y');
}

function tulip_daily_summary_list(array $bookings, string $emptyText): string
{
    $e = 'tulip_booking_email_escape';
    if (!$bookings) return '<p style="margin:12px 0 20px;color:#647067;">'.$e($emptyText).'</p>';
    $html = '';
    foreach ($bookings as $booking) {
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:12px 0 20px;border:1px solid #e8e3da;word-break:break-word;"><tr><td style="padding:16px;">'
            . '<div style="font-size:17px;font-weight:bold;color:#19352B;">'.$e($booking['booking_no'] ?? '').'</div>'
            . '<div style="margin:6px 0;font-weight:bold;">'.$e($booking['guest_name'] ?? $booking['full_name'] ?? 'Guest').'</div>';
        foreach (['Phone'=>$booking['guest_phone'] ?? $booking['phone'] ?? '', 'Email'=>$booking['guest_email'] ?? $booking['email'] ?? ''] as $label=>$value) {
            if (trim((string) $value) !== '') $html .= '<div style="margin:4px 0;">'.$e($label).': '.$e($value).'</div>';
        }
        $html .= '<div style="margin:8px 0;">'. $e(booking_guest_summary($booking)) .'</div>';
        foreach ($booking['room_assignments'] ?? [] as $room) {
            $number = trim((string) ($room['room_number'] ?? '')) ?: 'Not available';
            $html .= '<div style="padding:9px 0;border-top:1px solid #e8e3da;"><strong>'.$e($room['room_name'] ?? 'Room').'</strong><br>Assigned room: '.$e($number).'<br>'.$e(booking_guest_summary($room));
            $ages = booking_guest_ages($room);
            if ($ages !== '') $html .= '<br><span style="color:#647067;">Child ages at check-in: '.$e($ages).'</span>';
            $html .= '</div>';
        }
        $html .= '<div style="padding-top:9px;border-top:1px solid #e8e3da;">Check-in: '.$e(tulip_booking_email_date((string) ($booking['check_in_date'] ?? '')))
            .'<br>Check-out: '.$e(tulip_booking_email_date((string) ($booking['check_out_date'] ?? '')))
            .'<br>Booking status: '.$e($booking['status'] ?? 'Not available')
            .'<br>Payment status: '.$e($booking['payment_status'] ?? 'Not available')
            .'<br>Booking amount: '.$e($booking['currency'] ?? 'LKR').' '.number_format((float) ($booking['amount'] ?? 0), 2).'</div></td></tr></table>';
    }
    return $html;
}

/** Saved wording and brand only; operational lists are always generated from actual data. */
function tulip_render_daily_summary_email(array $content, array $summary, array $brand, bool $preview = false): array
{
    $content = booking_email_editor_validate($content, 'admin_daily_summary');
    $e = 'tulip_booking_email_escape';
    $date = tulip_daily_summary_date((string) ($summary['date'] ?? ''));
    $arrivals = $summary['check_ins'] ?? []; $departures = $summary['check_outs'] ?? [];
    $tokens = ['summary_date'=>$date, 'check_in_count'=>(string) count($arrivals), 'check_out_count'=>(string) count($departures), 'hotel_name'=>(string) $brand['name']];
    $subject = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', tulip_booking_email_plain_text($content['subject'], $tokens)) ?? '');
    $heading = tulip_booking_email_plain_text($content['heading'], $tokens);
    $width = max(40, min(240, (int) ($brand['logo_width'] ?? 76)));
    $logo = !empty($brand['logo_src']) ? '<img src="'.$e($brand['logo_src']).'" width="'.$width.'" alt="'.$e($brand['name']).'" style="display:block;width:'.$width.'px;max-width:100%;height:auto;margin:0 auto 12px;border:0;">' : '';
    if ($preview) $logo = '<div data-email-brand-logo="true">' . $logo . '</div>';
    $action = '';
    if ($preview || !empty($summary['is_test_email'])) {
        $action = '<div style="padding:13px;text-align:center;background:#19352B;color:#fff;border-radius:5px;font-weight:bold;">Open booking calendar (sample)</div>';
    } elseif (defined('ADMIN_APP_URL') && filter_var(ADMIN_APP_URL, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', ADMIN_APP_URL)) {
        $action = '<a href="'.$e(rtrim(ADMIN_APP_URL, '/').'/booking-calendar').'" style="display:block;padding:13px;text-align:center;background:#19352B;color:#fff;border-radius:5px;text-decoration:none;font-weight:bold;">Open booking calendar</a>';
    }
    $test = !empty($summary['is_test_email']) ? '<tr><td style="padding:12px;background:#fff5df;color:#865d1c;text-align:center;font-weight:bold;">TEST EMAIL — Sample daily summary. No real guest records.</td></tr>' : '';
    $csp = $preview ? '<meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; img-src data:; style-src &#39;unsafe-inline&#39;;"><style>a{pointer-events:none!important}</style>' : '';
    $html = '<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$e($subject).'</title>'.$csp.'<style>@media only screen and (max-width:480px){.summary-pad{padding-left:18px!important;padding-right:18px!important}.summary-metric{display:block!important;width:auto!important}}</style></head><body style="margin:0;padding:0;background:#f3f4f2;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.65;color:#27392e;">'
        .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f2;"><tr><td align="center" style="padding:20px 0;"><!--[if mso]><table width="600"><tr><td><![endif]--><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border:1px solid #e8e3da;">'
        .'<tr><td align="center" class="summary-pad" style="padding:26px 32px;border-bottom:2px solid #C7A86B;">'.$logo.'<strong style="font-family:Georgia,serif;font-size:20px;color:#19352B;">'.$e($brand['name']).'</strong></td></tr>'.$test
        .'<tr><td class="summary-pad" style="padding:24px 32px 12px;"><div style="font-size:12px;color:#865d1c;">'.$e($date).'</div><h1 style="font-family:Georgia,serif;font-size:29px;line-height:1.3;color:#19352B;margin:8px 0 20px;">'.$e($heading).'</h1>'
        .'<p style="font-weight:bold;">'.$e(tulip_booking_email_plain_text($content['greeting'], $tokens)).'</p>'.tulip_booking_email_rich_text($content['introduction_html'], $tokens).'</td></tr>'
        .'<tr><td class="summary-pad" style="padding:0 32px 20px;"><table role="presentation" width="100%" cellpadding="12" cellspacing="0" style="background:#f6f3ec;"><tr><td class="summary-metric" width="50%"><strong style="font-size:24px;color:#19352B;">'.count($arrivals).'</strong><br>Arriving reservations</td><td class="summary-metric" width="50%"><strong style="font-size:24px;color:#19352B;">'.count($departures).'</strong><br>Departing reservations</td></tr></table>'
        .'<h2 style="font-family:Georgia,serif;font-size:22px;color:#19352B;margin:24px 0 8px;">Today’s check-ins</h2>'.tulip_daily_summary_list($arrivals, 'No check-ins scheduled for today.')
        .'<h2 style="font-family:Georgia,serif;font-size:22px;color:#19352B;margin:24px 0 8px;">Today’s check-outs</h2>'.tulip_daily_summary_list($departures, 'No check-outs scheduled for today.').$action.'</td></tr>'
        .'<tr><td class="summary-pad" style="padding:0 32px 22px;">'.tulip_booking_email_rich_text($content['closing_html'], $tokens).'<p style="color:#647067;">'.nl2br($e(tulip_booking_email_plain_text($content['footer_note'], $tokens))).'</p></td></tr>'
        .tulip_email_note_row($content['please_note'] ?? '', $tokens, 'summary-pad')
        .'<tr><td data-email-theme-footer="true" align="center" style="padding:20px;border-top:1px solid #C7A86B;font-size:12px;color:#647067;">Internal operations summary · '.$e($brand['name']).'</td></tr></table><!--[if mso]></td></tr></table><![endif]--></td></tr></table></body></html>';
    return ['subject'=>$subject, 'html'=>tulip_email_apply_theme($html, $brand['colors'] ?? theme_default_colors())];
}
