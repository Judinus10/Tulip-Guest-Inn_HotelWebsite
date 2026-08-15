<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../calendar/ics-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_admin_auth();

try {
    $pdo = get_db_connection();
    $stmt = $pdo->query(
        "SELECT
            b.id,
            CONCAT('BK-', LPAD(b.id, 5, '0')) AS booking_no,
            CASE WHEN b.is_booking_for_other = 1 AND COALESCE(NULLIF(b.staying_guest_name, ''), '') <> '' THEN b.staying_guest_name ELSE b.full_name END AS guest_name,
            CASE WHEN b.is_booking_for_other = 1 AND COALESCE(NULLIF(b.staying_guest_email, ''), '') <> '' THEN b.staying_guest_email ELSE b.email END AS guest_email,
            CASE WHEN b.is_booking_for_other = 1 AND COALESCE(NULLIF(b.staying_guest_phone, ''), '') <> '' THEN b.staying_guest_phone ELSE b.phone END AS guest_phone,
            b.full_name AS booker_name,
            b.email AS booker_email,
            b.phone AS booker_phone,
            b.is_booking_for_other,
            b.staying_guest_name,
            b.staying_guest_email,
            b.staying_guest_phone,
            b.staying_guest_note,
            r.id AS room_id,
            b.room_name,
            CASE
                WHEN b.room_name LIKE 'Ground Floor%' THEN 'Ground Floor'
                WHEN b.room_name LIKE 'First Floor%' THEN 'First Floor'
                WHEN b.room_name = 'Family Room' THEN 'Family'
                WHEN b.room_name = 'Private Cottage' THEN 'Cottage'
                ELSE 'Guest House'
            END AS room_type,
            CONCAT('R', LPAD(COALESCE(r.id, b.id), 2, '0')) AS room_code,
            CASE
                WHEN b.room_name = 'Private Cottage' THEN 'Cottage'
                ELSE 'Guest House'
            END AS property_type,
            b.check_in_date,
            b.check_out_date,
            b.check_in_date AS check_in,
            b.check_out_date AS check_out,
            b.guests,
            b.guests AS adults,
            0 AS children,
            GREATEST(1, DATEDIFF(b.check_out_date, b.check_in_date)) AS total_nights,
            b.message AS special_requests,
            b.message AS special_request,
            LOWER(b.status) AS booking_status,
            b.payment_status,
            COALESCE((
                SELECT p.method
                FROM payments p
                WHERE p.booking_id = b.id
                ORDER BY p.id DESC
                LIMIT 1
            ), '') AS payment_method,
            b.amount AS total_amount,
            b.currency AS payment_currency,
            b.invoice_number,
            b.invoice_file_path,
            b.email_status,
            b.created_at,
            b.updated_at
         FROM bookings b
         LEFT JOIN rooms r ON r.room_name = b.room_name
         ORDER BY
            CASE WHEN CURDATE() >= b.check_in_date AND CURDATE() < b.check_out_date THEN 0
                 WHEN b.check_in_date >= CURDATE() THEN 1 ELSE 2 END,
            CASE WHEN b.check_in_date >= CURDATE() THEN b.check_in_date END ASC,
            b.created_at DESC"
    );

    $bookings = $stmt->fetchAll();
    ensure_ics_schema($pdo);
    $externalStmt = $pdo->query(
        "SELECT e.*, r.room_name
         FROM external_calendar_events e
         INNER JOIN rooms r ON r.id = e.room_id
         WHERE e.provider = 'booking.com'
         ORDER BY
            CASE WHEN CURDATE() >= e.start_date AND CURDATE() < e.end_date THEN 0
                 WHEN e.start_date >= CURDATE() THEN 1 ELSE 2 END,
            CASE WHEN e.start_date >= CURDATE() THEN e.start_date END ASC,
            e.updated_at DESC"
    );

    foreach ($externalStmt->fetchAll() as $event) {
        $isCancelled = (int) $event['is_active'] !== 1;
        $externalStatus = strtoupper((string) ($event['status'] ?? ''));
        $bookingStatus = $isCancelled ? 'cancelled' : ($externalStatus === 'PENDING' ? 'pending' : 'confirmed');
        $bookings[] = [
            'id' => -1 * (int) $event['id'],
            'booking_no' => 'BC-' . str_pad((string) $event['id'], 5, '0', STR_PAD_LEFT),
            'guest_name' => trim((string) ($event['summary'] ?? '')) ?: 'Booking.com Guest',
            'guest_email' => '', 'guest_phone' => '', 'booker_name' => 'Booking.com',
            'booker_email' => '', 'booker_phone' => '', 'is_booking_for_other' => 0,
            'staying_guest_name' => '', 'staying_guest_email' => '', 'staying_guest_phone' => '', 'staying_guest_note' => '',
            'room_id' => (int) $event['room_id'], 'room_name' => $event['room_name'],
            'room_type' => 'Booking.com', 'room_code' => 'R' . str_pad((string) $event['room_id'], 2, '0', STR_PAD_LEFT),
            'property_type' => 'Guest House',
            'check_in_date' => $event['start_date'], 'check_out_date' => $event['end_date'],
            'check_in' => $event['start_date'], 'check_out' => $event['end_date'],
            'guests' => 1, 'adults' => 1, 'children' => 0,
            'total_nights' => max(1, (int) (new DateTimeImmutable($event['start_date']))->diff(new DateTimeImmutable($event['end_date']))->days),
            'special_requests' => 'Imported from Booking.com calendar. UID: ' . $event['external_uid'],
            'special_request' => 'Imported from Booking.com calendar. UID: ' . $event['external_uid'],
            'booking_status' => $bookingStatus, 'payment_status' => 'No Pay',
            'total_amount' => 0, 'payment_currency' => PAYMENT_CURRENCY,
            'invoice_number' => '', 'invoice_file_path' => '', 'email_status' => 'Not applicable',
            'created_at' => $event['created_at'], 'updated_at' => $event['updated_at'],
            'source' => 'booking.com', 'is_external' => true, 'external_uid' => $event['external_uid'],
        ];
    }

    $today = date('Y-m-d');
    usort($bookings, static function (array $a, array $b) use ($today): int {
        $rank = static function (array $row) use ($today): int {
            if (($row['check_in_date'] ?? '') <= $today && ($row['check_out_date'] ?? '') > $today) return 0;
            if (($row['check_in_date'] ?? '') >= $today) return 1;
            return 2;
        };
        $rankCompare = $rank($a) <=> $rank($b);
        if ($rankCompare !== 0) return $rankCompare;
        if ($rank($a) <= 1) return strcmp((string) $a['check_in_date'], (string) $b['check_in_date']);
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });

    json_response(true, 'Bookings loaded successfully.', 200, [
        'data' => $bookings,
    ]);
} catch (Throwable $e) {
    error_log('Admin bookings list error: ' . $e->getMessage());
    json_response(false, 'Unable to load bookings.', 500);
}
