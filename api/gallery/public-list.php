<?php
declare(strict_types=1);

require_once __DIR__ . '/_gallery_helpers.php';

/**
 * Public gallery endpoint only.
 *
 * This endpoint is intentionally NOT protected by require_admin_auth().
 * It can still use the shared db/json helpers, but it must always send
 * public-safe CORS headers before returning JSON to the React public site.
 */
function apply_public_gallery_cors_headers(): void
{
    apply_cors_headers();

    $origin = rtrim(trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), " \t\n\r\0\x0B\"'"), '/');
    if ($origin === '') {
        return;
    }

    $allowed = [];

    foreach (['FRONTEND_URL', 'PUBLIC_APP_URL', 'APP_BASE_URL'] as $constantName) {
        if (defined($constantName)) {
            $value = rtrim(trim((string) constant($constantName), " \t\n\r\0\x0B\"'"), '/');
            if ($value !== '') {
                $allowed[$value] = true;
            }
        }
    }

    if (defined('ALLOWED_ORIGINS') && is_array(ALLOWED_ORIGINS)) {
        foreach (ALLOWED_ORIGINS as $allowedOrigin) {
            $value = rtrim(trim((string) $allowedOrigin, " \t\n\r\0\x0B\"'"), '/');
            if ($value !== '') {
                $allowed[$value] = true;
            }
        }
    }

    $host = parse_url($origin, PHP_URL_HOST);
    $isLocalPublicOrigin = defined('APP_ENV')
        && APP_ENV === 'local'
        && in_array($host, ['localhost', '127.0.0.1'], true);

    if (isset($allowed[$origin]) || $isLocalPublicOrigin) {
        header('Access-Control-Allow-Origin: ' . $origin, true);
        header('Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers', true);
    }
}

function gallery_public_has_folder_sort_order(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM gallery_folders LIKE 'sort_order'");
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

apply_public_gallery_cors_headers();

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
