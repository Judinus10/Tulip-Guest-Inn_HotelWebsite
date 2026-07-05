<?php
declare(strict_types=1);

require_once __DIR__ . '/_offer_helpers.php';

try {
    $pdo = offer_bootstrap(true);
    $data = read_request_data();
    $id = (int) ($data['id'] ?? 0);

    if ($id <= 0) {
        offer_json(['success' => false, 'message' => 'Offer ID is required.'], 400);
    }

    $stmt = $pdo->prepare('DELETE FROM offers WHERE id = :id');
    $stmt->execute([':id' => $id]);

    offer_json([
        'success' => true,
        'message' => 'Offer deleted successfully.',
    ]);
} catch (Throwable $e) {
    offer_json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
