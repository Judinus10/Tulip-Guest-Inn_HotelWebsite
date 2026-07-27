<?php
declare(strict_types=1);
require_once __DIR__ . '/_gallery_helpers.php';
apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Method not allowed.', 405);
require_admin_auth();

try {
    $folderId = (int) ($_POST['folder_id'] ?? 0);
    $status = clean_string($_POST['status'] ?? 'active', 20);
    $sortOrder = max(1, (int) ($_POST['sort_order'] ?? 1));

    if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';
    if ($folderId <= 0) json_response(false, 'Folder is required.', 422);
    if (empty($_FILES['images'])) json_response(false, 'Select at least one image.', 422);

    $uploadDir = gallery_upload_dir();
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) json_response(false, 'Upload folder could not be created.', 500);
    if (!is_writable($uploadDir)) json_response(false, 'Upload folder is not writable.', 500);

    $pdo = get_db_connection();
    $folder = $pdo->prepare('SELECT id FROM gallery_folders WHERE id = :id LIMIT 1');
    $folder->execute([':id' => $folderId]);
    if (!$folder->fetch()) json_response(false, 'Folder not found.', 404);

    $files = $_FILES['images'];
    $count = is_array($files['name']) ? count($files['name']) : 0;
    if ($count === 0) json_response(false, 'Select at least one image.', 422);
    if ($count > 20) json_response(false, 'Upload no more than 20 images at once.', 422);

    $saved = [];

    $pdo->beginTransaction();
    gallery_move_sort_space($pdo, $folderId, $sortOrder);

    $insert = $pdo->prepare(
        'INSERT INTO gallery_images (folder_id, title, image_path, image_file_name, status, sort_order)
         VALUES (:folder_id, :title, :image_path, :image_file_name, :status, :sort_order)'
    );

    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Image upload failed.');
        }

        $tmp = (string) $files['tmp_name'][$i];
        $original = basename((string) $files['name'][$i]);
        gallery_validate_uploaded_image([
            'tmp_name' => $tmp,
            'size' => $files['size'][$i] ?? 0,
        ]);

        $title = clean_string($_POST['titles'][$i] ?? pathinfo($original, PATHINFO_FILENAME), 180);
        if ($title === '') $title = 'Gallery Image';

        $result = secure_image_upload([
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'tmp_name' => $tmp,
            'size' => $files['size'][$i] ?? 0,
        ], $uploadDir, 'gallery');
        $filename = $result['filename'];

        $insert->execute([
            ':folder_id' => $folderId,
            ':title' => $title,
            ':image_path' => $filename,
            ':image_file_name' => $original,
            ':status' => $status,
            ':sort_order' => $sortOrder + $i,
        ]);
        $saved[] = (int) $pdo->lastInsertId();
    }

    gallery_reindex_folder($pdo, $folderId);
    $pdo->commit();

    json_response(true, 'Images uploaded successfully.', 201, ['data' => ['ids' => $saved]]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Gallery upload error: ' . $e->getMessage());
    json_response(false, 'Unable to upload gallery images.', 500);
}
