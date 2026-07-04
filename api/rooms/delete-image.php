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
    if ($id <= 0) json_response(false, 'Image id is required.', 422);
    $stmt = $pdo->prepare('SELECT * FROM room_images WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $image = $stmt->fetch();
    if (!$image) json_response(false, 'Image not found.', 404);
    $pdo->prepare('DELETE FROM room_images WHERE id = :id')->execute([':id' => $id]);
    $path = realpath(__DIR__ . '/../' . $image['image_path']);
    if ($path && str_contains($path, realpath(__DIR__ . '/../uploads/rooms') ?: '')) @unlink($path);
    json_response(true, 'Image deleted.');
} catch (Throwable $e) {
    json_response(false, 'Unable to delete image.', 500, ['error' => APP_ENV === 'local' ? $e->getMessage() : null]);
}
