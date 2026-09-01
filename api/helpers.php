<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$rootAutoload = __DIR__ . '/../vendor/autoload.php';
$apiAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($rootAutoload)) {
    require_once $rootAutoload;
} elseif (is_file($apiAutoload)) {
    require_once $apiAutoload;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function apply_security_headers(): void
{
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

function normalize_cors_origin(string $origin): string
{
    return rtrim(trim($origin, " \t\n\r\0\x0B\"'"), '/');
}

function get_configured_cors_origins(): array
{
    $origins = [];

    if (defined('ALLOWED_ORIGINS') && is_array(ALLOWED_ORIGINS)) {
        $origins = array_merge($origins, ALLOWED_ORIGINS);
    }

    foreach (['FRONTEND_URL', 'PUBLIC_APP_URL', 'APP_BASE_URL', 'ADMIN_APP_URL'] as $constantName) {
        if (defined($constantName) && trim((string) constant($constantName)) !== '') {
            $origins[] = (string) constant($constantName);
        }
    }

    $normalized = [];
    foreach ($origins as $origin) {
        $origin = normalize_cors_origin((string) $origin);
        if ($origin !== '') {
            $normalized[$origin] = true;
        }
    }

    return array_keys($normalized);
}

function is_local_dev_origin(string $origin): bool
{
    $appEnv = defined('APP_ENV') ? strtolower((string) APP_ENV) : 'production';
    if ($appEnv !== 'local') {
        return false;
    }

    $host = parse_url($origin, PHP_URL_HOST);
    $scheme = parse_url($origin, PHP_URL_SCHEME);

    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }

    return in_array($host, ['localhost', '127.0.0.1'], true);
}

function get_request_origin(): string
{
    return normalize_cors_origin((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
}

function is_cors_origin_allowed(string $origin): bool
{
    if ($origin === '') {
        return false;
    }

    return in_array($origin, get_configured_cors_origins(), true) || is_local_dev_origin($origin);
}

function apply_cors_headers(): void
{
    apply_security_headers();

    $origin = get_request_origin();

    if ($origin !== '' && is_cors_origin_allowed($origin)) {
        header('Access-Control-Allow-Origin: ' . $origin, true);
        header('Access-Control-Allow-Credentials: true', true);
        header('Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers', true);
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS', true);
    header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, X-CSRF-Token, X-Requested-With, Cache-Control, Pragma, X-HTTP-Method-Override', true);
    header('Access-Control-Max-Age: 86400', true);
    header('Content-Type: application/json; charset=utf-8', true);

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function json_response(bool $success, string $message, int $statusCode = 200, array $extra = []): void
{
    // JSON endpoints must never include PHP notices/warnings or output from an
    // included library. A single character before the JSON body makes
    // response.json() fail even when the database transaction succeeded.
    if (ob_get_level() > 0) {
        $unexpectedOutput = ob_get_contents();
        if (is_string($unexpectedOutput) && trim($unexpectedOutput) !== '') {
            error_log('Discarded unexpected API output before JSON response: ' . substr(trim($unexpectedOutput), 0, 2000));
        }
        ob_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8', true);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function read_request_data(): array
{
    enforce_request_body_limit();
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (is_array($data)) {
        return $data;
    }

    return $_POST ?: [];
}

function enforce_request_body_limit(?int $maxBytes = null): void
{
    $maxBytes ??= defined('MAX_REQUEST_BODY_BYTES') ? MAX_REQUEST_BODY_BYTES : 1048576;
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > $maxBytes) {
        json_response(false, 'Request body is too large.', 413);
    }
}

function public_token_secret(): string
{
    $secret = defined('PUBLIC_TOKEN_SECRET') ? trim((string) PUBLIC_TOKEN_SECRET) : '';
    if ($secret === '') {
        throw new RuntimeException('Public token secret is not configured.');
    }
    return $secret;
}

function create_public_token(string $purpose, array $claims, int $ttlSeconds): string
{
    $payload = array_merge($claims, [
        'purpose' => $purpose,
        'exp' => time() + max(300, $ttlSeconds),
    ]);
    $encoded = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $encoded, public_token_secret());
    return $encoded . '.' . $signature;
}

function verify_public_token(string $token, string $purpose, array $requiredClaims): bool
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], public_token_secret()), $parts[1])) {
        return false;
    }
    $padding = strlen($parts[0]) % 4;
    $decoded = base64_decode(strtr($parts[0] . ($padding ? str_repeat('=', 4 - $padding) : ''), '-_', '+/'), true);
    $payload = is_string($decoded) ? json_decode($decoded, true) : null;
    if (!is_array($payload) || ($payload['purpose'] ?? '') !== $purpose || (int) ($payload['exp'] ?? 0) < time()) {
        return false;
    }
    foreach ($requiredClaims as $key => $value) {
        if (!array_key_exists($key, $payload) || !hash_equals((string) $value, (string) $payload[$key])) {
            return false;
        }
    }
    return true;
}

