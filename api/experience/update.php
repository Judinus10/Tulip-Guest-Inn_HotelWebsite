<?php
declare(strict_types=1);

require_once __DIR__ . '/_experience_helpers.php';

experience_require_method('POST');
require_admin_auth();

try {
    $pdo = experience_db();
    experience_ensure_schema($pdo);

    $id = (int) ($_POST['id'] ?? 0);
    $title = clean_string($_POST['title'] ?? '', 180);
    $category = clean_string($_POST['category'] ?? '', 100);
    $location = clean_string($_POST['location'] ?? '', 180);
    $distance = clean_string($_POST['distance'] ?? '', 100);
    $duration = clean_string($_POST['duration'] ?? '', 100);
    $description = clean_string($_POST['description'] ?? '', 5000);
    $status = $_POST['status'] ?? 'active';
    if (!in_array($status, ['active','inactive'], true)) experience_json(['success'=>false,'message'=>'Invalid status.'], 422);
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
            duration = :duration,
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
        ':duration' => $duration,
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
    if (!empty($newImage)) experience_delete_file($newImage);
    experience_json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
