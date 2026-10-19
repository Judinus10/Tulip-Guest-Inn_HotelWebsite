<?php
declare(strict_types=1);
require_once __DIR__ . '/../bookings/booking-expiry-helper.php';

function dashboard_daily_room_summary(PDO $pdo): array
{
    $today = new DateTimeImmutable('today');
    $date = $today->format('Y-m-d');
    $tomorrow = $today->modify('+1 day')->format('Y-m-d');
    $cutoff = booking_hold_cutoff_datetime();
    $rooms = $pdo->query('SELECT id, room_name, status FROM rooms ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $table = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='external_calendar_events'");
    $table->execute();
    $hasExternal = (bool) $table->fetchColumn();
    $references = ['checkins' => [], 'checkouts' => []];

    // Count reservation groups once for arrivals/departures. Each physical room
    // still contributes independently to the availability calculation below.
    foreach (['checkins' => 'check_in_date', 'checkouts' => 'check_out_date'] as $kind => $field) {
        $completedDeparture = $kind === 'checkouts' ? " OR status = 'Checked Out'" : '';
        $active = preg_replace('/^AND\s*/', '', trim(active_booking_conflict_sql()));
        $query = $pdo->prepare("SELECT id, booking_group_id FROM bookings
            WHERE (booking_group_id IS NULL OR is_group_primary = 1)
              AND {$field} = :stay_date AND ({$active}{$completedDeparture})");
        $query->execute([':stay_date' => $date, ':hold_cutoff' => $cutoff]);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $booking) {
            $references[$kind][] = $booking['booking_group_id'] !== null
                ? 'MB-' . str_pad((string) $booking['booking_group_id'], 6, '0', STR_PAD_LEFT)
                : 'BK-' . str_pad((string) $booking['id'], 5, '0', STR_PAD_LEFT);
        }
        if ($hasExternal) {
            $externalField = $kind === 'checkins' ? 'start_date' : 'end_date';
            $query = $pdo->prepare("SELECT id FROM external_calendar_events
                WHERE provider = 'booking.com' AND is_active = 1 AND {$externalField} = :stay_date");
            $query->execute([':stay_date' => $date]);
            foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $references[$kind][] = 'BC-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            }
        }
    }

    $available = 0;
    $occupied = 0;
    $bookingConflict = $pdo->prepare("SELECT id FROM bookings
        WHERE room_name = :room_name " . active_booking_conflict_sql() . "
          AND :check_in < check_out_date AND :check_out > check_in_date LIMIT 1");
    $confirmedStay = $pdo->prepare("SELECT id FROM bookings
        WHERE room_name = :room_name
          AND (status IN ('Confirmed', 'Checked In') OR (status = 'Pending' AND payment_status = 'Paid'))
          AND :check_in < check_out_date AND :check_out > check_in_date LIMIT 1");
    $externalConflict = $hasExternal ? $pdo->prepare("SELECT id FROM external_calendar_events
        WHERE room_id = :room_id AND provider = 'booking.com' AND is_active = 1
          AND :check_in < end_date AND :check_out > start_date LIMIT 1") : null;
    foreach ($rooms as $room) {
        $params = [':room_name' => $room['room_name'], ':check_in' => $date, ':check_out' => $tomorrow];
        $bookingConflict->execute($params + [':hold_cutoff' => $cutoff]);
        $blocked = (bool) $bookingConflict->fetchColumn();
        $confirmedStay->execute($params);
        $staying = (bool) $confirmedStay->fetchColumn();
        $external = false;
        if ($externalConflict) {
            $externalConflict->execute([':room_id' => $room['id'], ':check_in' => $date, ':check_out' => $tomorrow]);
            $external = (bool) $externalConflict->fetchColumn();
        }
        // Pausing new ICS imports must not release existing active reservations.
        if ($staying || $external) $occupied++;
        if ($room['status'] === 'Available' && !$blocked && !$external) $available++;
    }
    return [
        'today' => $date,
        'todayCheckIns' => count($references['checkins']),
        'todayCheckOuts' => count($references['checkouts']),
        'checkInReferences' => $references['checkins'],
        'checkOutReferences' => $references['checkouts'],
        'totalRooms' => count($rooms),
        'availableTonight' => $available,
        'availableRooms' => $available,
        'occupancyRate' => count($rooms) > 0 ? (int) round($occupied / count($rooms) * 100) : 0,
    ];
}
