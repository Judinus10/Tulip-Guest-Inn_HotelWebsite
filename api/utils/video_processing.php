<?php
declare(strict_types=1);

class RoomVideoProcessingException extends RuntimeException {}

function media_binary(string $setting, string $program): string
{
    $configured = trim((string) jebal_env_value($setting, ''));
    $windows = PHP_OS_FAMILY === 'Windows';
    $name = $program . ($windows ? '.exe' : '');
    $candidates = $configured !== '' ? [$configured] : [];
    if ($configured === '' && $program === 'ffprobe') {
        $encoder = trim((string) jebal_env_value('FFMPEG_BINARY', ''));
        if ($encoder !== '' && dirname($encoder) !== '.') $candidates[] = dirname($encoder) . DIRECTORY_SEPARATOR . $name;
    }
    if ($configured === '' || basename($configured) === $configured) {
        $searchName = $configured !== '' ? $configured : $name;
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            $directory = trim($directory, " \t\n\r\0\x0B\"");
            if ($directory !== '') $candidates[] = $directory . DIRECTORY_SEPARATOR . $searchName;
        }
        if ($windows && $configured === '') {
            $candidates[] = dirname(PHP_BINARY, 2) . '/ffmpeg/bin/' . $name;
        }
    }
    foreach ($candidates as $candidate) {
        if (is_file($candidate) && ($windows || is_executable($candidate))) return $candidate;
    }
    throw new RoomVideoProcessingException($program . ' is unavailable. Install FFmpeg and set ' . $setting . ' to its full executable path in api/.env, then restart Apache.');
}

/** Use argument arrays (no shell) and bounded output files on Windows and Unix. */
function run_media_process(array $command, int $seconds): string
{
    if (!function_exists('proc_open')) throw new RoomVideoProcessingException('Video processing requires proc_open to be enabled in PHP.');
    if (PHP_OS_FAMILY === 'Linux') {
        $limiter = (string) jebal_env_value('PRLIMIT_BINARY', '/usr/bin/prlimit');
        if (!is_executable($limiter)) throw new RoomVideoProcessingException('Secure media resource limiter is unavailable.');
        $command = array_merge([$limiter, '--cpu=90', '--as=2147483648', '--fsize=53477376', '--'], $command);
    }
    $stdout = tempnam(sys_get_temp_dir(), 'tulip-media-out-');
    $stderr = tempnam(sys_get_temp_dir(), 'tulip-media-err-');
    if ($stdout === false || $stderr === false) {
        if ($stdout !== false) @unlink($stdout);
        if ($stderr !== false) @unlink($stderr);
        throw new RoomVideoProcessingException('Unable to create temporary video processing files.');
    }
    $process = null;
    $pipes = [];
    try {
        $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) throw new RoomVideoProcessingException('Unable to start FFmpeg. Check its executable path and permissions.');
        fclose($pipes[0]);
        $start = microtime(true);
        $exit = -1;
        while (true) {
            $state = proc_get_status($process);
            clearstatcache(true, $stdout);
            clearstatcache(true, $stderr);
            if ((int) filesize($stdout) + (int) filesize($stderr) > 1048576) {
                throw new RoomVideoProcessingException('Video processing output limit exceeded.');
            }
            if (!$state['running']) { $exit = (int) $state['exitcode']; break; }
            if (microtime(true) - $start > $seconds) throw new RoomVideoProcessingException('Video processing timed out. Use a smaller or shorter video.');
            usleep(20000);
        }
        if ($exit !== 0) {
            error_log('Video processing tool failed: ' . substr((string) file_get_contents($stderr), 0, 2000));
            throw new RoomVideoProcessingException('Unable to process this video. Use a valid MP4 or WebM video and check the server FFmpeg setup.');
        }
        return (string) file_get_contents($stdout);
    } finally {
        if (is_resource($process)) {
            $state = proc_get_status($process);
            if ($state['running']) proc_terminate($process, 9);
            proc_close($process);
        }
        @unlink($stdout);
        @unlink($stderr);
    }
}

function optimize_uploaded_video(array $file, string $destinationBase): string
{
    $size = (int) ($file['size'] ?? 0);
    $source = (string) ($file['tmp_name'] ?? '');
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($source) || $size < 1 || $size > 104857600) throw new RoomVideoProcessingException('Choose a valid video up to 100 MB.');
    if (!in_array((new finfo(FILEINFO_MIME_TYPE))->file($source), ['video/mp4', 'video/webm'], true)) throw new RoomVideoProcessingException('Only MP4 and WebM are supported.');
    // Resolve both tools before any room data is saved.
    $probe = media_binary('FFPROBE_BINARY', 'ffprobe');
    $ffmpeg = media_binary('FFMPEG_BINARY', 'ffmpeg');
    check_media_budget(53477376);
    if (function_exists('set_time_limit')) @set_time_limit(130);
    $lock = fopen(sys_get_temp_dir() . '/tulip-media-' . hash('sha256', __DIR__) . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { if ($lock) fclose($lock); throw new RoomVideoProcessingException('Another video is processing. Please retry later.'); }
    $output = $destinationBase . '.mp4';
    try {
        $info = json_decode(run_media_process([$probe, '-v', 'error', '-max_alloc', '268435456', '-show_streams', '-show_format', '-of', 'json', $source], 10), true, 512, JSON_THROW_ON_ERROR);
        $videos = array_values(array_filter($info['streams'] ?? [], static fn($stream) => ($stream['codec_type'] ?? '') === 'video'));
        $duration = (float) ($info['format']['duration'] ?? 0);
        if (count($videos) !== 1 || $duration <= 0 || $duration > 120 || (int) ($videos[0]['width'] ?? 0) * (int) ($videos[0]['height'] ?? 0) > 8294400) throw new RoomVideoProcessingException('Video must be at most two minutes and 4K.');
        run_media_process([$ffmpeg, '-nostdin', '-v', 'error', '-max_alloc', '268435456', '-threads', '2', '-filter_threads', '1', '-y', '-i', $source, '-map', '0:v:0', '-an', '-map_metadata', '-1', '-vf', 'scale=w=1920:h=1080:force_original_aspect_ratio=decrease:force_divisible_by=2', '-c:v', 'libx264', '-threads', '2', '-preset', 'veryfast', '-crf', '28', '-pix_fmt', 'yuv420p', '-fs', '52428800', '-movflags', '+faststart', $output], 100);
        clearstatcache(true, $output);
        if (!is_file($output) || filesize($output) < 1 || filesize($output) > 52428800) throw new RoomVideoProcessingException('Processed video exceeds 50 MB.');
        // A size-limited encoder may exit successfully after truncating the video.
        $result = json_decode(run_media_process([$probe, '-v', 'error', '-show_entries', 'format=duration', '-of', 'json', $output], 10), true, 512, JSON_THROW_ON_ERROR);
        if ((float) ($result['format']['duration'] ?? 0) < $duration - 1) throw new RoomVideoProcessingException('Video exceeds the processed size limit. Use a smaller video.');
        return $output;
    } catch (Throwable $e) {
        if (is_file($output)) @unlink($output);
        throw $e;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
