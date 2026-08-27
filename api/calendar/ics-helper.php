<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

function ics_enabled(): bool
{
    return filter_var(jebal_env_value('ICS_SYNC_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
}

function ics_timezone(): DateTimeZone
{
    try {
        return new DateTimeZone((string) jebal_env_value('ICS_DEFAULT_TIMEZONE', APP_TIMEZONE));
    } catch (Throwable) {
        return new DateTimeZone('Asia/Colombo');
    }
}

function ics_connections(): array
{
    if (!ics_enabled()) {
        return [];
    }

    $raw = jebal_env_value('ICS_ROOM_MAPPINGS_JSON', '[]');
    $rows = is_array($raw) ? $raw : json_decode((string) $raw, true);
    if (!is_array($rows)) {
        return [];
    }

    return array_values(array_filter($rows, static function ($row): bool {
        return is_array($row)
            && !empty($row['enabled'])
            && (int) ($row['room_id'] ?? 0) > 0
            && trim((string) ($row['import_url'] ?? '')) !== ''
            && trim((string) ($row['export_token'] ?? '')) !== '';
    }));
}

function ensure_ics_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $tableCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table_name"
    );
    $tableCheck->execute([':table_name' => 'external_calendar_events']);
    $eventsTableExists = (int) $tableCheck->fetchColumn() > 0;
    $tableCheck->execute([':table_name' => 'external_calendar_sync_status']);
    $statusTableExists = (int) $tableCheck->fetchColumn() > 0;

    if (!$eventsTableExists) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS external_calendar_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        room_id INT UNSIGNED NOT NULL,
        provider VARCHAR(40) NOT NULL DEFAULT 'booking.com',
        external_uid VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        summary VARCHAR(255) NULL,
        status VARCHAR(40) NULL,
        external_last_modified DATETIME NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_seen_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_provider_room_uid (provider, room_id, external_uid),
        KEY idx_external_room_dates (room_id, start_date, end_date, is_active),
        CONSTRAINT fk_external_calendar_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$statusTableExists) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS external_calendar_sync_status (
        room_id INT UNSIGNED NOT NULL PRIMARY KEY,
        provider VARCHAR(40) NOT NULL DEFAULT 'booking.com',
        last_sync_started_at DATETIME NULL,
        last_sync_completed_at DATETIME NULL,
        last_sync_status VARCHAR(30) NULL,
        last_sync_error TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_external_sync_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    $done = true;
}

function ics_room_conflict(PDO $pdo, int $roomId, string $checkIn, string $checkOut): bool
{
    if (!ics_enabled()) {
        return false;
    }

    ensure_ics_schema($pdo);
    $stmt = $pdo->prepare(
        "SELECT id FROM external_calendar_events
         WHERE room_id = :room_id AND provider = 'booking.com' AND is_active = 1
           AND :check_in < end_date AND :check_out > start_date
         LIMIT 1"
    );
    $stmt->execute([':room_id' => $roomId, ':check_in' => $checkIn, ':check_out' => $checkOut]);
    return (bool) $stmt->fetch();
}

function unfold_ics(string $body): array
{
    $body = preg_replace("/\r\n[ \t]/", '', $body) ?? $body;
    return preg_split('/\r\n|\n|\r/', $body) ?: [];
}

function parse_ics_date(string $value): ?DateTimeImmutable
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    try {
        if (preg_match('/^\d{8}$/', $value)) {
            return DateTimeImmutable::createFromFormat('!Ymd', $value, ics_timezone()) ?: null;
        }
        if (str_ends_with($value, 'Z')) {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(ics_timezone());
        }
        return new DateTimeImmutable($value, ics_timezone());
    } catch (Throwable) {
        return null;
    }
}

function parse_ics_events(string $body): array
{
    $events = [];
    $current = null;
    foreach (unfold_ics($body) as $line) {
        if ($line === 'BEGIN:VEVENT') {
            $current = [];
            continue;
        }
        if ($line === 'END:VEVENT') {
            if (is_array($current) && !empty($current['UID']) && !empty($current['DTSTART']) && !empty($current['DTEND'])) {
                $events[] = $current;
            }
            $current = null;
            continue;
        }
        if (!is_array($current) || !str_contains($line, ':')) {
            continue;
        }
        [$left, $value] = explode(':', $line, 2);
        $parts = explode(';', $left);
        $key = strtoupper((string) array_shift($parts));
        $current[$key] = $value;
    }
    return $events;
}

