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
    $id = (int) (read_request_data()['id'] ?? 0);
    if ($id <= 0) json_response(false, 'Amenity id is required.', 422);
    $rooms = $pdo->prepare('SELECT room_id FROM room_amenities WHERE amenity_id = :id');
    $rooms->execute([':id' => $id]);
    $roomIds = array_map('intval', $rooms->fetchAll(PDO::FETCH_COLUMN));
    $stmt = $pdo->prepare('DELETE FROM property_amenities WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) json_response(false, 'Amenity not found.', 404);
    sync_room_amenities_json($pdo, $roomIds);
    json_response(true, 'Amenity deleted.', 200, ['data' => list_amenities($pdo)]);
} catch (Throwable $e) {
    json_response(false, 'Unable to delete amenity.', 500);
}
