<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

apply_cors_headers();
$admin = require_admin_auth();
if ($admin['role'] !== 'super_admin') json_response(false, 'Owner access required.', 403);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

$data = read_request_data();
$id = (int) ($data['id'] ?? 0);

if ($id < 1) {
    json_response(false, 'Valid booking ID is required.', 422);
}

try {
    $pdo = get_db_connection();
    lock_booking_inventory($pdo);
    $q = $pdo->prepare('SELECT * FROM bookings WHERE id = ?'); $q->execute([$id]); $booking = $q->fetch();
    if ($booking && (booking_has_gateway_payment($pdo, $booking) || !empty($booking['booking_group_id']) || $booking['payment_status'] === 'Paid')) json_response(false, 'Retain financial/group records; cancel the booking instead.', 409);
    security_audit('booking_delete', 'requested', (int)$admin['id'], ['booking_id' => $id]);
    $stmt = $pdo->prepare('DELETE FROM bookings WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        json_response(false, 'Booking not found.', 404);
    }

    json_response(true, 'Booking deleted successfully.', 200, [
        'data' => ['id' => $id],
    ]);
} catch (Throwable $e) {
    error_log('Admin booking delete error: ' . $e->getMessage());
    json_response(false, 'Unable to delete booking.', 500);
}
