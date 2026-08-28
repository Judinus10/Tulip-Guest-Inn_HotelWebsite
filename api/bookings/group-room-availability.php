<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../calendar/ics-helper.php';
require_once __DIR__ . '/booking-expiry-helper.php';

apply_cors_headers();
require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

$data = read_request_data();
$groupId = (int) ($data['booking_group_id'] ?? 0);
$checkInDate = clean_string($data['check_in_date'] ?? '', 20);
$checkOutDate = clean_string($data['check_out_date'] ?? '', 20);

if ($groupId < 1 || !is_valid_date($checkInDate) || !is_valid_date($checkOutDate) || $checkOutDate <= $checkInDate) {
    json_response(false, 'Select valid check-in and check-out dates.', 422);
}

try {
    $pdo = get_db_connection();
    expire_pending_bookings($pdo, null, false);
    if (ics_enabled()) ensure_ics_schema($pdo);

    $rooms = $pdo->query("SELECT id, room_name, max_guests, base_price, currency, status FROM rooms ORDER BY sort_order ASC, id ASC")->fetchAll() ?: [];
    $conflictStmt = $pdo->prepare(
        "SELECT id FROM bookings
         WHERE (booking_group_id IS NULL OR booking_group_id <> :group_id)
           AND room_name = :room_name
           " . active_booking_conflict_sql() . "
           AND :check_in < check_out_date
           AND :check_out > check_in_date
         LIMIT 1"
    );

    $result = [];
    foreach ($rooms as $room) {
        $conflictStmt->execute([
            ':group_id' => $groupId,
            ':room_name' => $room['room_name'],
            ':hold_cutoff' => booking_hold_cutoff_datetime(),
            ':check_in' => $checkInDate,
            ':check_out' => $checkOutDate,
        ]);
        $available = strcasecmp((string) ($room['status'] ?? ''), 'Available') === 0
            && !$conflictStmt->fetch()
            && !ics_room_conflict($pdo, (int) $room['id'], $checkInDate, $checkOutDate);
        $result[] = [
            'room_id' => (int) $room['id'],
            'room_name' => (string) $room['room_name'],
            'capacity' => (int) $room['max_guests'],
            'price_per_night' => (float) $room['base_price'],
            'currency' => (string) ($room['currency'] ?: 'LKR'),
            'available' => $available,
        ];
    }

    json_response(true, 'Room availability loaded.', 200, ['data' => $result]);
} catch (Throwable $e) {
    error_log('Group room availability error: ' . $e->getMessage());
    json_response(false, 'Unable to check room availability.', 500);
}