function legacy_public_tokens_allowed(): bool
{
    $until = defined('ALLOW_LEGACY_PUBLIC_TOKENS_UNTIL') ? trim((string) ALLOW_LEGACY_PUBLIC_TOKENS_UNTIL) : '';
    if ($until === '') return false;
    $timestamp = strtotime($until);
    return $timestamp !== false && $timestamp >= time();
}

function secure_image_upload(array $file, string $targetDir, string $filenamePrefix): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0) {
        throw new RuntimeException('Invalid uploaded image.');
    }
    if ($size > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Image exceeds the upload size limit.');
    }
    $info = @getimagesize($tmp);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = strtolower((string) ($info['mime'] ?? ''));
    $width = (int) ($info[0] ?? 0);
    $height = (int) ($info[1] ?? 0);
    if (!isset($allowed[$mime]) || $width < 1 || $height < 1) {
        throw new RuntimeException('Only valid JPEG, PNG, and WebP images are allowed.');
    }
    if ($width > MAX_IMAGE_WIDTH || $height > MAX_IMAGE_HEIGHT || $width * $height > MAX_IMAGE_PIXELS) {
        throw new RuntimeException('Image dimensions are too large.');
    }
    if (!extension_loaded('gd')) {
        throw new RuntimeException('The GD image extension is required for secure uploads.');
    }
    $source = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png' => @imagecreatefrompng($tmp),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        default => false,
    };
    if (!$source) throw new RuntimeException('Unable to decode uploaded image.');
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        imagedestroy($source);
        throw new RuntimeException('Upload directory could not be created.');
    }
    if (!is_writable($targetDir)) {
        imagedestroy($source);
        throw new RuntimeException('Upload directory is not writable.');
    }
    $extension = $allowed[$mime];
    $filename = preg_replace('/[^a-z0-9_-]/i', '_', $filenamePrefix) . '_' . bin2hex(random_bytes(12)) . '.' . $extension;
    $target = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    $written = match ($mime) {
        'image/jpeg' => imagejpeg($source, $target, 88),
        'image/png' => imagepng($source, $target, 7),
        'image/webp' => function_exists('imagewebp') ? imagewebp($source, $target, 88) : false,
        default => false,
    };
    imagedestroy($source);
    if (!$written) {
        if (is_file($target)) @unlink($target);
        throw new RuntimeException('Unable to store the re-encoded image.');
    }
    @chmod($target, 0644);
    return ['filename' => $filename, 'mime' => $mime, 'extension' => $extension, 'width' => $width, 'height' => $height];
}

function clean_string(mixed $value, int $maxLength = 1000): string
{
    $value = trim((string) $value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr($value, 0, $maxLength);
}

function public_api_base_url(): string
{
    foreach (['ASSET_BASE_URL', 'API_BASE_URL'] as $constantName) {
        if (defined($constantName) && trim((string) constant($constantName)) !== '') {
            return rtrim((string) constant($constantName), '/');
        }
    }
    $scheme = is_https_request() ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'));
    $position = strpos($script, '/api/');
    $apiPath = $position === false ? '/api' : substr($script, 0, $position + 4);
    return $scheme . '://' . $host . rtrim($apiPath, '/');
}

function public_upload_url(string $path, string $directory): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) {
        $path = (string) parse_url($path, PHP_URL_PATH);
    }
    $filename = basename(str_replace('\\', '/', $path));
    return public_api_base_url() . '/uploads/' . trim($directory, '/') . '/' . rawurlencode($filename);
}

