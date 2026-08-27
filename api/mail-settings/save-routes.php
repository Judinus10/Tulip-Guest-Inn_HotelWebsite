<?php
declare(strict_types=1);
require_once __DIR__ . '/_mail_settings_helpers.php';
apply_cors_headers();
$admin = require_mail_super_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
try {
    $pdo=get_db_connection(); ensure_mail_settings_tables($pdo);
    $routes=(array)(read_request_data()['routes']??[]);
    $pdo->beginTransaction();
    $save=$pdo->prepare('INSERT INTO mail_routing_rules (function_key,sender_account_id,hotel_recipient_email,reply_to_email,send_customer_copy,send_hotel_copy,is_enabled) VALUES (:function_key,:sender,:recipient,:reply_to,:customer,:hotel,:enabled) ON DUPLICATE KEY UPDATE sender_account_id=VALUES(sender_account_id),hotel_recipient_email=VALUES(hotel_recipient_email),reply_to_email=VALUES(reply_to_email),send_customer_copy=VALUES(send_customer_copy),send_hotel_copy=VALUES(send_hotel_copy),is_enabled=VALUES(is_enabled)');
    foreach($routes as $route){
        $key=(string)($route['function_key']??''); if(!isset(MAIL_FUNCTIONS[$key])) continue;
        $sender=(int)($route['sender_account_id']??0); $sender=$sender>0?$sender:null;
        $recipient=strtolower(clean_string($route['hotel_recipient_email']??'',190));
        if($recipient!==''&&!filter_var($recipient,FILTER_VALIDATE_EMAIL)) json_response(false,'Enter a valid hotel recipient for '.MAIL_FUNCTIONS[$key].'.',422,['function_key'=>$key,'field'=>'hotel_recipient_email']);
        if($sender){$check=$pdo->prepare("SELECT id FROM mail_accounts WHERE id=:id AND is_enabled=1 AND connection_status='connected'");$check->execute([':id'=>$sender]);if(!$check->fetch())json_response(false,'Test and activate the selected sender before assigning it.',422,['function_key'=>$key]);}
        $policy=mail_delivery_policy($key);
        $save->execute([':function_key'=>$key,':sender'=>$sender,':recipient'=>$recipient?:null,':reply_to'=>null,':customer'=>$policy['customer']?1:0,':hotel'=>$policy['hotel']?1:0,':enabled'=>!empty($route['is_enabled'])?1:0]);
    }
    mail_settings_audit($pdo,$admin,'updated','mail_routing',null,'Updated mail senders and hotel recipients. Delivery audiences remain protected by application policy.');
    $pdo->commit(); json_response(true,'Mail routing rules saved.');
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('Mail routes save failed: '.$e->getMessage());json_response(false,'Mail routing rules could not be saved.',500);}
