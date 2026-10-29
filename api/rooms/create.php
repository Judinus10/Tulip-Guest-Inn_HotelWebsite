<?php
declare(strict_types=1);
require_once __DIR__ . '/_room_helpers.php';
apply_cors_headers();
$admin = require_admin_auth();
require_post_request();
$preparedVideo = null;
$createdFiles = [];
$obsoleteFiles = [];
$commitAttempted = false;
$pdo = null;
try {
    $pdo = get_db_connection();
    ensure_rooms_schema($pdo);
    ensure_room_video_column($pdo);
    ensure_room_ical_column($pdo);
    ensure_room_images_table($pdo);
    ensure_amenity_tables($pdo);
    lock_booking_inventory($pdo);
    $data = room_input_data();
    if (trim((string) ($data['booking_ical_url'] ?? '')) !== '' && ($admin['role'] ?? '') !== 'super_admin') throw new RoomSavePermissionException('Owner access required to change calendar feeds.');
    if (room_removed_image_ids($data)) throw new InvalidArgumentException('New rooms have no saved photos to remove.');
    $imageOrder = room_image_order_input($pdo, 0, $data, $_FILES['images'] ?? []);
    $roomNumber = room_number_input($pdo, $data, 0);
    $roomName = clean_string($data['room_name'] ?? '', 150);
    if ($roomName === '') json_response(false, 'Room name is required.', 422);
    $slug = slugify_room($data['slug'] ?? $roomName);
    $preparedVideo = prepare_room_video(0, $_FILES['video'] ?? []);
    $pdo->beginTransaction();
    $legacyAmenities = parse_amenities_input($data['amenities'] ?? $data['amenities_summary'] ?? '');
    $amenityIds = array_key_exists('amenity_ids', $data)
        ? parse_amenity_ids($data['amenity_ids'])
        : amenity_ids_for_names($pdo, $legacyAmenities);
    $amenities = amenity_names_for_ids($pdo, $amenityIds);
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
        ':status' => in_array($data['status'] ?? 'Available', ['Available', 'Unavailable', 'Maintenance'], true) ? ($data['status'] ?? 'Available') : 'Available',
        ':sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
    ]);
    $roomId = (int) $pdo->lastInsertId();
    replace_room_amenities($pdo, $roomId, $amenityIds);
    save_room_ical_url($pdo, $roomId, (string) ($data['booking_ical_url'] ?? ''), true);
    $uploadedImageIds = save_room_images($pdo, $roomId, $_FILES['images'] ?? [], $createdFiles);
    apply_room_image_order($pdo, $roomId, $imageOrder, $uploadedImageIds);
    save_room_video($pdo, $roomId, $_FILES['video'] ?? [], $preparedVideo, $obsoleteFiles);
    $room = room_saved_payload($pdo, $roomId);
    $commitAttempted = true;
    $pdo->commit();
    $preparedVideo = null;
    room_cleanup_obsolete_files($pdo, $obsoleteFiles);
    json_response(true, 'Room created.', 201, ['data' => $room, 'room' => $room]);
} catch (RoomSavePermissionException $e) {
    room_rollback_save($pdo, $createdFiles, $preparedVideo, $commitAttempted);
    json_response(false, $e->getMessage(), 403);
} catch (OutOfBoundsException $e) {
    room_rollback_save($pdo, $createdFiles, $preparedVideo, $commitAttempted);
    json_response(false, $e->getMessage(), 404);
} catch (InvalidArgumentException $e) {
    room_rollback_save($pdo, $createdFiles, $preparedVideo, $commitAttempted);
    json_response(false, $e->getMessage(), 422);
} catch (RoomVideoProcessingException $e) {
    room_rollback_save($pdo, $createdFiles, $preparedVideo, $commitAttempted);
    error_log('Room video upload failed: ' . $e->getMessage());
    json_response(false, $e->userMessage(), 422);
} catch (Throwable $e) {
    room_rollback_save($pdo, $createdFiles, $preparedVideo, $commitAttempted);
    if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) json_response(false, 'Room number or slug already exists. Use a unique value.', 422);
    error_log('Room create failed: ' . $e->getMessage());
    json_response(false, $commitAttempted ? 'We could not confirm the save. Refresh the room list before trying again.' : 'The room could not be saved. No room changes were saved. Please try again.', 500);
}
