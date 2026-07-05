<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

// Experience endpoints were missing the shared CORS bootstrap.
// This is required when the admin portal runs from a different subdomain,
// for example https://portal.jebalguesthouse.com calling https://jebalguesthouse.com/api.
apply_cors_headers();

function experience_json(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function experience_db(): PDO
{
    if (!function_exists('get_db_connection')) {
        experience_json([
            'success' => false,
            'message' => 'get_db_connection() not found in api/helpers.php',
        ], 500);
    }

    return get_db_connection();
}

function experience_upload_dir(): string
{
    $dir = __DIR__ . '/../uploads/experience';

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function experience_public_base(): string
{
    $base = '';

    if (defined('ASSET_BASE_URL') && trim((string) ASSET_BASE_URL) !== '') {
        $base = (string) ASSET_BASE_URL;
    } elseif (defined('API_BASE_URL') && trim((string) API_BASE_URL) !== '') {
        $base = (string) API_BASE_URL;
    }

    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api/experience'));
        $apiDir = preg_replace('#/experience$#', '', $scriptDir);
        $base = $scheme . '://' . $host . $apiDir;
    }

    return rtrim($base, '/') . '/uploads/experience';
}

function experience_image_url(?string $path): string
{
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    // Old records may contain the full localhost upload URL.
    // Do not return that old URL on live. Keep only the filename and rebuild it using ASSET_BASE_URL/API_BASE_URL.
    if (preg_match('#^https?://#i', $path)) {
        $parsedPath = parse_url($path, PHP_URL_PATH);
        $path = basename((string) $parsedPath);
    }

    // Also handle stored paths like /api/uploads/experience/file.jpg or uploads/experience/file.jpg.
    $path = basename($path);

    return experience_public_base() . '/' . rawurlencode($path);
}

function experience_ensure_schema(PDO $pdo): void
{
    $columns = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM experience_items");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[strtolower((string) $column['Field'])] = true;
    }

    if (!isset($columns['distance'])) {
        $pdo->exec("ALTER TABLE experience_items ADD COLUMN distance VARCHAR(100) NULL AFTER location");
    }

    if (!isset($columns['duration'])) {
        $pdo->exec("ALTER TABLE experience_items ADD COLUMN duration VARCHAR(100) NULL AFTER distance");
    }
}

function experience_normalize(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'category' => (string) $row['category'],
        'location' => (string) ($row['location'] ?? ''),
        'distance' => (string) ($row['distance'] ?? ''),
        'duration' => (string) ($row['duration'] ?? ''),
        'description' => (string) $row['description'],
        'image_path' => experience_image_url($row['image_path'] ?? ''),
        'stored_path' => (string) ($row['image_path'] ?? ''),
        'status' => (string) $row['status'],
        'sort_order' => (int) $row['sort_order'],
        'created_at' => (string) $row['created_at'],
        'updated_at' => (string) $row['updated_at'],
    ];
}

function experience_allowed_image_types(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
}

function experience_detect_image_mime(string $tmpPath): string
{
    if ($tmpPath === '' || !is_file($tmpPath)) {
        return '';
    }

    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($tmpPath);
        if (is_string($mime) && $mime !== '') {
            return strtolower($mime);
        }
    }

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = @$finfo->file($tmpPath);
        if (is_string($mime) && $mime !== '') {
            return strtolower($mime);
        }
    }

    $imageInfo = @getimagesize($tmpPath);
    if (is_array($imageInfo) && !empty($imageInfo['mime'])) {
        return strtolower((string) $imageInfo['mime']);
    }

    return '';
}

function experience_upload_image(string $field = 'image'): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        experience_json([
            'success' => false,
            'message' => 'Image upload failed.',
        ], 400);
    }

    $tmpPath = (string) ($_FILES[$field]['tmp_name'] ?? '');
    $size = (int) ($_FILES[$field]['size'] ?? 0);
    $allowed = experience_allowed_image_types();

    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        experience_json([
            'success' => false,
            'message' => 'Invalid uploaded image. Please choose the image again.',
        ], 400);
    }

    if ($size <= 0) {
        experience_json([
            'success' => false,
            'message' => 'Uploaded image is empty.',
        ], 400);
    }

    if ($size > 8388608) {
        experience_json([
            'success' => false,
            'message' => 'Image must be below 8MB.',
        ], 400);
    }

    $mime = experience_detect_image_mime($tmpPath);

    if (!isset($allowed[$mime])) {
        experience_json([
            'success' => false,
            'message' => 'Only JPG, PNG, and WEBP images are allowed.',
        ], 400);
    }

    $filename = 'experience_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    $target = experience_upload_dir() . '/' . $filename;

    if (!move_uploaded_file($tmpPath, $target)) {
        experience_json([
            'success' => false,
            'message' => 'Failed to save uploaded image.',
        ], 500);
    }

    return $filename;
}

function experience_delete_file(?string $path): void
{
    if (!$path || preg_match('/^https?:\/\//i', $path)) return;

    $file = experience_upload_dir() . '/' . basename($path);

    if (is_file($file)) {
        @unlink($file);
    }
}