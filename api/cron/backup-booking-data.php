<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../bookings/backup-helper.php';

try {
    $pdo = get_db_connection();
    booking_backup_acquire($pdo);
    try { $backup = booking_backup_write($pdo, 'scheduled'); }
    finally { booking_backup_release($pdo); }
    fwrite(STDOUT, sprintf("Booking backup complete: %s (%d bookings, %d bytes)\n", $backup['id'], $backup['booking_count'], $backup['size_bytes']));
    exit(0);
} catch (Throwable $e) {
    error_log('Booking backup cron failed: ' . $e->getMessage());
    fwrite(STDERR, "Booking backup failed. Check the protected server log.\n");
    exit(1);
}
