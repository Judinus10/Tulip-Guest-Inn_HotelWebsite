<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../calendar/ics-helper.php';
require_once __DIR__ . '/../mail/email-helper.php';
require_once __DIR__ . '/booking-audit-helper.php';

apply_cors_headers();
require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

$data = read_request_data();
$id = (int) ($data['id'] ?? 0);
$fullName = clean_string($data['full_name'] ?? '', 150);
$email = strtolower(clean_string($data['email'] ?? '', 190));
$phone = clean_string($data['phone'] ?? '', 50);
$roomName = clean_string($data['room_name'] ?? '', 150);
$checkInDate = clean_string($data['check_in_date'] ?? '', 20);
$checkOutDate = clean_string($data['check_out_date'] ?? '', 20);
$guests = (int) ($data['guests'] ?? 0);
$message = clean_string($data['message'] ?? '', 3000);
$isBookingForOther = !empty($data['is_booking_for_other']) && filter_var($data['is_booking_for_other'], FILTER_VALIDATE_BOOLEAN);
$stayingGuestName = clean_string($data['staying_guest_name'] ?? '', 150);
$stayingGuestEmail = strtolower(clean_string($data['staying_guest_email'] ?? '', 190));
$stayingGuestPhone = clean_string($data['staying_guest_phone'] ?? '', 50);
$stayingGuestNote = clean_string($data['staying_guest_note'] ?? '', 3000);
$sendEmail = !empty($data['send_email']) && filter_var($data['send_email'], FILTER_VALIDATE_BOOLEAN);

