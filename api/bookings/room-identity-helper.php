<?php
declare(strict_types=1);

/**
 * Run before any application transaction. The completion marker deliberately
 * prevents later room-name reuse from silently reassigning unmatched bookings.
 */
function ensure_booking_room_identity(PDO $pdo): void
{
    try {
        if ($pdo->query('SELECT id FROM booking_room_identity_migration WHERE id = 1')->fetchColumn()) return;
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) !== 1146) throw $e;
    }
    if ($pdo->inTransaction()) throw new RuntimeException('Room identity migration must run before transactions.');
    $lockName = 'tulip-room-identity-' . substr(hash('sha256', (string) $pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 32);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 30)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) throw new RuntimeException('Room identity migration is busy. Please retry.');
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS booking_room_identity_migration (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            completed_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        if ($pdo->query('SELECT id FROM booking_room_identity_migration WHERE id = 1')->fetchColumn()) return;
        $columns = $pdo->query('SHOW COLUMNS FROM bookings')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('room_id', $columns, true)) {
            $pdo->exec('ALTER TABLE bookings ADD COLUMN room_id INT UNSIGNED NULL AFTER room_name,
                ADD INDEX idx_bookings_room_id_dates (room_id, check_in_date, check_out_date)');
        }
        // DDL is complete. Lock physical rooms while assigning legacy bookings.
        $pdo->beginTransaction();
        $pdo->query('SELECT id FROM rooms ORDER BY id FOR UPDATE')->fetchAll();
        $pdo->exec('UPDATE bookings b JOIN (
            SELECT room_name, MIN(id) AS id FROM rooms GROUP BY room_name HAVING COUNT(*) = 1
        ) r ON r.room_name = b.room_name SET b.room_id = r.id WHERE b.room_id IS NULL');
        $pdo->exec('INSERT INTO booking_room_identity_migration (id, completed_at) VALUES (1, NOW())');
        $pdo->commit();
        $unmatched = (int) $pdo->query('SELECT COUNT(*) FROM bookings WHERE room_id IS NULL')->fetchColumn();
        if ($unmatched > 0) error_log('Room identity: ' . $unmatched . ' legacy bookings need explicit room assignment. Active overlaps will block availability until resolved.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}

/** IDs win. Name-only compatibility is for availability reads; booking writes require an ID. */
function booking_selected_room(PDO $pdo, int $roomId, string $roomName = '', bool $lock = false): array|false
{
    $stmt = $pdo->prepare('SELECT * FROM rooms WHERE ' . ($roomId > 0 ? 'id = :identity' : 'room_name = :identity')
        . ' ORDER BY id' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([':identity' => $roomId > 0 ? $roomId : $roomName]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return count($rows) === 1 ? $rows[0] : false;
}
