<?php
declare(strict_types=1);

require_once __DIR__ . '/_experience_helpers.php';

try {
    $pdo = experience_db();
    experience_ensure_schema($pdo);

    $stmt = $pdo->query("
        SELECT *
        FROM experience_items
        ORDER BY sort_order ASC, id DESC
    ");

    $items = array_map('experience_normalize', $stmt->fetchAll(PDO::FETCH_ASSOC));

    experience_json([
        'success' => true,
        'data' => $items,
    ]);
} catch (Throwable $e) {
    experience_json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}