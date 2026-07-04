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
        && (string) ($booking['payment_status'] ?? '') === 'Payment Pending';
}

function booking_hold_is_expired(array $booking): bool
{
    return booking_is_unpaid_pending($booking) && booking_seconds_remaining($booking) <= 0;
}

function expire_pending_bookings(PDO $pdo, ?int $bookingId = null, bool $sendEmails = false): int
{
    $cutoff = booking_hold_cutoff_datetime();

    $params = [':cutoff' => $cutoff];
    $whereId = '';

    if ($bookingId !== null && $bookingId > 0) {
        $whereId = ' AND id = :booking_id';
        $params[':booking_id'] = $bookingId;
    }

    $select = $pdo->prepare(
        "SELECT *
         FROM bookings
         WHERE status = 'Pending'
           AND payment_status = 'Payment Pending'
           AND created_at < :cutoff
           {$whereId}"
    );
    $select->execute($params);
    $expiredBookings = $select->fetchAll();

    if (!$expiredBookings) {
        return 0;
    }

    $ids = array_map(static fn (array $booking): int => (int) $booking['id'], $expiredBookings);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $update = $pdo->prepare(
        "UPDATE bookings
         SET status = 'Cancelled',
             payment_status = 'Cancelled',
             updated_at = NOW()
         WHERE id IN ({$placeholders})
           AND status = 'Pending'
           AND payment_status = 'Payment Pending'"
    );
    $update->execute($ids);

    $paymentUpdate = $pdo->prepare(
        "UPDATE payments
         SET status = 'Cancelled',
             updated_at = NOW()
         WHERE booking_id IN ({$placeholders})
           AND status = 'Payment Pending'"
    );
    $paymentUpdate->execute($ids);

    if ($sendEmails) {
        try {
            require_once __DIR__ . '/../mail/email-helper.php';
            foreach ($expiredBookings as $booking) {
                $booking['status'] = 'Cancelled';
                $booking['payment_status'] = 'Cancelled';
                booking_audit_log($pdo, (int) $booking['id'], 'booking_expired', 'Booking Expired', 'Unpaid pending booking expired and room was released.', [
                    'hold_minutes' => booking_hold_minutes(),
                ]);
                send_booking_expired_emails_once($pdo, $booking);
            }
        } catch (Throwable $exception) {
            error_log('Expired booking email error: ' . $exception->getMessage());
        }
    }

    return count($ids);
}

function active_booking_conflict_sql(): string
{
    return "AND (
                status = 'Confirmed'
                OR
                (status = 'Pending' AND COALESCE(payment_status, '') = 'Payment Pending' AND created_at >= :hold_cutoff)
            )";
}
