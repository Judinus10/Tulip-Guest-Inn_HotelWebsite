<?php
declare(strict_types=1);
require_once __DIR__.'/../helpers.php';
require_once __DIR__.'/email_branding_helpers.php';
apply_cors_headers();
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true))json_response(false,'Method not allowed.',405);
require_admin_auth();header('Cache-Control: no-store');
try {
    $pdo=get_db_connection();email_branding_init($pdo);
    $data=$_SERVER['REQUEST_METHOD']==='GET' ? email_branding_payload($pdo)
        : email_branding_save($pdo,$_POST,$_FILES['logo']??null);
    json_response(true,'Email branding loaded or saved.',200,['data'=>$data]);
} catch(InvalidArgumentException $e){json_response(false,$e->getMessage(),422);
} catch(Throwable $e){if($e->getCode()===409)json_response(false,$e->getMessage(),409);error_log('Email branding: '.$e->getMessage());json_response(false,'Could not process email branding. Please try again.',500);}
