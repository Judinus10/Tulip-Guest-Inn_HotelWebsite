<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../calendar/ics-helper.php';
require_once __DIR__ . '/multi-room-helper.php';

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
            CASE
                WHEN b.booking_group_id IS NOT NULL THEN CONCAT('MB-', LPAD(b.booking_group_id, 6, '0'))
                ELSE CONCAT('BK-', LPAD(b.id, 5, '0'))
            END AS booking_no,
            b.booking_group_id,
            b.is_group_primary,
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
                LEFT JOIN bookings payment_booking ON payment_booking.id = p.booking_id
                WHERE p.booking_id = b.id
                   OR (b.booking_group_id IS NOT NULL AND payment_booking.booking_group_id = b.booking_group_id)
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
         WHERE b.booking_group_id IS NULL OR b.is_group_primary = 1
         ORDER BY b.created_at DESC"
    );

    $data = $stmt->fetchAll();
    foreach ($data as &$bookingRow) {
        $groupId = (int) ($bookingRow['booking_group_id'] ?? 0);
        if ($groupId < 1) {
            $bookingRow['group_rooms'] = [];
            $bookingRow['room_count'] = 1;
            continue;
        }

        $groupRows = multi_room_group_rows($pdo, $groupId);
        $bookingRow['group_rooms'] = array_map(static fn(array $row): array => [
            'booking_id' => (int) $row['id'],
            'room_id' => (int) ($row['room_id'] ?? 0),
            'room_name' => (string) ($row['room_name'] ?? ''),
            'guests' => (int) ($row['guests'] ?? 0),
            'capacity' => (int) ($row['max_guests'] ?? 0),
            'price_per_night' => (float) ($row['base_price'] ?? 0),
            'amount' => (float) ($row['amount'] ?? 0),
        ], $groupRows);
        $bookingRow['room_count'] = count($groupRows);
        $bookingRow['room_name'] = implode(', ', array_column($groupRows, 'room_name'));
        $bookingRow['guests'] = array_sum(array_map(static fn(array $row): int => (int) ($row['guests'] ?? 0), $groupRows));
        $bookingRow['adults'] = $bookingRow['guests'];
        $bookingRow['total_amount'] = array_sum(array_map(static fn(array $row): float => (float) ($row['amount'] ?? 0), $groupRows));
    }
    unset($bookingRow);
    $external = [];
    if (ics_enabled()) {
        ensure_ics_schema($pdo);
        $external = $pdo->query("SELECT e.id, CONCAT('BC-', LPAD(e.id,5,'0')) booking_no, CASE WHEN e.is_active=0 OR UPPER(COALESCE(e.status,'')) IN ('CANCELLED','CANCELED') THEN 'Booking.com cancelled reservation' ELSE 'Booking.com reservation' END guest_name, '' guest_email, '' guest_phone, '' booker_name, '' booker_email, '' booker_phone, 0 is_booking_for_other, NULL staying_guest_name, NULL staying_guest_email, NULL staying_guest_phone, NULL staying_guest_note, r.id room_id, r.room_name, 'External' room_type, CONCAT('R',LPAD(r.id,2,'0')) room_code, 'Guest House' property_type, e.start_date check_in_date, e.end_date check_out_date, e.start_date check_in, e.end_date check_out, 0 guests, 0 adults, 0 children, GREATEST(1,DATEDIFF(e.end_date,e.start_date)) total_nights, '' special_requests, '' special_request, CASE WHEN e.is_active=0 OR UPPER(COALESCE(e.status,'')) IN ('CANCELLED','CANCELED') THEN 'cancelled' ELSE 'external' END booking_status, 'External' payment_status, 0 total_amount, 'USD' payment_currency, NULL invoice_number, NULL invoice_file_path, 'N/A' email_status, e.created_at, e.updated_at, 'booking.com' source, s.last_sync_status sync_status, s.last_sync_completed_at last_synced_at, e.is_active external_is_active, e.status external_status FROM external_calendar_events e JOIN rooms r ON r.id=e.room_id LEFT JOIN external_calendar_sync_status s ON s.room_id=e.room_id ORDER BY e.start_date DESC, e.created_at DESC")->fetchAll();
    }
    // Merge website and Booking.com records first, then apply one shared
    // newest-first order. Sorting each source separately before appending
    // forces every external booking to the bottom of the list.
    $data = array_merge($data, $external);

    usort($data, static function (array $left, array $right): int {
        $leftCreatedAt = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
        $rightCreatedAt = strtotime((string) ($right['created_at'] ?? '')) ?: 0;

        if ($leftCreatedAt !== $rightCreatedAt) {
            return $rightCreatedAt <=> $leftCreatedAt;
        }

        // Keep the result deterministic when two records have the same
        // creation/import timestamp. The numeric part of the booking number
        // is used as a stable newest-first fallback.
        $leftNumber = (int) preg_replace('/\D+/', '', (string) ($left['booking_no'] ?? '0'));
        $rightNumber = (int) preg_replace('/\D+/', '', (string) ($right['booking_no'] ?? '0'));

        return $rightNumber <=> $leftNumber;
    });

    json_response(true, 'Bookings loaded successfully.', 200, ['data' => $data]);
} catch (Throwable $e) {
    error_log('Admin bookings list error: ' . $e->getMessage());
    json_response(false, 'Unable to load bookings.', 500);
}
