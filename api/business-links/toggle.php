<?php
declare(strict_types=1);
require_once __DIR__ . '/_business_links_helpers.php';
apply_cors_headers();
$admin = require_mail_super_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
try {
    $pdo = get_db_connection(); ensure_business_links_table($pdo); $data = read_request_data();
    $id = (int) ($data['id'] ?? 0); $enabled = !empty($data['enabled']) ? 1 : 0;
    if ($id < 1) json_response(false, 'Select a valid business page.', 422);
    $stmt = $pdo->prepare('UPDATE external_portal_links SET is_enabled=:enabled WHERE id=:id'); $stmt->execute([':enabled'=>$enabled, ':id'=>$id]);
    if ($stmt->rowCount() === 0) { $check=$pdo->prepare('SELECT id FROM external_portal_links WHERE id=:id'); $check->execute([':id'=>$id]); if (!$check->fetch()) json_response(false, 'The selected business page no longer exists.', 404); }
    mail_settings_audit($pdo, $admin, $enabled ? 'enabled' : 'hidden', 'business_link', (string)$id, $enabled ? 'Enabled a business shortcut.' : 'Hid a business shortcut.');
    json_response(true, $enabled ? 'Business page is now visible.' : 'Business page hidden from the shortcut list.');
} catch (Throwable $e) { error_log('Business link toggle failed: '.$e->getMessage()); json_response(false, 'The business page status could not be changed.', 500); }
