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

    return $default;
}

$to = test_email_value('TEST_EMAIL_TO', defined('ADMIN_EMAIL') ? (string) ADMIN_EMAIL : '');
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

if (test_email_value('TEST_FROM_EMAIL') !== '') {
    $fromEmail = test_email_value('TEST_FROM_EMAIL');
}
if (test_email_value('TEST_FROM_NAME') !== '') {
    $fromName = test_email_value('TEST_FROM_NAME');
}

if (isset($_GET['to']) && filter_var((string) $_GET['to'], FILTER_VALIDATE_EMAIL)) {
    $to = trim((string) $_GET['to']);
}

header('Content-Type: text/html; charset=UTF-8');

echo '<!doctype html><html><head><meta charset="utf-8"><title>Jebal SMTP Test</title></head><body style="font-family:Arial,sans-serif;line-height:1.5;">';
echo '<h1>Jebal SMTP Test</h1>';
$smtpProfile = email_smtp_profile_for_from($fromEmail);
echo '<p><strong>Profile:</strong> ' . htmlspecialchars($profile, ENT_QUOTES, 'UTF-8') . '</p>';
echo '<p><strong>SMTP Host:</strong> ' . htmlspecialchars((string) $smtpProfile['host'], ENT_QUOTES, 'UTF-8') . '</p>';
echo '<p><strong>SMTP User:</strong> ' . htmlspecialchars((string) $smtpProfile['user'], ENT_QUOTES, 'UTF-8') . '</p>';
echo '<p><strong>SMTP Port/Secure:</strong> ' . htmlspecialchars((string) $smtpProfile['port'] . ' / ' . (string) $smtpProfile['secure'], ENT_QUOTES, 'UTF-8') . '</p>';
echo '<p><strong>From:</strong> ' . htmlspecialchars($fromEmail . ' (' . $fromName . ')', ENT_QUOTES, 'UTF-8') . '</p>';
echo '<p><strong>To:</strong> ' . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '</p>';

try {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('TEST_EMAIL_TO or ADMIN_EMAIL is missing/invalid. Add TEST_EMAIL_TO=your@email.com to api/.env or call test-email.php?to=your@email.com');
    }

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('FROM_EMAIL is missing/invalid.');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = static function ($str, $level): void {
        echo 'DEBUG [' . (int) $level . '] : ' . htmlspecialchars($str, ENT_QUOTES, 'UTF-8') . '<br>';
    };

    $mail->Host = (string) $smtpProfile['host'];
    $mail->SMTPAuth = true;
    $mail->Username = (string) $smtpProfile['user'];
    $mail->Password = (string) $smtpProfile['pass'];
    $mail->Port = (int) $smtpProfile['port'];
    $mail->Timeout = 30;

    $secure = strtolower(trim((string) $smtpProfile['secure']));
    if ($secure === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($secure === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
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
    $mail->Body = 'SMTP test successful from Jebal Guest House using ' . $profile . ' profile.';
    $mail->send();

    echo '<h2 style="color:green;">EMAIL SENT SUCCESSFULLY</h2>';
} catch (Throwable $e) {
    echo '<h2 style="color:red;">EMAIL FAILED</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
}

echo '</body></html>';
