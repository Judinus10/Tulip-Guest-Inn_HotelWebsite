<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/property_content_helpers.php';
apply_cors_headers();
require_admin_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
try {
    $content = save_property_content(get_db_connection(), read_request_data());
    json_response(true, 'Nearby places saved.', 200, ['data' => $content]);
} catch (Throwable $e) {
    error_log('Property content save error: ' . $e->getMessage());
    json_response(false, 'Could not save nearby places.', 500);
}
