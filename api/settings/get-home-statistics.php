<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/contact_helpers.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Only GET requests are allowed.', 405);
}

try {
    $pdo = get_db_connection();
    $settings = get_contact_settings($pdo);

    // Standalone bookings contribute their own guest count. A multi-room
    // booking contributes booking_groups.total_guests once, not once per room.
    $guestStmt = $pdo->query(
        "SELECT COALESCE(SUM(counted_guests), 0)
         FROM (
             SELECT b.guests AS counted_guests
             FROM bookings b
             WHERE b.booking_group_id IS NULL
               AND b.status IN ('Confirmed', 'Checked In', 'Checked Out')

             UNION ALL

             SELECT MAX(bg.total_guests) AS counted_guests
             FROM booking_groups bg
             INNER JOIN bookings b ON b.booking_group_id = bg.id
             WHERE b.status IN ('Confirmed', 'Checked In', 'Checked Out')
             GROUP BY bg.id
         ) counted_stays"
    );
    $bookedGuests = max(0, (int) $guestStmt->fetchColumn());
    $displayGuestCount = 2500 + (intdiv($bookedGuests, 100) * 100);

    $roomStmt = $pdo->query('SELECT COUNT(*) FROM rooms');
    $roomCount = max(0, (int) $roomStmt->fetchColumn());

    $currentYear = (int) date('Y');
    $openingYear = (int) ($settings['opening_year'] ?? $currentYear);
    $yearsOfService = max(0, $currentYear - $openingYear);

    json_response(true, 'Home statistics loaded successfully.', 200, [
        'data' => [
            ['id' => 'happy-guests', 'value' => $displayGuestCount, 'suffix' => '+', 'label' => 'Happy Guests'],
            ['id' => 'rooms', 'value' => $roomCount, 'suffix' => '', 'label' => 'Luxury Rooms'],
            ['id' => 'years', 'value' => $yearsOfService, 'suffix' => '', 'label' => 'Years of Service'],
            ['id' => 'rating', 'value' => 4.5, 'suffix' => '/5', 'label' => 'Guest Rating'],
        ],
    ]);
} catch (Throwable $e) {
    error_log('Home statistics load error: ' . $e->getMessage());
    json_response(false, 'Could not load home statistics.', 500);
}
