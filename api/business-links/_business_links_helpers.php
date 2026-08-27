<?php
declare(strict_types=1);
require_once __DIR__ . '/../mail-settings/_mail_settings_helpers.php';

const BUSINESS_LINK_CATEGORIES = ['business', 'booking', 'email', 'analytics', 'hosting', 'other'];

function ensure_business_links_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS external_portal_links (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        portal_key VARCHAR(80) NOT NULL,
        title VARCHAR(120) NOT NULL,
        description VARCHAR(500) NULL,
        portal_url VARCHAR(1000) NOT NULL,
        category VARCHAR(30) NOT NULL DEFAULT 'other',
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        is_system TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_external_portal_key (portal_key),
        KEY idx_external_portal_enabled (is_enabled, title)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function validate_business_url(string $url): string
{
    $url = trim($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
        json_response(false, 'Enter a complete HTTPS address, for example https://business.google.com/.', 422, ['field' => 'portal_url']);
    }
    return $url;
}

function business_link_key(string $title): string
{
    $key = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
    return mb_substr($key !== '' ? $key : 'business-link', 0, 60) . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
}

function public_business_link(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'portal_key' => (string) $row['portal_key'],
        'title' => (string) $row['title'],
        'description' => (string) ($row['description'] ?? ''),
        'portal_url' => (string) $row['portal_url'],
        'category' => (string) $row['category'],
        'is_enabled' => (bool) $row['is_enabled'],
        'is_system' => (bool) $row['is_system'],
    ];
}
