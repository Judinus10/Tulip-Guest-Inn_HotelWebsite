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
    $text = str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $text);
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
    if (!$value) return '-';
    $timestamp = strtotime($value);
    if (!$timestamp) return $value . ($time !== '' ? ' (' . $time . ')' : '');
    return date('d M Y', $timestamp) . ($time !== '' ? ' (' . $time . ')' : '');
}

function pdf_datetime_display(?string $value): string
{
    $timestamp = $value ? strtotime($value) : time();
    if (!$timestamp) $timestamp = time();
    return date('d M Y, h:i A', $timestamp);
}

function pdf_short_date(?string $value): string
{
    $timestamp = $value ? strtotime($value) : time();
    if (!$timestamp) $timestamp = time();
    return date('d M Y', $timestamp);
}

function pdf_text_width(string $text, float $fontSize): float
{
    return strlen($text) * $fontSize * 0.48;
}

function pdf_add_text(string &$content, float $x, float $y, string $text, float $size = 10, string $font = 'F1', array $color = [0.05, 0.12, 0.18]): void
{
    $content .= 'BT /' . $font . ' ' . $size . ' Tf ' . pdf_rgb($color[0], $color[1], $color[2]) . " rg 1 0 0 1 " . sprintf('%.2F %.2F', $x, $y) . ' Tm (' . pdf_escape_text($text) . ") Tj ET\n";
}

function pdf_add_center_text(string &$content, float $centerX, float $y, string $text, float $size = 10, string $font = 'F1', array $color = [0.05, 0.12, 0.18]): void
{
    pdf_add_text($content, $centerX - (pdf_text_width($text, $size) / 2), $y, $text, $size, $font, $color);
}

function pdf_add_right_text(string &$content, float $rightX, float $y, string $text, float $size = 10, string $font = 'F1', array $color = [0.05, 0.12, 0.18]): void
{
    pdf_add_text($content, $rightX - pdf_text_width($text, $size), $y, $text, $size, $font, $color);
}

function pdf_add_line(string &$content, float $x1, float $y1, float $x2, float $y2, array $color = [0.82, 0.74, 0.62], float $width = 0.7): void
{
    $content .= pdf_rgb($color[0], $color[1], $color[2]) . " RG " . sprintf("%.2F w %.2F %.2F m %.2F %.2F l S\n", $width, $x1, $y1, $x2, $y2);
}

function pdf_add_rect(string &$content, float $x, float $y, float $w, float $h, array $stroke = [0.86, 0.82, 0.75], ?array $fill = null, float $lineWidth = 0.7): void
{
    if ($fill) {
        $content .= pdf_rgb($fill[0], $fill[1], $fill[2]) . sprintf(" rg %.2F %.2F %.2F %.2F re f\n", $x, $y, $w, $h);
    }
    $content .= pdf_rgb($stroke[0], $stroke[1], $stroke[2]) . sprintf(" RG %.2F w %.2F %.2F %.2F %.2F re S\n", $lineWidth, $x, $y, $w, $h);
}

