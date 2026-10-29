<?php
declare(strict_types=1);
require_once __DIR__ . '/templates/booking-customizable-template.php';

/** Only guest receipts and confirmations use the editable templates. */
function tulip_booking_template_event(string $emailType): ?string
{
    return match ($emailType) {
        'booking_inquiry_received', 'booking_manual_received', 'booking_payment_pending_customer',
        'staying_guest_booking_received', 'staying_guest_manual_booking' => 'received',
        'booking_confirmed', 'staying_guest_booking_confirmed', 'payment_successful',
        'staying_guest_payment_successful', 'booking_status_updated', 'combined_status_updated' => 'confirmed',
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
        : booking_email_editor_validate(json_decode((string) $json, true, 32, JSON_THROW_ON_ERROR));
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
    // A payment receipt alone must never claim an unconfirmed reservation is confirmed.
    if ($event === 'confirmed' && $status !== 'confirmed') return null;
    if ($event === 'received' && !in_array($status, ['pending', 'confirmed'], true)) return null;
    $type = $status === 'confirmed' ? 'confirmed' : 'received';
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
    $stmt = $pdo->prepare('SELECT method, status FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$primaryId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $booking['payment_method'] = (string) ($payment['method'] ?? 'Not available');
    $booking['payment_status'] = (string) ($payment['status'] ?? $booking['payment_status'] ?? 'Payment Pending');
    if (str_starts_with($emailType, 'staying_guest_') && !empty($booking['is_booking_for_other'])) {
        $guestEmail = trim((string) ($booking['staying_guest_email'] ?? ''));
        if ($guestEmail === '' || strcasecmp($guestEmail, trim($recipient)) !== 0) return null;
        $booking['booked_by'] = ['name' => $booking['full_name'] ?? '', 'email' => $booking['email'] ?? '', 'phone' => $booking['phone'] ?? ''];
        $booking['full_name'] = trim((string) ($booking['staying_guest_name'] ?? '')) ?: 'Guest';
    }
    if (str_contains(strtolower($booking['payment_method']), 'payhere') && strtolower($booking['payment_status']) === 'payment pending') {
        $booking['payment_notice'] = 'Online payment is pending. Use the booking link to complete payment before your reservation hold expires.';
    }
    $brand = tulip_booking_email_brand($pdo, false);
    $content = tulip_saved_booking_template($pdo, $type);
    $url = latest_booking_bill_url($pdo, $primaryId);
    return tulip_render_booking_email($type, $content, $booking, $brand, $url);
}
