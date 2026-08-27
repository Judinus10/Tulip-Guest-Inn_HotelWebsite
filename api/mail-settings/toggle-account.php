<?php
declare(strict_types=1);
require_once __DIR__ . '/_mail_settings_helpers.php';
apply_cors_headers();
$admin = require_mail_super_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
try {
    $pdo = get_db_connection(); ensure_mail_settings_tables($pdo);
    $data = read_request_data(); $id = (int) ($data['id'] ?? 0); $enabled = !empty($data['enabled']);
    $stmt = $pdo->prepare('SELECT email_address, connection_status FROM mail_accounts WHERE id=:id LIMIT 1'); $stmt->execute([':id'=>$id]); $account=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$account) json_response(false,'The selected mail account no longer exists.',404);
    if($enabled && $account['connection_status']!=='connected') json_response(false,'Test this mail account successfully before enabling it.',422);
    $pdo->prepare('UPDATE mail_accounts SET is_enabled=:enabled WHERE id=:id')->execute([':enabled'=>$enabled?1:0,':id'=>$id]);
    mail_settings_audit($pdo,$admin,$enabled?'enabled':'disabled','mail_account',(string)$id,($enabled?'Enabled ':'Disabled ').$account['email_address'].'.');
    json_response(true,$enabled?'Mail account enabled.':'Mail account disabled. Email functions assigned to it will use the server fallback until another connected sender is selected.');
}catch(Throwable $e){error_log('Mail account toggle failed: '.$e->getMessage());json_response(false,'The mail account status could not be changed.',500);}
