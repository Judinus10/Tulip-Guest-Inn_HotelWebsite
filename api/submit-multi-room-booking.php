<?php
declare(strict_types=1);

ob_start();

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mail/email-helper.php';
require_once __DIR__ . '/bookings/booking-expiry-helper.php';
require_once __DIR__ . '/bookings/booking-audit-helper.php';
require_once __DIR__ . '/bookings/multi-room-helper.php';
require_once __DIR__ . '/calendar/ics-helper.php';
require_once __DIR__ . '/security/public-token-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

rate_limit_or_fail('submit_multi_room_booking', 4, 15);

$data = read_request_data();
$fullName = clean_string($data['full_name'] ?? '', 150);
$email = strtolower(clean_string($data['email'] ?? '', 190));
$phone = clean_string($data['phone'] ?? '', 50);
$checkInDate = clean_string($data['check_in_date'] ?? '', 20);
$checkOutDate = clean_string($data['check_out_date'] ?? '', 20);
$totalGuests = (int) ($data['total_guests'] ?? 0);
$message = clean_string($data['message'] ?? '', 3000);
$paymentMethod = strtolower(clean_string($data['payment_method'] ?? 'Cash', 30));
$requestedRooms = is_array($data['rooms'] ?? null) ? array_values($data['rooms']) : [];
$isCashPayment = in_array($paymentMethod, ['cash', 'pay on arrival'], true);
$isOnlinePayment = in_array($paymentMethod, ['payhere', 'online', 'pay online'], true);

if ($isOnlinePayment && !ONLINE_PAYMENT_ENABLED) {
    json_response(false, 'Online payment is not available at the moment. Please use Pay on Arrival.', 200, [
        'severity' => 'warning',
        'error_code' => 'ONLINE_PAYMENT_DISABLED',
        'fallback_payment_method' => 'Cash',
    ]);
}
if (!$isCashPayment && !$isOnlinePayment) {
    json_response(false, 'Please select a valid payment method.', 422);
}
if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
    json_response(false, 'Name, valid email, and phone are required.', 422);
}
if (!is_valid_date($checkInDate) || !is_valid_date($checkOutDate) || $checkOutDate <= $checkInDate) {
    json_response(false, 'Enter valid check-in and check-out dates.', 422);
}
if ($totalGuests < 2 || $totalGuests > 20 || count($requestedRooms) < 2) {
    json_response(false, 'A multi-room booking requires at least two rooms and a valid guest count.', 422);
}

$allocations = [];
$allocationTotal = 0;
foreach ($requestedRooms as $requestedRoom) {
    $roomId = (int) ($requestedRoom['room_id'] ?? 0);
    $guests = (int) ($requestedRoom['guests'] ?? 0);
    if ($roomId < 1 || $guests < 1 || isset($allocations[$roomId])) {
        json_response(false, 'Invalid room allocation.', 422);
    }
    $allocations[$roomId] = $guests;
    $allocationTotal += $guests;
}
if ($allocationTotal !== $totalGuests) {
    json_response(false, 'Allocated guests must equal the total guest count.', 422);
}

