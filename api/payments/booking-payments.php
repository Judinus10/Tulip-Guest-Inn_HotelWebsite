<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/booking-payment-ledger.php';
apply_cors_headers();
if ($_SERVER['REQUEST_METHOD']==='OPTIONS') { http_response_code(204); exit; }
require_admin_auth();
if (!in_array($_SERVER['REQUEST_METHOD']??'', ['GET','POST'],true)) json_response(false,'Method not allowed.',405);
try {
    $pdo=get_db_connection(); ensure_booking_payment_ledger($pdo);
    $input=$_SERVER['REQUEST_METHOD']==='POST'?read_request_data():$_GET;
    $bookingId=filter_var($input['booking_id']??null,FILTER_VALIDATE_INT);
    if (!$bookingId || $bookingId<1) json_response(false,'Valid booking_id required.',422);
    if ($_SERVER['REQUEST_METHOD']==='POST') $pdo->beginTransaction();
    $context=booking_payment_context($pdo,(int)$bookingId,$_SERVER['REQUEST_METHOD']==='POST');
    if (!$context) { if($pdo->inTransaction())$pdo->rollBack(); json_response(false,'Booking not found.',404); }
    $bookingId=(int)$context['id'];
    if (empty($context['payment_tracking_eligible'])) { if($pdo->inTransaction())$pdo->rollBack(); json_response(false,'Manual payment tracking is available only for admin manual reservations and website Pay on Arrival bookings.',403); }
    booking_payment_seed_legacy($pdo,$context);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (strtolower((string)$context['status'])==='cancelled') { $pdo->rollBack(); json_response(false,'Cannot record a payment for a cancelled booking.',409); }
        $raw=$input['amount']??null;
        if (!is_scalar($raw) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/',(string)$raw)) { $pdo->rollBack(); json_response(false,'Enter a valid payment amount.',422); }
        $amount=round((float)$raw,2); $method=clean_string($input['method']??'',60); $note=clean_string($input['note']??'',1000);
        if (!in_array($method,['Cash','Bank Transfer','Card','Other'],true)) { $pdo->rollBack(); json_response(false,'Invalid payment method.',422); }
        $totals=booking_payment_totals($pdo,$bookingId,(float)$context['tracking_total']);
        if ($amount<=0 || $amount>$totals['balance_due']+0.005) { $pdo->rollBack(); json_response(false,'Payment amount cannot exceed the remaining balance.',422); }
        $ref='ADMIN-'.date('YmdHis').'-'.$bookingId.'-'.bin2hex(random_bytes(3));
        $q=$pdo->prepare("INSERT INTO booking_payment_entries (booking_id,amount,currency,method,source,external_reference,note,recorded_by) VALUES (?,?,?,?,?,?,?,'admin')");
        $q->execute([$bookingId,$amount,$context['currency'] ?: 'LKR',$method,'admin',$ref,$note?:null]);
        $totals=booking_payment_totals($pdo,$bookingId,(float)$context['tracking_total']);
        $dbStatus=$totals['balance_due']<=0.005?'Paid':'Payment Pending';
        if ((int)($context['booking_group_id']??0)>0) {
            $q=$pdo->prepare("UPDATE bookings SET payment_status=?, updated_at=NOW() WHERE booking_group_id=?"); $q->execute([$dbStatus,(int)$context['booking_group_id']]);
        } else { $q=$pdo->prepare("UPDATE bookings SET payment_status=?, updated_at=NOW() WHERE id=?"); $q->execute([$dbStatus,$bookingId]); }
        $pdo->commit();
    }
    $context=booking_payment_context($pdo,$bookingId,false); booking_payment_seed_legacy($pdo,$context);
    $totals=booking_payment_totals($pdo,$bookingId,(float)$context['tracking_total']);
    $q=$pdo->prepare("SELECT id,amount,currency,method,source,external_reference,note,created_at FROM booking_payment_entries WHERE booking_id=? AND reversed_at IS NULL ORDER BY created_at DESC,id DESC"); $q->execute([$bookingId]);
    json_response(true,'Payment details loaded.',200,['payment_tracking_type'=>$context['payment_tracking_type'],'payment_summary'=>$totals,'payment_history'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Booking payment ledger error: '.$e->getMessage()); json_response(false,'Unable to process payment.',500);
}
