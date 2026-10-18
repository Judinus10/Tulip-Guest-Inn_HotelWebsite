<?php
declare(strict_types=1);

function shared_smtp_load(PDO $pdo, bool $lock = false): ?array
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_shared_smtp (id TINYINT UNSIGNED PRIMARY KEY, payload LONGTEXT NOT NULL, active_payload LONGTEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $row = $pdo->query('SELECT payload FROM mail_shared_smtp WHERE id=1' . ($lock ? ' FOR UPDATE' : ''))->fetchColumn();
    return $row === false ? null : json_decode((string) $row, true, 512, JSON_THROW_ON_ERROR);
}

function shared_smtp_store(PDO $pdo, array $config): void
{
    $pdo->prepare('INSERT INTO mail_shared_smtp (id,payload) VALUES (1,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)')->execute([json_encode($config, JSON_THROW_ON_ERROR)]);
}

function shared_smtp_public(?array $config): array
{
    $config ??= ['smtp_host'=>'', 'smtp_port'=>587, 'smtp_encryption'=>'tls', 'sender_name'=>'Tulip Guest Inn', 'senders'=>[]];
    foreach ($config['senders'] as &$sender) {
        $sender['has_password'] = !empty($sender['encrypted_password']);
        unset($sender['encrypted_password']);
    }
    unset($sender);
    return $config;
}

function shared_smtp_sender(array $config, string $role): array
{
    $sender = $config['senders'][$role] ?? [];
    return array_merge($config, ['sender_email'=>$sender['email'] ?? '', 'smtp_username'=>$sender['email'] ?? '', 'encrypted_password'=>$sender['encrypted_password'] ?? '']);
}

function shared_smtp_role(?string $function): string
{
    return match ($function) {
        'booking', 'payment' => 'booking',
        'contact', 'contact_auto_reply' => 'contact',
        default => 'admin',
    };
}

function shared_smtp_active(PDO $pdo): ?array
{
    shared_smtp_load($pdo);
    $row = $pdo->query('SELECT active_payload FROM mail_shared_smtp WHERE id=1')->fetchColumn();
    return !$row ? null : json_decode((string)$row, true, 512, JSON_THROW_ON_ERROR);
}
