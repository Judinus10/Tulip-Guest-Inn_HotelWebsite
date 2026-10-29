<?php
declare(strict_types=1);
require_once __DIR__ . '/../settings/contact_helpers.php';

function ensure_room_occupancy_schema(PDO $pdo): void
{
    if ($pdo->inTransaction()) return; // All DDL belongs before the room-save transaction.
    $columns = array_flip($pdo->query('SHOW COLUMNS FROM rooms')->fetchAll(PDO::FETCH_COLUMN));
    $definitions = [
        'max_adults' => 'INT UNSIGNED NULL DEFAULT NULL',
        'max_children' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'max_total_occupants' => 'INT UNSIGNED NULL DEFAULT NULL',
        'max_child_age' => 'TINYINT UNSIGNED NOT NULL DEFAULT 11',
    ];
    foreach ($definitions as $name => $definition) {
        if (isset($columns[$name])) continue;
        try { $pdo->exec("ALTER TABLE rooms ADD COLUMN $name $definition"); }
        catch (PDOException $e) { if ((int) ($e->errorInfo[1] ?? 0) !== 1060) throw $e; }
    }
    ensure_contact_settings_table($pdo);
}

function room_count_children_setting(PDO $pdo): bool
{
    $stmt = $pdo->prepare('SELECT setting_value FROM website_settings WHERE setting_key = ?');
    $stmt->execute(['count_children_toward_capacity']);
    $value = $stmt->fetchColumn();
    if ($value === false) return true;
    return room_occupancy_boolean($value);
}

function room_occupancy_boolean(mixed $value): bool
{
    if (in_array($value, [true, 1, '1'], true)) return true;
    if (in_array($value, [false, 0, '0'], true)) return false;
    throw new InvalidArgumentException('Choose a valid shared child-capacity setting.');
}

function room_occupancy_integer(mixed $value, int $minimum, int $maximum, string $label): int
{
    if ((!is_int($value) && !(is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/D', $value)))
        || (int) $value < $minimum || (int) $value > $maximum) {
        throw new InvalidArgumentException("$label must be a whole number from $minimum to $maximum.");
    }
    return (int) $value;
}

function room_occupancy_input(array $data, array $existing = []): array
{
    $capacity = room_occupancy_integer($data['max_guests'] ?? $data['capacity'] ?? $existing['max_guests'] ?? 2, 1, 20, 'Guest capacity');
    $limits = [
        'max_adults' => room_occupancy_integer($data['max_adults'] ?? $existing['max_adults'] ?? $capacity, 1, 20, 'Maximum adults'),
        'max_children' => room_occupancy_integer($data['max_children'] ?? $existing['max_children'] ?? 0, 0, 20, 'Maximum children'),
        'max_total_occupants' => room_occupancy_integer($data['max_total_occupants'] ?? $existing['max_total_occupants'] ?? $capacity, 1, 20, 'Maximum total occupants'),
        'max_child_age' => room_occupancy_integer($data['max_child_age'] ?? $existing['max_child_age'] ?? 11, 0, 17, 'Maximum child age'),
    ];
    if ($limits['max_adults'] > $capacity) throw new InvalidArgumentException('Maximum adults cannot exceed guest capacity.');
    if ($limits['max_total_occupants'] < max($limits['max_adults'], $limits['max_children'])) throw new InvalidArgumentException('Total occupancy cannot be lower than an individual adult or child limit.');
    if ($limits['max_total_occupants'] > $limits['max_adults'] + $limits['max_children']) throw new InvalidArgumentException('Total occupancy cannot exceed the combined adult and child limits.');
    return $limits;
}

/** Save the room limits and any deliberate global-toggle change as one unit. */
function save_room_occupancy(PDO $pdo, int $roomId, array $data, array $existing = []): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Occupancy settings must be saved with the room transaction.');
    $limits = room_occupancy_input($data, $existing);
    $pdo->prepare('UPDATE rooms SET max_adults = ?, max_children = ?, max_total_occupants = ?, max_child_age = ? WHERE id = ?')
        ->execute([...array_values($limits), $roomId]);
    if (!array_key_exists('count_children_toward_capacity', $data)) return;
    $next = room_occupancy_boolean($data['count_children_toward_capacity']);
    if (!array_key_exists('expected_count_children_toward_capacity', $data)) throw new InvalidArgumentException('Reopen the room before changing the shared child-capacity setting.');
    $expected = room_occupancy_boolean($data['expected_count_children_toward_capacity']);
    if (room_count_children_setting($pdo) !== $expected) throw new InvalidArgumentException('The shared child-capacity setting changed. Reopen this room and try again.');
    $pdo->prepare('INSERT INTO website_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')
        ->execute(['count_children_toward_capacity', $next ? '1' : '0']);
}
