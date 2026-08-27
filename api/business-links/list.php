<?php
declare(strict_types=1);
require_once __DIR__ . '/_business_links_helpers.php';
apply_cors_headers();
require_mail_super_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(false, 'Only GET requests are allowed.', 405);

try {
    $pdo = get_db_connection();
    ensure_business_links_table($pdo);
    $rows = $pdo->query('SELECT * FROM external_portal_links ORDER BY is_enabled DESC, title')->fetchAll(PDO::FETCH_ASSOC);
    json_response(true, 'Business pages loaded.', 200, ['data' => array_map('public_business_link', $rows)]);
} catch (Throwable $e) {
    error_log('Business links list failed: ' . $e->getMessage());
    json_response(false, 'Business pages could not be loaded. Run the Step 2 database migration and try again.', 500);
}
