<?php
declare(strict_types=1);

// Stream room videos with byte-range support. This allows mobile browsers to
// request the MP4 index near the end of a file without downloading it all.
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

$filename = (string) ($_GET['file'] ?? '');
if ($filename === '' || basename($filename) !== $filename
    || !preg_match('/^room-video-[A-Za-z0-9-]+\.(mp4|webm)$/i', $filename)) {
    http_response_code(404);
    exit;
}

$directory = realpath(__DIR__ . '/../uploads/rooms');
$path = $directory !== false ? realpath($directory . DIRECTORY_SEPARATOR . $filename) : false;
if ($directory === false || $path === false || !str_starts_with($path, $directory . DIRECTORY_SEPARATOR)
    || !is_file($path) || is_link($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

$size = filesize($path);
if ($size === false || $size < 1) {
    http_response_code(404);
    exit;
}

$mime = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'webm' ? 'video/webm' : 'video/mp4';
$start = 0;
$end = $size - 1;
$status = 200;
$rangeHeader = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));

if ($rangeHeader !== '') {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', $rangeHeader, $matches)
        || ($matches[1] === '' && $matches[2] === '')) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }

    if ($matches[1] === '') {
        $suffixLength = (int) $matches[2];
        if ($suffixLength < 1) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416);
            exit;
        }
        $start = max(0, $size - $suffixLength);
    } else {
        $start = (int) $matches[1];
        $end = $matches[2] === '' ? $size - 1 : min((int) $matches[2], $size - 1);
    }

    if ($start >= $size || $end < $start) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }
    $status = 206;
}

$length = $end - $start + 1;
http_response_code($status);
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Accept-Ranges: bytes');
header('Content-Length: ' . $length);
header('Cache-Control: public, max-age=86400, immutable');
header('X-Content-Type-Options: nosniff');
if ($status === 206) header("Content-Range: bytes {$start}-{$end}/{$size}");

if ($method === 'HEAD') exit;

set_time_limit(0);
$handle = fopen($path, 'rb');
if ($handle === false || fseek($handle, $start) !== 0) {
    if (is_resource($handle)) fclose($handle);
    exit;
}

while ($length > 0 && !feof($handle) && !connection_aborted()) {
    $chunk = fread($handle, min(1024 * 1024, $length));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    $length -= strlen($chunk);
    flush();
}
fclose($handle);
