<?php
declare(strict_types=1);

require_once __DIR__ . '/_room_helpers.php';
require_once __DIR__ . '/../bookings/booking-expiry-helper.php';
require_once __DIR__ . '/../calendar/ics-helper.php';

apply_cors_headers();

function room_is_available_for_dates(PDO $pdo, int $roomId, string $roomName, string $checkInDate, string $checkOutDate): bool
{
    $stmt = $pdo->prepare(
        "SELECT id
         FROM bookings
         WHERE room_name = :room_name
           " . active_booking_conflict_sql() . "
           AND :requested_check_in < check_out_date
           AND :requested_check_out > check_in_date
         LIMIT 1"
    );
    $stmt->execute([
        ':room_name' => $roomName,
        ':hold_cutoff' => booking_hold_cutoff_datetime(),
        ':requested_check_in' => $checkInDate,
        ':requested_check_out' => $checkOutDate,
    ]);

    return !$stmt->fetch() && !ics_room_conflict($pdo, $roomId, $checkInDate, $checkOutDate);
}

try {
    $pdo = get_db_connection();
    expire_pending_bookings($pdo, null, false);

    $checkInDate = clean_string($_GET['check_in_date'] ?? $_GET['check_in'] ?? '', 20);
    $checkOutDate = clean_string($_GET['check_out_date'] ?? $_GET['check_out'] ?? '', 20);
    $guests = (int) ($_GET['guests'] ?? 0);
    $roomType = clean_string($_GET['room_type'] ?? $_GET['type'] ?? '', 100);

    if (($checkInDate !== '' || $checkOutDate !== '') && (
        !is_valid_date($checkInDate)
        || !is_valid_date($checkOutDate)
        || strtotime($checkOutDate) < strtotime($checkInDate)
    )) {
        json_response(false, 'Enter valid check-in and check-out dates.', 422);
    }

    $rooms = get_room_payload($pdo, true);

    if ($guests > 0) {
        $rooms = array_values(array_filter($rooms, static fn(array $room): bool => (int) ($room['guests'] ?? 0) >= $guests));
    }

    if ($roomType !== '' && strtolower($roomType) !== 'all rooms' && strtolower($roomType) !== 'all') {
        $rooms = array_values(array_filter($rooms, static fn(array $room): bool => strcasecmp((string) ($room['type'] ?? ''), $roomType) === 0));
    }

    if ($checkInDate !== '' && $checkOutDate !== '') {
        $availabilityCheckOutDate = $checkOutDate;
        if ($checkOutDate === $checkInDate) {
            $availabilityCheckOutDate = (new DateTimeImmutable($checkInDate))->modify('+1 day')->format('Y-m-d');
        }

        $rooms = array_values(array_filter($rooms, static fn(array $room): bool => room_is_available_for_dates($pdo, (int) ($room['id'] ?? 0), (string) $room['name'], $checkInDate, $availabilityCheckOutDate)));
    }

    json_response(true, 'Rooms loaded.', 200, [
        'data' => $rooms,
        'rooms' => $rooms,
        'filters' => [
            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
            'guests' => $guests,
            'room_type' => $roomType,
        ],
    ]);
} catch (Throwable $e) {
    json_response(false, 'Unable to load rooms.', 500, ['error' => APP_ENV === 'local' ? $e->getMessage() : null]);
}
