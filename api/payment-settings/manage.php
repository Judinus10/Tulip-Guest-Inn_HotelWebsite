<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';
apply_cors_headers();
$admin = require_payment_owner();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) json_response(false, 'Method not allowed.', 405);
try {
    $pdo = get_db_connection(); payment_settings_table($pdo);
    if ($method === 'GET') {
        $row = $pdo->query('SELECT * FROM hotel_payment_settings WHERE id = 1')->fetch();
        json_response(true, 'Settings loaded.', 200, ['data' => payment_public_row($row)]);
    }
    $data = read_request_data();
    confirm_payment_password($pdo, (int)$admin['id'], (string)($data['admin_password'] ?? ''));
    if (!is_bool($data['pay_on_arrival'] ?? null) || !is_bool($data['payhere_enabled'] ?? null)) json_response(false, 'Invalid payment method selection.', 422);
    $arrival = $data['pay_on_arrival']; $online = $data['payhere_enabled'];
    if (!$arrival && !$online) json_response(false, 'At least one payment method must remain enabled.', 422);
    $environment = (string)($data['environment'] ?? '');
    if (!in_array($environment, ['sandbox', 'live'], true)) json_response(false, 'Invalid environment.', 422);
    $merchant = trim((string)($data['merchant_id'] ?? ''));
    $secret = (string)($data['merchant_secret'] ?? '');
    if (strlen($merchant) > 100 || strlen($secret) > 512) json_response(false, 'Invalid credential length.', 422);
    $pdo->beginTransaction();
    $stmt = $pdo->query('SELECT * FROM hotel_payment_settings WHERE id = 1 FOR UPDATE'); $existing = $stmt->fetch();
    // Avoid invalidating callbacks for in-flight PayHere orders during credential rotation.
    if ($environment !== $existing['environment'] || $merchant !== $existing['merchant_id'] || $secret !== '') {
        $pending = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'Payment Pending' AND order_id LIKE 'ONLINE-%'")->fetchColumn();
        if ((int)$pending > 0 && !empty($existing['encrypted_secret'])) {
            $pdo->rollBack();
            json_response(false, 'PayHere credentials cannot be changed while online payments are pending. Complete or resolve those payments first.', 409);
        }
    }
    // Never carry a secret across environments or merchant IDs.
    $changedIdentity = $environment !== $existing['environment'] || $merchant !== $existing['merchant_id'];
    $encrypted = $changedIdentity ? null : $existing['encrypted_secret'];
    if ($secret !== '') $encrypted = encrypt_payment_secret($secret);
    if ($online && ($merchant === '' || !$encrypted)) { $pdo->rollBack(); json_response(false, 'Enter Merchant ID and Merchant Secret before enabling PayHere.', 422); }
    // Even if online is disabled, reject a new secret if the server has no encryption key.
    $save = $pdo->prepare('UPDATE hotel_payment_settings SET pay_on_arrival=?,payhere_enabled=?,environment=?,merchant_id=?,encrypted_secret=? WHERE id=1');
    $save->execute([(int)$arrival, (int)$online, $environment, $merchant, $encrypted]);
    $pdo->commit();
    $row = $pdo->query('SELECT * FROM hotel_payment_settings WHERE id=1')->fetch();
    json_response(true, 'Payment configuration saved.', 200, ['data' => payment_public_row($row)]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Payment settings error: ' . $e->getMessage());
    json_response(false, 'Unable to load or save payment settings. Check server configuration.', 500);
}
