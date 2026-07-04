<?php
declare(strict_types=1);
require_once __DIR__ . '/_room_helpers.php';
apply_cors_headers();
require_admin_auth();
try {
    $pdo = get_db_connection();
    ensure_room_images_table($pdo);
    $data = read_request_data();
    $id = (int) ($data['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) json_response(false, 'Room id is required.', 422);
    $stmt = $pdo->prepare('SELECT image_path FROM room_images WHERE room_id = :id');
    $stmt->execute([':id' => $id]);
    foreach ($stmt->fetchAll() as $image) {
        $path = realpath(__DIR__ . '/../' . $image['image_path']);
        if ($path && str_contains($path, realpath(__DIR__ . '/../uploads/rooms') ?: '')) @unlink($path);
    }
    $delete = $pdo->prepare('DELETE FROM rooms WHERE id = :id');
    $delete->execute([':id' => $id]);
    json_response(true, 'Room deleted.');
} catch (Throwable $e) {
    json_response(false, 'Unable to delete room.', 500, ['error' => APP_ENV === 'local' ? $e->getMessage() : null]);
}
