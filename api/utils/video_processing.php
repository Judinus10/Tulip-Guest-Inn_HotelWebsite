<?php
declare(strict_types=1);
/** Shared best-effort video optimization. Never silently publishes a large unoptimized video. */
function optimize_uploaded_video(array $file, string $destinationBase): string {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('Video upload failed. The server may limit upload size. Compress your video to 50 MB or less and try again.');
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > 100 * 1024 * 1024) throw new RuntimeException('Choose a video up to 100 MB. Compress larger videos and try again.');
    $source = (string)($file['tmp_name'] ?? '');
    if (!is_uploaded_file($source)) throw new RuntimeException('Invalid video upload.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($source);
    $extensions = ['video/mp4'=>'mp4','video/webm'=>'webm'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Only MP4 and WebM videos are supported.');
    $originalPath = $destinationBase . '.' . $extensions[$mime];
    // Small videos need no re-encoding; avoid needless quality loss.
    if ($size <= 50 * 1024 * 1024) {
        if (!move_uploaded_file($source, $originalPath)) throw new RuntimeException('Unable to store video.');
        return $originalPath;
    }
    // Large videos require FFmpeg and a host that allows process execution.
    if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
        throw new RuntimeException('Automatic video compression is unavailable on this server. Please compress your video to 50 MB or less and try again.');
    }
    $ffmpeg = trim((string) (function_exists('jebal_env_value')
        ? jebal_env_value('FFMPEG_BINARY', 'ffmpeg')
        : (getenv('FFMPEG_BINARY') ?: 'ffmpeg')));
    if ($ffmpeg === '' || preg_match('/[\r\n]/', $ffmpeg)) throw new RuntimeException('Video compression is not configured. Compress the video to 50 MB or less and try again.');
    $output = $destinationBase . '.mp4';
    $command = escapeshellarg($ffmpeg).' -nostdin -hide_banner -loglevel error -y -i '.escapeshellarg($source)
        .' -map 0:v:0 -an -vf '.escapeshellarg('scale=w=1920:h=1080:force_original_aspect_ratio=decrease:force_divisible_by=2')
        .' -c:v libx264 -preset veryfast -crf 28 -pix_fmt yuv420p -movflags +faststart '.escapeshellarg($output).' 2>&1';
    $log=[];$status=1;
    @exec($command, $log, $status);
    clearstatcache(true, $output);
    if ($status !== 0 || !is_file($output) || filesize($output) < 1 || filesize($output) > 100 * 1024 * 1024) {
        @unlink($output);
        throw new RuntimeException('Video compression failed or exceeded 100 MB. Please compress your video to 50 MB or less and try again.');
    }
    return $output;
}
