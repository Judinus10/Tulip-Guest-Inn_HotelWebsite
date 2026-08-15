<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mail/email-helper.php';
require_once __DIR__ . '/bookings/booking-expiry-helper.php';
require_once __DIR__ . '/calendar/ics-helper.php';

apply_cors_headers();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
rate_limit_or_fail('submit_multi_room_booking', 4, 15);

$data = read_request_data();
$fullName = clean_string($data['full_name'] ?? '', 150);
$email = strtolower(clean_string($data['email'] ?? '', 190));
$phone = clean_string($data['phone'] ?? '', 50);
$checkInDate = clean_string($data['check_in_date'] ?? '', 20);
$checkOutDate = clean_string($data['check_out_date'] ?? '', 20);
$totalGuests = (int) ($data['total_guests'] ?? 0);
$message = clean_string($data['message'] ?? '', 3000);
$paymentMethod = strtolower(clean_string($data['payment_method'] ?? 'Cash', 30));
$requestedRooms = is_array($data['rooms'] ?? null) ? array_values($data['rooms']) : [];
$isCash = in_array($paymentMethod, ['cash', 'pay on arrival'], true);
$isOnline = in_array($paymentMethod, ['payhere', 'online', 'pay online'], true);

if (!$isCash && !$isOnline) json_response(false, 'Select a valid payment method.', 422);
if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') json_response(false, 'Name, valid email and phone are required.', 422);
if (!is_valid_date($checkInDate) || !is_valid_date($checkOutDate) || $checkOutDate <= $checkInDate) json_response(false, 'Enter valid stay dates.', 422);
if ($totalGuests < 2 || $totalGuests > 20 || count($requestedRooms) < 2 || count($requestedRooms) > 6) json_response(false, 'Select between 2 and 6 rooms and a valid guest count.', 422);

$allocations = []; $allocatedTotal = 0;
foreach ($requestedRooms as $item) {
    $roomId = (int) ($item['room_id'] ?? 0); $guests = (int) ($item['guests'] ?? 0);
    if ($roomId < 1 || $guests < 1 || isset($allocations[$roomId])) json_response(false, 'Invalid or duplicate room selection.', 422);
    $allocations[$roomId] = $guests; $allocatedTotal += $guests;
}
if ($allocatedTotal !== $totalGuests) json_response(false, 'Guest allocation must equal the total number of guests.', 422);

