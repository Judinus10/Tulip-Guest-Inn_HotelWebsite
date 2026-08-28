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
            COALESCE(b.booking_group_id, 0) AS booking_group_id,
            CASE
                WHEN COALESCE(b.booking_group_id, 0) > 0
                    THEN COALESCE(NULLIF(bg.booking_no, ''), CONCAT('MB-', LPAD(b.booking_group_id, 6, '0')))
                ELSE CONCAT('BK-', LPAD(COALESCE(p.booking_id, 0), 5, '0'))
            END AS booking_no,
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
            CASE WHEN COALESCE(b.booking_group_id, 0) > 0 THEN COALESCE(bg.total_guests, 0) ELSE COALESCE(b.guests, 0) END AS guests,
            CASE WHEN COALESCE(b.booking_group_id, 0) > 0 THEN COALESCE(bg.total_rooms, 0) ELSE 1 END AS total_rooms,
            GREATEST(1, DATEDIFF(COALESCE(b.check_out_date, CURDATE()), COALESCE(b.check_in_date, CURDATE()))) AS total_nights,
            COALESCE(b.message, '') AS special_request,
            CASE
                WHEN COALESCE(b.booking_group_id, 0) > 0 THEN COALESCE((
                    SELECT GROUP_CONCAT(group_booking.room_name ORDER BY group_booking.is_group_primary DESC, group_booking.id ASC SEPARATOR ', ')
                    FROM bookings group_booking
                    WHERE group_booking.booking_group_id = b.booking_group_id
                ), '-')
                ELSE COALESCE(b.room_name, '-')
            END AS room_name,
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
         LEFT JOIN booking_groups bg ON bg.id = b.booking_group_id
         ORDER BY p.created_at DESC"
    );

    $payments = $stmt->fetchAll() ?: [];
    $groupIds = array_values(array_unique(array_filter(array_map(
        static fn(array $payment): int => (int) ($payment['booking_group_id'] ?? 0),
        $payments
    ))));
    $roomsByGroup = [];

    if ($groupIds !== []) {
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $roomStmt = $pdo->prepare(
            "SELECT booking_group_id, id AS booking_id, room_name, guests, amount, currency, is_group_primary
             FROM bookings
             WHERE booking_group_id IN ($placeholders)
             ORDER BY booking_group_id ASC, is_group_primary DESC, id ASC"
        );
        $roomStmt->execute($groupIds);

        foreach ($roomStmt->fetchAll() ?: [] as $room) {
            $roomsByGroup[(int) $room['booking_group_id']][] = [
                'booking_id' => (int) $room['booking_id'],
                'room_name' => (string) $room['room_name'],
                'guests' => (int) $room['guests'],
                'amount' => (float) $room['amount'],
                'currency' => (string) $room['currency'],
                'is_primary' => (bool) $room['is_group_primary'],
            ];
        }
    }

    foreach ($payments as &$payment) {
        $groupId = (int) ($payment['booking_group_id'] ?? 0);
        $payment['group_rooms'] = $groupId > 0 ? ($roomsByGroup[$groupId] ?? []) : [];
    }
    unset($payment);

    json_response(true, 'Payments loaded successfully.', 200, [
        'data' => $payments,
    ]);
} catch (Throwable $e) {
    error_log('Admin payments list error: ' . $e->getMessage());
    json_response(false, 'Unable to load payments.', 500);
}
