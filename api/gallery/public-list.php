<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/_gallery_helpers.php';

try {
    $pdo = get_db_connection();

    $foldersStmt = $pdo->query("
        SELECT
            f.id,
            f.name,
            f.slug,
            f.status,
            f.sort_order,
            f.created_at,
            f.updated_at
        FROM gallery_folders f
        WHERE f.status = 'active'
        ORDER BY f.sort_order ASC, f.id ASC
    ");

    $imagesStmt = $pdo->query("
        SELECT
            i.id,
            i.folder_id,
            i.title,
            i.image_path,
            i.image_file_name,
            i.status,
            i.sort_order,
            i.created_at,
            i.updated_at,
            f.name AS folder_name,
            f.slug AS folder_slug
        FROM gallery_images i
        INNER JOIN gallery_folders f ON f.id = i.folder_id
        WHERE i.status = 'active'
        AND f.status = 'active'
        ORDER BY f.sort_order ASC, i.sort_order ASC
    ");

    $folders = array_map(
        'gallery_normalize_folder',
        $foldersStmt->fetchAll(PDO::FETCH_ASSOC)
    );

    $images = array_map(
        'gallery_normalize_image',
        $imagesStmt->fetchAll(PDO::FETCH_ASSOC)
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'folders' => $folders,
            'images' => $images,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}