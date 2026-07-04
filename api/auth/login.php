<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

rate_limit_or_fail('admin_login', 6, 15);

$data = read_request_data();
$email = strtolower(clean_string($data['email'] ?? '', 190));
$password = (string) ($data['password'] ?? '');

if ($email === '' || $password === '') {
    json_response(false, 'Email and password are required.', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Enter a valid email address.', 422);
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role, is_active FROM admin_users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1 || !password_verify($password, $user['password_hash'])) {
        json_response(false, 'Invalid login details.', 401);
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $rehash = $pdo->prepare('UPDATE admin_users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id');
        $rehash->execute([':password_hash' => $newHash, ':id' => $user['id']]);
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('+' . ADMIN_SESSION_HOURS . ' hours'))->format('Y-m-d H:i:s');

    $session = $pdo->prepare(
        'INSERT INTO admin_sessions (admin_user_id, token_hash, ip_address, user_agent, expires_at, created_at, last_used_at)
         VALUES (:admin_user_id, :token_hash, :ip_address, :user_agent, :expires_at, NOW(), NOW())'
    );
    $session->execute([
        ':admin_user_id' => $user['id'],
        ':token_hash' => $tokenHash,
        ':ip_address' => get_client_ip(),
        ':user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ':expires_at' => $expiresAt,
    ]);

    $updateLogin = $pdo->prepare('UPDATE admin_users SET last_login_at = NOW(), updated_at = NOW() WHERE id = :id');
    $updateLogin->execute([':id' => $user['id']]);

    json_response(true, 'Login successful.', 200, [
        'data' => [
            'token' => $token,
            'expires_at' => $expiresAt,
            'user' => [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ],
    ]);
} catch (Throwable $e) {
    error_log('Admin login error: ' . $e->getMessage());
    json_response(false, 'Unable to login right now.', 500);
}
