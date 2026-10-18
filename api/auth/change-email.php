<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mail/email-helper.php';

apply_cors_headers();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
$admin = require_admin_auth();
rate_limit_or_fail('admin_email_change', 8, 30);
rate_limit_subject('admin_email_change_user', (string) $admin['id'], 8, 30);
$data = read_request_data();
$action = (string) ($data['action'] ?? '');

try {
    $pdo = get_db_connection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_email_change_requests (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        admin_user_id INT UNSIGNED NOT NULL,
        new_email VARCHAR(190) NOT NULL,
        request_hash CHAR(64) NOT NULL UNIQUE,
        otp_hash VARCHAR(255) NOT NULL,
        expires_at_epoch INT UNSIGNED NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_admin_email_change_user (admin_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ($action === 'request') {
        $password = (string) ($data['current_password'] ?? '');
        $newEmail = mb_strtolower(trim((string) ($data['new_email'] ?? '')));
        if (strlen($newEmail) > 190 || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) json_response(false, 'Enter a valid new email.', 422);
        $stmt = $pdo->prepare('SELECT email, password_hash FROM admin_users WHERE id = ? AND is_active = 1');
        $stmt->execute([(int) $admin['id']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current || $password === '' || !password_verify($password, (string) $current['password_hash'])) json_response(false, 'Current password is incorrect.', 403);
        if (strcasecmp((string) $current['email'], $newEmail) === 0) json_response(false, 'That is already your email address.', 422);
        $exists = $pdo->prepare('SELECT id FROM admin_users WHERE email = ? LIMIT 1');
        $exists->execute([$newEmail]);
        if ($exists->fetchColumn()) json_response(false, 'This email address is already in use.', 409);
        $otp = (string) random_int(100000, 999999);
        $token = bin2hex(random_bytes(32));
        if (!send_html_email($newEmail, 'Verify your admin email - Tulip Guest Inn', otp_email_html('Verify your new admin email', $otp, 10))) {
            json_response(false, 'Unable to send the verification code.', 503);
        }
        $pdo->beginTransaction();
        $invalidate = $pdo->prepare('UPDATE admin_email_change_requests SET used_at = NOW() WHERE admin_user_id = ? AND used_at IS NULL');
        $invalidate->execute([(int) $admin['id']]);
        $insert = $pdo->prepare('INSERT INTO admin_email_change_requests (admin_user_id, new_email, request_hash, otp_hash, expires_at_epoch) VALUES (?, ?, ?, ?, ?)');
        $insert->execute([(int) $admin['id'], $newEmail, hash('sha256', $token), password_hash($otp, PASSWORD_DEFAULT), time() + 600]);
        $pdo->commit();
        json_response(true, 'Verification code sent to the new email.', 200, ['data' => ['request_token' => $token, 'expires_in' => 600]]);
    }

    if ($action !== 'verify') json_response(false, 'Invalid action.', 422);
    $token = (string) ($data['request_token'] ?? '');
    $otp = (string) ($data['otp'] ?? '');
    $password = (string) ($data['current_password'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !preg_match('/^[0-9]{6}$/D', $otp) || $password === '') json_response(false, 'Verification details are invalid.', 422);
    $pdo->beginTransaction();
    $lookup = $pdo->prepare('SELECT * FROM admin_email_change_requests WHERE request_hash = ? AND admin_user_id = ? FOR UPDATE');
    $lookup->execute([hash('sha256', $token), (int) $admin['id']]);
    $request = $lookup->fetch(PDO::FETCH_ASSOC);
    if (!$request || $request['used_at'] !== null || (int) $request['expires_at_epoch'] < time() || (int) $request['attempts'] >= 5) {
        $pdo->rollBack();
        json_response(false, 'Code expired or invalid. Request a new one.', 410);
    }
    $user = $pdo->prepare('SELECT email, password_hash FROM admin_users WHERE id = ? AND is_active = 1 FOR UPDATE');
    $user->execute([(int) $admin['id']]);
    $current = $user->fetch(PDO::FETCH_ASSOC);
    if (!$current || !password_verify($password, (string) $current['password_hash'])) {
        $pdo->rollBack();
        json_response(false, 'Current password is incorrect.', 403);
    }
    if (!password_verify($otp, (string) $request['otp_hash'])) {
        $failed = $pdo->prepare('UPDATE admin_email_change_requests SET attempts = attempts + 1 WHERE id = ?');
        $failed->execute([(int) $request['id']]);
        $pdo->commit();
        json_response(false, 'Incorrect verification code.', 422);
    }
    $update = $pdo->prepare('UPDATE admin_users SET email = ?, updated_at = NOW() WHERE id = ?');
    $update->execute([(string) $request['new_email'], (int) $admin['id']]);
    $used = $pdo->prepare('UPDATE admin_email_change_requests SET used_at = NOW() WHERE id = ?');
    $used->execute([(int) $request['id']]);
    $revoke = $pdo->prepare('UPDATE admin_sessions SET revoked_at = NOW() WHERE admin_user_id = ? AND token_hash <> ? AND revoked_at IS NULL');
    $revoke->execute([(int) $admin['id'], hash('sha256', (string) get_admin_session_token())]);
    $pdo->commit();
    try { security_audit('admin_email_changed', 'succeeded', (int) $admin['id']); } catch (Throwable $logError) { error_log('Admin email audit failed: ' . $logError->getMessage()); }
    if (filter_var((string) $current['email'], FILTER_VALIDATE_EMAIL)) {
        try { send_html_email((string) $current['email'], 'Admin email changed - Tulip Guest Inn', '<p>Your Tulip Guest Inn admin email address was changed. If you did not do this, contact the site owner immediately.</p>'); } catch (Throwable $mailError) { error_log('Old admin email notification failed: ' . $mailError->getMessage()); }
    }
    json_response(true, 'Admin email changed successfully.', 200, ['data' => ['email' => $request['new_email']]]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Admin email change failed: ' . $e->getMessage());
    json_response(false, 'Unable to change admin email right now.', 500);
}
