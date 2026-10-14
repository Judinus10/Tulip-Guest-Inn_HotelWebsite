<?php
declare(strict_types=1);
require_once __DIR__ . '/ics-helper.php';

function ics_run_all(PDO $pdo): array
{
    if (!ics_master_sync_enabled($pdo)) throw new RuntimeException('Calendar synchronization is paused.');
    $connections = ics_connections($pdo);
    if (!$connections) throw new RuntimeException('No configured room calendars. Add Booking.com iCal URLs in Rooms → Edit Room.');
    $lock = fopen(sys_get_temp_dir() . '/tulip-booking-com-ics.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) fclose($lock);
        throw new RuntimeException('Calendar synchronization is already running.');
    }
    try {
        $results = [];
        foreach ($connections as $connection) {
            // Re-check between rooms so disabling the switch stops remaining imports.
            if (!ics_master_sync_enabled($pdo)) break;
            $results[] = sync_ics_room($pdo, $connection);
        }
        return $results;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
