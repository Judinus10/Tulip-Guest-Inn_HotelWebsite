<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../amenities/_amenity_helpers.php';
require_once __DIR__ . '/occupancy-helper.php';

function rooms_upload_url(string $relativePath): string
{
    return public_upload_url($relativePath, 'rooms');
}


function ensure_rooms_schema(PDO $pdo): void
{
    $columns = [];
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM rooms');
        foreach ($stmt->fetchAll() as $column) {
            $columns[strtolower((string) $column['Field'])] = true;
        }
    } catch (Throwable $e) {
        return;
    }

    if (!isset($columns['room_category'])) {
        $pdo->exec("ALTER TABLE rooms ADD COLUMN room_category VARCHAR(50) NOT NULL DEFAULT 'standard' AFTER slug");
    }

    if (!isset($columns['room_size'])) {
        $pdo->exec("ALTER TABLE rooms ADD COLUMN room_size DECIMAL(8,2) NOT NULL DEFAULT 24 AFTER bed_type");
    }

    if (!isset($columns['room_number'])) {
        try {
            $pdo->exec("ALTER TABLE rooms ADD COLUMN room_number VARCHAR(30) NULL DEFAULT NULL UNIQUE");
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1060) throw $e;
        }
    }
    $pdo->exec("UPDATE rooms SET room_number = CONCAT('R', LPAD(id, GREATEST(2, CHAR_LENGTH(id)), '0')) WHERE room_number IS NULL");
    ensure_room_occupancy_schema($pdo);
}

function room_number_input(PDO $pdo, array $data, int $roomId = 0): string
{
    if (!array_key_exists('room_number', $data) && $roomId > 0) {
        $stmt = $pdo->prepare('SELECT room_number FROM rooms WHERE id = ?');
        $stmt->execute([$roomId]);
        return (string) $stmt->fetchColumn();
    }
    $number = trim((string) ($data['room_number'] ?? ''));
    if ($number === '') {
        throw new InvalidArgumentException('Enter a room number and try again.');
    }
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._\/-]{0,29}$/D', $number)) {
        throw new InvalidArgumentException('Enter a room number of up to 30 letters, numbers, spaces, dots, hyphens, underscores or slashes.');
    }
    $stmt = $pdo->prepare('SELECT id FROM rooms WHERE room_number = ? AND id <> ? LIMIT 1');
    $stmt->execute([$number, $roomId]);
    if ($stmt->fetchColumn()) throw new InvalidArgumentException('This room number is already assigned to another room.');
    return $number;
}

function ensure_room_ical_column(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM rooms LIKE 'booking_ical_url'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE rooms ADD COLUMN booking_ical_url VARCHAR(1024) NULL");
    }
}

function save_room_ical_url(PDO $pdo, int $roomId, string $url, bool $permissionChecked = false): void
{
    $current = $pdo->prepare('SELECT booking_ical_url FROM rooms WHERE id = ?');
    $current->execute([$roomId]);
    $row = $current->fetch(PDO::FETCH_ASSOC);
    if ($row && trim((string) ($row['booking_ical_url'] ?? '')) === trim($url)) return;
    if (!$permissionChecked) require_calendar_permission($pdo, $roomId, $url);
    $url = trim($url);
    if ($url !== '') {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!$parts || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443) || ($parts['scheme'] ?? '') !== 'https' || !($host === 'ical.booking.com' || $host === 'admin.booking.com')) {
            throw new InvalidArgumentException('Enter a valid Booking.com iCal export URL.');
        }
        $check = $pdo->prepare('SELECT id FROM rooms WHERE booking_ical_url = :url AND id <> :id LIMIT 1');
        $check->execute([':url' => $url, ':id' => $roomId]);
        if ($check->fetchColumn()) throw new InvalidArgumentException('This iCal URL is already assigned to another room.');
    }
    $stmt = $pdo->prepare('UPDATE rooms SET booking_ical_url = :url WHERE id = :id');
    $stmt->execute([':url' => $url !== '' ? $url : null, ':id' => $roomId]);
}

function normalize_room_category(mixed $value): string
{
    $category = strtolower(trim((string) $value));
    $category = preg_replace('/[^a-z0-9]+/', '-', $category) ?? '';
    $category = trim($category, '-');

    return in_array($category, ['standard', 'deluxe', 'family', 'suite'], true) ? $category : 'standard';
}

