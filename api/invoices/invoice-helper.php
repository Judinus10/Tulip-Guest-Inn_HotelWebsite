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

function pdf_rgb(float $r, float $g, float $b): string
{
    return sprintf('%.3F %.3F %.3F', $r, $g, $b);
}

function pdf_money(float $amount, string $currency = 'LKR'): string
{
    return trim($currency ?: 'LKR') . ' ' . number_format($amount, 2);
}

function pdf_date_display(?string $value, string $time = ''): string
{
    if (!$value) {
        return '-';
    }
    $timestamp = strtotime($value);
    if (!$timestamp) {
        return $value . ($time !== '' ? ' (' . $time . ')' : '');
    }
    return date('d M Y', $timestamp) . ($time !== '' ? ' (' . $time . ')' : '');
}

function pdf_datetime_display(?string $value): string
{
    $timestamp = $value ? strtotime($value) : time();
    if (!$timestamp) {
        $timestamp = time();
    }
    return date('d M Y, h:i A', $timestamp);
}

function pdf_text_width(string $text, float $fontSize): float
{
    return strlen($text) * $fontSize * 0.48;
}

function pdf_add_text(string &$content, float $x, float $y, string $text, float $size = 10, string $font = 'F1', array $color = [0.05, 0.12, 0.18]): void
{
    $content .= 'BT /' . $font . ' ' . $size . ' Tf ' . pdf_rgb($color[0], $color[1], $color[2]) . " rg 1 0 0 1 " . $x . ' ' . $y . ' Tm (' . pdf_escape_text($text) . ") Tj ET\n";
}

function pdf_add_right_text(string &$content, float $rightX, float $y, string $text, float $size = 10, string $font = 'F1', array $color = [0.05, 0.12, 0.18]): void
{
    pdf_add_text($content, $rightX - pdf_text_width($text, $size), $y, $text, $size, $font, $color);
}

function pdf_add_line(string &$content, float $x1, float $y1, float $x2, float $y2, array $color = [0.82, 0.74, 0.62], float $width = 0.7): void
{
    $content .= pdf_rgb($color[0], $color[1], $color[2]) . " RG " . $width . " w $x1 $y1 m $x2 $y2 l S\n";
}

function pdf_add_rect(string &$content, float $x, float $y, float $w, float $h, array $stroke = [0.86, 0.82, 0.75], ?array $fill = null, float $lineWidth = 0.7): void
{
    if ($fill) {
        $content .= pdf_rgb($fill[0], $fill[1], $fill[2]) . " rg $x $y $w $h re f\n";
    }
    $content .= pdf_rgb($stroke[0], $stroke[1], $stroke[2]) . " RG " . $lineWidth . " w $x $y $w $h re S\n";
}

