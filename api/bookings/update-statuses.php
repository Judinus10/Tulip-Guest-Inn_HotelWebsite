<?php
/**
 * Unified admin status update endpoint.
 * Updates booking status and payment status/method in one request and sends one email when both changed.
 */

declare(strict_types=1);

// Some hosts print first-run mail-queue warnings/notices into the response.
// Buffer all incidental output so the admin always receives valid JSON.
ob_start();

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mail/email-helper.php';
require_once __DIR__ . '/multi-room-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

function admin_booking_status_for_db(mixed $status): string
{
    $value = strtolower(trim((string) $status));
    $value = str_replace([' ', '-'], '_', $value);

    return match ($value) {
        'confirmed' => 'Confirmed',
        'checked_in', 'checkedin' => 'Checked In',
        'checked_out', 'checkedout' => 'Checked Out',
        'cancelled', 'canceled' => 'Cancelled',
        'no_show', 'noshow' => 'No Show',
        default => 'Pending',
    };
}

function admin_payment_status_for_db_unified(mixed $status): string
{
    $value = strtolower(trim((string) $status));
    $value = preg_replace('/^payment\s+/', '', $value) ?? $value;
    $value = str_replace([' ', '-'], '_', $value);

    return match ($value) {
        // Online PayHere payment must be marked Paid only by api/payments/payhere-notify.php.
        'paid' => 'Paid',
        'cancelled', 'canceled' => 'Cancelled',
        'refunded' => 'Refunded',
        'no_pay', 'nopay', 'no_payment' => 'No Pay',
        // Failed is not accepted here. Gateway notify should write Failed.
        default => 'Payment Pending',
    };
}

function admin_payment_method_for_db_unified(mixed $method): string
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
        default => $value !== '' ? mb_substr($value, 0, 60) : 'Manual',
    };
}