function ensure_room_images_table(PDO $pdo): void
{
    // Room saves initialize schema before their transaction. DDL here would commit it.
    if ($pdo->inTransaction()) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS room_images (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        room_id INT UNSIGNED NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        is_main TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_room_images_room (room_id),
        CONSTRAINT fk_room_images_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ensure_room_video_column(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM rooms LIKE 'video_path'");
    if (!$stmt->fetch()) $pdo->exec("ALTER TABLE rooms ADD COLUMN video_path VARCHAR(255) NULL DEFAULT NULL");
}

require_once __DIR__ . '/../utils/video_processing.php';

function prepare_room_video(int $roomId, array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK) throw new RoomVideoProcessingException('Video upload failed. Check PHP upload limits.');
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 100 * 1024 * 1024) throw new RoomVideoProcessingException('Video must be 100 MB or smaller.');
    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) throw new RoomVideoProcessingException('Invalid video upload.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extensions = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    if (!isset($extensions[$mime])) throw new RoomVideoProcessingException('Only MP4 and WebM videos are supported.');
    $directory = __DIR__ . '/../uploads/rooms';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RoomVideoProcessingException('Unable to create video directory.');
    return optimize_uploaded_video($file, $directory . '/room-video-' . $roomId . '-' . bin2hex(random_bytes(12)));
}

function save_room_video(PDO $pdo, int $roomId, array $file, ?string $preparedVideo = null, ?array &$obsoleteFiles = null): void
{
    if ($pdo->inTransaction() && $obsoleteFiles === null) throw new LogicException('Transactional video saves require deferred file cleanup.');
    $destination = $preparedVideo ?? prepare_room_video($roomId, $file);
    if ($destination === null) return;
    $filename = basename($destination);
    $stmt = $pdo->prepare('SELECT video_path FROM rooms WHERE id = :id');
    $stmt->execute([':id' => $roomId]);
    $old = $stmt->fetchColumn();
    try {
        $pdo->prepare('UPDATE rooms SET video_path = :path WHERE id = :id')->execute([':path' => 'uploads/rooms/' . $filename, ':id' => $roomId]);
    } catch (Throwable $e) { @unlink($destination); throw $e; }
    if ($obsoleteFiles !== null) {
        if (is_string($old) && $old !== '') $obsoleteFiles[] = $old;
    } else {
        delete_room_video_file($old);
    }
}

function delete_room_video_file(mixed $path): void
{
    if (!is_string($path) || !preg_match('~^uploads/rooms/room-video-[a-zA-Z0-9-]+\.(mp4|webm)$~', $path)) return;
    $file = __DIR__ . '/../' . $path;
    if (is_file($file)) @unlink($file);
}

function slugify_room(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'room-' . time();
}

function infer_room_type(array $room): string
{
    $value = strtolower(($room['room_name'] ?? '') . ' ' . ($room['slug'] ?? ''));
    if (str_contains($value, 'private') || str_contains($value, 'cottage')) return 'Private Cottage';
    if (str_contains($value, 'family')) return 'Family Room';
    if (str_contains($value, 'first')) return 'First Floor';
    if (str_contains($value, 'ground')) return 'Ground Floor';
    return 'Rooms';
}

function decode_amenities(mixed $value): array
{
    if (is_array($value)) return array_values(array_filter($value));
    $decoded = json_decode((string) $value, true);
    if (is_array($decoded)) return array_values(array_filter(array_map('strval', $decoded)));
    if (trim((string) $value) === '') return [];
    return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
}

function fetch_room_images(PDO $pdo, array $roomIds): array
{
    ensure_room_images_table($pdo);
    if ($roomIds === []) return [];
    $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM room_images WHERE room_id IN ($placeholders) ORDER BY is_main DESC, sort_order ASC, id ASC");
    $stmt->execute($roomIds);
    $grouped = [];
    foreach ($stmt->fetchAll() as $image) {
        $image['image_url'] = rooms_upload_url($image['image_path']);
        $grouped[(int) $image['room_id']][] = $image;
    }
    return $grouped;
}

