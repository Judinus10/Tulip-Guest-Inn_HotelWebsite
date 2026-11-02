<?php
declare(strict_types=1);

require_once __DIR__ . '/../payments/booking-payment-ledger.php';
require_once __DIR__ . '/booking-audit-helper.php';
require_once __DIR__ . '/booking-expiry-helper.php';

const TULIP_BOOKING_BACKUP_LIMIT = 10;
const TULIP_BOOKING_BACKUP_LOCK = 'tulip_booking_data_backup_v1';

function booking_backup_directory(): string
{
    // api/ sits below the public document root; this directory is its private sibling.
    return dirname(__DIR__, 3) . '/private/booking-data-backups';
}

function booking_backup_prepare(PDO $pdo): string
{
    ensure_booking_payment_ledger($pdo);
    ensure_booking_audit_table($pdo);
    ensure_booking_expiry_events_table($pdo);
    $directory = booking_backup_directory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Private booking backup directory could not be created.');
    }
    @chmod($directory, 0700);
    if (!is_writable($directory)) throw new RuntimeException('Private booking backup directory is not writable by PHP.');
    return $directory;
}

function booking_backup_acquire(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $stmt->execute([TULIP_BOOKING_BACKUP_LOCK]);
    if ((int) $stmt->fetchColumn() !== 1) throw new RuntimeException('A booking backup or restore is already running. Try again shortly.');
}

function booking_backup_release(PDO $pdo): void
{
    try { $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([TULIP_BOOKING_BACKUP_LOCK]); }
    catch (Throwable $e) { error_log('Booking backup lock release failed: ' . $e->getMessage()); }
}

function booking_backup_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Only booking records and their directly related business records are included. */
function booking_backup_table_rules(): array
{
    return [
        'booking_groups' => null,
        'bookings' => null,
        'payments' => 'booking_id IS NOT NULL',
        'booking_payment_entries' => null,
        'invoices' => null,
        'booking_audit_logs' => null,
        'booking_expiry_events' => null,
        'email_logs' => 'booking_id IS NOT NULL',
    ];
}

function booking_backup_capture(PDO $pdo, string $kind): array
{
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) $pdo->beginTransaction();
    try {
    $tables = [];
    $bookingCount = 0;
    foreach (booking_backup_table_rules() as $table => $where) {
        if (!booking_backup_table_exists($pdo, $table)) continue;
        $columns = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
        $query = 'SELECT * FROM `' . $table . '`' . ($where ? ' WHERE ' . $where : '') . ' ORDER BY 1';
        $rows = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        $tables[$table] = ['columns' => $columns, 'rows' => $rows];
        if ($table === 'bookings') $bookingCount = count($rows);
    }
    if (!isset($tables['bookings'])) throw new RuntimeException('The bookings table is unavailable.');
    if ($startedTransaction) $pdo->commit();
    return [
        'format_version' => 1,
        'kind' => $kind,
        'created_at' => date(DATE_ATOM),
        'booking_count' => $bookingCount,
        'tables' => $tables,
    ];
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function booking_backup_filename(): string
{
    return 'booking-data-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json.gz';
}

function booking_backup_prune(string $directory): array
{
    $files = glob($directory . '/booking-data-????????-??????-????????.json.gz') ?: [];
    sort($files, SORT_STRING);
    $removed = [];
    while (count($files) > TULIP_BOOKING_BACKUP_LIMIT) {
        $oldest = $files[0];
        if (!unlink($oldest)) throw new RuntimeException('The oldest booking backup could not be removed to enforce the 10-backup limit.');
        $removed[] = basename($oldest);
        array_splice($files, 0, 1);
    }
    return $removed;
}

function booking_backup_write(PDO $pdo, string $kind = 'scheduled'): array
{
    $directory = booking_backup_prepare($pdo);
    $snapshot = booking_backup_capture($pdo, $kind);
    $json = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $compressed = gzencode($json, 6);
    if (!is_string($compressed)) throw new RuntimeException('Booking data compression failed.');
    $filename = booking_backup_filename();
    $target = $directory . '/' . $filename;
    $temp = $target . '.tmp';
    if (file_put_contents($temp, $compressed, LOCK_EX) !== strlen($compressed)) {
        @unlink($temp);
        throw new RuntimeException('Booking backup could not be written completely.');
    }
    @chmod($temp, 0600);
    if (!rename($temp, $target)) {
        @unlink($temp);
        throw new RuntimeException('Booking backup could not be finalized.');
    }
    @chmod($target, 0600);
    booking_backup_prune($directory);
    return [
        'id' => $filename,
        'created_at' => $snapshot['created_at'],
        'booking_count' => $snapshot['booking_count'],
        'size_bytes' => strlen($compressed),
        'kind' => $kind,
    ];
}

function booking_backup_read(string $id): array
{
    if (!preg_match('/^booking-data-\d{8}-\d{6}-[a-f0-9]{8}\.json\.gz$/D', $id)) {
        throw new InvalidArgumentException('Choose a valid backup.');
    }
    $path = booking_backup_directory() . '/' . $id;
    if (!is_file($path) || filesize($path) > 100 * 1024 * 1024) throw new RuntimeException('The selected backup is missing or too large to restore.');
    $compressed = file_get_contents($path);
    $json = is_string($compressed) ? gzdecode($compressed, 512 * 1024 * 1024) : false;
    if (!is_string($json)) throw new RuntimeException('The selected backup is damaged.');
    $snapshot = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($snapshot) || (int) ($snapshot['format_version'] ?? 0) !== 1 || !is_array($snapshot['tables'] ?? null) || !isset($snapshot['tables']['bookings']['rows'])) {
        throw new RuntimeException('The selected backup format is not supported.');
    }
    return $snapshot;
}

