<?php
declare(strict_types=1);

function security_env(string $key, string $default = ''): string {
    return (string) jebal_env_value($key, $default);
}
function trusted_proxy_request(): bool {
    $peers = array_filter(array_map('trim', explode(',', security_env('TRUSTED_PROXY_IPS'))));
    return in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), $peers, true);
}
function security_request_id(): string {
    static $id; return $id ??= bin2hex(random_bytes(16));
}
function security_enforce_transport(): void {
    if (PHP_SAPI !== 'cli' && APP_ENV !== 'local' && !is_https_request()) {
        http_response_code(400);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        exit('{"success":false,"message":"HTTPS is required."}');
    }
}
function require_post_request(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
}
// Serializes inventory mutation across all PHP workers sharing this MySQL server.
// A hotel-sized inventory benefits from correctness over concurrent writes.
function lock_booking_inventory(PDO $pdo): void {
    static $locked = false;
    if ($locked) return;
    $name = 'tulip_inventory_' . substr(hash('sha256', DB_NAME), 0, 32);
    $stmt = $pdo->prepare('SELECT GET_LOCK(?, 10)'); $stmt->execute([$name]);
    if ((int) $stmt->fetchColumn() !== 1) throw new RuntimeException('Inventory is busy. Please retry.');
    $locked = true;
    register_shutdown_function(static function () use ($pdo, $name): void {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]);
    });
}
function booking_access_token(int $id): string {
    return create_public_token('booking-checkout', ['booking_id' => $id, 'nonce' => bin2hex(random_bytes(16))], PUBLIC_LINK_TTL_SECONDS);
}
function require_booking_capability(int $id, mixed $token): void {
    if (!is_string($token) || strlen($token) > 2048 || !verify_public_token($token, 'booking-checkout', ['booking_id' => $id])) {
        json_response(false, 'A valid booking access token is required.', 403);
    }
}
function validate_public_stay(string $start, string $end): void {
    if (!is_valid_date($start) || !is_valid_date($end)) json_response(false, 'Invalid stay dates.', 422);
    $in = new DateTimeImmutable($start); $out = new DateTimeImmutable($end); $today = new DateTimeImmutable('today');
    $maxNights = max(1, min(90, (int) security_env('MAX_PUBLIC_STAY_NIGHTS', '30')));
    $horizon = max(1, min(730, (int) security_env('MAX_BOOKING_ADVANCE_DAYS', '365')));
    if ($in < $today || $out <= $in || $in > $today->modify('+' . $horizon . ' days') || (int) $in->diff($out)->days > $maxNights) {
        json_response(false, 'Choose a future stay of up to ' . $maxNights . ' nights within the next ' . $horizon . ' days.', 422);
    }
}
function rate_limit_subject(string $action, string $subject, int $max, int $minutes): void {
    rate_limit_or_fail(substr($action, 0, 25) . '_' . substr(hash('sha256', $subject), 0, 32), $max, $minutes, '0.0.0.0');
}
function protect_public_booking(array $data, string $email, string $phone, string $in, string $out): void {
    if (!preg_match('/^[0-9+() .-]{7,30}$/', $phone) || strlen(preg_replace('/\D/', '', $phone)) < 7 || strlen(preg_replace('/\D/', '', $phone)) > 15) json_response(false, 'Invalid phone number.', 422);
    validate_public_stay($in, $out);
    verify_bot_challenge($data, 'booking');
    rate_limit_subject('booking_email', strtolower($email), 4, 60);
    rate_limit_subject('booking_phone', preg_replace('/\D/', '', $phone), 4, 60);
    rate_limit_subject('booking_global', 'all', max(10, (int) security_env('MAX_PUBLIC_BOOKINGS_PER_HOUR', '40')), 60);
}
function verify_bot_challenge(array $data, string $action): void {
    $secret = security_env('TURNSTILE_SECRET_KEY');
    if (APP_ENV === 'local' && $secret === '') return;
    $token = $data['bot_token'] ?? '';
    if ($secret === '' || !function_exists('curl_init')) json_response(false, 'Form verification is unavailable. Please contact the property.', 503);
    if (!is_string($token) || $token === '' || strlen($token) > 2048) json_response(false, 'Complete the security check and try again.', 422);
    $curl = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => get_client_ip()]), CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
    $raw = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
    $result = is_string($raw) ? json_decode($raw, true) : null;
    $host = strtolower((string) parse_url(FRONTEND_URL, PHP_URL_HOST));
    if ($status !== 200 || !is_array($result) || ($result['success'] ?? false) !== true) {
        json_response(false, 'Security check failed or expired. Please try again.', 422);
    }
    // Cloudflare test tokens return synthetic action/hostname metadata. Accept
    // that metadata only with the official passing test secret in local mode,
    // after successful server-side verification. Real tokens keep strict checks.
    $localTestToken = APP_ENV === 'local'
        && hash_equals('1x0000000000000000000000000000000AA', $secret)
        && hash_equals('XXXX.DUMMY.TOKEN.XXXX', $token);
    if (!$localTestToken && (($result['action'] ?? '') !== $action || strtolower((string) ($result['hostname'] ?? '')) !== $host)) {
        json_response(false, 'Security check failed or expired. Please try again.', 422);
    }
}
function payment_is_gateway(array $payment): bool {
    return strcasecmp((string) ($payment['method'] ?? ''), 'PayHere') === 0
        || str_starts_with((string) ($payment['order_id'] ?? ''), 'ONLINE-');
}
function booking_has_gateway_payment(PDO $pdo, array $booking): bool {
    $stmt = $pdo->prepare("SELECT p.id FROM payments p JOIN bookings b ON b.id = p.booking_id
        WHERE (b.id = ? OR (? > 0 AND b.booking_group_id = ?))
        AND (p.method = 'PayHere' OR p.order_id LIKE 'ONLINE-%') LIMIT 1");
    $group = (int) ($booking['booking_group_id'] ?? 0);
    $stmt->execute([(int) $booking['id'], $group, $group]);
    return (bool) $stmt->fetchColumn();
}
function assert_manual_payment_allowed(PDO $pdo, array $booking, string $method): void {
    if (strcasecmp($method, 'PayHere') === 0 || booking_has_gateway_payment($pdo, $booking)) {
        json_response(false, 'Gateway payment records can only be changed by verified payment notifications.', 409);
    }
    if (!in_array($method, ['Cash', 'Manual', 'Bank Transfer', 'Card', 'No Pay', 'Other'], true)) json_response(false, 'Invalid payment method.', 422);
}
function security_audit(string $event, string $outcome, ?int $actor = null, array $metadata = []): void {
    // Explicit allowlist: never persist request bodies, credentials, cookies or links.
    $safe = array_intersect_key($metadata, array_flip(['target_id', 'booking_id', 'payment_id', 'status', 'method', 'role']));
    $stmt = get_db_connection()->prepare('INSERT INTO security_audit_events (request_id, actor_id, event, outcome, ip_address, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([security_request_id(), $actor, substr($event, 0, 190), substr($outcome, 0, 30), get_client_ip(), json_encode($safe, JSON_THROW_ON_ERROR)]);
}
function security_track_admin(array $admin): void {
    $GLOBALS['security_actor'] = (int) $admin['id'];
    static $tracked = false;
    if ($tracked) return;
    $tracked = true;
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if (!empty($_FILES)) rate_limit_subject('admin_upload', (string)$admin['id'], 20, 60);
    if (str_contains($script, '/mail-settings/test-')) rate_limit_subject('admin_mail_test', (string)$admin['id'], 5, 60);
    $path = substr((string) ($_SERVER['SCRIPT_NAME'] ?? ''), -180);
    // Record reads too: lists provide the source data for browser exports.
    security_audit($path, 'authorized', (int) $admin['id']);
    register_shutdown_function(static function () use ($path, $admin): void {
        try {
            $pdo = get_db_connection();
            $rolledBack = $pdo->inTransaction();
            if ($rolledBack) $pdo->rollBack();
            $outcome = $rolledBack ? 'rolled_back' : ($GLOBALS['security_outcome'] ?? (http_response_code() >= 400 ? 'rejected' : 'completed'));
            security_audit($path, $outcome, (int) $admin['id'], $GLOBALS['security_target'] ?? []);
        } catch (Throwable $e) { error_log('Security audit completion failed: ' . security_request_id()); }
    });
}

function security_lock_account(PDO $pdo, int $id): void {
    $name = 'tulip_otp_' . substr(hash('sha256', DB_NAME . ':' . $id), 0, 45);
    $q = $pdo->prepare('SELECT GET_LOCK(?, 5)'); $q->execute([$name]);
    if ((int)$q->fetchColumn() !== 1) json_response(false, 'Please retry shortly.', 429);
    register_shutdown_function(static function() use ($pdo, $name): void {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]);
    });
}
function require_calendar_permission(PDO $pdo, int $id, string $url): void {
    $q = $pdo->prepare('SELECT booking_ical_url FROM rooms WHERE id = ?'); $q->execute([$id]);
    if (trim((string)$q->fetchColumn()) === trim($url)) return;
    $admin = require_admin_auth();
    if ($admin['role'] !== 'super_admin') json_response(false, 'Owner access required to change calendar feeds.', 403);
}
function check_media_budget(int $incoming): void {
    $root = dirname(__DIR__) . '/uploads'; $total = 0; $count = 0;
    if (is_dir($root)) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) { $total += $file->getSize(); $count++; }
    }
    if ($total + $incoming > (int)security_env('MAX_MEDIA_STORAGE_BYTES', '2147483648') || $count >= (int)security_env('MAX_MEDIA_FILES', '10000')) throw new RuntimeException('Media storage limit reached.');
}
