<?php
declare(strict_types=1);

require_once __DIR__ . '/_offer_helpers.php';

function offer_ensure_booking_snapshot_schema(PDO $pdo): void
{
    offer_ensure_schema($pdo);
    $definitions = [
        'subtotal_amount' => 'DECIMAL(12,2) NULL',
        'discount_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
        'applied_offer_id' => 'INT UNSIGNED NULL',
        'applied_offer_title' => 'VARCHAR(150) NULL',
        'offer_snapshot_json' => 'LONGTEXT NULL',
    ];
    foreach (['bookings', 'booking_groups'] as $table) {
        $columns = array_flip(array_column($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC), 'Field'));
        foreach ($definitions as $column => $definition) {
            if (!isset($columns[$column])) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            }
        }
    }
}

function offer_best_price(PDO $pdo, float $subtotal, string $checkInDate, int $nights, int $rooms, int $guests): array
{
    $scope = $rooms > 1 ? 'multi' : 'single';
    $stmt = $pdo->prepare(
        "SELECT * FROM offers
         WHERE status = 'active' AND automatic_apply = 1
           AND discount_value > 0
           AND (booking_scope = 'both' OR booking_scope = :scope)
           AND minimum_nights <= :nights AND minimum_rooms <= :rooms AND minimum_guests <= :guests
           AND (start_date IS NULL OR start_date <= :check_in_start)
           AND (end_date IS NULL OR end_date >= :check_in_end)
         ORDER BY priority DESC, id ASC"
    );
    // Native PDO MySQL prepares do not allow one named placeholder to be
    // reused. Keep separate bindings for the two date comparisons.
    $stmt->execute([
        ':scope' => $scope,
        ':nights' => $nights,
        ':rooms' => $rooms,
        ':guests' => $guests,
        ':check_in_start' => $checkInDate,
        ':check_in_end' => $checkInDate,
    ]);

    $best = null;
    $bestSaving = 0.0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $offer) {
        $saving = ($offer['discount_type'] ?? 'percentage') === 'fixed'
            ? (float) $offer['discount_value']
            : $subtotal * ((float) $offer['discount_value'] / 100);
        $saving = round(min($subtotal, max(0, $saving)), 2);
        if ($saving > $bestSaving) {
            $best = $offer;
            $bestSaving = $saving;
        }
    }
    return [
        'subtotal' => round($subtotal, 2),
        'discount' => $bestSaving,
        'total' => round($subtotal - $bestSaving, 2),
        'offer_id' => $best ? (int) $best['id'] : null,
        'offer_title' => $best ? (string) $best['title'] : null,
        'snapshot' => $best ? json_encode([
            'id'=>(int)$best['id'], 'title'=>$best['title'], 'discount_type'=>$best['discount_type'],
            'discount_value'=>(float)$best['discount_value'], 'booking_scope'=>$best['booking_scope'],
            'minimum_nights'=>(int)$best['minimum_nights'], 'minimum_rooms'=>(int)$best['minimum_rooms'],
            'minimum_guests'=>(int)$best['minimum_guests'],
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
    ];
}
