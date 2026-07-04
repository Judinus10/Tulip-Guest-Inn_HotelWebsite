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
$status = clean_string($data['status'] ?? '', 30);
$allowedStatuses = ['New', 'Read', 'Replied'];

if ($id < 1 || !in_array($status, $allowedStatuses, true)) {
    json_response(false, 'Valid enquiry ID and status are required.', 422);
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('UPDATE enquiries SET status = :status, updated_at = NOW() WHERE id = :id');
    $stmt->execute([':status' => $status, ':id' => $id]);

    if ($stmt->rowCount() === 0) {
        json_response(false, 'Enquiry not found or status unchanged.', 404);
    }

    json_response(true, 'Enquiry status updated successfully.', 200, [
        'data' => ['id' => $id, 'status' => $status],
    ]);
} catch (Throwable $e) {
    error_log('Enquiry update error: ' . $e->getMessage());
    json_response(false, 'Could not update enquiry status.', 500);
}
