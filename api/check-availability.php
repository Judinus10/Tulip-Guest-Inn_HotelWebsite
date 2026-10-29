<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/bookings/booking-expiry-helper.php';
require_once __DIR__ . '/calendar/ics-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

rate_limit_or_fail('check_availability', 30, 15);

$data = read_request_data();
$roomId = (int) ($data['room_id'] ?? 0);
$roomName = clean_string($data['room_name'] ?? '', 150);
$checkInDate = clean_string($data['check_in_date'] ?? '', 20);
$checkOutDate = clean_string($data['check_out_date'] ?? '', 20);

if (($roomId < 1 && $roomName === '') || $checkInDate === '' || $checkOutDate === '') {
    json_response(false, 'Room, check-in date, and check-out date are required.', 422);
}

if (!is_valid_date($checkInDate) || !is_valid_date($checkOutDate) || strtotime($checkOutDate) < strtotime($checkInDate)) {
    json_response(false, 'Enter valid check-in and check-out dates.', 422);
}

$availabilityCheckOutDate = $checkOutDate;
if ($checkOutDate === $checkInDate) {
    $availabilityCheckOutDate = (new DateTimeImmutable($checkInDate))->modify('+1 day')->format('Y-m-d');
}

try {
    $pdo = get_db_connection();
    expire_pending_bookings($pdo, null, false);

    $room = booking_selected_room($pdo, $roomId, $roomName);
    if (!$room) json_response(false, 'Room not found or room name is ambiguous.', 404);
    $roomId = (int) $room['id'];
    $roomName = (string) $room['room_name'];

    $stmt = $pdo->prepare(
        "SELECT id, check_in_date, check_out_date, status, payment_status, created_at
         FROM bookings
         WHERE (room_id = :room_id OR room_id IS NULL)
           " . active_booking_conflict_sql() . "
           AND :requested_check_in < check_out_date
           AND :requested_check_out > check_in_date
         LIMIT 1"
    );
    $stmt->execute([
        ':room_id' => $roomId,
        ':hold_cutoff' => booking_hold_cutoff_datetime(),
        ':requested_check_in' => $checkInDate,
        ':requested_check_out' => $availabilityCheckOutDate,
    ]);
    $conflict = $stmt->fetch();
    $bookingComConflict = ics_room_conflict($pdo, $roomId, $checkInDate, $availabilityCheckOutDate);
    $unavailable = strcasecmp((string) ($room['status'] ?? ''), 'Available') !== 0 || (bool) $conflict || $bookingComConflict;

    json_response(true, $unavailable ? 'Room is unavailable for the selected dates.' : 'Room is available.', 200, [
        'available' => !$unavailable,
        'room_id' => $roomId,
        'room_name' => $roomName,
        'conflict' => $conflict ?: ($bookingComConflict ? ['source' => 'booking.com'] : null),
    ]);
} catch (Throwable $e) {
    error_log('Availability check error: ' . $e->getMessage());
    json_response(false, 'Unable to check availability.', 500);
}
