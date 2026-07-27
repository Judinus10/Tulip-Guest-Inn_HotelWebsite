<?php
declare(strict_types=1);

require_once __DIR__ . '/_experience_helpers.php';

experience_require_method('POST');
require_admin_auth();

try {
    $pdo = experience_db();
    experience_ensure_schema($pdo);

    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $distance = trim($_POST['distance'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
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
    experience_json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
