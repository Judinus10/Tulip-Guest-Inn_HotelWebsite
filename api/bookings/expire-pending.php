<?php
/**
 * Manual/cron endpoint to release old unpaid pending bookings.
 * Admin-authenticated POST is supported. For server cron, pass CRON_SECRET in
 * the X-Cron-Secret header or ?secret=... when CRON_SECRET is configured.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/booking-expiry-helper.php';
require_once __DIR__ . '/../mail/email-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

$cronSecret = defined('CRON_SECRET') ? trim((string) CRON_SECRET) : '';
$requestSecret = clean_string($_SERVER['HTTP_X_CRON_SECRET'] ?? ($_GET['secret'] ?? ''), 255);

if ($cronSecret !== '' && hash_equals($cronSecret, $requestSecret)) {
    // Cron allowed.
} else {
    require_admin_auth();
}

try {
    $pdo = get_db_connection();
    $expiredCount = expire_pending_bookings($pdo, null, true);

    json_response(true, 'Expired pending bookings processed.', 200, [
        'expired_count' => $expiredCount,
        'hold_minutes' => booking_hold_minutes(),
    ]);
} catch (Throwable $exception) {
    error_log('Expire pending bookings error: ' . $exception->getMessage());
    json_response(false, 'Unable to expire pending bookings.', 500);
}
