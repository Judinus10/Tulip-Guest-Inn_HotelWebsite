<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

apply_cors_headers();
require_admin_auth();

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
