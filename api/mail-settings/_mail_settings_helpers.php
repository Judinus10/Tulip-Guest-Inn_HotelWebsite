<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

const MAIL_FUNCTIONS = [
    'booking' => 'Booking emails',
    'payment' => 'Payment emails',
    'contact' => 'Contact notifications',
    'contact_auto_reply' => 'Contact auto-replies',
    'stay_reminder' => 'Stay reminders',
    'admin_alert' => 'Admin alerts',
    'test_email' => 'Test emails',
];

// Delivery audiences are application rules, not administrator preferences.
// Keeping them server-side prevents an accidental checkbox change from
// stopping required customer or hotel notifications.
const MAIL_DELIVERY_POLICY = [
    'booking' => ['customer' => true, 'hotel' => true, 'label' => 'Customer and hotel'],
    'payment' => ['customer' => true, 'hotel' => true, 'label' => 'Customer and hotel'],
    'contact' => ['customer' => false, 'hotel' => true, 'label' => 'Hotel only'],
    'contact_auto_reply' => ['customer' => true, 'hotel' => false, 'label' => 'Customer only'],
    'stay_reminder' => ['customer' => true, 'hotel' => false, 'label' => 'Customer only'],
    'admin_alert' => ['customer' => false, 'hotel' => true, 'label' => 'Hotel only'],
    'test_email' => ['customer' => false, 'hotel' => true, 'label' => 'Hotel only'],
];

function mail_delivery_policy(string $functionKey): array
{
    return MAIL_DELIVERY_POLICY[$functionKey] ?? ['customer' => false, 'hotel' => true, 'label' => 'Hotel only'];
}