$pdo = null;
try {
    $pdo = get_db_connection(); expire_pending_bookings($pdo, null, false); ensure_ics_schema($pdo);
    try {
        // Read-only check: customer requests must never create or alter tables.
        $pdo->query('SELECT id, booking_no FROM booking_groups LIMIT 0');
        $pdo->query('SELECT booking_group_id, is_group_primary FROM bookings LIMIT 0');
    } catch (Throwable $schemaError) {
        error_log('Multiple-room migration missing: ' . $schemaError->getMessage());
        json_response(false, 'Multiple-room booking is temporarily unavailable because its database migration has not been installed.', 503);
    }

    $pdo->beginTransaction();
    $ids = array_keys($allocations); $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, room_name, max_guests, base_price, currency, status FROM rooms WHERE id IN ($marks) FOR UPDATE");
    $stmt->execute($ids); $rooms = $stmt->fetchAll() ?: [];
    if (count($rooms) !== count($ids)) throw new RuntimeException('One or more rooms no longer exist.');
    $nights = max(1, (int) (new DateTimeImmutable($checkInDate))->diff(new DateTimeImmutable($checkOutDate))->days);
    $totalAmount = 0.0; $currency = PAYMENT_CURRENCY;
    foreach ($rooms as &$room) {
        $id = (int) $room['id']; $roomGuests = $allocations[$id] ?? 0;
        if (($room['status'] ?? '') !== 'Available' || $roomGuests > (int) $room['max_guests']) throw new RuntimeException('A selected room cannot hold its allocated guests.');
        $conflict = $pdo->prepare("SELECT id FROM bookings WHERE room_name=:room_name " . active_booking_conflict_sql() . " AND :cin < check_out_date AND :cout > check_in_date LIMIT 1 FOR UPDATE");
        $conflict->execute([':room_name'=>$room['room_name'],':hold_cutoff'=>booking_hold_cutoff_datetime(),':cin'=>$checkInDate,':cout'=>$checkOutDate]);
        if ($conflict->fetch() || ics_room_conflict($pdo, $id, $checkInDate, $checkOutDate)) throw new RuntimeException('A selected room is no longer available. Search again.');
        $room['allocated_guests']=$roomGuests; $room['room_total']=round((float)$room['base_price']*$nights,2); $totalAmount += $room['room_total']; $currency=(string)($room['currency']?:$currency);
    }
    unset($room);
    $group=$pdo->prepare('INSERT INTO booking_groups(total_guests,total_rooms,total_amount,currency,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW())');
    $group->execute([$totalGuests,count($rooms),$totalAmount,$currency]); $groupId=(int)$pdo->lastInsertId();
    $bookingNo='MB-'.str_pad((string)$groupId,6,'0',STR_PAD_LEFT); $bookingIds=[];
    foreach ($rooms as $index=>$room) {
        $insert=$pdo->prepare("INSERT INTO bookings(booking_group_id,is_group_primary,full_name,email,phone,room_name,check_in_date,check_out_date,guests,message,status,payment_status,amount,currency,ip_address,user_agent,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?, 'Pending','Payment Pending',?,?,?,?,NOW(),NOW())");
        $insert->execute([$groupId,$index===0?1:0,$fullName,$email,$phone,$room['room_name'],$checkInDate,$checkOutDate,$room['allocated_guests'],$message,$room['room_total'],$currency,get_client_ip(),mb_substr($_SERVER['HTTP_USER_AGENT']??'',0,255)]);
        $bookingIds[]=(int)$pdo->lastInsertId();
    }
    $primaryId=$bookingIds[0]; $pdo->prepare('UPDATE booking_groups SET booking_no=?,primary_booking_id=? WHERE id=?')->execute([$bookingNo,$primaryId,$groupId]);
    $billUrl=null; $orderId=null;
    if ($isCash) {
        $orderId='CASH-'.$bookingNo;
        $cashPayment = $pdo->prepare("INSERT INTO payments(booking_id,order_id,amount,currency,status,method,created_at,updated_at) VALUES(?,?,?,?,'Payment Pending','Cash',NOW(),NOW())");
        foreach ($bookingIds as $index => $groupBookingId) {
            $roomOrderId = $index === 0 ? $orderId : $orderId . '-R' . ($index + 1);
            $cashPayment->execute([$groupBookingId, $roomOrderId, $index === 0 ? $totalAmount : (float) $rooms[$index]['room_total'], $currency]);
        }
    }
    $pdo->commit();
    if ($isCash) {
        $amountToken=number_format($totalAmount,2,'.',''); $token=create_public_token('booking-status',['order_id'=>$orderId,'booking_id'=>$primaryId,'amount'=>$amountToken],BOOKING_LINK_TTL_SECONDS);
        $base=defined('FRONTEND_URL')&&FRONTEND_URL!==''?FRONTEND_URL:(defined('PUBLIC_APP_URL')?PUBLIC_APP_URL:APP_BASE_URL);
        $billUrl=rtrim((string)$base,'/').'/booking-bill?'.http_build_query(['booking_id'=>$primaryId,'order_id'=>$orderId,'token'=>$token]);
        try { queue_booking_received_emails($pdo,['id'=>$primaryId,'booking_no'=>$bookingNo,'full_name'=>$fullName,'email'=>$email,'phone'=>$phone,'room_name'=>implode(', ',array_column($rooms,'room_name')),'check_in_date'=>$checkInDate,'check_out_date'=>$checkOutDate,'guests'=>$totalGuests,'rooms'=>count($rooms),'status'=>'Pending','payment_status'=>'Payment Pending','payment_method'=>'Cash','amount'=>$totalAmount,'currency'=>$currency]); } catch(Throwable $mailError){ error_log('Multi-room booking email queue error: '.$mailError->getMessage()); }
    }
    json_response(true,'Multi-room booking created.',201,['booking_id'=>$primaryId,'booking_no'=>$bookingNo,'booking_group_id'=>$groupId,'bill_url'=>$billUrl,'requires_online_checkout'=>$isOnline]);
} catch(Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Multi-room booking error: '.$e->getMessage());
    json_response(false,APP_ENV==='local'?$e->getMessage():'Unable to create the multi-room booking.',409);
}
