<?php
declare(strict_types=1);
require_once __DIR__ . '/templates/booking-customizable-template.php';

/** Map supported guest and hotel booking notifications to their editable templates. */
function tulip_booking_template_event(string $emailType): ?string
{
    return match ($emailType) {
        'admin_new_booking', 'admin_manual_booking' => 'admin_new_booking',
        'booking_payment_pending_admin' => 'admin_payment_pending',
        'booking_expired_admin' => 'admin_booking_expired',
        'admin_booking_cancelled' => 'admin_booking_cancelled',
        'booking_payment_pending_customer' => 'payment_pending',
        'payment_failed' => 'payment_failed',
        'booking_expired_customer' => 'expired',
        'booking_cancelled' => 'cancelled',
        'payment_status_updated', 'booking_status_updated', 'combined_status_updated' => 'status_updated',
        'admin_payment_received' => 'admin_payment_received',
        'admin_payment_failed' => 'admin_payment_failed',
        'booking_inquiry_received', 'booking_manual_received',
        'staying_guest_booking_received', 'staying_guest_manual_booking' => 'received',
        'booking_confirmed', 'staying_guest_booking_confirmed', 'payment_successful',
        'staying_guest_payment_successful' => 'confirmed',
        default => null,
    };
}

function tulip_saved_booking_template(PDO $pdo, string $type): array
{
    try {
        $stmt = $pdo->prepare('SELECT content_json FROM website_booking_email_templates WHERE template_key = ?');
        $stmt->execute([$type]);
        $json = $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Delivery must not run CREATE TABLE during a booking transaction.
        if ((int) ($e->errorInfo[1] ?? 0) !== 1146) throw $e;
        $json = false;
    }
    return $json === false ? booking_email_editor_defaults()[$type]
        : booking_email_editor_validate(json_decode((string) $json, true, 32, JSON_THROW_ON_ERROR), $type);
}

