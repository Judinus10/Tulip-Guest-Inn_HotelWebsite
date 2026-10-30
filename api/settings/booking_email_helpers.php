<?php
declare(strict_types=1);

function booking_email_editor_placeholders(?string $type = null): array
{
    $summary = ['summary_date'=>'Summary date', 'check_in_count'=>'Arriving reservations', 'check_out_count'=>'Departing reservations', 'hotel_name'=>'Hotel name'];
    if ($type === 'admin_daily_summary') return $summary;
    $contact = ['sender_name'=>'Sender name','enquiry_reference'=>'Enquiry reference','enquiry_subject'=>'Enquiry subject','hotel_name'=>'Hotel name'];
    if ($type !== null && str_starts_with($type, 'contact_')) return $contact;
    $booking = ['guest_name' => 'Guest name', 'booking_reference' => 'Booking reference',
        'check_in' => 'Check-in date', 'check_out' => 'Check-out date', 'hotel_name' => 'Hotel name'];
    $payment = ['payment_status'=>'Payment status','payment_amount'=>'Payment amount', 'booking_status'=>'Booking status'];
    if ($type !== null && !str_starts_with($type,'contact_')) return array_merge($booking,$payment);
    return $type === null ? array_merge($booking, $contact, $payment, $summary) : $booking;
}

