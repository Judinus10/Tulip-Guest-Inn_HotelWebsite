<?php
declare(strict_types=1);
require_once __DIR__.'/../helpers.php';
require_once __DIR__.'/hero_helpers.php';
apply_cors_headers();
require_admin_auth();
if($_SERVER['REQUEST_METHOD']!=='POST') json_response(false,'Method not allowed.',405);
try {
 $file=$_FILES['file']??null;
 $kind=$_POST['kind']??'image';
 if(!is_array($file)||($file['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Select a valid file.');
 if(!in_array($kind,['image','video'],true)) throw new InvalidArgumentException('Invalid media type.');
 $max=$kind==='video'?52428800:12582912;
 if($file['size']>$max) throw new InvalidArgumentException('File exceeds the upload limit.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
 $types=$kind==='video'?['video/mp4'=>'mp4','video/webm'=>'webm']:['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
 if(!isset($types[$mime])) throw new InvalidArgumentException('Unsupported media format.');
 $dir=__DIR__.'/../uploads/hero';
 if(!is_dir($dir)&&!mkdir($dir,0755,true)) throw new RuntimeException('Upload directory unavailable.');
 if($kind==='image') {
  $result=secure_image_upload($file,$dir,'hero');
  $filename=basename(is_array($result)?($result['filename']??''):(string)$result);
  if(!$filename) throw new RuntimeException('Image processing failed.');
 } else {
  $filename=bin2hex(random_bytes(16)).'.'.$types[$mime];
  if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$filename)) throw new RuntimeException('Video upload failed.');
 }
 json_response(true,'Uploaded.',200,['data'=>['url'=>public_upload_url($filename,'hero')]]);
} catch(InvalidArgumentException $e){json_response(false,$e->getMessage(),422);
} catch(Throwable $e){error_log('Hero upload: '.$e->getMessage());json_response(false,'Upload failed.',500);}
