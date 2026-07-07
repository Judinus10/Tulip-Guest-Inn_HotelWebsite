<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mail/email-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Only POST requests are allowed.', 405);
}

rate_limit_or_fail('submit_contact', 5, 15);

$data = read_request_data();
$name = clean_string($data['name'] ?? '', 150);
$email = strtolower(clean_string($data['email'] ?? '', 190));
$phone = clean_string($data['phone'] ?? '', 50);
$subject = clean_string($data['subject'] ?? 'General Inquiry', 190);
$message = clean_string($data['message'] ?? '', 5000);

if ($name === '' || $email === '' || $message === '') {
    json_response(false, 'Name, email, and message are required.', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Please provide a valid email address.', 422);
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare(
        'INSERT INTO enquiries (name, email, phone, subject, message, status, ip_address, user_agent, created_at, updated_at)
         VALUES (:name, :email, :phone, :subject, :message, :status, :ip_address, :user_agent, NOW(), NOW())'
    );
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phone,
        ':subject' => $subject,
        ':message' => $message,
        ':status' => 'New',
        ':ip_address' => get_client_ip(),
        ':user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    $id = (int) $pdo->lastInsertId();
    $ref = 'INQ-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    $queuedCount = 0;

    try {
        // Queue email notifications for the existing cron/mail worker.
        // Do not send SMTP mail during the contact form request, otherwise the frontend waits.
        $queuedCount = queue_contact_enquiry_emails(
            $pdo,
            $id,
            $name,
            $email,
            $phone,
            $subject,
            $message
        );
    } catch (Throwable $queueError) {
        // The enquiry is saved and visible to admin. Never fail the visitor message because queue insert had a problem.
        error_log('Contact email queue insert failed for enquiry #' . $id . ': ' . $queueError->getMessage());
    }

    json_response(true, 'Your message has been received successfully. Our team will respond as soon as possible.', 201, [
        'data' => [
            'id' => $id,
            'inquiry_id' => $ref,
            'email_queued' => $queuedCount > 0,
            'queued_count' => $queuedCount,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Contact submit error: ' . $e->getMessage());
    json_response(false, 'Could not save your enquiry. Please try again.', 500);
}
