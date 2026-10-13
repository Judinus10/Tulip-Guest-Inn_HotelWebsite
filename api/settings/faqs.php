<?php
declare(strict_types=1);
require_once __DIR__.'/../helpers.php';
require_once __DIR__.'/faq_helpers.php';
apply_cors_headers();
if($_SERVER['REQUEST_METHOD']==='OPTIONS') exit;
try{
 $pdo=get_db_connection();
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $admin=($_GET['admin']??'')==='1';
  if($admin) require_admin_auth();
  json_response(true,'FAQs loaded.',200,['data'=>faq_read($pdo,$admin)]);
 }
 require_admin_auth();
 if($_SERVER['REQUEST_METHOD']!=='POST') json_response(false,'Method not allowed.',405);
 $input=read_request_data();$action=$input['action']??'draft';
 faq_init($pdo);
 if($action==='publish_now'){
  $items=faq_validate($input['faqs']??null);
  $json=json_encode($items,JSON_UNESCAPED_UNICODE);
  $stmt=$pdo->prepare('UPDATE website_faqs SET published_json=?,draft_json=? WHERE id=1');
  $stmt->execute([$json,$json]);
 }elseif($action==='draft'){
  $items=faq_validate($input['faqs']??null);
  $stmt=$pdo->prepare('UPDATE website_faqs SET draft_json=? WHERE id=1');
  $stmt->execute([json_encode($items,JSON_UNESCAPED_UNICODE)]);
 }elseif($action==='publish'){
  $state=faq_read($pdo,true);
  $items=faq_validate($state['draft']);
  $json=json_encode($items,JSON_UNESCAPED_UNICODE);
  $stmt=$pdo->prepare('UPDATE website_faqs SET published_json=?,draft_json=? WHERE id=1');
  $stmt->execute([$json,$json]);
 }elseif($action==='discard'){
  $pdo->exec('UPDATE website_faqs SET draft_json=published_json WHERE id=1');
 }else json_response(false,'Invalid action.',422);
 json_response(true,'FAQs updated.',200,['data'=>faq_read($pdo,true)]);
}catch(InvalidArgumentException $e){json_response(false,$e->getMessage(),422);
}catch(Throwable $e){error_log('FAQ error: '.$e->getMessage());json_response(false,'Unable to update FAQs.',500);}
