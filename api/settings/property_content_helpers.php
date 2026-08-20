<?php
declare(strict_types=1);

function ensure_nearby_places_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS nearby_places (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(120) NOT NULL,
        distance DECIMAL(8,2) NOT NULL,
        distance_unit ENUM('m', 'km') NOT NULL DEFAULT 'km',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function get_property_content(PDO $pdo): array
{
    ensure_nearby_places_table($pdo);
    $places = array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'distance' => (float) $row['distance'],
        'distance_unit' => (string) $row['distance_unit'],
    ], $pdo->query('SELECT id, name, distance, distance_unit FROM nearby_places ORDER BY id ASC')->fetchAll());

    return ['nearby_places' => $places];
}

function save_property_content(PDO $pdo, array $payload): array
{
    ensure_nearby_places_table($pdo);
    $input = $payload['nearby_places'] ?? [];
    if (!is_array($input)) json_response(false, 'Nearby places must be a valid list.', 422);
    if (count($input) > 100) json_response(false, 'A maximum of 100 nearby places is allowed.', 422);

    $places = [];
    foreach ($input as $item) {
        if (!is_array($item)) continue;
        $name = clean_string($item['name'] ?? '', 120);
        $distance = filter_var($item['distance'] ?? null, FILTER_VALIDATE_FLOAT);
        $unit = ($item['distance_unit'] ?? 'km') === 'm' ? 'm' : 'km';
        if ($name === '' || $distance === false || $distance < 0) {
            json_response(false, 'Each nearby place needs a name and a valid non-negative distance.', 422);
        }
        $places[] = ['name' => $name, 'distance' => round((float) $distance, 2), 'distance_unit' => $unit];
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM nearby_places');
        $insert = $pdo->prepare('INSERT INTO nearby_places (name, distance, distance_unit) VALUES (:name, :distance, :distance_unit)');
        foreach ($places as $place) $insert->execute($place);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return get_property_content($pdo);
}
