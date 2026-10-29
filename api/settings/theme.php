<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/contact_helpers.php';
apply_cors_headers();
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) json_response(false, 'Method not allowed.', 405);
// The palette is public; changing it still requires admin authentication and CSRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST') require_admin_auth();
function theme_default_colors(): array {
    return ['primary'=>'#C7A86B','secondary'=>'#19352B','background'=>'#FAF8F4','surface'=>'#FFFFFF','text'=>'#1A1A1A','muted'=>'#6B7280','footer'=>'#1A1A1A','border'=>'#ECE7DF'];
}
function theme_valid_colors(mixed $value): array {
    $defaults = theme_default_colors();
    if (!is_array($value) || array_diff(array_keys($value), array_keys($defaults)) || array_diff(array_keys($defaults), array_keys($value))) throw new InvalidArgumentException('Provide all eight theme colours.');
    $clean = [];
    foreach ($defaults as $key => $_) {
        if (!is_string($value[$key]) || !preg_match('/^#[0-9a-fA-F]{6}$/D', trim($value[$key]))) throw new InvalidArgumentException('Enter a six-digit HEX colour for ' . $key . '.');
        $clean[$key] = strtoupper(trim($value[$key]));
    }
    return $clean;
}
try {
    $pdo = get_db_connection();
    ensure_contact_settings_table($pdo);
    $defaults = theme_default_colors();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare('SELECT setting_value FROM website_settings WHERE setting_key = ?');
        $stmt->execute(['website_theme_colors']);
        $stored = $stmt->fetchColumn();
        $colors = $stored === false ? $defaults : theme_valid_colors(json_decode((string) $stored, true));
        header('Cache-Control: no-store');
        json_response(true, 'Appearance settings loaded.', 200, ['data'=>['colors'=>$colors,'defaults'=>$defaults]]);
    }
    $input = read_request_data();
    $colors = theme_valid_colors($input['colors'] ?? null);
    $stmt = $pdo->prepare('INSERT INTO website_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute(['website_theme_colors', json_encode($colors, JSON_THROW_ON_ERROR)]);
    json_response(true, 'Appearance settings saved.', 200, ['data'=>['colors'=>$colors,'defaults'=>$defaults]]);
} catch (InvalidArgumentException $e) {
    json_response(false, $e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('Theme settings error: ' . $e->getMessage());
    json_response(false, 'Could not process appearance settings. Please try again.', 500);
}
