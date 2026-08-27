<?php
/**
 * Public signed payment/booking status endpoint for the customer bill page.
 * The token is generated when the PayHere checkout session is created.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../invoices/invoice-helper.php';
require_once __DIR__ . '/../bookings/booking-expiry-helper.php';
require_once __DIR__ . '/../bookings/booking-audit-helper.php';
require_once __DIR__ . '/../bookings/multi-room-helper.php';
require_once __DIR__ . '/../mail/email-helper.php';
require_once __DIR__ . '/../security/public-token-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Only GET requests are allowed.', 405);
}

function public_invoice_download_token(int $bookingId): string
{
    return create_public_token('invoice-download', ['booking_id' => $bookingId], INVOICE_LINK_TTL_SECONDS);
}


function load_payment_history(PDO $pdo, int $bookingId): array
{
    $stmt = $pdo->prepare(
        'SELECT order_id, payment_id, amount, currency, status, method, invoice_number, created_at, updated_at
         FROM payments
         WHERE booking_id = :booking_id
         ORDER BY created_at ASC, id ASC'
    );
    $stmt->execute([':booking_id' => $bookingId]);

    $attempt = 0;
    return array_map(static function (array $payment) use (&$attempt): array {
        $attempt++;
        return [
            'attempt' => $attempt,
            'order_id' => (string) ($payment['order_id'] ?? ''),
            'payment_id' => (string) ($payment['payment_id'] ?? ''),
            'amount' => (float) ($payment['amount'] ?? 0),
            'currency' => (string) ($payment['currency'] ?? PAYMENT_CURRENCY),
            'status' => (string) ($payment['status'] ?? 'Payment Pending'),
            'method' => (string) ($payment['method'] ?? 'PayHere'),
            'invoice_number' => (string) ($payment['invoice_number'] ?? ''),
            'created_at' => (string) ($payment['created_at'] ?? ''),
            'updated_at' => (string) ($payment['updated_at'] ?? ''),
        ];
    }, $stmt->fetchAll() ?: []);
}

function public_room_url(PDO $pdo, string $roomName): string
{
    $publicBaseUrl = defined('FRONTEND_URL') && FRONTEND_URL !== ''
        ? FRONTEND_URL
        : (defined('PUBLIC_APP_URL') && PUBLIC_APP_URL !== '' ? PUBLIC_APP_URL : APP_BASE_URL);
    $publicBaseUrl = rtrim((string) $publicBaseUrl, '/');

    try {
        $stmt = $pdo->prepare('SELECT id, slug FROM rooms WHERE room_name = :room_name LIMIT 1');
        $stmt->execute([':room_name' => $roomName]);
        $room = $stmt->fetch();

        if ($room) {
            $identifier = trim((string) ($room['slug'] ?? ''));
            if ($identifier === '') {
                $identifier = (string) ($room['id'] ?? '');
            }

            if ($identifier !== '') {
                return $publicBaseUrl . '/rooms/' . rawurlencode($identifier);
            }
        }
    } catch (Throwable $exception) {
        error_log('Unable to build public room URL: ' . $exception->getMessage());
    }

    return $publicBaseUrl . '/rooms';
}

$bookingId = (int) ($_GET['booking_id'] ?? 0);
$orderId = clean_string($_GET['order_id'] ?? '', 100);
$token = clean_string($_GET['token'] ?? '', 1024);

if ($bookingId < 1 || $orderId === '' || $token === '') {
    json_response(false, 'Booking ID, order ID, and token are required.', 422);
}
// PayHere bills poll every 15 seconds and Cash bills every 7 minutes.
// Allow normal bill monitoring without letting the endpoint run unrestricted.
rate_limit_or_fail('payment_status_lookup', 120, 15);

try {
    $pdo = get_db_connection();
    expire_pending_bookings($pdo, $bookingId, true);

    $stmt = $pdo->prepare(
        'SELECT
            b.*,
            p.order_id,
            p.payment_id,
            p.status AS gateway_payment_status,
            p.method AS payment_method,
            p.amount AS paid_amount,
            p.currency AS paid_currency,
            p.updated_at AS payment_updated_at,
            p.invoice_id AS payment_invoice_id,
            p.invoice_number AS payment_invoice_number
         FROM bookings b
         INNER JOIN payments p ON p.booking_id = b.id
         WHERE b.id = :booking_id AND p.order_id = :order_id
         LIMIT 1'
    );
    $stmt->execute([
        ':booking_id' => $bookingId,
        ':order_id' => $orderId,
    ]);
    $record = $stmt->fetch();

    if (!$record) {
        json_response(false, 'Payment record was not found.', 404);
    }

    $amountForToken = number_format((float) ($record['paid_amount'] ?? $record['amount'] ?? 0), 2, '.', '');
    $validToken = verify_public_token($token, 'payment-status', [
        'order_id' => $orderId,
        'booking_id' => $bookingId,
        'amount' => $amountForToken,
    ]);
    if (!$validToken && legacy_public_tokens_allowed()) {
        $legacyToken = hash_hmac('sha256', $orderId . '|' . $bookingId . '|' . $amountForToken, PAYHERE_MERCHANT_SECRET);
        $validToken = hash_equals($legacyToken, $token);
    }
    if (!$validToken) {
        json_response(false, 'Invalid bill access token.', 403);
    }

    $paymentStatus = (string) ($record['gateway_payment_status'] ?: $record['payment_status'] ?: 'Payment Pending');

    // Do not trigger booking emails from the public bill/status polling endpoint.
    // Emails are queued by the verified PayHere notify endpoint and delivered by cron.

    if ($paymentStatus === 'Paid' && (empty($record['invoice_id']) || empty($record['invoice_file_path']))) {
        try {
            booking_audit_log($pdo, $bookingId, 'invoice_generation_started', 'Invoice Generation Started', 'Invoice generation was triggered from the bill page.', ['order_id' => $orderId]);

            generate_invoice_for_booking($pdo, $bookingId, [
                'amount' => (float) ($record['paid_amount'] ?? $record['amount'] ?? 0),
                'currency' => (string) ($record['paid_currency'] ?? $record['currency'] ?? PAYMENT_CURRENCY),
                'method' => (string) ($record['payment_method'] ?? 'PayHere'),
                'paid_at' => (string) ($record['payment_updated_at'] ?? date('Y-m-d H:i:s')),
            ]);

            $freshStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1');
            $freshStmt->execute([':id' => $bookingId]);
            $freshBooking = $freshStmt->fetch();
            if ($freshBooking) {
                $record = array_merge($record, $freshBooking);
            }
        } catch (Throwable $exception) {
            error_log('Public payment status invoice generation failed: ' . $exception->getMessage());
        }
    }

    $paymentHistory = load_payment_history($pdo, $bookingId);
    $bookingGroupId = (int) ($record['booking_group_id'] ?? 0);
    $groupRooms = $bookingGroupId > 0 ? multi_room_group_rows($pdo, $bookingGroupId) : [];
    $groupBookingNo = $bookingGroupId > 0
        ? 'MB-' . str_pad((string) $bookingGroupId, 6, '0', STR_PAD_LEFT)
        : 'BK-' . str_pad((string) $bookingId, 5, '0', STR_PAD_LEFT);
    $displayRoomName = $groupRooms
        ? implode(', ', array_map(static fn(array $room): string => (string) $room['room_name'], $groupRooms))
        : (string) ($record['room_name'] ?? '');
    $displayGuests = $groupRooms
        ? array_sum(array_map(static fn(array $room): int => (int) $room['guests'], $groupRooms))
        : (int) ($record['guests'] ?? 0);

    $expiresAt = booking_expires_at($record);
    $secondsRemaining = $paymentStatus === 'Payment Pending' ? booking_seconds_remaining($record) : 0;
    $paymentMethod = strtolower((string) ($record['payment_method'] ?? ''));
    $isOnlinePayment = str_contains($paymentMethod, 'payhere');
    $canRetryPayment = $isOnlinePayment
        && in_array($paymentStatus, ['Payment Pending', 'Failed', 'Cancelled'], true)
        && (string) ($record['status'] ?? '') !== 'Confirmed';

    $invoiceDownloadUrl = null;
    if ($paymentStatus === 'Paid' && !empty($record['invoice_file_path'])) {
        $baseApiUrl = API_BASE_URL !== '' ? API_BASE_URL : rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/payments')), '/');
        $invoiceDownloadUrl = $baseApiUrl . '/invoices/download.php?' . http_build_query([
            'id' => $bookingId,
            'booking_no' => $groupBookingNo,
            'booking_group_id' => $bookingGroupId ?: null,
            'token' => public_invoice_download_token($bookingId),
        ]);
    }

    json_response(true, 'Payment status loaded.', 200, [
        'booking' => [
            'id' => $bookingId,
            'full_name' => (string) ($record['full_name'] ?? ''),
            'email' => (string) ($record['email'] ?? ''),
            'phone' => (string) ($record['phone'] ?? ''),
            'room_name' => $displayRoomName,
            'check_in_date' => (string) ($record['check_in_date'] ?? ''),
            'check_out_date' => (string) ($record['check_out_date'] ?? ''),
            'guests' => $displayGuests,
            'booking_status' => (string) ($record['status'] ?? 'Pending'),
            'payment_status' => $paymentStatus,
            'amount' => (float) ($record['paid_amount'] ?? $record['amount'] ?? 0),
            'currency' => (string) ($record['paid_currency'] ?? $record['currency'] ?? PAYMENT_CURRENCY),
            'invoice_number' => (string) ($record['invoice_number'] ?? $record['payment_invoice_number'] ?? ''),
            'order_id' => $orderId,
            'payment_id' => (string) ($record['payment_id'] ?? ''),
            'payment_method' => (string) ($record['payment_method'] ?? 'PayHere'),
            'room_url' => public_room_url($pdo, (string) ($record['room_name'] ?? '')),
            'invoice_download_url' => $invoiceDownloadUrl,
            'hold_minutes' => booking_hold_minutes(),
            'expires_at' => $expiresAt,
            'seconds_remaining' => $secondsRemaining,
            'can_retry_payment' => $canRetryPayment,
            'payment_history' => $paymentHistory,
            'rooms' => array_map(static fn(array $room): array => [
                'booking_id' => (int) $room['id'],
                'room_id' => (int) ($room['room_id'] ?? 0),
                'room_name' => (string) $room['room_name'],
                'guests' => (int) $room['guests'],
                'amount' => (float) $room['amount'],
                'currency' => (string) $room['currency'],
            ], $groupRooms),
        ],
    ]);
} catch (Throwable $exception) {
    error_log('Public payment status error: ' . $exception->getMessage());
    json_response(false, 'Unable to load payment status.', 500);
}
