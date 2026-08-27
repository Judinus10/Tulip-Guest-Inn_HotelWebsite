<?php
declare(strict_types=1);

function multi_room_group_id(array $booking): int
{
    return (int) ($booking['booking_group_id'] ?? 0);
}

function multi_room_booking_number(array $booking): string
{
    $groupId = multi_room_group_id($booking);
    if ($groupId > 0) {
        return 'MB-' . str_pad((string) $groupId, 6, '0', STR_PAD_LEFT);
    }
    return 'BK-' . str_pad((string) ($booking['id'] ?? 0), 5, '0', STR_PAD_LEFT);
}

function multi_room_group_rows(PDO $pdo, int $groupId): array
{
    if ($groupId < 1) return [];

    $stmt = $pdo->prepare(
        'SELECT b.*, r.id AS room_id, r.max_guests, r.base_price
         FROM bookings b
         LEFT JOIN rooms r ON r.room_name = b.room_name
         WHERE b.booking_group_id = :group_id
         ORDER BY b.is_group_primary DESC, b.id ASC'
    );
    $stmt->execute([':group_id' => $groupId]);
    return $stmt->fetchAll() ?: [];
}

function multi_room_primary_booking_id(PDO $pdo, int $groupId): int
{
    $stmt = $pdo->prepare(
        'SELECT id FROM bookings
         WHERE booking_group_id = :group_id
         ORDER BY is_group_primary DESC, id ASC
         LIMIT 1'
    );
    $stmt->execute([':group_id' => $groupId]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

