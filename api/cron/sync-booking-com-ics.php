<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../calendar/run-all.php';
try {
    $results = ics_run_all(get_db_connection());
    foreach ($results as $result) {
        fwrite(STDOUT, sprintf("room=%d status=%s events=%d\n", (int)$result['room_id'], $result['success'] ? 'success' : 'failed', (int)($result['events'] ?? 0)));
    }
    exit(count(array_filter($results, static fn($r) => !$r['success'])) ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
