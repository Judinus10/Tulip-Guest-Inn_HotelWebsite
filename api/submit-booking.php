<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mail/email-helper.php';
require_once __DIR__ . '/bookings/booking-expiry-helper.php';
require_once __DIR__ . '/bookings/booking-audit-helper.php';
require_once __DIR__ . '/calendar/ics-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

rate_limit_or_fail('submit_booking', 6, 15);

$data = read_request_data();
$fullName = clean_string($data['full_name'] ?? '', 150);
$email = strtolower(clean_string($data['email'] ?? '', 190));
$phone = clean_string($data['phone'] ?? '', 50);
$isBookingForOther = !empty($data['is_booking_for_other']) && filter_var($data['is_booking_for_other'], FILTER_VALIDATE_BOOLEAN);
$stayingGuestName = clean_string($data['staying_guest_name'] ?? '', 150);
$stayingGuestEmail = strtolower(clean_string($data['staying_guest_email'] ?? '', 190));
$stayingGuestPhone = clean_string($data['staying_guest_phone'] ?? '', 50);
$stayingGuestNote = clean_string($data['staying_guest_note'] ?? '', 3000);
$roomName = clean_string($data['room_name'] ?? '', 150);
$checkInDate = clean_string($data['check_in_date'] ?? '', 20);
$checkOutDate = clean_string($data['check_out_date'] ?? '', 20);
$guests = (int) ($data['guests'] ?? 0);
$message = clean_string($data['message'] ?? '', 3000);
$paymentMethod = strtolower(clean_string($data['payment_method'] ?? 'Cash', 30));
$isOnlinePayment = in_array($paymentMethod, ['payhere', 'online', 'pay online'], true);
$isCashPayment = in_array($paymentMethod, ['cash', 'pay on arrival'], true);

if (!$isCashPayment && !$isOnlinePayment) {
    json_response(false, 'Please select a valid payment method.', 422);
}