function pdf_add_wrapped_text(string &$content, float $x, float $y, string $text, float $size, float $maxWidth, float $lineHeight = 14, string $font = 'F1', array $color = [0.05, 0.12, 0.18], int $maxLines = 99): float
{
    $rawWords = preg_split('/\s+/', trim($text)) ?: [];
    $words = [];
    $maxChars = max(8, (int) floor($maxWidth / max(1, $size * 0.48)));
    foreach ($rawWords as $rawWord) {
        if (pdf_text_width($rawWord, $size) > $maxWidth) {
            foreach (str_split($rawWord, $maxChars) as $chunk) $words[] = $chunk;
        } else {
            $words[] = $rawWord;
        }
    }
    $line = '';
    $lineCount = 0;
    foreach ($words as $word) {
        $candidate = trim($line . ' ' . $word);
        if ($line !== '' && pdf_text_width($candidate, $size) > $maxWidth) {
            $lineCount++;
            if ($lineCount >= $maxLines) {
                pdf_add_text($content, $x, $y, rtrim($line, '.') . '...', $size, $font, $color);
                return $y - $lineHeight;
            }
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

function pdf_draw_jpeg(string &$content, float $x, float $y, float $w, float $h, string $name = 'Im1'): void
{
    $content .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n", $w, $h, $x, $y, $name);
}

function invoice_find_room_image(PDO $pdo, string $roomName): string
{
    try {
        $stmt = $pdo->prepare('SELECT id FROM rooms WHERE room_name = :room_name LIMIT 1');
        $stmt->execute([':room_name' => $roomName]);
        $room = $stmt->fetch();
        if (!$room) return '';

        $pdo->exec("CREATE TABLE IF NOT EXISTS room_images (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            room_id INT UNSIGNED NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            is_main TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_room_images_room (room_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $imageStmt = $pdo->prepare('SELECT image_path FROM room_images WHERE room_id = :room_id ORDER BY is_main DESC, sort_order ASC, id ASC LIMIT 1');
        $imageStmt->execute([':room_id' => (int) $room['id']]);
        $image = $imageStmt->fetch();
        return (string) ($image['image_path'] ?? '');
    } catch (Throwable $e) {
        return '';
    }
}

function invoice_settings(PDO $pdo): array
{
    $defaults = [
        'business_name' => 'Tulip Guest Inn',
        'address' => 'Point Pedro, Northern Sri Lanka',
        'phone' => '+94 77 123 4567',
        'email' => 'info@tulipguestinn.com',
        'website' => 'www.tulipguestinn.com',
    ];
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM website_settings WHERE setting_key IN ('business_name','address','phone','email','website','website_url')");
        foreach (($stmt ? $stmt->fetchAll() : []) as $row) {
            $key = (string) $row['setting_key'];
            $value = trim((string) $row['setting_value']);
            if ($value !== '') {
                if ($key === 'website_url') $key = 'website';
                $defaults[$key] = $value;
            }
        }
    } catch (Throwable $e) {}
    return $defaults;
}

function build_invoice_data_for_booking(PDO $pdo, int $bookingId, array $payment = []): ?array
{
    $booking = get_booking_by_id($pdo, $bookingId);
    if (!$booking) return null;

    if (!$payment) {
        $paymentStmt = $pdo->prepare('SELECT * FROM payments WHERE booking_id = :booking_id ORDER BY id DESC LIMIT 1');
        $paymentStmt->execute([':booking_id' => $bookingId]);
        $payment = $paymentStmt->fetch() ?: [];
    }

    $amountPaid = (float) ($payment['amount'] ?? $booking['amount'] ?? $booking['payment_amount'] ?? 0);
    $currency = (string) ($payment['currency'] ?? $booking['currency'] ?? $booking['payment_currency'] ?? PAYMENT_CURRENCY);
    $paymentMethod = (string) ($payment['method'] ?? $payment['payment_method'] ?? 'PayHere');
    $paymentDate = (string) ($payment['updated_at'] ?? $payment['paid_at'] ?? $payment['payment_date'] ?? date('Y-m-d H:i:s'));
    $transactionId = (string) ($payment['payment_id'] ?? $payment['transaction_id'] ?? $payment['order_id'] ?? $booking['payment_order_id'] ?? '-');
    $nights = 1;
    $checkInTs = strtotime((string) ($booking['check_in_date'] ?? ''));
    $checkOutTs = strtotime((string) ($booking['check_out_date'] ?? ''));
    if ($checkInTs && $checkOutTs && $checkOutTs > $checkInTs) {
        $nights = max(1, (int) round(($checkOutTs - $checkInTs) / 86400));
    }

    $settings = invoice_settings($pdo);
    $roomImage = invoice_find_room_image($pdo, (string) ($booking['room_name'] ?? ''));

    return [
        'booking_id' => $bookingId,
        'invoice_number' => (string) ($booking['invoice_number'] ?: generate_invoice_number($bookingId)),
        'customer_name' => (string) ($booking['full_name'] ?? 'Guest'),
        'customer_email' => (string) ($booking['email'] ?? '-'),
        'customer_phone' => (string) ($booking['phone'] ?? '-'),
        'customer_address' => (string) ($booking['message'] ?? ''),
        'room_name' => (string) ($booking['room_name'] ?? '-'),
        'check_in_date' => (string) ($booking['check_in_date'] ?? ''),
        'check_out_date' => (string) ($booking['check_out_date'] ?? ''),
        'amount_paid' => $amountPaid,
        'currency' => $currency,
        'payment_method' => $paymentMethod,
        'payment_date' => $paymentDate,
        'transaction_id' => $transactionId,
        'payment_status' => (string) ($payment['status'] ?? $booking['payment_status'] ?? 'Paid'),
        'guests' => (int) ($booking['guests'] ?? 1),
        'nights' => $nights,
        'room_image_path' => $roomImage,
        'business_name' => $settings['business_name'],
        'business_address' => $settings['address'],
        'business_phone' => $settings['phone'],
        'business_email' => $settings['email'],
        'business_website' => $settings['website'],
    ];
}

function invoice_resolve_image_path(string $imagePath): string
{
    $imagePath = trim($imagePath);
    if ($imagePath === '') return '';
    if (preg_match('/^https?:\/\//i', $imagePath)) return '';
    $clean = ltrim($imagePath, '/');
    $candidates = [
        __DIR__ . '/../' . $clean,
        __DIR__ . '/../uploads/rooms/' . basename($clean),
        __DIR__ . '/../../' . $clean,
    ];
    foreach ($candidates as $candidate) {
        if (is_file($candidate) && preg_match('/\.(jpe?g)$/i', $candidate)) return $candidate;
    }
    return '';
}

function create_invoice_pdf_binary(array $invoice): string
{
    $navy = [0.03, 0.11, 0.19];
    $gold = [0.72, 0.50, 0.22];
    $line = [0.86, 0.82, 0.75];
    $softGold = [0.995, 0.965, 0.915];
    $white = [1, 1, 1];
    $green = [0.22, 0.62, 0.28];

    $invoiceNumber = (string) ($invoice['invoice_number'] ?? '-');
    $bookingCode = 'TGI-' . str_pad((string) ((int) ($invoice['booking_id'] ?? 0)), 6, '0', STR_PAD_LEFT);
    $customerName = (string) ($invoice['customer_name'] ?? 'Guest');
    $customerEmail = (string) ($invoice['customer_email'] ?? '-');
    $customerPhone = (string) ($invoice['customer_phone'] ?? '-');
    $roomName = (string) ($invoice['room_name'] ?? '-');
    $guests = max(1, (int) ($invoice['guests'] ?? 1));
    $nights = max(1, (int) ($invoice['nights'] ?? 1));
    $currency = (string) ($invoice['currency'] ?? 'LKR');
    $amountPaid = (float) ($invoice['amount_paid'] ?? 0);
    $paymentMethod = (string) ($invoice['payment_method'] ?? 'PayHere');
    $transactionId = (string) ($invoice['transaction_id'] ?? '-');
    $paymentDate = pdf_datetime_display((string) ($invoice['payment_date'] ?? ''));
    $paymentStatus = strtolower((string) ($invoice['payment_status'] ?? 'Paid')) === 'paid' ? 'Paid' : (string) ($invoice['payment_status'] ?? 'Paid');
    $businessAddress = (string) ($invoice['business_address'] ?? 'Point Pedro, Northern Sri Lanka');
    $businessPhone = (string) ($invoice['business_phone'] ?? '+94 77 123 4567');
    $businessEmail = (string) ($invoice['business_email'] ?? 'info@tulipguestinn.com');
    $businessWebsite = (string) ($invoice['business_website'] ?? 'www.tulipguestinn.com');

    $content = "";

    // Header - close to the provided reference, but text-only logo as requested.
    pdf_add_text($content, 38, 774, 'TULIP', 34, 'F4', $navy);
    pdf_add_text($content, 39, 748, 'GUEST INN', 17, 'F1', $gold);
    pdf_add_text($content, 40, 726, 'Luxury Boutique Hotel', 9.5, 'F2', $navy);
    pdf_add_wrapped_text($content, 40, 711, $businessAddress, 9, 150, 11, 'F1', $navy, 2);

    pdf_add_center_text($content, 300, 764, 'INVOICE', 40, 'F4', $navy);
    pdf_add_line($content, 255, 746, 345, 746, $gold, 0.8);
    pdf_add_center_text($content, 300, 718, 'Thank you for choosing Tulip Guest Inn.', 13, 'F3', $gold);

    pdf_add_rect($content, 482, 778, 78, 26, $navy, $navy, 0.5);
    pdf_add_center_text($content, 521, 787, 'PAID', 12, 'F2', $white);
    pdf_add_text($content, 482, 752, 'Invoice Number', 9, 'F2', $navy);
    pdf_add_text($content, 482, 737, $invoiceNumber, 9, 'F1', $navy);
    pdf_add_text($content, 482, 713, 'Invoice Date', 9, 'F2', $navy);
    pdf_add_text($content, 482, 698, pdf_short_date((string) ($invoice['payment_date'] ?? '')), 9, 'F1', $navy);
    pdf_add_text($content, 482, 674, 'Payment Date', 9, 'F2', $navy);
    pdf_add_text($content, 482, 659, $paymentDate, 8.5, 'F1', $navy);
    pdf_add_line($content, 38, 632, 560, 632, $line, 0.6);

    // Bill-to and booking area.
    pdf_add_text($content, 50, 608, 'BILL TO', 12, 'F4', $navy);
    pdf_add_line($content, 50, 596, 145, 596, $gold, 0.8);
    pdf_add_wrapped_text($content, 65, 573, $customerName, 9.5, 112, 11, 'F1', $navy, 3);
    pdf_add_wrapped_text($content, 65, 538, $customerEmail, 9.5, 112, 11, 'F1', $navy, 2);
    pdf_add_text($content, 65, 505, $customerPhone, 9.5, 'F1', $navy);
    pdf_add_text($content, 50, 573, 'o', 11, 'F2', $navy);
    pdf_add_text($content, 50, 538, 'x', 10, 'F2', $navy);
    pdf_add_text($content, 50, 505, 'c', 10, 'F2', $navy);

    pdf_add_line($content, 188, 608, 188, 472, $line, 0.6);
    pdf_add_text($content, 210, 608, 'BOOKING INFORMATION', 12, 'F4', $navy);
    pdf_add_line($content, 210, 596, 392, 596, $gold, 0.8);
    $infoRows = [
        ['Booking ID', $bookingCode],
        ['Room Name', $roomName],
        ['Check-in Date', pdf_date_display((string) ($invoice['check_in_date'] ?? ''), '11:00 AM')],
        ['Check-out Date', pdf_date_display((string) ($invoice['check_out_date'] ?? ''), '10:00 AM')],
        ['Guests', $guests . ' ' . ($guests === 1 ? 'Guest' : 'Guests')],
        ['Nights', $nights . ' ' . ($nights === 1 ? 'Night' : 'Nights')],
    ];
    $y = 572;
    foreach ($infoRows as $row) {
        pdf_add_text($content, 210, $y, $row[0], 9, 'F2', $navy);
        pdf_add_wrapped_text($content, 296, $y, $row[1], 9, 118, 10.5, 'F1', $navy, 2);
        $y -= 21;
    }

    $imageFile = invoice_resolve_image_path((string) ($invoice['room_image_path'] ?? ''));
    $hasImage = $imageFile !== '' && @getimagesize($imageFile);
    if ($hasImage) {
        pdf_add_rect($content, 410, 488, 150, 92, [0.78, 0.65, 0.48], null, 0.7);
        pdf_draw_jpeg($content, 412, 490, 146, 88, 'Im1');
    } else {
        pdf_add_rect($content, 410, 488, 150, 92, [0.78, 0.65, 0.48], $softGold, 0.7);
        pdf_add_center_text($content, 485, 534, 'Room image', 10, 'F2', $gold);
    }
    pdf_add_line($content, 38, 456, 560, 456, $line, 0.6);

    // Invoice breakdown.
    pdf_add_text($content, 50, 432, 'INVOICE BREAKDOWN', 12, 'F4', $navy);
    pdf_add_rect($content, 38, 397, 522, 25, $navy, $navy, 0.5);
    pdf_add_text($content, 50, 406, 'DESCRIPTION', 10, 'F2', $white);
    pdf_add_right_text($content, 545, 406, 'AMOUNT (' . $currency . ')', 10, 'F2', $white);
    pdf_add_rect($content, 38, 303, 522, 94, $line, null, 0.5);
    pdf_add_text($content, 50, 375, 'Room Charges (' . $nights . ' ' . ($nights === 1 ? 'Night' : 'Nights') . ')', 10, 'F1', $navy);
    pdf_add_right_text($content, 545, 375, pdf_money($amountPaid, $currency), 10, 'F1', $navy);
    pdf_add_line($content, 50, 332, 545, 332, $line, 0.5);
    pdf_add_text($content, 50, 314, 'TOTAL AMOUNT', 12, 'F4', $gold);
    pdf_add_right_text($content, 545, 313, pdf_money($amountPaid, $currency), 18, 'F4', $gold);

    // Payment info and total paid card.
    pdf_add_rect($content, 38, 135, 282, 137, $line, null, 0.6);
    pdf_add_text($content, 50, 249, 'PAYMENT INFORMATION', 12, 'F4', $navy);
    $paymentRows = [
        ['Payment Method', $paymentMethod],
        ['Transaction ID', $transactionId],
        ['Payment Gateway', 'PayHere'],
        ['Payment Date', $paymentDate],
        ['Payment Status', $paymentStatus],
    ];
    $y = 226;
    foreach ($paymentRows as $row) {
        pdf_add_text($content, 50, $y, $row[0], 9, 'F2', $gold);
        if ($row[0] === 'Payment Status' && strtolower($row[1]) === 'paid') {
            pdf_add_text($content, 178, $y, '●', 10, 'F2', $green);
            pdf_add_text($content, 193, $y, 'Paid', 9, 'F2', $green);
        } else {
            pdf_add_wrapped_text($content, 180, $y, $row[1], 9, 128, 10.5, 'F1', $navy, 2);
        }
        $y -= 20;
    }

    pdf_add_rect($content, 336, 160, 224, 112, [0.93, 0.83, 0.66], $softGold, 0.6);
    pdf_add_center_text($content, 448, 235, 'TOTAL PAID', 10, 'F2', $navy);
    pdf_add_center_text($content, 448, 211, pdf_money($amountPaid, $currency), 20, 'F4', $gold);
    pdf_add_center_text($content, 448, 184, 'Thank You', 18, 'F3', $navy);
    pdf_add_center_text($content, 448, 168, 'for your stay with us!', 11, 'F1', $navy);

    // Notes and signature.
    pdf_add_text($content, 50, 118, 'IMPORTANT NOTES', 10.5, 'F4', $navy);
    $notes = [
        'This is a computer generated invoice.',
        'No signature is required.',
        'Standard check-in time is 11:00 AM and check-out time is 10:00 AM.',
        'For any queries, please contact our support team.',
    ];
    $y = 101;
    foreach ($notes as $note) {
        pdf_add_text($content, 54, $y, '- ' . $note, 8, 'F1', $navy);
        $y -= 11;
    }
    pdf_add_center_text($content, 455, 100, 'Tulip Guest Inn', 18, 'F3', $navy);
    pdf_add_line($content, 378, 86, 532, 91, $line, 0.5);
    pdf_add_center_text($content, 455, 72, 'Authorized Signatory', 8.5, 'F2', $navy);
    pdf_add_center_text($content, 455, 60, 'Tulip Guest Inn', 8.5, 'F1', $navy);

    // Footer.
    pdf_add_rect($content, 0, 0, 595, 46, $navy, $navy, 0.5);
    pdf_add_text($content, 50, 26, $businessAddress, 8.5, 'F1', $white);
    pdf_add_text($content, 235, 26, $businessPhone, 8.5, 'F1', $white);
    pdf_add_text($content, 340, 26, $businessEmail, 8.2, 'F1', $white);
    pdf_add_text($content, 475, 26, $businessWebsite, 7.5, 'F1', $white);

    $objects = [];
    $resources = '/Font << /F1 4 0 R /F2 5 0 R /F3 6 0 R /F4 7 0 R >>';
    $imageObject = '';
    if ($hasImage) {
        $imageData = file_get_contents($imageFile);
        $imageInfo = getimagesize($imageFile);
        if ($imageData !== false && $imageInfo) {
            $resources .= ' /XObject << /Im1 9 0 R >>';
            $imageObject = "9 0 obj\n<< /Type /XObject /Subtype /Image /Width " . (int) $imageInfo[0] . ' /Height ' . (int) $imageInfo[1] . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($imageData) . " >>\nstream\n" . $imageData . "\nendstream\nendobj\n";
        }
    }

    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << $resources >> /Contents 8 0 R >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
    $objects[] = "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Times-Italic >>\nendobj\n";
    $objects[] = "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold >>\nendobj\n";
    $objects[] = "8 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj\n";
    if ($imageObject !== '') $objects[] = $imageObject;

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
    return $pdf;
}

function create_simple_invoice_pdf(string $filePath, array $invoice): void
{
    file_put_contents($filePath, create_invoice_pdf_binary($invoice));
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
    if (!$booking) return null;

    if (!$forceRegenerate && !empty($booking['invoice_id'])) {
        $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int) $booking['invoice_id']]);
        $existing = $stmt->fetch();
        if ($existing) return $existing;
    }

    $invoice = build_invoice_data_for_booking($pdo, $bookingId, $payment);
    if (!$invoice) return null;

    // Do not store generated PDF files locally. The PDF is generated only when downloaded.
    $relativePath = '';
    $invoice['file_path'] = $relativePath;

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
        ':file_path' => $relativePath,
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
        ':invoice_number' => $invoice['invoice_number'],
        ':invoice_file_path' => $relativePath,
        ':booking_id' => $bookingId,
    ]);

    $updatePayment = $pdo->prepare(
        'UPDATE payments SET invoice_id = :invoice_id, invoice_number = :invoice_number, updated_at = NOW() WHERE booking_id = :booking_id'
    );

    $updatePayment->execute([
        ':invoice_id' => $invoiceId,
        ':invoice_number' => $invoice['invoice_number'],
        ':booking_id' => $bookingId,
    ]);

    $invoice['id'] = $invoiceId;
    return $invoice;
}
