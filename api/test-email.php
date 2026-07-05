<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail/email-helper.php';

use PHPMailer\PHPMailer\PHPMailer;

function test_email_value(string $key, string $default = ''): string
{
    if (function_exists('jebal_env_value')) {
        return trim((string) jebal_env_value($key, $default));
    }

    return trim($default);
}

function test_email_bool(string $key, bool $default = false): bool
{
    $value = strtolower(test_email_value($key, $default ? 'true' : 'false'));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

$defaultTo = test_email_value('TEST_EMAIL_TO', 'jjudinas@gmail.com');
$to = $defaultTo;

$profile = strtolower(trim((string) ($_GET['profile'] ?? test_email_value('TEST_EMAIL_PROFILE', 'contact'))));

if ($profile === 'booking') {
    $fromEmail = booking_from_email();
    $fromName = booking_from_name();
} elseif ($profile === 'admin' || $profile === 'reminder') {
    $fromEmail = admin_from_email();
    $fromName = admin_from_name();
    $profile = 'admin';
} else {
    $fromEmail = contact_from_email();
    $fromName = contact_from_name();
    $profile = 'contact';
}

$overrideFromEmail = test_email_value('TEST_FROM_EMAIL');
$overrideFromName = test_email_value('TEST_FROM_NAME');

if ($overrideFromEmail !== '') {
    $fromEmail = $overrideFromEmail;
}
if ($overrideFromName !== '') {
    $fromName = $overrideFromName;
}

if (isset($_GET['to']) && filter_var((string) $_GET['to'], FILTER_VALIDATE_EMAIL)) {
    $to = trim((string) $_GET['to']);
}

header('Content-Type: text/html; charset=UTF-8');

echo '<!doctype html><html><head><meta charset="utf-8"><title>Jebal SMTP Test</title></head><body style="font-family:Arial,sans-serif;line-height:1.5;">';
echo '<h1>Jebal SMTP Test</h1>';

$smtpProfile = email_smtp_profile_for_from($fromEmail);

$debugEnabled = isset($_GET['debug']) ? $_GET['debug'] !== '0' : test_email_bool('TEST_EMAIL_DEBUG', true);

$host = trim((string) ($smtpProfile['host'] ?? ''));
$user = trim((string) ($smtpProfile['user'] ?? ''));
$pass = (string) ($smtpProfile['pass'] ?? '');
$port = (int) ($smtpProfile['port'] ?? 587);
$secure = strtolower(trim((string) ($smtpProfile['secure'] ?? 'tls')));

if ($to === '') {
    $to = 'jjudinas@gmail.com';
}

echo '<p><strong>Profile:</strong> ' . htmlspecialchars($profile, ENT_QUOTES, 'UTF-8') . '</p>';
echo '<p><strong>SMTP Host:</strong> ' . htmlspecialchars($host, ENT_QUOTES, 'UTF-8') . '</p>';
echo '<p><strong>SMTP User:</strong> ' . htmlspecialchars($user, ENT_QUOTES, 'UTF-8') . '</p>';
echo '<p><strong>SMTP Port/Secure:</strong> ' . htmlspecialchars((string) $port . ' / ' . $secure, ENT_QUOTES, 'UTF-8') . '</p>';
echo '<p><strong>From:</strong> ' . htmlspecialchars($fromEmail . ' (' . $fromName . ')', ENT_QUOTES, 'UTF-8') . '</p>';
echo '<p><strong>To:</strong> ' . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '</p>';

echo '<hr>';

try {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Test recipient email is missing/invalid. Use api/.env TEST_EMAIL_TO=jjudinas@gmail.com or call test-email.php?to=jjudinas@gmail.com');
    }

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('FROM email is missing/invalid for profile: ' . $profile);
    }

    if ($host === '') {
        throw new RuntimeException('SMTP host is missing. Check api/.env SMTP_HOST.');
    }

    if (!filter_var($user, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('SMTP username is missing/invalid. For Gmail this must usually be the full email address.');
    }

    if ($pass === '') {
        throw new RuntimeException('SMTP password is missing. For Gmail, use a Google App Password, not the normal Gmail password.');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPDebug = $debugEnabled ? 2 : 0;
    $mail->Debugoutput = static function ($str, $level): void {
        echo 'DEBUG [' . (int) $level . '] : ' . htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8') . '<br>';
    };

    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $pass;
    $mail->Port = $port;
    $mail->Timeout = 30;

    if ($secure === 'ssl' || $secure === 'smtps') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($secure === 'tls' || $secure === 'starttls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
    }

    if (defined('APP_ENV') && APP_ENV === 'local') {
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
    $mail->isHTML(false);
    $mail->Subject = 'Jebal SMTP Test - ' . ucfirst($profile);
    $mail->Body = "SMTP test successful from Tulip Guest Inn using {$profile} profile.\n\nSent to: {$to}";
    $mail->send();

    echo '<h2 style="color:green;">EMAIL SENT SUCCESSFULLY</h2>';
} catch (Throwable $e) {
    echo '<h2 style="color:red;">EMAIL FAILED</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';

    if (stripos($e->getMessage(), 'authenticate') !== false || stripos($e->getMessage(), 'Password') !== false) {
        echo '<p style="color:#b00020;"><strong>Fix:</strong> Gmail rejected the SMTP login. Use a Google App Password in your api/.env SMTP password value. Do not use the normal Gmail login password.</p>';
    }
}

echo '</body></html>';
