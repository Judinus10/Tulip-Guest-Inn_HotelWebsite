<?php
/**
 * PayHere server notification endpoint.
 *
 * High-priority fixes included:
 * 1. Only this webhook can mark online PayHere payments as Paid and bookings as Confirmed.
 * 2. Merchant ID, signature, order ID, amount, and currency are verified before confirmation.
 * 3. Duplicate PayHere notifications are idempotent and do not resend invoices/emails.
 * 4. Expired pending bookings are released before non-success statuses are applied.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../bookings/booking-expiry-helper.php';
require_once __DIR__ . '/../bookings/booking-audit-helper.php';
require_once __DIR__ . '/../invoices/invoice-helper.php';
require_once __DIR__ . '/../calendar/ics-helper.php';
require_once __DIR__ . '/../mail/email-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Only POST allowed');
}

function notify_text_response(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function normalize_payhere_amount(string|float|int $amount): string
{
    return number_format((float) $amount, 2, '.', '');
}

function payhere_status_for_db(string $statusCode): string
{
    return match ($statusCode) {
        '2' => 'Paid',
        '0' => 'Payment Pending',
        '-1' => 'Cancelled',
        '-2' => 'Failed',
        '-3' => 'Refunded',
        default => 'Failed',
    };
}

$merchantId = clean_string($_POST['merchant_id'] ?? '', 100);
$orderId = clean_string($_POST['order_id'] ?? '', 100);
$paymentId = clean_string($_POST['payment_id'] ?? '', 100);
$payhereAmountRaw = clean_string($_POST['payhere_amount'] ?? '', 50);
$payhereAmount = normalize_payhere_amount($payhereAmountRaw);
$payhereCurrency = strtoupper(clean_string($_POST['payhere_currency'] ?? '', 10));
$statusCode = clean_string($_POST['status_code'] ?? '', 10);
$md5sig = strtoupper(clean_string($_POST['md5sig'] ?? '', 100));
$method = clean_string($_POST['method'] ?? 'PayHere', 60);
$statusMessage = clean_string($_POST['status_message'] ?? '', 500);
$postedBookingId = (int) clean_string($_POST['custom_1'] ?? '0', 20);

if ($merchantId === '' || $orderId === '' || $payhereAmountRaw === '' || $payhereCurrency === '' || $statusCode === '' || $md5sig === '') {
    notify_text_response(400, 'Missing required PayHere fields');
}

if (PAYHERE_MERCHANT_ID === '' || PAYHERE_MERCHANT_SECRET === '') {
    error_log('PayHere notify rejected: backend PayHere credentials are missing.');
    notify_text_response(500, 'Payment gateway is not configured');
}

$localHash = generate_payhere_notify_hash($merchantId, $orderId, $payhereAmount, $payhereCurrency, $statusCode, PAYHERE_MERCHANT_SECRET);

if (!hash_equals((string) PAYHERE_MERCHANT_ID, $merchantId) || !hash_equals($localHash, $md5sig)) {
    error_log('PayHere notify rejected: invalid merchant/signature for order ' . $orderId);
    notify_text_response(403, 'Invalid PayHere signature');
}

$incomingPaymentStatus = payhere_status_for_db($statusCode);
$sideEffects = [
    'send_success' => false,
    'send_failed' => false,
    'failure_status' => '',
    'booking_id' => 0,
    'amount' => (float) $payhereAmount,
    'currency' => $payhereCurrency,
    'method' => $method ?: 'PayHere',
    'payment_id' => $paymentId,
];

try {
    $pdo = get_db_connection();
    ensure_booking_audit_table($pdo);
    $pdo->beginTransaction();

    $paymentStmt = $pdo->prepare('SELECT * FROM payments WHERE order_id = :order_id LIMIT 1 FOR UPDATE');
    $paymentStmt->execute([':order_id' => $orderId]);
    $existingPayment = $paymentStmt->fetch();

    if (!$existingPayment) {
        $pdo->rollBack();
        error_log('PayHere notify rejected: unknown order_id ' . $orderId);
        notify_text_response(404, 'Unknown order ID');
    }

    $bookingId = (int) ($existingPayment['booking_id'] ?? 0);

    if ($postedBookingId > 0 && $bookingId > 0 && $postedBookingId !== $bookingId) {
        $pdo->rollBack();
        error_log('PayHere notify rejected: booking ID mismatch for order ' . $orderId);
        notify_text_response(400, 'Booking mismatch');
    }

    if ($bookingId < 1 && $postedBookingId > 0) {
        $bookingId = $postedBookingId;
    }

    $booking = null;
    if ($bookingId > 0) {
        $bookingStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE');
        $bookingStmt->execute([':id' => $bookingId]);
        $booking = $bookingStmt->fetch() ?: null;
    }

    if (!$booking) {
        $pdo->rollBack();
        error_log('PayHere notify rejected: booking not found for order ' . $orderId);
        notify_text_response(404, 'Booking not found');
    }

    $sideEffects['booking_id'] = $bookingId;

    $expectedAmount = normalize_payhere_amount((string) ($existingPayment['amount'] ?? $booking['amount'] ?? 0));
    if ((float) $expectedAmount <= 0) {
        $expectedAmount = normalize_payhere_amount((string) calculate_booking_amount((string) $booking['room_name'], (string) $booking['check_in_date'], (string) $booking['check_out_date']));
    }
    $expectedCurrency = strtoupper((string) ($existingPayment['currency'] ?? $booking['currency'] ?? PAYMENT_CURRENCY));

    if (!hash_equals($expectedAmount, $payhereAmount) || !hash_equals($expectedCurrency, $payhereCurrency)) {
        $gatewayResponse = $_POST;
        $gatewayResponse['server_status_message'] = 'Rejected: amount or currency mismatch. Expected ' . $expectedAmount . ' ' . $expectedCurrency . ', received ' . $payhereAmount . ' ' . $payhereCurrency . '.';

        $rejectPayment = $pdo->prepare(
            'UPDATE payments
             SET payment_id = :payment_id,
                 status = :status,
                 method = :method,
                 gateway_response = :gateway_response,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $rejectPayment->execute([
            ':payment_id' => $paymentId ?: null,
            ':status' => 'Failed',
            ':method' => $method ?: 'PayHere',
            ':gateway_response' => json_encode($gatewayResponse, JSON_UNESCAPED_SLASHES),
            ':id' => (int) $existingPayment['id'],
        ]);

        $rejectBooking = $pdo->prepare(
            "UPDATE bookings
             SET payment_status = 'Failed', updated_at = NOW()
             WHERE id = :id AND payment_status <> 'Paid'"
        );
        $rejectBooking->execute([':id' => $bookingId]);

        $pdo->commit();
        error_log('PayHere notify rejected: amount/currency mismatch for order ' . $orderId);
        notify_text_response(400, 'Amount or currency mismatch');
    }

    $currentPaymentStatus = (string) ($existingPayment['status'] ?? 'Payment Pending');

    // Duplicate success notification: acknowledge without doing side effects again.
    if ($currentPaymentStatus === 'Paid' && $incomingPaymentStatus === 'Paid') {
        booking_audit_log($pdo, $bookingId, 'duplicate_payment_notify', 'Duplicate Payment Notification Ignored', 'PayHere sent a duplicate successful notification. No email or invoice was sent again.', [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
        ]);
        $pdo->commit();
        notify_text_response(200, 'OK duplicate ignored');
    }

    // If the user did not pay and the booking hold is expired, release the room now.
    if ($incomingPaymentStatus !== 'Paid') {
        expire_pending_bookings($pdo, $bookingId, false);
        $bookingStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE');
        $bookingStmt->execute([':id' => $bookingId]);
        $booking = $bookingStmt->fetch() ?: $booking;
    }

    $finalPaymentStatus = $incomingPaymentStatus;
    $finalBookingStatus = $finalPaymentStatus === 'Paid' ? 'Confirmed' : 'Pending';

    if ($finalPaymentStatus === 'Paid') {
        $conflictStatement = $pdo->prepare(
            "SELECT blocker.id
             FROM bookings current_booking
             INNER JOIN bookings blocker
               ON blocker.room_name = current_booking.room_name
              AND blocker.id <> current_booking.id
              AND blocker.status = 'Confirmed'
              AND current_booking.check_in_date < blocker.check_out_date
              AND current_booking.check_out_date > blocker.check_in_date
             WHERE current_booking.id = :booking_id
             LIMIT 1"
        );
        $conflictStatement->execute([':booking_id' => $bookingId]);

        $roomIdStmt = $pdo->prepare('SELECT id FROM rooms WHERE room_name = :room_name LIMIT 1');
        $roomIdStmt->execute([':room_name' => (string) ($booking['room_name'] ?? '')]);
        $roomId = (int) $roomIdStmt->fetchColumn();
        $bookingComConflict = $roomId > 0 && ics_room_conflict(
            $pdo,
            $roomId,
            (string) ($booking['check_in_date'] ?? ''),
            (string) ($booking['check_out_date'] ?? '')
        );

        if ($conflictStatement->fetch() || $bookingComConflict) {
            $finalPaymentStatus = 'Paid';
            $finalBookingStatus = 'Pending';
            $statusMessage = trim($statusMessage . ' Paid but booking has a confirmed overlap; manual review required.');
            error_log('Paid PayHere booking has availability conflict and needs manual review. Booking ID: ' . $bookingId);
        }
    }

    if (in_array($finalPaymentStatus, ['Failed', 'Cancelled', 'Refunded'], true)) {
        $finalBookingStatus = 'Cancelled';
    }

    $gatewayResponse = $_POST;
    $gatewayResponse['server_status_message'] = $statusMessage;
    $gatewayResponse['verified_by_server'] = true;

    $updatePayment = $pdo->prepare(
        'UPDATE payments
         SET booking_id = :booking_id,
             payment_id = :payment_id,
             amount = :amount,
             currency = :currency,
             status = :status,
             method = :method,
             gateway_response = :gateway_response,
             updated_at = NOW()
         WHERE id = :id'
    );
    $updatePayment->execute([
        ':booking_id' => $bookingId,
        ':payment_id' => $paymentId ?: null,
        ':amount' => (float) $payhereAmount,
        ':currency' => $payhereCurrency,
        ':status' => $finalPaymentStatus,
        ':method' => $method ?: 'PayHere',
        ':gateway_response' => json_encode($gatewayResponse, JSON_UNESCAPED_SLASHES),
        ':id' => (int) $existingPayment['id'],
    ]);

    $updateBooking = $pdo->prepare(
        'UPDATE bookings
         SET status = :status,
             payment_status = :payment_status,
             amount = :amount,
             currency = :currency,
             updated_at = NOW()
         WHERE id = :id'
    );
    $updateBooking->execute([
        ':status' => $finalBookingStatus,
        ':payment_status' => $finalPaymentStatus,
        ':amount' => (float) $payhereAmount,
        ':currency' => $payhereCurrency,
        ':id' => $bookingId,
    ]);

    if ($finalPaymentStatus === 'Paid') {
        booking_audit_log($pdo, $bookingId, 'payment_success', 'Payment Success', 'PayHere payment was verified and booking was confirmed.', [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'amount' => $payhereAmount,
            'currency' => $payhereCurrency,
        ]);
    } elseif (in_array($finalPaymentStatus, ['Failed', 'Cancelled', 'Refunded'], true)) {
        booking_audit_log($pdo, $bookingId, 'payment_' . strtolower($finalPaymentStatus), 'Payment ' . $finalPaymentStatus, 'PayHere reported that the payment was not completed.', [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'amount' => $payhereAmount,
            'currency' => $payhereCurrency,
            'status_message' => $statusMessage,
        ]);
    } else {
        booking_audit_log($pdo, $bookingId, 'payment_pending', 'Payment Pending', 'PayHere reported payment is still pending.', [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
        ]);
    }

    $pdo->commit();

    $sideEffects['send_success'] = $finalPaymentStatus === 'Paid' && $currentPaymentStatus !== 'Paid' && $finalBookingStatus === 'Confirmed';
    $sideEffects['send_failed'] = in_array($finalPaymentStatus, ['Failed', 'Cancelled', 'Refunded'], true) && $currentPaymentStatus !== $finalPaymentStatus;
    $sideEffects['failure_status'] = $sideEffects['send_failed'] ? $finalPaymentStatus : '';
    $sideEffects['amount'] = (float) $payhereAmount;
    $sideEffects['currency'] = $payhereCurrency;
    $sideEffects['method'] = $method ?: 'PayHere';
    $sideEffects['payment_id'] = $paymentId;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('PayHere notify database error: ' . $e->getMessage());
    notify_text_response(500, 'Server error');
}

if ($sideEffects['booking_id'] > 0) {
    try {
        $freshBooking = get_booking_by_id($pdo, (int) $sideEffects['booking_id']);

        if ($freshBooking) {
            if ($sideEffects['send_success']) {
                cancel_pending_payment_email_jobs($pdo, (int) $sideEffects['booking_id'], 'Skipped because PayHere confirmed payment before the 10-minute reminder.');
                booking_audit_log($pdo, (int) $sideEffects['booking_id'], 'invoice_generation_started', 'Invoice Generation Started', 'Creating invoice after verified PayHere payment.', []);

                $invoice = generate_invoice_for_booking($pdo, (int) $sideEffects['booking_id'], [
                    'amount' => (float) $sideEffects['amount'],
                    'currency' => (string) $sideEffects['currency'],
                    'method' => (string) $sideEffects['method'],
                    'paid_at' => date('Y-m-d H:i:s'),
                ]);

                $freshBooking = get_booking_by_id($pdo, (int) $sideEffects['booking_id']) ?: $freshBooking;
                booking_audit_log($pdo, (int) $sideEffects['booking_id'], 'invoice_generated', 'Invoice Generated', 'Invoice was generated for the successful payment.', [
                    'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
                ]);

                queue_payment_success_emails($pdo, $freshBooking, [
                    'amount' => (float) $sideEffects['amount'],
                    'currency' => (string) $sideEffects['currency'],
                    'method' => (string) $sideEffects['method'],
                    'payment_id' => (string) $sideEffects['payment_id'],
                    'transaction_id' => (string) $sideEffects['payment_id'],
                    'invoice' => $invoice,
                ]);
                booking_audit_log($pdo, (int) $sideEffects['booking_id'], 'success_email_queued', 'Success Email Queued', 'Payment success emails were queued for cron delivery to customer/admin.', []);
            } elseif ($sideEffects['send_failed']) {
                cancel_pending_payment_email_jobs($pdo, (int) $sideEffects['booking_id'], 'Skipped because PayHere returned ' . (string) $sideEffects['failure_status'] . ' before the 10-minute reminder.');
                queue_payment_failed_email($pdo, $freshBooking, (string) $sideEffects['failure_status']);
                booking_audit_log($pdo, (int) $sideEffects['booking_id'], 'failed_email_queued', 'Failed Payment Email Queued', 'Payment failed/cancelled emails were queued for cron delivery.', []);
            }
        }
    } catch (Throwable $e) {
        error_log('PayHere notify side-effect error after DB update: ' . $e->getMessage());
    }
}

notify_text_response(200, 'OK');
