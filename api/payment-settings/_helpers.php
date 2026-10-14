<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
function payment_settings_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS hotel_payment_settings (id TINYINT UNSIGNED NOT NULL PRIMARY KEY, pay_on_arrival TINYINT(1) NOT NULL DEFAULT 1, payhere_enabled TINYINT(1) NOT NULL DEFAULT 0, environment VARCHAR(10) NOT NULL DEFAULT 'sandbox', merchant_id VARCHAR(100) NOT NULL DEFAULT '', encrypted_secret TEXT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec('INSERT IGNORE INTO hotel_payment_settings (id) VALUES (1)');
}
function payment_key(): string {
    $encoded = getenv('TULIP_PAYMENT_ENCRYPTION_KEY');
    if (!is_string($encoded) || $encoded === '') throw new RuntimeException('Payment encryption key not configured.');
    $key = base64_decode($encoded, true);
    if ($key === false || strlen($key) !== 32) throw new RuntimeException('Payment encryption key must be a base64-encoded 32-byte key.');
    return $key;
}
function encrypt_payment_secret(string $secret): string {
    $nonce = random_bytes(12); $tag = '';
    $cipher = openssl_encrypt($secret, 'aes-256-gcm', payment_key(), OPENSSL_RAW_DATA, $nonce, $tag, 'tulip-payhere-v1');
    if ($cipher === false) throw new RuntimeException('Encryption failed.');
    return base64_encode($nonce . $tag . $cipher);
}
function payment_public_row(array $row): array {
    return ['pay_on_arrival' => (bool)$row['pay_on_arrival'], 'payhere_enabled' => (bool)$row['payhere_enabled'], 'environment' => $row['environment'], 'merchant_id' => $row['merchant_id'], 'secret_configured' => !empty($row['encrypted_secret'])];
}
function require_payment_owner(): array {
    $admin = require_admin_auth();
    if (!in_array(strtolower((string)$admin['role']), ['super_admin'], true)) json_response(false, 'Owner access required.', 403);
    return $admin;
}
function confirm_payment_password(PDO $pdo, int $id, string $password): void {
    if ($password === '') json_response(false, 'Admin password is required.', 422);
    $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = ? AND is_active = 1');
    $stmt->execute([$id]); $hash = $stmt->fetchColumn();
    if (!is_string($hash) || !password_verify($password, $hash)) json_response(false, 'Incorrect admin password.', 403);
}