function booking_backup_list(PDO $pdo): array
{
    $directory = booking_backup_prepare($pdo);
    $files = glob($directory . '/booking-data-????????-??????-????????.json.gz') ?: [];
    sort($files, SORT_STRING);
    $files = array_slice(array_reverse($files), 0, TULIP_BOOKING_BACKUP_LIMIT);
    $items = [];
    foreach ($files as $path) {
        try {
            $snapshot = booking_backup_read(basename($path));
            $items[] = [
                'id' => basename($path),
                'created_at' => (string) ($snapshot['created_at'] ?? ''),
                'booking_count' => (int) ($snapshot['booking_count'] ?? count($snapshot['tables']['bookings']['rows'] ?? [])),
                'size_bytes' => (int) filesize($path),
                'kind' => in_array(($snapshot['kind'] ?? ''), ['scheduled', 'pre_restore'], true) ? $snapshot['kind'] : 'scheduled',
                'valid' => true,
            ];
        } catch (Throwable $e) {
            $items[] = ['id' => basename($path), 'created_at' => '', 'booking_count' => 0, 'size_bytes' => (int) filesize($path), 'kind' => 'unknown', 'valid' => false];
        }
    }
    return $items;
}

function booking_backup_current_columns(PDO $pdo, string $table): array
{
    return $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
}

function booking_backup_insert_rows(PDO $pdo, string $table, array $rows, array $currentColumns, array $paymentIdMap = []): array
{
    if (!$rows) return [];
    $allowed = array_flip($currentColumns);
    $newPaymentIds = [];
    foreach ($rows as $row) {
        if (!is_array($row)) throw new RuntimeException('A backup row is malformed.');
        $filtered = array_intersect_key($row, $allowed);
        $originalId = isset($filtered['id']) ? (int) $filtered['id'] : 0;
        if (in_array($table, ['payments', 'email_logs'], true)) unset($filtered['id']);
        if ($table === 'booking_audit_logs' && !empty($filtered['payment_id'])) {
            $filtered['payment_id'] = $paymentIdMap[(int) $filtered['payment_id']] ?? null;
        }
        if ($table === 'bookings' && array_key_exists('room_id', $filtered) && $filtered['room_id'] !== null) {
            $roomExists = $pdo->prepare('SELECT id FROM rooms WHERE id = ? LIMIT 1');
            $roomExists->execute([(int) $filtered['room_id']]);
            if (!$roomExists->fetchColumn()) $filtered['room_id'] = null;
        }
        if ($table === 'bookings' && array_key_exists('invoice_id', $filtered)) $filtered['invoice_id'] = null;
        if ($table === 'payments' && !empty($filtered['invoice_id'])) {
            $invoiceExists = $pdo->prepare('SELECT id FROM invoices WHERE id = ? LIMIT 1');
            $invoiceExists->execute([(int) $filtered['invoice_id']]);
            if (!$invoiceExists->fetchColumn()) $filtered['invoice_id'] = null;
        }
        if ($table === 'email_logs' && !empty($filtered['enquiry_id'])) {
            $enquiryExists = $pdo->prepare('SELECT id FROM enquiries WHERE id = ? LIMIT 1');
            $enquiryExists->execute([(int) $filtered['enquiry_id']]);
            if (!$enquiryExists->fetchColumn()) $filtered['enquiry_id'] = null;
        }
        if (!$filtered) continue;
        $columns = array_keys($filtered);
        $quoted = implode(',', array_map(static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns));
        $marks = implode(',', array_fill(0, count($columns), '?'));
        $stmt = $pdo->prepare('INSERT INTO `' . $table . '` (' . $quoted . ') VALUES (' . $marks . ')');
        $stmt->execute(array_values($filtered));
        if ($table === 'payments' && $originalId > 0) $newPaymentIds[$originalId] = (int) $pdo->lastInsertId();
    }
    return $newPaymentIds;
}

