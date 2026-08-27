<?php
declare(strict_types=1);

require_once __DIR__ . '/_mail_settings_helpers.php';

const MAIL_PROVIDERS = ['server', 'google', 'microsoft_graph'];

function ensure_mail_provider_table(PDO $pdo): void
{
    ensure_mail_settings_tables($pdo);
    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_provider_settings (
        provider VARCHAR(40) NOT NULL PRIMARY KEY,
        sender_email VARCHAR(190) NULL,
        sender_name VARCHAR(190) NOT NULL DEFAULT 'Tulip Guest Inn',
        smtp_host VARCHAR(190) NULL,
        smtp_port SMALLINT UNSIGNED NULL,
        smtp_encryption VARCHAR(20) NULL,
        smtp_username VARCHAR(190) NULL,
        encrypted_password TEXT NULL,
        tenant_id VARCHAR(255) NULL,
        oauth_client_id VARCHAR(255) NULL,
        encrypted_client_secret TEXT NULL,
        encrypted_refresh_token TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 0,
        connection_status VARCHAR(20) NOT NULL DEFAULT 'untested',
        last_tested_at DATETIME NULL,
        last_test_message VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_mail_provider_active (is_active, connection_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Upgrade installations that created this table before Tenant ID became
    // editable from the admin panel.
    $tenantColumn = $pdo->query("SHOW COLUMNS FROM mail_provider_settings LIKE 'tenant_id'")->fetch(PDO::FETCH_ASSOC);
    if (!$tenantColumn) {
        $pdo->exec("ALTER TABLE mail_provider_settings ADD COLUMN tenant_id VARCHAR(255) NULL AFTER encrypted_password");
    }
}

function provider_setting(PDO $pdo, string $provider): ?array
{
    ensure_mail_provider_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM mail_provider_settings WHERE provider=:provider LIMIT 1');
    $stmt->execute([':provider' => $provider]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function public_provider_setting(?array $row, string $provider): array
{
    return [
        'provider' => $provider,
        'sender_email' => (string) ($row['sender_email'] ?? ''),
        'sender_name' => (string) ($row['sender_name'] ?? 'Tulip Guest Inn'),
        'smtp_host' => (string) ($row['smtp_host'] ?? ''),
        'smtp_port' => (int) ($row['smtp_port'] ?? 587),
        'smtp_encryption' => (string) ($row['smtp_encryption'] ?? 'tls'),
        'smtp_username' => (string) ($row['smtp_username'] ?? ''),
        'tenant_id' => (string) ($row['tenant_id'] ?? ''),
        'oauth_client_id' => (string) ($row['oauth_client_id'] ?? ''),
        'has_password' => !empty($row['encrypted_password']),
        'has_client_secret' => !empty($row['encrypted_client_secret']),
        'has_refresh_token' => !empty($row['encrypted_refresh_token']),
        'is_active' => (bool) ($row['is_active'] ?? false),
        'connection_status' => (string) ($row['connection_status'] ?? 'untested'),
        'last_tested_at' => $row['last_tested_at'] ?? null,
        'last_test_message' => $row['last_test_message'] ?? null,
    ];
}

function active_provider_setting(PDO $pdo): ?array
{
    ensure_mail_provider_table($pdo);
    $row = $pdo->query("SELECT * FROM mail_provider_settings WHERE is_active=1 AND connection_status='connected' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function provider_http_request(string $url, array $options): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('The server cURL extension is required for OAuth mail delivery.');
    $ch = curl_init($url);
    curl_setopt_array($ch, $options + [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false) throw new RuntimeException('The mail provider could not be reached: ' . $error);
    return [$status, (string) $body];
}

function microsoft_graph_access_token(array $config): string
{
    $tenant = trim((string) ($config['tenant_id'] ?? ''));
    if ($tenant === '') throw new RuntimeException('Enter the Microsoft Tenant ID in the Office 365 settings.');
    $clientId = trim((string) ($config['oauth_client_id'] ?? ''));
    $secret = !empty($config['encrypted_client_secret']) ? decrypt_mail_secret((string) $config['encrypted_client_secret']) : '';
    if ($clientId === '' || $secret === '') throw new RuntimeException('Enter the Azure application client ID and client secret.');
    [$status, $body] = provider_http_request('https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/token', [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query(['client_id'=>$clientId, 'client_secret'=>$secret, 'scope'=>'https://graph.microsoft.com/.default', 'grant_type'=>'client_credentials']),
    ]);
    $json = json_decode($body, true);
    if ($status < 200 || $status >= 300 || empty($json['access_token'])) throw new RuntimeException('Microsoft rejected the Azure credentials. Check the tenant, client ID, client secret and Graph permissions.');
    return (string) $json['access_token'];
}

function send_via_microsoft_graph(array $config, string $to, string $subject, string $html): bool
{
    $sender = trim((string) ($config['sender_email'] ?? ''));
    if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('The Office 365 sender address is invalid.');
    $token = microsoft_graph_access_token($config);
    $payload = ['message'=>['subject'=>$subject, 'body'=>['contentType'=>'HTML','content'=>$html], 'toRecipients'=>[['emailAddress'=>['address'=>$to]]]], 'saveToSentItems'=>true];
    [$status, $body] = provider_http_request('https://graph.microsoft.com/v1.0/users/' . rawurlencode($sender) . '/sendMail', [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
    ]);
    if ($status !== 202) throw new RuntimeException('Microsoft Graph rejected the email request (HTTP ' . $status . ').');
    return true;
}

function send_via_provider_smtp(array $config, string $to, string $subject, string $html): bool
{
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) throw new RuntimeException('The server mail library is unavailable.');
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = (string) $config['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Timeout = 30;
    $mail->Username = (string) $config['smtp_username'];
    $mail->Password = decrypt_mail_secret((string) $config['encrypted_password']);
    $mail->Port = (int) $config['smtp_port'];
    $secure = strtolower((string) $config['smtp_encryption']);
    $mail->SMTPSecure = $secure === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : ($secure === 'tls' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : '');
    $mail->CharSet = 'UTF-8';
    $mail->setFrom((string) $config['sender_email'], (string) $config['sender_name']);
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $html;
    $mail->AltBody = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return $mail->send();
}

/** Returns null when no database-managed provider is active. */
function send_via_active_provider(PDO $pdo, string $to, string $subject, string $html): ?bool
{
    $config = active_provider_setting($pdo);
    if (!$config) return null;
    if ((string) $config['provider'] === 'microsoft_graph' && !empty($config['oauth_client_id']) && !empty($config['encrypted_client_secret'])) {
        return send_via_microsoft_graph($config, $to, $subject, $html);
    }
    return send_via_provider_smtp($config, $to, $subject, $html);
}
