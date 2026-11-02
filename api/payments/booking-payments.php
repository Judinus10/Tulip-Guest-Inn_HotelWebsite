<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/booking-payment-ledger.php';
apply_cors_headers();
if ($_SERVER['REQUEST_METHOD']==='OPTIONS') { http_response_code(204); exit; }
require_admin_auth();
if (!in_array($_SERVER['REQUEST_METHOD']??'', ['GET','POST'],true)) json_response(false,'Method not allowed.',405);
try {
    $pdo=get_db_connection();
    ensure_booking_payment_ledger($pdo);
    $input=$_SERVER['REQUEST_METHOD']==='POST'?read_request_data():$_GET;
    $bookingId=filter_var($input['booking_id']??null,FILTER_VALIDATE_INT);
    if (!$bookingId || $bookingId<1) json_response(false,'Valid booking_id required.',422);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $raw=$input['amount']??null;
        if (!is_scalar($raw) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/',(string)$raw)) json_response(false,'Invalid payment amount.',422);
        $amount=(float)$raw;
        $method=(string)($input['method']??'');
        if (!in_array($method,['Cash','Bank Transfer','Card','Other'],true)) json_response(false,'Invalid offline payment method.',422);
        $note=trim((string)($input['note']??''));
        if (mb_strlen($note)>1000) json_response(false,'Note too long.',422);
        $pdo->beginTransaction();
        $q=$pdo->prepare('SELECT id, amount, currency, status, payment_status FROM bookings WHERE id=? FOR UPDATE');
        $q->execute([$bookingId]); $booking=$q->fetch(PDO::FETCH_ASSOC);
        if (!$booking) { $pdo->rollBack(); json_response(false,'Booking not found.',404); }
        if (strtolower((string)$booking['status'])==='cancelled') { $pdo->rollBack(); json_response(false,'Cannot collect payment for a cancelled booking.',409); }
        $totals=booking_payment_totals($pdo,$bookingId,(float)$booking['amount']);
        if ($amount<=0 || $amount>$totals['balance_due']+0.005) { $pdo->rollBack(); json_response(false,'Amount exceeds outstanding balance.',422); }
        $q=$pdo->prepare("INSERT INTO booking_payment_entries (booking_id,amount,currency,method,source,note) VALUES (?,?,?,?,'admin',?)");
        $q->execute([$bookingId,$amount,$booking['currency'],$method,$note]);
        $pdo->commit();
    }
    $q=$pdo->prepare('SELECT id,amount,currency FROM bookings WHERE id=?');
    $q->execute([$bookingId]); $booking=$q->fetch(PDO::FETCH_ASSOC);
    if (!$booking) json_response(false,'Booking not found.',404);
    $totals=booking_payment_totals($pdo,$bookingId,(float)$booking['amount']);
    $q=$pdo->prepare('SELECT id,amount,currency,method,source,external_reference,note,created_at,reversed_at FROM booking_payment_entries WHERE booking_id=? ORDER BY id DESC');
    $q->execute([$bookingId]);
    json_response(true,'Payment details loaded.',200,['payment_summary'=>$totals,'payment_history'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Booking ledger error: '.$e->getMessage());
    json_response(false,'Unable to process payment.',500);
}
