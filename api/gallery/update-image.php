<?php
declare(strict_types=1);
require_once __DIR__ . '/_gallery_helpers.php';
apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Method not allowed.', 405);
require_admin_auth();

try {
    $id = (int) ($_POST['id'] ?? 0);
    $folderId = (int) ($_POST['folder_id'] ?? 0);
    $title = clean_string($_POST['title'] ?? '', 180);
    $status = clean_string($_POST['status'] ?? 'active', 20);
    $sortOrder = max(1, (int) ($_POST['sort_order'] ?? 1));

    if ($id <= 0) json_response(false, 'Image id is required.', 422);
    if ($folderId <= 0) json_response(false, 'Folder is required.', 422);
    if ($title === '') json_response(false, 'Image title is required.', 422);
    if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';

    $pdo = get_db_connection();
    $currentStmt = $pdo->prepare('SELECT * FROM gallery_images WHERE id = :id LIMIT 1');
    $currentStmt->execute([':id' => $id]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) json_response(false, 'Image not found.', 404);

    $folder = $pdo->prepare('SELECT id FROM gallery_folders WHERE id = :id LIMIT 1');
    $folder->execute([':id' => $folderId]);
    if (!$folder->fetch()) json_response(false, 'Folder not found.', 404);

    $storedPath = (string) $current['image_path'];
    $originalName = (string) ($current['image_file_name'] ?? '');

    if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $uploadDir = gallery_upload_dir();
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) json_response(false, 'Upload folder could not be created.', 500);
        if (!is_writable($uploadDir)) json_response(false, 'Upload folder is not writable.', 500);

        $tmp = (string) $_FILES['image']['tmp_name'];
        try {
            gallery_validate_uploaded_image($_FILES['image']);
        } catch (RuntimeException $validationError) {
            json_response(false, $validationError->getMessage(), 422);
        }

        try {
            $result = secure_image_upload($_FILES['image'], $uploadDir, 'gallery');
        } catch (RuntimeException $validationError) {
            json_response(false, $validationError->getMessage(), 422);
        }
        $filename = $result['filename'];
        gallery_delete_file_if_local($storedPath);
        $storedPath = $filename;
        $originalName = basename((string) $_FILES['image']['name']);
    }

    $pdo->beginTransaction();
    $oldFolderId = (int) $current['folder_id'];
    $stmt = $pdo->prepare(
        'UPDATE gallery_images
         SET folder_id = :folder_id, title = :title, image_path = :image_path, image_file_name = :image_file_name, status = :status, sort_order = :sort_order
         WHERE id = :id'
    );
    $stmt->execute([
        ':folder_id' => $folderId,
        ':title' => $title,
        ':image_path' => $storedPath,
        ':image_file_name' => $originalName,
        ':status' => $status,
        ':sort_order' => $sortOrder,
        ':id' => $id,
    ]);
    gallery_reindex_folder($pdo, $oldFolderId);
    if ($oldFolderId !== $folderId) gallery_reindex_folder($pdo, $folderId);
    $pdo->commit();

    json_response(true, 'Image updated successfully.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Gallery update image error: ' . $e->getMessage());
    json_response(false, 'Unable to update image.', 500);
}
