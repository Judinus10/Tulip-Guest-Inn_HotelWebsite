<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/contact_helpers.php';
require_once __DIR__ . '/../mail/templates/booking-customizable-template.php';
require_once __DIR__ . '/booking-email-preview-data.php';
apply_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
require_admin_auth();
header('Cache-Control: no-store');
try {
    $input = read_request_data();
    $type = booking_email_editor_type($input['type'] ?? null);
    $content = booking_email_editor_validate($input['content'] ?? null);
    $scenario = $input['scenario'] ?? 'single';
    if (!is_string($scenario)) throw new InvalidArgumentException('Choose a sample booking.');
    $booking = booking_email_preview_data($scenario);
    $pdo = get_db_connection();
    ensure_contact_settings_table($pdo);
    $brand = tulip_booking_email_brand($pdo);
    $preview = tulip_render_booking_email($type, $content, $booking, $brand, '', true);
    json_response(true, 'Email preview prepared.', 200, ['data' => $preview]);
} catch (InvalidArgumentException $e) {
    json_response(false, $e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('Booking email preview error: ' . $e->getMessage());
    json_response(false, 'Could not prepare the email preview. Please try again.', 500);
}
