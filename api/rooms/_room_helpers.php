<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../amenities/_amenity_helpers.php';

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
}

function ensure_room_ical_column(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM rooms LIKE 'booking_ical_url'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE rooms ADD COLUMN booking_ical_url VARCHAR(1024) NULL");
    }
}

function save_room_ical_url(PDO $pdo, int $roomId, string $url): void
{
    $url = trim($url);
    if ($url !== '') {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || !($host === 'ical.booking.com' || $host === 'admin.booking.com')) {
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

function save_room_video(PDO $pdo, int $roomId, array $file): void
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return;
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK) throw new RuntimeException('Video upload failed. Check PHP upload limits.');
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 100 * 1024 * 1024) throw new RuntimeException('Video must be 100 MB or smaller.');
    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) throw new RuntimeException('Invalid video upload.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extensions = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Only MP4 and WebM videos are supported.');
    $directory = __DIR__ . '/../uploads/rooms';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('Unable to create video directory.');
    $destination = optimize_uploaded_video($file, $directory . '/room-video-' . $roomId . '-' . bin2hex(random_bytes(12)));
    $filename = basename($destination);
    $stmt = $pdo->prepare('SELECT video_path FROM rooms WHERE id = :id');
    $stmt->execute([':id' => $roomId]);
    $old = $stmt->fetchColumn();
    try {
        $pdo->prepare('UPDATE rooms SET video_path = :path WHERE id = :id')->execute([':path' => 'uploads/rooms/' . $filename, ':id' => $roomId]);
    } catch (Throwable $e) { @unlink($destination); throw $e; }
    delete_room_video_file($old);
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

function normalize_room(array $room, array $images = [], array $amenityRecords = [], array $amenityCatalog = []): array
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
    $guests = (int) ($room['max_guests'] ?? 2);
    $bedType = trim((string) ($room['bed_type'] ?? '')) !== '' ? (string) $room['bed_type'] : 'Double Bed';
    $roomSize = (float) ($room['room_size'] ?? 24);
    if ($roomSize <= 0) $roomSize = $guests > 3 ? 45 : 24;

    return [
        'id' => (int) $room['id'],
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
        'booking_ical_url' => (string) ($room['booking_ical_url'] ?? ''),
        'video_url' => !empty($room['video_path']) ? rooms_upload_url($room['video_path']) : null,
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
    return array_map(
        static fn($room): array => normalize_room(
            $room,
            $images[(int) $room['id']] ?? [],
            $amenities[(int) $room['id']] ?? [],
            $catalog
        ),
        $rooms
    );
}

function save_room_images(PDO $pdo, int $roomId, array $files): void
{
    ensure_room_images_table($pdo);
    if (!isset($files['name']) || $files['name'] === []) return;

    $uploadDir = __DIR__ . '/../uploads/rooms';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('Room upload folder could not be created.');
    }

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];

    $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM room_images WHERE room_id = :room_id');
    $sortStmt->execute([':room_id' => $roomId]);
    $sortOrder = (int) $sortStmt->fetchColumn();

    $hasMainStmt = $pdo->prepare('SELECT COUNT(*) FROM room_images WHERE room_id = :room_id AND is_main = 1');
    $hasMainStmt->execute([':room_id' => $roomId]);
    $hasMain = (int) $hasMainStmt->fetchColumn() > 0;

    foreach ($names as $index => $originalName) {
        if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $tmpName = $tmpNames[$index] ?? '';
        if (!is_uploaded_file($tmpName)) continue;

        $result = secure_image_upload([
            'error' => $errors[$index] ?? UPLOAD_ERR_NO_FILE,
            'tmp_name' => $tmpName,
            'size' => is_array($files['size'] ?? null) ? ($files['size'][$index] ?? 0) : ($files['size'] ?? 0),
        ], $uploadDir, 'room_' . $roomId);
        $filename = $result['filename'];

        $relativePath = 'uploads/rooms/' . $filename;
        $sortOrder++;
        $insert = $pdo->prepare('INSERT INTO room_images (room_id, image_path, is_main, sort_order) VALUES (:room_id, :image_path, :is_main, :sort_order)');
        $insert->execute([
            ':room_id' => $roomId,
            ':image_path' => $relativePath,
            ':is_main' => $hasMain ? 0 : 1,
            ':sort_order' => $sortOrder,
        ]);
        $hasMain = true;
    }
}

function room_input_data(): array
{
    if (str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data')) return $_POST;
    return read_request_data();
}

function parse_amenities_input(mixed $value): array
{
    if (is_array($value)) return array_values(array_filter(array_map('trim', array_map('strval', $value))));
    $decoded = json_decode((string) $value, true);
    if (is_array($decoded)) return array_values(array_filter(array_map('trim', array_map('strval', $decoded))));
    return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
}
