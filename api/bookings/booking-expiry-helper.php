<?php
/**
 * Shared booking reservation expiry helpers.
 *
 * Pending unpaid bookings should only hold a room for a short time.
 * This file keeps the public availability check, booking submit, checkout,
 * and bill page using the same rule.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/booking-audit-helper.php';

const BOOKING_PAYMENT_HOLD_MINUTES = 15;

function booking_hold_minutes(): int
{
    return BOOKING_PAYMENT_HOLD_MINUTES;
}

function booking_hold_cutoff_datetime(): string
{
    return (new DateTimeImmutable('-' . booking_hold_minutes() . ' minutes'))->format('Y-m-d H:i:s');
}

function booking_expires_at(array $booking): ?string
{
    $createdAt = trim((string) ($booking['created_at'] ?? ''));

    if ($createdAt === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($createdAt))->modify('+' . booking_hold_minutes() . ' minutes')->format('Y-m-d H:i:s');
    } catch (Throwable $exception) {
        return null;
    }
}

function booking_seconds_remaining(array $booking): int
{
    $expiresAt = booking_expires_at($booking);

    if ($expiresAt === null) {
        return 0;
    }

    try {
        $expires = new DateTimeImmutable($expiresAt);
        $now = new DateTimeImmutable('now');
        return max(0, $expires->getTimestamp() - $now->getTimestamp());
    } catch (Throwable $exception) {
        return 0;
    }
}

function booking_is_unpaid_pending(array $booking): bool
{
    return (string) ($booking['status'] ?? '') === 'Pending'
        && in_array((string) ($booking['payment_status'] ?? ''), ['Payment Pending', 'Failed', 'Cancelled'], true)
        && strcasecmp((string) ($booking['payment_method'] ?? ''), 'Cash') !== 0;
}

function booking_can_retry_payment(array $booking): bool
{
    return booking_is_unpaid_pending($booking) && booking_seconds_remaining($booking) > 0;
}

function booking_hold_is_expired(array $booking): bool
{
    return booking_is_unpaid_pending($booking) && booking_seconds_remaining($booking) <= 0;
}

/** Prepare outside transactions: MySQL CREATE TABLE would otherwise commit them. */
function ensure_booking_expiry_events_table(PDO $pdo): void
{
    static $ready = [];
    $key = spl_object_id($pdo);
    if (isset($ready[$key])) return;
    if ($pdo->inTransaction()) {
        throw new RuntimeException('Prepare booking expiry storage before starting a transaction.');
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS booking_expiry_events (
        booking_id INT UNSIGNED NOT NULL PRIMARY KEY,
        booking_group_id INT UNSIGNED NULL,
        hold_expires_at DATETIME NOT NULL,
        expired_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME NULL,
        next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        outcome VARCHAR(20) NULL,
        KEY idx_expiry_events_pending (processed_at, next_attempt_at, expired_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready[$key] = true;
}

function expire_pending_bookings(PDO $pdo, ?int $bookingId = null, bool $sendEmails = false): int
{
    ensure_booking_expiry_events_table($pdo);
    if (!$pdo->inTransaction()) ensure_booking_audit_table($pdo);
    $ownTransaction = !$pdo->inTransaction();
    $expiredCount = 0;
    try {
        if ($ownTransaction) $pdo->beginTransaction();
        $params = [':cutoff' => booking_hold_cutoff_datetime()];
        $filter = '';
        if ($bookingId !== null && $bookingId > 0) {
            // A lookup of any room expires the complete reservation together.
            $filter = ' AND (b.id = :booking_id OR b.booking_group_id = (
                SELECT NULLIF(booking_group_id, 0) FROM bookings WHERE id = :group_booking_id))';
            $params[':booking_id'] = $bookingId;
            $params[':group_booking_id'] = $bookingId;
        }
        $stmt = $pdo->prepare("SELECT b.* FROM bookings b
            WHERE b.status = 'Pending'
              AND b.payment_status IN ('Payment Pending','Failed','Cancelled')
              AND b.created_at <= :cutoff {$filter}
              AND NOT EXISTS (
                  SELECT 1 FROM payments p INNER JOIN bookings pb ON pb.id = p.booking_id
                  WHERE (pb.id = b.id OR (b.booking_group_id > 0 AND pb.booking_group_id = b.booking_group_id))
                    AND (p.status = 'Paid' OR LOWER(COALESCE(p.method, '')) = 'cash')
              )
            ORDER BY b.id FOR UPDATE");
        $stmt->execute($params);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $visited = [];
        foreach ($candidates as $candidate) {
            $groupId = (int) ($candidate['booking_group_id'] ?? 0);
            $scopeKey = $groupId > 0 ? 'group:' . $groupId : 'booking:' . $candidate['id'];
            if (isset($visited[$scopeKey])) continue;
            $visited[$scopeKey] = true;
            $scope = $pdo->prepare($groupId > 0
                ? 'SELECT * FROM bookings WHERE booking_group_id = ? ORDER BY is_group_primary DESC, id ASC FOR UPDATE'
                : 'SELECT * FROM bookings WHERE id = ? FOR UPDATE');
            $scope->execute([$groupId > 0 ? $groupId : (int) $candidate['id']]);
            $rows = $scope->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) continue;
            foreach ($rows as $row) {
                if (!booking_hold_is_expired($row)) continue 2;
            }
            $ids = array_map(static fn(array $row): int => (int) $row['id'], $rows);
            $sqlIds = implode(',', $ids); // Locked database IDs only.
            $protected = $pdo->query("SELECT id FROM payments WHERE booking_id IN ({$sqlIds})
                AND (status = 'Paid' OR LOWER(COALESCE(method, '')) = 'cash') LIMIT 1 FOR UPDATE");
            if ($protected->fetchColumn()) continue;
            $updated = $pdo->exec("UPDATE bookings SET status = 'Cancelled', payment_status = 'Cancelled',
                updated_at = NOW() WHERE id IN ({$sqlIds})");
            $pdo->exec("UPDATE payments SET status = 'Cancelled', updated_at = NOW()
                WHERE booking_id IN ({$sqlIds}) AND status = 'Payment Pending'");
            $primary = $rows[0];
            // This durable event commits with the room release, even for callers
            // that do not queue mail (availability checks and gateway callbacks).
            $event = $pdo->prepare('INSERT INTO booking_expiry_events
                (booking_id, booking_group_id, hold_expires_at) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE booking_id = VALUES(booking_id)');
            $event->execute([(int) $primary['id'], $groupId ?: null, booking_expires_at($primary)]);
            booking_audit_log($pdo, (int) $primary['id'], 'booking_expired', 'Booking Hold Expired',
                'The unpaid reservation hold expired. All rooms were released and an expiry notification event was recorded.',
                ['hold_minutes' => booking_hold_minutes(), 'room_booking_ids' => $ids]);
            $expiredCount += (int) $updated;
        }
        if ($ownTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    // Never prepare mail schemas inside a caller's inventory/payment transaction.
    // Cron recovers durable events left by those callers or by queue failures.
    if ($sendEmails && !$pdo->inTransaction()) {
        try {
            require_once __DIR__ . '/../mail/email-helper.php';
            queue_booking_expiry_events($pdo);
        } catch (Throwable $e) {
            error_log('Expiry notification queue deferred to cron: ' . $e->getMessage());
        }
    }
    return $expiredCount;
}

function active_booking_conflict_sql(): string
{
    return "AND (
                status IN ('Confirmed', 'Checked In')
                OR (status = 'Pending' AND payment_status = 'Paid')
                OR
                (
                    status = 'Pending'
                    AND COALESCE(payment_status, '') IN ('Payment Pending', 'Failed', 'Cancelled')
                    AND (
                        created_at >= :hold_cutoff
                        OR EXISTS (
                            SELECT 1 FROM payments p
                            INNER JOIN bookings payment_booking ON payment_booking.id = p.booking_id
                            WHERE (payment_booking.id = bookings.id
                                   OR (bookings.booking_group_id > 0
                                       AND payment_booking.booking_group_id = bookings.booking_group_id))
                              AND LOWER(COALESCE(p.method, '')) = 'cash'
                        )
                    )
                )
            )";
}
