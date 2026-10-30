<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/booking_email_helpers.php';
require_once __DIR__ . '/email_branding_helpers.php';
apply_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) json_response(false, 'Method not allowed.', 405);
require_admin_auth(); // Existing session authentication and write CSRF validation.
header('Cache-Control: no-store');
try {
    $pdo = get_db_connection();
    // Schema initialization must stay outside the save transaction.
    booking_email_editor_init($pdo);
    email_branding_init($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $data = booking_email_editor_read($pdo);
        $data['branding'] = email_branding_payload($pdo);
        json_response(true, 'Email settings loaded.', 200, ['data' => $data]);
    }
    $input = read_request_data();
    $resize = array_key_exists('logo_width', $input);
    if ($resize && (!is_int($input['logo_width']) || $input['logo_width'] < 40 || $input['logo_width'] > 240
        || !is_int($input['branding_version'] ?? null) || $input['branding_version'] < 0 || $input['branding_version'] > 2147483646)) {
        throw new InvalidArgumentException('Logo size must be 40–240 pixels. Reload before saving if the settings are outdated.');
    }
    $pdo->beginTransaction();
    if ($resize) {
        $row = $pdo->query('SELECT version FROM website_email_branding WHERE id=1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int) $row['version'] !== $input['branding_version']) {
            throw new RuntimeException('Email logo size changed in another session. Reload before saving.', 409);
        }
        $stmt = $pdo->prepare('UPDATE website_email_branding SET logo_width=?, version=version+1 WHERE id=1');
        $stmt->execute([$input['logo_width']]); // Resize only: preserve the saved logo file.
    }
    $template = booking_email_editor_save($pdo, $input);
    $template['branding'] = email_branding_payload($pdo);
    $pdo->commit();
    json_response(true, 'Email changes saved.', 200, ['data' => $template]);
} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    json_response(false, $e->getMessage(), 422);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if ($e->getCode() === 409) json_response(false, $e->getMessage(), 409);
    error_log('Email editor error: ' . $e->getMessage());
    json_response(false, 'Could not process email settings. Please try again.', 500);
}
