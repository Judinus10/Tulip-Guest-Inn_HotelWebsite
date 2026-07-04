<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/contact_helpers.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Only GET requests are allowed.', 405);
}

try {
    $pdo = get_db_connection();

    json_response(true, 'Contact settings loaded successfully.', 200, [
        'data' => get_contact_settings($pdo),
    ]);
} catch (Throwable $e) {
    error_log('Contact settings load error: ' . $e->getMessage());
    json_response(false, 'Could not load contact settings.', 500);
}