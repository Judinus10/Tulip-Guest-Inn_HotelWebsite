<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../calendar/ics-helper.php';

if (!ics_enabled()) {
    fwrite(STDOUT, "Booking.com ICS sync is disabled.\n");
    exit(0);
}

$connections = ics_connections();
if ($connections === []) {
    fwrite(STDERR, "No enabled room mappings found in ICS_ROOM_MAPPINGS_JSON.\n");
    exit(1);
}

$lock = fopen(sys_get_temp_dir() . '/tulip-booking-com-ics.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "Booking.com ICS sync is already running.\n");
    exit(0);
}

$pdo = get_db_connection();
$failed = 0;
foreach ($connections as $connection) {
    $result = sync_ics_room($pdo, $connection);
    if (!$result['success']) {
        $failed++;
    }
    fwrite(STDOUT, sprintf(
        "room=%d name=%s status=%s events=%d%s\n",
        (int) $result['room_id'],
        (string) ($result['room_name'] ?? '-'),
        $result['success'] ? 'success' : 'failed',
        (int) ($result['events'] ?? 0),
        empty($result['error']) ? '' : ' error=' . $result['error']
    ));
}

flock($lock, LOCK_UN);
fclose($lock);
exit($failed > 0 ? 1 : 0);
