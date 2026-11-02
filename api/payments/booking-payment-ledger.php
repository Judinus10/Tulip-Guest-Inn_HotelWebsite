<?php
declare(strict_types=1);

/** Idempotent payment-ledger schema for admin-manual and website Pay-on-Arrival bookings only. */
function ensure_booking_payment_ledger(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS booking_payment_entries (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        booking_id INT UNSIGNED NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        currency VARCHAR(10) NOT NULL DEFAULT 'LKR',
        method VARCHAR(60) NOT NULL,
        source VARCHAR(32) NOT NULL,
        external_reference VARCHAR(150) DEFAULT NULL,
        note VARCHAR(1000) DEFAULT NULL,
        recorded_by VARCHAR(150) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reversed_at DATETIME DEFAULT NULL,
        reversed_by VARCHAR(150) DEFAULT NULL,
        reversal_reason VARCHAR(1000) DEFAULT NULL,
        UNIQUE KEY uq_booking_source_reference (booking_id, source, external_reference),
        KEY idx_booking_entries (booking_id, reversed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function booking_payment_context(PDO $pdo, int $bookingId, bool $forUpdate = false): ?array {
    $sql = "SELECT b.id, b.booking_group_id, b.is_group_primary, b.amount, b.currency, b.status, b.payment_status, b.user_agent,
                   COALESCE((SELECT p.method FROM payments p
                     LEFT JOIN bookings pb ON pb.id=p.booking_id
                     WHERE p.booking_id=b.id OR (b.booking_group_id IS NOT NULL AND pb.booking_group_id=b.booking_group_id)
                     ORDER BY p.id DESC LIMIT 1), '') AS latest_payment_method,
                   COALESCE((SELECT p.order_id FROM payments p
                     LEFT JOIN bookings pb ON pb.id=p.booking_id
                     WHERE p.booking_id=b.id OR (b.booking_group_id IS NOT NULL AND pb.booking_group_id=b.booking_group_id)
                     ORDER BY p.id DESC LIMIT 1), '') AS latest_order_id
            FROM bookings b WHERE b.id=?" . ($forUpdate ? " FOR UPDATE" : "");
    $q=$pdo->prepare($sql); $q->execute([$bookingId]);
    $row=$q->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    if ((int)($row['booking_group_id'] ?? 0)>0 && !(bool)$row['is_group_primary']) {
        $q=$pdo->prepare("SELECT id FROM bookings WHERE booking_group_id=? AND is_group_primary=1 LIMIT 1");
        $q->execute([(int)$row['booking_group_id']]);
        $primary=(int)$q->fetchColumn();
        if ($primary>0 && $primary!==$bookingId) return booking_payment_context($pdo,$primary,$forUpdate);
    }
    $groupId=(int)($row['booking_group_id'] ?? 0);
    if ($groupId>0) {
        $q=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM bookings WHERE booking_group_id=?"); $q->execute([$groupId]);
        $row['tracking_total']=(float)$q->fetchColumn();
    } else $row['tracking_total']=(float)$row['amount'];
    $manual = trim((string)$row['user_agent']) === 'Admin manual reservation';
    $payOnArrival = !$manual && strcasecmp((string)$row['latest_payment_method'],'Cash')===0 && str_starts_with((string)$row['latest_order_id'],'CASH-');
    $row['payment_tracking_type']=$manual ? 'admin_manual' : ($payOnArrival ? 'website_pay_on_arrival' : '');
    $row['payment_tracking_eligible']=$manual || $payOnArrival;
    return $row;
}

function booking_payment_totals(PDO $pdo, int $bookingId, float $total): array {
    $q=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM booking_payment_entries WHERE booking_id=? AND reversed_at IS NULL");
    $q->execute([$bookingId]);
    $received=round((float)$q->fetchColumn(),2); $total=round($total,2);
    return ['total_amount'=>$total,'total_received'=>$received,'balance_due'=>round(max(0,$total-$received),2),
        'payment_state'=>$received<=0?'Payment Pending':($received+0.005<$total?'Partially Paid':'Paid')];
}

function booking_payment_seed_legacy(PDO $pdo, array $context): void {
    if (empty($context['payment_tracking_eligible'])) return;
    $bookingId=(int)$context['id'];
    $q=$pdo->prepare("SELECT COUNT(*) FROM booking_payment_entries WHERE booking_id=?"); $q->execute([$bookingId]);
    if ((int)$q->fetchColumn()>0) return;
    $q=$pdo->prepare("SELECT id, gateway_response FROM payments WHERE booking_id=? ORDER BY id DESC"); $q->execute([$bookingId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $payment) {
        $meta=json_decode((string)($payment['gateway_response']??''),true);
        $advance=is_array($meta)?(float)($meta['advance_payment']??0):0;
        if ($advance>0) {
            $i=$pdo->prepare("INSERT IGNORE INTO booking_payment_entries (booking_id,amount,currency,method,source,external_reference,note) VALUES (?,?,?,?,?,?,?)");
            $i->execute([$bookingId,$advance,$context['currency'] ?: 'LKR',$context['latest_payment_method'] ?: 'Manual','legacy_advance','payment-'.$payment['id'],'Migrated existing advance payment']);
            return;
        }
    }
    if (strcasecmp((string)$context['payment_status'],'Paid')===0 && (float)$context['tracking_total']>0) {
        $i=$pdo->prepare("INSERT IGNORE INTO booking_payment_entries (booking_id,amount,currency,method,source,external_reference,note) VALUES (?,?,?,?,?,?,?)");
        $i->execute([$bookingId,(float)$context['tracking_total'],$context['currency'] ?: 'LKR',$context['latest_payment_method'] ?: 'Manual','legacy_paid','booking-'.$bookingId,'Migrated existing paid status']);
    }
}
