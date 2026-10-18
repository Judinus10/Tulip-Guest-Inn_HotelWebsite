<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

function ics_enabled(): bool
{
    return filter_var(jebal_env_value('ICS_SYNC_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
}

/** Master switch controls new imports only. Existing imported reservations stay active. */
function ics_master_sync_enabled(?PDO $pdo = null): bool
{
    if (!ics_enabled()) return false;
    try {
        $pdo ??= get_db_connection();
        ics_master_settings_table($pdo);
        $stmt = $pdo->query('SELECT sync_enabled FROM hotel_calendar_sync_settings WHERE id = 1');
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Calendar master settings unavailable: ' . $e->getMessage());
        return false; // Fail closed: never run imports if configuration cannot be read.
    }
}

function ics_master_settings_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS hotel_calendar_sync_settings (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        sync_enabled TINYINT(1) NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec('INSERT IGNORE INTO hotel_calendar_sync_settings (id, sync_enabled) VALUES (1, 0)');
}

function ics_timezone(): DateTimeZone
{
    try {
        return new DateTimeZone((string) jebal_env_value('ICS_DEFAULT_TIMEZONE', APP_TIMEZONE));
    } catch (Throwable) {
        return new DateTimeZone('Asia/Colombo');
    }
}

/** Read Booking.com import URLs configured in Admin > Rooms > Edit Room. */
function ics_connections(?PDO $pdo = null): array
{
    if (!ics_enabled()) return [];
    $pdo ??= get_db_connection();
    // Older databases may not yet have the column until the Rooms API is used.
    $column = $pdo->query("SHOW COLUMNS FROM rooms LIKE 'booking_ical_url'");
    if (!$column->fetch()) return [];
    $rows = $pdo->query("SELECT id, booking_ical_url FROM rooms WHERE booking_ical_url IS NOT NULL AND TRIM(booking_ical_url) <> '' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $connections = [];
    foreach ($rows as $row) {
        $url = trim((string)$row['booking_ical_url']);
        if (validate_ics_url($url) || (ics_test_mode_enabled() && str_starts_with($url, 'test://'))) {
            $connections[] = ['room_id' => (int)$row['id'], 'import_url' => $url, 'enabled' => true];
        }
    }
    return $connections;
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
    $body = preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body;
    $body = preg_replace('/(?:\r\n|\n|\r)[ \t]/', '', $body) ?? $body;
    return preg_split('/\r\n|\n|\r/', $body) ?: [];
}

function parse_ics_date(string $value, ?string $timezone = null): ?DateTimeImmutable
{
    $value = trim($value);
    if (!preg_match('/^(?:\d{8}|\d{8}T\d{6}Z?)$/D', $value)) return null;
    try {
        $utc = str_ends_with($value, 'Z');
        $zone = $utc ? new DateTimeZone('UTC') : ($timezone ? new DateTimeZone($timezone) : ics_timezone());
        $format = strlen($value) === 8 ? 'Ymd' : ($utc ? 'Ymd\THis\Z' : 'Ymd\THis');
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value, $zone);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
            || $date->format($format) !== $value) return null;
        return $date->setTimezone(ics_timezone());
    } catch (Throwable) {
        return null;
    }
}

/** Parse a complete snapshot, or throw before any reservation is changed. */
function parse_ics_events(string $body): array
{
    $events = [];
    $stack = [];
    $current = null;
    $opened = false;
    $closed = false;
    $version = null;
    $uids = [];
    foreach (unfold_ics($body) as $line) {
        if ($line === '') continue;
        if (!str_contains($line, ':')) throw new RuntimeException('Malformed calendar line. Existing reservations were retained.');
        [$left, $value] = explode(':', $line, 2);
        $parts = explode(';', $left);
        $key = strtoupper((string) array_shift($parts));
        $parent = $stack ? $stack[count($stack) - 1] : null;
        if ($key === 'BEGIN') {
            $component = strtoupper($value);
            $allowed = $parent === null ? ['VCALENDAR']
                : ($parent === 'VCALENDAR' ? ['VEVENT', 'VTIMEZONE']
                : ($parent === 'VEVENT' ? ['VALARM'] : ($parent === 'VTIMEZONE' ? ['STANDARD', 'DAYLIGHT'] : [])));
            if ($closed || !in_array($component, $allowed, true)
                || ($component === 'VCALENDAR' && $opened)) throw new RuntimeException('Invalid calendar structure.');
            if ($component === 'VCALENDAR') $opened = true;
            if ($component === 'VEVENT') $current = [];
            $stack[] = $component;
            continue;
        }
        if ($key === 'END') {
            if ($parent === null || strtoupper($value) !== $parent) throw new RuntimeException('Unbalanced calendar components.');
            if ($parent === 'VEVENT') {
                $uid = trim((string) ($current['UID'] ?? ''));
                $start = parse_ics_date((string) ($current['DTSTART'] ?? ''), $current['DTSTART_TZID'] ?? null);
                $end = parse_ics_date((string) ($current['DTEND'] ?? ''), $current['DTEND_TZID'] ?? null);
                if ($uid === '' || strlen($uid) > 255 || isset($uids[$uid]) || !$start || !$end
                    || $end <= $start || $end->format('Y-m-d') <= $start->format('Y-m-d')) {
                    throw new RuntimeException('Invalid or duplicate calendar event. Existing reservations were retained.');
                }
                $current['UID'] = $uid;
                $uids[$uid] = true;
                $events[] = $current;
                $current = null;
            }
            array_pop($stack);
            if ($parent === 'VCALENDAR') $closed = true;
            continue;
        }
        if ($parent === null || $closed) throw new RuntimeException('Unexpected content outside calendar.');
        if ($parent === 'VCALENDAR') {
            if ($key === 'VERSION') {
                if ($version !== null || $value !== '2.0') throw new RuntimeException('Unsupported calendar version.');
                $version = $value;
            }
            // Scheduling messages are not full availability snapshots.
            if ($key === 'METHOD' && strtoupper($value) !== 'PUBLISH') throw new RuntimeException('Calendar is not a full published snapshot.');
        }
        if ($parent !== 'VEVENT') continue;
        if (in_array($key, ['RRULE', 'RDATE', 'EXDATE', 'RECURRENCE-ID'], true)) {
            throw new RuntimeException('Recurring events are not supported by this room-calendar importer.');
        }
        if (in_array($key, ['UID', 'DTSTART', 'DTEND', 'STATUS'], true) && array_key_exists($key, $current)) {
            throw new RuntimeException('Duplicate calendar event property.');
        }
        $current[$key] = $value;
        if (in_array($key, ['DTSTART', 'DTEND'], true)) {
            foreach ($parts as $parameter) {
                if (stripos($parameter, 'TZID=') === 0) $current[$key . '_TZID'] = trim(substr($parameter, 5), '"');
                if (stripos($parameter, 'VALUE=') === 0) {
                    $type = strtoupper(substr($parameter, 6));
                    if (($type === 'DATE' && !preg_match('/^\d{8}$/D', $value))
                        || ($type === 'DATE-TIME' && !preg_match('/^\d{8}T\d{6}Z?$/D', $value))
                        || !in_array($type, ['DATE', 'DATE-TIME'], true)) throw new RuntimeException('Invalid calendar date value.');
                }
            }
        }
    }
    if (!$opened || !$closed || $stack || $version !== '2.0') throw new RuntimeException('Incomplete calendar. Existing reservations were retained.');
    return $events;
}

function validate_ics_url(string $url): bool {
    $p = parse_url($url);
    return is_array($p) && ($p['scheme'] ?? '') === 'https' && in_array(strtolower($p['host'] ?? ''), ['admin.booking.com', 'ical.booking.com'], true) && !isset($p['user']) && !isset($p['pass']) && !isset($p['fragment']) && (!isset($p['port']) || $p['port'] === 443);
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
    if (!ics_master_sync_enabled($pdo)) {
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
        lock_booking_inventory($pdo);
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
            $start = parse_ics_date((string) $event['DTSTART'], $event['DTSTART_TZID'] ?? null);
            $end = parse_ics_date((string) $event['DTEND'], $event['DTEND_TZID'] ?? null);
            $uid = mb_substr(trim((string) $event['UID']), 0, 255);
            if (!$start || !$end || $end <= $start || $uid === '') {
                throw new RuntimeException('Invalid event; calendar update rolled back.');
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

        // Reconcile by UID, not second-resolution timestamps: two successful
        // syncs in one second must still release events absent from the new feed.
        $cancelSql = "UPDATE external_calendar_events SET is_active = 0, status = 'CANCELLED'
            WHERE room_id = ? AND provider = 'booking.com' AND is_active = 1";
        $cancelParams = [$roomId];
        if ($seen !== []) {
            $cancelSql .= ' AND external_uid NOT IN (' . implode(',', array_fill(0, count($seen), '?')) . ')';
            $cancelParams = array_merge($cancelParams, $seen);
        }
        $pdo->prepare($cancelSql)->execute($cancelParams);

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
