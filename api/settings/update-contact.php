<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/contact_helpers.php';

apply_cors_headers();
require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

try {
    $pdo = get_db_connection();
    $settings = save_contact_settings($pdo, read_request_data());

    json_response(true, 'Contact settings saved successfully.', 200, [
        'data' => $settings,
    ]);
} catch (Throwable $e) {
    error_log('Contact settings save error: ' . $e->getMessage());
    json_response(false, 'Could not save contact settings.', 500);
}