function validate_ics_url(string $url): bool
{
    $parts = parse_url($url);
    if (!$parts || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
        return false;
    }
    $host = strtolower((string) $parts['host']);
    return $host === 'admin.booking.com' || str_ends_with($host, '.booking.com');
}

function ics_test_mode_enabled(): bool
{
    return APP_ENV === 'local'
        && filter_var(jebal_env_value('ICS_TEST_MODE', false), FILTER_VALIDATE_BOOLEAN);
}

function fetch_local_test_ics(string $source): string
{
    if (!ics_test_mode_enabled()) {
        throw new RuntimeException('Local calendar test mode is disabled.');
    }

    $parts = parse_url($source);
    if (!$parts || strtolower((string) ($parts['scheme'] ?? '')) !== 'test') {
        throw new RuntimeException('Invalid local test calendar reference.');
    }

    // Only a basename inside api/calendar/test-feeds is accepted. This prevents
    // test mode from being used to read arbitrary files from the computer.
    $fileName = basename((string) (($parts['host'] ?? '') . ($parts['path'] ?? '')));
    if (!preg_match('/^[a-zA-Z0-9_-]+\.ics$/', $fileName)) {
        throw new RuntimeException('Invalid local test calendar filename.');
    }

    $testDirectory = realpath(__DIR__ . '/test-feeds');
    $filePath = realpath(__DIR__ . '/test-feeds/' . $fileName);
    if ($testDirectory === false || $filePath === false || dirname($filePath) !== $testDirectory || !is_file($filePath)) {
        throw new RuntimeException('Local test calendar file was not found: ' . $fileName);
    }

    $maxBytes = max(1024, (int) jebal_env_value('ICS_MAX_RESPONSE_BYTES', 1048576));
    $size = filesize($filePath);
    if ($size === false || $size > $maxBytes) {
        throw new RuntimeException('Local test calendar exceeds the allowed size.');
    }

    $body = file_get_contents($filePath);
    if ($body === false || !str_contains($body, 'BEGIN:VCALENDAR')) {
        throw new RuntimeException('Local test calendar is invalid.');
    }
    return $body;
}

function fetch_ics(string $url): string
{
    if (str_starts_with(strtolower(trim($url)), 'test://')) {
        return fetch_local_test_ics(trim($url));
    }

    if (!validate_ics_url($url)) {
        throw new RuntimeException('Untrusted Booking.com calendar URL.');
    }

    $maxBytes = max(1024, (int) jebal_env_value('ICS_MAX_RESPONSE_BYTES', 1048576));
    $body = '';
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Unable to initialize calendar download.');
    }

    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => max(5, (int) jebal_env_value('ICS_REQUEST_TIMEOUT_SECONDS', 15)),
        CURLOPT_USERAGENT => 'TulipGuestInn-BookingCalendar/1.0',
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, $maxBytes): int {
            if (strlen($body) + strlen($chunk) > $maxBytes) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);

    $ok = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($ok === false || $statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException('Calendar download failed' . ($error !== '' ? ': ' . $error : '.'));
    }
    if (!str_contains($body, 'BEGIN:VCALENDAR')) {
        throw new RuntimeException('Booking.com returned an invalid calendar.');
    }
    return $body;
}

