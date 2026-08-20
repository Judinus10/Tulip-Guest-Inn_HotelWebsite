<?php
declare(strict_types=1);

function ensure_amenity_tables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS property_amenities (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(120) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_property_amenity_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS room_amenities (
        room_id INT UNSIGNED NOT NULL,
        amenity_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (room_id, amenity_id),
        KEY idx_room_amenities_amenity (amenity_id),
        CONSTRAINT fk_room_amenities_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        CONSTRAINT fk_room_amenities_amenity FOREIGN KEY (amenity_id) REFERENCES property_amenities(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columnStmt = $pdo->query("SHOW COLUMNS FROM rooms LIKE 'show_unavailable_amenities'");
    if (!$columnStmt->fetch()) {
        $pdo->exec('ALTER TABLE rooms ADD COLUMN show_unavailable_amenities TINYINT(1) NOT NULL DEFAULT 0 AFTER amenities');
    }
}

function list_amenities(PDO $pdo): array
{
    ensure_amenity_tables($pdo);
    $rows = $pdo->query(
        'SELECT a.id, a.name AS amenity_name, COUNT(ra.room_id) AS assigned_room_count
         FROM property_amenities a
         LEFT JOIN room_amenities ra ON ra.amenity_id = a.id
         GROUP BY a.id, a.name
         ORDER BY a.name ASC'
    )->fetchAll();

    return array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'amenity_name' => (string) $row['amenity_name'],
        'assigned_room_count' => (int) $row['assigned_room_count'],
    ], $rows);
}

function parse_amenity_ids(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : explode(',', $value);
    }
    if (!is_array($value)) return [];

    return array_values(array_unique(array_filter(
        array_map('intval', $value),
        static fn(int $id): bool => $id > 0
    )));
}

function amenity_ids_for_names(PDO $pdo, array $names): array
{
    $names = array_values(array_unique(array_filter(array_map(
        static fn(mixed $name): string => trim((string) $name),
        $names
    ))));
    if ($names === []) return [];

    $find = $pdo->prepare('SELECT id FROM property_amenities WHERE LOWER(name) = LOWER(:name) LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO property_amenities (name) VALUES (:name)');
    $ids = [];

    foreach ($names as $name) {
        $find->execute([':name' => $name]);
        $id = $find->fetchColumn();
        if (!$id) {
            $insert->execute([':name' => $name]);
            $id = $pdo->lastInsertId();
        }
        $ids[] = (int) $id;
    }

    return array_values(array_unique($ids));
}

function replace_room_amenities(PDO $pdo, int $roomId, array $amenityIds): array
{
    $validIds = [];
    if ($amenityIds !== []) {
        $placeholders = implode(',', array_fill(0, count($amenityIds), '?'));
        $stmt = $pdo->prepare("SELECT id FROM property_amenities WHERE id IN ($placeholders)");
        $stmt->execute($amenityIds);
        $validIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $delete = $pdo->prepare('DELETE FROM room_amenities WHERE room_id = :room_id');
    $delete->execute([':room_id' => $roomId]);
    $insert = $pdo->prepare('INSERT INTO room_amenities (room_id, amenity_id) VALUES (:room_id, :amenity_id)');
    foreach ($validIds as $amenityId) {
        $insert->execute([':room_id' => $roomId, ':amenity_id' => $amenityId]);
    }
    return $validIds;
}

function amenity_names_for_ids(PDO $pdo, array $amenityIds): array
{
    if ($amenityIds === []) return [];
    $placeholders = implode(',', array_fill(0, count($amenityIds), '?'));
    $stmt = $pdo->prepare("SELECT name FROM property_amenities WHERE id IN ($placeholders) ORDER BY name ASC");
    $stmt->execute($amenityIds);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function sync_room_amenities_json(PDO $pdo, array $roomIds): void
{
    $roomIds = array_values(array_unique(array_filter(array_map('intval', $roomIds))));
    if ($roomIds === []) return;

    $names = $pdo->prepare(
        'SELECT a.name FROM room_amenities ra
         JOIN property_amenities a ON a.id = ra.amenity_id
         WHERE ra.room_id = :room_id ORDER BY a.name ASC'
    );
    $update = $pdo->prepare('UPDATE rooms SET amenities = :amenities WHERE id = :room_id');
    foreach ($roomIds as $roomId) {
        $names->execute([':room_id' => $roomId]);
        $update->execute([
            ':room_id' => $roomId,
            ':amenities' => json_encode(array_map('strval', $names->fetchAll(PDO::FETCH_COLUMN)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
