<?php
/**
 * Lightweight booking audit trail helper.
 * Creates the audit table automatically if it does not exist, so this patch
 * does not require manually editing the main SQL dump before testing.
 */

declare(strict_types=1);

function ensure_booking_audit_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS booking_audit_logs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id INT UNSIGNED NOT NULL,
            payment_id INT UNSIGNED NULL,
            order_id VARCHAR(120) NULL,
            event_type VARCHAR(80) NOT NULL,
            event_title VARCHAR(160) NOT NULL,
            event_message TEXT NULL,
            metadata JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_booking_audit_booking_id (booking_id),
            KEY idx_booking_audit_event_type (event_type),
            KEY idx_booking_audit_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $ready = true;
}

function booking_audit_log(PDO $pdo, int $bookingId, string $eventType, string $eventTitle, string $eventMessage = '', array $metadata = []): void
{
    if ($bookingId < 1 || trim($eventType) === '' || trim($eventTitle) === '') {
        return;
    }

    try {
        if (!$pdo->inTransaction()) {
            ensure_booking_audit_table($pdo);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO booking_audit_logs
                (booking_id, payment_id, order_id, event_type, event_title, event_message, metadata, created_at)
             VALUES
                (:booking_id, :payment_id, :order_id, :event_type, :event_title, :event_message, :metadata, NOW())'
        );

        $stmt->execute([
            ':booking_id' => $bookingId,
            ':payment_id' => isset($metadata['payment_row_id']) ? (int) $metadata['payment_row_id'] : null,
            ':order_id' => isset($metadata['order_id']) ? (string) $metadata['order_id'] : null,
            ':event_type' => substr($eventType, 0, 80),
            ':event_title' => substr($eventTitle, 0, 160),
            ':event_message' => $eventMessage !== '' ? $eventMessage : null,
            ':metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Throwable $exception) {
        error_log('Booking audit log failed: ' . $exception->getMessage());
    }
}

function get_booking_audit_timeline(PDO $pdo, int $bookingId): array
{
    if ($bookingId < 1) {
        return [];
    }

    try {
        if (!$pdo->inTransaction()) {
            ensure_booking_audit_table($pdo);
        }
        $stmt = $pdo->prepare(
            'SELECT event_type, event_title, event_message, order_id, created_at
             FROM booking_audit_logs
             WHERE booking_id = :booking_id
             ORDER BY id ASC'
        );
        $stmt->execute([':booking_id' => $bookingId]);

        return array_map(static function (array $row): array {
            return [
                'event_type' => (string) ($row['event_type'] ?? ''),
                'title' => (string) ($row['event_title'] ?? ''),
                'message' => (string) ($row['event_message'] ?? ''),
                'order_id' => (string) ($row['order_id'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }, $stmt->fetchAll() ?: []);
    } catch (Throwable $exception) {
        error_log('Booking audit timeline load failed: ' . $exception->getMessage());
        return [];
    }
}
