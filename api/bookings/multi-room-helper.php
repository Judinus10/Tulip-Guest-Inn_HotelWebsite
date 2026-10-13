<?php
declare(strict_types=1);

function multi_room_group_id(array $booking): int
{
    return (int) ($booking['booking_group_id'] ?? 0);
}

function multi_room_booking_number(array $booking): string
{
    $groupId = multi_room_group_id($booking);
    if ($groupId > 0) {
        return 'MB-' . str_pad((string) $groupId, 6, '0', STR_PAD_LEFT);
    }
    return 'BK-' . str_pad((string) ($booking['id'] ?? 0), 5, '0', STR_PAD_LEFT);
}

function multi_room_group_rows(PDO $pdo, int $groupId): array
{
    if ($groupId < 1) return [];

    $stmt = $pdo->prepare(
        'SELECT b.*, r.id AS room_id, r.max_guests, r.base_price
         FROM bookings b
         LEFT JOIN rooms r ON r.room_name = b.room_name
         WHERE b.booking_group_id = :group_id
         ORDER BY b.is_group_primary DESC, b.id ASC'
    );
    $stmt->execute([':group_id' => $groupId]);
    return $stmt->fetchAll() ?: [];
}

function multi_room_primary_booking_id(PDO $pdo, int $groupId): int
{
    $stmt = $pdo->prepare(
        'SELECT id FROM bookings
         WHERE booking_group_id = :group_id
         ORDER BY is_group_primary DESC, id ASC
         LIMIT 1'
    );
    $stmt->execute([':group_id' => $groupId]);
    return (int) ($stmt->fetchColumn() ?: 0);
}


/**
 * Validate requested room/guest allocation. Throws before opening a transaction.
 * Returns a room-id => guest-count map.
 */
function multi_room_validate_allocations(array $requestedRooms, int $totalGuests): array
{
    $allocations = [];
    $allocatedGuests = 0;
    foreach ($requestedRooms as $requestedRoom) {
        if (!is_array($requestedRoom)) {
            throw new InvalidArgumentException('Invalid room allocation.');
        }
        $roomId = (int) ($requestedRoom['room_id'] ?? 0);
        $guests = (int) ($requestedRoom['guests'] ?? 0);
        if ($roomId < 1 || $guests < 1 || isset($allocations[$roomId])) {
            throw new InvalidArgumentException('Invalid room allocation.');
        }
        $allocations[$roomId] = $guests;
        $allocatedGuests += $guests;
    }
    if ($allocatedGuests !== $totalGuests) {
        throw new InvalidArgumentException('Allocated guests must equal the total guest count.');
    }
    return $allocations;
}

/** Lock all selected physical rooms for the duration of the booking transaction. */
function multi_room_lock_selected_rooms(PDO $pdo, array $allocations): array
{
    $ids = array_keys($allocations);
    if (!$ids) throw new InvalidArgumentException('Select at least two rooms.');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, room_name, max_guests, base_price, currency, status
         FROM rooms WHERE id IN ({$placeholders})
         ORDER BY sort_order ASC, id ASC FOR UPDATE"
    );
    $stmt->execute($ids);
    $rooms = $stmt->fetchAll() ?: [];
    if (count($rooms) !== count($ids)) {
        throw new RuntimeException('One or more assigned rooms no longer exist.');
    }
    return $rooms;
}

/** Calculate each room's nightly subtotal after checking capacity and conflicts. */
function multi_room_prepare_rooms(
    PDO $pdo, array $rooms, array $allocations,
    string $checkInDate, string $checkOutDate, int $nights
): array {
    $total = 0.0;
    $currency = PAYMENT_CURRENCY;
    foreach ($rooms as &$room) {
        $id = (int) $room['id'];
        $guests = $allocations[$id] ?? 0;
        if (($room['status'] ?? '') !== 'Available' || $guests > (int) ($room['max_guests'] ?? 0)) {
            throw new RuntimeException('An assigned room cannot accommodate the selected guests.');
        }
        $conflict = $pdo->prepare(
            "SELECT id FROM bookings WHERE room_name = :room_name
               " . active_booking_conflict_sql() . "
               AND :requested_check_in < check_out_date
               AND :requested_check_out > check_in_date
             LIMIT 1 FOR UPDATE"
        );
        $conflict->execute([
            ':room_name' => $room['room_name'],
            ':hold_cutoff' => booking_hold_cutoff_datetime(),
            ':requested_check_in' => $checkInDate,
            ':requested_check_out' => $checkOutDate,
        ]);
        if ($conflict->fetch() || ics_room_conflict($pdo, $id, $checkInDate, $checkOutDate)) {
            throw new RuntimeException('One of the assigned rooms is no longer available. Please search again.');
        }
        $room['allocated_guests'] = $guests;
        $room['room_total'] = round((float) $room['base_price'] * $nights, 2);
        $total += $room['room_total'];
        $currency = (string) ($room['currency'] ?: $currency);
    }
    unset($room);
    return [$rooms, $total, $currency];
}

/** Spread an offer discount across rooms, preserving the exact overall discount. */
function multi_room_apply_discount(array $rooms, array $offerPrice, float $subtotal): array
{
    $remaining = (float) $offerPrice['discount'];
    foreach ($rooms as $index => &$room) {
        $discount = $index === count($rooms) - 1
            ? $remaining
            : round((float) $offerPrice['discount'] * ($room['room_total'] / max(0.01, $subtotal)), 2);
        $discount = min($discount, $room['room_total']);
        $room['room_subtotal'] = $room['room_total'];
        $room['room_discount'] = $discount;
        $room['room_total'] = round($room['room_total'] - $discount, 2);
        $remaining = round($remaining - $discount, 2);
    }
    unset($room);
    return $rooms;
}