function ensure_mail_settings_tables(PDO $pdo): void
{
    static $ready = [];
    $connectionId = spl_object_id($pdo);
    if (isset($ready[$connectionId])) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_accounts (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        account_name VARCHAR(120) NOT NULL,
        provider VARCHAR(30) NOT NULL DEFAULT 'office365',
        email_address VARCHAR(190) NOT NULL,
        smtp_username VARCHAR(190) NOT NULL,
        encrypted_password TEXT NOT NULL,
        from_name VARCHAR(190) NOT NULL,
        smtp_host VARCHAR(190) NOT NULL DEFAULT 'smtp.office365.com',
        smtp_port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
        smtp_encryption VARCHAR(20) NOT NULL DEFAULT 'tls',
        is_enabled TINYINT(1) NOT NULL DEFAULT 0,
        connection_status VARCHAR(20) NOT NULL DEFAULT 'untested',
        last_tested_at DATETIME NULL,
        last_test_message VARCHAR(500) NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_mail_accounts_email (email_address),
        KEY idx_mail_accounts_enabled (is_enabled)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_account_functions (
        mail_account_id INT UNSIGNED NOT NULL,
        function_key VARCHAR(60) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (mail_account_id, function_key),
        KEY idx_mail_account_functions_key (function_key),
        CONSTRAINT fk_mail_account_functions_account_runtime FOREIGN KEY (mail_account_id) REFERENCES mail_accounts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_routing_rules (
        function_key VARCHAR(60) NOT NULL PRIMARY KEY,
        sender_account_id INT UNSIGNED NULL,
        hotel_recipient_email VARCHAR(190) NULL,
        reply_to_email VARCHAR(190) NULL,
        send_customer_copy TINYINT(1) NOT NULL DEFAULT 1,
        send_hotel_copy TINYINT(1) NOT NULL DEFAULT 1,
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_mail_routing_sender (sender_account_id),
        CONSTRAINT fk_mail_routing_sender_runtime FOREIGN KEY (sender_account_id) REFERENCES mail_accounts(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_settings_audit_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        admin_user_id INT UNSIGNED NULL,
        action VARCHAR(80) NOT NULL,
        entity_type VARCHAR(40) NOT NULL,
        entity_id VARCHAR(80) NULL,
        change_summary VARCHAR(500) NOT NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_mail_audit_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $seed = $pdo->prepare("INSERT IGNORE INTO mail_routing_rules
        (function_key, hotel_recipient_email, send_customer_copy, send_hotel_copy, is_enabled)
        VALUES (:function_key, :recipient, :customer, :hotel, 1)");
    $defaults = [
        'booking' => [defined('BOOKING_ADMIN_EMAIL') ? BOOKING_ADMIN_EMAIL : 'bookings@tulipguestinn.com', 1, 1],
        'payment' => [defined('BOOKING_ADMIN_EMAIL') ? BOOKING_ADMIN_EMAIL : 'bookings@tulipguestinn.com', 1, 1],
        'contact' => [defined('CONTACT_ADMIN_EMAIL') ? CONTACT_ADMIN_EMAIL : 'info@tulipguestinn.com', 1, 1],
        'contact_auto_reply' => [null, 1, 0],
        'stay_reminder' => [defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@tulipguestinn.com', 1, 0],
        'admin_alert' => [defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@tulipguestinn.com', 0, 1],
        'test_email' => [defined('TEST_EMAIL_TO') ? TEST_EMAIL_TO : null, 0, 1],
    ];
    foreach ($defaults as $key => [$recipient, $customer, $hotel]) {
        $seed->execute([':function_key' => $key, ':recipient' => $recipient, ':customer' => $customer, ':hotel' => $hotel]);
    }
    $ready[$connectionId] = true;
}

function require_mail_super_admin(): array
{
    $admin = require_admin_auth();
    if (strtolower((string) ($admin['role'] ?? '')) !== 'super_admin') {
        json_response(false, 'Only a super administrator can manage mail settings.', 403, ['error_code' => 'SUPER_ADMIN_REQUIRED']);
    }
    return $admin;
}

function mail_encryption_key(): string
{
    $configured = defined('MAIL_CREDENTIAL_ENCRYPTION_KEY') ? trim((string) MAIL_CREDENTIAL_ENCRYPTION_KEY) : '';
    if ($configured === '') {
        throw new RuntimeException('Mail credential encryption is not configured. Add MAIL_CREDENTIAL_ENCRYPTION_KEY to the server configuration.');
    }
    return hash('sha256', $configured, true);
}

function encrypt_mail_secret(string $secret): string
{
    if ($secret === '') throw new InvalidArgumentException('A mailbox password is required.');
    if (!function_exists('openssl_encrypt')) throw new RuntimeException('The server encryption extension is unavailable.');
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($secret, 'aes-256-gcm', mail_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new RuntimeException('Unable to encrypt the mailbox password.');
    return base64_encode($iv . $tag . $cipher);
}

function decrypt_mail_secret(string $payload): string
{
    $decoded = base64_decode($payload, true);
    if ($decoded === false || strlen($decoded) < 29) throw new RuntimeException('Stored mailbox credentials are invalid. Re-enter the password.');
    $plain = openssl_decrypt(substr($decoded, 28), 'aes-256-gcm', mail_encryption_key(), OPENSSL_RAW_DATA, substr($decoded, 0, 12), substr($decoded, 12, 16));
    if ($plain === false) throw new RuntimeException('Unable to unlock the stored mailbox password. Re-enter it.');
    return $plain;
}

function mail_settings_audit(PDO $pdo, array $admin, string $action, string $type, ?string $id, string $summary): void
{
    $stmt = $pdo->prepare('INSERT INTO mail_settings_audit_logs (admin_user_id, action, entity_type, entity_id, change_summary, ip_address) VALUES (:admin, :action, :type, :entity, :summary, :ip)');
    $stmt->execute([
        ':admin' => $admin['id'] ?? null,
        ':action' => $action,
        ':type' => $type,
        ':entity' => $id,
        ':summary' => mb_substr($summary, 0, 500),
        ':ip' => get_client_ip(),
    ]);
}

function mail_function_key_for_type(string $emailType, string $relatedType = ''): string
{
    $type = strtolower($emailType);
    if (str_contains($type, 'contact_auto') || str_contains($type, 'customer_contact')) return 'contact_auto_reply';
    if (str_contains($type, 'contact') || strtolower($relatedType) === 'enquiry') return 'contact';
    if (str_contains($type, 'reminder') || str_contains($type, 'stay')) return 'stay_reminder';
    if (str_contains($type, 'payment')) return 'payment';
    if (str_contains($type, 'booking') || strtolower($relatedType) === 'booking' || str_contains($type, 'cancel') || str_contains($type, 'expired')) return 'booking';
    if (str_contains($type, 'test')) return 'test_email';
    return 'admin_alert';
}

function database_mail_profile(PDO $pdo, string $functionKey): ?array
{
    ensure_mail_settings_tables($pdo);
    $stmt = $pdo->prepare("SELECT a.* FROM mail_routing_rules r INNER JOIN mail_accounts a ON a.id = r.sender_account_id WHERE r.function_key = :function_key AND r.is_enabled = 1 AND a.is_enabled = 1 AND a.connection_status = 'connected' LIMIT 1");
    $stmt->execute([':function_key' => $functionKey]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) return null;
    return [
        'id' => (int) $account['id'],
        'host' => (string) $account['smtp_host'],
        'user' => (string) $account['smtp_username'],
        'pass' => decrypt_mail_secret((string) $account['encrypted_password']),
        'port' => (int) $account['smtp_port'],
        'secure' => (string) $account['smtp_encryption'],
        'from_email' => (string) $account['email_address'],
        'from_name' => (string) $account['from_name'],
    ];
}

function mail_route(PDO $pdo, string $functionKey): ?array
{
    ensure_mail_settings_tables($pdo);
    $stmt = $pdo->prepare('SELECT * FROM mail_routing_rules WHERE function_key = :function_key AND is_enabled = 1 LIMIT 1');
    $stmt->execute([':function_key' => $functionKey]);
    $route = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$route) return null;
    $policy = mail_delivery_policy($functionKey);
    $route['send_customer_copy'] = $policy['customer'] ? 1 : 0;
    $route['send_hotel_copy'] = $policy['hotel'] ? 1 : 0;
    $route['reply_to_email'] = null;
    return $route;
}

function public_mail_account(array $row, array $functions = []): array
{
    return [
        'id' => (int) $row['id'],
        'account_name' => (string) $row['account_name'],
        'provider' => (string) $row['provider'],
        'email_address' => (string) $row['email_address'],
        'smtp_username' => (string) $row['smtp_username'],
        'from_name' => (string) $row['from_name'],
        'smtp_host' => (string) $row['smtp_host'],
        'smtp_port' => (int) $row['smtp_port'],
        'smtp_encryption' => (string) $row['smtp_encryption'],
        'is_enabled' => (bool) $row['is_enabled'],
        'connection_status' => (string) $row['connection_status'],
        'last_tested_at' => $row['last_tested_at'],
        'last_test_message' => $row['last_test_message'],
        'has_password' => trim((string) $row['encrypted_password']) !== '',
        'functions' => array_values($functions),
    ];
}
