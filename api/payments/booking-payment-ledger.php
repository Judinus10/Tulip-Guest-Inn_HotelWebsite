<?php
declare(strict_types=1);

/** Idempotent, on-demand payment ledger schema. No manual SQL import. */
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
        UNIQUE KEY uq_source_reference (source, external_reference),
        KEY idx_booking_entries (booking_id, reversed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function booking_payment_totals(PDO $pdo, int $bookingId, float $total): array {
    $q=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM booking_payment_entries WHERE booking_id=? AND reversed_at IS NULL");
    $q->execute([$bookingId]);
    $received=round((float)$q->fetchColumn(),2);
    return ['total_amount'=>round($total,2),'total_received'=>$received,
        'balance_due'=>round(max(0,$total-$received),2),
        'payment_state'=>$received<=0?'Payment Pending':($received+0.005<$total?'Partially Paid':'Paid')];
}
