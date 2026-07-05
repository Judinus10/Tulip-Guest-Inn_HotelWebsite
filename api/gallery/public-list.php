<?php
declare(strict_types=1);

require_once __DIR__ . '/_gallery_helpers.php';

/*
 * Public gallery endpoint only.
 * CORS is handled only by api/helpers.php through _gallery_helpers.php.
 */
function gallery_public_has_folder_sort_order(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM gallery_folders LIKE 'sort_order'");
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Method not allowed.', 405);
}

try {
    $pdo = get_db_connection();
    $hasFolderSortOrder = gallery_public_has_folder_sort_order($pdo);

    $folderSortSelect = $hasFolderSortOrder ? 'f.sort_order' : '1 AS sort_order';
    $folderGroupSort = $hasFolderSortOrder ? ', f.sort_order' : '';
    $folderOrder = $hasFolderSortOrder ? 'f.sort_order ASC, f.id ASC' : 'f.id ASC';

    $foldersStmt = $pdo->query("\n        SELECT\n            f.id,\n            f.name,\n            f.slug,\n            f.status,\n            {$folderSortSelect},\n            f.created_at,\n            f.updated_at,\n            COUNT(i.id) AS image_count,\n            SUM(CASE WHEN i.status = 'active' THEN 1 ELSE 0 END) AS active_count,\n            SUM(CASE WHEN i.status <> 'active' THEN 1 ELSE 0 END) AS inactive_count,\n            MIN(CASE WHEN i.status = 'active' THEN i.image_path ELSE NULL END) AS cover_image\n        FROM gallery_folders f\n        LEFT JOIN gallery_images i ON i.folder_id = f.id\n        WHERE f.status = 'active'\n        GROUP BY f.id, f.name, f.slug, f.status{$folderGroupSort}, f.created_at, f.updated_at\n        ORDER BY {$folderOrder}\n    ");

    $imageFolderOrder = $hasFolderSortOrder ? 'f.sort_order ASC, ' : '';

    $imagesStmt = $pdo->query("\n        SELECT\n            i.id,\n            i.folder_id,\n            i.title,\n            i.image_path,\n            i.image_file_name,\n            i.status,\n            i.sort_order,\n            i.created_at,\n            i.updated_at,\n            f.name AS folder_name,\n            f.slug AS folder_slug\n        FROM gallery_images i\n        INNER JOIN gallery_folders f ON f.id = i.folder_id\n        WHERE i.status = 'active'\n          AND f.status = 'active'\n        ORDER BY {$imageFolderOrder}i.sort_order ASC, i.id ASC\n    ");

    $folders = array_map('gallery_normalize_folder', $foldersStmt->fetchAll(PDO::FETCH_ASSOC));
    $images = array_map('gallery_normalize_image', $imagesStmt->fetchAll(PDO::FETCH_ASSOC));

    json_response(true, 'Gallery loaded.', 200, [
        // Keep both shapes so old/new public services can read it safely.
        'folders' => $folders,
        'images' => $images,
        'data' => [
            'folders' => $folders,
            'images' => $images,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Public gallery load error: ' . $e->getMessage());

    json_response(false, 'Unable to load gallery.', 500, [
        'error' => defined('APP_ENV') && APP_ENV === 'local' ? $e->getMessage() : null,
    ]);
}
