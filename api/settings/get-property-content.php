<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/property_content_helpers.php';
apply_cors_headers();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(false, 'Only GET requests are allowed.', 405);
try {
    json_response(true, 'Property content loaded.', 200, ['data' => get_property_content(get_db_connection())]);
} catch (Throwable $e) {
    error_log('Property content load error: ' . $e->getMessage());
    json_response(false, 'Could not load property content.', 500);
}