function booking_email_editor_defaults(): array
{
    $common = ['greeting' => 'Hello {guest_name},',
        'closing_html' => '<p>If you have any questions before your stay, please contact us.</p>',
        'footer_note' => 'We look forward to welcoming you to Point Pedro.'];
    $defaults = [
        'admin_daily_summary' => ['subject'=>'Daily stay summary - {summary_date}', 'greeting'=>'Hello team,', 'introduction_html'=>'<p>Here are the arrivals and departures scheduled for {summary_date}. Review room assignments and payment statuses before welcoming guests.</p>', 'closing_html'=>'<p>Open the admin panel to review or update the booking records.</p>', 'footer_note'=>'Internal daily operations summary for {hotel_name}.'],
        'received' => array_merge($common, [
            'subject' => 'We received your booking - {booking_reference}',
            'introduction_html' => '<p>Thank you for choosing {hotel_name}. We have received your booking request and will confirm availability shortly.</p>',
        ]),
        'contact_customer' => ['subject'=>'We received your enquiry - {enquiry_reference}','greeting'=>'Hello {sender_name},','introduction_html'=>'<p>Thank you for contacting {hotel_name}. We have received your message and will get back to you.</p>','closing_html'=>'<p>You can reply to this email if you need to add anything to your enquiry.</p>','footer_note'=>'Thank you for getting in touch.'],
        'contact_admin' => ['subject'=>'New enquiry - {enquiry_reference}: {enquiry_subject}','greeting'=>'Hello team,','introduction_html'=>'<p>A new website enquiry has been received. Review the details below and reply to the customer.</p>','closing_html'=>'<p>Use Reply in your email app to contact the sender.</p>','footer_note'=>'Website enquiry notification.'],
        'admin_new_booking' => ['subject'=>'New booking - {booking_reference}','greeting'=>'Hello team,','introduction_html'=>'<p>A booking from {guest_name} has been received. Review the reservation and payment details below.</p>','closing_html'=>'<p>Open the admin panel to manage this reservation.</p>','footer_note'=>'Internal booking notification.'],
        'admin_payment_received' => ['subject'=>'Payment received - {booking_reference}','greeting'=>'Hello team,','introduction_html'=>'<p>A payment for the booking by {guest_name} has been received. Review the transaction details below.</p>','closing_html'=>'<p>Open the admin panel to review the booking and payment record.</p>','footer_note'=>'Internal payment notification.'],
        'admin_payment_failed' => ['subject'=>'Payment failed - {booking_reference}','greeting'=>'Hello team,','introduction_html'=>'<p>A payment attempt for the booking by {guest_name} has failed. Review the booking and follow up if needed.</p>','closing_html'=>'<p>Check the payment record before contacting the customer.</p>','footer_note'=>'A failed payment attempt does not confirm a reservation.'],
        'payment_pending' => ['subject'=>'Payment pending - {booking_reference}','greeting'=>'Hello {guest_name},','introduction_html'=>'<p>Your online payment has not been completed. Your reservation is not confirmed by this message. Please review your booking before the hold expires.</p>','closing_html'=>'<p>Please contact us if you need assistance.</p>','footer_note'=>'Thank you for choosing {hotel_name}.'],
        'payment_failed' => ['subject'=>'Payment failed - {booking_reference}','greeting'=>'Hello {guest_name},','introduction_html'=>'<p>Your payment attempt was unsuccessful. Please review your booking or contact us before trying again. This email does not confirm a reservation.</p>','closing_html'=>'<p>Please contact us if you need assistance.</p>','footer_note'=>'Thank you for choosing {hotel_name}.'],
        'payment_received' => ['subject'=>'Payment received - {booking_reference}','greeting'=>'Hello {guest_name},','introduction_html'=>'<p>We have recorded your payment. Please check the booking status below; a payment receipt alone does not confirm a reservation.</p>','closing_html'=>'<p>Please contact us if you need assistance.</p>','footer_note'=>'Thank you for choosing {hotel_name}.'],
        'expired' => ['subject'=>'Booking hold expired - {booking_reference}','greeting'=>'Hello {guest_name},','introduction_html'=>'<p>Your unpaid online booking hold has expired and the room is no longer held. Please check availability before making a new booking.</p>','closing_html'=>'<p>Please contact us if you need assistance.</p>','footer_note'=>'Thank you for choosing {hotel_name}.'],
        'cancelled' => ['subject'=>'Booking cancelled - {booking_reference}','greeting'=>'Hello {guest_name},','introduction_html'=>'<p>Your reservation has been cancelled. Please contact us if you have questions. Any refund must be verified separately.</p>','closing_html'=>'<p>Please contact us if you need assistance.</p>','footer_note'=>'Thank you for choosing {hotel_name}.'],
        'status_updated' => ['subject'=>'Booking details updated - {booking_reference}','greeting'=>'Hello {guest_name},','introduction_html'=>'<p>Your booking or payment details have changed. Please review the current statuses below.</p>','closing_html'=>'<p>Please contact us if you need assistance.</p>','footer_note'=>'Thank you for choosing {hotel_name}.'],
        'admin_payment_pending' => ['subject'=>'Payment pending - {booking_reference}','greeting'=>'Hello team,','introduction_html'=>'<p>Online payment for the booking by {guest_name} has not been completed. Review the current booking and payment status.</p>','closing_html'=>'<p>Open the admin panel to review the record.</p>','footer_note'=>'Internal booking notification.'],
        'admin_booking_expired' => ['subject'=>'Booking hold expired - {booking_reference}','greeting'=>'Hello team,','introduction_html'=>'<p>The unpaid online reservation hold for {guest_name} has expired. Review the booking record before following up.</p>','closing_html'=>'<p>Open the admin panel to review the record.</p>','footer_note'=>'Internal booking notification.'],
        'admin_booking_cancelled' => ['subject'=>'Booking cancelled - {booking_reference}','greeting'=>'Hello team,','introduction_html'=>'<p>The booking by {guest_name} has been cancelled. Review the booking and any refund requirements separately.</p>','closing_html'=>'<p>Open the admin panel to review the record.</p>','footer_note'=>'Internal booking notification.'],
        'confirmed' => array_merge($common, [
            'subject' => 'Your booking is confirmed - {booking_reference}',
            'introduction_html' => '<p>Thank you for choosing {hotel_name}. Your booking is confirmed. We look forward to welcoming you.</p>',
        ]),
    ];
    foreach ($defaults as $type => &$content) {
        $content['please_note'] = $type === 'received'
            ? 'This is an acknowledgement that we have received your request. Your reservation is only confirmed when you receive a booking confirmation email.' : '';
    }
    unset($content);
    return $defaults;
}