function fetch_room_amenity_assignments(PDO $pdo, array $roomIds): array
{
    ensure_amenity_tables($pdo);
    if ($roomIds === []) return [];
    $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT ra.room_id, a.id, a.name AS amenity_name
         FROM room_amenities ra
         JOIN property_amenities a ON a.id = ra.amenity_id
         WHERE ra.room_id IN ($placeholders)
         ORDER BY a.name ASC"
    );
    $stmt->execute($roomIds);
    $grouped = [];
    foreach ($stmt->fetchAll() as $amenity) {
        $grouped[(int) $amenity['room_id']][] = [
            'id' => (int) $amenity['id'],
            'amenity_name' => (string) $amenity['amenity_name'],
        ];
    }
    return $grouped;
}

function normalize_room(array $room, array $images = [], array $amenityRecords = [], array $amenityCatalog = [], bool $includePrivate = false): array
{
    $legacyAmenities = decode_amenities($room['amenities'] ?? '[]');
    $amenities = $amenityCatalog !== []
        ? array_map(static fn(array $amenity): string => (string) $amenity['amenity_name'], $amenityRecords)
        : $legacyAmenities;
    $amenityIds = array_map(static fn(array $amenity): int => (int) $amenity['id'], $amenityRecords);
    $selectedLookup = array_fill_keys($amenityIds, true);
    $catalog = array_map(static fn(array $amenity): array => [
        'id' => (int) $amenity['id'],
        'amenity_name' => (string) $amenity['amenity_name'],
        'selected' => isset($selectedLookup[(int) $amenity['id']]),
    ], $amenityCatalog);
    $imageUrls = array_map(static fn(array $image): string => $image['image_url'] ?? rooms_upload_url($image['image_path']), $images);
    $fallbackImage = 'https://images.unsplash.com/photo-1611892440506-42a832e657fb?w=1200&q=80';
    if ($imageUrls === []) $imageUrls = [$fallbackImage];

    $type = infer_room_type($room);
    $category = normalize_room_category($room['room_category'] ?? '');
    $price = (float) ($room['base_price'] ?? 0);
    $currency = (string) ($room['currency'] ?? 'LKR');
    $guests = (int) ($room['max_total_occupants'] ?? $room['max_guests'] ?? 2);
    $bedType = trim((string) ($room['bed_type'] ?? '')) !== '' ? (string) $room['bed_type'] : 'Double Bed';
    $roomSize = (float) ($room['room_size'] ?? 24);
    if ($roomSize <= 0) $roomSize = $guests > 3 ? 45 : 24;

    return [
        'id' => (int) $room['id'],
        'room_number' => (string) ($room['room_number'] ?? ('R' . str_pad((string) $room['id'], 2, '0', STR_PAD_LEFT))),
        'slug' => (string) ($room['slug'] ?? ''),
        'name' => (string) ($room['room_name'] ?? ''),
        'room_name' => (string) ($room['room_name'] ?? ''),
        'type' => $type,
        'room_type' => $type,
        'category' => $category,
        'room_category' => $category,
        'description' => (string) ($room['description'] ?? ''),
        'longDescription' => (string) ($room['description'] ?? ''),
        'guests' => $guests,
        'max_guests' => $guests,
        'capacity' => $guests,
        'max_adults' => (int) ($room['max_adults'] ?? $room['max_guests'] ?? $guests),
        'max_children' => (int) ($room['max_children'] ?? 0),
        'max_total_occupants' => (int) ($room['max_total_occupants'] ?? $guests),
        'max_child_age' => (int) ($room['max_child_age'] ?? 11),
        'count_children_toward_capacity' => (bool) ($room['count_children_toward_capacity'] ?? true),
        'beds' => $bedType,
        'bed_type' => $bedType,
        'size' => $roomSize,
        'room_size' => $roomSize,
        'price' => $price,
        'base_price' => $price,
        'price_per_night' => $price,
        'currency' => $currency,
        'amenities' => $amenities,
        'amenity_ids' => $amenityIds,
        'amenity_records' => $amenityRecords,
        'amenities_catalog' => $catalog,
        'amenities_summary' => implode(', ', $amenities),
        'show_unavailable_amenities' => !empty($room['show_unavailable_amenities']),
        'status' => (string) ($room['status'] ?? 'Available'),
        'sort_order' => (int) ($room['sort_order'] ?? 0),
        ...($includePrivate ? ['booking_ical_url' => (string) ($room['booking_ical_url'] ?? '')] : []),
        'video_url' => !empty($room['video_path'])
            ? public_api_base_url() . '/rooms/video.php?file=' . rawurlencode(basename((string) $room['video_path']))
            : null,
        'images' => $imageUrls,
        'main_image' => $imageUrls[0] ?? $fallbackImage,
        'image' => $images[0] ?? null,
        'image_records' => $images,
        'created_at' => $room['created_at'] ?? null,
        'updated_at' => $room['updated_at'] ?? null,
    ];
}

