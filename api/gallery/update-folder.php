<?php
declare(strict_types=1);
require_once __DIR__ . '/_gallery_helpers.php';
apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Method not allowed.', 405);
require_admin_auth();

try {
    $data = read_request_data();
    $id = (int) ($data['id'] ?? 0);
    $name = clean_string($data['name'] ?? '', 120);
    $status = clean_string($data['status'] ?? 'active', 20);
    $sortOrder = max(1, (int) ($data['sort_order'] ?? 1));
    $slug = gallery_slugify($name);

    if ($id <= 0) json_response(false, 'Folder id is required.', 422);
    if ($name === '' || $slug === '') json_response(false, 'Folder name is required.', 422);
    if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';

    $pdo = get_db_connection();
    $exists = $pdo->prepare('SELECT id FROM gallery_folders WHERE slug = :slug AND id <> :id LIMIT 1');
    $exists->execute([':slug' => $slug, ':id' => $id]);
    if ($exists->fetch()) json_response(false, 'Another folder already uses this name.', 409);

    $stmt = $pdo->prepare('UPDATE gallery_folders SET name = :name, slug = :slug, status = :status, sort_order = :sort_order WHERE id = :id');
    $stmt->execute([':name' => $name, ':slug' => $slug, ':status' => $status, ':sort_order' => $sortOrder, ':id' => $id]);

    json_response(true, 'Folder updated successfully.');
} catch (Throwable $e) {
    error_log('Gallery folder update error: ' . $e->getMessage());
    json_response(false, 'Unable to update folder: ' . $e->getMessage(), 500);
}