function booking_email_editor_type(mixed $type): string
{
    if (!is_string($type) || !array_key_exists($type, booking_email_editor_defaults())) {
        throw new InvalidArgumentException('Choose a supported email template.');
    }
    return $type;
}

function booking_email_editor_safe_link(string $href): string
{
    $href = trim($href);
    // No relative URLs, dynamic URL placeholders, control characters or script schemes.
    return !preg_match('/[\x00-\x20\x7f{}]/u', $href)
        && preg_match('~^(https?://[^/]+|mailto:[^@]+@[^@]+|tel:\+?[0-9().-]+)~i', $href) ? $href : '';
}

function booking_email_editor_sanitize(string $html): string
{
    if (!class_exists('DOMDocument')) throw new RuntimeException('The PHP DOM extension is required for email editing.');
    $doc = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    try {
        $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>' . $html . '</body></html>', LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) throw new InvalidArgumentException('Enter valid email text.');
    $allowed = ['p', 'div', 'strong', 'b', 'em', 'i', 'a', 'ul', 'ol', 'li', 'br'];
    $discard = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'form', 'input', 'button',
        'textarea', 'select', 'img', 'video', 'audio', 'link', 'meta', 'base'];
    $clean = static function (DOMNode $node) use (&$clean, $allowed, $discard): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, $discard, true)) { $node->removeChild($child); continue; }
                $clean($child);
                if (!in_array($tag, $allowed, true)) {
                    while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                    $node->removeChild($child);
                    continue;
                }
                $href = $tag === 'a' ? booking_email_editor_safe_link($child->getAttribute('href')) : '';
                foreach (iterator_to_array($child->attributes) as $attribute) $child->removeAttribute($attribute->name);
                if ($href !== '') { $child->setAttribute('href', $href); $child->setAttribute('rel', 'noopener noreferrer'); }
            } elseif (!$child instanceof DOMText) $node->removeChild($child);
        }
    };
    $clean($body);
    $result = '';
    foreach ($body->childNodes as $child) $result .= $doc->saveHTML($child);
    return trim($result);
}

function booking_email_editor_validate(mixed $content, ?string $type = null): array
{
    $limits = ['subject' => 200, 'greeting' => 200, 'introduction_html' => 6000, 'closing_html' => 4000, 'footer_note' => 500];
    if (!is_array($content)) throw new InvalidArgumentException('Provide the email content fields.');
    // Existing saved templates and older clients contain only the original five fields.
    // An absent notice stays empty, preserving previously saved wording.
    $limits['please_note'] = 1500;
    if (!array_key_exists('please_note', $content)) $content['please_note'] = '';
    if (array_diff(array_keys($content), array_keys($limits)) || array_diff(array_keys($limits), array_keys($content))) {
        throw new InvalidArgumentException('Provide the supported email content fields.');
    }
    $result = [];
    foreach ($limits as $key => $limit) {
        $value = $content[$key];
        if (!is_string($value) || !preg_match('//u', $value) || strlen($value) > $limit) {
            throw new InvalidArgumentException('Email field ' . $key . ' is invalid or too long (maximum ' . $limit . ' UTF-8 bytes).');
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)
            || (in_array($key, ['subject', 'greeting'], true) && preg_match('/[\r\n]/', $value))) {
            throw new InvalidArgumentException('Remove invalid control characters from ' . $key . '.');
        }
        $rich = str_ends_with($key, '_html');
        if (!$rich && preg_match('/[<>]/', $value)) throw new InvalidArgumentException('HTML is not allowed in ' . $key . '.');
        $value = $rich ? booking_email_editor_sanitize($value) : trim($value);
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (in_array($key, ['subject', 'greeting', 'introduction_html'], true) && trim(str_replace("\xc2\xa0", ' ', $text)) === '') {
            throw new InvalidArgumentException('Subject, greeting and introduction cannot be empty.');
        }
        preg_match_all('/\{([^{}]*)\}/u', $text, $matches);
        foreach ($matches[1] as $token) {
            if (!isset(booking_email_editor_placeholders($type)[$token])) throw new InvalidArgumentException('Unsupported placeholder: {' . $token . '}. Use Insert booking detail.');
        }
        if ($rich) {
            $tokenDoc = new DOMDocument('1.0', 'UTF-8');
            $previousErrors = libxml_use_internal_errors(true);
            try { $tokenDoc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $value . '</body></html>', LIBXML_NONET); }
            finally { libxml_clear_errors(); libxml_use_internal_errors($previousErrors); }
            $nodes = (new DOMXPath($tokenDoc))->query('//body//text()');
            $nodeTokens = [];
            foreach ($nodes as $textNode) {
                preg_match_all('/\{[^{}]*\}/u', $textNode->nodeValue, $nodeMatches);
                array_push($nodeTokens, ...$nodeMatches[0]);
            }
            $allTokens = $matches[0]; sort($allTokens); sort($nodeTokens);
            if ($allTokens !== $nodeTokens) throw new InvalidArgumentException('Keep each placeholder together. Apply formatting to the whole placeholder, not part of it.');
        }
        if (preg_match('/[{}]/', preg_replace('/\{[^{}]*\}/u', '', $text))) throw new InvalidArgumentException('A placeholder is incomplete. Use Insert booking detail.');
        $result[$key] = $value;
    }
    return $result;
}

