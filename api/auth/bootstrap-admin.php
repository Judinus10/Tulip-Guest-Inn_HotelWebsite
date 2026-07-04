<?php
declare(strict_types=1);

/*
==================================================
LOCAL-ONLY LEGACY ADMIN BOOTSTRAP
==================================================
This endpoint is kept only as a protected compatibility wrapper for local development.
It must not be used on production.
It refuses to run unless APP_ENV is local.
Prefer tools/local-create-admin.php for local admin creation.
*/

require_once __DIR__ . '/../helpers.php';

apply_cors_headers();

if (APP_ENV !== 'local') {
    json_response(false, 'Admin bootstrap is disabled outside local environment.', 403);
}

$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$localAddresses = ['127.0.0.1', '::1', 'localhost'];
if ($remoteAddress !== '' && !in_array($remoteAddress, $localAddresses, true)) {
    json_response(false, 'Admin bootstrap is only allowed from localhost.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

$data = read_request_data();
$name = clean_string($data['name'] ?? 'Jebal Homes Admin', 120);
$email = strtolower(clean_string($data['email'] ?? '', 190));
$password = (string) ($data['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Valid email is required.', 422);
}

if (strlen($password) < 12) {
    json_response(false, 'Password must be at least 12 characters.', 422);
}

try {
    $pdo = get_db_connection();

    $existing = $pdo->prepare('SELECT id FROM admin_users WHERE email = :email LIMIT 1');
    $existing->execute([':email' => $email]);
    $existingId = $existing->fetchColumn();

    if ($existingId) {
        $stmt = $pdo->prepare(
            'UPDATE admin_users
             SET name = :name,
                 password_hash = :password_hash,
                 role = :role,
                 is_active = 1,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $name,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':role' => 'admin',
            ':id' => (int) $existingId,
        ]);

        json_response(true, 'Local admin user updated.', 200, [
            'data' => [
                'id' => (int) $existingId,
                'email' => $email,
            ],
        ]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO admin_users (name, email, password_hash, role, is_active, created_at, updated_at)
         VALUES (:name, :email, :password_hash, :role, 1, NOW(), NOW())'
    );
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':role' => 'admin',
    ]);

    json_response(true, 'Local admin user created.', 201, [
        'data' => [
            'id' => (int) $pdo->lastInsertId(),
            'email' => $email,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Local admin bootstrap error: ' . $e->getMessage());
    json_response(false, 'Unable to create or update local admin user.', 500);
}
