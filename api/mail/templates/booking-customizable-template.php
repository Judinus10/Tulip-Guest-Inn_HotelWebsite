<?php
declare(strict_types=1);
require_once __DIR__ . '/../../settings/booking_email_helpers.php';
require_once __DIR__ . '/../../bookings/guest-display-helper.php';

function tulip_booking_email_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tulip_booking_email_date(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
    return $date && $date->format('Y-m-d') === substr($value, 0, 10) ? $date->format('d M Y') : 'Not available';
}

/** Load actual saved contact values. Missing contact fields are omitted, not replaced by sample numbers. */
function tulip_booking_email_brand(PDO $pdo, bool $inlineLogo = true): array
{
    $settings = [];
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM website_settings WHERE setting_key IN
        ('business_name','address','phone','reception_contact_number','email','check_in_mode','check_in_time','check_out_until')");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $settings[$row['setting_key']] = trim((string) $row['setting_value']);
    $website = '';
    foreach (['FRONTEND_URL', 'PUBLIC_APP_URL', 'APP_BASE_URL'] as $key) {
        $candidate = defined($key) ? rtrim((string) constant($key), '/') : '';
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $candidate)) { $website = $candidate; break; }
    }
    $phones = [];
    foreach (['phone', 'reception_contact_number'] as $key) {
        $number = $settings[$key] ?? '';
        $normalized = preg_replace('/[^+0-9]/', '', $number);
        if ($number !== '' && preg_match('/[0-9]/', $normalized)) $phones[$normalized] = $number;
    }
    $logoPath = dirname(__DIR__, 2) . '/invoices/assets/tulip-logo-full.jpg';
    $logo = '';
    if (is_file($logoPath)) $logo = $inlineLogo ? 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($logoPath)) : 'cid:jebal-brand-logo';
    $timeHint = static function (string $value, string $prefix): string {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $value)) return '';
        $time = DateTimeImmutable::createFromFormat('!H:i', $value);
        return $prefix . $time->format('g:i A');
    };
    return ['name' => ($settings['business_name'] ?? '') ?: 'Tulip Guest Inn', 'logo_src' => $logo,
        'address' => $settings['address'] ?? '', 'phones' => array_values($phones),
        'email' => filter_var($settings['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $settings['email'] : '',
        'website' => $website,
        'check_in_hint' => ($settings['check_in_mode'] ?? '') === '24_hours' ? '24-hour check-in' : $timeHint($settings['check_in_time'] ?? '', 'From '),
        'check_out_hint' => $timeHint($settings['check_out_until'] ?? '', 'By ')];
}

function tulip_booking_email_tokens(array $booking, array $brand): array
{
    return ['guest_name' => (string) ($booking['full_name'] ?? 'Guest'),
        'booking_reference' => (string) ($booking['booking_no'] ?? ''),
        'check_in' => tulip_booking_email_date((string) ($booking['check_in_date'] ?? '')),
        'check_out' => tulip_booking_email_date((string) ($booking['check_out_date'] ?? '')),
        'hotel_name' => (string) $brand['name']];
}

/** Replace text nodes only, so guest values cannot introduce HTML or alter links. */
function tulip_booking_email_rich_text(string $html, array $tokens): string
{
    $html = booking_email_editor_sanitize($html);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    try { $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>' . $html . '</body></html>', LIBXML_NONET); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    $body = $doc->getElementsByTagName('body')->item(0);
    $replace = static function (DOMNode $node) use (&$replace, $tokens): void {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) $child->nodeValue = tulip_booking_email_plain_text($child->nodeValue, $tokens);
            else $replace($child);
        }
    };
    $replace($body);
    foreach ($body->getElementsByTagName('*') as $element) {
        $tag = strtolower($element->tagName);
        $styles = ['p' => 'margin:0 0 12px;', 'div' => 'margin:0 0 8px;', 'ul' => 'margin:8px 0 14px;padding-left:24px;',
            'ol' => 'margin:8px 0 14px;padding-left:24px;', 'li' => 'margin:0 0 5px;', 'a' => 'color:#19352B;text-decoration:underline;'];
        if (isset($styles[$tag])) $element->setAttribute('style', $styles[$tag]);
    }
    $result = '';
    foreach ($body->childNodes as $node) $result .= $doc->saveHTML($node);
    return $result;
}

function tulip_booking_email_plain_text(string $text, array $tokens): string
{
    $replacements = [];
    foreach ($tokens as $key => $value) $replacements['{' . $key . '}'] = $value;
    return strtr($text, $replacements);
}

