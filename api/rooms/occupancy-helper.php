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
    // Migrate the former per-room age limit once, before any room-save transaction.
    $query = $pdo->prepare('SELECT setting_value FROM website_settings WHERE setting_key = ?');
    $query->execute(['max_child_age']);
    if ($query->fetchColumn() === false) {
        $pdo->beginTransaction();
        try {
            // Preserve the most common existing rule; ties use the lower age.
            $age = $pdo->query('SELECT max_child_age FROM rooms WHERE max_child_age BETWEEN 0 AND 17 GROUP BY max_child_age ORDER BY COUNT(*) DESC, max_child_age ASC LIMIT 1')->fetchColumn();
            $age = $age === false ? 11 : (int) $age;
            $insert = $pdo->prepare('INSERT IGNORE INTO website_settings (setting_key, setting_value) VALUES (?, ?)');
            $insert->execute(['max_child_age', (string) $age]);
            if ($insert->rowCount() === 1) {
                $pdo->prepare('UPDATE rooms SET max_child_age = ? WHERE max_child_age <> ?')->execute([$age, $age]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

function room_max_child_age_setting(PDO $pdo): int
{
    $stmt = $pdo->prepare('SELECT setting_value FROM website_settings WHERE setting_key = ?');
    $stmt->execute(['max_child_age']);
    $value = $stmt->fetchColumn();
    if ($value === false) throw new RuntimeException('Reload the room list to initialize the shared child age setting.');
    return room_occupancy_integer($value, 0, 17, 'Shared maximum child age');
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
    // One hard total; max_guests/capacity are legacy aliases, not a second limit.
    $total = room_occupancy_integer($data['max_total_occupants'] ?? $data['max_guests'] ?? $data['capacity'] ?? $existing['max_total_occupants'] ?? $existing['max_guests'] ?? 2, 1, 20, 'Maximum total guests');
    $limits = [
        'max_adults' => room_occupancy_integer($data['max_adults'] ?? $existing['max_adults'] ?? $total, 1, 20, 'Maximum adults'),
        'max_children' => room_occupancy_integer($data['max_children'] ?? $existing['max_children'] ?? 0, 0, 20, 'Extra child places'),
        'max_total_occupants' => $total,
        'max_child_age' => room_occupancy_integer($data['max_child_age'] ?? $existing['max_child_age'] ?? 11, 0, 17, 'Maximum child age'),
    ];
    if ($limits['max_adults'] > $total) throw new InvalidArgumentException('Maximum adults cannot exceed maximum total guests.');
    if ($limits['max_children'] > $total) throw new InvalidArgumentException('Extra child places cannot exceed maximum total guests.');
    return $limits;
}

/** Save room limits and deliberate shared-policy changes in the same transaction. */
function save_room_occupancy(PDO $pdo, int $roomId, array $data, array $existing = []): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Occupancy settings must be saved with the room transaction.');
    // Serialize shared-policy writes and compare against the value seen when the form opened.
    $lock = $pdo->query("SELECT setting_key, setting_value FROM website_settings WHERE setting_key IN ('max_child_age','count_children_toward_capacity') ORDER BY setting_key FOR UPDATE");
    $shared = $lock->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!array_key_exists('max_child_age', $shared)) throw new InvalidArgumentException('Reopen the room to load the shared child age setting.');
    $age = room_occupancy_integer($shared['max_child_age'], 0, 17, 'Shared maximum child age');
    $nextAge = array_key_exists('max_child_age', $data)
        ? room_occupancy_integer($data['max_child_age'], 0, 17, 'Maximum child age') : $age;
    if (array_key_exists('expected_max_child_age', $data)) {
        $expectedAge = room_occupancy_integer($data['expected_max_child_age'], 0, 17, 'Previous maximum child age');
        if ($age !== $expectedAge) throw new InvalidArgumentException('The shared child age setting changed. Reopen this room and try again.');
    } elseif ($nextAge !== $age) {
        throw new InvalidArgumentException('Reopen the room before changing the shared child age setting.');
    }
    $countChildren = array_key_exists('count_children_toward_capacity', $shared)
        ? room_occupancy_boolean($shared['count_children_toward_capacity']) : true;
    $nextCount = $countChildren;
    if (array_key_exists('count_children_toward_capacity', $data)) {
        $nextCount = room_occupancy_boolean($data['count_children_toward_capacity']);
        if (!array_key_exists('expected_count_children_toward_capacity', $data)) throw new InvalidArgumentException('Reopen the room before changing the shared child-capacity setting.');
        $expectedCount = room_occupancy_boolean($data['expected_count_children_toward_capacity']);
        if ($countChildren !== $expectedCount) throw new InvalidArgumentException('The shared child-capacity setting changed. Reopen this room and try again.');
    }
    // The room column mirrors the shared age so existing availability readers also
    // use the common policy. Room-specific adult, child and total limits stay separate.
    $limits = room_occupancy_input(array_replace($data, ['max_child_age' => $nextAge]), $existing);
    $pdo->prepare('UPDATE rooms SET max_guests = ?, max_adults = ?, max_children = ?, max_total_occupants = ?, max_child_age = ? WHERE id = ?')
        ->execute([$limits['max_total_occupants'], ...array_values($limits), $roomId]);
    $write = $pdo->prepare('INSERT INTO website_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    if ($nextAge !== $age) {
        $write->execute(['max_child_age', (string) $nextAge]);
        $pdo->prepare('UPDATE rooms SET max_child_age = ? WHERE max_child_age <> ?')->execute([$nextAge, $nextAge]);
    }
    if ($nextCount !== $countChildren) $write->execute(['count_children_toward_capacity', $nextCount ? '1' : '0']);
}
