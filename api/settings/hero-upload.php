<?php
declare(strict_types=1);
require_once __DIR__.'/../helpers.php';
require_once __DIR__.'/hero_helpers.php';
require_once __DIR__.'/../utils/video_processing.php';
apply_cors_headers();
require_admin_auth();
if($_SERVER['REQUEST_METHOD']!=='POST') json_response(false,'Method not allowed.',405);
try {
 $file=$_FILES['file']??null;
 $kind=$_POST['kind']??'image';
 if(!is_array($file)) throw new InvalidArgumentException('Select a valid file.');
 if(($file['error']??1)!==UPLOAD_ERR_OK) throw new RuntimeException('Video upload failed. The server may limit upload size. Compress the video to 50 MB or less and try again.');
 if(!is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Select a valid file.');
 if(!in_array($kind,['image','video'],true)) throw new InvalidArgumentException('Invalid media type.');
 $max=$kind==='video'?104857600:12582912;
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
  $stored=optimize_uploaded_video($file,$dir.'/'.bin2hex(random_bytes(16)));
  $filename=basename($stored);
 }
 json_response(true,'Uploaded.',200,['data'=>['url'=>public_upload_url($filename,'hero')]]);
} catch(InvalidArgumentException $e){json_response(false,$e->getMessage(),422);
} catch(RuntimeException $e){error_log('Hero upload: '.$e->getMessage());json_response(false,$e->getMessage(),422);
} catch(Throwable $e){error_log('Hero upload: '.$e->getMessage());json_response(false,'Upload failed. Compress the video to 50 MB or less and try again.',500);}
