<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/_verification-helper.php';

apply_cors_headers();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
$admin = require_admin_auth();
$data = read_request_data();
$action = (string) ($data['action'] ?? '');

try {
    $pdo = get_db_connection();
    security_lock_account($pdo, (int)$admin['id']);
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
        // Delivery retries must never consume the verification allowance.
        rate_limit_or_fail('admin_email_send', 8, 30);
        rate_limit_subject('admin_email_send_user', (string)$admin['id'], 8, 30);
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
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);
        if (!send_admin_verification_code($newEmail, 'Verify your new admin email', $otp)) {
            json_response(false, 'Unable to send the verification code.', 503);
        }
        $expiresAtEpoch = time() + ADMIN_VERIFICATION_TTL;
        $pdo->beginTransaction();
        $invalidate = $pdo->prepare('UPDATE admin_email_change_requests SET used_at = NOW() WHERE admin_user_id = ? AND used_at IS NULL');
        $invalidate->execute([(int) $admin['id']]);
        $insert = $pdo->prepare('INSERT INTO admin_email_change_requests (admin_user_id, new_email, request_hash, otp_hash, expires_at_epoch) VALUES (?, ?, ?, ?, ?)');
        $insert->execute([(int) $admin['id'], $newEmail, hash('sha256', $token), $otpHash, $expiresAtEpoch]);
        $pdo->commit();
        json_response(true, 'Verification code sent to the new email.', 200, ['data' => ['request_token' => $token, 'expires_in' => max(0, $expiresAtEpoch - time())]]);
    }

    if ($action !== 'verify') json_response(false, 'Invalid action.', 422);
    // Separate abuse limit; the locked challenge below permits only five wrong codes.
    rate_limit_or_fail('admin_email_verify', 30, 30);
    rate_limit_subject('admin_email_verify_user', (string)$admin['id'], 30, 30);
    $token = (string) ($data['request_token'] ?? '');
    $otp = (string) ($data['otp'] ?? '');
    $password = (string) ($data['current_password'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !preg_match('/^[0-9]{6}$/D', $otp) || $password === '') json_response(false, 'Verification details are invalid.', 422);
    // Complete all queue/schema DDL before the atomic account update.
    ensure_email_queue_table($pdo);
    ensure_mail_settings_tables($pdo);
    $hasPasswordOtps = (bool)$pdo->query("SHOW TABLES LIKE 'admin_password_otps_v2'")->fetchColumn();
    $pdo->beginTransaction();
    $lookup = $pdo->prepare('SELECT * FROM admin_email_change_requests WHERE request_hash = ? AND admin_user_id = ? FOR UPDATE');
    $lookup->execute([hash('sha256', $token), (int) $admin['id']]);
    $request = $lookup->fetch(PDO::FETCH_ASSOC);
    if (!$request || $request['used_at'] !== null || (int) $request['expires_at_epoch'] <= time() || (int) $request['attempts'] >= 5) {
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
    $used = $pdo->prepare('UPDATE admin_email_change_requests SET used_at = NOW() WHERE admin_user_id = ? AND used_at IS NULL');
    $used->execute([(int)$admin['id']]);
    if ($hasPasswordOtps) $pdo->prepare('UPDATE admin_password_otps_v2 SET used_at=NOW(),updated_at=NOW() WHERE admin_user_id=? AND used_at IS NULL')->execute([(int)$admin['id']]);
    $revoke = $pdo->prepare('UPDATE admin_sessions SET revoked_at = NOW() WHERE admin_user_id = ? AND token_hash <> ? AND revoked_at IS NULL');
    $revoke->execute([(int) $admin['id'], hash('sha256', (string) get_admin_session_token())]);
    if (filter_var((string)$current['email'], FILTER_VALIDATE_EMAIL)) {
        $queued = enqueue_email($pdo, 'admin_security', (int)$request['id'], (string)$current['email'], 'Admin email changed - Tulip Guest Inn', '<p>Your Tulip Guest Inn admin email address was changed. If you did not do this, contact the site owner immediately.</p>', 'admin_email_changed_security', null, 3, null, null, null, true);
        if (!$queued) throw new RuntimeException('Security notification could not be queued.');
    }
    $pdo->commit();
    try { security_audit('admin_email_changed', 'succeeded', (int) $admin['id']); } catch (Throwable $logError) { error_log('Admin email audit failed: ' . $logError->getMessage()); }
    json_response(true, 'Admin email changed successfully.', 200, ['data' => ['email' => $request['new_email']]]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Admin email change failed: ' . $e->getMessage());
    json_response(false, 'Unable to change admin email right now.', 500);
}
