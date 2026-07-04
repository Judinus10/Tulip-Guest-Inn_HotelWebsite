<?php
declare(strict_types=1);
require_once __DIR__ . '/_gallery_helpers.php';
apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Method not allowed.', 405);
require_admin_auth();

try {
    $data = read_request_data();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) json_response(false, 'Folder id is required.', 422);

    $pdo = get_db_connection();
    $files = $pdo->prepare('SELECT image_path FROM gallery_images WHERE folder_id = :id');
    $files->execute([':id' => $id]);
    foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $path) {
        gallery_delete_file_if_local((string) $path);
    }

    $stmt = $pdo->prepare('DELETE FROM gallery_folders WHERE id = :id');
    $stmt->execute([':id' => $id]);

    json_response(true, 'Folder deleted successfully.');
} catch (Throwable $e) {
    error_log('Gallery folder delete error: ' . $e->getMessage());
    json_response(false, 'Unable to delete folder: ' . $e->getMessage(), 500);
}