$pdo = null;
$bookingCommitted = false;
$primaryBookingId = 0;
$groupId = 0;
$bookingNumber = '';
$orderId = '';
$totalAmount = 0.0;
$currency = PAYMENT_CURRENCY;
try {
    $pdo = get_db_connection();
    expire_pending_bookings($pdo, null, false);

    // MySQL DDL implicitly commits an active transaction. The ICS helper runs
    // CREATE TABLE IF NOT EXISTS during its one-time schema check, so it must
    // be initialized before the atomic multi-room booking transaction starts.
    if (ics_enabled()) {
        ensure_ics_schema($pdo);
    }

    $pdo->beginTransaction();

    $roomIds = array_keys($allocations);
    $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
    $roomStmt = $pdo->prepare(
        "SELECT id, room_name, max_guests, base_price, currency, status
         FROM rooms
         WHERE id IN ({$placeholders})
         ORDER BY sort_order ASC, id ASC
         FOR UPDATE"
    );
    $roomStmt->execute($roomIds);
    $selectedRooms = $roomStmt->fetchAll() ?: [];

    if (count($selectedRooms) !== count($roomIds)) {
        throw new RuntimeException('One or more assigned rooms no longer exist.');
    }

    $nights = max(1, (int) ((new DateTimeImmutable($checkInDate))->diff(new DateTimeImmutable($checkOutDate))->days));
    $totalAmount = 0.0;
    $currency = PAYMENT_CURRENCY;

    foreach ($selectedRooms as &$selectedRoom) {
        $roomId = (int) $selectedRoom['id'];
        $roomGuests = $allocations[$roomId] ?? 0;
        if (($selectedRoom['status'] ?? '') !== 'Available' || $roomGuests > (int) ($selectedRoom['max_guests'] ?? 0)) {
            throw new RuntimeException('An assigned room cannot accommodate the selected guests.');
        }

        $conflict = $pdo->prepare(
            "SELECT id FROM bookings
             WHERE room_name = :room_name
               " . active_booking_conflict_sql() . "
               AND :requested_check_in < check_out_date
               AND :requested_check_out > check_in_date
             LIMIT 1
             FOR UPDATE"
        );
        $conflict->execute([
            ':room_name' => $selectedRoom['room_name'],
            ':hold_cutoff' => booking_hold_cutoff_datetime(),
            ':requested_check_in' => $checkInDate,
            ':requested_check_out' => $checkOutDate,
        ]);
        if ($conflict->fetch() || ics_room_conflict($pdo, $roomId, $checkInDate, $checkOutDate)) {
            throw new RuntimeException('One of the assigned rooms is no longer available. Please search again.');
        }

        $selectedRoom['allocated_guests'] = $roomGuests;
        $selectedRoom['room_total'] = round((float) $selectedRoom['base_price'] * $nights, 2);
        $totalAmount += $selectedRoom['room_total'];
        $currency = (string) ($selectedRoom['currency'] ?: $currency);
    }
    unset($selectedRoom);

    $groupStmt = $pdo->prepare(
        'INSERT INTO booking_groups (total_guests, total_rooms, total_amount, currency, created_at, updated_at)
         VALUES (:total_guests, :total_rooms, :total_amount, :currency, NOW(), NOW())'
    );
    $groupStmt->execute([
        ':total_guests' => $totalGuests,
        ':total_rooms' => count($selectedRooms),
        ':total_amount' => $totalAmount,
        ':currency' => $currency,
    ]);
    $groupId = (int) $pdo->lastInsertId();
    $bookingNumber = 'MB-' . str_pad((string) $groupId, 6, '0', STR_PAD_LEFT);

    $bookingIds = [];
    foreach ($selectedRooms as $index => $selectedRoom) {
        $bookingStmt = $pdo->prepare(
            'INSERT INTO bookings
                (booking_group_id, is_group_primary, full_name, email, phone,
                 is_booking_for_other, staying_guest_name, staying_guest_email, staying_guest_phone, staying_guest_note,
                 room_name, check_in_date, check_out_date, guests, message, status, payment_status,
                 amount, currency, email_status, ip_address, user_agent, created_at, updated_at)
             VALUES
                (:booking_group_id, :is_group_primary, :full_name, :email, :phone,
                 0, NULL, NULL, NULL, NULL,
                 :room_name, :check_in_date, :check_out_date, :guests, :message, \'Pending\', \'Payment Pending\',
                 :amount, :currency, \'Pending\', :ip_address, :user_agent, NOW(), NOW())'
        );
        $bookingStmt->execute([
            ':booking_group_id' => $groupId,
            ':is_group_primary' => $index === 0 ? 1 : 0,
            ':full_name' => $fullName,
            ':email' => $email,
            ':phone' => $phone,
            ':room_name' => $selectedRoom['room_name'],
            ':check_in_date' => $checkInDate,
            ':check_out_date' => $checkOutDate,
            ':guests' => $selectedRoom['allocated_guests'],
            ':message' => $message,
            ':amount' => $selectedRoom['room_total'],
            ':currency' => $currency,
            ':ip_address' => get_client_ip(),
            ':user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
        $bookingIds[] = (int) $pdo->lastInsertId();
    }

    $primaryBookingId = $bookingIds[0];
    $pdo->prepare('UPDATE booking_groups SET booking_no = :booking_no, primary_booking_id = :primary_booking_id WHERE id = :id')
        ->execute([':booking_no' => $bookingNumber, ':primary_booking_id' => $primaryBookingId, ':id' => $groupId]);

    $orderId = ($isCashPayment ? 'CASH-' : 'PAYHERE-') . $bookingNumber;
    if ($isCashPayment) {
        $paymentStmt = $pdo->prepare(
            "INSERT INTO payments
                (booking_id, order_id, amount, currency, status, method, gateway_response, created_at, updated_at)
             VALUES
                (:booking_id, :order_id, :amount, :currency, 'Payment Pending', 'Cash', NULL, NOW(), NOW())"
        );
        $paymentStmt->execute([
            ':booking_id' => $primaryBookingId,
            ':order_id' => $orderId,
            ':amount' => $totalAmount,
            ':currency' => $currency,
        ]);
    }

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Multi-room booking transaction ended unexpectedly before commit.');
    }
    $pdo->commit();
    $bookingCommitted = true;

    $amountForToken = number_format($totalAmount, 2, '.', '');
    $billToken = create_public_token('payment-status', [
        'order_id' => $orderId,
        'booking_id' => $primaryBookingId,
        'amount' => $amountForToken,
    ], PUBLIC_LINK_TTL_SECONDS);
    $publicBaseUrl = defined('FRONTEND_URL') && FRONTEND_URL !== ''
        ? FRONTEND_URL
        : (defined('PUBLIC_APP_URL') && PUBLIC_APP_URL !== '' ? PUBLIC_APP_URL : APP_BASE_URL);
    $billUrl = rtrim((string) $publicBaseUrl, '/') . '/booking-bill?' . http_build_query([
        'booking_id' => $primaryBookingId,
        'order_id' => $orderId,
        'token' => $billToken,
    ]);

    if ($isCashPayment) {
        try {
            $combinedBooking = [
                'id' => $primaryBookingId,
                'booking_no' => $bookingNumber,
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'room_name' => implode(', ', array_column($selectedRooms, 'room_name')),
                'check_in_date' => $checkInDate,
                'check_out_date' => $checkOutDate,
                'guests' => $totalGuests,
                'message' => $message,
                'status' => 'Pending',
                'payment_status' => 'Payment Pending',
                'payment_method' => 'Cash',
                'amount' => $totalAmount,
                'currency' => $currency,
            ];
            send_booking_received_emails($pdo, $combinedBooking);
        } catch (Throwable $emailError) {
            error_log('Multi-room booking email error: ' . $emailError->getMessage());
        }
    }

    json_response(true, 'Multi-room booking submitted successfully.', 201, [
        'booking_id' => $primaryBookingId,
        'booking_no' => $bookingNumber,
        'booking_group_id' => $groupId,
        'order_id' => $orderId,
        'bill_url' => $billUrl,
        'amount' => $totalAmount,
        'currency' => $currency,
        'rooms' => array_map(static fn(array $room): array => [
            'room_id' => (int) $room['id'],
            'room_name' => (string) $room['room_name'],
            'guests' => (int) $room['allocated_guests'],
            'amount' => (float) $room['room_total'],
        ], $selectedRooms),
    ]);
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Multi-room booking error: ' . $exception->getMessage());

    $persistedBookingExists = $bookingCommitted;
    $persistenceVerificationFailed = false;
    if (!$persistedBookingExists && $primaryBookingId > 0) {
        try {
            $verificationPdo = get_db_connection();
            $verificationStmt = $verificationPdo->prepare('SELECT id FROM bookings WHERE id = :id AND booking_group_id = :group_id LIMIT 1');
            $verificationStmt->execute([':id' => $primaryBookingId, ':group_id' => $groupId]);
            $persistedBookingExists = (bool) $verificationStmt->fetchColumn();
        } catch (Throwable $verificationError) {
            $persistenceVerificationFailed = true;
            error_log('Multi-room persistence verification failed: ' . $verificationError->getMessage());
        }
    }

    if ($persistedBookingExists && $primaryBookingId > 0 && $orderId !== '') {
        try {
            $recoveredToken = create_public_token('payment-status', [
                'order_id' => $orderId,
                'booking_id' => $primaryBookingId,
                'amount' => number_format($totalAmount, 2, '.', ''),
            ], PUBLIC_LINK_TTL_SECONDS);
            $recoveredBaseUrl = defined('FRONTEND_URL') && FRONTEND_URL !== ''
                ? FRONTEND_URL
                : (defined('PUBLIC_APP_URL') && PUBLIC_APP_URL !== '' ? PUBLIC_APP_URL : APP_BASE_URL);
            $recoveredBillUrl = rtrim((string) $recoveredBaseUrl, '/') . '/booking-bill?' . http_build_query([
                'booking_id' => $primaryBookingId,
                'order_id' => $orderId,
                'token' => $recoveredToken,
            ]);

            json_response(true, 'Multi-room booking submitted successfully.', 201, [
                'booking_id' => $primaryBookingId,
                'booking_no' => $bookingNumber,
                'booking_group_id' => $groupId,
                'order_id' => $orderId,
                'bill_url' => $recoveredBillUrl,
                'amount' => $totalAmount,
                'currency' => $currency,
                'response_recovered' => true,
            ]);
        } catch (Throwable $recoveryError) {
            error_log('Multi-room bill recovery error: ' . $recoveryError->getMessage());
            json_response(false, 'Your booking was saved, but we could not open the booking bill. Please do not book again. Contact the property for help.', 503, [
                'error_code' => 'BOOKING_SAVED_BILL_UNAVAILABLE',
                'booking_id' => $primaryBookingId,
                'booking_no' => $bookingNumber,
            ]);
        }
    }

    if ($primaryBookingId > 0 && $persistenceVerificationFailed) {
        json_response(false, 'We could not confirm the final booking result. Your booking may already be saved. Please do not submit again. Contact the property with your name and booking dates.', 503, [
            'error_code' => 'BOOKING_OUTCOME_UNKNOWN',
        ]);
    }

    $reason = $exception->getMessage();
    if (str_contains($reason, 'no longer available')) {
        json_response(false, 'One of the selected rooms was just booked. Please search again to receive a new room combination.', 409, ['error_code' => 'ROOM_UNAVAILABLE']);
    }
    if (str_contains($reason, 'cannot accommodate')) {
        json_response(false, 'The selected guest allocation exceeds one room\'s capacity. Please adjust the guests in each room.', 422, ['error_code' => 'ROOM_CAPACITY_EXCEEDED']);
    }
    if (str_contains($reason, 'no longer exist')) {
        json_response(false, 'One of the selected rooms is no longer offered. Please search again.', 409, ['error_code' => 'ROOM_SELECTION_CHANGED']);
    }

    json_response(false, 'Your multi-room booking could not be completed. Please review the room allocation and try again.', 500, ['error_code' => 'BOOKING_NOT_SAVED']);
}
