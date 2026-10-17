<?php
declare(strict_types=1);
// CLI only: prepares a candidate file; never overwrites the active .env or database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../vendor/autoload.php';

function deployment_table_exists(PDO $pdo, string $table): bool
{
    $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $query->execute([$table]);
    return (int) $query->fetchColumn() > 0;
}
function deployment_secret_matches(string $encoded, string $key, string $aad = ''): bool
{
    $raw = base64_decode($encoded, true);
    return $raw !== false && strlen($raw) >= 29
        && openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA,
            substr($raw, 0, 12), substr($raw, 12, 16), $aad) !== false;
}
function deployment_select_payment_key(array $candidates, ?string $savedSecret): string
{
    foreach ($candidates as $candidate) {
        $key = base64_decode((string) $candidate, true);
        if ($key !== false && strlen($key) === 32
            && (!$savedSecret || deployment_secret_matches($savedSecret, $key, 'tulip-payhere-v1'))) {
            return base64_encode($key);
        }
    }
    if ($savedSecret) throw new RuntimeException('No supplied payment key decrypts the saved PayHere secret. Supply its original key file; no files were changed.');
    // Generate only after the target database confirms there is no saved secret.
    return base64_encode(random_bytes(32));
}
function deployment_env_text(array $values): string
{
    $text = "# Prepared for production. Keep this file private.\n";
    foreach ($values as $name => $value) {
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/D', (string) $name)) throw new RuntimeException('Unexpected environment variable name.');
        $value = (string) $value;
        // Single quotes prevent interpolation of credential characters such as $.
        if (strpbrk($value, "'\r\n\0") !== false) throw new RuntimeException('A value contains quotes/newlines requiring manual ENV editing; no file was written.');
        $text .= $name . "='" . $value . "'\n";
    }
    return $text;
}
// Helpers can be loaded by local tests without invoking deployment.
if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;
try {
    $options = getopt('', ['source:', 'origin:', 'payment-key-source:']);
    if (!isset($options['source'], $options['origin'])) {
        throw new RuntimeException('Usage: php tools/prepare-production.php --source=.env_server --origin=https://your-host [--payment-key-source=".env copy"]');
    }
    $origin = rtrim((string) $options['origin'], '/');
    $url = parse_url($origin);
    if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
        || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
        || !empty($url['path'])) throw new RuntimeException('Origin must be an HTTPS site origin with no path or credentials.');
    $source = realpath((string) $options['source']);
    if (!$source || !is_readable($source)) throw new RuntimeException('Source environment file is not readable.');
    $env = Dotenv\Dotenv::parse((string) file_get_contents($source));
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER'] as $required) {
        if (empty($env[$required])) throw new RuntimeException('The source must contain target database settings.');
    }
    try {
        $pdo = new PDO('mysql:host=' . $env['DB_HOST'] . ';dbname=' . $env['DB_NAME'] . ';charset=utf8mb4',
            $env['DB_USER'], $env['DB_PASS'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Throwable $e) {
        throw new RuntimeException('Could not connect to the source environment database. Run this on the target server with its database credentials. No file was written.');
    }
    $savedPayment = null;
    if (deployment_table_exists($pdo, 'hotel_payment_settings')) {
        $savedPayment = $pdo->query('SELECT encrypted_secret FROM hotel_payment_settings WHERE id = 1')->fetchColumn() ?: null;
    }
    $candidates = [$env['TULIP_PAYMENT_ENCRYPTION_KEY'] ?? ''];
    if (isset($options['payment-key-source'])) {
        $keySource = realpath((string) $options['payment-key-source']);
        if (!$keySource || !is_readable($keySource)) throw new RuntimeException('Payment-key source is not readable.');
        $oldEnv = Dotenv\Dotenv::parse((string) file_get_contents($keySource));
        $candidates[] = $oldEnv['TULIP_PAYMENT_ENCRYPTION_KEY'] ?? '';
    }
    $env['TULIP_PAYMENT_ENCRYPTION_KEY'] = deployment_select_payment_key($candidates, $savedPayment);
    $mailSecrets = [];
    if (deployment_table_exists($pdo, 'mail_accounts')) {
        $mailSecrets = $pdo->query('SELECT encrypted_password FROM mail_accounts')->fetchAll(PDO::FETCH_COLUMN);
    }
    if (deployment_table_exists($pdo, 'mail_provider_settings')) {
        foreach ($pdo->query('SELECT encrypted_password, encrypted_client_secret, encrypted_refresh_token FROM mail_provider_settings')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mailSecrets = array_merge($mailSecrets, array_values($row));
        }
    }
    foreach (array_filter($mailSecrets) as $secret) {
        if (empty($env['MAIL_CREDENTIAL_ENCRYPTION_KEY']) || !deployment_secret_matches((string) $secret,
            hash('sha256', trim((string) $env['MAIL_CREDENTIAL_ENCRYPTION_KEY']), true))) {
            throw new RuntimeException('The source mail encryption key cannot decrypt saved mail credentials. Use the original server key. No file was written.');
        }
    }
    if (empty($env['MAIL_CREDENTIAL_ENCRYPTION_KEY'])) $env['MAIL_CREDENTIAL_ENCRYPTION_KEY'] = bin2hex(random_bytes(32));
    $env = array_replace($env, [
        'APP_ENV' => 'production', 'APP_TIMEZONE' => 'Asia/Colombo',
        'APP_BASE_URL' => $origin, 'FRONTEND_URL' => $origin, 'PUBLIC_APP_URL' => $origin,
        'ADMIN_APP_URL' => $origin . '/admin', 'API_BASE_URL' => $origin . '/api',
        'ASSET_BASE_URL' => $origin . '/api', 'ALLOWED_ORIGINS' => $origin,
        'ICS_TEST_MODE' => 'false', 'TEST_EMAIL_DEBUG' => 'false', 'TEST_EMAIL_TOOL_ENABLED' => 'false',
        'FFMPEG_BINARY' => $env['FFMPEG_BINARY'] ?? 'ffmpeg',
    ]);
    $text = deployment_env_text($env);
    if (Dotenv\Dotenv::parse($text) !== array_map('strval', $env)) throw new RuntimeException('Environment round-trip verification failed.');
    $destination = dirname(__DIR__) . '/.env.production.ready';
    $oldMask = umask(0077);
    $handle = fopen($destination, 'x');
    umask($oldMask);
    if (!$handle) throw new RuntimeException('Candidate file already exists or cannot be created. Move the previous candidate before retrying.');
    $written = fwrite($handle, $text); fclose($handle);
    if ($written !== strlen($text)) { unlink($destination); throw new RuntimeException('Could not write the complete candidate file.'); }
    echo "Prepared api/.env.production.ready. Database and active .env were not changed.\n";
    $mappings = json_decode($env['ICS_ROOM_MAPPINGS_JSON'] ?? '[]', true);
    if (!$mappings) echo "NOTE: No calendar export mappings are configured. Imports use room settings separately.\n";
    echo "Review the candidate, back up the active .env outside the web root, then install the candidate as api/.env.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n"); exit(1);
}
