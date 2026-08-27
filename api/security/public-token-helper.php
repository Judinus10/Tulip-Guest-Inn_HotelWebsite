<?php
declare(strict_types=1);

if (!function_exists('public_token_secret')) {
function public_token_secret(): string
{
    $secret = defined('PUBLIC_LINK_SIGNING_KEY') ? PUBLIC_LINK_SIGNING_KEY : '';
    if ($secret === '') {
        throw new RuntimeException('Public link signing key is not configured.');
    }
    return $secret;
}
}

if (!function_exists('base64url_encode')) {
function base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}
}

if (!function_exists('base64url_decode')) {
function base64url_decode(string $value): string|false
{
    $padding = (4 - strlen($value) % 4) % 4;
    return base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
}
}

if (!function_exists('create_public_token')) {
function create_public_token(string $purpose, array $claims, int $ttlSeconds): string
{
    $payload = [
        'v' => 1,
        'purpose' => $purpose,
        'exp' => time() + max(60, $ttlSeconds),
        'claims' => $claims,
    ];
    $encoded = base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $signature = base64url_encode(hash_hmac('sha256', $encoded, public_token_secret(), true));
    return $encoded . '.' . $signature;
}
}

if (!function_exists('verify_public_token')) {
function verify_public_token(string $token, string $purpose, array $expectedClaims): bool
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) return false;

    [$encoded, $signature] = $parts;
    $expectedSignature = base64url_encode(hash_hmac('sha256', $encoded, public_token_secret(), true));
    if (!hash_equals($expectedSignature, $signature)) return false;

    $decoded = base64url_decode($encoded);
    if ($decoded === false) return false;
    $payload = json_decode($decoded, true);

    if (!is_array($payload)
        || ($payload['purpose'] ?? '') !== $purpose
        || !is_int($payload['exp'] ?? null)
        || $payload['exp'] < time()
        || !is_array($payload['claims'] ?? null)) {
        return false;
    }

    foreach ($expectedClaims as $key => $value) {
        if (!array_key_exists($key, $payload['claims'])
            || !hash_equals((string) $value, (string) $payload['claims'][$key])) {
            return false;
        }
    }
    return true;
}
}

if (!function_exists('legacy_public_tokens_allowed')) {
function legacy_public_tokens_allowed(): bool
{
    return defined('PUBLIC_LINK_LEGACY_UNTIL')
        && PUBLIC_LINK_LEGACY_UNTIL !== ''
        && strtotime(PUBLIC_LINK_LEGACY_UNTIL) !== false
        && time() <= (int) strtotime(PUBLIC_LINK_LEGACY_UNTIL);
}
}