function get_room_payload(PDO $pdo, bool $publicOnly = false): array
{
    ensure_rooms_schema($pdo);
    ensure_room_video_column($pdo);
    ensure_room_ical_column($pdo);
    ensure_room_images_table($pdo);
    ensure_amenity_tables($pdo);
    $sql = 'SELECT * FROM rooms';
    if ($publicOnly) $sql .= " WHERE status = 'Available'";
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    $rooms = $pdo->query($sql)->fetchAll();
    $roomIds = array_map(static fn($room) => (int) $room['id'], $rooms);
    $images = fetch_room_images($pdo, $roomIds);
    $amenities = fetch_room_amenity_assignments($pdo, $roomIds);
    $catalog = list_amenities($pdo);
    $countChildren = room_count_children_setting($pdo);
    return array_map(
        static fn($room): array => normalize_room(
            $room + ['count_children_toward_capacity' => $countChildren],
            $images[(int) $room['id']] ?? [],
            $amenities[(int) $room['id']] ?? [],
            $catalog, !$publicOnly
        ),
        $rooms
    );
}

function save_room_images(PDO $pdo, int $roomId, array $files, ?array &$createdFiles = null): array
{
    ensure_room_images_table($pdo);
    if (!isset($files['name']) || $files['name'] === []) return [];

    $uploadDir = __DIR__ . '/../uploads/rooms';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('Room upload folder could not be created.');
    }

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    if (count($names) > 20) throw new RuntimeException('Upload no more than 20 images at once.');
    $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];

    $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM room_images WHERE room_id = :room_id');
    $sortStmt->execute([':room_id' => $roomId]);
    $sortOrder = (int) $sortStmt->fetchColumn();

    $hasMainStmt = $pdo->prepare('SELECT COUNT(*) FROM room_images WHERE room_id = :room_id AND is_main = 1');
    $hasMainStmt->execute([':room_id' => $roomId]);
    $hasMain = (int) $hasMainStmt->fetchColumn() > 0;

    $savedIds = [];
    foreach ($names as $index => $originalName) {
        if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('An image upload failed. Check the upload size and try again.');
        $tmpName = $tmpNames[$index] ?? '';
        if (!is_uploaded_file($tmpName)) throw new InvalidArgumentException('Invalid image upload. Please select the photos again.');

        $result = secure_image_upload([
            'error' => $errors[$index] ?? UPLOAD_ERR_NO_FILE,
            'tmp_name' => $tmpName,
            'size' => is_array($files['size'] ?? null) ? ($files['size'][$index] ?? 0) : ($files['size'] ?? 0),
        ], $uploadDir, 'room_' . $roomId);
        $filename = $result['filename'];
        if ($createdFiles !== null) $createdFiles[] = $uploadDir . '/' . $filename;

        $relativePath = 'uploads/rooms/' . $filename;
        $sortOrder++;
        $insert = $pdo->prepare('INSERT INTO room_images (room_id, image_path, is_main, sort_order) VALUES (:room_id, :image_path, :is_main, :sort_order)');
        try { $insert->execute([
            ':room_id' => $roomId,
            ':image_path' => $relativePath,
            ':is_main' => $hasMain ? 0 : 1,
            ':sort_order' => $sortOrder,
        ]);
        } catch (Throwable $e) { @unlink($uploadDir . '/' . $filename); throw $e; }
        $savedIds[$index] = (int) $pdo->lastInsertId();
        $hasMain = true;
    }
    return $savedIds;
}

