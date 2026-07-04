<?php
declare(strict_types=1);

require_once __DIR__ . '/_experience_helpers.php';

try {
    $pdo = experience_db();

    $id = (int) ($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $distance = trim($_POST['distance'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $sortOrder = (int) ($_POST['sort_order'] ?? 1);

    if ($id <= 0) {
        experience_json([
            'success' => false,
            'message' => 'Invalid experience item ID.',
        ], 400);
    }

    if ($title === '' || $category === '' || $description === '') {
        experience_json([
            'success' => false,
            'message' => 'Title, category, and description are required.',
        ], 400);
    }

    $oldStmt = $pdo->prepare("SELECT image_path FROM experience_items WHERE id = :id");
    $oldStmt->execute([':id' => $id]);
    $oldItem = $oldStmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldItem) {
        experience_json([
            'success' => false,
            'message' => 'Experience item not found.',
        ], 404);
    }

    $newImage = experience_upload_image('image');
    $imagePath = $newImage ?: $oldItem['image_path'];

    $stmt = $pdo->prepare("
        UPDATE experience_items
        SET
            title = :title,
            category = :category,
            location = :location,
            distance = :distance,
            description = :description,
            image_path = :image_path,
            status = :status,
            sort_order = :sort_order
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $id,
        ':title' => $title,
        ':category' => $category,
        ':location' => $location,
        ':distance' => $distance,
        ':description' => $description,
        ':image_path' => $imagePath,
        ':status' => $status,
        ':sort_order' => $sortOrder,
    ]);

    if ($newImage) {
        experience_delete_file($oldItem['image_path']);
    }

    experience_json([
        'success' => true,
        'message' => 'Experience item updated successfully.',
    ]);
} catch (Throwable $e) {
    experience_json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}