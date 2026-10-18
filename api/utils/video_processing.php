<?php
declare(strict_types=1);
/** Run media tools without a shell, under OS and wall-clock resource limits. */
function run_media_process(array $command, int $seconds): string {
    if (!function_exists('proc_open')) throw new RuntimeException('Secure media processing is unavailable.');
    $limiter = (string)jebal_env_value('PRLIMIT_BINARY', '/usr/bin/prlimit');
    if (!is_executable($limiter)) throw new RuntimeException('Secure media resource limiter is unavailable.');
    $command = array_merge([$limiter, '--cpu=90', '--as=2147483648', '--fsize=53477376', '--'], $command);
    $pipes = []; $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Unable to start media processing.');
    fclose($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
    $start = microtime(true); $output = ''; $error = ''; $exit = -1;
    try {
        while (true) {
            $output .= stream_get_contents($pipes[1]); $error .= stream_get_contents($pipes[2]);
            $state = proc_get_status($process);
            if (!$state['running']) { $exit = $state['exitcode']; break; }
            if (microtime(true)-$start > $seconds || strlen($output)+strlen($error) > 1048576) { proc_terminate($process, 9); throw new RuntimeException('Media processing limit exceeded.'); }
            usleep(20000);
        }
        $output .= stream_get_contents($pipes[1]);
    } finally { fclose($pipes[1]); fclose($pipes[2]); proc_close($process); }
    if ($exit !== 0) throw new RuntimeException('Invalid video or media processing failed.');
    return $output;
}
function optimize_uploaded_video(array $file, string $destinationBase): string {
    $size = (int)($file['size'] ?? 0); $source = (string)($file['tmp_name'] ?? '');
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($source) || $size < 1 || $size > 104857600) throw new RuntimeException('Choose a valid video up to 100 MB.');
    if (!in_array((new finfo(FILEINFO_MIME_TYPE))->file($source), ['video/mp4','video/webm'], true)) throw new RuntimeException('Only MP4 and WebM are supported.');
    check_media_budget(53477376);
    // One encoder per host; fail promptly instead of accumulating PHP workers.
    $lock = fopen(sys_get_temp_dir().'/tulip-media-'.hash('sha256', __DIR__).'.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX|LOCK_NB)) { if ($lock) fclose($lock); throw new RuntimeException('Another video is processing. Please retry later.'); }
    $output = $destinationBase.'.mp4';
    try {
        $probe = (string)jebal_env_value('FFPROBE_BINARY', 'ffprobe');
        $info = json_decode(run_media_process([$probe,'-v','error','-show_streams','-show_format','-of','json',$source],10),true,512,JSON_THROW_ON_ERROR);
        $videos = array_values(array_filter($info['streams'] ?? [], static fn($s) => ($s['codec_type'] ?? '') === 'video'));
        $duration = (float)($info['format']['duration'] ?? 0);
        if (count($videos)!==1 || $duration<=0 || $duration>120 || (int)($videos[0]['width']??0)*(int)($videos[0]['height']??0)>8294400) throw new RuntimeException('Video must be at most two minutes and 4K.');
        $ffmpeg=(string)jebal_env_value('FFMPEG_BINARY','ffmpeg');
        run_media_process([$ffmpeg,'-nostdin','-v','error','-threads','2','-filter_threads','1','-y','-i',$source,'-map','0:v:0','-an','-map_metadata','-1','-vf','scale=w=1920:h=1080:force_original_aspect_ratio=decrease:force_divisible_by=2','-c:v','libx264','-threads','2','-preset','veryfast','-crf','28','-pix_fmt','yuv420p','-movflags','+faststart',$output],100);
        clearstatcache(true,$output);
        if (!is_file($output) || filesize($output)<1 || filesize($output)>52428800) throw new RuntimeException('Processed video exceeds 50 MB.');
        return $output;
    } catch (Throwable $e) { if (is_file($output)) unlink($output); throw $e; }
    finally { flock($lock,LOCK_UN); fclose($lock); }
}