if ($id < 1) {
    json_response(false, 'Please select a valid booking to edit.', 422);
}
if ($fullName === '' || $email === '' || $phone === '' || $roomName === '' || $checkInDate === '' || $checkOutDate === '' || $guests < 1) {
    json_response(false, 'Please complete all required booking details.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Please enter a valid guest email address.', 422);
}
if ($isBookingForOther && $stayingGuestName === '') {
    json_response(false, 'Please enter the name of the guest who will be staying.', 422);
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

$checkIn = DateTimeImmutable::createFromFormat('!Y-m-d', $checkInDate);
$checkOut = DateTimeImmutable::createFromFormat('!Y-m-d', $checkOutDate);
if (!$checkIn || !$checkOut || $checkOut <= $checkIn) {
    json_response(false, 'Check-out date must be after check-in date.', 422);
}
$today = new DateTimeImmutable('today');
if ($checkIn <= $today) {
    json_response(false, 'Check-in date must be after today.', 422);
}
if ($guests > 20) {
    json_response(false, 'Please enter a valid number of guests.', 422);
}

try {
    $pdo = get_db_connection();
    ensure_booking_audit_table($pdo);
    // The ICS helper may create/upgrade its tables the first time it runs.
    // MySQL DDL implicitly commits an active transaction, so initialise the
    // schema before beginning the atomic booking update below.
    if (ics_enabled()) {
        ensure_ics_schema($pdo);
    }
    $pdo->beginTransaction();

    $bookingStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE');
    $bookingStmt->execute([':id' => $id]);
    $booking = $bookingStmt->fetch();

    if (!$booking) {
        $pdo->rollBack();
        json_response(false, 'Booking was not found. Refresh the page and try again.', 404);
    }

    if ((int) ($booking['booking_group_id'] ?? 0) > 0) {
        $pdo->rollBack();
        json_response(false, 'Multi-room reservations must be edited as a complete group. This booking cannot be changed from the single-room editor.', 409);
    }

    $statusKey = strtolower(str_replace([' ', '-'], '_', trim((string) ($booking['status'] ?? ''))));
    if (in_array($statusKey, ['checked_in', 'checked_out', 'cancelled', 'canceled', 'no_show'], true)) {
        $pdo->rollBack();
        json_response(false, 'This booking can no longer be edited because it is cancelled or the stay has already started.', 409);
    }

    $roomStmt = $pdo->prepare('SELECT id, room_name, max_guests, base_price, currency, status FROM rooms WHERE room_name = :room_name LIMIT 1 FOR UPDATE');
    $roomStmt->execute([':room_name' => $roomName]);
    $room = $roomStmt->fetch();
    $sameRoom = strcasecmp($roomName, (string) ($booking['room_name'] ?? '')) === 0;

    if (!$room || (!$sameRoom && (string) ($room['status'] ?? '') !== 'Available')) {
        $pdo->rollBack();
        json_response(false, 'The selected room is not currently available. Please choose another room.', 409);
    }
    if ($guests > (int) ($room['max_guests'] ?? 0)) {
        $pdo->rollBack();
        json_response(false, 'The selected room can accommodate a maximum of ' . (int) $room['max_guests'] . ' guests.', 422);
    }

    $conflictStmt = $pdo->prepare(
        "SELECT id FROM bookings
         WHERE id <> :id
           AND room_name = :room_name
           AND status IN ('Pending', 'Confirmed', 'Checked In')
           AND COALESCE(payment_status, '') NOT IN ('Failed', 'Cancelled', 'Refunded')
           AND :check_in < check_out_date
           AND :check_out > check_in_date
         LIMIT 1"
    );
    $conflictStmt->execute([
        ':id' => $id,
        ':room_name' => $roomName,
        ':check_in' => $checkInDate,
        ':check_out' => $checkOutDate,
    ]);

    if ($conflictStmt->fetch() || ics_room_conflict($pdo, (int) $room['id'], $checkInDate, $checkOutDate)) {
        $pdo->rollBack();
        json_response(false, 'That room is already reserved for part of the selected dates. Please choose another room or different dates.', 409, ['available' => false]);
    }

    $nights = max(1, (int) $checkIn->diff($checkOut)->days);
    $amount = round((float) ($room['base_price'] ?? 0) * $nights, 2);
    if ($amount <= 0) {
        $pdo->rollBack();
        json_response(false, 'The room price is missing, so the booking total cannot be recalculated. Update the room price first.', 422);
    }

    $oldAmount = (float) ($booking['amount'] ?? 0);
    $currency = (string) ($room['currency'] ?? $booking['currency'] ?? 'USD');

    $paymentStmt = $pdo->prepare('SELECT * FROM payments WHERE booking_id = :id ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $paymentStmt->execute([':id' => $id]);
    $payment = $paymentStmt->fetch() ?: [];
    $paymentStatus = strtolower(trim((string) ($payment['status'] ?? $booking['payment_status'] ?? 'Payment Pending')));

    $updateStmt = $pdo->prepare(
        'UPDATE bookings SET
            full_name = :full_name, email = :email, phone = :phone,
            is_booking_for_other = :is_booking_for_other,
            staying_guest_name = :staying_guest_name,
            staying_guest_email = :staying_guest_email,
            staying_guest_phone = :staying_guest_phone,
            staying_guest_note = :staying_guest_note,
            room_name = :room_name, check_in_date = :check_in_date,
            check_out_date = :check_out_date, guests = :guests,
            message = :message, amount = :amount, currency = :currency,
            updated_at = NOW()
         WHERE id = :id'
    );
    $updateStmt->execute([
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
        ':message' => $message !== '' ? $message : null,
        ':amount' => $amount,
        ':currency' => $currency,
        ':id' => $id,
    ]);

    if ($payment && in_array($paymentStatus, ['payment pending', 'pending'], true)) {
        $paymentUpdate = $pdo->prepare('UPDATE payments SET amount = :amount, currency = :currency, updated_at = NOW() WHERE id = :id');
        $paymentUpdate->execute([':amount' => $amount, ':currency' => $currency, ':id' => (int) $payment['id']]);
    }

    $oldSnapshot = [
        'full_name' => (string) ($booking['full_name'] ?? ''),
        'email' => (string) ($booking['email'] ?? ''),
        'phone' => (string) ($booking['phone'] ?? ''),
        'room_name' => (string) ($booking['room_name'] ?? ''),
        'check_in_date' => (string) ($booking['check_in_date'] ?? ''),
        'check_out_date' => (string) ($booking['check_out_date'] ?? ''),
        'guests' => (int) ($booking['guests'] ?? 0),
        'message' => (string) ($booking['message'] ?? ''),
        'is_booking_for_other' => (int) ($booking['is_booking_for_other'] ?? 0),
        'staying_guest_name' => (string) ($booking['staying_guest_name'] ?? ''),
        'staying_guest_email' => (string) ($booking['staying_guest_email'] ?? ''),
        'staying_guest_phone' => (string) ($booking['staying_guest_phone'] ?? ''),
        'staying_guest_note' => (string) ($booking['staying_guest_note'] ?? ''),
        'amount' => $oldAmount,
    ];
    $newSnapshot = [
        'full_name' => $fullName, 'email' => $email, 'phone' => $phone,
        'room_name' => $roomName, 'check_in_date' => $checkInDate,
        'check_out_date' => $checkOutDate, 'guests' => $guests,
        'message' => $message, 'amount' => $amount,
        'is_booking_for_other' => $isBookingForOther ? 1 : 0,
        'staying_guest_name' => $stayingGuestName,
        'staying_guest_email' => $stayingGuestEmail,
        'staying_guest_phone' => $stayingGuestPhone,
        'staying_guest_note' => $stayingGuestNote,
    ];
    booking_audit_log($pdo, $id, 'booking_details_updated', 'Booking details updated', 'Guest, room or stay details were edited in the admin portal.', [
        'old' => $oldSnapshot,
        'new' => $newSnapshot,
        'payment_row_id' => (int) ($payment['id'] ?? 0),
        'order_id' => (string) ($payment['order_id'] ?? ''),
    ]);

    $pdo->commit();

    $delta = round($amount - $oldAmount, 2);
    $adjustmentType = 'none';
    if ($paymentStatus === 'paid' && $delta > 0) $adjustmentType = 'balance_due';
    if ($paymentStatus === 'paid' && $delta < 0) $adjustmentType = 'refund_due';

    $updatedBooking = array_merge($booking, $newSnapshot, [
        'id' => $id,
        'status' => (string) ($booking['status'] ?? 'Pending'),
        'payment_status' => (string) ($booking['payment_status'] ?? 'Payment Pending'),
        'currency' => $currency,
        'total_nights' => $nights,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $emailQueued = false;
    if ($sendEmail) {
        try {
            $subject = 'Booking details updated - Tulip Guest Inn #' . $id;
            $html = booking_email_html(
                'updated',
                $updatedBooking,
                $payment,
                false,
                '',
                [],
                'Your booking details were updated',
                'The property has updated your reservation. Please review the room, dates, guest count and total shown below.',
                'UPDATED'
            );
            $emailQueued = send_tracked_email($pdo, 'booking', $id, $email, $subject, $html, 'booking_details_updated');
        } catch (Throwable $emailError) {
            error_log('Booking edit notification error: ' . $emailError->getMessage());
        }
    }

    if (ob_get_level() > 0) ob_clean();
    json_response(true, 'Booking details updated successfully.', 200, [
        'data' => $updatedBooking,
        'adjustment' => [
            'type' => $adjustmentType,
            'amount' => abs($delta),
            'currency' => $currency,
            'old_total' => $oldAmount,
            'new_total' => $amount,
        ],
        'email_queued' => $emailQueued,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Admin booking details update error: ' . $e->getMessage());
    if (ob_get_level() > 0) ob_clean();
    json_response(false, 'The booking could not be updated. No changes were saved. Please try again.', 500);
}