function pdf_add_wrapped_text(string &$content, float $x, float $y, string $text, float $size, float $maxWidth, float $lineHeight = 14, string $font = 'F1', array $color = [0.05, 0.12, 0.18]): float
{
    $rawWords = preg_split('/\s+/', trim($text)) ?: [];
    $words = [];
    $maxChars = max(8, (int) floor($maxWidth / max(1, $size * 0.48)));
    foreach ($rawWords as $rawWord) {
        if (pdf_text_width($rawWord, $size) > $maxWidth) {
            $chunks = str_split($rawWord, $maxChars);
            foreach ($chunks as $chunk) {
                $words[] = $chunk;
            }
        } else {
            $words[] = $rawWord;
        }
    }
    $line = '';
    foreach ($words as $word) {
        $candidate = trim($line . ' ' . $word);
        if ($line !== '' && pdf_text_width($candidate, $size) > $maxWidth) {
            pdf_add_text($content, $x, $y, $line, $size, $font, $color);
            $y -= $lineHeight;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }
    if ($line !== '') {
        pdf_add_text($content, $x, $y, $line, $size, $font, $color);
        $y -= $lineHeight;
    }
    return $y;
}

function create_simple_invoice_pdf(string $filePath, array $invoice): void
{
    $navy = [0.04, 0.12, 0.20];
    $gold = [0.73, 0.52, 0.25];
    $muted = [0.32, 0.38, 0.42];
    $softGold = [0.99, 0.96, 0.90];
    $light = [0.97, 0.96, 0.94];

    $invoiceNumber = (string) ($invoice['invoice_number'] ?? '-');
    $bookingId = 'TGI-' . str_pad((string) ((int) ($invoice['booking_id'] ?? 0)), 6, '0', STR_PAD_LEFT);
    $customerName = (string) ($invoice['customer_name'] ?? 'Guest');
    $customerEmail = (string) ($invoice['customer_email'] ?? '-');
    $customerPhone = (string) ($invoice['customer_phone'] ?? '-');
    $roomName = (string) ($invoice['room_name'] ?? '-');
    $guests = (int) ($invoice['guests'] ?? 1);
    $nights = max(1, (int) ($invoice['nights'] ?? 1));
    $currency = (string) ($invoice['currency'] ?? 'LKR');
    $amountPaid = (float) ($invoice['amount_paid'] ?? 0);
    $paymentMethod = (string) ($invoice['payment_method'] ?? 'PayHere');
    $transactionId = (string) ($invoice['transaction_id'] ?? '-');
    $paymentDate = pdf_datetime_display((string) ($invoice['payment_date'] ?? ''));

    $content = "";

    // Header
    pdf_add_text($content, 48, 780, 'TULIP', 31, 'F4', $navy);
    pdf_add_text($content, 50, 759, 'GUEST INN', 16, 'F1', $gold);
    pdf_add_text($content, 50, 742, 'Luxury Boutique Hotel', 9, 'F2', $navy);
    pdf_add_text($content, 50, 728, 'Point Pedro, Northern Sri Lanka', 9, 'F1', $navy);

    pdf_add_text($content, 235, 765, 'INVOICE', 39, 'F4', $navy);
    pdf_add_line($content, 258, 748, 338, 748, $gold, 0.8);
    pdf_add_text($content, 212, 725, 'Thank you for choosing Tulip Guest Inn.', 13, 'F3', $gold);

    pdf_add_rect($content, 482, 778, 64, 24, $navy, $navy, 0.5);
    pdf_add_text($content, 506, 786, 'PAID', 12, 'F2', [1, 1, 1]);
    pdf_add_text($content, 480, 755, 'Invoice Number', 9, 'F2', $navy);
    pdf_add_text($content, 480, 740, $invoiceNumber, 9, 'F1', $navy);
    pdf_add_text($content, 480, 718, 'Invoice Date', 9, 'F2', $navy);
    pdf_add_text($content, 480, 703, date('d M Y'), 9, 'F1', $navy);
    pdf_add_text($content, 480, 681, 'Payment Date', 9, 'F2', $navy);
    pdf_add_text($content, 480, 666, $paymentDate, 9, 'F1', $navy);
    pdf_add_line($content, 45, 648, 550, 648, [0.86, 0.82, 0.75], 0.6);

    // Bill to and booking info
    pdf_add_text($content, 55, 625, 'BILL TO', 12, 'F4', $navy);
    pdf_add_line($content, 55, 615, 155, 615, $gold, 0.8);
    pdf_add_text($content, 60, 590, 'Guest Name', 8, 'F2', $gold);
    pdf_add_wrapped_text($content, 60, 576, $customerName, 9, 150, 12, 'F1', $navy);
    pdf_add_text($content, 60, 548, 'Email', 8, 'F2', $gold);
    pdf_add_wrapped_text($content, 60, 534, $customerEmail, 9, 150, 12, 'F1', $navy);
    pdf_add_text($content, 60, 506, 'Phone', 8, 'F2', $gold);
    pdf_add_text($content, 60, 492, $customerPhone, 9, 'F1', $navy);

    pdf_add_line($content, 205, 625, 205, 485, [0.86, 0.82, 0.75], 0.6);
    pdf_add_text($content, 230, 625, 'BOOKING INFORMATION', 12, 'F4', $navy);
    pdf_add_line($content, 230, 615, 430, 615, $gold, 0.8);
    $infoRows = [
        ['Booking ID', $bookingId],
        ['Room Name', $roomName],
        ['Check-in Date', pdf_date_display((string) ($invoice['check_in_date'] ?? ''), '11:00 AM')],
        ['Check-out Date', pdf_date_display((string) ($invoice['check_out_date'] ?? ''), '10:00 AM')],
        ['Guests', $guests . ' ' . ($guests === 1 ? 'Guest' : 'Guests')],
        ['Nights', $nights . ' ' . ($nights === 1 ? 'Night' : 'Nights')],
    ];
    $y = 590;
    foreach ($infoRows as $row) {
        pdf_add_text($content, 230, $y, $row[0], 9, 'F2', $navy);
        pdf_add_wrapped_text($content, 320, $y, $row[1], 9, 190, 11, 'F1', $navy);
        $y -= 21;
    }

    // Invoice breakdown
    pdf_add_line($content, 45, 468, 550, 468, [0.86, 0.82, 0.75], 0.6);
    pdf_add_text($content, 55, 443, 'INVOICE BREAKDOWN', 12, 'F4', $navy);
    pdf_add_rect($content, 50, 412, 500, 22, $navy, $navy, 0.5);
    pdf_add_text($content, 62, 420, 'DESCRIPTION', 10, 'F2', [1, 1, 1]);
    pdf_add_right_text($content, 530, 420, 'AMOUNT (' . $currency . ')', 10, 'F2', [1, 1, 1]);
    pdf_add_rect($content, 50, 332, 500, 80, [0.86, 0.82, 0.75], null, 0.5);
    pdf_add_text($content, 62, 392, 'Room Charges (' . $nights . ' ' . ($nights === 1 ? 'Night' : 'Nights') . ')', 10, 'F1', $navy);
    pdf_add_right_text($content, 530, 392, pdf_money($amountPaid, $currency), 10, 'F1', $navy);
    pdf_add_line($content, 62, 361, 530, 361, [0.86, 0.82, 0.75], 0.5);
    pdf_add_text($content, 62, 343, 'TOTAL AMOUNT', 12, 'F4', $gold);
    pdf_add_right_text($content, 530, 343, pdf_money($amountPaid, $currency), 17, 'F4', $gold);

    // Payment and total cards
    pdf_add_rect($content, 50, 197, 265, 105, [0.86, 0.82, 0.75], null, 0.6);
    pdf_add_text($content, 62, 280, 'PAYMENT INFORMATION', 12, 'F4', $navy);
    $paymentRows = [
        ['Payment Method', $paymentMethod],
        ['Transaction ID', $transactionId],
        ['Payment Gateway', 'PayHere'],
        ['Payment Date', $paymentDate],
        ['Payment Status', 'Paid'],
    ];
    $y = 258;
    foreach ($paymentRows as $row) {
        pdf_add_text($content, 62, $y, $row[0], 9, 'F2', $gold);
        pdf_add_wrapped_text($content, 178, $y, $row[1], 9, 125, 10, 'F1', $navy);
        $y -= 18;
    }

    pdf_add_rect($content, 335, 197, 215, 105, [0.93, 0.83, 0.66], $softGold, 0.6);
    pdf_add_text($content, 418, 270, 'TOTAL PAID', 10, 'F2', $navy);
    pdf_add_text($content, 377, 246, pdf_money($amountPaid, $currency), 20, 'F4', $gold);
    pdf_add_text($content, 416, 220, 'Thank You', 18, 'F3', $navy);
    pdf_add_text($content, 393, 204, 'for your stay with us!', 11, 'F1', $navy);

    // Notes and signature
    pdf_add_text($content, 55, 165, 'IMPORTANT NOTES', 11, 'F4', $navy);
    $notes = [
        'This is a computer generated invoice. No signature is required.',
        'Standard check-in time is 11:00 AM.',
        'Standard check-out time is 10:00 AM.',
        'For booking changes, please contact our support team before arrival.',
    ];
    $y = 147;
    foreach ($notes as $note) {
        pdf_add_text($content, 60, $y, '- ' . $note, 8.5, 'F1', $navy);
        $y -= 14;
    }
    pdf_add_text($content, 405, 128, 'Tulip Guest Inn', 18, 'F3', $navy);
    pdf_add_line($content, 365, 112, 520, 112, [0.86, 0.82, 0.75], 0.5);
    pdf_add_text($content, 404, 96, 'Authorized Signatory', 8.5, 'F2', $navy);
    pdf_add_text($content, 420, 83, 'Tulip Guest Inn', 8.5, 'F1', $navy);

    // Footer
    pdf_add_rect($content, 0, 0, 595, 58, $navy, $navy, 0.5);
    pdf_add_text($content, 62, 32, 'No. 123, Beach Road, Point Pedro, Sri Lanka', 9, 'F1', [1, 1, 1]);
    pdf_add_text($content, 265, 32, '+94 77 123 4567', 9, 'F1', [1, 1, 1]);
    pdf_add_text($content, 390, 32, 'info@tulipguestinn.com', 9, 'F1', [1, 1, 1]);
    pdf_add_text($content, 498, 32, 'www.tulipguestinn.com', 8, 'F1', [1, 1, 1]);

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R /F3 6 0 R /F4 7 0 R >> >> /Contents 8 0 R >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
    $objects[] = "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Times-Italic >>\nendobj\n";
    $objects[] = "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold >>\nendobj\n";
    $objects[] = "8 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj\n";

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

function generate_invoice_for_booking(PDO $pdo, int $bookingId, array $payment = [], bool $forceRegenerate = false): ?array
{
    $booking = get_booking_by_id($pdo, $bookingId);

    if (!$booking) {
        return null;
    }

    if (!$forceRegenerate && !empty($booking['invoice_id']) && !empty($booking['invoice_file_path'])) {
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
    $paymentDate = (string) ($payment['paid_at'] ?? $payment['payment_date'] ?? date('Y-m-d H:i:s'));
    $transactionId = (string) ($payment['payment_id'] ?? $payment['transaction_id'] ?? $payment['order_id'] ?? $booking['payment_order_id'] ?? '-');
    $nights = 1;
    $checkInTs = strtotime((string) ($booking['check_in_date'] ?? ''));
    $checkOutTs = strtotime((string) ($booking['check_out_date'] ?? ''));
    if ($checkInTs && $checkOutTs && $checkOutTs > $checkInTs) {
        $nights = max(1, (int) round(($checkOutTs - $checkInTs) / 86400));
    }

    $invoice = [
        'booking_id' => $bookingId,
        'invoice_number' => $invoiceNumber,
        'customer_name' => $booking['full_name'],
        'customer_email' => $booking['email'],
        'customer_phone' => $booking['phone'] ?? '-',
        'room_name' => $booking['room_name'],
        'check_in_date' => $booking['check_in_date'],
        'check_out_date' => $booking['check_out_date'],
        'amount_paid' => $amountPaid,
        'currency' => $currency,
        'payment_method' => $paymentMethod,
        'payment_date' => $paymentDate,
        'transaction_id' => $transactionId,
        'guests' => (int) ($booking['guests'] ?? 1),
        'nights' => $nights,
        'file_path' => $relativePath,
    ];

    create_simple_invoice_pdf($absolutePath, $invoice);

    if ($forceRegenerate && !empty($booking['invoice_id'])) {
        $invoice['id'] = (int) $booking['invoice_id'];
        return $invoice;
    }

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
