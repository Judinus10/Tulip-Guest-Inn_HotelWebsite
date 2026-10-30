<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/contact_helpers.php';
require_once __DIR__ . '/../mail/templates/booking-customizable-template.php';
require_once __DIR__ . '/booking-email-preview-data.php';
require_once __DIR__ . '/../mail/templates/contact-customizable-template.php';
require_once __DIR__ . '/contact-email-preview-data.php';
require_once __DIR__ . '/../mail/templates/daily-summary-customizable-template.php';
require_once __DIR__ . '/daily-summary-preview-data.php';
apply_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
require_admin_auth();
header('Cache-Control: no-store');
try {
    $input = read_request_data();
    $type = booking_email_editor_type($input['type'] ?? null);
    $content = booking_email_editor_validate($input['content'] ?? null, $type);
    $scenario = $input['scenario'] ?? 'single';
    if (!is_string($scenario)) throw new InvalidArgumentException('Choose a sample booking.');
    $isDaily = $type === 'admin_daily_summary';
    $isContact = str_starts_with($type, 'contact_');
    $booking = $isDaily ? tulip_daily_summary_sample($scenario) : ($isContact ? tulip_contact_sample() : booking_email_event_preview_data($type, $scenario));
    $pdo = get_db_connection();
    ensure_contact_settings_table($pdo);
    $brand = tulip_booking_email_brand($pdo);
    $preview = $isDaily ? tulip_render_daily_summary_email($content, $booking, $brand, true) : ($isContact ? tulip_render_contact_email($type, $content, $booking, $brand, true) : tulip_render_booking_email($type, $content, $booking, $brand, '', true));
    json_response(true, 'Email preview prepared.', 200, ['data' => $preview]);
} catch (InvalidArgumentException $e) {
    json_response(false, $e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('Booking email preview error: ' . $e->getMessage());
    json_response(false, 'Could not prepare the email preview. Please try again.', 500);
}
