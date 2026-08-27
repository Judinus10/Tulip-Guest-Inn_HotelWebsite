<?php
declare(strict_types=1);
require_once __DIR__ . '/_mail_settings_helpers.php';
require_once __DIR__ . '/_provider_helpers.php';
apply_cors_headers();
require_mail_super_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(false, 'Only GET requests are allowed.', 405);

try {
    $pdo = get_db_connection();
    ensure_mail_settings_tables($pdo);
    ensure_mail_provider_table($pdo);
    $accounts = $pdo->query('SELECT * FROM mail_accounts ORDER BY account_name, id')->fetchAll(PDO::FETCH_ASSOC);
    $functionRows = $pdo->query('SELECT mail_account_id, function_key FROM mail_account_functions ORDER BY function_key')->fetchAll(PDO::FETCH_ASSOC);
    $byAccount = [];
    foreach ($functionRows as $row) $byAccount[(int) $row['mail_account_id']][] = (string) $row['function_key'];
    $routes = $pdo->query('SELECT * FROM mail_routing_rules ORDER BY function_key')->fetchAll(PDO::FETCH_ASSOC);
    json_response(true, 'Mail settings loaded.', 200, ['data' => [
        'accounts' => array_map(fn($row) => public_mail_account($row, $byAccount[(int) $row['id']] ?? []), $accounts),
        'functions' => array_map(fn($key, $label) => ['key' => $key, 'label' => $label], array_keys(MAIL_FUNCTIONS), array_values(MAIL_FUNCTIONS)),
        'routes' => array_map(static function ($row) {
            $functionKey = (string) $row['function_key'];
            $policy = mail_delivery_policy($functionKey);
            return [
            'function_key' => $functionKey,
            'sender_account_id' => $row['sender_account_id'] !== null ? (int) $row['sender_account_id'] : null,
            'hotel_recipient_email' => (string) ($row['hotel_recipient_email'] ?? ''),
            'delivery_label' => (string) $policy['label'],
            'send_customer_copy' => (bool) $policy['customer'],
            'send_hotel_copy' => (bool) $policy['hotel'],
            'is_enabled' => (bool) $row['is_enabled'],
        ]; }, $routes),
        'providers' => array_values(array_map(static fn($provider) => public_provider_setting(provider_setting($pdo, $provider), $provider), MAIL_PROVIDERS)),
    ]]);
} catch (Throwable $e) {
    error_log('Mail settings list failed: ' . $e->getMessage());
    json_response(false, 'Mail settings could not be loaded. Check the database migration and server encryption configuration.', 500);
}
