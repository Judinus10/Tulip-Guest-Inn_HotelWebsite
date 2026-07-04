<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

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
            b.amount AS total_amount,
            b.currency AS payment_currency,
            b.invoice_number,
            b.invoice_file_path,
            b.email_status,
            b.created_at,
            b.updated_at
         FROM bookings b
         LEFT JOIN rooms r ON r.room_name = b.room_name
         ORDER BY b.created_at DESC"
    );

    json_response(true, 'Bookings loaded successfully.', 200, [
        'data' => $stmt->fetchAll(),
    ]);
} catch (Throwable $e) {
    error_log('Admin bookings list error: ' . $e->getMessage());
    json_response(false, 'Unable to load bookings.', 500);
}

