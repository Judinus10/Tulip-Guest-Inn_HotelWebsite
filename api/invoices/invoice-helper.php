<?php
/**
 * Invoice generation helper.
 * Uses a small built-in PDF writer so no Composer package is required on shared hosting.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

function generate_invoice_number(int $bookingId): string
{
    return 'JH-' . date('Y') . '-' . str_pad((string) $bookingId, 5, '0', STR_PAD_LEFT);
}

function pdf_escape_text(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function create_simple_invoice_pdf(string $filePath, array $invoice): void
{
    $lines = [
        'JEBAL HOMES',
        'Booking Payment Invoice',
        '',
        'Invoice Number: ' . $invoice['invoice_number'],
        'Booking ID: #' . $invoice['booking_id'],
        'Customer Name: ' . $invoice['customer_name'],
        'Room Name: ' . $invoice['room_name'],
        'Check-in Date: ' . $invoice['check_in_date'],
        'Check-out Date: ' . $invoice['check_out_date'],
        'Amount Paid: ' . format_money_amount((float) $invoice['amount_paid']),
        'Payment Method: ' . $invoice['payment_method'],
        'Payment Date: ' . ($invoice['payment_date'] ?: date('Y-m-d H:i:s')),
        '',
        'Thank you for choosing Jebal Homes.',
    ];

    $content = "BT\n/F1 18 Tf\n50 790 Td\n(" . pdf_escape_text($lines[0]) . ") Tj\n";
    $content .= "/F1 12 Tf\n0 -28 Td\n(" . pdf_escape_text($lines[1]) . ") Tj\n";

    foreach (array_slice($lines, 2) as $line) {
        $content .= "0 -22 Td\n(" . pdf_escape_text($line) . ") Tj\n";
    }

    $content .= "ET";

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objects[] = "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

    file_put_contents($filePath, $pdf);
}

function get_booking_by_id(PDO $pdo, int $bookingId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();
    return $booking ?: null;
}

function generate_invoice_for_booking(PDO $pdo, int $bookingId, array $payment = []): ?array
{
    $booking = get_booking_by_id($pdo, $bookingId);

    if (!$booking) {
        return null;
    }

    if (!empty($booking['invoice_id']) && !empty($booking['invoice_file_path'])) {
        $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int) $booking['invoice_id']]);
        $existing = $stmt->fetch();
        return $existing ?: null;
    }

    ensure_directory_exists(INVOICE_STORAGE_DIR);

    $invoiceNumber = generate_invoice_number($bookingId);
    $relativePath = 'storage/invoices/' . $invoiceNumber . '.pdf';
    $absolutePath = __DIR__ . '/../' . $relativePath;

    $amountPaid = (float) ($payment['amount'] ?? $booking['amount'] ?? $booking['payment_amount'] ?? 0);
    $currency = (string) ($payment['currency'] ?? $booking['currency'] ?? $booking['payment_currency'] ?? PAYMENT_CURRENCY);
    $paymentMethod = (string) ($payment['method'] ?? $payment['payment_method'] ?? 'PayHere');
    $paymentDate = (string) ($payment['paid_at'] ?? date('Y-m-d H:i:s'));

    $invoice = [
        'booking_id' => $bookingId,
        'invoice_number' => $invoiceNumber,
        'customer_name' => $booking['full_name'],
        'customer_email' => $booking['email'],
        'room_name' => $booking['room_name'],
        'check_in_date' => $booking['check_in_date'],
        'check_out_date' => $booking['check_out_date'],
        'amount_paid' => $amountPaid,
        'currency' => $currency,
        'payment_method' => $paymentMethod,
        'payment_date' => $paymentDate,
        'file_path' => $relativePath,
    ];

    create_simple_invoice_pdf($absolutePath, $invoice);

    $insert = $pdo->prepare(
        'INSERT INTO invoices
        (booking_id, invoice_number, customer_name, customer_email, room_name, check_in_date, check_out_date, amount_paid, currency, payment_method, payment_date, file_path)
        VALUES
        (:booking_id, :invoice_number, :customer_name, :customer_email, :room_name, :check_in_date, :check_out_date, :amount_paid, :currency, :payment_method, :payment_date, :file_path)'
    );

    $insert->execute([
        ':booking_id' => $invoice['booking_id'],
        ':invoice_number' => $invoice['invoice_number'],
        ':customer_name' => $invoice['customer_name'],
        ':customer_email' => $invoice['customer_email'],
        ':room_name' => $invoice['room_name'],
        ':check_in_date' => $invoice['check_in_date'],
        ':check_out_date' => $invoice['check_out_date'],
        ':amount_paid' => $invoice['amount_paid'],
        ':currency' => $invoice['currency'],
        ':payment_method' => $invoice['payment_method'],
        ':payment_date' => $invoice['payment_date'],
        ':file_path' => $invoice['file_path'],
    ]);

    $invoiceId = (int) $pdo->lastInsertId();

    $updateBooking = $pdo->prepare(
        'UPDATE bookings
         SET invoice_id = :invoice_id,
             invoice_number = :invoice_number,
             invoice_file_path = :invoice_file_path,
             invoice_generated_at = NOW(),
             updated_at = NOW()
         WHERE id = :booking_id'
    );

    $updateBooking->execute([
        ':invoice_id' => $invoiceId,
        ':invoice_number' => $invoiceNumber,
        ':invoice_file_path' => $relativePath,
        ':booking_id' => $bookingId,
    ]);

    $updatePayment = $pdo->prepare(
        'UPDATE payments SET invoice_id = :invoice_id, invoice_number = :invoice_number, updated_at = NOW() WHERE booking_id = :booking_id'
    );

    $updatePayment->execute([
        ':invoice_id' => $invoiceId,
        ':invoice_number' => $invoiceNumber,
        ':booking_id' => $bookingId,
    ]);

    $invoice['id'] = $invoiceId;
    return $invoice;
}
