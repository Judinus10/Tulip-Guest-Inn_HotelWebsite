<?php
declare(strict_types=1);
require_once __DIR__ . '/guest-occupancy-helper.php';

/** Read the saved party, never reclassify it using today's room settings. */
function booking_guest_rooms(PDO $pdo, int $bookingId, int $groupId = 0): array
{
    $where = $groupId > 0 ? 'b.booking_group_id = :id' : 'b.id = :id';
    $stmt = $pdo->prepare("SELECT b.id AS booking_id, b.room_id, b.room_name,
        COALESCE(r.room_number, '') AS room_number, b.guests, b.adults, b.children, b.children_ages_json
        FROM bookings b LEFT JOIN rooms r ON r.id = b.room_id
        WHERE $where ORDER BY b.is_group_primary DESC, b.id ASC");
    $stmt->execute([':id' => $groupId > 0 ? $groupId : $bookingId]);
    return array_map(static fn(array $row): array => array_merge($row, booking_stored_party($row)), $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function booking_guest_totals(array $rooms): array
{
    $totals = ['guests' => 0, 'adults' => 0, 'children' => 0, 'children_ages' => []];
    foreach ($rooms as $room) {
        $party = booking_guest_display_party($room);
        $totals['guests'] += $party['adults'] + $party['children'];
        $totals['adults'] += $party['adults'];
        $totals['children'] += $party['children'];
        $totals['children_ages'] = array_merge($totals['children_ages'], $party['children_ages']);
    }
    return $totals;
}

function booking_with_guest_details(PDO $pdo, array $booking): array
{
    $id = (int) ($booking['id'] ?? 0);
    if ($id < 1) return $booking;
    $stmt = $pdo->prepare('SELECT booking_group_id FROM bookings WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return $booking;
    $rooms = booking_guest_rooms($pdo, $id, (int) ($row['booking_group_id'] ?? 0));
    return array_merge($booking, booking_guest_totals($rooms), ['room_assignments' => $rooms]);
}

function booking_guest_display_party(array $row): array
{
    $party = booking_stored_party($row);
    if (isset($row['children_ages']) && is_array($row['children_ages'])) $party['children_ages'] = $row['children_ages'];
    return $party;
}

function booking_guest_summary(array $row): string
{
    $party = booking_guest_display_party($row);
    return $party['adults'] . ($party['adults'] === 1 ? ' adult' : ' adults') . ', '
        . $party['children'] . ($party['children'] === 1 ? ' child' : ' children');
}

function booking_guest_ages(array $row): string
{
    $party = booking_guest_display_party($row);
    if ($party['children'] === 0) return '';
    if (count($party['children_ages']) !== $party['children']) return 'Not recorded';
    return implode(', ', array_map(static fn($age): string => (int) $age === 0 ? 'Under 1' : (string) (int) $age, $party['children_ages']));
}

/** Plain-text values: email renderers escape every label and value. */
function booking_guest_detail_rows(array $booking): array
{
    $rows = ['Guests' => booking_guest_summary($booking)];
    $rooms = $booking['room_assignments'] ?? [];
    if (count($rooms) > 1) {
        foreach ($rooms as $index => $room) {
            $label = 'Room ' . ($index + 1) . ' - ' . ($room['room_name'] ?? 'Room');
            $number = trim((string) ($room['room_number'] ?? ''));
            $value = ($number !== '' ? 'Assigned number: ' . $number . '; ' : '') . booking_guest_summary($room);
            $rows[$label] = $value;
        }
    }
    return $rows;
}
