<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mail/email-helper.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    apply_cors_headers();

    $configuredToken = '';
    if (function_exists('jebal_env_value')) {
        $configuredToken = trim((string) jebal_env_value('EMAIL_QUEUE_CRON_TOKEN', ''));
    }

    // Optional protection for production: add EMAIL_QUEUE_CRON_TOKEN=your-secret in api/.env
    // Then call: /api/cron/send-email-queue.php?token=your-secret
    if ($configuredToken !== '') {
        $requestToken = trim((string) ($_GET['token'] ?? ''));
        if (!hash_equals($configuredToken, $requestToken)) {
            json_response(false, 'Unauthorized cron request.', 401);
        }
    }
}

try {
    $pdo = get_db_connection();
    ensure_email_queue_table($pdo);

    $limit = 10;
    if ($isCli) {
        $argLimit = $argv[1] ?? null;
        if ($argLimit !== null && ctype_digit((string) $argLimit)) {
            $limit = (int) $argLimit;
        }
    } elseif (isset($_GET['limit']) && ctype_digit((string) $_GET['limit'])) {
        $limit = (int) $_GET['limit'];
    }

    $result = process_email_queue($pdo, $limit);

    if ($isCli) {
        echo json_encode([
            'success' => true,
            'message' => 'Email queue worker completed.',
            'data' => $result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    json_response(true, 'Email queue worker completed.', 200, ['data' => $result]);
} catch (Throwable $e) {
    error_log('Email queue worker error: ' . $e->getMessage());

    if ($isCli) {
        fwrite(STDERR, 'Email queue worker error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    json_response(false, 'Email queue worker failed.', 500, [
        'error' => defined('APP_ENV') && APP_ENV === 'production' ? null : $e->getMessage(),
    ]);
}
