<?php
/**
 * Invoice download endpoint.
 * Admins can download using bearer/session auth.
 * Guests can download using signed token links:
 * /api/invoices/download.php?id=BOOKING_ID&token=SIGNED_TOKEN
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../security/public-token-helper.php';
require_once __DIR__ . '/invoice-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Only GET requests are allowed.', 405);
}

$bookingId = (int) ($_GET['id'] ?? 0);
$token = clean_string($_GET['token'] ?? '', 1024);

if ($bookingId < 1) {
    header('Content-Type: application/json; charset=utf-8');
    json_response(false, 'Valid booking ID is required.', 422);
}

function invoice_download_token(int $bookingId): string
{
    return hash_hmac('sha256', (string) $bookingId, PAYHERE_MERCHANT_SECRET);
}

$hasGuestToken = $token !== '' && verify_public_token($token, 'invoice-download', ['booking_id' => $bookingId]);
if (!$hasGuestToken && $token !== '' && legacy_public_tokens_allowed()) {
    $hasGuestToken = hash_equals(invoice_download_token($bookingId), $token);
}

if (!$hasGuestToken) {
    require_admin_auth();
} else {
    rate_limit_or_fail('invoice_download', 20, 15);
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare(
        'SELECT invoice_number, payment_status
         FROM bookings
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        header('Content-Type: application/json; charset=utf-8');
        json_response(false, 'Booking not found.', 404);
    }

    $paymentStmt = $pdo->prepare(
        'SELECT *
         FROM payments
         WHERE booking_id = :booking_id
         ORDER BY id DESC
         LIMIT 1'
    );
    $paymentStmt->execute([':booking_id' => $bookingId]);
    $payment = $paymentStmt->fetch() ?: [];

    $invoice = build_invoice_data_for_booking($pdo, $bookingId, $payment);
    if (!$invoice) {
        header('Content-Type: application/json; charset=utf-8');
        json_response(false, 'Booking confirmation could not be generated.', 404);
    }

    $isPaid = strcasecmp((string) ($booking['payment_status'] ?? ''), 'Paid') === 0;
    $documentNumber = $isPaid
        ? (string) ($invoice['invoice_number'] ?: generate_invoice_number($bookingId))
        : 'BK-' . str_pad((string) $bookingId, 5, '0', STR_PAD_LEFT);
    $invoiceNumber = preg_replace('/[^A-Za-z0-9_-]/', '', $documentNumber) ?: 'booking-confirmation';
    $pdf = create_invoice_pdf_binary($invoice);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $invoiceNumber . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    echo $pdf;
    exit;
} catch (Throwable $e) {
    error_log('Invoice download error: ' . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    json_response(false, 'Unable to download invoice.', 500);
}