function booking_email_editor_init(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS website_booking_email_templates (
        template_key VARCHAR(32) NOT NULL PRIMARY KEY,
        content_json TEXT NOT NULL,
        version INT UNSIGNED NOT NULL DEFAULT 1,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function booking_email_editor_read(PDO $pdo): array
{
    $defaults = booking_email_editor_defaults();
    $templates = [];
    foreach ($defaults as $key => $content) $templates[$key] = ['content' => $content, 'version' => 0, 'updated_at' => null];
    $rows = $pdo->query('SELECT template_key, content_json, version, updated_at FROM website_booking_email_templates')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $key = $row['template_key'];
        if (!isset($defaults[$key])) continue;
        $templates[$key] = ['content' => booking_email_editor_validate(json_decode($row['content_json'], true, 32, JSON_THROW_ON_ERROR), $key),
            'version' => (int) $row['version'], 'updated_at' => $row['updated_at']];
    }
    return ['templates' => $templates, 'defaults' => $defaults, 'placeholders' => booking_email_editor_placeholders(), 'placeholders_by_type' => array_combine(array_keys($defaults), array_map('booking_email_editor_placeholders', array_keys($defaults)))];
}

function booking_email_editor_save(PDO $pdo, array $input): array
{
    $type = booking_email_editor_type($input['type'] ?? null);
    $version = $input['version'] ?? null;
    if (!is_int($version) || $version < 0 || $version > 2147483646) throw new InvalidArgumentException('Reload this email template before saving.');
    $content = booking_email_editor_validate($input['content'] ?? null, $type);
    $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if ($version === 0) {
        try {
            $stmt = $pdo->prepare('INSERT INTO website_booking_email_templates (template_key, content_json, version) VALUES (?, ?, 1)');
            $stmt->execute([$type, $json]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) throw new RuntimeException('This template was changed in another session. Reload before saving.', 409);
            throw $e;
        }
    } else {
        $stmt = $pdo->prepare('UPDATE website_booking_email_templates SET content_json = ?, version = version + 1 WHERE template_key = ? AND version = ?');
        $stmt->execute([$json, $type, $version]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('This template was changed in another session. Reload before saving.', 409);
    }
    // Return precisely this write, even if another session saves immediately afterwards.
    return ['content' => $content, 'version' => $version + 1];
}
