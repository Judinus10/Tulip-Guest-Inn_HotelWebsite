<?php
declare(strict_types=1);

require_once __DIR__ . '/_offer_helpers.php';

try {
    $pdo = offer_bootstrap(true);

    $stmt = $pdo->query('SELECT * FROM offers ORDER BY sort_order ASC, id DESC');
    $offers = array_map('offer_normalize', $stmt->fetchAll(PDO::FETCH_ASSOC));

    offer_json([
        'success' => true,
        'data' => $offers,
    ]);
} catch (Throwable $e) {
    offer_json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
