<?php
declare(strict_types=1);

function ensure_contact_settings_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS website_settings (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function default_contact_settings(): array
{
    $weeklySchedule = [];
    foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
        $weeklySchedule[$day] = ['enabled' => true, 'all_day' => false, 'open' => '08:00', 'close' => '18:00'];
    }

    return [
        'business_name' => 'Tulip Guest Inn',
        'opening_year' => (string) max(1900, (int) date('Y') - 8),
        'address' => 'Tulip Guest Inn, Sri Lanka',
        'phone' => '+94 77 123 4567',
        'reception_contact_number' => '+94 21 222 4567',
        'whatsapp_reservation_number' => '+94 77 123 4567',
        'email' => defined('CONTACT_FROM_EMAIL') ? (string) CONTACT_FROM_EMAIL : '',
        'check_in_mode' => '24_hours',
        'stay_time_mode' => 'custom',
        'check_in_custom' => 'Available 24 hours',
        'check_out_custom' => 'From 06:00 to 12:00',
        'check_in_time' => '13:00',
        'check_out_from' => '06:00',
        'check_out_until' => '12:00',
        'business_hours' => 'Daily · 7:00 AM – 10:00 PM',
        'business_hours_mode' => '24_7',
        'business_hours_schedule' => json_encode($weeklySchedule, JSON_UNESCAPED_SLASHES),
        'social_links' => '',
        'facebook_link' => '',
        'instagram_link' => '',
        'map_embed_url' => '',
    ];
}

function clean_multiline_setting(mixed $value, int $maxLength = 1000): string
{
    $value = str_replace(["\r\n", "\r"], "\n", trim((string) $value));
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr($value, 0, $maxLength);
}

function clean_business_hours_schedule(mixed $value): string
{
    $decoded = is_array($value) ? $value : json_decode((string) $value, true);
    if (!is_array($decoded)) {
        json_response(false, 'Weekly business hours are invalid.', 422);
    }

    $cleaned = [];
    foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
        $entry = is_array($decoded[$day] ?? null) ? $decoded[$day] : [];
        $enabled = filter_var($entry['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $allDay = $enabled && filter_var($entry['all_day'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $open = (string) ($entry['open'] ?? '08:00');
        $close = (string) ($entry['close'] ?? '18:00');

        if ($enabled && !$allDay) {
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $open) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $close)) {
                json_response(false, ucfirst($day) . ' opening and closing times are required.', 422);
            }
            if ($open >= $close) {
                json_response(false, ucfirst($day) . ' closing time must be after opening time.', 422);
            }
        }

        $cleaned[$day] = ['enabled' => $enabled, 'all_day' => $allDay, 'open' => $open, 'close' => $close];
    }

    return (string) json_encode($cleaned, JSON_UNESCAPED_SLASHES);
}

function get_contact_settings(PDO $pdo): array
{
    ensure_contact_settings_table($pdo);

    $settings = default_contact_settings();
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM website_settings');

    foreach ($stmt->fetchAll() as $row) {
        $key = (string) ($row['setting_key'] ?? '');
        if (array_key_exists($key, $settings)) {
            $settings[$key] = (string) ($row['setting_value'] ?? '');
        }
    }

    return $settings;
}

