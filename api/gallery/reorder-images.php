<?php
declare(strict_types=1);
require_once __DIR__ . '/_gallery_helpers.php';
apply_cors_headers();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
require_admin_auth();
try {
    $data = read_request_data();
    $folderId = (int) ($data['folder_id'] ?? 0);
    $ids = $data['image_ids'] ?? null;
    $expected = $data['expected_ids'] ?? null;
    if ($folderId < 1 || !is_array($ids) || !is_array($expected) || !$ids || count($ids) !== count($expected)) {
        throw new InvalidArgumentException('Provide the complete photo order for one folder.');
    }
    foreach (array_merge($ids, $expected) as $id) {
        if (!is_int($id) || $id < 1) throw new InvalidArgumentException('Invalid photo identifier.');
    }
    if (count(array_unique($ids)) !== count($ids) || count(array_unique($expected)) !== count($expected)) {
        throw new InvalidArgumentException('Each photo must appear exactly once.');
    }
    $pdo = get_db_connection();
    $pdo->beginTransaction();
    $folder = $pdo->prepare('SELECT id FROM gallery_folders WHERE id = ? FOR UPDATE');
    $folder->execute([$folderId]);
    if (!$folder->fetchColumn()) throw new RuntimeException('This folder no longer exists. Reload the gallery.', 409);
    $current = $pdo->prepare('SELECT id FROM gallery_images WHERE folder_id = ? ORDER BY sort_order ASC, id ASC FOR UPDATE');
    $current->execute([$folderId]);
    $currentIds = array_map('intval', $current->fetchAll(PDO::FETCH_COLUMN));
    if ($currentIds !== array_values($expected)) throw new RuntimeException('The folder changed since it was opened. Reload the gallery before arranging photos again.', 409);
    $sortedIds = $ids; $sortedCurrent = $currentIds;
    sort($sortedIds, SORT_NUMERIC); sort($sortedCurrent, SORT_NUMERIC);
    if ($sortedIds !== $sortedCurrent) throw new InvalidArgumentException('The order must contain only the photos in this folder.');
    $update = $pdo->prepare('UPDATE gallery_images SET sort_order = ? WHERE id = ? AND folder_id = ?');
    foreach (array_values($ids) as $index => $id) $update->execute([$index + 1, $id, $folderId]);
    $pdo->commit();
    json_response(true, 'Photo order saved.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if ($e instanceof InvalidArgumentException) json_response(false, $e->getMessage(), 422);
    if ($e->getCode() === 409) json_response(false, $e->getMessage(), 409);
    error_log('Gallery reorder failed: ' . $e->getMessage());
    json_response(false, 'Could not save the photo order. Please try again.', 500);
}