function is_valid_date(string $date): bool
{
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function get_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function rate_limit_or_fail(string $action, int $maxAttempts = PUBLIC_RATE_LIMIT_MAX, int $windowMinutes = PUBLIC_RATE_LIMIT_WINDOW_MINUTES): void
{
    $pdo = get_db_connection();
    $ip = get_client_ip();
    $windowStart = (new DateTimeImmutable('-' . $windowMinutes . ' minutes'))->format('Y-m-d H:i:s');

    $delete = $pdo->prepare('DELETE FROM rate_limits WHERE created_at < :window_start');
    $delete->execute([':window_start' => $windowStart]);

    $count = $pdo->prepare('SELECT COUNT(*) FROM rate_limits WHERE ip_address = :ip_address AND action = :action AND created_at >= :window_start');
    $count->execute([
        ':ip_address' => $ip,
        ':action' => $action,
        ':window_start' => $windowStart,
    ]);

    if ((int) $count->fetchColumn() >= $maxAttempts) {
        json_response(false, 'Too many attempts. Please try again later.', 429);
    }

    $insert = $pdo->prepare('INSERT INTO rate_limits (ip_address, action, created_at) VALUES (:ip_address, :action, NOW())');
    $insert->execute([
        ':ip_address' => $ip,
        ':action' => $action,
    ]);
}

function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function admin_session_cookie_name(): string
{
    return 'tulip_admin_session';
}

function admin_csrf_cookie_name(): string
{
    return 'tulip_admin_csrf';
}

function get_admin_session_token(): ?string
{
    $token = trim((string) ($_COOKIE[admin_session_cookie_name()] ?? ''));
    return $token !== '' ? $token : null;
}

function admin_csrf_token_for_session(string $sessionToken): string
{
    return hash_hmac('sha256', 'tulip-admin-csrf', $sessionToken);
}

function set_admin_auth_cookies(string $sessionToken, bool $rememberMe): void
{
    $secure = is_https_request() || (defined('APP_ENV') && APP_ENV === 'production');
    $expires = $rememberMe ? time() + (max(1, (int) ADMIN_SESSION_HOURS) * 3600) : 0;
    $common = [
        'expires' => $expires,
        'path' => '/',
        'secure' => $secure,
        'samesite' => 'Strict',
    ];

    setcookie(admin_session_cookie_name(), $sessionToken, $common + ['httponly' => true]);
    setcookie(admin_csrf_cookie_name(), admin_csrf_token_for_session($sessionToken), $common + ['httponly' => false]);
}

function clear_admin_auth_cookies(): void
{
    $secure = is_https_request() || (defined('APP_ENV') && APP_ENV === 'production');
    $options = [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'samesite' => 'Strict',
    ];

    setcookie(admin_session_cookie_name(), '', $options + ['httponly' => true]);
    setcookie(admin_csrf_cookie_name(), '', $options + ['httponly' => false]);
}

function validate_admin_csrf(string $sessionToken): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    $origin = get_request_origin();
    if ($origin !== '' && !is_cors_origin_allowed($origin)) {
        json_response(false, 'Request origin is not allowed.', 403);
    }

    $headerToken = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $cookieToken = trim((string) ($_COOKIE[admin_csrf_cookie_name()] ?? ''));
    $expectedToken = admin_csrf_token_for_session($sessionToken);

    if (
        $headerToken === ''
        || $cookieToken === ''
        || !hash_equals($cookieToken, $headerToken)
        || !hash_equals($expectedToken, $headerToken)
    ) {
        json_response(false, 'Invalid CSRF token.', 403);
    }
}

function require_admin_auth(): array
{
    $token = get_admin_session_token();

    if ($token === null || $token === '') {
        json_response(false, 'Authentication required.', 401);
    }

    validate_admin_csrf($token);

    $tokenHash = hash('sha256', $token);
    $pdo = get_db_connection();
    $idleMinutes = defined('ADMIN_SESSION_IDLE_MINUTES')
        ? max(5, (int) ADMIN_SESSION_IDLE_MINUTES)
        : 30;
    $idleCutoff = (new DateTimeImmutable('-' . $idleMinutes . ' minutes'))->format('Y-m-d H:i:s');

    $revokeExpired = $pdo->prepare(
        "UPDATE admin_sessions
         SET revoked_at = NOW()
         WHERE token_hash = :token_hash
           AND revoked_at IS NULL
           AND (
               expires_at <= NOW()
               OR last_used_at IS NULL
               OR last_used_at <= :idle_cutoff
           )"
    );
    $revokeExpired->execute([
        ':token_hash' => $tokenHash,
        ':idle_cutoff' => $idleCutoff,
    ]);

    $stmt = $pdo->prepare(
        "SELECT s.id AS session_id, s.expires_at, s.last_used_at,
                u.id, u.name, u.email, u.role, u.is_active
         FROM admin_sessions s
         INNER JOIN admin_users u ON u.id = s.admin_user_id
         WHERE s.token_hash = :token_hash
           AND s.revoked_at IS NULL
           AND s.expires_at > NOW()
           AND s.last_used_at > :idle_cutoff
         LIMIT 1"
    );
    $stmt->execute([
        ':token_hash' => $tokenHash,
        ':idle_cutoff' => $idleCutoff,
    ]);
    $session = $stmt->fetch();

    if (!$session) {
        clear_admin_auth_cookies();
        json_response(false, 'Invalid or expired session.', 401);
    }

    if ((int) $session['is_active'] !== 1) {
        $revokeInactive = $pdo->prepare(
            'UPDATE admin_sessions SET revoked_at = NOW() WHERE id = :id AND revoked_at IS NULL'
        );
        $revokeInactive->execute([':id' => $session['session_id']]);
        clear_admin_auth_cookies();
        json_response(false, 'Invalid or expired session.', 401);
    }

    $touch = $pdo->prepare('UPDATE admin_sessions SET last_used_at = NOW() WHERE id = :id');
    $touch->execute([':id' => $session['session_id']]);

    return [
        'id' => (int) $session['id'],
        'name' => $session['name'],
        'email' => $session['email'],
        'role' => $session['role'],
    ];
}


