<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mail/email-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

$data = read_request_data();
$bookingId = (int) ($data['id'] ?? 0);
function normalize_workflow_status(mixed $value, string $prefix = ''): string
{
    $normalized = strtolower(trim((string) $value));
    $normalized = preg_replace('/\s+/', '_', $normalized) ?? $normalized;
    $normalized = str_replace('-', '_', $normalized);
    if ($prefix !== '') {
        $normalized = preg_replace('/^' . preg_quote($prefix, '/') . '_+/', '', $normalized) ?? $normalized;
    }
    return trim($normalized, '_');
}

$requestedKey = normalize_workflow_status($data['status'] ?? '', 'booking');

$statusMap = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'checked_in' => 'Checked In',
    'checked_out' => 'Checked Out',
    'cancelled' => 'Cancelled',
    'canceled' => 'Cancelled',
    'no_show' => 'No Show',
];

if ($bookingId < 1 || !isset($statusMap[$requestedKey])) {
    json_response(false, 'Invalid booking or status.', 422);
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, status, payment_status FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE');
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();
    if (!$booking) {
        $pdo->rollBack();
        json_response(false, 'Booking not found.', 404);
    }

    $currentStatus = normalize_workflow_status($booking['status'], 'booking');
    $paymentStatus = normalize_workflow_status($booking['payment_status'], 'payment');
    $paymentAliases = [
        'paid' => 'paid',
        'no_pay' => 'no_pay',
        'nopay' => 'no_pay',
        'no_payment' => 'no_pay',
    ];
    $paymentStatus = $paymentAliases[$paymentStatus] ?? $paymentStatus;
    $paymentAllowed = in_array($paymentStatus, ['paid', 'no_pay'], true);

    if ($requestedKey === 'confirmed' && (!$paymentAllowed || $currentStatus !== 'pending')) {
        $pdo->rollBack();
        json_response(false, 'Only a pending booking with Paid or No Pay payment can be confirmed. Current booking status: ' . $currentStatus . '; payment status: ' . $paymentStatus . '.', 409);
    }
    if ($requestedKey === 'checked_in' && (!$paymentAllowed || $currentStatus !== 'confirmed')) {
        $pdo->rollBack();
        json_response(false, 'Confirm the booking and set payment to Paid or No Pay before check-in.', 409);
    }
    if ($requestedKey === 'checked_out' && $currentStatus !== 'checked_in') {
        $pdo->rollBack();
        json_response(false, 'A booking can be checked out only after it is checked in.', 409);
    }
    if ($requestedKey === 'cancelled' && in_array($currentStatus, ['cancelled', 'checked_out'], true)) {
        $pdo->rollBack();
        json_response(false, 'This booking can no longer be cancelled.', 409);
    }

    $newStatus = $statusMap[$requestedKey];
    $update = $pdo->prepare('UPDATE bookings SET status = :status, updated_at = NOW() WHERE id = :id');
    $update->execute([':status' => $newStatus, ':id' => $bookingId]);
    $pdo->commit();

    if ($requestedKey === 'confirmed' && $paymentAllowed && $currentStatus !== 'confirmed') {
        try {
            $freshStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1');
            $freshStmt->execute([':id' => $bookingId]);
            $freshBooking = $freshStmt->fetch();
            if ($freshBooking) {
                cancel_pending_payment_email_jobs($pdo, $bookingId, 'Skipped because the paid booking was confirmed before the pending reminder.');
                queue_payment_success_emails($pdo, $freshBooking, [
                    'amount' => (float) ($freshBooking['amount'] ?? 0),
                    'currency' => (string) ($freshBooking['currency'] ?? PAYMENT_CURRENCY),
                    'method' => 'Manual',
                ]);
            }
        } catch (Throwable $emailException) {
            error_log('Booking confirmed but confirmation emails could not be queued for booking #' . $bookingId . ': ' . $emailException->getMessage());
        }
    }

    json_response(true, 'Booking status updated.', 200, [
        'data' => [
            'id' => $bookingId,
            'status' => $newStatus,
            'booking_status' => strtolower(str_replace(' ', '_', $newStatus)),
        ],
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Booking status update failed: ' . $exception->getMessage());
    json_response(false, 'Unable to update booking status.', 500);
}
