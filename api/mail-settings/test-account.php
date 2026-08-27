<?php
declare(strict_types=1);
require_once __DIR__ . '/_mail_settings_helpers.php';
apply_cors_headers();
$admin = require_mail_super_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);

try {
    $pdo = get_db_connection();
    ensure_mail_settings_tables($pdo);
    $data = read_request_data();
    $id = (int) ($data['id'] ?? 0);
    $recipient = strtolower(clean_string($data['recipient_email'] ?? '', 190));
    $stmt = $pdo->prepare('SELECT * FROM mail_accounts WHERE id=:id LIMIT 1');
    $stmt->execute([':id'=>$id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) json_response(false, 'The selected mail account no longer exists.', 404);
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) $recipient = (string) $account['email_address'];

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) throw new RuntimeException('The server mail library is unavailable.');
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = (string) $account['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Timeout = 15;
    $mail->Username = (string) $account['smtp_username'];
    $mail->Password = decrypt_mail_secret((string) $account['encrypted_password']);
    $mail->Port = (int) $account['smtp_port'];
    $secure = strtolower((string) $account['smtp_encryption']);
    $mail->SMTPSecure = $secure === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : ($secure === 'tls' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : '');
    $mail->CharSet = 'UTF-8';
    $mail->setFrom((string) $account['email_address'], (string) $account['from_name']);
    $mail->addAddress($recipient);
    $mail->Subject = 'Tulip Guest Inn mail connection test';
    $mail->Body = 'This message confirms that the Office 365 mail account is connected to the Tulip Guest Inn admin system.';
    $mail->send();

    $pdo->prepare("UPDATE mail_accounts SET connection_status='connected',is_enabled=1,last_tested_at=NOW(),last_test_message='Connection and test email succeeded.' WHERE id=:id")->execute([':id'=>$id]);
    mail_settings_audit($pdo, $admin, 'tested', 'mail_account', (string) $id, 'Connection test succeeded for ' . $account['email_address'] . '.');
    json_response(true, 'Connection successful. A test email was sent and this account is now active.');
} catch (Throwable $e) {
    if (isset($pdo, $id) && $id > 0) {
        $pdo->prepare("UPDATE mail_accounts SET connection_status='failed',is_enabled=0,last_tested_at=NOW(),last_test_message=:message WHERE id=:id")->execute([':message'=>mb_substr($e->getMessage(),0,500), ':id'=>$id]);
    }
    error_log('Mail account test failed: ' . $e->getMessage());
    json_response(false, 'Office 365 rejected the connection. Confirm the email, password, SMTP AUTH permission and port 587 TLS settings.', 422, ['error_code'=>'MAIL_CONNECTION_FAILED']);
}
