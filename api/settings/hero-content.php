<?php
declare(strict_types=1);
require_once __DIR__.'/../helpers.php';
require_once __DIR__.'/hero_helpers.php';
apply_cors_headers();
try {
 $pdo=get_db_connection();
 if($_SERVER['REQUEST_METHOD']==='GET') {
  $admin=isset($_GET['admin']) && $_GET['admin']==='1';
  if($admin) require_admin_auth();
  json_response(true,'Hero loaded.',200,['data'=>hero_read($pdo,$admin)]);
 }
 require_admin_auth();
 if($_SERVER['REQUEST_METHOD']!=='POST') json_response(false,'Method not allowed.',405);
 $body=read_request_data();
 $action=$body['action']??'draft';
 hero_init($pdo);
 if($action==='draft') {
  $value=hero_validate($body['hero']??null);
  $stmt=$pdo->prepare('UPDATE website_hero SET draft_json=? WHERE id=1');
  $stmt->execute([json_encode($value,JSON_UNESCAPED_SLASHES)]);
 } elseif($action==='publish') {
  $state=hero_read($pdo,true);
  $value=hero_validate($state['draft']);
  $stmt=$pdo->prepare('UPDATE website_hero SET published_json=?, draft_json=? WHERE id=1');
  $json=json_encode($value,JSON_UNESCAPED_SLASHES);
  $stmt->execute([$json,$json]);
 } elseif($action==='discard') {
  $pdo->exec('UPDATE website_hero SET draft_json=published_json WHERE id=1');
 } else json_response(false,'Unknown action.',422);
 json_response(true,'Hero content saved.',200,['data'=>hero_read($pdo,true)]);
} catch(InvalidArgumentException $e) { json_response(false,$e->getMessage(),422);
} catch(Throwable $e) {error_log('Hero content error: '.$e->getMessage());json_response(false,'Unable to update hero.',500);}
