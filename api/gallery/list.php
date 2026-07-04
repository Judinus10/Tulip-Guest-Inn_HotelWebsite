<?php
declare(strict_types=1);
require_once __DIR__ . '/_gallery_helpers.php';
apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Method not allowed.', 405);
}

require_admin_auth();

try {
    $pdo = get_db_connection();

    $foldersStmt = $pdo->query(
        "SELECT
            f.*,
            COUNT(i.id) AS image_count,
            SUM(CASE WHEN i.status = 'active' THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN i.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_count,
            (
                SELECT gi.image_path
                FROM gallery_images gi
                WHERE gi.folder_id = f.id
                ORDER BY gi.sort_order ASC, gi.id ASC
                LIMIT 1
            ) AS cover_image
         FROM gallery_folders f
         LEFT JOIN gallery_images i ON i.folder_id = f.id
         GROUP BY f.id
         ORDER BY f.sort_order ASC, f.name ASC"
    );

    $imagesStmt = $pdo->query(
        "SELECT i.*, f.name AS folder_name, f.slug AS folder_slug
         FROM gallery_images i
         INNER JOIN gallery_folders f ON f.id = i.folder_id
         ORDER BY f.sort_order ASC, i.sort_order ASC, i.id ASC"
    );

    json_response(true, 'Gallery loaded successfully.', 200, [
        'data' => [
            'folders' => array_map('gallery_normalize_folder', $foldersStmt->fetchAll(PDO::FETCH_ASSOC)),
            'images' => array_map('gallery_normalize_image', $imagesStmt->fetchAll(PDO::FETCH_ASSOC)),
        ],
    ]);
} catch (Throwable $e) {
    error_log('Gallery list error: ' . $e->getMessage());
    json_response(false, 'Unable to load gallery: ' . $e->getMessage(), 500);
}
