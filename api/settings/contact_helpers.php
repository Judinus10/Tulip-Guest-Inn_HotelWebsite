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
    return [
        'business_name' => 'Tulip Guest Inn',
        'address' => 'Tulip Guest Inn, Sri Lanka',
        'phone' => '+94 77 123 4567',
        'reception_contact_number' => '+94 21 222 4567',
        'whatsapp_reservation_number' => '+94 77 123 4567',
        'email' => defined('CONTACT_FROM_EMAIL') ? (string) CONTACT_FROM_EMAIL : '',
        'business_hours' => 'Daily · 7:00 AM – 10:00 PM',
        'facebook_link' => '',
        'instagram_link' => '',
        'map_embed_url' => '',
    ];
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
        $maxLength = in_array($key, ['map_embed_url', 'facebook_link', 'instagram_link'], true) ? 1000 : 255;
        $cleaned[$key] = clean_string($settings[$key] ?? $defaultValue, $maxLength);
    }

    if ($cleaned['business_name'] === '') {
        json_response(false, 'Business name is required.', 422);
    }

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