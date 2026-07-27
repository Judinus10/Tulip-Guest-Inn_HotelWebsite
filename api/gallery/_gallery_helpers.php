<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

function gallery_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function gallery_public_api_base(): string
{
    $base = defined('API_BASE_URL') && API_BASE_URL !== ''
        ? rtrim((string) API_BASE_URL, '/')
        : '';

    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/gallery/index.php')));
        $base = $scheme . '://' . $host . rtrim($scriptDir, '/');
    }

    return rtrim($base, '/');
}

function gallery_upload_public_base(): string
{
    return gallery_public_api_base() . '/uploads/gallery';
}

function gallery_upload_dir(): string
{
    $dir = __DIR__ . '/../uploads/gallery';

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}


function gallery_allowed_image_types(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
}

function gallery_detect_image_mime(string $tmpPath): string
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

function gallery_validate_uploaded_image(array $file, int $maxBytes = 8388608): array
{
    $tmp = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    $allowed = gallery_allowed_image_types();

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid uploaded image. Please choose the image again.');
    }

    if ($size <= 0) {
        throw new RuntimeException('Uploaded image is empty.');
    }

    if ($size > $maxBytes) {
        throw new RuntimeException('Each image must be below 8MB.');
    }

    $mime = gallery_detect_image_mime($tmp);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, and WEBP images are allowed.');
    }

    return [$mime, $allowed[$mime]];
}

function gallery_image_url(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('/^https?:\/\//i', $path)) return $path;

    $path = str_replace('\\', '/', $path);
    $path = ltrim($path, '/');

    // New uploads store only the filename. Older rows may store uploads/gallery/file.jpg
    // or /api/uploads/gallery/file.jpg. Return one correct public URL in every case.
    if (preg_match('#(?:^|/)uploads/gallery/([^/]+)$#i', $path, $matches)) {
        return gallery_upload_public_base() . '/' . rawurlencode($matches[1]);
    }

    if (str_contains($path, '/api/uploads/gallery/')) {
        $filename = basename($path);
        return gallery_upload_public_base() . '/' . rawurlencode($filename);
    }

    return gallery_upload_public_base() . '/' . rawurlencode(basename($path));
}

function gallery_normalize_folder(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'slug' => (string) ($row['slug'] ?? ''),
        'status' => (string) ($row['status'] ?? 'active'),
        'sort_order' => (int) ($row['sort_order'] ?? 1),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
        'image_count' => (int) ($row['image_count'] ?? 0),
        'active_count' => (int) ($row['active_count'] ?? 0),
        'inactive_count' => (int) ($row['inactive_count'] ?? 0),
        'cover_image' => gallery_image_url((string) ($row['cover_image'] ?? '')),
    ];
}

function gallery_normalize_image(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'folder_id' => (int) ($row['folder_id'] ?? 0),
        'folder_name' => (string) ($row['folder_name'] ?? ''),
        'folder_slug' => (string) ($row['folder_slug'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'image_path' => gallery_image_url((string) ($row['image_path'] ?? '')),
        'stored_path' => (string) ($row['image_path'] ?? ''),
        'image_file_name' => (string) ($row['image_file_name'] ?? ''),
        'status' => (string) ($row['status'] ?? 'active'),
        'sort_order' => (int) ($row['sort_order'] ?? 1),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function gallery_reindex_folder(PDO $pdo, int $folderId): void
{
    $stmt = $pdo->prepare('SELECT id FROM gallery_images WHERE folder_id = :folder_id ORDER BY sort_order ASC, id ASC');
    $stmt->execute([':folder_id' => $folderId]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $update = $pdo->prepare('UPDATE gallery_images SET sort_order = :sort_order WHERE id = :id');
    foreach ($ids as $index => $id) {
        $update->execute([':sort_order' => $index + 1, ':id' => (int) $id]);
    }
}

function gallery_move_sort_space(PDO $pdo, int $folderId, int $sortOrder): void
{
    $stmt = $pdo->prepare('UPDATE gallery_images SET sort_order = sort_order + 1 WHERE folder_id = :folder_id AND sort_order >= :sort_order');
    $stmt->execute([':folder_id' => $folderId, ':sort_order' => $sortOrder]);
}

function gallery_delete_file_if_local(string $storedPath): void
{
    $storedPath = trim($storedPath);
    if ($storedPath === '' || preg_match('/^https?:\/\//i', $storedPath)) return;

    $dir = realpath(gallery_upload_dir());
    $file = realpath(gallery_upload_dir() . '/' . basename($storedPath));

    if ($file && $dir && str_starts_with($file, $dir) && is_file($file)) {
        @unlink($file);
    }
}
