<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider_helpers.php';
require_once __DIR__ . '/shared-smtp-helper.php';
apply_cors_headers();
$admin = require_mail_super_admin();
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) json_response(false, 'Only GET and POST are allowed.', 405);
try {
    $pdo = get_db_connection();
    ensure_mail_provider_table($pdo);
    // Create the table before opening a transaction (MySQL DDL commits implicitly).
    $config = shared_smtp_load($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!$config) {
            $existing = provider_setting($pdo, 'server');
            $config = ['smtp_host'=>$existing['smtp_host'] ?? SMTP_HOST, 'smtp_port'=>(int)($existing['smtp_port'] ?? SMTP_PORT), 'smtp_encryption'=>$existing['smtp_encryption'] ?? SMTP_SECURE, 'sender_name'=>$existing['sender_name'] ?? 'Tulip Guest Inn', 'senders'=>[]];
            foreach (['booking'=>'BOOKING_FROM_EMAIL','contact'=>'CONTACT_FROM_EMAIL','admin'=>'ADMIN_FROM_EMAIL'] as $role=>$constant) {
                $config['senders'][$role] = ['email'=>defined($constant) ? constant($constant) : '', 'connection_status'=>'untested'];
            }
        }
        json_response(true, 'SMTP settings loaded.', 200, ['data'=>shared_smtp_public($config)]);
    }
    $data = read_request_data();
    $action = (string)($data['action'] ?? '');
    if ($action === 'save') {
        $host = strtolower(clean_string($data['smtp_host'] ?? '',190));
        $port = (int)($data['smtp_port'] ?? 0);
        $secure = (string)($data['smtp_encryption'] ?? '');
        $name = clean_string($data['sender_name'] ?? '',190);
        if ($host === '' || preg_match('/[\s\/]/', $host) || $port < 1 || $port > 65535 || !in_array($secure,['tls','ssl','none'],true) || $name === '') json_response(false,'Enter a valid SMTP host, port, encryption and sender name.',422);
        $new = ['smtp_host'=>$host,'smtp_port'=>$port,'smtp_encryption'=>$secure,'sender_name'=>$name,'senders'=>[]];
        $transportChanged = !$config || $host !== $config['smtp_host'] || $port !== $config['smtp_port'] || $secure !== $config['smtp_encryption'];
        foreach (['booking','contact','admin'] as $role) {
            $input = $data['senders'][$role] ?? [];
            $old = $config['senders'][$role] ?? [];
            $email = strtolower(clean_string($input['email'] ?? '',190));
            $password = (string)($input['password'] ?? '');
            if (!filter_var($email,FILTER_VALIDATE_EMAIL)) json_response(false,'Enter a valid ' . $role . ' sender email.',422);
            if ($password === '' && empty($old['encrypted_password'])) json_response(false,'Enter the ' . $role . ' mailbox password.',422);
            if ($password === '' && !empty($old['email']) && $email !== $old['email']) json_response(false,'Enter the password for the changed ' . $role . ' address.',422);
            $changed = $transportChanged || $password !== '' || $email !== ($old['email'] ?? '');
            $new['senders'][$role] = ['email'=>$email,'encrypted_password'=>$password !== '' ? encrypt_mail_secret($password) : $old['encrypted_password'],'connection_status'=>$changed ? 'untested' : ($old['connection_status'] ?? 'untested'),'last_tested_at'=>$changed ? null : ($old['last_tested_at'] ?? null)];
        }
        $pdo->beginTransaction();
        shared_smtp_store($pdo,$new);
        mail_settings_audit($pdo,$admin,'saved','shared_smtp','server','Shared SMTP settings saved; passwords excluded.');
        $pdo->commit();
        json_response(true,'Saved. Test each sender, then activate SMTP. The current active configuration is unchanged.',200,['data'=>shared_smtp_public($new)]);
    }
    if (!$config) json_response(false,'Save the shared SMTP settings first.',422);
    if ($action === 'test') {
        $role = (string)($data['role'] ?? '');
        $to = clean_string($data['recipient_email'] ?? '',190);
        if (!in_array($role,['booking','contact','admin'],true) || !filter_var($to,FILTER_VALIDATE_EMAIL)) json_response(false,'Choose a sender and enter a valid test destination.',422);
        $snapshot = json_encode($config);
        $success = false;
        try { $success = send_via_provider_smtp(shared_smtp_sender($config,$role),$to,'Tulip Guest Inn — ' . $role . ' SMTP test','<p>This test verifies the selected sender using the shared SMTP server.</p>'); }
        catch (Throwable $error) { error_log('Shared SMTP test failed: ' . $error->getMessage()); }
        $pdo->beginTransaction();
        $row = $pdo->query('SELECT payload FROM mail_shared_smtp WHERE id=1 FOR UPDATE')->fetchColumn();
        $latest = json_decode((string)$row,true,512,JSON_THROW_ON_ERROR);
        if (json_encode($latest) !== $snapshot) { $pdo->rollBack(); json_response(false,'Settings changed during testing. Reload and test again.',409); }
        $latest['senders'][$role]['connection_status'] = $success ? 'connected' : 'failed';
        $latest['senders'][$role]['last_tested_at'] = date('Y-m-d H:i:s');
        shared_smtp_store($pdo,$latest);
        mail_settings_audit($pdo,$admin,'tested','shared_smtp',$role,$success ? 'SMTP sender test succeeded.' : 'SMTP sender test failed.');
        $pdo->commit();
        json_response($success,$success ? 'Test email sent successfully.' : 'SMTP test failed. Check this mailbox password and the shared server settings.', $success ? 200 : 422);
    }
    if ($action === 'activate') {
        $pdo->beginTransaction();
        $row = $pdo->query('SELECT payload FROM mail_shared_smtp WHERE id=1 FOR UPDATE')->fetchColumn();
        $config = json_decode((string)$row,true,512,JSON_THROW_ON_ERROR);
        foreach (['booking','contact','admin'] as $role) {
            if (($config['senders'][$role]['connection_status'] ?? '') !== 'connected') { $pdo->rollBack(); json_response(false,'All three senders must pass their tests before activating SMTP.',422); }
        }
        $pdo->prepare('UPDATE mail_shared_smtp SET active_payload=payload WHERE id=1')->execute();
        $pdo->prepare("INSERT INTO mail_provider_settings (provider,smtp_host,smtp_port,smtp_encryption,sender_name,connection_status) VALUES ('server',?,?,?,?,'connected') ON DUPLICATE KEY UPDATE smtp_host=VALUES(smtp_host),smtp_port=VALUES(smtp_port),smtp_encryption=VALUES(smtp_encryption),sender_name=VALUES(sender_name)")->execute([$config['smtp_host'],$config['smtp_port'],$config['smtp_encryption'],$config['sender_name']]);
        $pdo->exec('UPDATE mail_provider_settings SET is_active=0');
        $pdo->exec("UPDATE mail_provider_settings SET is_active=1,connection_status='connected',last_tested_at=NOW() WHERE provider='server'");
        mail_settings_audit($pdo,$admin,'activated','shared_smtp','server','SMTP activated with three verified senders.');
        $pdo->commit();
        json_response(true,'SMTP is now the active default provider.');
    }
    json_response(false,'Unsupported SMTP action.',422);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Shared SMTP settings failed: ' . $error->getMessage());
    json_response(false,'SMTP settings could not be processed. Check server logs and the mail encryption key.',500);
}
