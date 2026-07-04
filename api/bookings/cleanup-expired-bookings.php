<?php
/**
 * Admin cleanup endpoint.
 * 1. Expires current unpaid pending bookings and sends expiry emails.
 * 2. Deletes cancelled/expired bookings older than 90 days to keep DB clean.
 *
 * Call from admin or a daily server cron with an admin Bearer token.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/booking-expiry-helper.php';
require_once __DIR__ . '/booking-audit-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

require_admin_auth();

try {
    $pdo = get_db_connection();

    $expiredNow = expire_pending_bookings($pdo, null, true);

    $cutoff = (new DateTimeImmutable('-90 days'))->format('Y-m-d H:i:s');

    $selectOld = $pdo->prepare(
        "SELECT id
         FROM bookings
         WHERE status = 'Cancelled'
           AND payment_status IN ('Cancelled', 'Failed', 'Refunded', 'Payment Pending')
           AND updated_at < :cutoff"
    );
    $selectOld->execute([':cutoff' => $cutoff]);
    $oldIds = array_map('intval', $selectOld->fetchAll(PDO::FETCH_COLUMN) ?: []);

    $deletedOld = 0;
    if ($oldIds) {
        foreach ($oldIds as $bookingId) {
            booking_audit_log($pdo, $bookingId, 'old_expired_booking_deleted', 'Old Expired Booking Deleted', 'Cancelled/expired booking older than 90 days was removed by cleanup.', [
                'cutoff' => $cutoff,
            ]);
        }

        $placeholders = implode(',', array_fill(0, count($oldIds), '?'));
        $delete = $pdo->prepare("DELETE FROM bookings WHERE id IN ({$placeholders})");
        $delete->execute($oldIds);
        $deletedOld = $delete->rowCount();
    }

    json_response(true, 'Cleanup completed.', 200, [
        'expired_now' => $expiredNow,
        'deleted_old_cancelled_bookings' => $deletedOld,
        'delete_cutoff' => $cutoff,
    ]);
} catch (Throwable $exception) {
    error_log('Expired booking cleanup error: ' . $exception->getMessage());
    json_response(false, 'Unable to clean expired bookings.', 500);
}
