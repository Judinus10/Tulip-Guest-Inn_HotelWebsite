<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/booking_email_helpers.php';
apply_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) json_response(false, 'Method not allowed.', 405);
require_admin_auth(); // Existing session authentication and write CSRF validation.
header('Cache-Control: no-store');
try {
    $pdo = get_db_connection();
    booking_email_editor_init($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        json_response(true, 'Booking email settings loaded.', 200, ['data' => booking_email_editor_read($pdo)]);
    }
    $template = booking_email_editor_save($pdo, read_request_data());
    json_response(true, 'Email content saved.', 200, ['data' => $template]);
} catch (InvalidArgumentException $e) {
    json_response(false, $e->getMessage(), 422);
} catch (Throwable $e) {
    if ($e->getCode() === 409) json_response(false, $e->getMessage(), 409);
    error_log('Booking email editor error: ' . $e->getMessage());
    json_response(false, 'Could not process booking email settings. Please try again.', 500);
}