if ($fullName === '' || $email === '' || $phone === '' || $roomName === '' || $checkInDate === '' || $checkOutDate === '' || $guests < 1) {
    json_response(false, 'Please fill in all required fields.', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Please enter a valid email address.', 422);
}

if ($isBookingForOther && $stayingGuestName === '') {
    json_response(false, 'Please enter the staying guest name.', 422);
}

if ($stayingGuestEmail !== '' && !filter_var($stayingGuestEmail, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Please enter a valid staying guest email address.', 422);
}

if (!$isBookingForOther) {
    $stayingGuestName = '';
    $stayingGuestEmail = '';
    $stayingGuestPhone = '';
    $stayingGuestNote = '';
}

if (!is_valid_date($checkInDate) || !is_valid_date($checkOutDate)) {
    json_response(false, 'Please enter valid check-in and check-out dates.', 422);
}

$today = new DateTimeImmutable('today');
$checkIn = DateTimeImmutable::createFromFormat('Y-m-d', $checkInDate);
$checkOut = DateTimeImmutable::createFromFormat('Y-m-d', $checkOutDate);

if (!$checkIn || !$checkOut) {
    json_response(false, 'Please enter valid check-in and check-out dates.', 422);
}

if ($checkIn < $today) {
    json_response(false, 'Check-in date cannot be in the past.', 422);
}

if ($checkOut < $checkIn) {
    json_response(false, 'Check-out date cannot be before check-in date.', 422);
}

if ($checkOutDate === $checkInDate) {
    $checkOutDate = $checkIn->modify('+1 day')->format('Y-m-d');
    $checkOut = DateTimeImmutable::createFromFormat('Y-m-d', $checkOutDate);
}

if ($guests > 20) {
    json_response(false, 'Please enter a valid number of guests.', 422);
}

$amount = calculate_booking_amount($roomName, $checkInDate, $checkOutDate);

if ($amount <= 0) {
    json_response(false, 'Unable to calculate booking amount for the selected room.', 422);
}

try {
    $pdo = get_db_connection();
    expire_pending_bookings($pdo, null, true);

    ensure_ics_schema($pdo);
    $pdo->beginTransaction();

    $roomStmt = $pdo->prepare("SELECT id, max_guests, status FROM rooms WHERE room_name = :room_name LIMIT 1");
    $roomStmt->execute([':room_name' => $roomName]);
    $room = $roomStmt->fetch();

    if (!$room || ($room['status'] ?? '') !== 'Available') {
        $pdo->rollBack();
        json_response(false, 'Please select a valid available room.', 422);
    }

    if ($guests > (int) ($room['max_guests'] ?? 0)) {
        $pdo->rollBack();
        json_response(false, 'Selected room cannot hold this number of guests.', 422);
    }

    $conflict = $pdo->prepare(
        "SELECT id, check_in_date, check_out_date
         FROM bookings
         WHERE room_name = :room_name
           " . active_booking_conflict_sql() . "
           AND :requested_check_in < check_out_date
           AND :requested_check_out > check_in_date
         LIMIT 1"
    );
    $conflict->execute([
        ':room_name' => $roomName,
        ':hold_cutoff' => booking_hold_cutoff_datetime(),
        ':requested_check_in' => $checkInDate,
        ':requested_check_out' => $checkOutDate,
    ]);

    if ($conflict->fetch() || ics_room_conflict($pdo, (int) $room['id'], $checkInDate, $checkOutDate)) {
        $pdo->rollBack();
        json_response(false, 'Sorry, this room is not available for the selected dates.', 409, ['available' => false]);
    }

    $columnStmt = $pdo->query('SHOW COLUMNS FROM bookings');
    $bookingColumns = array_flip(array_column($columnStmt->fetchAll(), 'Field'));

    $bookingValues = [
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'is_booking_for_other' => $isBookingForOther ? 1 : 0,
        'staying_guest_name' => $stayingGuestName !== '' ? $stayingGuestName : null,
        'staying_guest_email' => $stayingGuestEmail !== '' ? $stayingGuestEmail : null,
        'staying_guest_phone' => $stayingGuestPhone !== '' ? $stayingGuestPhone : null,
        'staying_guest_note' => $stayingGuestNote !== '' ? $stayingGuestNote : null,
        'room_name' => $roomName,
        'check_in_date' => $checkInDate,
        'check_out_date' => $checkOutDate,
        'guests' => $guests,
        'message' => $message,
        'status' => 'Pending',
        'payment_status' => 'Payment Pending',
        'amount' => $amount,
        'currency' => PAYMENT_CURRENCY,
        'ip_address' => get_client_ip(),
        'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ];

    $insertColumns = [];
    $insertPlaceholders = [];
    $insertParams = [];

    foreach ($bookingValues as $column => $value) {
        if (isset($bookingColumns[$column])) {
            $insertColumns[] = $column;
            $insertPlaceholders[] = ':' . $column;
            $insertParams[':' . $column] = $value;
        }
    }

    $insertColumns[] = 'created_at';
    $insertPlaceholders[] = 'NOW()';
    $insertColumns[] = 'updated_at';
    $insertPlaceholders[] = 'NOW()';

    $stmt = $pdo->prepare(
        'INSERT INTO bookings (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')'
    );
    $stmt->execute($insertParams);

    $bookingId = (int) $pdo->lastInsertId();

    $bookingNumber = 'BK-' . str_pad((string) $bookingId, 5, '0', STR_PAD_LEFT);
    $cashOrderId = '';
    $cashBillUrl = '';

    if ($isCashPayment) {
        $cashOrderId = 'CASH-' . str_pad((string) $bookingId, 5, '0', STR_PAD_LEFT);
        $paymentStmt = $pdo->prepare(
            "INSERT INTO payments
                (booking_id, order_id, amount, currency, status, method, gateway_response, created_at, updated_at)
             VALUES
                (:booking_id, :order_id, :amount, :currency, 'Payment Pending', 'Cash', NULL, NOW(), NOW())"
        );
        $paymentStmt->execute([
            ':booking_id' => $bookingId,
            ':order_id' => $cashOrderId,
            ':amount' => $amount,
            ':currency' => PAYMENT_CURRENCY,
        ]);

        $amountForToken = number_format($amount, 2, '.', '');
        $cashBillToken = create_public_token('booking-status', [
            'order_id' => $cashOrderId,
            'booking_id' => $bookingId,
            'amount' => $amountForToken,
        ], BOOKING_LINK_TTL_SECONDS);
        $publicBaseUrl = defined('FRONTEND_URL') && FRONTEND_URL !== ''
            ? FRONTEND_URL
            : (defined('PUBLIC_APP_URL') && PUBLIC_APP_URL !== '' ? PUBLIC_APP_URL : APP_BASE_URL);
        $cashBillUrl = rtrim((string) $publicBaseUrl, '/') . '/booking-bill?' . http_build_query([
            'booking_id' => $bookingId,
            'order_id' => $cashOrderId,
            'token' => $cashBillToken,
        ]);
    }

    booking_audit_log($pdo, $bookingId, 'booking_created', 'Booking Created', 'Customer submitted booking details and a pending booking was created.', [
        'room_name' => $roomName,
        'amount' => $amount,
        'currency' => PAYMENT_CURRENCY,
        'payment_method' => $isOnlinePayment ? 'PayHere' : 'Cash',
    ]);

    $pdo->commit();

    // Notifications must run only after the booking transaction is committed.
    // Email logging/schema checks must never implicitly end the booking
    // transaction or turn a saved Cash booking into a 500 response.
    if ($isCashPayment) {
        $emailBooking = $bookingValues;
        $emailBooking['id'] = $bookingId;
        $emailBooking['booking_no'] = $bookingNumber;
        $emailBooking['payment_method'] = 'Cash';
        try {
            send_booking_received_emails($pdo, $emailBooking);
        } catch (Throwable $emailError) {
            error_log('Pay on Arrival booking email error: ' . $emailError->getMessage());
        }
    }

    json_response(true, $isCashPayment ? 'Booking request received successfully.' : 'Booking details saved. Continue to payment.', 201, [
        'inquiry_id' => $bookingId,
        'booking_id' => $bookingId,
        'booking_no' => $bookingNumber,
        'amount' => $amount,
        'currency' => PAYMENT_CURRENCY,
        'payment_method' => $isOnlinePayment ? 'PayHere' : 'Cash',
        'order_id' => $cashOrderId !== '' ? $cashOrderId : null,
        'bill_url' => $cashBillUrl !== '' ? $cashBillUrl : null,
        'requires_online_checkout' => $isOnlinePayment,
        'data' => [
            'booking_id' => $bookingId,
            'booking_no' => $bookingNumber,
            'amount' => $amount,
            'currency' => PAYMENT_CURRENCY,
            'payment_method' => $isOnlinePayment ? 'PayHere' : 'Cash',
            'order_id' => $cashOrderId !== '' ? $cashOrderId : null,
            'bill_url' => $cashBillUrl !== '' ? $cashBillUrl : null,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Booking submit error: ' . $e->getMessage());
    json_response(false, 'Unable to save booking details.', 500);
}
