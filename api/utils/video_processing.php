<?php
declare(strict_types=1);

/** Video upload storage for hosts without FFmpeg/FFprobe. No transcoding occurs. */
class RoomVideoProcessingException extends RuntimeException
{
    public function userMessage(): string
    {
        $message = $this->getMessage();
        if (str_contains($message, '100 MB')) return 'Choose a video smaller than 100 MB.';
        if (str_contains($message, 'MP4 or WebM')) return 'Choose a valid MP4 or WebM video.';
        if (str_contains($message, 'Another video')) return 'Another video is being uploaded. Please try again.';
        return 'We could not upload this video. Please try again or contact support.';
    }
}

/**
 * Store a submitted MP4 or WebM without FFmpeg/FFprobe.
 * CAUTION: Container signature/MIME checks do not validate video codecs, duration,
 * resolution, full media integrity, or remove metadata. Pre-process trusted media
 * before upload, and do not rely on this for rigorous video-content validation.
 */
function optimize_uploaded_video(array $file, string $destinationBase): string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $source = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($source) || $size < 1 || $size > 104857600) {
        throw new RoomVideoProcessingException('Choose a valid video up to 100 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($source);
    $extensions = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    if (!is_string($mime) || !isset($extensions[$mime])) {
        throw new RoomVideoProcessingException('Only a valid MP4 or WebM video can be uploaded.');
    }

    $handle = @fopen($source, 'rb');
    if ($handle === false) throw new RoomVideoProcessingException('Unable to inspect the uploaded video.');
    try {
        $header = fread($handle, 64);
    } finally {
        fclose($handle);
    }
    if (!is_string($header)) throw new RoomVideoProcessingException('Invalid MP4 or WebM file.');

    if ($mime === 'video/mp4') {
        // ISO BMFF: first box must be ftyp; its size must fit inside the upload.
        $boxLength = strlen($header) >= 4 ? unpack('N', substr($header, 0, 4))[1] : 0;
        $valid = strlen($header) >= 12 && $boxLength >= 16 && $boxLength <= $size
            && substr($header, 4, 4) === 'ftyp';
    } else {
        // Matroska/WebM EBML signature, followed by an EBML document type.
        $valid = strlen($header) >= 4 && substr($header, 0, 4) === "\x1A\x45\xDF\xA3";
    }
    if (!$valid) throw new RoomVideoProcessingException('Invalid MP4 or WebM container.');

    $directory = dirname($destinationBase);
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RoomVideoProcessingException('Video storage directory is unavailable.');
    }
    // Never trust a client-provided filename, and never save executable extensions.
    $destination = $destinationBase . '.' . $extensions[$mime];
    if (file_exists($destination)) throw new RoomVideoProcessingException('Video destination already exists.');
    if (!move_uploaded_file($source, $destination)) {
        throw new RoomVideoProcessingException('Could not save uploaded video.');
    }
    @chmod($destination, 0644);
    clearstatcache(true, $destination);
    if (!is_file($destination) || filesize($destination) !== $size) {
        @unlink($destination);
        throw new RoomVideoProcessingException('Video upload was incomplete.');
    }
    return $destination;
}
