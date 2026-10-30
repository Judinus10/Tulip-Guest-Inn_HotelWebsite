<?php
declare(strict_types=1);
require_once __DIR__ . '/_room_helpers.php';
apply_cors_headers();
require_admin_auth();
try {
    $pdo = get_db_connection();
    $rooms = get_room_payload($pdo, false);
    $occupancySettings = ['count_children_toward_capacity' => room_count_children_setting($pdo), 'max_child_age' => room_max_child_age_setting($pdo)];
    json_response(true, 'Rooms loaded.', 200, ['data' => $rooms, 'rooms' => $rooms, 'occupancy_settings' => $occupancySettings]);
} catch (Throwable $e) {
    json_response(false, 'Unable to load rooms.', 500, ['error' => APP_ENV === 'local' ? $e->getMessage() : null]);
}
