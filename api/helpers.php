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
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

function apply_cors_headers(): void
{
    apply_security_headers();

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = defined('ALLOWED_ORIGINS') ? ALLOWED_ORIGINS : [];

    if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With, Cache-Control, Pragma, X-HTTP-Method-Override');
    header('Access-Control-Max-Age: 86400');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function json_response(bool $success, string $message, int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function read_request_data(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (is_array($data)) {
        return $data;
    }

    return $_POST ?: [];
}

function clean_string(mixed $value, int $maxLength = 1000): string
{
    $value = trim((string) $value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr($value, 0, $maxLength);
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

function get_bearer_token(): ?string
{
    $headers = [];

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }

    $authorization = $headers['Authorization']
        ?? $headers['authorization']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if ($authorization === '' && function_exists('apache_request_headers')) {
        $apacheHeaders = apache_request_headers();
        $authorization = $apacheHeaders['Authorization']
            ?? $apacheHeaders['authorization']
            ?? '';
    }

    if (!preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
        return null;
    }

    return trim($matches[1]);
}
function require_admin_auth(): array
{
    $token = get_bearer_token();

    if ($token === null || $token === '') {
        json_response(false, 'Authentication required.', 401);
    }

    $tokenHash = hash('sha256', $token);
    $pdo = get_db_connection();

    $stmt = $pdo->prepare(
        "SELECT s.id AS session_id, s.expires_at, u.id, u.name, u.email, u.role, u.is_active
         FROM admin_sessions s
         INNER JOIN admin_users u ON u.id = s.admin_user_id
         WHERE s.token_hash = :token_hash
           AND s.revoked_at IS NULL
           AND s.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([':token_hash' => $tokenHash]);
    $session = $stmt->fetch();

    if (!$session || (int) $session['is_active'] !== 1) {
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
        $fromName = defined('FROM_NAME') ? trim((string) FROM_NAME) : 'Jebal Guest House';

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
