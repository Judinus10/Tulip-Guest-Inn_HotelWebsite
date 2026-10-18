<?php
declare(strict_types=1);

require_once __DIR__ . '/_experience_helpers.php';

experience_require_method('POST');
require_admin_auth();

try {
    $pdo = experience_db();
    experience_ensure_schema($pdo);

    $title = clean_string($_POST['title'] ?? '', 180);
    $category = clean_string($_POST['category'] ?? '', 100);
    $location = clean_string($_POST['location'] ?? '', 180);
    $distance = clean_string($_POST['distance'] ?? '', 100);
    $duration = clean_string($_POST['duration'] ?? '', 100);
    $description = clean_string($_POST['description'] ?? '', 5000);
    $status = $_POST['status'] ?? 'active';
    if (!in_array($status, ['active','inactive'], true)) experience_json(['success'=>false,'message'=>'Invalid status.'], 422);
    $sortOrder = (int) ($_POST['sort_order'] ?? 1);

    if ($title === '' || $category === '' || $description === '') {
        experience_json([
            'success' => false,
            'message' => 'Title, category, and description are required.',
        ], 400);
    }

    $imagePath = experience_upload_image('image');

    $stmt = $pdo->prepare("
        INSERT INTO experience_items
        (title, category, location, distance, duration, description, image_path, status, sort_order)
        VALUES
        (:title, :category, :location, :distance, :duration, :description, :image_path, :status, :sort_order)
    ");

    $stmt->execute([
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

    experience_json([
        'success' => true,
        'message' => 'Experience item created successfully.',
    ]);
} catch (Throwable $e) {
    if (!empty($imagePath)) experience_delete_file($imagePath);
    experience_json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