function sync_ics_room(PDO $pdo, array $map): array
{
    if (!ics_enabled()) {
        return [
            'room_id' => (int) ($map['room_id'] ?? 0),
            'success' => false,
            'events' => 0,
            'error' => 'Calendar synchronization is disabled.',
        ];
    }

    ensure_ics_schema($pdo);
    $roomId = (int) $map['room_id'];
    $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

    $roomCheck = $pdo->prepare('SELECT id, room_name FROM rooms WHERE id = :id LIMIT 1');
    $roomCheck->execute([':id' => $roomId]);
    $room = $roomCheck->fetch();
    if (!$room) {
        return ['room_id' => $roomId, 'success' => false, 'events' => 0, 'error' => 'Mapped room does not exist.'];
    }

    $pdo->prepare(
        "INSERT INTO external_calendar_sync_status (room_id, last_sync_started_at, last_sync_status)
         VALUES (:room_id, :started, 'running')
         ON DUPLICATE KEY UPDATE last_sync_started_at = VALUES(last_sync_started_at), last_sync_status = 'running', last_sync_error = NULL"
    )->execute([':room_id' => $roomId, ':started' => $now]);

    try {
        $events = parse_ics_events(fetch_ics((string) $map['import_url']));
        $seen = [];
        $pdo->beginTransaction();

        $upsert = $pdo->prepare(
            "INSERT INTO external_calendar_events
                (room_id, provider, external_uid, start_date, end_date, summary, status, external_last_modified, is_active, last_seen_at)
             VALUES (:room_id, 'booking.com', :uid, :start_date, :end_date, :summary, :status, :last_modified, :is_active, :seen)
             ON DUPLICATE KEY UPDATE
                start_date = VALUES(start_date), end_date = VALUES(end_date), summary = VALUES(summary),
                status = VALUES(status), external_last_modified = VALUES(external_last_modified),
                is_active = VALUES(is_active), last_seen_at = VALUES(last_seen_at)"
        );

        foreach ($events as $event) {
            $start = parse_ics_date((string) $event['DTSTART']);
            $end = parse_ics_date((string) $event['DTEND']);
            $uid = mb_substr(trim((string) $event['UID']), 0, 255);
            if (!$start || !$end || $end <= $start || $uid === '') {
                continue;
            }

            $status = strtoupper(trim((string) ($event['STATUS'] ?? '')));
            $isActive = !in_array($status, ['CANCELLED', 'CANCELED'], true);
            $lastModified = isset($event['LAST-MODIFIED']) ? parse_ics_date((string) $event['LAST-MODIFIED']) : null;
            $upsert->execute([
                ':room_id' => $roomId,
                ':uid' => $uid,
                ':start_date' => $start->format('Y-m-d'),
                ':end_date' => $end->format('Y-m-d'),
                ':summary' => mb_substr(trim((string) ($event['SUMMARY'] ?? 'Booking.com reservation')), 0, 255),
                ':status' => $isActive ? ($status !== '' ? $status : 'CONFIRMED') : 'CANCELLED',
                ':last_modified' => $lastModified?->format('Y-m-d H:i:s'),
                ':is_active' => $isActive ? 1 : 0,
                ':seen' => $now,
            ]);
            $seen[] = $uid;
        }

        // Anything previously imported but absent from the newest complete feed is cancelled.
        $pdo->prepare(
            "UPDATE external_calendar_events
             SET is_active = 0, status = 'CANCELLED'
             WHERE room_id = :room_id AND provider = 'booking.com' AND is_active = 1 AND last_seen_at < :seen"
        )->execute([':room_id' => $roomId, ':seen' => $now]);

        $pdo->commit();
        $pdo->prepare(
            "UPDATE external_calendar_sync_status
             SET last_sync_completed_at = :completed, last_sync_status = 'success', last_sync_error = NULL
             WHERE room_id = :room_id"
        )->execute([':completed' => $now, ':room_id' => $roomId]);

        return ['room_id' => $roomId, 'room_name' => $room['room_name'], 'success' => true, 'events' => count($seen)];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->prepare(
            "UPDATE external_calendar_sync_status
             SET last_sync_completed_at = :completed, last_sync_status = 'failed', last_sync_error = :error
             WHERE room_id = :room_id"
        )->execute([
            ':completed' => $now,
            ':error' => mb_substr($exception->getMessage(), 0, 1000),
            ':room_id' => $roomId,
        ]);
        return ['room_id' => $roomId, 'room_name' => $room['room_name'], 'success' => false, 'events' => 0, 'error' => $exception->getMessage()];
    }
}

function ics_escape(string $value): string
{
    return str_replace(["\\", ';', ',', "\r", "\n"], ["\\\\", '\\;', '\\,', '', '\\n'], $value);
}
