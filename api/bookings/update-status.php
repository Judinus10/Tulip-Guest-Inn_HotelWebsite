<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mail/email-helper.php';

apply_cors_headers();
require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

$data = read_request_data();
$id = (int) ($data['id'] ?? 0);
$status = strtolower(clean_string($data['status'] ?? '', 30));
$allowedStatuses = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'canceled', 'no_show'];

if ($status === 'canceled') {
    $status = 'cancelled';
}

if ($id < 1 || !in_array($status, $allowedStatuses, true)) {
    json_response(false, 'Valid booking ID and status are required.', 422);
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        json_response(false, 'Booking was not found.', 404);
    }

    $oldStatus = strtolower((string) ($booking['status'] ?? ''));

    if ($oldStatus === $status) {
        json_response(true, 'Booking status is already updated.', 200, [
            'data' => [
                'id' => $id,
                'booking_status' => $status,
            ],
        ]);
    }

    if ($status === 'confirmed' && (string) ($booking['payment_status'] ?? '') !== 'Paid') {
        json_response(false, 'Confirmed status is locked until PayHere verifies the payment as Paid.', 403);
    }

    if ($status === 'confirmed') {
        $conflict = $pdo->prepare(
            "SELECT id
             FROM bookings
             WHERE id <> :id
               AND room_name = :room_name
               AND status = 'Confirmed'
               AND :requested_check_in < check_out_date
               AND :requested_check_out > check_in_date
             LIMIT 1"
        );
        $conflict->execute([
            ':id' => $id,
            ':room_name' => $booking['room_name'],
            ':requested_check_in' => $booking['check_in_date'],
            ':requested_check_out' => $booking['check_out_date'],
        ]);

        if ($conflict->fetch()) {
            json_response(false, 'Cannot confirm this booking because the room is already confirmed for overlapping dates.', 409);
        }
    }

    $displayStatusMap = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'checked_in' => 'Checked In',
        'checked_out' => 'Checked Out',
        'cancelled' => 'Cancelled',
        'no_show' => 'No Show',
    ];
    $displayStatus = $displayStatusMap[$status] ?? ucfirst($status);

    $update = $pdo->prepare('UPDATE bookings SET status = :status, updated_at = NOW() WHERE id = :id');
    $update->execute([
        ':status' => $displayStatus,
        ':id' => $id,
    ]);

    $booking['status'] = $displayStatus;

    try {
        if ($status === 'confirmed') {
            send_booking_confirmed_email($pdo, $booking);
        }

        if ($status === 'cancelled') {
            send_booking_cancelled_emails($pdo, $booking);
        }
    } catch (Throwable $emailError) {
        error_log('Booking status email error: ' . $emailError->getMessage());
    }

    json_response(true, 'Booking status updated successfully.', 200, [
        'data' => [
            'id' => $id,
            'booking_status' => $status,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Admin update booking status error: ' . $e->getMessage());
    json_response(false, 'Unable to update booking status.', 500);
}