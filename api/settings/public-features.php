<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../calendar/ics-helper.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Only GET requests are allowed.', 405);
}

json_response(true, 'Public feature settings loaded.', 200, [
    'data' => [
        'online_payment_enabled' => ONLINE_PAYMENT_ENABLED,
        'calendar_sync_enabled' => ics_enabled(),
    ],
]);
