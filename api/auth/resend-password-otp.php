<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mail/email-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

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

    $oldToken = clean_string($data['request_token'] ?? '', 64);

    if ($oldToken === '' || strlen($oldToken) !== 64) {
        json_response(false, 'OTP request is invalid.', 422);
    }

    $pdo = get_db_connection();
    ensure_admin_password_otp_table($pdo);

    $stmt = $pdo->prepare(
        'SELECT id, expires_at_epoch, used_at
         FROM admin_password_otps
         WHERE request_token = :request_token AND admin_user_id = :admin_user_id
         LIMIT 1'
    );
    $stmt->execute([
        ':request_token' => $oldToken,
        ':admin_user_id' => (int) $admin['id'],
    ]);
    $existing = $stmt->fetch();

    if (!$existing || !empty($existing['used_at'])) {
        json_response(false, 'OTP request is no longer valid. Start again.', 410);
    }

    if ((int) $existing['expires_at_epoch'] > time()) {
        json_response(false, 'Wait until the current OTP expires before resending.', 429, [
            'data' => [
                'expires_in' => max(1, (int) $existing['expires_at_epoch'] - time()),
            ],
        ]);
    }

    $recipientEmail = filter_var((string) ($admin['email'] ?? ''), FILTER_VALIDATE_EMAIL)
        ? (string) $admin['email']
        : ADMIN_EMAIL;

    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        json_response(false, 'Admin email address is invalid.', 500);
    }

    $otp = (string) random_int(100000, 999999);
    $requestToken = bin2hex(random_bytes(32));
    $expiresAtEpoch = time() + 60;

    $subject = 'Admin password OTP - Tulip Guest Inn';
    $message = otp_email_html('Admin password OTP', $otp, 1);

    $sent = send_html_email($recipientEmail, $subject, $message);

    if (!$sent) {
        error_log('Admin password resend OTP email failed for admin user ID ' . (int) $admin['id'] . ' to ' . $recipientEmail);
        json_response(false, 'Unable to resend OTP right now.', 500);
    }

    $pdo->beginTransaction();

    $invalidate = $pdo->prepare(
        'UPDATE admin_password_otps
         SET used_at = NOW(), updated_at = NOW()
         WHERE admin_user_id = :admin_user_id AND used_at IS NULL'
    );
    $invalidate->execute([':admin_user_id' => (int) $admin['id']]);

    $insert = $pdo->prepare(
        'INSERT INTO admin_password_otps
            (admin_user_id, request_token, otp_hash, expires_at_epoch, used_at, attempts, created_at, updated_at)
         VALUES
            (:admin_user_id, :request_token, :otp_hash, :expires_at_epoch, NULL, 0, NOW(), NOW())'
    );
    $insert->execute([
        ':admin_user_id' => (int) $admin['id'],
        ':request_token' => $requestToken,
        ':otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
        ':expires_at_epoch' => $expiresAtEpoch,
    ]);

    $pdo->commit();

    json_response(true, 'New OTP sent to admin email.', 200, [
        'data' => [
            'request_token' => $requestToken,
            'expires_in' => 60,
            'email' => $recipientEmail,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Resend admin password OTP error: ' . $e->getMessage());
    json_response(false, 'Unable to resend OTP right now.', 500);
}
