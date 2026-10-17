<?php
declare(strict_types=1);

require_once __DIR__ . '/ics-helper.php';
require_once __DIR__ . '/../bookings/booking-expiry-helper.php';

$roomId = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT);
$token = trim((string) ($_GET['token'] ?? ''));
if (!$roomId || $token === '') {
    http_response_code(404);
    exit;
}

// Export links are separate from import URLs. Preserve existing legacy export
// tokens until a dedicated database-backed export-token feature is introduced.
$legacyRaw = jebal_env_value('ICS_ROOM_MAPPINGS_JSON', '[]');
$legacyMappings = is_array($legacyRaw) ? $legacyRaw : json_decode((string)$legacyRaw, true);
$mapping = null;
foreach (is_array($legacyMappings) ? $legacyMappings : [] as $candidate) {
    if (is_array($candidate) && (int)($candidate['room_id'] ?? 0) === (int)$roomId) {
        $mapping = $candidate;
        break;
    }
}
if (!$mapping || empty($mapping['export_token']) || !hash_equals((string)$mapping['export_token'], $token)) {
    http_response_code(404);
    exit;
}

$pdo = get_db_connection();
$roomStmt = $pdo->prepare('SELECT id, room_name FROM rooms WHERE id = :id LIMIT 1');
$roomStmt->execute([':id' => $roomId]);
$room = $roomStmt->fetch();
if (!$room) {
    http_response_code(404);
    exit;
}

$bookingStmt = $pdo->prepare(
    "SELECT id, check_in_date, check_out_date, status, updated_at
     FROM bookings
     WHERE room_name = :room_name
       " . active_booking_conflict_sql() . "
       AND check_out_date >= CURDATE()
     ORDER BY check_in_date ASC, id ASC"
);
$bookingStmt->execute([':room_name' => $room['room_name'], ':hold_cutoff' => booking_hold_cutoff_datetime()]);

$host = preg_replace('/[^A-Za-z0-9.-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'tulip.local')) ?: 'tulip.local';
$stamp = gmdate('Ymd\THis\Z');
$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Tulip Guest Inn//Room Availability//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'X-WR-CALNAME:' . ics_escape('Tulip - ' . $room['room_name']),
];

foreach ($bookingStmt->fetchAll() as $booking) {
    $updated = !empty($booking['updated_at']) ? strtotime((string) $booking['updated_at']) : false;
    $lines[] = 'BEGIN:VEVENT';
    $lines[] = 'UID:tulip-booking-' . (int) $booking['id'] . '@' . $host;
    $lines[] = 'DTSTAMP:' . ($updated ? gmdate('Ymd\THis\Z', $updated) : $stamp);
    $lines[] = 'DTSTART;VALUE=DATE:' . date('Ymd', strtotime((string) $booking['check_in_date']));
    $lines[] = 'DTEND;VALUE=DATE:' . date('Ymd', strtotime((string) $booking['check_out_date']));
    $lines[] = 'SUMMARY:' . ics_escape('Reserved - Tulip Guest Inn');
    $lines[] = 'STATUS:CONFIRMED';
    $lines[] = 'END:VEVENT';
}

$lines[] = 'END:VCALENDAR';
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="tulip-room-' . (int) $roomId . '.ics"');
header('Cache-Control: no-store, private');
echo implode("\r\n", $lines) . "\r\n";
