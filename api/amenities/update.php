<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/_amenity_helpers.php';
apply_cors_headers();
require_admin_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
try {
    $pdo = get_db_connection();
    ensure_amenity_tables($pdo);
    $data = read_request_data();
    $id = (int) ($data['id'] ?? 0);
    $name = clean_string($data['name'] ?? '', 120);
    if ($id <= 0 || $name === '') json_response(false, 'Amenity id and name are required.', 422);
    $duplicate = $pdo->prepare('SELECT id FROM property_amenities WHERE LOWER(name) = LOWER(:name) AND id <> :id LIMIT 1');
    $duplicate->execute([':name' => $name, ':id' => $id]);
    if ($duplicate->fetch()) json_response(false, 'An amenity with this name already exists.', 409);
    $stmt = $pdo->prepare('UPDATE property_amenities SET name = :name WHERE id = :id');
    $stmt->execute([':id' => $id, ':name' => $name]);
    $rooms = $pdo->prepare('SELECT room_id FROM room_amenities WHERE amenity_id = :id');
    $rooms->execute([':id' => $id]);
    sync_room_amenities_json($pdo, $rooms->fetchAll(PDO::FETCH_COLUMN));
    json_response(true, 'Amenity updated.', 200, ['data' => list_amenities($pdo)]);
} catch (Throwable $e) {
    json_response(false, 'Unable to update amenity.', 500);
}
