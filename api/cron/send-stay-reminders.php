<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mail/email-helper.php';
require_once __DIR__ . '/../mail/templates/daily-summary-customizable-template.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    apply_cors_headers();

    $configuredToken = function_exists('jebal_env_value')
        ? trim((string) jebal_env_value('STAY_REMINDER_CRON_TOKEN', ''))
        : '';

    // HTTP execution must fail closed. CLI execution remains available for
    // server-managed scheduled jobs and does not require an HTTP token.
    if ($configuredToken === '') {
        error_log('Stay reminder HTTP cron disabled: STAY_REMINDER_CRON_TOKEN is not configured.');
        json_response(false, 'HTTP cron execution is disabled.', 503);
    }

    $requestToken = trim((string) ($_SERVER['HTTP_X_CRON_TOKEN'] ?? $_GET['token'] ?? ''));
    if ($requestToken === '' || !hash_equals($configuredToken, $requestToken)) {
        json_response(false, 'Forbidden.', 403);
    }
}

function stay_reminder_fetch_bookings(PDO $pdo, string $date, string $field): array
{
    return tulip_daily_summary_bookings($pdo, $date, $field);
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
    $adminEmail = booking_admin_email();
    $checkInCount = count($checkIns);
    $checkOutCount = count($checkOuts);

    if ($checkInCount === 0 && $checkOutCount === 0) {
        return 0;
    }

    $summary = tulip_render_daily_summary_email(tulip_saved_booking_template($pdo, 'admin_daily_summary'),
        ['date'=>$date, 'check_ins'=>$checkIns, 'check_outs'=>$checkOuts], tulip_booking_email_brand($pdo, false));

    return enqueue_email(
        $pdo,
        'admin_stay_reminder',
        (int) str_replace('-', '', $date),
        $adminEmail,
        $summary['subject'],
        $summary['html'],
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

    json_response(false, 'Stay reminders could not be processed. Review the protected cron log.', 500, [
        'error_code' => 'STAY_REMINDER_FAILED',
    ]);
}
