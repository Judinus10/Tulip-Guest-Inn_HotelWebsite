<?php
declare(strict_types=1);
require_once __DIR__ . '/_room_helpers.php';
apply_cors_headers();
require_admin_auth();
try {
    $rooms = get_room_payload(get_db_connection(), false);
    json_response(true, 'Rooms loaded.', 200, ['data' => $rooms, 'rooms' => $rooms]);
} catch (Throwable $e) {
    json_response(false, 'Unable to load rooms.', 500, ['error' => APP_ENV === 'local' ? $e->getMessage() : null]);
}
