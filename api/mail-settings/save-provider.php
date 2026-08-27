<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider_helpers.php';
apply_cors_headers();
$admin = require_mail_super_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);

try {
    $pdo = get_db_connection();
    ensure_mail_provider_table($pdo);
    $data = read_request_data();
    $provider = strtolower(clean_string($data['provider'] ?? '', 40));
    if (!in_array($provider, MAIL_PROVIDERS, true)) json_response(false, 'Select a supported mail provider.', 422, ['field'=>'provider']);
    $existing = provider_setting($pdo, $provider);
    $email = strtolower(clean_string($data['sender_email'] ?? '', 190));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(false, 'Enter a valid sender email address.', 422, ['field'=>'sender_email']);
    $name = clean_string($data['sender_name'] ?? 'Tulip Guest Inn', 190) ?: 'Tulip Guest Inn';
    $host = strtolower(clean_string($data['smtp_host'] ?? '', 190));
    $port = (int) ($data['smtp_port'] ?? 587);
    $secure = strtolower(clean_string($data['smtp_encryption'] ?? 'tls', 20));
    $username = clean_string($data['smtp_username'] ?? $email, 190) ?: $email;
    $password = trim((string) ($data['password'] ?? ''));
    $tenantId = clean_string($data['tenant_id'] ?? '', 255);
    $clientId = clean_string($data['oauth_client_id'] ?? '', 255);
    $clientSecret = trim((string) ($data['client_secret'] ?? ''));

    if ($provider === 'google') { $host='smtp.gmail.com'; $port=587; $secure='tls'; }
    if ($provider === 'microsoft_graph') { $host='smtp.office365.com'; $port=587; $secure='tls'; }
    if ($provider === 'server' && ($host === '' || $port < 1 || $port > 65535 || !in_array($secure, ['tls','ssl','none'], true))) json_response(false, 'Enter a valid SMTP host, port and encryption method.', 422, ['field'=>'smtp_host']);
    $oldPassword = (string) ($existing['encrypted_password'] ?? '');
    $oldClientSecret = (string) ($existing['encrypted_client_secret'] ?? '');
    if ($password === '' && $oldPassword === '' && !($provider === 'microsoft_graph' && $clientId !== '' && ($clientSecret !== '' || $oldClientSecret !== ''))) json_response(false, 'Enter the mailbox or application password.', 422, ['field'=>'password']);
    if ($provider === 'microsoft_graph' && $clientId !== '' && $clientSecret === '' && $oldClientSecret === '') json_response(false, 'Enter the Azure client secret.', 422, ['field'=>'client_secret']);
    if ($provider === 'microsoft_graph' && $clientId !== '' && $tenantId === '') json_response(false, 'Enter the Microsoft Tenant ID.', 422, ['field'=>'tenant_id']);

    $encryptedPassword = $password !== '' ? encrypt_mail_secret($password) : $oldPassword;
    $encryptedClientSecret = $clientSecret !== '' ? encrypt_mail_secret($clientSecret) : $oldClientSecret;
    $sql = "INSERT INTO mail_provider_settings (provider,sender_email,sender_name,smtp_host,smtp_port,smtp_encryption,smtp_username,encrypted_password,tenant_id,oauth_client_id,encrypted_client_secret,connection_status,last_test_message)
            VALUES (:provider,:email,:name,:host,:port,:secure,:username,:password,:tenant_id,:client_id,:client_secret,'untested','Connection must be tested after saving.')
            ON DUPLICATE KEY UPDATE sender_email=VALUES(sender_email),sender_name=VALUES(sender_name),smtp_host=VALUES(smtp_host),smtp_port=VALUES(smtp_port),smtp_encryption=VALUES(smtp_encryption),smtp_username=VALUES(smtp_username),encrypted_password=VALUES(encrypted_password),tenant_id=VALUES(tenant_id),oauth_client_id=VALUES(oauth_client_id),encrypted_client_secret=VALUES(encrypted_client_secret),connection_status='untested',is_active=0,last_test_message='Connection must be tested after saving.'";
    $pdo->prepare($sql)->execute([':provider'=>$provider,':email'=>$email,':name'=>$name,':host'=>$host,':port'=>$port,':secure'=>$secure,':username'=>$username,':password'=>$encryptedPassword,':tenant_id'=>$tenantId,':client_id'=>$clientId,':client_secret'=>$encryptedClientSecret]);
    mail_settings_audit($pdo, $admin, 'saved', 'mail_provider', $provider, 'Saved ' . $provider . ' configuration. Secret values were not logged.');
    json_response(true, 'Mail settings saved. Run the connection test to activate this provider.', 200, ['data'=>public_provider_setting(provider_setting($pdo,$provider),$provider)]);
} catch (Throwable $e) {
    error_log('Provider save failed: ' . $e->getMessage());
    json_response(false, str_contains(strtolower($e->getMessage()), 'encryption') ? $e->getMessage() : 'The mail settings could not be saved. Check the fields and try again.', 500);
}
