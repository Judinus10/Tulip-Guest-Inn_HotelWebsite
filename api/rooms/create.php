<?php
declare(strict_types=1);
require_once __DIR__ . '/_room_helpers.php';
apply_cors_headers();
require_admin_auth();
try {
    $pdo = get_db_connection();
    ensure_rooms_schema($pdo);
    ensure_room_images_table($pdo);
    $data = room_input_data();
    $roomName = clean_string($data['room_name'] ?? '', 150);
    if ($roomName === '') json_response(false, 'Room name is required.', 422);
    $slug = slugify_room($data['slug'] ?? $roomName);
    $amenities = parse_amenities_input($data['amenities'] ?? $data['amenities_summary'] ?? '');
    $stmt = $pdo->prepare("INSERT INTO rooms (room_name, slug, room_category, description, max_guests, bed_type, room_size, base_price, currency, amenities, status, sort_order) VALUES (:room_name, :slug, :room_category, :description, :max_guests, :bed_type, :room_size, :base_price, :currency, :amenities, :status, :sort_order)");
    $stmt->execute([
        ':room_name' => $roomName,
        ':slug' => $slug,
        ':description' => clean_string($data['description'] ?? '', 5000),
        ':max_guests' => max(1, (int) ($data['max_guests'] ?? $data['capacity'] ?? 2)),
        ':bed_type' => clean_string($data['bed_type'] ?? 'Double Bed', 100),
        ':room_size' => max(1, (float) ($data['room_size'] ?? $data['size'] ?? 24)),
        ':base_price' => max(0, (float) ($data['base_price'] ?? $data['price_per_night'] ?? 0)),
        ':currency' => clean_string($data['currency'] ?? 'LKR', 10),
        ':amenities' => json_encode($amenities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':status' => in_array($data['status'] ?? 'Available', ['Available', 'Unavailable', 'Maintenance'], true) ? $data['status'] : 'Available',
        ':sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
    ]);
    $roomId = (int) $pdo->lastInsertId();
    save_room_images($pdo, $roomId, $_FILES['images'] ?? []);
    $rooms = get_room_payload($pdo, false);
    $room = array_values(array_filter($rooms, static fn($item) => (int) $item['id'] === $roomId))[0] ?? null;
    json_response(true, 'Room created.', 201, ['data' => $room, 'room' => $room]);
} catch (Throwable $e) {
    json_response(false, 'Unable to create room.', 500, ['error' => APP_ENV === 'local' ? $e->getMessage() : null]);
}
