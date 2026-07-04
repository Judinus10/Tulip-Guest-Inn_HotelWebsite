<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Keep MySQL NOW() / timestamp comparisons aligned with PHP DateTime.
    // Asia/Colombo is UTC+05:30 and does not use daylight saving time.
    $pdo->exec("SET time_zone = '+05:30'");

    return $pdo;
}
