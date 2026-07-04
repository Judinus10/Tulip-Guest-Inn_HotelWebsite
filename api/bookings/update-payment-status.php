<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

apply_cors_headers();
require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

$data = read_request_data();
$id = (int) ($data['id'] ?? 0);
$rawStatus = clean_string($data['payment_status'] ?? '', 40);
$paymentMethod = clean_string($data['payment_method'] ?? 'Manual', 60);

$statusKey = strtolower(trim($rawStatus));
$statusKey = preg_replace('/^payment\s+/', '', $statusKey) ?? $statusKey;
$statusKey = str_replace([' ', '-'], '_', $statusKey);

$statusMap = [
    'pending' => 'Payment Pending',
    'payment_pending' => 'Payment Pending',
    'paid' => 'Paid',
    'failed' => 'Failed',
    'cancelled' => 'Cancelled',
    'canceled' => 'Cancelled',
    'refunded' => 'Refunded',
    'no_pay' => 'No Pay',
    'nopay' => 'No Pay',
    'no_payment' => 'No Pay',
];

$paymentStatus = $statusMap[$statusKey] ?? null;

if ($id < 1 || $paymentStatus === null) {
    json_response(false, 'Valid booking ID and payment status are required.', 422);
}

if ($paymentMethod === '') {
    $paymentMethod = 'Manual';
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $bookingStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE');
    $bookingStmt->execute([':id' => $id]);
    $booking = $bookingStmt->fetch();

    if (!$booking) {
        $pdo->rollBack();
        json_response(false, 'Booking was not found.', 404);
    }

    $updateBooking = $pdo->prepare('UPDATE bookings SET payment_status = :payment_status, updated_at = NOW() WHERE id = :id');
    $updateBooking->execute([
        ':payment_status' => $paymentStatus,
        ':id' => $id,
    ]);

    $amount = (float) ($booking['amount'] ?? 0);
    $currency = (string) ($booking['currency'] ?? PAYMENT_CURRENCY);
    $orderId = 'MANUAL-' . date('YmdHis') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);

    $paymentStmt = $pdo->prepare('SELECT id FROM payments WHERE booking_id = :booking_id ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $paymentStmt->execute([':booking_id' => $id]);
    $existingPayment = $paymentStmt->fetch();

    $gatewayResponse = json_encode([
        'source' => 'admin_manual_update',
        'updated_at' => date('Y-m-d H:i:s'),
        'payment_status' => $paymentStatus,
        'payment_method' => $paymentMethod,
    ], JSON_UNESCAPED_SLASHES);

    if ($existingPayment) {
        $updatePayment = $pdo->prepare(
            'UPDATE payments
             SET amount = :amount,
                 currency = :currency,
                 status = :status,
                 method = :method,
                 gateway_response = :gateway_response,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $updatePayment->execute([
            ':amount' => $amount,
            ':currency' => $currency,
            ':status' => $paymentStatus,
            ':method' => $paymentMethod,
            ':gateway_response' => $gatewayResponse,
            ':id' => (int) $existingPayment['id'],
        ]);
    } else {
        $insertPayment = $pdo->prepare(
            'INSERT INTO payments (booking_id, order_id, amount, currency, status, method, gateway_response, created_at, updated_at)
             VALUES (:booking_id, :order_id, :amount, :currency, :status, :method, :gateway_response, NOW(), NOW())'
        );
        $insertPayment->execute([
            ':booking_id' => $id,
            ':order_id' => $orderId,
            ':amount' => $amount,
            ':currency' => $currency,
            ':status' => $paymentStatus,
            ':method' => $paymentMethod,
            ':gateway_response' => $gatewayResponse,
        ]);
    }

    $pdo->commit();

    json_response(true, 'Payment status updated successfully.', 200, [
        'data' => [
            'id' => $id,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Admin update payment status error: ' . $e->getMessage());
    json_response(false, 'Unable to update payment status.', 500);
}
