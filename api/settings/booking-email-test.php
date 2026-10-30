<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/contact_helpers.php';
require_once __DIR__ . '/../mail/email-helper.php';
require_once __DIR__ . '/booking-email-preview-data.php';
require_once __DIR__ . '/../mail/templates/contact-customizable-template.php';
require_once __DIR__ . '/contact-email-preview-data.php';
apply_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
require_admin_auth();
header('Cache-Control: no-store');
try {
    $input = read_request_data();
    $recipient = $input['recipient'] ?? null;
    if (!is_string($recipient) || strlen($recipient) > 190 || !filter_var(trim($recipient), FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter one valid recipient email address.');
    }
    $type = booking_email_editor_type($input['type'] ?? null);
    $content = booking_email_editor_validate($input['content'] ?? null, $type);
    $scenario = $input['scenario'] ?? 'single';
    if (!is_string($scenario)) throw new InvalidArgumentException('Choose a sample booking.');
    $isAdmin = str_starts_with($type, 'admin_');
    $isContact = str_starts_with($type, 'contact_');
    $booking = $isContact ? tulip_contact_sample() : ($isAdmin ? booking_email_admin_preview_data($type, $scenario) : booking_email_preview_data($scenario));
    $booking['is_test_email'] = true;
    rate_limit_or_fail('booking_email_test', 3, 10);
    $pdo = get_db_connection();
    ensure_contact_settings_table($pdo);
    $brand = tulip_booking_email_brand($pdo, false);
    $email = $isContact ? tulip_render_contact_email($type, $content, $booking, $brand) : tulip_render_booking_email($type, $content, $booking, $brand);
    $queued = enqueue_email($pdo, 'booking_email_test', null, trim($recipient), '[TEST] ' . $email['subject'], $email['html'],
        ($isContact ? ($type === 'contact_customer' ? 'contact_auto_template_test' : 'contact_template_test') : ($isAdmin && $type !== 'admin_new_booking' ? 'booking_payment_template_test' : 'booking_template_test')), null, 3, ($isContact ? contact_from_email() : booking_from_email()), ($isContact ? contact_from_name() : booking_from_name()));
    if (!$queued) json_response(false, 'The test email could not be queued. Check that booking email delivery is enabled in Mail Integrations.', 503);
    json_response(true, 'Test email queued. It will be sent by the email queue worker.', 200, ['data' => ['queued' => true]]);
} catch (InvalidArgumentException $e) {
    json_response(false, $e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('Booking email test error: ' . $e->getMessage());
    json_response(false, 'Could not queue the test email. Please try again.', 500);
}
