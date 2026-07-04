<?php
declare(strict_types=1);
require_once __DIR__ . '/_gallery_helpers.php';
apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Method not allowed.', 405);
require_admin_auth();

try {
    $data = read_request_data();
    $name = clean_string($data['name'] ?? '', 120);
    $status = clean_string($data['status'] ?? 'active', 20);
    $slug = gallery_slugify($name);

    if ($name === '' || $slug === '') json_response(false, 'Folder name is required.', 422);
    if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';

    $pdo = get_db_connection();
    $exists = $pdo->prepare('SELECT id FROM gallery_folders WHERE slug = :slug LIMIT 1');
    $exists->execute([':slug' => $slug]);
    if ($exists->fetch()) json_response(false, 'Folder already exists.', 409);

    $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM gallery_folders')->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO gallery_folders (name, slug, status, sort_order) VALUES (:name, :slug, :status, :sort_order)');
    $stmt->execute([':name' => $name, ':slug' => $slug, ':status' => $status, ':sort_order' => $sort]);

    json_response(true, 'Folder created successfully.', 201, ['data' => ['id' => (int) $pdo->lastInsertId()]]);
} catch (Throwable $e) {
    error_log('Gallery folder create error: ' . $e->getMessage());
    json_response(false, 'Unable to create folder: ' . $e->getMessage(), 500);
}
