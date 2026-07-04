<?php
declare(strict_types=1);
require_once __DIR__ . '/_gallery_helpers.php';
apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Method not allowed.', 405);
require_admin_auth();

try {
    $data = read_request_data();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) json_response(false, 'Image id is required.', 422);

    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT folder_id, image_path FROM gallery_images WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$image) json_response(false, 'Image not found.', 404);

    $delete = $pdo->prepare('DELETE FROM gallery_images WHERE id = :id');
    $delete->execute([':id' => $id]);
    gallery_delete_file_if_local((string) $image['image_path']);
    gallery_reindex_folder($pdo, (int) $image['folder_id']);

    json_response(true, 'Image deleted successfully.');
} catch (Throwable $e) {
    error_log('Gallery delete image error: ' . $e->getMessage());
    json_response(false, 'Unable to delete image: ' . $e->getMessage(), 500);
}