function room_input_data(): array
{
    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if (str_starts_with($contentType, 'multipart/form-data')) {
        // PHP empties both $_POST and $_FILES when the complete multipart body
        // exceeds post_max_size. Check this before reporting missing fields.
        $postMax = trim((string) ini_get('post_max_size'));
        $unit = strtolower(substr($postMax, -1));
        $multiplier = ['k' => 1024, 'm' => 1048576, 'g' => 1073741824][$unit] ?? 1;
        $postMaxBytes = (float) $postMax * $multiplier;
        if ($postMaxBytes > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $postMaxBytes) {
            error_log('Room upload exceeds PHP post_max_size (' . $postMax . '), request bytes=' . (int) ($_SERVER['CONTENT_LENGTH'] ?? 0));
            json_response(false, 'The selected photos and video are too large to upload together. Choose smaller files or contact support.', 413);
        }
        foreach ($_FILES as $upload) {
            $errors = (array) ($upload['error'] ?? []);
            array_walk_recursive($errors, static function ($error): void {
                if (in_array((int) $error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                    error_log('Room upload exceeds PHP upload_max_filesize (' . ini_get('upload_max_filesize') . ')');
                    json_response(false, 'One of the selected files is too large to upload. Choose a smaller file or contact support.', 413);
                }
            });
        }
        if ($_POST === []) {
            json_response(false, 'The room form could not be read. Refresh the page and submit it again with smaller files.', 422);
        }
        return $_POST;
    }
    return read_request_data();
}

function parse_amenities_input(mixed $value): array
{
    if (is_array($value)) return array_values(array_filter(array_map('trim', array_map('strval', $value))));
    $decoded = json_decode((string) $value, true);
    if (is_array($decoded)) return array_values(array_filter(array_map('trim', array_map('strval', $decoded))));
    return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
}

// Validate the full room-photo manifest before changing room details.
function room_image_order_input(PDO $pdo, int $roomId, array $data, array $files): ?array
{
    if (!array_key_exists('image_order', $data)) return null;
    $order = is_string($data['image_order']) ? json_decode($data['image_order'], true) : $data['image_order'];
    if (!is_array($order) || !array_is_list($order)) throw new InvalidArgumentException('Invalid photo order.');
    $existing = []; $uploads = [];
    foreach ($order as $entry) {
        if (!is_array($entry) || count($entry) !== 1) throw new InvalidArgumentException('Invalid photo order entry.');
        if (isset($entry['id']) && is_int($entry['id']) && $entry['id'] > 0) $existing[] = $entry['id'];
        elseif (isset($entry['upload_index']) && is_int($entry['upload_index']) && $entry['upload_index'] >= 0) $uploads[] = $entry['upload_index'];
        else throw new InvalidArgumentException('Invalid photo identifier.');
    }
    $stmt = $pdo->prepare('SELECT id FROM room_images WHERE room_id = ?');
    $stmt->execute([$roomId]);
    $current = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $removed = room_removed_image_ids($data);
    if (array_diff($removed, $current)) throw new InvalidArgumentException('A removed photo no longer belongs to this room. Reopen the room.');
    $current = array_values(array_diff($current, $removed));
    sort($existing, SORT_NUMERIC); sort($current, SORT_NUMERIC); sort($uploads, SORT_NUMERIC);
    $count = isset($files['name']) ? (is_array($files['name']) ? count($files['name']) : 1) : 0;
    $expectedUploads = $count ? range(0, $count - 1) : [];
    if ($existing !== $current || $uploads !== $expectedUploads) throw new InvalidArgumentException('Room photos changed or the order is incomplete. Reopen the room and try again.');
    return $order;
}

function apply_room_image_order(PDO $pdo, int $roomId, ?array $order, array $uploadedIds): void
{
    if ($order === null) return;
    $ids = [];
    foreach ($order as $entry) {
        $id = isset($entry['id']) ? $entry['id'] : ($uploadedIds[$entry['upload_index']] ?? 0);
        if ($id < 1) throw new InvalidArgumentException('A photo upload did not complete. Reopen the room before saving its order.');
        $ids[] = $id;
    }
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id FROM room_images WHERE room_id = ? FOR UPDATE');
        $stmt->execute([$roomId]);
        $current = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $sorted = $ids; sort($sorted, SORT_NUMERIC); sort($current, SORT_NUMERIC);
        if ($sorted !== $current) throw new InvalidArgumentException('Room photos changed while saving. Reopen the room and try again.');
        $update = $pdo->prepare('UPDATE room_images SET sort_order = ?, is_main = ? WHERE id = ? AND room_id = ?');
        foreach ($ids as $index => $id) $update->execute([$index + 1, $index === 0 ? 1 : 0, $id, $roomId]);
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $e) { if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}


class RoomSavePermissionException extends RuntimeException {}

function room_removed_image_ids(array $data): array
{
    $ids = $data['remove_image_ids'] ?? [];
    if (is_string($ids)) $ids = json_decode($ids, true);
    if (!is_array($ids) || !array_is_list($ids)) throw new InvalidArgumentException('Invalid removed photo list.');
    foreach ($ids as $id) {
        if (!is_int($id) || $id < 1) throw new InvalidArgumentException('Invalid removed photo identifier.');
    }
    if (count(array_unique($ids)) !== count($ids)) throw new InvalidArgumentException('Duplicate removed photo identifier.');
    if ($ids && !array_key_exists('image_order', $data)) throw new InvalidArgumentException('Provide the complete photo order when removing photos.');
    return $ids;
}

/** Delete database records now; keep their files until the room transaction commits. */
function room_remove_images(PDO $pdo, int $roomId, array $ids, array &$obsoleteFiles): void
{
    if (!$ids) return;
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, image_path FROM room_images WHERE room_id = ? AND id IN ($marks) FOR UPDATE");
    $stmt->execute(array_merge([$roomId], $ids));
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($images) !== count($ids)) throw new InvalidArgumentException('Room photos changed. Reopen the room and try again.');
    foreach ($images as $image) $obsoleteFiles[] = (string) $image['image_path'];
    $pdo->prepare("DELETE FROM room_images WHERE room_id = ? AND id IN ($marks)")->execute(array_merge([$roomId], $ids));
}