function ensure_directory_exists(string $directory): bool
{
    $directory = rtrim($directory, DIRECTORY_SEPARATOR);

    if ($directory === '') {
        return false;
    }

    if (is_dir($directory)) {
        return is_writable($directory);
    }

    if (file_exists($directory) && !is_dir($directory)) {
        error_log('Directory path exists but is not a directory: ' . $directory);
        return false;
    }

    $created = mkdir($directory, 0755, true);

    if (!$created && !is_dir($directory)) {
        error_log('Unable to create directory: ' . $directory);
        return false;
    }

    return is_writable($directory);
}

function format_money_amount(float|int|string $amount): string
{
    $numericAmount = is_numeric($amount) ? (float) $amount : 0.0;
    $currency = defined('PAYMENT_CURRENCY') ? PAYMENT_CURRENCY : '';
    return trim($currency . ' ' . number_format($numericAmount, 2, '.', ','));
}

function format_amount_only(float|int|string $amount): string
{
    $numericAmount = is_numeric($amount) ? (float) $amount : 0.0;
    return number_format($numericAmount, 2, '.', ',');
}

function email_safe(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function generate_payhere_notify_hash(
    string $merchantId,
    string $orderId,
    string $payhereAmount,
    string $payhereCurrency,
    string $statusCode,
    string $merchantSecret
): string {
    $hashedSecret = strtoupper(md5($merchantSecret));
    return strtoupper(md5($merchantId . $orderId . $payhereAmount . $payhereCurrency . $statusCode . $hashedSecret));
}

function send_plain_email(string $to, string $subject, string $message, ?string $replyTo = null): bool
{
    try {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            error_log('PHPMailer class not found. Check vendor/autoload.php path.');
            return false;
        }

        $smtpHost = defined('SMTP_HOST') ? trim((string) SMTP_HOST) : '';
        $smtpUser = defined('SMTP_USER') ? trim((string) SMTP_USER) : '';
        $smtpPass = defined('SMTP_PASS') ? trim((string) SMTP_PASS) : '';
        $smtpPort = defined('SMTP_PORT') ? (int) SMTP_PORT : 0;
        $smtpSecure = defined('SMTP_SECURE') ? strtolower(trim((string) SMTP_SECURE)) : 'tls';
        $fromEmail = defined('FROM_EMAIL') ? trim((string) FROM_EMAIL) : '';
        $fromName = defined('FROM_NAME') ? trim((string) FROM_NAME) : 'Tulip Guest Inn';

        if (
            $smtpHost === '' ||
            $smtpUser === '' ||
            $smtpPass === '' ||
            $smtpPort < 1 ||
            $fromEmail === ''
        ) {
            error_log('SMTP configuration missing. Host/User/Password/Port/FromEmail required.');
            return false;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log('Invalid recipient email: ' . $to);
            return false;
        }

        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            error_log('Invalid FROM_EMAIL: ' . $fromEmail);
            return false;
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();

        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->Port = $smtpPort;

        if ($smtpSecure === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtpSecure === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
        }

        if (APP_ENV === 'local') {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo);
        }

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $message;

        return $mail->send();
    } catch (Throwable $e) {
        error_log('PHPMailer send failed: ' . $e->getMessage());
        return false;
    }
}

function calculate_nights(string $checkInDate, string $checkOutDate): int
{
    $checkIn = new DateTime($checkInDate);
    $checkOut = new DateTime($checkOutDate);
    return max(1, (int) $checkIn->diff($checkOut)->days);
}

function get_room_base_price(string $roomName): float
{
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT base_price FROM rooms WHERE room_name = :room_name AND status = 'Available' LIMIT 1");
        $stmt->execute([':room_name' => $roomName]);
        $price = $stmt->fetchColumn();

        if ($price !== false && is_numeric($price)) {
            return (float) $price;
        }
    } catch (Throwable $e) {
        error_log('Room price lookup failed: ' . $e->getMessage());
    }

    $roomRates = defined('ROOM_RATES') && is_array(ROOM_RATES) ? ROOM_RATES : [];
    return (float) ($roomRates[$roomName] ?? 0);
}

function calculate_booking_amount(string $roomName, string $checkInDate, string $checkOutDate): float
{
    return get_room_base_price($roomName) * calculate_nights($checkInDate, $checkOutDate);
}