function tulip_booking_email_for_queue(PDO $pdo, ?string $relatedType, ?int $bookingId, string $recipient, string $emailType): ?array
{
    $event = tulip_booking_template_event($emailType);
    if ($relatedType !== 'booking' || !$bookingId || !$event) return null;
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ? LIMIT 1');
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) return null;
    $status = strtolower(trim((string) ($booking['status'] ?? '')));
    $isAdmin = str_starts_with($event, 'admin_');
    // A payment receipt alone must never claim an unconfirmed reservation is confirmed.
    if (!$isAdmin && $event === 'confirmed' && $status !== 'confirmed'
        && !in_array($emailType, ['payment_successful','staying_guest_payment_successful'], true)) return null;
    if (!$isAdmin && $event === 'received' && !in_array($status, ['pending', 'confirmed'], true)) return null;
    $type = $event;
    if (!$isAdmin && $event === 'received') $type = $status === 'confirmed' ? 'confirmed' : 'received';
    $groupId = (int) ($booking['booking_group_id'] ?? 0);
    $primaryId = $bookingId;
    if ($groupId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM booking_groups WHERE id = ? LIMIT 1');
        $stmt->execute([$groupId]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$group) throw new RuntimeException('Booking group details are missing.');
        $booking['booking_no'] = trim((string) ($group['booking_no'] ?? '')) ?: 'MB-' . str_pad((string) $groupId, 6, '0', STR_PAD_LEFT);
        $booking['amount'] = (float) $group['total_amount'];
        $booking['currency'] = $group['currency'] ?? $booking['currency'];
        $stmt = $pdo->prepare('SELECT id FROM bookings WHERE booking_group_id = ? ORDER BY is_group_primary DESC, id ASC LIMIT 1');
        $stmt->execute([$groupId]);
        $primaryId = (int) $stmt->fetchColumn();
    } else {
        // Match the public booking bill's reference format.
        $booking['booking_no'] = 'BK-' . str_pad((string) $bookingId, 5, '0', STR_PAD_LEFT);
    }
    $rooms = booking_guest_rooms($pdo, $bookingId, $groupId);
    $booking = array_merge($booking, booking_guest_totals($rooms), ['room_assignments' => $rooms]);
    $stmt = $pdo->prepare('SELECT method, status, amount, currency, order_id, payment_id FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$primaryId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $booking['payment_method'] = (string) ($payment['method'] ?? 'Not available');
    $booking['payment_status'] = (string) ($payment['status'] ?? $booking['payment_status'] ?? 'Payment Pending');
    $paymentState = strtolower(trim($booking['payment_status']));
    $booking['payment_amount'] = $payment['amount'] ?? null;
    $booking['payment_currency'] = $payment['currency'] ?? $booking['currency'] ?? 'LKR';
    $booking['transaction_id'] = trim((string) ($payment['payment_id'] ?? '')) ?: (string) ($payment['order_id'] ?? '');
    if (in_array($emailType, ['payment_successful','staying_guest_payment_successful'], true)) {
        if ($paymentState !== 'paid') return null;
        $type = $status === 'confirmed' ? 'confirmed' : 'payment_received';
    }
    if ($event === 'status_updated') {
        // Booking termination takes priority over payment updates. Paid is not a refund.
        $type = match (true) {
            in_array($status, ['cancelled','canceled'], true) => 'cancelled',
            $status === 'expired' => 'expired',
            $emailType !== 'booking_status_updated' && $paymentState === 'failed' => 'payment_failed',
            $emailType !== 'payment_status_updated' && $status === 'confirmed' => 'confirmed',
            $emailType !== 'booking_status_updated' && $paymentState === 'paid' => 'payment_received',
            default => 'status_updated',
        };
    }
    if (in_array($event, ['payment_pending','admin_payment_pending'], true)
        && ($status !== 'pending' || !in_array($paymentState, ['payment pending','pending'], true))) return null;
    if ($event === 'payment_failed' && $paymentState !== 'failed') return null;
    // Legacy expiry stores Cancelled; the expiry event carries the more precise reason.
    if (in_array($event, ['expired','admin_booking_expired'], true)
        && !in_array($status, ['expired','cancelled','canceled'], true)) return null;
    if (in_array($event, ['cancelled','admin_booking_cancelled'], true)
        && !in_array($status, ['cancelled','canceled'], true)) return null;

    if ($isAdmin) {
        if ($event === 'admin_payment_received' && strtolower($booking['payment_status']) !== 'paid') return null;
        if ($event === 'admin_payment_failed' && strtolower($booking['payment_status']) !== 'failed') return null;
    }
    if (str_starts_with($emailType, 'staying_guest_') && !empty($booking['is_booking_for_other'])) {
        $guestEmail = trim((string) ($booking['staying_guest_email'] ?? ''));
        if ($guestEmail === '' || strcasecmp($guestEmail, trim($recipient)) !== 0) return null;
        $booking['booked_by'] = ['name' => $booking['full_name'] ?? '', 'email' => $booking['email'] ?? '', 'phone' => $booking['phone'] ?? ''];
        $booking['full_name'] = trim((string) ($booking['staying_guest_name'] ?? '')) ?: 'Guest';
    }
    if (!$isAdmin && $status === 'pending' && str_contains(strtolower($booking['payment_method']), 'payhere') && $paymentState === 'payment pending') {
        $booking['payment_notice'] = 'Online payment is pending. Use the booking link to complete payment before your reservation hold expires.';
    }
    if (!$isAdmin && in_array(strtolower($booking['payment_method']), ['cash','cash on arrival','pay on arrival'], true)
        && in_array($paymentState, ['pending','payment pending'], true)) {
        $booking['payment_notice'] = 'Payment is due on arrival. No online payment is required for this payment method.';
    }
    if (in_array($type, ['expired','admin_booking_expired'], true)) $booking['status'] = 'Expired';
    if ($type === 'payment_received' && $status === 'pending') {
        $booking['payment_notice'] = 'Your payment has been recorded. Your reservation is awaiting confirmation.';
    }
    $brand = tulip_booking_email_brand($pdo, false);
    $content = tulip_saved_booking_template($pdo, $type);
    $url = $isAdmin ? (defined('ADMIN_APP_URL') && preg_match('~^https?://~i', (string) ADMIN_APP_URL) ? rtrim((string) ADMIN_APP_URL, '/') . '/bookings' : '') : latest_booking_bill_url($pdo, $primaryId);
    return tulip_render_booking_email($type, $content, $booking, $brand, $url);
}
