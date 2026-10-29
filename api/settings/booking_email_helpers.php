<?php
declare(strict_types=1);

function booking_email_editor_placeholders(): array
{
    return ['guest_name' => 'Guest name', 'booking_reference' => 'Booking reference',
        'check_in' => 'Check-in date', 'check_out' => 'Check-out date', 'hotel_name' => 'Hotel name'];
}

function booking_email_editor_defaults(): array
{
    $common = ['greeting' => 'Hello {guest_name},',
        'closing_html' => '<p>If you have any questions before your stay, please contact us.</p>',
        'footer_note' => 'We look forward to welcoming you to Point Pedro.'];
    return [
        'received' => array_merge($common, [
            'subject' => 'We received your booking - {booking_reference}',
            'introduction_html' => '<p>Thank you for choosing {hotel_name}. We have received your booking request and will confirm availability shortly.</p>',
        ]),
        'confirmed' => array_merge($common, [
            'subject' => 'Your booking is confirmed - {booking_reference}',
            'introduction_html' => '<p>Thank you for choosing {hotel_name}. Your booking is confirmed. We look forward to welcoming you.</p>',
        ]),
    ];
}

function booking_email_editor_type(mixed $type): string
{
    if (!is_string($type) || !array_key_exists($type, booking_email_editor_defaults())) {
        throw new InvalidArgumentException('Choose Booking Received or Booking Confirmed.');
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

function booking_email_editor_validate(mixed $content): array
{
    $limits = ['subject' => 200, 'greeting' => 200, 'introduction_html' => 6000, 'closing_html' => 4000, 'footer_note' => 500];
    if (!is_array($content) || array_diff(array_keys($content), array_keys($limits)) || array_diff(array_keys($limits), array_keys($content))) {
        throw new InvalidArgumentException('Provide all five email content fields.');
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
            if (!isset(booking_email_editor_placeholders()[$token])) throw new InvalidArgumentException('Unsupported placeholder: {' . $token . '}. Use Insert booking detail.');
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
        $templates[$key] = ['content' => booking_email_editor_validate(json_decode($row['content_json'], true, 32, JSON_THROW_ON_ERROR)),
            'version' => (int) $row['version'], 'updated_at' => $row['updated_at']];
    }
    return ['templates' => $templates, 'defaults' => $defaults, 'placeholders' => booking_email_editor_placeholders()];
}

function booking_email_editor_save(PDO $pdo, array $input): array
{
    $type = booking_email_editor_type($input['type'] ?? null);
    $version = $input['version'] ?? null;
    if (!is_int($version) || $version < 0 || $version > 2147483646) throw new InvalidArgumentException('Reload this email template before saving.');
    $content = booking_email_editor_validate($input['content'] ?? null);
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
