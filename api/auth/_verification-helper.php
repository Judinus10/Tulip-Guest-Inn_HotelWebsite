<?php
declare(strict_types=1);
require_once __DIR__ . '/../mail/email-helper.php';

const ADMIN_VERIFICATION_TTL = 120;

// OTPs bypass the scheduled queue and use the standard mail transport timeout.
// Delivery timing is logged without codes or credentials.
function send_admin_verification_code(string $to, string $title, string $otp): bool
{
    $started = microtime(true);
    $sent = send_html_email($to, $title . ' - Tulip Guest Inn', otp_email_html($title, $otp, 2), null, null, null, 'admin_alert');
    error_log('Admin verification mail: ' . ($sent ? 'accepted' : 'failed') . ', transport_ms=' . (int)round((microtime(true)-$started)*1000));
    return $sent;
}
