<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../calendar/ics-helper.php';
require_once __DIR__ . '/booking-audit-helper.php';
require_once __DIR__ . '/booking-expiry-helper.php';
require_once __DIR__ . '/multi-room-helper.php';
require_once __DIR__ . '/../offers/offer-pricing-helper.php';

apply_cors_headers();
require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);

$data = read_request_data();
$id = (int) ($data['id'] ?? 0);
$fullName = clean_string($data['full_name'] ?? '', 150);
$email = strtolower(clean_string($data['email'] ?? '', 190));
$phone = clean_string($data['phone'] ?? '', 50);
$checkInDate = clean_string($data['check_in_date'] ?? '', 20);
$checkOutDate = clean_string($data['check_out_date'] ?? '', 20);
$message = clean_string($data['message'] ?? '', 3000);
$requestedRooms = is_array($data['rooms'] ?? null) ? array_values($data['rooms']) : [];

if ($id < 1 || $fullName === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Please complete the guest name, valid email and phone number.', 422);
}
if (!is_valid_date($checkInDate) || !is_valid_date($checkOutDate) || $checkOutDate <= $checkInDate) {
    json_response(false, 'Check-out date must be after check-in date.', 422);
}
$today = new DateTimeImmutable('today');
$checkIn = new DateTimeImmutable($checkInDate);
$checkOut = new DateTimeImmutable($checkOutDate);
if ($checkIn <= $today) json_response(false, 'Check-in date must be after today.', 422);

$allocations = [];
foreach ($requestedRooms as $requestedRoom) {
    $roomId = (int) ($requestedRoom['room_id'] ?? 0);
    $guests = (int) ($requestedRoom['guests'] ?? 0);
    if ($roomId < 1 || $guests < 1 || isset($allocations[$roomId])) {
        json_response(false, 'Select each room once and allocate at least one guest to it.', 422);
    }
    $allocations[$roomId] = $guests;
}
if ($allocations === []) json_response(false, 'The booking must contain at least one room.', 422);

