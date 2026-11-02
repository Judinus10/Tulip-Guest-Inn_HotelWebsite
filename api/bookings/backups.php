<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/backup-helper.php';

apply_cors_headers();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }
$admin = require_admin_auth();
if (strtolower((string) ($admin['role'] ?? '')) !== 'super_admin') json_response(false, 'Owner access required.', 403);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) json_response(false, 'Method not allowed.', 405);

try {
    $pdo = get_db_connection();
    if ($method === 'GET') {
        json_response(true, 'Booking backups loaded.', 200, ['data' => ['backups' => booking_backup_list($pdo), 'limit' => TULIP_BOOKING_BACKUP_LIMIT]]);
    }

    $data = read_request_data();
    if (($data['action'] ?? '') !== 'restore') json_response(false, 'Unknown backup action.', 422);
    if (($data['confirm_restore'] ?? false) !== true) json_response(false, 'Confirm that you want to replace current booking data with this snapshot.', 422);
    $id = (string) ($data['backup_id'] ?? '');

    booking_backup_acquire($pdo);
    try { $result = booking_backup_restore($pdo, $id); }
    finally { booking_backup_release($pdo); }

    json_response(true, 'Booking data restored. A safety backup of the previous data was created.', 200, ['data' => $result]);
} catch (InvalidArgumentException $e) {
    json_response(false, $e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('Booking backup management error: ' . $e->getMessage());
    json_response(false, 'Booking backup action failed. Review the protected server log.', 500);
}