try {
    $data = read_request_data();
    $bookingId = (int) ($data['id'] ?? $data['booking_id'] ?? 0);

    if ($bookingId < 1) {
        json_response(false, 'Booking ID is required.', 422);
    }

    $sendEmail = !array_key_exists('send_email', $data) || filter_var($data['send_email'], FILTER_VALIDATE_BOOLEAN);
    $bookingStatus = admin_booking_status_for_db($data['booking_status'] ?? $data['status'] ?? 'Pending');
    $requestedPaymentStatusRaw = (string) ($data['payment_status'] ?? 'Payment Pending');
    $requestedPaymentStatusNormalized = strtolower(str_replace([' ', '-'], '_', trim($requestedPaymentStatusRaw)));

    $submittedPaymentMethod = admin_payment_method_for_db_unified($data['payment_method'] ?? 'Manual');
    if ($submittedPaymentMethod === 'PayHere' && in_array($requestedPaymentStatusNormalized, ['paid', 'payment_paid'], true)) {
        json_response(false, 'Paid status is locked. PayHere payments can only be marked Paid by the verified PayHere notify webhook.', 403);
    }

    $paymentStatus = admin_payment_status_for_db_unified($requestedPaymentStatusRaw);
    $paymentMethod = $submittedPaymentMethod;
    $reference = clean_string($data['transaction_reference'] ?? $data['reference'] ?? '', 100);
    $remarks = clean_string($data['remarks'] ?? '', 1000);

    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $bookingStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE');
    $bookingStmt->execute([':id' => $bookingId]);
    $booking = $bookingStmt->fetch();

    if (!$booking) {
        $pdo->rollBack();
        json_response(false, 'Booking was not found.', 404);
    }

    $oldBookingStatus = (string) ($booking['status'] ?? 'Pending');
    $oldPaymentStatus = (string) ($booking['payment_status'] ?? 'Payment Pending');

    $cashConfirmationAllowed = $bookingStatus === 'Confirmed' && $paymentMethod === 'Cash';
    if (in_array($bookingStatus, ['Confirmed', 'Checked In'], true) && $paymentStatus !== 'Paid' && $paymentStatus !== 'No Pay' && !$cashConfirmationAllowed) {
        $pdo->rollBack();
        json_response(false, 'Payment must be Paid or No Pay before confirming or checking in.', 409);
    }

    $allowedTransitions = [
        'Pending' => ['Pending', 'Confirmed', 'Cancelled', 'No Show'],
        'Confirmed' => ['Confirmed', 'Checked In', 'Cancelled', 'No Show'],
        'Checked In' => ['Checked In', 'Checked Out'],
        'Checked Out' => ['Checked Out'],
        'Cancelled' => ['Cancelled'],
        'No Show' => ['No Show'],
    ];
    if (!in_array($bookingStatus, $allowedTransitions[$oldBookingStatus] ?? [$oldBookingStatus], true)) {
        $pdo->rollBack();
        json_response(false, 'This booking status change is not allowed from its current status.', 409);
    }

    if ($bookingStatus === 'Confirmed' && strcasecmp($oldBookingStatus, $bookingStatus) !== 0) {
        $conflict = $pdo->prepare(
            "SELECT id
             FROM bookings
             WHERE id <> :id
               AND room_name = :room_name
               AND status IN ('Confirmed', 'Checked In')
               AND :requested_check_in < check_out_date
               AND :requested_check_out > check_in_date
             LIMIT 1"
        );
        $conflict->execute([
            ':id' => $bookingId,
            ':room_name' => $booking['room_name'],
            ':requested_check_in' => $booking['check_in_date'],
            ':requested_check_out' => $booking['check_out_date'],
        ]);

        if ($conflict->fetch()) {
            $pdo->rollBack();
            json_response(false, 'Cannot confirm this booking because the room is already confirmed for overlapping dates.', 409);
        }
    }

    $groupId = (int) ($booking['booking_group_id'] ?? 0);
    if ($groupId > 0) {
        $updateBooking = $pdo->prepare(
            'UPDATE bookings SET status = :status, payment_status = :payment_status, updated_at = NOW()
             WHERE booking_group_id = :group_id'
        );
        $updateBooking->execute([':status' => $bookingStatus, ':payment_status' => $paymentStatus, ':group_id' => $groupId]);
    } else {
        $updateBooking = $pdo->prepare(
            'UPDATE bookings SET status = :status, payment_status = :payment_status, updated_at = NOW()
             WHERE id = :id'
        );
        $updateBooking->execute([':status' => $bookingStatus, ':payment_status' => $paymentStatus, ':id' => $bookingId]);
    }

    $groupRows = $groupId > 0 ? multi_room_group_rows($pdo, $groupId) : [];
    $amount = $groupRows
        ? array_sum(array_map(static fn(array $row): float => (float) $row['amount'], $groupRows))
        : (float) ($booking['amount'] ?? 0);
    $currency = (string) ($booking['currency'] ?? PAYMENT_CURRENCY);
    $orderId = 'MANUAL-' . date('YmdHis') . '-' . str_pad((string) $bookingId, 5, '0', STR_PAD_LEFT);
    $gatewayResponse = json_encode([
        'source' => 'admin_combined_status_update',
        'updated_at' => date('Y-m-d H:i:s'),
        'booking_status' => $bookingStatus,
        'payment_status' => $paymentStatus,
        'payment_method' => $paymentMethod,
        'remarks' => $remarks,
    ], JSON_UNESCAPED_SLASHES);

    $paymentBookingId = $groupId > 0 ? multi_room_primary_booking_id($pdo, $groupId) : $bookingId;
    $paymentStmt = $pdo->prepare('SELECT * FROM payments WHERE booking_id = :booking_id ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $paymentStmt->execute([':booking_id' => $paymentBookingId]);
    $payment = $paymentStmt->fetch();

    if ($payment) {
        $updateSql = 'UPDATE payments
             SET amount = :amount,
                 currency = :currency,
                 status = :status,
                 method = :method,
                 gateway_response = :gateway_response,
                 updated_at = NOW()';
        $params = [
            ':amount' => $amount,
            ':currency' => $currency,
            ':status' => $paymentStatus,
            ':method' => $paymentMethod,
            ':gateway_response' => $gatewayResponse,
            ':id' => (int) $payment['id'],
        ];
        if ($reference !== '') {
            $updateSql .= ', payment_id = :payment_id';
            $params[':payment_id'] = $reference;
        }
        $updateSql .= ' WHERE id = :id';
        $updatePayment = $pdo->prepare($updateSql);
        $updatePayment->execute($params);
        $paymentId = (int) $payment['id'];
    } else {
        $insertPayment = $pdo->prepare(
            'INSERT INTO payments (booking_id, order_id, payment_id, amount, currency, status, method, gateway_response, created_at, updated_at)
             VALUES (:booking_id, :order_id, :payment_id, :amount, :currency, :status, :method, :gateway_response, NOW(), NOW())'
        );
        $insertPayment->execute([
            ':booking_id' => $paymentBookingId,
            ':order_id' => $orderId,
            ':payment_id' => $reference,
            ':amount' => $amount,
            ':currency' => $currency,
            ':status' => $paymentStatus,
            ':method' => $paymentMethod,
            ':gateway_response' => $gatewayResponse,
        ]);
        $paymentId = (int) $pdo->lastInsertId();
    }

    $freshBookingStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1');
    $freshBookingStmt->execute([':id' => $bookingId]);
    $freshBooking = $freshBookingStmt->fetch() ?: $booking;
    if ($groupRows) {
        $freshBooking['booking_no'] = multi_room_booking_number($freshBooking);
        $freshBooking['room_name'] = implode(', ', array_column($groupRows, 'room_name'));
        $freshBooking['guests'] = array_sum(array_map(static fn(array $row): int => (int) $row['guests'], $groupRows));
        $freshBooking['amount'] = $amount;
    }

    $freshPaymentStmt = $pdo->prepare('SELECT * FROM payments WHERE id = :id LIMIT 1');
    $freshPaymentStmt->execute([':id' => $paymentId]);
    $freshPayment = $freshPaymentStmt->fetch() ?: [];

    $pdo->commit();

    $bookingChanged = strcasecmp($oldBookingStatus, $bookingStatus) !== 0;
    $paymentChanged = strcasecmp($oldPaymentStatus, $paymentStatus) !== 0;

    if ($sendEmail && filter_var((string) ($freshBooking['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        try {
            if ($bookingChanged && $paymentChanged) {
                send_combined_status_changed_email($pdo, $freshBooking, $freshPayment, $oldBookingStatus, $bookingStatus, $oldPaymentStatus, $paymentStatus);
            } elseif ($bookingChanged) {
                if ($bookingStatus === 'Confirmed') {
                    send_booking_confirmed_email($pdo, $freshBooking);
                } elseif ($bookingStatus === 'Cancelled') {
                    send_booking_cancelled_emails($pdo, $freshBooking);
                } else {
                    send_booking_status_changed_email($pdo, $freshBooking, $oldBookingStatus, $bookingStatus);
                }
            } elseif ($paymentChanged) {
                send_payment_status_changed_email($pdo, $freshBooking, $freshPayment, $oldPaymentStatus, $paymentStatus);
            }
        } catch (Throwable $emailError) {
            error_log('Unified status email error: ' . $emailError->getMessage());
        }
    }

    if (ob_get_length() !== false && ob_get_length() > 0) {
        ob_clean();
    }
    json_response(true, 'Statuses updated successfully.', 200, [
        'data' => [
            'id' => $bookingId,
            'booking_status' => strtolower($bookingStatus),
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
            'payment_id' => $paymentId,
            'email_sent' => $sendEmail && ($bookingChanged || $paymentChanged),
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Unified admin status update error: ' . $e->getMessage());
    if (ob_get_length() !== false && ob_get_length() > 0) {
        ob_clean();
    }
    json_response(false, 'Unable to update statuses.', 500);
}
