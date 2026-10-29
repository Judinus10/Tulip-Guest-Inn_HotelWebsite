<?php
declare(strict_types=1);
require_once __DIR__ . '/_room_helpers.php';
require_once __DIR__ . '/../calendar/url-guard.php';
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
    $id = (int) ($data['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) json_response(false, 'Room id is required.', 422);
    $roomName = clean_string($data['room_name'] ?? '', 150);
    if ($roomName === '') json_response(false, 'Room name is required.', 422);
    $preparedVideo = prepare_room_video($id, $_FILES['video'] ?? []);
    $pdo->beginTransaction();
    $calendarStmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ? FOR UPDATE');
    $calendarStmt->execute([$id]);
    $currentCalendar = $calendarStmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentCalendar) throw new OutOfBoundsException('Room not found.');
    // Old clients may resend an unchanged legacy/test feed. Do not revalidate it
    // or write it while saving unrelated room fields.
    if (array_key_exists('booking_ical_url', $data)) {
        if (trim((string) $data['booking_ical_url']) === trim((string) ($currentCalendar['booking_ical_url'] ?? ''))) {
            unset($data['booking_ical_url']);
        } else {
            if (($admin['role'] ?? '') !== 'super_admin') throw new RoomSavePermissionException('Owner access required to change calendar feeds.');
        }
    }
    $imageOrder = room_image_order_input($pdo, $id, $data, $_FILES['images'] ?? []);
    // Validate calendar ownership before updating any room fields or media.
    $calendarUrl = trim((string) ($data['booking_ical_url'] ?? ''));
    if ($calendarUrl !== '') {
        $parts = parse_url($calendarUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!$parts || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || !in_array($host, ['ical.booking.com', 'admin.booking.com'], true)) {
            throw new InvalidArgumentException('Enter a valid Booking.com iCal export URL.');
        }
        if (tulip_ics_duplicate_room($pdo, $calendarUrl, $id) !== null) {
            throw new InvalidArgumentException('This Booking.com calendar URL is already assigned to another room.');
        }
    }
    $roomNumber = room_number_input($pdo, $data, $id);
    $legacyAmenities = parse_amenities_input($data['amenities'] ?? $data['amenities_summary'] ?? '');
    $amenityIds = array_key_exists('amenity_ids', $data)
        ? parse_amenity_ids($data['amenity_ids'])
        : amenity_ids_for_names($pdo, $legacyAmenities);
    $amenities = amenity_names_for_ids($pdo, $amenityIds);
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
        ':status' => in_array($data['status'] ?? 'Available', ['Available', 'Unavailable', 'Maintenance'], true) ? ($data['status'] ?? 'Available') : 'Available',
        ':sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
    ]);
    save_room_occupancy($pdo, $id, $data, $currentCalendar);
    replace_room_amenities($pdo, $id, $amenityIds);
    if (array_key_exists('booking_ical_url', $data)) save_room_ical_url($pdo, $id, (string)$data['booking_ical_url'], true);
    room_remove_images($pdo, $id, room_removed_image_ids($data), $obsoleteFiles);
    $uploadedImageIds = save_room_images($pdo, $id, $_FILES['images'] ?? [], $createdFiles);
    apply_room_image_order($pdo, $id, $imageOrder, $uploadedImageIds);
    if (!empty($data['remove_video'])) {
        $old = $pdo->prepare('SELECT video_path FROM rooms WHERE id = :id');
        $old->execute([':id' => $id]);
        $oldPath = $old->fetchColumn();
        $pdo->prepare('UPDATE rooms SET video_path = NULL WHERE id = :id')->execute([':id' => $id]);
        if (is_string($oldPath) && $oldPath !== '') $obsoleteFiles[] = $oldPath;
    }
    save_room_video($pdo, $id, $_FILES['video'] ?? [], $preparedVideo, $obsoleteFiles);
    $normalized = room_saved_payload($pdo, $id);
    $commitAttempted = true;
    $pdo->commit();
    $preparedVideo = null;
    room_cleanup_obsolete_files($pdo, $obsoleteFiles);
    json_response(true, 'Room updated.', 200, ['data' => $normalized, 'room' => $normalized]);
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
    error_log('Room update failed: ' . $e->getMessage());
    json_response(false, $commitAttempted ? 'We could not confirm the save. Refresh the room list before trying again.' : 'The room could not be saved. No room changes were saved. Please try again.', 500);
}
