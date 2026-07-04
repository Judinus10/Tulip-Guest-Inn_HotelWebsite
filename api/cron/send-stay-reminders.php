<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mail/email-helper.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    apply_cors_headers();

    $configuredToken = function_exists('jebal_env_value')
        ? trim((string) jebal_env_value('STAY_REMINDER_CRON_TOKEN', ''))
        : '';

    if ($configuredToken !== '') {
        $requestToken = trim((string) ($_GET['token'] ?? ''));
        if (!hash_equals($configuredToken, $requestToken)) {
            json_response(false, 'Unauthorized cron request.', 401);
        }
    }
}

function stay_reminder_fetch_bookings(PDO $pdo, string $date, string $field): array
{
    if (!in_array($field, ['check_in_date', 'check_out_date'], true)) {
        throw new InvalidArgumentException('Invalid stay reminder date field.');
    }

    $stmt = $pdo->prepare(
        "SELECT
            b.id,
            CONCAT('BK-', LPAD(b.id, 5, '0')) AS booking_no,
            b.full_name,
            b.email,
            b.phone,
            CASE WHEN b.is_booking_for_other = 1 AND COALESCE(NULLIF(b.staying_guest_name, ''), '') <> '' THEN b.staying_guest_name ELSE b.full_name END AS guest_name,
            CASE WHEN b.is_booking_for_other = 1 AND COALESCE(NULLIF(b.staying_guest_email, ''), '') <> '' THEN b.staying_guest_email ELSE b.email END AS guest_email,
            CASE WHEN b.is_booking_for_other = 1 AND COALESCE(NULLIF(b.staying_guest_phone, ''), '') <> '' THEN b.staying_guest_phone ELSE b.phone END AS guest_phone,
            b.room_name,
            b.check_in_date,
            b.check_out_date,
            b.guests,
            b.amount,
            b.currency,
            b.status,
            b.payment_status,
            b.created_at,
            b.updated_at
         FROM bookings b
         WHERE b.$field = :stay_date
           AND LOWER(COALESCE(b.status, '')) NOT IN ('cancelled', 'canceled', 'no_show', 'checked_out', 'expired')
           AND LOWER(COALESCE(b.payment_status, '')) NOT IN ('cancelled', 'canceled', 'failed', 'refunded', 'expired')
         ORDER BY b.room_name ASC, b.check_in_date ASC, b.id ASC"
    );
    $stmt->execute([':stay_date' => $date]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function stay_reminder_booking_rows(array $booking): array
{
    return [
        'Booking' => (string) ($booking['booking_no'] ?? ('BK-' . str_pad((string) ($booking['id'] ?? 0), 5, '0', STR_PAD_LEFT))),
        'Guest' => (string) ($booking['guest_name'] ?? $booking['full_name'] ?? 'Guest'),
        'Phone' => (string) ($booking['guest_phone'] ?? $booking['phone'] ?? '-'),
        'Email' => (string) ($booking['guest_email'] ?? $booking['email'] ?? '-'),
        'Room' => (string) ($booking['room_name'] ?? '-'),
        'Check-in' => (string) ($booking['check_in_date'] ?? '-'),
        'Check-out' => (string) ($booking['check_out_date'] ?? '-'),
        'Guests' => (string) ($booking['guests'] ?? '-'),
        'Amount' => format_money_amount((float) ($booking['amount'] ?? 0)),
        'Booking Status' => ucwords(str_replace('_', ' ', (string) ($booking['status'] ?? '-'))),
        'Payment Status' => ucwords(str_replace('_', ' ', (string) ($booking['payment_status'] ?? '-'))),
    ];
}

function stay_reminder_bookings_html(array $bookings, string $emptyText): string
{
    if ($bookings === []) {
        return '<p style="margin:0;color:#6b7280;">' . email_safe($emptyText) . '</p>';
    }

    $html = '';
    foreach ($bookings as $booking) {
        $html .= '<div style="margin:18px 0;padding:16px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;">'
            . email_info_table(stay_reminder_booking_rows($booking))
            . '</div>';
    }

    return $html;
}

function queue_stay_reminder_email(PDO $pdo, string $date, array $checkIns, array $checkOuts): int
{
    $adminEmail = defined('ADMIN_EMAIL') ? trim((string) ADMIN_EMAIL) : '';
    if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('Stay reminder skipped: ADMIN_EMAIL is missing or invalid.');
        return 0;
    }

    $checkInCount = count($checkIns);
    $checkOutCount = count($checkOuts);

    if ($checkInCount === 0 && $checkOutCount === 0) {
        return 0;
    }

    $body = function_exists('stay_reminder_email_html')
        ? stay_reminder_email_html($date, $checkIns, $checkOuts)
        : email_shell(
            'Today\'s stay reminders',
            '<p style="margin:0 0 16px;">These are the bookings that need admin attention today.</p>' .
            email_badge($checkInCount . ' check-in' . ($checkInCount === 1 ? '' : 's'), 'blue') .
            ' <span style="display:inline-block;width:8px;"></span>' .
            email_badge($checkOutCount . ' check-out' . ($checkOutCount === 1 ? '' : 's'), 'gold') .
            '<h2 style="margin:26px 0 10px;font-size:18px;color:#111827;">Today check-ins</h2>' .
            stay_reminder_bookings_html($checkIns, 'No check-ins scheduled for today.') .
            '<h2 style="margin:26px 0 10px;font-size:18px;color:#111827;">Today check-outs</h2>' .
            stay_reminder_bookings_html($checkOuts, 'No check-outs scheduled for today.'),
            'Today check-in and check-out reminders for Jebal Guest House.'
        );

    return enqueue_email(
        $pdo,
        'admin_stay_reminder',
        (int) str_replace('-', '', $date),
        $adminEmail,
        'Today check-in/check-out reminders - Jebal Guest House - ' . $date,
        $body,
        'admin_stay_reminder_' . str_replace('-', '', $date),
        null,
        3,
        admin_from_email(),
        admin_from_name()
    ) ? 1 : 0;
}

try {
    $pdo = get_db_connection();
    ensure_email_queue_table($pdo);

    $date = date('Y-m-d');
    if ($isCli && isset($argv[1]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $argv[1])) {
        $date = (string) $argv[1];
    } elseif (!$isCli && isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['date'])) {
        $date = (string) $_GET['date'];
    }

    $checkIns = stay_reminder_fetch_bookings($pdo, $date, 'check_in_date');
    $checkOuts = stay_reminder_fetch_bookings($pdo, $date, 'check_out_date');
    $queued = queue_stay_reminder_email($pdo, $date, $checkIns, $checkOuts);

    $processLimit = 20;
    $queueResult = process_email_queue($pdo, $processLimit);

    $payload = [
        'success' => true,
        'message' => 'Stay reminder cron completed.',
        'date' => $date,
        'check_ins' => count($checkIns),
        'check_outs' => count($checkOuts),
        'queued' => $queued,
        'email_queue' => $queueResult,
    ];

    if ($isCli) {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    json_response(true, 'Stay reminder cron completed.', 200, ['data' => $payload]);
} catch (Throwable $e) {
    error_log('Stay reminder cron error: ' . $e->getMessage());

    if ($isCli) {
        fwrite(STDERR, 'Stay reminder cron error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    json_response(false, 'Stay reminder cron failed.', 500, [
        'error' => defined('APP_ENV') && APP_ENV === 'production' ? null : $e->getMessage(),
    ]);
}
