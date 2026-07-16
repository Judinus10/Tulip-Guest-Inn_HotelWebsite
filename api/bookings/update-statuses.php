<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

function combined_status_key(mixed $value, string $prefix = ''): string
{
    $key = strtolower(trim((string) $value));
    $key = preg_replace('/\s+/', '_', $key) ?? $key;
    $key = str_replace('-', '_', $key);
    if ($prefix !== '') {
        $key = preg_replace('/^' . preg_quote($prefix, '/') . '_+/', '', $key) ?? $key;
    }
    return trim($key, '_');
}

$data = read_request_data();
$bookingId = (int) ($data['id'] ?? 0);
$bookingKey = combined_status_key($data['booking_status'] ?? 'pending', 'booking');
$paymentKey = combined_status_key($data['payment_status'] ?? 'payment_pending', 'payment');
$paymentMethod = clean_string($data['payment_method'] ?? 'Manual', 60);
$reference = clean_string($data['transaction_reference'] ?? '', 100);
$remarks = clean_string($data['remarks'] ?? '', 1000);

$bookingMap = [
    'pending' => 'Pending', 'confirmed' => 'Confirmed',
    'checked_in' => 'Checked In', 'checked_out' => 'Checked Out',
    'cancelled' => 'Cancelled', 'canceled' => 'Cancelled', 'no_show' => 'No Show',
];
$paymentMap = [
    'pending' => 'Payment Pending', 'payment_pending' => 'Payment Pending',
    'paid' => 'Paid', 'failed' => 'Failed',
    'cancelled' => 'Cancelled', 'canceled' => 'Cancelled',
    'refunded' => 'Refunded', 'no_pay' => 'No Pay',
    'nopay' => 'No Pay', 'no_payment' => 'No Pay',
];

if ($bookingId < 1 || !isset($bookingMap[$bookingKey]) || !isset($paymentMap[$paymentKey]) || $paymentMethod === '') {
    json_response(false, 'Invalid booking or payment update.', 422);
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $bookingStmt = $pdo->prepare('SELECT id FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE');
    $bookingStmt->execute([':id' => $bookingId]);
    if (!$bookingStmt->fetch()) {
        $pdo->rollBack();
        json_response(false, 'Booking not found.', 404);
    }

    $bookingStatus = $bookingMap[$bookingKey];
    $paymentStatus = $paymentMap[$paymentKey];
    $updateBooking = $pdo->prepare(
        'UPDATE bookings SET status = :booking_status, payment_status = :payment_status, updated_at = NOW() WHERE id = :id'
    );
    $updateBooking->execute([
        ':booking_status' => $bookingStatus,
        ':payment_status' => $paymentStatus,
        ':id' => $bookingId,
    ]);

    $paymentStmt = $pdo->prepare('SELECT id, gateway_response FROM payments WHERE booking_id = :booking_id ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $paymentStmt->execute([':booking_id' => $bookingId]);
    $payment = $paymentStmt->fetch();

    if ($payment) {
        $gatewayResponse = json_decode((string) ($payment['gateway_response'] ?? ''), true);
        if (!is_array($gatewayResponse)) {
            $gatewayResponse = [];
        }
        $gatewayResponse['manual_update'] = [
            'reference' => $reference,
            'remarks' => $remarks,
            'updated_at' => date(DATE_ATOM),
        ];

        $updatePayment = $pdo->prepare(
            'UPDATE payments SET status = :status, method = :method,
             payment_id = CASE WHEN :reference_check <> \'\' THEN :reference_value ELSE payment_id END,
             gateway_response = :gateway_response, updated_at = NOW() WHERE id = :id'
        );
        $updatePayment->execute([
            ':status' => $paymentStatus,
            ':method' => $paymentMethod,
            ':reference_check' => $reference,
            ':reference_value' => $reference,
            ':gateway_response' => json_encode($gatewayResponse, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':id' => (int) $payment['id'],
        ]);
    }

    $pdo->commit();
    json_response(true, 'Booking and payment statuses updated.', 200, [
        'data' => [
            'id' => $bookingId,
            'booking_status' => combined_status_key($bookingStatus),
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
        ],
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Combined booking/payment update failed: ' . $exception->getMessage());
    json_response(false, 'Unable to save the booking and payment update.', 500);
}
