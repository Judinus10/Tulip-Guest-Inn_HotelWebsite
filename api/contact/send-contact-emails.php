<?php
declare(strict_types=1);

// Backward-compatible URL. The real worker now lives in api/cron/send-email-queue.php.
require_once __DIR__ . '/../cron/send-email-queue.php';