function booking_backup_restore(PDO $pdo, string $id): array
{
    $snapshot = booking_backup_read($id);
    booking_backup_prepare($pdo);
    $tables = $snapshot['tables'];
    $rules = booking_backup_table_rules();
    foreach (array_keys($tables) as $table) {
        if (!isset($rules[$table]) || !booking_backup_table_exists($pdo, $table)) throw new RuntimeException('Backup schema does not match this installation. No booking data was changed.');
        if (!is_array($tables[$table]['rows'] ?? null)) throw new RuntimeException('Backup data is incomplete. No booking data was changed.');
    }
    $safety = booking_backup_write($pdo, 'pre_restore');

    $pdo->beginTransaction();
    try {
        // Remove only booking-linked rows; enquiry email history and unrelated records remain untouched.
        foreach (['booking_audit_logs', 'booking_expiry_events', 'booking_payment_entries'] as $table) {
            if (booking_backup_table_exists($pdo, $table)) $pdo->exec('DELETE FROM `' . $table . '`');
        }
        if (booking_backup_table_exists($pdo, 'email_logs')) $pdo->exec('DELETE FROM email_logs WHERE booking_id IS NOT NULL');
        if (booking_backup_table_exists($pdo, 'payments')) $pdo->exec('DELETE FROM payments WHERE booking_id IS NOT NULL');
        if (booking_backup_table_exists($pdo, 'email_queue')) {
            $pdo->exec("UPDATE email_queue SET status = 'failed', locked_at = NULL, last_error = 'Cancelled during booking data restore.' WHERE related_type = 'booking' AND status IN ('pending', 'processing')");
        }
        if (booking_backup_table_exists($pdo, 'invoices')) $pdo->exec('DELETE FROM invoices');
        if (in_array('invoice_id', booking_backup_current_columns($pdo, 'bookings'), true)) $pdo->exec('UPDATE bookings SET invoice_id = NULL');
        $pdo->exec('DELETE FROM bookings');
        if (booking_backup_table_exists($pdo, 'booking_groups')) $pdo->exec('DELETE FROM booking_groups');

        $restoreOrder = ['booking_groups', 'bookings', 'invoices', 'payments', 'booking_payment_entries', 'booking_audit_logs', 'booking_expiry_events', 'email_logs'];
        $currentColumns = [];
        $paymentIdMap = [];
        foreach ($restoreOrder as $table) {
            if (!isset($tables[$table])) continue;
            $currentColumns[$table] = booking_backup_current_columns($pdo, $table);
            $insertedMap = booking_backup_insert_rows($pdo, $table, $tables[$table]['rows'], $currentColumns[$table], $paymentIdMap);
            if ($table === 'payments') $paymentIdMap = $insertedMap;
        }

        if (isset($tables['bookings']) && in_array('invoice_id', $currentColumns['bookings'] ?? [], true)) {
            $update = $pdo->prepare('UPDATE bookings SET invoice_id = ? WHERE id = ?');
            foreach ($tables['bookings']['rows'] as $row) {
                if (isset($row['invoice_id']) && $row['invoice_id'] !== null) {
                    $invoiceExists = $pdo->prepare('SELECT id FROM invoices WHERE id = ? LIMIT 1');
                    $invoiceExists->execute([(int) $row['invoice_id']]);
                    if ($invoiceExists->fetchColumn()) $update->execute([(int) $row['invoice_id'], (int) $row['id']]);
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return ['booking_count' => (int) ($snapshot['booking_count'] ?? count($tables['bookings']['rows'])), 'safety_backup' => $safety['id']];
}
