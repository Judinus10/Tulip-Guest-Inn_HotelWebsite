<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mail/email-helper.php';
require_once __DIR__ . '/../calendar/ics-helper.php';

apply_cors_headers();
require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

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
$paymentMethod = clean_string($data['payment_method'] ?? 'Cash', 60);
$rawPaymentStatus = clean_string($data['payment_status'] ?? 'Payment Pending', 40);

$statusKey = strtolower(trim($rawPaymentStatus));
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
$paymentStatus = $statusMap[$statusKey] ?? 'Payment Pending';

if ($fullName === '' || $email === '' || $phone === '' || $roomName === '' || $checkInDate === '' || $checkOutDate === '' || $guests < 1) {
    json_response(false, 'Please fill in all required fields.', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Please enter a valid email address.', 422);
}

if ($isBookingForOther && ($stayingGuestName === '' || $stayingGuestPhone === '')) {
    json_response(false, 'Please fill in the staying guest name and phone number.', 422);
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

if ($checkOut <= $checkIn) {
    json_response(false, 'Check-out date must be after check-in date.', 422);
}

if ($guests > 20) {
    json_response(false, 'Please enter a valid number of guests.', 422);
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $roomStmt = $pdo->prepare('SELECT id, room_name, max_guests, status FROM rooms WHERE room_name = :room_name LIMIT 1 FOR UPDATE');
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
        "SELECT id
         FROM bookings
         WHERE room_name = :room_name
           AND status IN ('Confirmed', 'Pending')
           AND COALESCE(payment_status, '') NOT IN ('Failed', 'Cancelled', 'Refunded')
           AND :requested_check_in < check_out_date
           AND :requested_check_out > check_in_date
         LIMIT 1"
    );
    $conflict->execute([
        ':room_name' => $roomName,
        ':requested_check_in' => $checkInDate,
        ':requested_check_out' => $checkOutDate,
    ]);

    if ($conflict->fetch() || ics_room_conflict($pdo, (int) $room['id'], $checkInDate, $checkOutDate)) {
        $pdo->rollBack();
        json_response(false, 'This room is not available for the selected dates.', 409, ['available' => false]);
    }

    $amount = calculate_booking_amount($roomName, $checkInDate, $checkOutDate);

    if ($amount <= 0) {
        $pdo->rollBack();
        json_response(false, 'Unable to calculate booking amount for the selected room.', 422);
    }

    $bookingStatus = 'Pending';

    $stmt = $pdo->prepare(
        'INSERT INTO bookings
        (full_name, email, phone, is_booking_for_other, staying_guest_name, staying_guest_email, staying_guest_phone, staying_guest_note, room_name, check_in_date, check_out_date, guests, message, status, payment_status, amount, currency, ip_address, user_agent, created_at, updated_at)
        VALUES
        (:full_name, :email, :phone, :is_booking_for_other, :staying_guest_name, :staying_guest_email, :staying_guest_phone, :staying_guest_note, :room_name, :check_in_date, :check_out_date, :guests, :message, :status, :payment_status, :amount, :currency, :ip_address, :user_agent, NOW(), NOW())'
    );
    $stmt->execute([
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':is_booking_for_other' => $isBookingForOther ? 1 : 0,
        ':staying_guest_name' => $stayingGuestName !== '' ? $stayingGuestName : null,
        ':staying_guest_email' => $stayingGuestEmail !== '' ? $stayingGuestEmail : null,
        ':staying_guest_phone' => $stayingGuestPhone !== '' ? $stayingGuestPhone : null,
        ':staying_guest_note' => $stayingGuestNote !== '' ? $stayingGuestNote : null,
        ':room_name' => $roomName,
        ':check_in_date' => $checkInDate,
        ':check_out_date' => $checkOutDate,
        ':guests' => $guests,
        ':message' => $message,
        ':status' => $bookingStatus,
        ':payment_status' => $paymentStatus,
        ':amount' => $amount,
        ':currency' => PAYMENT_CURRENCY,
        ':ip_address' => get_client_ip(),
        ':user_agent' => 'Admin manual reservation',
    ]);

    $bookingId = (int) $pdo->lastInsertId();

    $insertPayment = $pdo->prepare(
        'INSERT INTO payments (booking_id, order_id, amount, currency, status, method, gateway_response, created_at, updated_at)
         VALUES (:booking_id, :order_id, :amount, :currency, :status, :method, :gateway_response, NOW(), NOW())'
    );
    $insertPayment->execute([
        ':booking_id' => $bookingId,
        ':order_id' => 'MANUAL-' . date('YmdHis') . '-' . str_pad((string) $bookingId, 5, '0', STR_PAD_LEFT),
        ':amount' => $amount,
        ':currency' => PAYMENT_CURRENCY,
        ':status' => $paymentStatus,
        ':method' => $paymentMethod === '' ? 'Cash' : $paymentMethod,
        ':gateway_response' => json_encode([
            'source' => 'admin_manual_booking',
            'created_at' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_SLASHES),
    ]);

    $pdo->commit();

    $emailBooking = [
        'id' => $bookingId,
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'is_booking_for_other' => $isBookingForOther ? 1 : 0,
        'staying_guest_name' => $stayingGuestName,
        'staying_guest_email' => $stayingGuestEmail,
        'staying_guest_phone' => $stayingGuestPhone,
        'staying_guest_note' => $stayingGuestNote,
        'room_name' => $roomName,
        'check_in_date' => $checkInDate,
        'check_out_date' => $checkOutDate,
        'guests' => $guests,
        'message' => $message,
        'status' => $bookingStatus,
        'booking_status' => $bookingStatus,
        'payment_status' => $paymentStatus,
        'amount' => $amount,
        'currency' => PAYMENT_CURRENCY,
    ];

    try {
        queue_booking_received_emails($pdo, $emailBooking);
    } catch (Throwable $emailError) {
        error_log('Manual booking email queue error: ' . $emailError->getMessage());
    }

    json_response(true, 'Manual booking created successfully.', 201, [
        'data' => [
            'id' => $bookingId,
            'booking_no' => 'BK-' . str_pad((string) $bookingId, 5, '0', STR_PAD_LEFT),
            'guest_name' => $isBookingForOther && $stayingGuestName !== '' ? $stayingGuestName : $fullName,
            'guest_email' => $isBookingForOther && $stayingGuestEmail !== '' ? $stayingGuestEmail : $email,
            'guest_phone' => $isBookingForOther && $stayingGuestPhone !== '' ? $stayingGuestPhone : $phone,
            'booker_name' => $fullName,
            'booker_email' => $email,
            'booker_phone' => $phone,
            'is_booking_for_other' => $isBookingForOther ? 1 : 0,
            'staying_guest_name' => $stayingGuestName,
            'staying_guest_email' => $stayingGuestEmail,
            'staying_guest_phone' => $stayingGuestPhone,
            'staying_guest_note' => $stayingGuestNote,
            'room_id' => (int) $room['id'],
            'room_name' => $roomName,
            'check_in' => $checkInDate,
            'check_out' => $checkOutDate,
            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
            'guests' => $guests,
            'adults' => $guests,
            'children' => 0,
            'total_nights' => max(1, (int) $checkIn->diff($checkOut)->days),
            'special_request' => $message,
            'special_requests' => $message,
            'booking_status' => strtolower($bookingStatus),
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
            'total_amount' => $amount,
            'payment_currency' => PAYMENT_CURRENCY,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Admin create manual booking error: ' . $e->getMessage());
    json_response(false, 'Unable to create manual booking.', 500);
}