function room_delete_upload_file(string $absolutePath): void
{
    $root = realpath(__DIR__ . '/../uploads/rooms');
    $file = realpath($absolutePath);
    if ($root !== false && $file !== false && dirname($file) === $root && is_file($file)) {
        if (!@unlink($file)) error_log('Could not clean up unused room upload: ' . basename($file));
    }
}

/** A failed connection during COMMIT has an unknown outcome: retain files in that case. */
function room_rollback_save(?PDO $pdo, array $createdFiles, ?string $preparedVideo, bool $commitAttempted): void
{
    $rolledBack = false;
    try {
        if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); $rolledBack = true; }
    } catch (Throwable $e) { error_log('Room rollback could not be confirmed: ' . $e->getMessage()); }
    if (!$commitAttempted || $rolledBack) {
        foreach ($createdFiles as $path) room_delete_upload_file($path);
        if ($preparedVideo !== null) room_delete_upload_file($preparedVideo);
    }
}

/** Best-effort garbage collection must never turn a committed save into an error response. */
function room_cleanup_obsolete_files(PDO $pdo, array $paths): void
{
    foreach (array_unique($paths) as $path) {
        if (!is_string($path) || !str_starts_with($path, 'uploads/rooms/') || basename($path) !== substr($path, strlen('uploads/rooms/'))) continue;
        try {
            $images = $pdo->prepare('SELECT COUNT(*) FROM room_images WHERE image_path = ?');
            $images->execute([$path]);
            $videos = $pdo->prepare('SELECT COUNT(*) FROM rooms WHERE video_path = ?');
            $videos->execute([$path]);
            if (!(int) $images->fetchColumn() && !(int) $videos->fetchColumn()) room_delete_upload_file(__DIR__ . '/../' . $path);
        } catch (Throwable $e) { error_log('Deferred room file cleanup failed: ' . $e->getMessage()); }
    }
}

function room_saved_payload(PDO $pdo, int $id): array
{
    $images = fetch_room_images($pdo, [$id]);
    $stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $room = $stmt->fetch();
    if (!$room) throw new RuntimeException('Room was not found while saving.');
    $assignments = fetch_room_amenity_assignments($pdo, [$id]);
    $room['count_children_toward_capacity'] = room_count_children_setting($pdo);
    return normalize_room($room, $images[$id] ?? [], $assignments[$id] ?? [], list_amenities($pdo), true);
}
