<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/run-all.php';
require_once __DIR__ . '/url-guard.php';
apply_cors_headers();
$admin = require_admin_auth();
if (strtolower((string)($admin['role'] ?? '')) !== 'super_admin') json_response(false, 'Owner access required.', 403);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) json_response(false, 'Method not allowed.', 405);
try {
    $pdo = get_db_connection();
    ics_master_settings_table($pdo);
    if ($method === 'GET') {
        $enabled = ics_master_sync_enabled($pdo);
        $legacy = ics_enabled();
        $connections = ics_connections($pdo);
        ensure_ics_schema($pdo);
        ensure_ics_export_schema($pdo);
        $rooms = $pdo->query("SELECT r.id, r.room_name, r.booking_ical_url, s.last_sync_completed_at, s.last_sync_status, s.last_sync_error FROM rooms r LEFT JOIN external_calendar_sync_status s ON s.room_id = r.id ORDER BY r.id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rooms as &$room) {
            $roomId = (int) $room['id'];
            $token = ics_export_token($pdo, $roomId, true);
            $room['export_ical_url'] = $token === null ? null : ics_export_url($roomId, $token);
        }
        unset($room);
        $status = $pdo->query("SELECT COUNT(*) AS total, MAX(last_sync_completed_at) AS last_sync, SUM(CASE WHEN last_sync_status = 'failed' THEN 1 ELSE 0 END) AS failures FROM external_calendar_sync_status")->fetch(PDO::FETCH_ASSOC);
        json_response(true, 'Calendar settings loaded.', 200, ['data' => [
            'enabled' => $enabled, 'configured_rooms' => count($connections),
            'legacy_enabled' => $legacy, 'last_sync' => $status['last_sync'] ?? null,
            'failed_rooms' => (int)($status['failures'] ?? 0),
            'rooms' => $rooms,
        ]]);
    }
    $data = read_request_data();
    $action = (string)($data['action'] ?? '');
    if ($action === 'rotate_export_token') {
        $roomId = filter_var($data['room_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$roomId || $roomId < 1) json_response(false, 'Invalid room.', 422);
        $exists = $pdo->prepare('SELECT id FROM rooms WHERE id = ? LIMIT 1');
        $exists->execute([$roomId]);
        if (!$exists->fetchColumn()) json_response(false, 'Room not found.', 404);
        ensure_ics_export_schema($pdo);
        $stmt = $pdo->prepare('INSERT INTO room_calendar_export_tokens (room_id, token_nonce) VALUES (?, ?) ON DUPLICATE KEY UPDATE token_nonce = VALUES(token_nonce)');
        $stmt->execute([$roomId, bin2hex(random_bytes(32))]);
        json_response(true, 'Website iCal link regenerated. Update the URL in Booking.com.', 200);
    }
    if ($action === 'toggle') {
        if (!is_bool($data['enabled'] ?? null)) json_response(false, 'Invalid enabled value.', 422);
        if ($data['enabled'] && (!ics_enabled() || !ics_connections($pdo))) json_response(false, 'Enable ICS_SYNC_ENABLED and configure at least one Booking.com iCal URL under Rooms → Edit Room.', 422);
        $stmt = $pdo->prepare('UPDATE hotel_calendar_sync_settings SET sync_enabled = ? WHERE id = 1');
        $stmt->execute([(int)$data['enabled']]);
        json_response(true, $data['enabled'] ? 'Calendar synchronization enabled.' : 'Calendar synchronization paused.', 200, ['data' => ['enabled' => (bool)$data['enabled']]]);
    }
    if ($action === 'update_url') {
        $roomId = filter_var($data['room_id'] ?? null, FILTER_VALIDATE_INT);
        $url = trim((string)($data['url'] ?? ''));
        if (!$roomId || $roomId < 1 || strlen($url) > 1024) json_response(false, 'Invalid room or calendar URL.', 422);
        if ($url !== '' && !validate_ics_url($url)) json_response(false, 'Enter a valid HTTPS Booking.com iCal URL.', 422);
        $room = $pdo->prepare('SELECT id FROM rooms WHERE id = ? LIMIT 1');
        $room->execute([$roomId]);
        if (!$room->fetchColumn()) json_response(false, 'Room not found.', 404);
        if ($url !== '') {
            $duplicateRoom = tulip_ics_duplicate_room($pdo, $url, (int)$roomId);
            if ($duplicateRoom !== null) json_response(false, 'This Booking.com calendar URL is already assigned to another room.', 422);
        }
        $stmt = $pdo->prepare('UPDATE rooms SET booking_ical_url = ? WHERE id = ?');
        $stmt->execute([$url !== '' ? $url : null, $roomId]);
        json_response(true, $url === '' ? 'Calendar URL removed.' : 'Calendar URL updated.', 200);
    }
    if ($action === 'sync_room') {
        if (!ics_master_sync_enabled($pdo)) json_response(false, 'Enable synchronization first.', 409);
        $roomId = filter_var($data['room_id'] ?? null, FILTER_VALIDATE_INT);
        $connection = null;
        foreach (ics_connections($pdo) as $candidate) {
            if ((int)$candidate['room_id'] === (int)$roomId) { $connection = $candidate; break; }
        }
        if (!$connection) json_response(false, 'Room has no valid Booking.com calendar URL.', 422);
        $lock = fopen(sys_get_temp_dir() . '/tulip-booking-com-ics.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            json_response(false, 'Another synchronization is running.', 409);
        }
        try { $result = sync_ics_room($pdo, $connection); }
        finally { flock($lock, LOCK_UN); fclose($lock); }
        json_response((bool)$result['success'], $result['success'] ? 'Room synchronized.' : 'Room synchronization failed.', $result['success'] ? 200 : 422, ['data' => $result]);
    }
    if ($action === 'sync') {
        if (!ics_master_sync_enabled($pdo)) json_response(false, 'Enable synchronization first.', 409);
        // Manual sync is synchronous. For large hotels, move this to a protected queue.
        $results = ics_run_all($pdo);
        $failed = count(array_filter($results, static fn($r) => !$r['success']));
        json_response($failed === 0, $failed ? 'Some room calendars failed to sync.' : 'Calendar sync completed.', $failed ? 207 : 200, ['data' => ['results' => $results]]);
    }
    json_response(false, 'Unknown action.', 422);
} catch (Throwable $e) {
    error_log('Calendar management error: ' . $e->getMessage());
    json_response(false, 'Calendar action failed. Check server logs.', 500);
}
