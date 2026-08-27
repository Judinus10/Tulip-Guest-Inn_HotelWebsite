<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider_helpers.php';
apply_cors_headers();
$admin = require_mail_super_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);

try {
    $pdo = get_db_connection();
    $data = read_request_data();
    $provider = strtolower(clean_string($data['provider'] ?? '', 40));
    $recipient = strtolower(clean_string($data['recipient_email'] ?? '', 190));
    if (!in_array($provider, MAIL_PROVIDERS, true)) json_response(false, 'Select a supported mail provider.', 422);
    $config = provider_setting($pdo, $provider);
    if (!$config) json_response(false, 'Save this mail configuration before testing it.', 422);
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) json_response(false, 'Enter a valid diagnostics destination.', 422, ['field'=>'recipient_email']);
    if ($provider === 'microsoft_graph' && !empty($config['oauth_client_id']) && !empty($config['encrypted_client_secret'])) send_via_microsoft_graph($config,$recipient,'Tulip Guest Inn mail connection test','<p>This message confirms that Microsoft Graph mail delivery is connected.</p>');
    else send_via_provider_smtp($config,$recipient,'Tulip Guest Inn mail connection test','<p>This message confirms that outgoing mail delivery is connected.</p>');
    $pdo->beginTransaction();
    $pdo->exec('UPDATE mail_provider_settings SET is_active=0');
    $pdo->prepare("UPDATE mail_provider_settings SET is_active=1,connection_status='connected',last_tested_at=NOW(),last_test_message='Connection and test email succeeded.' WHERE provider=:provider")->execute([':provider'=>$provider]);
    mail_settings_audit($pdo,$admin,'tested','mail_provider',$provider,'Connection test succeeded and provider became active.');
    $pdo->commit();
    json_response(true, 'Connection successful. A diagnostics email was sent and this provider is now active.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if (isset($pdo,$provider) && in_array($provider,MAIL_PROVIDERS,true)) $pdo->prepare("UPDATE mail_provider_settings SET is_active=0,connection_status='failed',last_tested_at=NOW(),last_test_message=:message WHERE provider=:provider")->execute([':message'=>mb_substr($e->getMessage(),0,500),':provider'=>$provider]);
    error_log('Provider connection test failed: ' . $e->getMessage());
    json_response(false, $e->getMessage(), 422, ['error_code'=>'MAIL_CONNECTION_FAILED']);
}

