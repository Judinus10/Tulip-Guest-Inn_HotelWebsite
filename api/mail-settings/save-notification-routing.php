<?php
declare(strict_types=1);
require_once __DIR__ . '/_mail_settings_helpers.php';
apply_cors_headers();
$admin = require_mail_super_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
try {
    $pdo = get_db_connection();
    ensure_mail_settings_tables($pdo);
    $input = read_request_data();
    $mode = (string) ($input['mode'] ?? 'default');
    if (!in_array($mode, ['default', 'custom'], true)) json_response(false, 'Invalid routing mode.', 422);
    $items = $input['recipients'] ?? [];
    if (!is_array($items) || count($items) > 100) json_response(false, 'Too many recipients.', 422);
    $allowed = ['booking', 'payment', 'contact', 'admin_alert'];
    $validated = [];
    foreach ($items as $item) {
        if (!is_array($item)) json_response(false, 'Invalid recipient entry.', 422);
        $email = strtolower(trim((string) ($item['email_address'] ?? '')));
        $functions = $item['functions'] ?? [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || !is_array($functions)) json_response(false, 'Enter a valid recipient email and functions.', 422);
        foreach ($functions as $key) {
            if (!in_array($key, $allowed, true)) json_response(false, 'Invalid notification function.', 422);
            $validated[$email . '|' . $key] = [$email, $key];
        }
    }
    if ($mode === 'custom') {
        foreach (['booking', 'contact', 'admin_alert'] as $required) {
            if (!array_filter($validated, static fn($pair) => $pair[1] === $required)) json_response(false, 'Assign at least one recipient for booking, contact and admin notifications.', 422);
        }
    }
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE mail_notification_preferences SET routing_mode=:mode WHERE id=1')->execute([':mode' => $mode]);
    $pdo->exec('DELETE FROM mail_notification_recipients');
    $insert = $pdo->prepare('INSERT INTO mail_notification_recipients (email_address,function_key) VALUES (?,?)');
    foreach ($validated as $pair) $insert->execute($pair);
    mail_settings_audit($pdo, $admin, 'updated', 'notification_recipients', null, 'Updated hotel notification recipient assignments.');
    $pdo->commit();
    json_response(true, 'Notification routing saved.');
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Notification routing save failed: ' . $error->getMessage());
    json_response(false, 'Notification routing could not be saved.', 500);
}
