<?php
declare(strict_types=1);
require_once __DIR__ . '/_room_helpers.php';
apply_cors_headers();
require_admin_auth();
require_post_request();
$preparedVideo = null;
try {
    $pdo = get_db_connection();
    ensure_rooms_schema($pdo);
    ensure_room_video_column($pdo);
    ensure_room_ical_column($pdo);
    ensure_room_images_table($pdo);
    ensure_amenity_tables($pdo);
    $data = room_input_data();
    if (array_key_exists('booking_ical_url', $data)) require_calendar_permission($pdo, (int)($data['id'] ?? 0), (string)$data['booking_ical_url']);
    $roomNumber = room_number_input($pdo, $data, 0);
    $roomName = clean_string($data['room_name'] ?? '', 150);
    if ($roomName === '') json_response(false, 'Room name is required.', 422);
    $slug = slugify_room($data['slug'] ?? $roomName);
    $legacyAmenities = parse_amenities_input($data['amenities'] ?? $data['amenities_summary'] ?? '');
    $amenityIds = array_key_exists('amenity_ids', $data)
        ? parse_amenity_ids($data['amenity_ids'])
        : amenity_ids_for_names($pdo, $legacyAmenities);
    $amenities = amenity_names_for_ids($pdo, $amenityIds);
    $preparedVideo = prepare_room_video(0, $_FILES['video'] ?? []);
    $stmt = $pdo->prepare("INSERT INTO rooms (room_number, room_name, slug, room_category, description, max_guests, bed_type, room_size, base_price, currency, amenities, show_unavailable_amenities, status, sort_order) VALUES (:room_number, :room_name, :slug, :room_category, :description, :max_guests, :bed_type, :room_size, :base_price, :currency, :amenities, :show_unavailable_amenities, :status, :sort_order)");
    $stmt->execute([
        ':room_number' => $roomNumber,
        ':room_name' => $roomName,
        ':slug' => $slug,
        ':room_category' => normalize_room_category($data['room_category'] ?? $data['category'] ?? 'standard'),
        ':description' => clean_string($data['description'] ?? '', 5000),
        ':max_guests' => max(1, (int) ($data['max_guests'] ?? $data['capacity'] ?? 2)),
        ':bed_type' => clean_string($data['bed_type'] ?? 'Double Bed', 100),
        ':room_size' => max(1, (float) ($data['room_size'] ?? $data['size'] ?? 24)),
        ':base_price' => max(0, (float) ($data['base_price'] ?? $data['price_per_night'] ?? 0)),
        ':currency' => clean_string($data['currency'] ?? 'LKR', 10),
        ':amenities' => json_encode($amenities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':show_unavailable_amenities' => !empty($data['show_unavailable_amenities']) ? 1 : 0,
        ':status' => in_array($data['status'] ?? 'Available', ['Available', 'Unavailable', 'Maintenance'], true) ? $data['status'] : 'Available',
        ':sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
    ]);
    $roomId = (int) $pdo->lastInsertId();
    replace_room_amenities($pdo, $roomId, $amenityIds);
    save_room_ical_url($pdo, $roomId, (string) ($data['booking_ical_url'] ?? ''));
    save_room_images($pdo, $roomId, $_FILES['images'] ?? []);
    save_room_video($pdo, $roomId, $_FILES['video'] ?? [], $preparedVideo);
    $preparedVideo = null;
    $rooms = get_room_payload($pdo, false);
    $room = array_values(array_filter($rooms, static fn($item) => (int) $item['id'] === $roomId))[0] ?? null;
    json_response(true, 'Room created.', 201, ['data' => $room, 'room' => $room]);
} catch (InvalidArgumentException $e) {
    if ($preparedVideo !== null && is_file($preparedVideo)) @unlink($preparedVideo);
    json_response(false, $e->getMessage(), 422);
} catch (RoomVideoProcessingException $e) {
    if ($preparedVideo !== null && is_file($preparedVideo)) @unlink($preparedVideo);
    error_log('Room video upload failed: ' . $e->getMessage());
    json_response(false, $e->userMessage(), 422);
} catch (Throwable $e) {
    if ($preparedVideo !== null && is_file($preparedVideo)) @unlink($preparedVideo);
    if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) json_response(false, 'Room number or slug already exists. Use a unique value.', 422);
    error_log('Room create failed: ' . $e->getMessage());
    json_response(false, 'We could not save the room. Please try again or contact support.', 500);
}
