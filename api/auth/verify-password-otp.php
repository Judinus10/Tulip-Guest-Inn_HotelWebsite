<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}
rate_limit_or_fail('password_otp_verify', 10, 30);

function ensure_admin_password_otp_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_password_otps (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_user_id INT NOT NULL,
            request_token CHAR(64) NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at_epoch INT UNSIGNED NOT NULL,
            used_at DATETIME NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_admin_password_otps_token (request_token),
            KEY idx_admin_password_otps_admin_user (admin_user_id),
            KEY idx_admin_password_otps_expiry (expires_at_epoch)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

try {
    $admin = require_admin_auth();
    $data = read_request_data();

    $requestToken = clean_string($data['request_token'] ?? '', 64);
    $otp = preg_replace('/\D+/', '', (string) ($data['otp'] ?? ''));
    $currentPassword = (string) ($data['current_password'] ?? '');
    $newPassword = (string) ($data['new_password'] ?? '');
    $confirmPassword = (string) ($data['confirm_password'] ?? '');

    if ($requestToken === '' || strlen($requestToken) !== 64) {
        json_response(false, 'OTP request is invalid. Start again.', 422);
    }

    if (strlen($otp) !== 6) {
        json_response(false, 'Enter the 6-digit OTP.', 422);
    }

    if ($currentPassword === '') {
        json_response(false, 'Current password is required.', 422);
    }

    if (strlen($newPassword) < 8) {
        json_response(false, 'New password must be at least 8 characters.', 422);
    }

    if ($newPassword !== $confirmPassword) {
        json_response(false, 'New password and confirm password must match.', 422);
    }

    if ($newPassword === $currentPassword) {
        json_response(false, 'New password must be different from current password.', 422);
    }

    $pdo = get_db_connection();
    ensure_admin_password_otp_table($pdo);

    $otpStmt = $pdo->prepare(
        'SELECT id, otp_hash, expires_at_epoch, used_at, attempts
         FROM admin_password_otps
         WHERE request_token = :request_token AND admin_user_id = :admin_user_id
         LIMIT 1'
    );
    $otpStmt->execute([
        ':request_token' => $requestToken,
        ':admin_user_id' => (int) $admin['id'],
    ]);
    $otpRow = $otpStmt->fetch();

    if (!$otpRow || !empty($otpRow['used_at'])) {
        json_response(false, 'OTP request is no longer valid. Start again.', 410);
    }

    if ((int) $otpRow['expires_at_epoch'] < time()) {
        $expire = $pdo->prepare('UPDATE admin_password_otps SET used_at = NOW(), updated_at = NOW() WHERE id = :id');
        $expire->execute([':id' => (int) $otpRow['id']]);
        json_response(false, 'OTP expired. Resend OTP and try again.', 410);
    }

    if ((int) $otpRow['attempts'] >= 5) {
        $lock = $pdo->prepare('UPDATE admin_password_otps SET used_at = NOW(), updated_at = NOW() WHERE id = :id');
        $lock->execute([':id' => (int) $otpRow['id']]);
        json_response(false, 'Too many incorrect OTP attempts. Start again.', 429);
    }

    if (!password_verify($otp, (string) $otpRow['otp_hash'])) {
        $attempt = $pdo->prepare('UPDATE admin_password_otps SET attempts = attempts + 1, updated_at = NOW() WHERE id = :id');
        $attempt->execute([':id' => (int) $otpRow['id']]);
        json_response(false, 'Invalid OTP.', 422);
    }

    $adminStmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1');
    $adminStmt->execute([':id' => (int) $admin['id']]);
    $currentHash = $adminStmt->fetchColumn();

    if (!is_string($currentHash) || $currentHash === '' || !password_verify($currentPassword, $currentHash)) {
        json_response(false, 'Current password is incorrect.', 422);
    }

    $pdo->beginTransaction();

    $updatePassword = $pdo->prepare('UPDATE admin_users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id');
    $updatePassword->execute([
        ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ':id' => (int) $admin['id'],
    ]);

    $markUsed = $pdo->prepare('UPDATE admin_password_otps SET used_at = NOW(), updated_at = NOW() WHERE id = :id');
    $markUsed->execute([':id' => (int) $otpRow['id']]);

    $revokeOtherSessions = $pdo->prepare(
        'UPDATE admin_sessions
         SET revoked_at = NOW()
         WHERE admin_user_id = :admin_user_id
           AND token_hash <> :current_token_hash
           AND revoked_at IS NULL'
    );
    $revokeOtherSessions->execute([
        ':admin_user_id' => (int) $admin['id'],
        ':current_token_hash' => hash('sha256', (string) get_admin_session_token()),
    ]);

    $pdo->commit();

    json_response(true, 'Password changed successfully.', 200);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Verify admin password OTP error: ' . $e->getMessage());
    json_response(false, 'Unable to change password right now.', 500);
}
