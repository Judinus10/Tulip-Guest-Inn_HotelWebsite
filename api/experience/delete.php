<?php
declare(strict_types=1);

require_once __DIR__ . '/_experience_helpers.php';

experience_require_method('DELETE');
require_admin_auth();

try {
    $pdo = experience_db();

    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int) ($input['id'] ?? 0);

    if ($id <= 0) {
        experience_json([
            'success' => false,
            'message' => 'Invalid experience item ID.',
        ], 400);
    }

    $stmt = $pdo->prepare("SELECT image_path FROM experience_items WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        experience_json([
            'success' => false,
            'message' => 'Experience item not found.',
        ], 404);
    }

    $delete = $pdo->prepare("DELETE FROM experience_items WHERE id = :id");
    $delete->execute([':id' => $id]);

    experience_delete_file($item['image_path']);

    experience_json([
        'success' => true,
        'message' => 'Experience item deleted successfully.',
    ]);
} catch (Throwable $e) {
    experience_json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