/** Pure email renderer, shared by preview now and delivery integration in the following step. */
function tulip_render_booking_email(string $type, array $content, array $booking, array $brand, string $billUrl = '', bool $preview = false): array
{
    $type = booking_email_editor_type($type);
    $content = booking_email_editor_validate($content);
    $e = 'tulip_booking_email_escape';
    $tokens = tulip_booking_email_tokens($booking, $brand);
    $subject = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', tulip_booking_email_plain_text($content['subject'], $tokens)) ?? '');
    $heading = $type === 'received' ? 'We received your booking' : 'Your booking is confirmed';
    $status = $type === 'received' ? 'Awaiting confirmation' : 'Confirmed';
    $greeting = $e(tulip_booking_email_plain_text($content['greeting'], $tokens));
    $intro = tulip_booking_email_rich_text($content['introduction_html'], $tokens);
    $closing = tulip_booking_email_rich_text($content['closing_html'], $tokens);
    $footer = nl2br($e(tulip_booking_email_plain_text($content['footer_note'], $tokens)));
    $roomRows = '';
    foreach ($booking['room_assignments'] ?? [] as $room) {
        $ages = booking_guest_ages($room);
        $roomRows .= '<tr><td style="padding:16px 20px;border-top:1px solid #e8e3da;">'
            . '<div style="font-size:16px;font-weight:bold;color:#19352B;">' . $e($room['room_name'] ?? 'Room') . '</div>'
            . '<div style="margin-top:5px;">Assigned room: <strong>' . $e(trim((string) ($room['room_number'] ?? '')) ?: 'Not available') . '</strong></div>'
            . '<div style="margin-top:8px;">' . $e(booking_guest_summary($room)) . '</div>'
            . ($ages !== '' ? '<div style="margin-top:4px;color:#647067;">Child ages at check-in (years): ' . $e($ages) . '</div>' : '') . '</td></tr>';
    }
    $amount = number_format((float) ($booking['amount'] ?? 0), 2);
    $currency = $e($booking['currency'] ?? 'LKR');
    $payment = $e($booking['payment_method'] ?? 'Not available');
    $paymentStatus = $e($booking['payment_status'] ?? 'Payment Pending');
    $contacts = [];
    foreach ($brand['phones'] ?? [] as $phone) {
        $tel = preg_replace('/[^+0-9]/', '', (string) $phone);
        $contacts[] = '<a href="tel:' . $e($tel) . '" style="color:#56635b;text-decoration:none;">' . $e($phone) . '</a>';
    }
    $contactHtml = $contacts ? '<div style="margin-top:7px;">' . implode(' &nbsp;|&nbsp; ', $contacts) . '</div>' : '';
    if (!empty($brand['email'])) $contactHtml .= '<div style="margin-top:7px;"><a style="color:#56635b;text-decoration:none;" href="mailto:' . $e($brand['email']) . '">' . $e($brand['email']) . '</a></div>';
    if (!empty($brand['website'])) $contactHtml .= '<div style="margin-top:7px;"><a style="color:#56635b;text-decoration:none;" href="' . $e($brand['website']) . '">' . $e(parse_url($brand['website'], PHP_URL_HOST) ?: $brand['website']) . '</a></div>';
    $logo = !empty($brand['logo_src']) ? '<img src="' . $e($brand['logo_src']) . '" width="76" alt="' . $e($brand['name']) . '" style="display:block;width:76px;max-width:100%;height:auto;margin:0 auto 12px;border:0;">' : '';
    $action = '';
    if ($preview || (filter_var($billUrl, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $billUrl))) {
        $action = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" bgcolor="#19352B" style="border-radius:6px;"><a href="' . ($preview ? '#' : $e($billUrl)) . '" style="display:block;padding:15px 20px;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;">View booking &amp; bill</a></td></tr></table>';
    }
    $previewHead = $preview ? '<meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; img-src data:; style-src &#39;unsafe-inline&#39;;"><style>a{pointer-events:none!important;cursor:default!important}</style>' : '';
    $html = '<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $e($subject) . '</title>' . $previewHead . '
<style>@media only screen and (max-width:480px){.email-pad{padding-left:18px!important;padding-right:18px!important}.email-title{font-size:27px!important}.email-date{display:block!important;width:auto!important;padding:12px 0!important;border:0!important}.email-payment-label,.email-payment-value{display:block!important;text-align:left!important;padding:6px 0!important;width:auto!important}}</style></head>
<body style="margin:0;padding:0;background:#f3f4f2;color:#27392e;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.65;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $e($heading . ' - ' . $tokens['booking_reference']) . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f2;"><tr><td align="center" style="padding:20px 0;">
<!--[if mso]><table role="presentation" width="600"><tr><td><![endif]-->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e8e3da;">
<tr><td align="center" class="email-pad" style="padding:26px 32px 20px;border-bottom:2px solid #C7A86B;">' . $logo . '<div style="font-family:Georgia,Times,serif;font-size:20px;font-weight:bold;color:#19352B;">' . $e($brand['name']) . '</div></td></tr>
<tr><td class="email-pad" style="padding:26px 32px 14px;text-align:center;"><span style="display:inline-block;border-radius:20px;padding:4px 13px;font-size:12px;font-weight:bold;background:' . ($type === 'received' ? '#fff5df;color:#865d1c' : '#edf6ee;color:#245d35') . ';">' . $status . '</span><h1 class="email-title" style="margin:13px 0 8px;font-family:Georgia,Times,serif;font-size:31px;line-height:1.25;color:#19352B;">' . $heading . '</h1></td></tr>
<tr><td class="email-pad" style="padding:12px 32px 18px;word-break:break-word;overflow-wrap:anywhere;"><p style="margin:0 0 12px;font-weight:bold;">' . $greeting . '</p>' . $intro . '</td></tr>
<tr><td class="email-pad" style="padding:0 32px 20px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f6f3ec" style="border-radius:5px;"><tr><td style="padding:13px 17px;"><div style="font-size:11px;letter-spacing:1.2px;text-transform:uppercase;color:#647067;">Booking reference</div><strong style="font-size:19px;color:#19352B;">' . $e($tokens['booking_reference']) . '</strong></td></tr></table></td></tr>
<tr><td class="email-pad" style="padding:0 32px 20px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e8e3da;border-radius:6px;word-break:break-word;overflow-wrap:anywhere;">
<tr><td style="padding:15px 20px 0;font-family:Georgia,Times,serif;font-size:20px;font-weight:bold;color:#19352B;">Your stay</td></tr>
<tr><td style="padding:8px 20px 15px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
<td class="email-date" width="50%" valign="top" style="padding:8px 12px 8px 0;"><div style="font-size:11px;color:#647067;letter-spacing:0.7px;">CHECK-IN</div><strong style="font-size:17px;color:#19352B;">' . $e($tokens['check_in']) . '</strong><div style="font-size:12px;color:#647067;">' . $e($brand['check_in_hint'] ?? '') . '</div></td>
<td class="email-date" width="50%" valign="top" style="padding:8px 0 8px 18px;border-left:1px solid #e8e3da;"><div style="font-size:11px;color:#647067;letter-spacing:0.7px;">CHECK-OUT</div><strong style="font-size:17px;color:#19352B;">' . $e($tokens['check_out']) . '</strong><div style="font-size:12px;color:#647067;">' . $e($brand['check_out_hint'] ?? '') . '</div></td>
</tr></table></td></tr>' . $roomRows . '<tr><td style="padding:13px 20px;background:#faf9f6;border-top:1px solid #e8e3da;"><strong>Booking total:</strong> ' . $e(booking_guest_summary($booking)) . '</td></tr></table></td></tr>
<tr><td class="email-pad" style="padding:0 32px 22px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e8e3da;border-radius:6px;"><tr><td style="padding:15px 20px 8px;font-family:Georgia,Times,serif;font-size:20px;font-weight:bold;color:#19352B;">Payment details</td></tr><tr><td style="padding:0 20px 16px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">
<tr><td class="email-payment-label" style="padding:8px 0;">Payment method</td><td class="email-payment-value" align="right" style="padding:8px 0;font-weight:bold;">' . $payment . '</td></tr>
<tr><td class="email-payment-label" style="padding:8px 0;border-top:1px solid #e8e3da;font-weight:bold;">Total amount</td><td class="email-payment-value" align="right" style="padding:8px 0;border-top:1px solid #e8e3da;font-weight:bold;">' . $currency . ' ' . $amount . '</td></tr>
</table><div style="margin-top:9px;padding:8px 12px;background:#f6f3ec;border-radius:4px;font-size:12px;text-align:center;">' . $paymentStatus . '</div></td></tr></table></td></tr>
<tr><td class="email-pad" style="padding:0 32px 22px;">' . $action . '</td></tr>
<tr><td class="email-pad" style="padding:0 32px 22px;word-break:break-word;overflow-wrap:anywhere;">' . $closing . ($footer !== '' ? '<p style="margin:10px 0 0;color:#647067;">' . $footer . '</p>' : '') . '</td></tr>
<tr><td class="email-pad" align="center" style="padding:20px 32px 26px;border-top:1px solid #C7A86B;font-size:12px;line-height:1.8;color:#56635b;word-break:break-word;overflow-wrap:anywhere;"><strong style="font-family:Georgia,Times,serif;font-size:17px;color:#19352B;">' . $e($brand['name']) . '</strong>' . (!empty($brand['address']) ? '<div style="margin-top:5px;">' . nl2br($e($brand['address'])) . '</div>' : '') . $contactHtml . '</td></tr></table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr></table></body></html>';
    return ['subject' => $subject, 'html' => $html];
}
