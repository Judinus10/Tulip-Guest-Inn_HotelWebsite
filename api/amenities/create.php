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
    $name = clean_string(read_request_data()['name'] ?? '', 120);
    if ($name === '') json_response(false, 'Amenity name is required.', 422);
    $duplicate = $pdo->prepare('SELECT id FROM property_amenities WHERE LOWER(name) = LOWER(:name) LIMIT 1');
    $duplicate->execute([':name' => $name]);
    if ($duplicate->fetch()) json_response(false, 'An amenity with this name already exists.', 409);
    $stmt = $pdo->prepare('INSERT INTO property_amenities (name) VALUES (:name)');
    $stmt->execute([':name' => $name]);
    json_response(true, 'Amenity added.', 201, ['data' => list_amenities($pdo)]);
} catch (Throwable $e) {
    json_response(false, 'Unable to add amenity.', 500);
}
