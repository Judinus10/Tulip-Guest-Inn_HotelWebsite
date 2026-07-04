<?php
/**
 * Admin payments list endpoint.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Only GET requests are allowed.', 405);
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->query(
        "SELECT
            p.id,
            p.booking_id,
            CONCAT('BK-', LPAD(COALESCE(p.booking_id, 0), 5, '0')) AS booking_no,
            COALESCE(CASE WHEN b.is_booking_for_other = 1 AND COALESCE(NULLIF(b.staying_guest_name, ''), '') <> '' THEN b.staying_guest_name ELSE b.full_name END, 'Unknown Guest') AS guest_name,
            COALESCE(CASE WHEN b.is_booking_for_other = 1 AND COALESCE(NULLIF(b.staying_guest_email, ''), '') <> '' THEN b.staying_guest_email ELSE b.email END, '') AS guest_email,
            COALESCE(CASE WHEN b.is_booking_for_other = 1 AND COALESCE(NULLIF(b.staying_guest_phone, ''), '') <> '' THEN b.staying_guest_phone ELSE b.phone END, '') AS guest_phone,
            COALESCE(b.full_name, '') AS booker_name,
            COALESCE(b.email, '') AS booker_email,
            COALESCE(b.phone, '') AS booker_phone,
            COALESCE(b.is_booking_for_other, 0) AS is_booking_for_other,
            COALESCE(b.staying_guest_name, '') AS staying_guest_name,
            COALESCE(b.staying_guest_email, '') AS staying_guest_email,
            COALESCE(b.staying_guest_phone, '') AS staying_guest_phone,
            COALESCE(b.staying_guest_note, '') AS staying_guest_note,
            COALESCE(b.status, '') AS booking_status,
            COALESCE(b.check_in_date, '') AS check_in,
            COALESCE(b.check_out_date, '') AS check_out,
            COALESCE(b.guests, 0) AS guests,
            GREATEST(1, DATEDIFF(COALESCE(b.check_out_date, CURDATE()), COALESCE(b.check_in_date, CURDATE()))) AS total_nights,
            COALESCE(b.message, '') AS special_request,
            COALESCE(b.room_name, '-') AS room_name,
            p.order_id,
            p.payment_id,
            p.amount,
            p.currency,
            p.status AS payment_status,
            p.method AS payment_method,
            p.method AS payment_gateway,
            COALESCE(NULLIF(p.payment_id, ''), NULLIF(p.order_id, ''), CONCAT('PAY-', LPAD(p.id, 4, '0'))) AS transaction_id,
            p.gateway_response,
            p.invoice_id,
            COALESCE(p.invoice_number, b.invoice_number, '') AS invoice_number,
            COALESCE(b.invoice_file_path, '') AS invoice_file_path,
            COALESCE(b.email_status, 'Pending') AS email_status,
            CASE WHEN p.status = 'Paid' THEN p.updated_at ELSE NULL END AS paid_at,
            p.created_at,
            p.updated_at
         FROM payments p
         LEFT JOIN bookings b ON b.id = p.booking_id
         ORDER BY p.created_at DESC"
    );

    json_response(true, 'Payments loaded successfully.', 200, [
        'data' => $stmt->fetchAll(),
    ]);
} catch (Throwable $e) {
    error_log('Admin payments list error: ' . $e->getMessage());
    json_response(false, 'Unable to load payments.', 500);
}