<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mail/email-helper.php';
require_once __DIR__ . '/../bookings/booking-audit-helper.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    apply_cors_headers();

    $configuredToken = '';
    if (function_exists('jebal_env_value')) {
        $configuredToken = trim((string) jebal_env_value('EMAIL_QUEUE_CRON_TOKEN', ''));
    }

    if ($configuredToken === '') {
        error_log('Email queue cron denied: EMAIL_QUEUE_CRON_TOKEN is not configured.');
        json_response(false, 'Cron endpoint is not configured.', 503);
    }

    $requestToken = trim((string) (
        $_SERVER['HTTP_X_CRON_TOKEN']
        ?? $_GET['token']
        ?? ''
    ));
    if ($requestToken === '' || !hash_equals($configuredToken, $requestToken)) {
        json_response(false, 'Unauthorized cron request.', 401);
    }
}

$cronLock = fopen(sys_get_temp_dir() . '/tulip-send-email-queue.lock', 'c');
if ($cronLock === false || !flock($cronLock, LOCK_EX | LOCK_NB)) {
    if (is_resource($cronLock)) {
        fclose($cronLock);
    }
    if ($isCli) {
        fwrite(STDOUT, "Email queue worker is already running.\n");
        exit(0);
    }
    json_response(false, 'Email queue worker is already running.', 409);
}

register_shutdown_function(static function () use ($cronLock): void {
    flock($cronLock, LOCK_UN);
    fclose($cronLock);
});

function payment_pending_email_delay_minutes(): int
{
    $value = function_exists('jebal_env_value')
        ? (int) jebal_env_value('PAYMENT_PENDING_EMAIL_DELAY_MINUTES', '10')
        : 10;

    return max(5, min(180, $value));
}

function queue_due_payment_pending_emails(PDO $pdo, int $limit = 25): array
{
    ensure_email_queue_table($pdo);
    ensure_booking_audit_table($pdo);

    $delayMinutes = payment_pending_email_delay_minutes();
    $cutoff = (new DateTimeImmutable())->modify('-' . $delayMinutes . ' minutes')->format('Y-m-d H:i:s');
    $limit = max(1, min(100, $limit));

    $stmt = $pdo->prepare(
        "SELECT b.*, p.id AS latest_payment_row_id, p.order_id, p.amount AS payment_amount, p.currency AS payment_currency, p.status AS latest_payment_status, p.method AS payment_method, p.created_at AS payment_created_at
         FROM bookings b
         INNER JOIN payments p
           ON p.id = (
                SELECT p2.id
                FROM payments p2
                WHERE p2.booking_id = b.id
                ORDER BY p2.id DESC
                LIMIT 1
           )
         WHERE b.status = 'Pending'
           AND b.payment_status = 'Payment Pending'
           AND p.status = 'Payment Pending'
           AND p.created_at <= :cutoff
           AND NOT EXISTS (
                SELECT 1
                FROM email_logs el
                WHERE el.booking_id = b.id
                  AND el.email_type IN ('booking_payment_pending_customer', 'booking_payment_pending_admin', 'staying_guest_payment_pending')
                  AND el.status = 'Sent'
           )
           AND NOT EXISTS (
                SELECT 1
                FROM email_queue eq
                WHERE eq.related_type = 'booking'
                  AND eq.related_id = b.id
                  AND eq.email_type IN ('booking_payment_pending_customer', 'booking_payment_pending_admin', 'staying_guest_payment_pending')
                  AND eq.status IN ('pending','processing','sent','Pending','Processing','Sent')
           )
         ORDER BY p.created_at ASC
         LIMIT " . $limit
    );
    $stmt->bindValue(':cutoff', $cutoff);
    $stmt->execute();

    $checked = 0;
    $queued = 0;
    $bookingIds = [];

    while ($booking = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $checked++;
        $bookingId = (int) ($booking['id'] ?? 0);
        if ($bookingId < 1) {
            continue;
        }

        $payment = [
            'order_id' => (string) ($booking['order_id'] ?? ''),
            'amount' => (float) ($booking['payment_amount'] ?? $booking['amount'] ?? 0),
            'currency' => (string) ($booking['payment_currency'] ?? $booking['currency'] ?? PAYMENT_CURRENCY),
            'status' => (string) ($booking['latest_payment_status'] ?? 'Payment Pending'),
            'method' => (string) ($booking['payment_method'] ?? 'PayHere'),
            'bill_url' => latest_booking_bill_url($pdo, $bookingId),
        ];

        $added = queue_payment_pending_emails_once($pdo, $booking, $payment);
        if ($added > 0) {
            $queued += $added;
            $bookingIds[] = $bookingId;
        }
    }

    return [
        'checked_bookings' => $checked,
        'queued_emails' => $queued,
        'booking_ids' => $bookingIds,
        'delay_minutes' => $delayMinutes,
    ];
}

try {
    $pdo = get_db_connection();
    ensure_email_queue_table($pdo);

    $limit = 10;
    if ($isCli) {
        $argLimit = $argv[1] ?? null;
        if ($argLimit !== null && ctype_digit((string) $argLimit)) {
            $limit = (int) $argLimit;
        }
    } elseif (isset($_GET['limit']) && ctype_digit((string) $_GET['limit'])) {
        $limit = (int) $_GET['limit'];
    }

    // Single email cron responsibility:
    // 1) queue due one-time payment pending emails
    // 2) send the normal email queue
    $pendingResult = queue_due_payment_pending_emails($pdo, 25);
    $queueResult = process_email_queue($pdo, $limit);

    $result = [
        'pending_payment_scan' => $pendingResult,
        'email_queue' => $queueResult,
    ];

    if ($isCli) {
        echo json_encode([
            'success' => true,
            'message' => 'Email queue worker completed.',
            'data' => $result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    json_response(true, 'Email queue worker completed.', 200, ['data' => $result]);
} catch (Throwable $e) {
    error_log('Email queue worker error: ' . $e->getMessage());

    if ($isCli) {
        fwrite(STDERR, 'Email queue worker error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    json_response(false, 'Email queue worker failed.', 500, [
        'error' => defined('APP_ENV') && APP_ENV === 'production' ? null : $e->getMessage(),
    ]);
}
