-- Run against the existing hotel database before deploying the Step 3 PHP files.
-- Preserves the legacy OTP table and all admin passwords. Request fresh OTPs after deployment.
CREATE TABLE IF NOT EXISTS admin_password_otps_v2 (
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
            UNIQUE KEY uniq_admin_password_otps_v2_token (request_token),
            KEY idx_admin_password_otps_v2_admin_user (admin_user_id),
            KEY idx_admin_password_otps_v2_expiry (expires_at_epoch)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
