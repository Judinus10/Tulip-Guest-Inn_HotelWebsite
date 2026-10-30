<?php
declare(strict_types=1);
require_once __DIR__ . '/_room_helpers.php';
apply_cors_headers();
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(false, 'Only GET requests are allowed.', 405);
rate_limit_or_fail('public_occupancy_policy', 120, 15);
try {
    $pdo = get_db_connection();
    ensure_rooms_schema($pdo);
    json_response(true, 'Guest age policy loaded.', 200, ['data' => [
        'max_child_age' => room_max_child_age_setting($pdo),
        'count_children_toward_capacity' => room_count_children_setting($pdo),
    ]]);
} catch (Throwable $e) {
    error_log('Public guest age policy: ' . $e->getMessage());
    json_response(false, 'Unable to load the guest age policy. Please try again.', 503);
}