try {
    $pdo = get_db_connection();
    offer_ensure_booking_snapshot_schema($pdo);
    ensure_booking_audit_table($pdo);
    expire_pending_bookings($pdo, null, false);
    if (ics_enabled()) ensure_ics_schema($pdo);
    $pdo->beginTransaction();

    $primaryStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1 FOR UPDATE');
    $primaryStmt->execute([':id' => $id]);
    $primary = $primaryStmt->fetch();
    if (!$primary || (int) ($primary['booking_group_id'] ?? 0) < 1) {
        $pdo->rollBack();
        json_response(false, 'Multi-room booking was not found.', 404);
    }
    $groupId = (int) $primary['booking_group_id'];
    $statusKey = strtolower(str_replace([' ', '-'], '_', trim((string) ($primary['status'] ?? ''))));
    if (in_array($statusKey, ['checked_in', 'checked_out', 'cancelled', 'canceled', 'no_show'], true)) {
        $pdo->rollBack();
        json_response(false, 'This reservation can no longer be edited because it is cancelled or the stay has started.', 409);
    }

    $oldRows = multi_room_group_rows($pdo, $groupId);
    $oldTotal = array_sum(array_map(static fn(array $row): float => (float) ($row['amount'] ?? 0), $oldRows));
    $primaryBookingId = multi_room_primary_booking_id($pdo, $groupId);

    $roomIds = array_keys($allocations);
    $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
    $roomStmt = $pdo->prepare("SELECT id, room_name, max_guests, base_price, currency, status FROM rooms WHERE id IN ($placeholders) FOR UPDATE");
    $roomStmt->execute($roomIds);
    $selectedRooms = $roomStmt->fetchAll() ?: [];
    if (count($selectedRooms) !== count($roomIds)) {
        $pdo->rollBack();
        json_response(false, 'One or more selected rooms no longer exist.', 409);
    }
    $selectedById = [];
    foreach ($selectedRooms as $room) $selectedById[(int) $room['id']] = $room;

    $nights = max(1, (int) $checkIn->diff($checkOut)->days);
    $newTotal = 0.0;
    $totalGuests = 0;
    foreach ($allocations as $roomId => $guests) {
        $room = $selectedById[$roomId];
        $capacity = (int) ($room['max_guests'] ?? 0);
        if (strcasecmp((string) ($room['status'] ?? ''), 'Available') !== 0) {
            $pdo->rollBack();
            json_response(false, (string) $room['room_name'] . ' is not active.', 409);
        }
        if ($guests > $capacity) {
            $pdo->rollBack();
            json_response(false, (string) $room['room_name'] . ' allows a maximum of ' . $capacity . ' guests.', 422);
        }
        $conflictStmt = $pdo->prepare(
            "SELECT id FROM bookings
             WHERE (booking_group_id IS NULL OR booking_group_id <> :group_id)
               AND room_name = :room_name
               " . active_booking_conflict_sql() . "
               AND :check_in < check_out_date AND :check_out > check_in_date
             LIMIT 1 FOR UPDATE"
        );
        $conflictStmt->execute([
            ':group_id' => $groupId, ':room_name' => $room['room_name'],
            ':hold_cutoff' => booking_hold_cutoff_datetime(), ':check_in' => $checkInDate, ':check_out' => $checkOutDate,
        ]);
        if ($conflictStmt->fetch() || ics_room_conflict($pdo, $roomId, $checkInDate, $checkOutDate)) {
            $pdo->rollBack();
            json_response(false, (string) $room['room_name'] . ' is unavailable for the selected dates.', 409);
        }
        $newTotal += round((float) $room['base_price'] * $nights, 2);
        $totalGuests += $guests;
    }
    $subtotalAmount = round($newTotal, 2);
    $offerPrice = offer_best_price($pdo, $subtotalAmount, $checkInDate, $nights, count($allocations), $totalGuests);
    $newTotal = $offerPrice['total'];
    $roomPricing = [];
    $remainingDiscount = $offerPrice['discount'];
    $pricingIndex = 0;
    foreach ($allocations as $roomId => $guests) {
        $roomSubtotal = round((float) $selectedById[$roomId]['base_price'] * $nights, 2);
        $roomDiscount = $pricingIndex === count($allocations) - 1 ? $remainingDiscount : round($offerPrice['discount'] * ($roomSubtotal / max(.01, $subtotalAmount)), 2);
        $roomPricing[$roomId] = ['subtotal'=>$roomSubtotal, 'discount'=>$roomDiscount, 'total'=>round($roomSubtotal-$roomDiscount, 2)];
        $remainingDiscount = round($remainingDiscount-$roomDiscount, 2);
        $pricingIndex++;
    }

    $existingByRoomId = [];
    $existingById = [];
    foreach ($oldRows as $row) {
        $existingById[(int) $row['id']] = $row;
        if ((int) ($row['room_id'] ?? 0) > 0) $existingByRoomId[(int) $row['room_id']] = $row;
    }
    $assignments = [];
    $usedBookingIds = [];
    $selectedRoomOrder = array_keys($allocations);
    $primaryCurrentRoomId = (int) ($existingById[$primaryBookingId]['room_id'] ?? 0);

    if (!isset($allocations[$primaryCurrentRoomId])) {
        $firstRoomId = array_shift($selectedRoomOrder);
        $assignments[$firstRoomId] = $primaryBookingId;
        $usedBookingIds[$primaryBookingId] = true;
    }
    foreach ($selectedRoomOrder as $roomId) {
        $matching = $existingByRoomId[$roomId] ?? null;
        if ($matching && !isset($usedBookingIds[(int) $matching['id']])) {
            $assignments[$roomId] = (int) $matching['id'];
            $usedBookingIds[(int) $matching['id']] = true;
        }
    }
    foreach ($selectedRoomOrder as $roomId) {
        if (isset($assignments[$roomId])) continue;
        foreach ($oldRows as $oldRow) {
            $candidateId = (int) $oldRow['id'];
            if (!isset($usedBookingIds[$candidateId])) {
                $assignments[$roomId] = $candidateId;
                $usedBookingIds[$candidateId] = true;
                break;
            }
        }
    }

    $updateStmt = $pdo->prepare(
        'UPDATE bookings SET is_group_primary=:is_primary, full_name=:full_name, email=:email, phone=:phone,
         room_name=:room_name, check_in_date=:check_in, check_out_date=:check_out, guests=:guests,
         message=:message, subtotal_amount=:subtotal, discount_amount=:discount, applied_offer_id=:offer_id,
         applied_offer_title=:offer_title, offer_snapshot_json=:offer_snapshot, amount=:amount, currency=:currency, updated_at=NOW() WHERE id=:id'
    );
    $insertStmt = $pdo->prepare(
        "INSERT INTO bookings
        (booking_group_id, is_group_primary, full_name, email, phone, is_booking_for_other,
         staying_guest_name, staying_guest_email, staying_guest_phone, staying_guest_note,
         room_name, check_in_date, check_out_date, guests, message, status, payment_status,
         subtotal_amount, discount_amount, applied_offer_id, applied_offer_title, offer_snapshot_json,
         amount, currency, email_status, ip_address, user_agent, created_at, updated_at)
        VALUES (:group_id, 0, :full_name, :email, :phone, 0, NULL, NULL, NULL, NULL,
         :room_name, :check_in, :check_out, :guests, :message, :status, :payment_status,
         :subtotal, :discount, :offer_id, :offer_title, :offer_snapshot,
         :amount, :currency, 'Pending', :ip_address, :user_agent, NOW(), NOW())"
    );
    foreach ($allocations as $roomId => $guests) {
        $room = $selectedById[$roomId];
        $amount = $roomPricing[$roomId]['total'];
        $priceParams = [':subtotal'=>$roomPricing[$roomId]['subtotal'], ':discount'=>$roomPricing[$roomId]['discount'],
            ':offer_id'=>$offerPrice['offer_id'], ':offer_title'=>$offerPrice['offer_title'], ':offer_snapshot'=>$offerPrice['snapshot']];
        $bookingId = (int) ($assignments[$roomId] ?? 0);
        if ($bookingId > 0) {
            $updateStmt->execute($priceParams + [
                ':is_primary' => $bookingId === $primaryBookingId ? 1 : 0,
                ':full_name' => $fullName, ':email' => $email, ':phone' => $phone,
                ':room_name' => $room['room_name'], ':check_in' => $checkInDate, ':check_out' => $checkOutDate,
                ':guests' => $guests, ':message' => $message !== '' ? $message : null,
                ':amount' => $amount, ':currency' => $room['currency'] ?: ($primary['currency'] ?? 'LKR'), ':id' => $bookingId,
            ]);
        } else {
            $insertStmt->execute($priceParams + [
                ':group_id' => $groupId, ':full_name' => $fullName, ':email' => $email, ':phone' => $phone,
                ':room_name' => $room['room_name'], ':check_in' => $checkInDate, ':check_out' => $checkOutDate,
                ':guests' => $guests, ':message' => $message !== '' ? $message : null,
                ':status' => $primary['status'], ':payment_status' => $primary['payment_status'], ':amount' => $amount,
                ':currency' => $room['currency'] ?: ($primary['currency'] ?? 'LKR'),
                ':ip_address' => $primary['ip_address'] ?? get_client_ip(),
                ':user_agent' => $primary['user_agent'] ?? mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
            $usedBookingIds[(int) $pdo->lastInsertId()] = true;
        }
    }

    $removeIds = array_values(array_filter(array_keys($existingById), static fn(int $bookingId): bool => !isset($usedBookingIds[$bookingId])));
    if ($removeIds !== []) {
        $deletePlaceholders = implode(',', array_fill(0, count($removeIds), '?'));
        $pdo->prepare("DELETE FROM bookings WHERE id IN ($deletePlaceholders) AND booking_group_id = ?")
            ->execute([...$removeIds, $groupId]);
    }

    $pdo->prepare('UPDATE booking_groups SET primary_booking_id=:primary_id, total_guests=:guests, total_rooms=:rooms,
        subtotal_amount=:subtotal, discount_amount=:discount, applied_offer_id=:offer_id, applied_offer_title=:offer_title,
        offer_snapshot_json=:offer_snapshot, total_amount=:amount, updated_at=NOW() WHERE id=:id')
        ->execute([':primary_id'=>$primaryBookingId, ':guests'=>$totalGuests, ':rooms'=>count($allocations), ':subtotal'=>$offerPrice['subtotal'],
            ':discount'=>$offerPrice['discount'], ':offer_id'=>$offerPrice['offer_id'], ':offer_title'=>$offerPrice['offer_title'],
            ':offer_snapshot'=>$offerPrice['snapshot'], ':amount'=>$newTotal, ':id'=>$groupId]);

    $paymentStmt = $pdo->prepare('SELECT * FROM payments WHERE booking_id=:id ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $paymentStmt->execute([':id' => $primaryBookingId]);
    $payment = $paymentStmt->fetch() ?: [];
    $paymentStatus = strtolower(trim((string) ($payment['status'] ?? $primary['payment_status'] ?? '')));
    if ($payment && in_array($paymentStatus, ['payment pending', 'pending'], true)) {
        $pdo->prepare('UPDATE payments SET amount=:amount, updated_at=NOW() WHERE id=:id')
            ->execute([':amount' => $newTotal, ':id' => (int) $payment['id']]);
    }
    booking_audit_log($pdo, $primaryBookingId, 'group_booking_details_updated', 'Multi-room booking updated', 'Guest details, dates, rooms or guest allocations were edited.', [
        'booking_group_id' => $groupId, 'old_total' => $oldTotal, 'new_total' => $newTotal,
        'old_rooms' => array_map(static fn(array $row): int => (int) ($row['room_id'] ?? 0), $oldRows),
        'new_rooms' => $roomIds, 'total_guests' => $totalGuests,
    ]);
    $pdo->commit();

    $freshRows = multi_room_group_rows($pdo, $groupId);
    $freshPrimary = null;
    foreach ($freshRows as $freshRow) if ((int) $freshRow['id'] === $primaryBookingId) { $freshPrimary = $freshRow; break; }
    $freshPrimary ??= $freshRows[0] ?? $primary;
    $groupRooms = array_map(static fn(array $row): array => [
        'booking_id' => (int) $row['id'], 'room_id' => (int) ($row['room_id'] ?? 0),
        'room_name' => (string) $row['room_name'], 'guests' => (int) $row['guests'],
        'capacity' => (int) ($row['max_guests'] ?? 0), 'price_per_night' => (float) ($row['base_price'] ?? 0),
        'amount' => (float) $row['amount'],
    ], $freshRows);
    $delta = round($newTotal - $oldTotal, 2);
    $adjustmentType = $paymentStatus === 'paid' && $delta > 0 ? 'balance_due' : ($paymentStatus === 'paid' && $delta < 0 ? 'refund_due' : 'none');

    json_response(true, 'Multi-room booking updated successfully.', 200, [
        'data' => array_merge($freshPrimary, [
            'booking_group_id' => $groupId, 'booking_no' => multi_room_booking_number($freshPrimary),
            'full_name' => $fullName, 'guest_name' => $fullName, 'email' => $email, 'guest_email' => $email,
            'phone' => $phone, 'guest_phone' => $phone, 'check_in_date' => $checkInDate, 'check_out_date' => $checkOutDate,
            'guests' => $totalGuests, 'amount' => $newTotal, 'total_amount' => $newTotal,
            'room_name' => implode(', ', array_column($groupRooms, 'room_name')),
            'room_count' => count($groupRooms), 'group_rooms' => $groupRooms, 'message' => $message,
        ]),
        'adjustment' => ['type' => $adjustmentType, 'amount' => abs($delta), 'old_total' => $oldTotal, 'new_total' => $newTotal],
        'email_queued' => false,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Admin group booking update error: ' . $e->getMessage());
    json_response(false, 'The multi-room booking could not be updated. No changes were saved.', 500);
}
