<?php
/**
 * Creates a PayHere checkout session for an existing booking.
 * This endpoint never trusts frontend amount values. It recalculates amount from
 * the saved booking room name and stay dates using the current room price from the database.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../bookings/booking-expiry-helper.php';
require_once __DIR__ . '/../bookings/booking-audit-helper.php';
require_once __DIR__ . '/../mail/email-helper.php';
require_once __DIR__ . '/../calendar/ics-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

rate_limit_or_fail('create_checkout_session', 10, 15);

function payhere_format_amount(float $amount): string
{
    return number_format($amount, 2, '.', '');
}

function generate_payhere_order_id(int $bookingId): string
{
    $bookingPart = str_pad((string) $bookingId, 5, '0', STR_PAD_LEFT);
    $microtimePart = str_replace('.', '', sprintf('%.6F', microtime(true)));

    try {
        $randomPart = strtoupper(bin2hex(random_bytes(3)));
    } catch (Throwable $exception) {
        $randomPart = strtoupper(substr(hash('sha256', uniqid((string) $bookingId, true)), 0, 6));
    }

    return 'JH-' . date('YmdHis') . '-' . $bookingPart . '-' . substr($microtimePart, -6) . '-' . $randomPart;
}

function create_checkout_token(string $orderId, int $bookingId, string $amount): string
{
    return hash_hmac('sha256', $orderId . '|' . $bookingId . '|' . $amount, PAYHERE_MERCHANT_SECRET);
}

function get_public_base_url(): string
{
    $publicBaseUrl = defined('FRONTEND_URL') && FRONTEND_URL !== ''
        ? FRONTEND_URL
        : (defined('PUBLIC_APP_URL') && PUBLIC_APP_URL !== '' ? PUBLIC_APP_URL : APP_BASE_URL);

    return rtrim((string) $publicBaseUrl, '/');
}

function build_room_details_url(PDO $pdo, string $roomName, int $bookingId, string $orderId, string $paymentState): string
{
    $publicBaseUrl = get_public_base_url();
    $roomPath = '/rooms';

    try {
        $roomStmt = $pdo->prepare('SELECT slug, id FROM rooms WHERE room_name = :room_name LIMIT 1');
        $roomStmt->execute([':room_name' => $roomName]);
        $room = $roomStmt->fetch();

        if ($room) {
            $roomIdentifier = trim((string) ($room['slug'] ?? ''));
            if ($roomIdentifier === '') {
                $roomIdentifier = (string) ($room['id'] ?? '');
            }

            if ($roomIdentifier !== '') {
                $roomPath = '/rooms/' . rawurlencode($roomIdentifier);
            }
        }
    } catch (Throwable $exception) {
        error_log('Unable to build room redirect URL: ' . $exception->getMessage());
    }

    $query = http_build_query([
        'payment' => $paymentState,
        'booking_id' => $bookingId,
        'order_id' => $orderId,
    ]);

    return $publicBaseUrl . $roomPath . '?' . $query;
}

function build_booking_bill_url(int $bookingId, string $orderId, string $token): string
{
    $query = http_build_query([
        'booking_id' => $bookingId,
        'order_id' => $orderId,
        'token' => $token,
    ]);

    return get_public_base_url() . '/booking-bill?' . $query;
}

$data = read_request_data();
$bookingId = (int) ($data['booking_id'] ?? $data['inquiry_id'] ?? 0);

if ($bookingId < 1) {
    json_response(false, 'Valid booking ID is required to start payment.', 422);
}

if (PAYHERE_MERCHANT_ID === '' || PAYHERE_MERCHANT_SECRET === '' || PAYHERE_MERCHANT_ID === 'YOUR_PAYHERE_MERCHANT_ID' || PAYHERE_MERCHANT_SECRET === 'YOUR_PAYHERE_MERCHANT_SECRET') {
    json_response(false, 'PayHere credentials are not configured on the backend.', 500);
}

try {
    $pdo = get_db_connection();
    expire_pending_bookings($pdo, $bookingId, true);
    ensure_booking_audit_table($pdo);
    $pdo->beginTransaction();

    $bookingStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE');
    $bookingStmt->execute([':id' => $bookingId]);
    $booking = $bookingStmt->fetch();

    if (!$booking) {
        $pdo->rollBack();
        json_response(false, 'Booking was not found.', 404);
    }

    if (($booking['payment_status'] ?? '') === 'Paid') {
        $pdo->rollBack();
        json_response(false, 'This booking has already been paid.', 409);
    }

    if (booking_hold_is_expired($booking)) {
        $pdo->rollBack();
        json_response(false, 'This payment hold has expired. Please retry payment to create a fresh checkout.', 409, [
            'expired' => true,
            'can_retry_payment' => true,
        ]);
    }

    $roomName = (string) ($booking['room_name'] ?? '');
    $checkInDate = (string) ($booking['check_in_date'] ?? '');
    $checkOutDate = (string) ($booking['check_out_date'] ?? '');

    if (!is_valid_date($checkInDate) || !is_valid_date($checkOutDate)) {
        $pdo->rollBack();
        json_response(false, 'Booking contains invalid dates.', 422);
    }

    $checkIn = DateTimeImmutable::createFromFormat('Y-m-d', $checkInDate);
    $checkOut = DateTimeImmutable::createFromFormat('Y-m-d', $checkOutDate);
    $today = new DateTimeImmutable('today');

    if (!$checkIn || !$checkOut || $checkIn < $today || $checkOut <= $checkIn) {
        $pdo->rollBack();
        json_response(false, 'Booking dates are no longer valid for payment.', 422);
    }

    $amount = calculate_booking_amount($roomName, $checkInDate, $checkOutDate);

    if ($amount <= 0) {
        $pdo->rollBack();
        json_response(false, 'Unable to calculate payment amount.', 422);
    }

    $conflict = $pdo->prepare(
        "SELECT id
         FROM bookings
         WHERE id <> :booking_id
           AND room_name = :room_name
           " . active_booking_conflict_sql() . "
           AND :requested_check_in < check_out_date
           AND :requested_check_out > check_in_date
         LIMIT 1"
    );
    $conflict->execute([
        ':booking_id' => $bookingId,
        ':room_name' => $roomName,
        ':hold_cutoff' => booking_hold_cutoff_datetime(),
        ':requested_check_in' => $checkInDate,
        ':requested_check_out' => $checkOutDate,
    ]);

    $roomIdStmt = $pdo->prepare('SELECT id FROM rooms WHERE room_name = :room_name LIMIT 1');
    $roomIdStmt->execute([':room_name' => $roomName]);
    $roomId = (int) $roomIdStmt->fetchColumn();

    if ($conflict->fetch() || $roomId < 1 || ics_room_conflict($pdo, $roomId, $checkInDate, $checkOutDate)) {
        $pdo->rollBack();
        json_response(false, 'Sorry, this room is no longer available for the selected dates.', 409, ['available' => false]);
    }

    $amountFormatted = payhere_format_amount($amount);
    $currency = PAYMENT_CURRENCY;
    $orderId = generate_payhere_order_id($bookingId);

    $checkoutToken = create_checkout_token($orderId, $bookingId, $amountFormatted);
    $returnUrl = build_booking_bill_url($bookingId, $orderId, $checkoutToken);
    $cancelUrl = build_room_details_url($pdo, $roomName, $bookingId, $orderId, 'failed');
    $notifyUrl = (API_BASE_URL !== '' ? API_BASE_URL : '') . '/payments/payhere-notify.php';

    $checkoutPayload = [
        'merchant_id' => PAYHERE_MERCHANT_ID,
        'return_url' => $returnUrl,
        'cancel_url' => $cancelUrl,
        'notify_url' => $notifyUrl,
        'order_id' => $orderId,
        'items' => 'Tulip Guest Inn booking #' . $bookingId . ' - ' . $roomName,
        'currency' => $currency,
        'amount' => $amountFormatted,
        'first_name' => (string) ($booking['full_name'] ?? 'Guest'),
        'last_name' => '',
        'email' => (string) ($booking['email'] ?? ''),
        'phone' => (string) ($booking['phone'] ?? ''),
        'address' => '',
        'city' => '',
        'country' => 'Sri Lanka',
        'custom_1' => (string) $bookingId,
        'custom_2' => '',
    ];

    $checkoutPayload['hash'] = strtoupper(md5(
        PAYHERE_MERCHANT_ID .
        $orderId .
        $amountFormatted .
        $currency .
        strtoupper(md5(PAYHERE_MERCHANT_SECRET))
    ));

    $insertPayment = $pdo->prepare(
        'INSERT INTO payments (booking_id, order_id, amount, currency, status, method, gateway_response, created_at, updated_at)
         VALUES (:booking_id, :order_id, :amount, :currency, :status, :method, :gateway_response, NOW(), NOW())'
    );
    $insertPayment->execute([
        ':booking_id' => $bookingId,
        ':order_id' => $orderId,
        ':amount' => $amount,
        ':currency' => $currency,
        ':status' => 'Payment Pending',
        ':method' => 'PayHere',
        ':gateway_response' => json_encode([
            'checkout_created_at' => date('Y-m-d H:i:s'),
            'checkout_payload' => $checkoutPayload,
        ], JSON_UNESCAPED_SLASHES),
    ]);

    $paymentRowId = (int) $pdo->lastInsertId();

    booking_audit_log($pdo, $bookingId, 'payment_started', 'Payment Started', 'A PayHere checkout session was created.', [
        'order_id' => $orderId,
        'payment_row_id' => $paymentRowId,
        'amount' => $amountFormatted,
        'currency' => $currency,
    ]);

    $updateBooking = $pdo->prepare("UPDATE bookings SET amount = :amount, currency = :currency, status = 'Pending', payment_status = :payment_status, updated_at = NOW() WHERE id = :id");
    $updateBooking->execute([
        ':amount' => $amount,
        ':currency' => $currency,
        ':payment_status' => 'Payment Pending',
        ':id' => $bookingId,
    ]);

    booking_audit_log($pdo, $bookingId, 'booking_payment_hold_refreshed', 'Booking Payment Hold Active', 'Booking remains reserved while awaiting payment.', [
        'order_id' => $orderId,
        'hold_minutes' => booking_hold_minutes(),
    ]);

    $pdo->commit();

    // Queue the one-time payment-pending emails now, but delay delivery using available_at.
    // This makes the queue visible immediately and the existing cron sends it only after the configured delay.
    try {
        $pendingDelayMinutes = function_exists('jebal_env_value')
            ? (int) jebal_env_value('PAYMENT_PENDING_EMAIL_DELAY_MINUTES', '10')
            : 10;
        $pendingDelayMinutes = max(5, min(180, $pendingDelayMinutes));
        $pendingAvailableAt = (new DateTimeImmutable())->modify('+' . $pendingDelayMinutes . ' minutes')->format('Y-m-d H:i:s');

        $bookingForEmail = $booking;
        $bookingForEmail['amount'] = $amount;
        $bookingForEmail['currency'] = $currency;
        $bookingForEmail['payment_status'] = 'Payment Pending';
        $bookingForEmail['status'] = 'Pending';

        queue_payment_pending_emails_once($pdo, $bookingForEmail, [
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'Payment Pending',
            'method' => 'PayHere',
            'bill_url' => $returnUrl,
            'available_at' => $pendingAvailableAt,
        ]);
    } catch (Throwable $emailQueueError) {
        error_log('Payment pending email queue failed for booking #' . $bookingId . ': ' . $emailQueueError->getMessage());
    }

    // Final success/failed emails are still queued after PayHere confirms the payment outcome.

    $baseApiUrl = API_BASE_URL !== '' ? API_BASE_URL : rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/payments')), '/');
    $checkoutUrl = $baseApiUrl . '/payments/payhere-redirect.php?order_id=' . rawurlencode($orderId) . '&booking_id=' . $bookingId . '&token=' . rawurlencode($checkoutToken);

    json_response(true, 'PayHere checkout session created.', 200, [
        'checkout_url' => $checkoutUrl,
        'order_id' => $orderId,
        'amount' => $amountFormatted,
        'currency' => $currency,
    ]);
} catch (Throwable $e) {
    error_log('Create checkout session error: ' . $e->getMessage());

    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (Throwable $rollbackException) {
            error_log('Create checkout session rollback failed: ' . $rollbackException->getMessage());
        }
    }

    json_response(false, 'Unable to start payment checkout.', 500);
}
