<?php
declare(strict_types=1);
require_once __DIR__.'/../helpers.php';
require_once __DIR__.'/policy_helpers.php';
apply_cors_headers();
if($_SERVER['REQUEST_METHOD']==='OPTIONS') exit;
try {
 $pdo=get_db_connection();
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $admin=($_GET['admin']??'')==='1';
  if($admin) require_admin_auth();
  json_response(true,'Policies loaded.',200,['data'=>policy_read($pdo,$admin)]);
 }
 require_admin_auth();
 if($_SERVER['REQUEST_METHOD']!=='POST') json_response(false,'Method not allowed.',405);
 $input=read_request_data();
 $action=$input['action']??'draft';
 policy_init($pdo);
 if($action==='draft') {
  $data=policy_normalize($input['policies']??null);
  $stmt=$pdo->prepare('UPDATE website_policies SET draft_json=? WHERE id=1');
  $stmt->execute([json_encode($data,JSON_UNESCAPED_UNICODE)]);
 } elseif($action==='publish') {
  $state=policy_read($pdo,true);
  $json=json_encode($state['draft'],JSON_UNESCAPED_UNICODE);
  $stmt=$pdo->prepare('UPDATE website_policies SET published_json=?,draft_json=? WHERE id=1');
  $stmt->execute([$json,$json]);
 } elseif($action==='discard') {
  $pdo->exec('UPDATE website_policies SET draft_json=published_json WHERE id=1');
 } else json_response(false,'Invalid action.',422);
 json_response(true,'Policies updated.',200,['data'=>policy_read($pdo,true)]);
} catch(InvalidArgumentException $e){json_response(false,$e->getMessage(),422);
} catch(Throwable $e){error_log('Policies error: '.$e->getMessage());json_response(false,'Unable to update policies.',500);}
