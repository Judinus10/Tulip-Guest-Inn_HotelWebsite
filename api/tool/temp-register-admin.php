<?php
declare(strict_types=1);

/*
==================================================
LOCAL ADMIN CREATION TOOL - DO NOT UPLOAD
==================================================
This file is for local development only.
Do NOT upload this file to production hosting.
Do NOT expose it as a public API endpoint.
It refuses to run unless APP_ENV is local.

CLI usage:
php tools/local-create-admin.php --email=admin@example.com --password="StrongPassword123!" --name="Jebal Homes Admin"
*/

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

if (APP_ENV !== 'local') {
    fwrite(STDERR, "Refused: this tool only runs when APP_ENV is local.\n");
    exit(1);
}

$options = getopt('', ['email:', 'password:', 'name::']);

$email = strtolower(trim((string) ($options['email'] ?? '')));
$password = (string) ($options['password'] ?? '');
$name = trim((string) ($options['name'] ?? 'Jebal Homes Admin'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Valid --email is required.\n");
    exit(1);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}

try {
    $pdo = get_db_connection();

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(190) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'admin',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_login_at DATETIME NULL DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_admin_users_email (email),
        KEY idx_admin_users_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_sessions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        admin_user_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME NULL DEFAULT NULL,
        revoked_at DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_admin_sessions_token_hash (token_hash),
        KEY idx_admin_sessions_user (admin_user_id),
        KEY idx_admin_sessions_expires (expires_at),
        CONSTRAINT fk_admin_sessions_user_local_tool
            FOREIGN KEY (admin_user_id) REFERENCES admin_users(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        ip_address VARCHAR(45) NOT NULL,
        action VARCHAR(80) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_rate_limits_lookup (ip_address, action, created_at),
        KEY idx_rate_limits_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $existing = $pdo->prepare('SELECT id FROM admin_users WHERE email = :email LIMIT 1');
    $existing->execute([':email' => $email]);
    $existingId = $existing->fetchColumn();

    if ($existingId) {
        $update = $pdo->prepare(
            'UPDATE admin_users
             SET name = :name,
                 password_hash = :password_hash,
                 role = :role,
                 is_active = 1,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $update->execute([
            ':name' => $name,
            ':password_hash' => $hash,
            ':role' => 'admin',
            ':id' => (int) $existingId,
        ]);

        echo "Local admin updated: {$email}\n";
        exit(0);
    }

    $insert = $pdo->prepare(
        'INSERT INTO admin_users (name, email, password_hash, role, is_active, created_at, updated_at)
         VALUES (:name, :email, :password_hash, :role, 1, NOW(), NOW())'
    );
    $insert->execute([
        ':name' => $name,
        ':email' => $email,
        ':password_hash' => $hash,
        ':role' => 'admin',
    ]);

    echo "Local admin created: {$email}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed to create/update local admin: ' . $e->getMessage() . "\n");
    exit(1);
}