function save_contact_settings(PDO $pdo, array $settings): array
{
    ensure_contact_settings_table($pdo);

    $allowed = default_contact_settings();
    $cleaned = [];

    foreach ($allowed as $key => $defaultValue) {
        if ($key === 'address') {
            $cleaned[$key] = clean_multiline_setting($settings[$key] ?? $defaultValue, 1000);
            continue;
        }
        if ($key === 'business_hours_schedule') {
            $cleaned[$key] = clean_business_hours_schedule($settings[$key] ?? $defaultValue);
            continue;
        }
        if ($key === 'social_links') {
            $existing = get_contact_settings($pdo);
            $cleaned[$key] = clean_social_links($settings[$key] ?? $existing[$key]);
            continue;
        }
        $maxLength = in_array($key, ['map_embed_url', 'facebook_link', 'instagram_link'], true) ? 1000 : 255;
        $cleaned[$key] = clean_string($settings[$key] ?? $defaultValue, $maxLength);
    }

    if (!in_array($cleaned['stay_time_mode'], ['standard', 'custom'], true)) {
        json_response(false, 'Choose valid stay times.', 422);
    }
    if ($cleaned['stay_time_mode'] === 'custom' && ($cleaned['check_in_custom'] === '' || $cleaned['check_out_custom'] === '')) {
        json_response(false, 'Enter check-in and check-out times.', 422);
    }
    if (!in_array($cleaned['check_in_mode'], ['24_hours', 'fixed'], true)) {
        json_response(false, 'Choose a valid check-in mode.', 422);
    }
    foreach (['check_in_time', 'check_out_from', 'check_out_until'] as $timeKey) {
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $cleaned[$timeKey])) {
            json_response(false, 'Enter a valid time for ' . str_replace('_', ' ', $timeKey) . '.', 422);
        }
    }
    if ($cleaned['check_out_from'] >= $cleaned['check_out_until']) {
        json_response(false, 'Check-out end time must be later than the start time.', 422);
    }

    if (!in_array($cleaned['business_hours_mode'], ['24_7', 'custom'], true)) {
        json_response(false, 'Select a valid business-hours mode.', 422);
    }

    if ($cleaned['business_name'] === '') {
        json_response(false, 'Business name is required.', 422);
    }

    $openingYear = (int) $cleaned['opening_year'];
    $currentYear = (int) date('Y');
    if ($openingYear < 1900 || $openingYear > $currentYear) {
        json_response(false, 'Opening year must be between 1900 and ' . $currentYear . '.', 422);
    }
    $cleaned['opening_year'] = (string) $openingYear;

    if (
        $cleaned['phone'] === '' &&
        $cleaned['reception_contact_number'] === '' &&
        $cleaned['whatsapp_reservation_number'] === ''
    ) {
        json_response(false, 'At least one contact number is required.', 422);
    }

    if ($cleaned['email'] === '' || !filter_var($cleaned['email'], FILTER_VALIDATE_EMAIL)) {
        json_response(false, 'A valid reservation email is required.', 422);
    }

    foreach (['facebook_link', 'instagram_link', 'map_embed_url'] as $urlKey) {
        if ($cleaned[$urlKey] !== '' && !filter_var($cleaned[$urlKey], FILTER_VALIDATE_URL)) {
            json_response(false, ucfirst(str_replace('_', ' ', $urlKey)) . ' must be a valid URL.', 422);
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO website_settings (setting_key, setting_value, updated_at)
         VALUES (:setting_key, :setting_value, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
    );

    foreach ($cleaned as $key => $value) {
        $stmt->execute([
            ':setting_key' => $key,
            ':setting_value' => $value,
        ]);
    }

    return $cleaned;
}

function clean_social_links(mixed $value): string
{
    if ($value === '') return '';
    $items = is_array($value) ? $value : json_decode((string) $value, true);
    if (!is_array($items) || !array_is_list($items) || count($items) > 20) {
        json_response(false, 'Social links must be a list of at most 20 entries.', 422);
    }
    $result = [];
    foreach ($items as $item) {
        if (!is_array($item)) json_response(false, 'Invalid social link.', 422);
        $url = trim((string) ($item['url'] ?? ''));
        if ($url === '' || $url === '#') continue;
        $platform = (string) ($item['platform'] ?? 'other');
        if (!in_array($platform, ['facebook','instagram','youtube','tiktok','x','linkedin','other'], true)) {
            json_response(false, 'Choose a valid social platform.', 422);
        }
        if (strlen($url) > 1000 || !filter_var($url, FILTER_VALIDATE_URL)
            || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http','https'], true)
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            json_response(false, 'Enter a valid http or https social URL without login details.', 422);
        }
        $label = clean_string($item['label'] ?? '', 60);
        $result[] = ['platform' => $platform, 'url' => $url, 'label' => $label];
    }
    return (string) json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
