<?php
declare(strict_types=1);
function email_branding_default_path(): string { return dirname(__DIR__) . '/invoices/assets/tulip-logo-full.jpg'; }
function email_branding_logo_path(string $name): string
{
    if ($name === '') return email_branding_default_path();
    if (!preg_match('/^[a-f0-9]{32}\.(png|jpg)$/D', $name)) throw new RuntimeException('Invalid email logo reference.');
    return dirname(__DIR__) . '/uploads/email-branding/' . $name;
}
function email_branding_read(PDO $pdo): array
{
    try { $row = $pdo->query('SELECT logo_file, logo_width, version FROM website_email_branding WHERE id=1')->fetch(PDO::FETCH_ASSOC); }
    catch (PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1146) throw $e; $row=false; }
    $row = $row ?: ['logo_file'=>'','logo_width'=>76,'version'=>0];
    $path=email_branding_logo_path((string)$row['logo_file']);
    if ($row['logo_file']!=='' && !is_file($path)) throw new RuntimeException('The saved email logo is missing.');
    return ['logo_file'=>(string)$row['logo_file'],'width'=>max(40,min(240,(int)$row['logo_width'])),'version'=>(int)$row['version']];
}
function email_branding_init(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS website_email_branding (id TINYINT UNSIGNED PRIMARY KEY, logo_file VARCHAR(40) NOT NULL DEFAULT '', logo_width SMALLINT UNSIGNED NOT NULL DEFAULT 76, version INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec('INSERT IGNORE INTO website_email_branding(id) VALUES(1)');
}
function email_branding_data_uri(string $path): string
{
    if (!is_file($path)) return '';
    $mime = str_ends_with($path,'.png') ? 'image/png' : 'image/jpeg';
    return 'data:'.$mime.';base64,'.base64_encode((string)file_get_contents($path));
}
function email_branding_payload(PDO $pdo): array
{
    $settings=email_branding_read($pdo);
    return ['width'=>$settings['width'],'version'=>$settings['version'],
        'logo'=>email_branding_data_uri(email_branding_logo_path($settings['logo_file'])),
        'default_logo'=>email_branding_data_uri(email_branding_default_path()),'default_width'=>76];
}
function email_branding_validate_image(string $path): string
{
    if (!is_file($path) || filesize($path)<1 || filesize($path)>2097152) throw new InvalidArgumentException('Choose a PNG or JPEG logo up to 2 MB.');
    $info=@getimagesize($path);
    if (!$info || $info[0]<1 || $info[1]<1 || $info[0]>2000 || $info[1]>2000) throw new InvalidArgumentException('Logo dimensions must be at most 2000 × 2000 pixels.');
    if ($info[0]/$info[1] < 0.25 || $info[0]/$info[1] > 8) throw new InvalidArgumentException('This image is too tall or too wide for an email logo.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
    if ($mime==='image/png' && $info[2]===IMAGETYPE_PNG) return 'png';
    if ($mime==='image/jpeg' && $info[2]===IMAGETYPE_JPEG) return 'jpg';
    throw new InvalidArgumentException('Use a PNG or JPEG image. SVG and other formats are not supported.');
}
function email_branding_save(PDO $pdo, array $input, ?array $file): array
{
    $width=filter_var($input['width']??null,FILTER_VALIDATE_INT);
    $version=filter_var($input['version']??null,FILTER_VALIDATE_INT);
    if ($width===false || $width<40 || $width>240) throw new InvalidArgumentException('Logo width must be between 40 and 240 pixels.');
    if ($version===false || $version<0 || $version>2147483646) throw new InvalidArgumentException('Reload email branding before saving.');
    $reset=($input['reset']??'0')==='1';$storedFile=null;
    if ($reset && $file && ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) throw new InvalidArgumentException('Choose either a replacement logo or reset.');
    if ($file && ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
        if (($file['error']??-1)!==UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name']??''))) throw new InvalidArgumentException('Logo upload failed. Try a PNG or JPEG under 2 MB.');
        $ext=email_branding_validate_image($file['tmp_name']);
        $name=bin2hex(random_bytes(16)).'.'.$ext;$storedFile=email_branding_logo_path($name);
        $dir=dirname($storedFile);
        if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('Email logo storage is unavailable.');
        if (!move_uploaded_file($file['tmp_name'],$storedFile)) throw new RuntimeException('Could not store the email logo.');
    }
    try {
        $pdo->beginTransaction();
        $row=$pdo->query('SELECT logo_file,version FROM website_email_branding WHERE id=1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)$row['version']!==$version) throw new RuntimeException('Email branding changed in another session. Reload before saving.',409);
        $name=$reset ? '' : ($storedFile ? basename($storedFile) : $row['logo_file']);
        $stmt=$pdo->prepare('UPDATE website_email_branding SET logo_file=?,logo_width=?,version=version+1 WHERE id=1');
        $stmt->execute([$name,$width]);$pdo->commit();
    } catch (Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();if($storedFile && is_file($storedFile))unlink($storedFile);throw $e; }
    // Keep prior images: emails already queued refer to their exact original file.
    return email_branding_payload($pdo);
}
