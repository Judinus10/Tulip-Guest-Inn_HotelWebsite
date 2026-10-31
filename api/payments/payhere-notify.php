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
require_once __DIR__ . '/../payment-settings/_helpers.php';
require_once __DIR__ . '/../bookings/booking-expiry-helper.php';
require_once __DIR__ . '/../bookings/multi-room-helper.php';
require_once __DIR__ . '/../calendar/ics-helper.php';
require_once __DIR__ . '/../bookings/booking-audit-helper.php';
require_once __DIR__ . '/../invoices/invoice-helper.php';
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
        '-3' => 'Chargeback',
        default => 'Failed',
    };
}

/** Reentrant delivery: unique queue keys make gateway retries safe after a crash. */
function notify_queue_verified_result(PDO $pdo, int $bookingId, string $orderId): void
{
    $booking = get_booking_by_id($pdo, $bookingId);
    if (!$booking) return;
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY (status = 'Paid') DESC, id DESC LIMIT 1");
    $stmt->execute([$bookingId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    // A delayed failure for an older attempt must never send a current failure notice.
    if (!$payment || (string) $payment['order_id'] !== $orderId) return;
    if ((string) $payment['status'] === 'Paid') {
        queue_payment_success_emails($pdo, $booking, $payment);
        // Invoice generation must not prevent the payment receipt from being queued.
        try {
            generate_invoice_for_booking($pdo, $bookingId, [
                'amount' => (float) $payment['amount'], 'currency' => (string) $payment['currency'],
                'method' => 'PayHere', 'paid_at' => (string) ($payment['updated_at'] ?? date('Y-m-d H:i:s')),
            ]);
        } catch (Throwable $e) {
            error_log('Paid booking invoice generation failed: ' . $e->getMessage());
        }
    } elseif (in_array((string) $payment['status'], ['Failed','Cancelled'], true)) {
        queue_payment_failed_email($pdo, $booking, $orderId);
    }
}

$merchantId = clean_string($_POST['merchant_id'] ?? '', 100);
$orderId = clean_string($_POST['order_id'] ?? '', 100);
$paymentId = clean_string($_POST['payment_id'] ?? '', 100);
$payhereAmountRaw = clean_string($_POST['payhere_amount'] ?? '', 50);
$payhereAmount = normalize_payhere_amount($payhereAmountRaw);
$payhereCurrency = strtoupper(clean_string($_POST['payhere_currency'] ?? '', 10));
$statusCode = clean_string($_POST['status_code'] ?? '', 10);
$md5sig = strtoupper(clean_string($_POST['md5sig'] ?? '', 100));
$method = 'PayHere'; // Gateway identity is not taken from unsigned method metadata.
$statusMessage = clean_string($_POST['status_message'] ?? '', 500);
$postedBookingId = (int) clean_string($_POST['custom_1'] ?? '0', 20);

if ($merchantId === '' || $orderId === '' || $payhereAmountRaw === '' || $payhereCurrency === '' || $statusCode === '' || $md5sig === '') {
    notify_text_response(400, 'Missing required PayHere fields');
}

if (!in_array($statusCode, ['2', '0', '-1', '-2', '-3'], true) || !preg_match('/^[0-9]{1,12}(?:\.[0-9]{1,2})?$/', $payhereAmountRaw)) notify_text_response(400, 'Invalid payment fields');

if (payment_runtime()['merchant_id'] === '' || payment_runtime()['merchant_secret'] === '') {
    error_log('PayHere notify rejected: backend PayHere credentials are missing.');
    notify_text_response(500, 'Payment gateway is not configured');
}

$localHash = generate_payhere_notify_hash($merchantId, $orderId, $payhereAmount, $payhereCurrency, $statusCode, payment_runtime()['merchant_secret']);

if (!hash_equals((string) payment_runtime()['merchant_id'], $merchantId) || !hash_equals($localHash, $md5sig)) {
    error_log('PayHere notify rejected: invalid merchant/signature for order ' . $orderId);
    notify_text_response(403, 'Invalid PayHere signature');
}

$incomingPaymentStatus = payhere_status_for_db($statusCode);
$sideEffects = [
    'send_success' => false,
    'send_failed' => false,
    'booking_id' => 0,
    'amount' => (float) $payhereAmount,
    'currency' => $payhereCurrency,
    'method' => $method ?: 'PayHere',
    'payment_id' => $paymentId,
];

try {
    $pdo = get_db_connection();
    lock_booking_inventory($pdo);
    ensure_booking_expiry_events_table($pdo);
    ensure_booking_audit_table($pdo);
    ensure_email_queue_table($pdo);
    if (ics_enabled()) ensure_ics_schema($pdo);
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

    $scopeRows = multi_room_lock_booking_scope($pdo, $booking);
    $scopeIds = array_map(static fn(array $row): int => (int) $row['id'], $scopeRows);
    $scopeSqlIds = implode(',', $scopeIds);
    $sideEffects['booking_id'] = $bookingId;

    $expectedAmount = normalize_payhere_amount((string) ($existingPayment['amount'] ?? $booking['amount'] ?? 0));
    if ((float) $expectedAmount <= 0) {
        $expectedAmount = normalize_payhere_amount((string) calculate_booking_amount((int) ($booking['room_id'] ?? 0), (string) $booking['check_in_date'], (string) $booking['check_out_date']));
    }
    $expectedCurrency = strtoupper((string) ($existingPayment['currency'] ?? $booking['currency'] ?? PAYMENT_CURRENCY));

    if (!hash_equals($expectedAmount, $payhereAmount) || !hash_equals($expectedCurrency, $payhereCurrency)) {
        $pdo->rollBack();
        notify_text_response(400, 'Amount or currency mismatch');
    }

    $currentPaymentStatus = (string) ($existingPayment['status'] ?? 'Payment Pending');

    // Duplicate success notification: acknowledge without doing side effects again.
    if ($currentPaymentStatus === 'Paid' && $incomingPaymentStatus === 'Paid') {
        booking_audit_log($pdo, $bookingId, 'duplicate_payment_notify', 'Duplicate Payment Notification Ignored', 'PayHere sent a duplicate successful notification. No email or invoice was queued again.', [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
        ]);
        $pdo->commit();
        notify_queue_verified_result($pdo, $bookingId, $orderId);
        notify_text_response(200, 'OK duplicate acknowledged');
    }

    // A final result for this PayHere order is immutable. This prevents contradictory
    // confirmation and failure emails when duplicate/out-of-order callbacks arrive.
    if ($currentPaymentStatus === $incomingPaymentStatus || $currentPaymentStatus === 'Chargeback' || ($currentPaymentStatus === 'Paid' && $incomingPaymentStatus !== 'Chargeback') || (in_array($currentPaymentStatus, ['Failed', 'Cancelled'], true) && !in_array($incomingPaymentStatus, ['Paid', 'Chargeback'], true))) {
        booking_audit_log($pdo, $bookingId, 'duplicate_payment_notify', 'Duplicate Payment Notification Ignored', 'PayHere sent a notification after this payment order had already reached a final state.', [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'current_status' => $currentPaymentStatus,
            'incoming_status' => $incomingPaymentStatus,
        ]);
        $pdo->commit();
        notify_queue_verified_result($pdo, $bookingId, $orderId);
        notify_text_response(200, 'OK final state already processed');
    }

    // Expiry and gateway results apply to the entire reservation.
    $otherPaid = $pdo->prepare("SELECT id FROM payments WHERE booking_id IN ({$scopeSqlIds}) AND id <> ? AND status = 'Paid' LIMIT 1");
    $otherPaid->execute([(int)$existingPayment['id']]);
    $hasOtherPaid = (bool)$otherPaid->fetchColumn();
    $latestAttempt = $pdo->query("SELECT id FROM payments WHERE booking_id IN ({$scopeSqlIds}) ORDER BY id DESC LIMIT 1");
    $isLatestAttempt = (int) $latestAttempt->fetchColumn() === (int) $existingPayment['id'];
    $applyToBooking = !$hasOtherPaid && ($isLatestAttempt || $incomingPaymentStatus === 'Paid');
    if ($incomingPaymentStatus !== 'Paid' && $applyToBooking) {
        foreach ($scopeIds as $scopeId) expire_pending_bookings($pdo, $scopeId, false);
    }

    $finalPaymentStatus = $incomingPaymentStatus;
    $finalBookingStatus = $finalPaymentStatus === 'Paid' ? 'Confirmed' : 'Pending';
    if ($finalPaymentStatus === 'Payment Pending' && booking_hold_is_expired($booking)) {
        $finalPaymentStatus = 'Cancelled';
        $finalBookingStatus = 'Cancelled';
    }

    if ($finalPaymentStatus === 'Paid' && multi_room_scope_has_conflict($pdo, $scopeRows)) {
        $finalBookingStatus = 'Pending';
        $statusMessage = trim($statusMessage . ' Paid but a room has an overlapping reservation; manual review required.');
        error_log('Paid PayHere booking needs availability review. Booking ID: ' . $bookingId);
    }

    if (in_array($finalPaymentStatus, ['Failed', 'Cancelled'], true)) {
        // A gateway cancellation cancels this attempt, not an active reservation.
        // Retrying never resets created_at or extends the original hold.
        $finalBookingStatus = booking_seconds_remaining($booking) > 0 ? 'Pending' : 'Cancelled';
    } elseif (in_array($finalPaymentStatus, ['Refunded', 'Chargeback'], true)) {
        $finalBookingStatus = 'Cancelled';
    }

    // Preserve operational history on delayed payment results.
    if (in_array((string)$booking['status'], ['Confirmed', 'Checked In', 'Checked Out', 'No Show', 'Cancelled', 'Expired'], true)) $finalBookingStatus = (string)$booking['status'];
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

    // Room amounts are allocated at booking creation. The payment amount is
    // the group total and must never replace one room's individual amount.
    $updateBooking = $pdo->prepare(
        "UPDATE bookings SET status = :status, payment_status = :payment_status,
             currency = :currency, updated_at = NOW()
         WHERE id IN ({$scopeSqlIds})"
    );
    if ($applyToBooking) $updateBooking->execute([
        ':status' => $finalBookingStatus,
        ':payment_status' => $finalPaymentStatus,
        ':currency' => $payhereCurrency,
    ]);

    if ($finalPaymentStatus === 'Paid') {
        booking_audit_log($pdo, $bookingId, 'payment_success', 'Payment Success', $finalBookingStatus === 'Confirmed' ? 'PayHere payment was verified and all rooms were confirmed.' : 'PayHere payment was verified; availability requires manual review.', [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'amount' => $payhereAmount,
            'currency' => $payhereCurrency,
        ]);
    } elseif (in_array($finalPaymentStatus, ['Failed', 'Cancelled', 'Refunded', 'Chargeback'], true)) {
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

    if ($applyToBooking && in_array($finalPaymentStatus, ['Paid', 'Failed', 'Cancelled'], true)) {
        foreach ($scopeIds as $scopeId) {
            cancel_scheduled_pending_booking_emails($pdo, $scopeId, 'PayHere verified final payment status: ' . $finalPaymentStatus);
        }
    }

    $pdo->commit();

    $sideEffects['send_success'] = $finalPaymentStatus === 'Paid' && $currentPaymentStatus !== 'Paid' && !$hasOtherPaid;
    $sideEffects['send_failed'] = $applyToBooking && in_array($finalPaymentStatus, ['Failed', 'Cancelled'], true) && !in_array($currentPaymentStatus, ['Failed', 'Cancelled'], true);
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

if ($sideEffects['booking_id'] > 0 && ($sideEffects['send_success'] || $sideEffects['send_failed'])) {
    try {
        notify_queue_verified_result($pdo, (int) $sideEffects['booking_id'], $orderId);
    } catch (Throwable $e) {
        // A repeated verified callback can recover queue work after DB commit.
        error_log('PayHere notification queue error: ' . $e->getMessage());
        notify_text_response(500, 'Notification queue temporarily unavailable');
    }
}

notify_text_response(200, 'OK');
