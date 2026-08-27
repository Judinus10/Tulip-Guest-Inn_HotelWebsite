<?php
declare(strict_types=1);
require_once __DIR__ . '/_mail_settings_helpers.php';
apply_cors_headers();
$admin = require_mail_super_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
try {
    $pdo = get_db_connection(); ensure_mail_settings_tables($pdo);
    $id = (int) (read_request_data()['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT email_address FROM mail_accounts WHERE id=:id'); $stmt->execute([':id'=>$id]);
    $email = $stmt->fetchColumn();
    if (!$email) json_response(false, 'The selected mail account no longer exists.', 404);
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE mail_routing_rules SET sender_account_id=NULL WHERE sender_account_id=:id')->execute([':id'=>$id]);
    $pdo->prepare('DELETE FROM mail_accounts WHERE id=:id')->execute([':id'=>$id]);
    mail_settings_audit($pdo,$admin,'deleted','mail_account',(string)$id,'Deleted '.$email.'. Password value was not logged.');
    $pdo->commit();
    json_response(true, 'Mail account deleted. Assign another sender to its email functions.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Mail account delete failed: '.$e->getMessage());
    json_response(false, 'The mail account could not be deleted.', 500);
}
