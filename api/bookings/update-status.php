<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mail/email-helper.php';
require_once __DIR__ . '/multi-room-helper.php';

apply_cors_headers();
require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

$data = read_request_data();
$id = (int) ($data['id'] ?? 0);
$status = strtolower(clean_string($data['status'] ?? '', 30));
$status = str_replace([' ', '-'], '_', $status);
if ($status === 'canceled') $status = 'cancelled';

$allowedStatuses = ['confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show'];
if ($id < 1 || !in_array($status, $allowedStatuses, true)) {
    json_response(false, 'Valid booking ID and action are required.', 422);
}

function calendar_status_key(mixed $value): string
{
    return str_replace([' ', '-'], '_', strtolower(trim((string) $value)));
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE');
    $stmt->execute([':id' => $id]);
    $booking = $stmt->fetch();
    if (!$booking) {
        $pdo->rollBack();
        json_response(false, 'Booking was not found.', 404);
    }

    $groupId = (int) ($booking['booking_group_id'] ?? 0);
    $primaryBookingId = $groupId > 0 ? multi_room_primary_booking_id($pdo, $groupId) : $id;
    $paymentStmt = $pdo->prepare('SELECT * FROM payments WHERE booking_id = :id ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $paymentStmt->execute([':id' => $primaryBookingId]);
    $payment = $paymentStmt->fetch() ?: [];

    $oldStatus = calendar_status_key($booking['status'] ?? 'Pending');
    $paymentStatus = (string) ($booking['payment_status'] ?? 'Payment Pending');
    $paymentKey = calendar_status_key(preg_replace('/^payment\s+/i', '', $paymentStatus) ?? $paymentStatus);
    $paymentMethod = trim((string) ($payment['method'] ?? ''));

    $transitions = [
        'pending' => ['confirmed', 'cancelled', 'no_show'],
        'confirmed' => ['checked_in', 'cancelled', 'no_show'],
        'checked_in' => ['checked_out'],
        'checked_out' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    if (!in_array($status, $transitions[$oldStatus] ?? [], true)) {
        $pdo->rollBack();
        json_response(false, 'This action is not allowed for the current booking status.', 409);
    }

    $isCashPayment = strcasecmp($paymentMethod, 'Cash') === 0;
    if ($status === 'confirmed' && $paymentKey !== 'paid' && !$isCashPayment) {
        $pdo->rollBack();
        json_response(false, 'Payment must be Paid before confirming the booking.', 409);
    }

    if ($status === 'no_show' && date('Y-m-d') < (string) ($booking['check_in_date'] ?? '')) {
        $pdo->rollBack();
        json_response(false, 'No Show is available only on or after the check-in date.', 409);
    }

    if ($status === 'confirmed') {
        $conflict = $pdo->prepare("SELECT id FROM bookings WHERE id <> :id AND room_name = :room_name AND status IN ('Confirmed','Checked In') AND :check_in < check_out_date AND :check_out > check_in_date LIMIT 1");
        $conflict->execute([
            ':id' => $id,
            ':room_name' => $booking['room_name'],
            ':check_in' => $booking['check_in_date'],
            ':check_out' => $booking['check_out_date'],
        ]);
        if ($conflict->fetch()) {
            $pdo->rollBack();
            json_response(false, 'Cannot confirm because this room has an overlapping active booking.', 409);
        }
    }

    $displayMap = [
        'confirmed' => 'Confirmed',
        'checked_in' => 'Checked In',
        'checked_out' => 'Checked Out',
        'cancelled' => 'Cancelled',
        'no_show' => 'No Show',
    ];
    $displayStatus = $displayMap[$status];
    $refundRequired = false;

    if ($status === 'no_show' && $paymentKey === 'paid') {
        if (strcasecmp($paymentMethod, 'PayHere') === 0) {
            $refundRequired = true;
        }
    } elseif ($status === 'cancelled' && !in_array($paymentKey, ['paid', 'refunded'], true)) {
        $paymentStatus = 'Cancelled';
        $paymentKey = 'cancelled';
    }

    if ($groupId > 0) {
        $update = $pdo->prepare('UPDATE bookings SET status = :status, payment_status = :payment_status, updated_at = NOW() WHERE booking_group_id = :group_id');
        $update->execute([':status' => $displayStatus, ':payment_status' => $paymentStatus, ':group_id' => $groupId]);
    } else {
        $update = $pdo->prepare('UPDATE bookings SET status = :status, payment_status = :payment_status, updated_at = NOW() WHERE id = :id');
        $update->execute([':status' => $displayStatus, ':payment_status' => $paymentStatus, ':id' => $id]);
    }

    if ($payment) {
        $paymentUpdate = $pdo->prepare('UPDATE payments SET status = :status, updated_at = NOW() WHERE id = :id');
        $paymentUpdate->execute([':status' => $paymentStatus, ':id' => (int) $payment['id']]);
    }

    $pdo->commit();
    $booking['status'] = $displayStatus;
    $booking['payment_status'] = $paymentStatus;
    if ($groupId > 0) {
        $groupRows = multi_room_group_rows($pdo, $groupId);
        $booking['booking_no'] = multi_room_booking_number($booking);
        $booking['room_name'] = implode(', ', array_column($groupRows, 'room_name'));
        $booking['guests'] = array_sum(array_map(static fn(array $row): int => (int) $row['guests'], $groupRows));
        $booking['amount'] = array_sum(array_map(static fn(array $row): float => (float) $row['amount'], $groupRows));
    }

    try {
        if ($status === 'confirmed') send_booking_confirmed_email($pdo, $booking);
        if ($status === 'cancelled') send_booking_cancelled_emails($pdo, $booking);
    } catch (Throwable $emailError) {
        error_log('Calendar status email error: ' . $emailError->getMessage());
    }

    $unexpectedOutput = ob_get_contents();
    if (is_string($unexpectedOutput) && trim($unexpectedOutput) !== '') {
        error_log('Discarded unexpected calendar status output: ' . trim($unexpectedOutput));
    }
    if (ob_get_level() > 0) ob_clean();

    $message = $refundRequired
        ? 'Booking marked No Show. PayHere payment remains Paid; complete the real refund before marking it Refunded.'
        : 'Booking status updated successfully.';

    json_response(true, $message, 200, ['data' => [
        'id' => $id,
        'booking_status' => $status,
        'payment_status' => $paymentStatus,
        'payment_method' => $paymentMethod,
        'refund_required' => $refundRequired,
    ]]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Admin calendar status update error: ' . $e->getMessage());
    if (ob_get_level() > 0) ob_clean();
    json_response(false, 'Unable to update booking status.', 500);
}
