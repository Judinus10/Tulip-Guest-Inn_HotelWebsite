<?php
/**
 * Admin payment status/method update endpoint.
 * Keeps PayHere failed updates gateway-controlled: the admin UI does not offer Failed.
 */

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

function admin_payment_status_for_db(mixed $status): string
{
    $value = strtolower(trim((string) $status));
    $value = preg_replace('/[\s\-]+/', '_', $value) ?? '';
    $value = preg_replace('/^payment_/', '', $value) ?? '';

    return match ($value) {
        // Online PayHere payment must be marked Paid only by api/payments/payhere-notify.php.
        // Admin may still use Cancelled/Refunded/No Pay for non-success adjustments.
        'paid' => 'Payment Pending',
        'cancelled', 'canceled' => 'Cancelled',
        'refunded' => 'Refunded',
        'no_pay', 'nopay', 'no_payment' => 'No Pay',
        // Failed is intentionally not accepted from the admin form.
        // Failed should be written by the payment gateway notify flow.
        default => 'Payment Pending',
    };
}

function admin_payment_method_for_db(mixed $method): string
{
    $value = clean_string($method, 60);
    $normalized = strtolower(preg_replace('/[\s\-_]+/', '_', $value) ?? '');

    return match ($normalized) {
        'payhere' => 'PayHere',
        'cash' => 'Cash',
        'bank_transfer', 'bank' => 'Bank Transfer',
        'card', 'card_pos', 'pos' => 'Card',
        'no_pay', 'nopay', 'no_payment' => 'No Pay',
        'other' => 'Other',
        default => $value !== '' ? mb_substr($value, 0, 60) : 'PayHere',
    };
}

try {
    $data = read_request_data();

    $paymentId = (int) ($data['id'] ?? $data['payment_id'] ?? 0);
    if ($paymentId <= 0) {
        json_response(false, 'Payment ID is required.', 422);
    }

    $requestedStatusRaw = (string) ($data['payment_status'] ?? $data['status'] ?? 'Payment Pending');
    $requestedStatusNormalized = strtolower(str_replace([' ', '-'], '_', trim($requestedStatusRaw)));

    if (in_array($requestedStatusNormalized, ['paid', 'payment_paid'], true)) {
        json_response(false, 'Paid status is locked. PayHere payments can only be marked Paid by the verified PayHere notify webhook.', 200, [
            'severity' => 'warning',
            'error_code' => 'PAYHERE_PAID_LOCKED',
        ]);
    }

    $paymentStatus = admin_payment_status_for_db($requestedStatusRaw);
    $paymentMethod = admin_payment_method_for_db($data['payment_method'] ?? $data['method'] ?? 'PayHere');
    $reference = clean_string($data['transaction_reference'] ?? $data['reference'] ?? '', 100);

    $pdo = get_db_connection();

    $currentStmt = $pdo->prepare('SELECT id, booking_id FROM payments WHERE id = :id LIMIT 1');
    $currentStmt->execute([':id' => $paymentId]);
    $currentPayment = $currentStmt->fetch();

    if (!$currentPayment) {
        json_response(false, 'Payment record not found.', 404);
    }

    $updateSql = 'UPDATE payments SET status = :status, method = :method, updated_at = NOW()';
    $updateParams = [
        ':status' => $paymentStatus,
        ':method' => $paymentMethod,
        ':id' => $paymentId,
    ];

    if ($reference !== '') {
        $updateSql .= ', payment_id = :payment_id';
        $updateParams[':payment_id'] = $reference;
    }

    $updateSql .= ' WHERE id = :id';
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute($updateParams);

    if (!empty($currentPayment['booking_id'])) {
        $bookingStmt = $pdo->prepare(
            'UPDATE bookings
             SET payment_status = :payment_status,
                 updated_at = NOW()
             WHERE id = :booking_id'
        );
        $bookingStmt->execute([
            ':payment_status' => $paymentStatus,
            ':booking_id' => (int) $currentPayment['booking_id'],
        ]);
    }

    $selectStmt = $pdo->prepare(
        "SELECT
            p.id,
            p.booking_id,
            CONCAT('BK-', LPAD(COALESCE(p.booking_id, 0), 5, '0')) AS booking_no,
            COALESCE(b.full_name, 'Unknown Guest') AS guest_name,
            COALESCE(b.email, '') AS guest_email,
            COALESCE(b.room_name, '-') AS room_name,
            p.order_id,
            p.payment_id,
            p.amount,
            p.currency,
            p.status AS payment_status,
            p.method AS payment_method,
            p.method AS payment_gateway,
            COALESCE(NULLIF(p.payment_id, ''), NULLIF(p.order_id, ''), CONCAT('PAY-', LPAD(p.id, 4, '0'))) AS transaction_id,
            p.invoice_id,
            COALESCE(p.invoice_number, b.invoice_number, '') AS invoice_number,
            COALESCE(b.invoice_file_path, '') AS invoice_file_path,
            COALESCE(b.email_status, 'Pending') AS email_status,
            CASE WHEN p.status = 'Paid' THEN p.updated_at ELSE NULL END AS paid_at,
            p.created_at,
            p.updated_at
         FROM payments p
         LEFT JOIN bookings b ON b.id = p.booking_id
         WHERE p.id = :id
         LIMIT 1"
    );
    $selectStmt->execute([':id' => $paymentId]);
    $updatedPayment = $selectStmt->fetch();

    json_response(true, 'Payment updated successfully.', 200, [
        'data' => $updatedPayment,
    ]);
} catch (Throwable $e) {
    error_log('Admin payment update error: ' . $e->getMessage());
    json_response(false, 'Unable to update payment. Check the server error log for details.', 500);
}
