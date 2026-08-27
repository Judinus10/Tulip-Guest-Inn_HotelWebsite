<?php
declare(strict_types=1);
require_once __DIR__ . '/_mail_settings_helpers.php';
apply_cors_headers();
$admin = require_mail_super_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);

try {
    $pdo = get_db_connection();
    ensure_mail_settings_tables($pdo);
    $data = read_request_data();
    $id = max(0, (int) ($data['id'] ?? 0));
    $name = clean_string($data['account_name'] ?? '', 120);
    $provider = strtolower(clean_string($data['provider'] ?? 'office365', 30));
    $email = strtolower(clean_string($data['email_address'] ?? '', 190));
    $username = clean_string($data['smtp_username'] ?? $email, 190);
    $password = (string) ($data['password'] ?? '');
    $fromName = clean_string($data['from_name'] ?? '', 190);
    $host = strtolower(clean_string($data['smtp_host'] ?? '', 190));
    $port = (int) ($data['smtp_port'] ?? 0);
    $secure = strtolower(clean_string($data['smtp_encryption'] ?? 'tls', 20));
    $functions = array_values(array_unique(array_filter((array) ($data['functions'] ?? []), fn($key) => isset(MAIL_FUNCTIONS[(string) $key]))));

    if ($name === '') json_response(false, 'Enter a name for this mail account.', 422, ['field' => 'account_name']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(false, 'Enter a valid sender email address.', 422, ['field' => 'email_address']);
    if ($username === '') json_response(false, 'Enter the Office 365 SMTP username.', 422, ['field' => 'smtp_username']);
    if ($fromName === '') json_response(false, 'Enter the sender name shown to customers.', 422, ['field' => 'from_name']);
    if ($host === '') json_response(false, 'Enter the SMTP host.', 422, ['field' => 'smtp_host']);
    if ($port < 1 || $port > 65535) json_response(false, 'Enter a valid SMTP port.', 422, ['field' => 'smtp_port']);
    if (!in_array($secure, ['tls', 'ssl', 'none'], true)) json_response(false, 'Select TLS, SSL or no encryption.', 422, ['field' => 'smtp_encryption']);
    if ($provider === 'office365' && ($host !== 'smtp.office365.com' || $port !== 587 || $secure !== 'tls')) {
        json_response(false, 'Office 365 must use smtp.office365.com on port 587 with TLS.', 422, ['field' => 'smtp_host']);
    }

    $existing = null;
    if ($id > 0) {
        $find = $pdo->prepare('SELECT * FROM mail_accounts WHERE id = :id LIMIT 1');
        $find->execute([':id' => $id]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);
        if (!$existing) json_response(false, 'The selected mail account no longer exists.', 404);
    }
    if (!$existing && $password === '') json_response(false, 'Enter the mailbox password before saving this account.', 422, ['field' => 'password']);

    $encrypted = $password !== '' ? encrypt_mail_secret($password) : (string) $existing['encrypted_password'];
    $credentialsChanged = !$existing || $password !== '' || $username !== (string) $existing['smtp_username'] || $host !== (string) $existing['smtp_host'] || $port !== (int) $existing['smtp_port'] || $secure !== (string) $existing['smtp_encryption'];

    $pdo->beginTransaction();
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE mail_accounts SET account_name=:name, provider=:provider, email_address=:email, smtp_username=:username, encrypted_password=:password, from_name=:from_name, smtp_host=:host, smtp_port=:port, smtp_encryption=:secure, connection_status=:status, is_enabled=:enabled, last_test_message=:message, updated_at=NOW() WHERE id=:id");
        $stmt->execute([':name'=>$name, ':provider'=>$provider, ':email'=>$email, ':username'=>$username, ':password'=>$encrypted, ':from_name'=>$fromName, ':host'=>$host, ':port'=>$port, ':secure'=>$secure, ':status'=>$credentialsChanged ? 'untested' : $existing['connection_status'], ':enabled'=>$credentialsChanged ? 0 : (int) $existing['is_enabled'], ':message'=>$credentialsChanged ? 'Connection must be tested after credential changes.' : $existing['last_test_message'], ':id'=>$id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO mail_accounts (account_name,provider,email_address,smtp_username,encrypted_password,from_name,smtp_host,smtp_port,smtp_encryption,created_by) VALUES (:name,:provider,:email,:username,:password,:from_name,:host,:port,:secure,:admin)");
        $stmt->execute([':name'=>$name, ':provider'=>$provider, ':email'=>$email, ':username'=>$username, ':password'=>$encrypted, ':from_name'=>$fromName, ':host'=>$host, ':port'=>$port, ':secure'=>$secure, ':admin'=>$admin['id']]);
        $id = (int) $pdo->lastInsertId();
    }

    $pdo->prepare('DELETE FROM mail_account_functions WHERE mail_account_id=:id')->execute([':id'=>$id]);
    $insertFunction = $pdo->prepare('INSERT INTO mail_account_functions (mail_account_id,function_key) VALUES (:id,:function_key)');
    $assignRoute = $pdo->prepare('UPDATE mail_routing_rules SET sender_account_id=:id WHERE function_key=:function_key');
    foreach ($functions as $functionKey) {
        $insertFunction->execute([':id'=>$id, ':function_key'=>$functionKey]);
        $assignRoute->execute([':id'=>$id, ':function_key'=>$functionKey]);
    }
    if ($existing) {
        $placeholders = $functions ? implode(',', array_fill(0, count($functions), '?')) : "''";
        $params = array_merge([$id], $functions);
        $pdo->prepare("UPDATE mail_routing_rules SET sender_account_id=NULL WHERE sender_account_id=? AND function_key NOT IN ({$placeholders})")->execute($params);
    }
    mail_settings_audit($pdo, $admin, $existing ? 'updated' : 'created', 'mail_account', (string) $id, ($existing ? 'Updated ' : 'Created ') . $email . '. Password value was not logged.');
    $pdo->commit();
    json_response(true, $credentialsChanged ? 'Mail account saved. Test the connection before it can send emails.' : 'Mail account updated.', $existing ? 200 : 201, ['data'=>['id'=>$id, 'requires_test'=>$credentialsChanged]]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if ((string) $e->getCode() === '23000') json_response(false, 'That sender email address is already configured.', 409, ['field'=>'email_address']);
    error_log('Mail account save failed: ' . $e->getMessage());
    json_response(false, 'The mail account could not be saved. Check the database migration and try again.', 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Mail account save failed: ' . $e->getMessage());
    $message = str_contains($e->getMessage(), 'MAIL_CREDENTIAL') || str_contains(strtolower($e->getMessage()), 'encryption') ? $e->getMessage() : 'The mail account could not be saved. Please verify the details and try again.';
    json_response(false, $message, 500);
}
