<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

apply_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Only GET requests are allowed.', 405);
}

$user = require_admin_auth();

json_response(true, 'Session is valid.', 200, [
    'data' => [
        'user' => $user,
    ],
]);