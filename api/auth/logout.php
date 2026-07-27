<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

try {
    require_admin_auth();
    $token = get_admin_session_token();
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('UPDATE admin_sessions SET revoked_at = NOW() WHERE token_hash = :token_hash AND revoked_at IS NULL');
    $stmt->execute([':token_hash' => hash('sha256', (string) $token)]);
} catch (Throwable $e) {
    error_log('Admin logout error: ' . $e->getMessage());
}

clear_admin_auth_cookies();
json_response(true, 'Logged out successfully.');
