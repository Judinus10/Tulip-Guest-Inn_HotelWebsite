<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

apply_cors_headers();
require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Only GET requests are allowed.', 405);
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->query(
        "SELECT
            id,
            CONCAT('INQ-', LPAD(id, 5, '0')) AS inquiry_id,
            name,
            email,
            phone,
            subject,
            message,
            status,
            created_at,
            updated_at
         FROM enquiries
         ORDER BY created_at DESC"
    );

    json_response(true, 'Enquiries loaded successfully.', 200, [
        'data' => $stmt->fetchAll(),
    ]);
} catch (Throwable $e) {
    error_log('Enquiry list error: ' . $e->getMessage());
    json_response(false, 'Could not load enquiries.', 500);
}
