<?php
declare(strict_types=1);
require_once __DIR__ . '/_room_helpers.php';
require_once __DIR__ . '/../calendar/url-guard.php';
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
    $id = (int) ($data['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) json_response(false, 'Room id is required.', 422);
    // Validate calendar ownership before updating any room fields or media.
    $calendarUrl = trim((string) ($data['booking_ical_url'] ?? ''));
    if ($calendarUrl !== '') {
        $parts = parse_url($calendarUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!$parts || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || !in_array($host, ['ical.booking.com', 'admin.booking.com'], true)) {
            json_response(false, 'Enter a valid Booking.com iCal export URL.', 422);
        }
        if (tulip_ics_duplicate_room($pdo, $calendarUrl, $id) !== null) {
            json_response(false, 'This Booking.com calendar URL is already assigned to another room.', 422);
        }
    }
    $roomNumber = room_number_input($pdo, $data, $id);
    $roomName = clean_string($data['room_name'] ?? '', 150);
    if ($roomName === '') json_response(false, 'Room name is required.', 422);
    $legacyAmenities = parse_amenities_input($data['amenities'] ?? $data['amenities_summary'] ?? '');
    $amenityIds = array_key_exists('amenity_ids', $data)
        ? parse_amenity_ids($data['amenity_ids'])
        : amenity_ids_for_names($pdo, $legacyAmenities);
    $amenities = amenity_names_for_ids($pdo, $amenityIds);
    $preparedVideo = prepare_room_video($id, $_FILES['video'] ?? []);
    $stmt = $pdo->prepare("UPDATE rooms SET room_number = :room_number, room_name = :room_name, slug = :slug, room_category = :room_category, description = :description, max_guests = :max_guests, bed_type = :bed_type, room_size = :room_size, base_price = :base_price, currency = :currency, amenities = :amenities, show_unavailable_amenities = :show_unavailable_amenities, status = :status, sort_order = :sort_order WHERE id = :id");
    $stmt->execute([
        ':id' => $id,
        ':room_number' => $roomNumber,
        ':room_name' => $roomName,
        ':slug' => slugify_room($data['slug'] ?? $roomName),
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
    replace_room_amenities($pdo, $id, $amenityIds);
    if (array_key_exists('booking_ical_url', $data)) save_room_ical_url($pdo, $id, (string)$data['booking_ical_url']);
    save_room_images($pdo, $id, $_FILES['images'] ?? []);
    if (!empty($data['remove_video'])) {
        $old = $pdo->prepare('SELECT video_path FROM rooms WHERE id = :id');
        $old->execute([':id' => $id]);
        $oldPath = $old->fetchColumn();
        $pdo->prepare('UPDATE rooms SET video_path = NULL WHERE id = :id')->execute([':id' => $id]);
        delete_room_video_file($oldPath);
    }
    save_room_video($pdo, $id, $_FILES['video'] ?? [], $preparedVideo);
    $preparedVideo = null;
    $images = fetch_room_images($pdo, [$id]);
    $stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $room = $stmt->fetch();
    $assignments = fetch_room_amenity_assignments($pdo, [$id]);
    $normalized = normalize_room($room, $images[$id] ?? [], $assignments[$id] ?? [], list_amenities($pdo), true);
    json_response(true, 'Room updated.', 200, ['data' => $normalized, 'room' => $normalized]);
} catch (InvalidArgumentException $e) {
    if ($preparedVideo !== null && is_file($preparedVideo)) @unlink($preparedVideo);
    json_response(false, $e->getMessage(), 422);
} catch (RoomVideoProcessingException $e) {
    if ($preparedVideo !== null && is_file($preparedVideo)) @unlink($preparedVideo);
    json_response(false, $e->getMessage(), 422);
} catch (Throwable $e) {
    if ($preparedVideo !== null && is_file($preparedVideo)) @unlink($preparedVideo);
    if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) json_response(false, 'Room number or slug already exists. Use a unique value.', 422);
    json_response(false, 'Unable to update room.', 500, ['error' => APP_ENV === 'local' ? $e->getMessage() : null]);
}
