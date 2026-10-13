<?php
declare(strict_types=1);
require_once __DIR__.'/../helpers.php';
apply_cors_headers();
if ($_SERVER['REQUEST_METHOD']==='OPTIONS') exit;
function legal_init(PDO $pdo): void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS website_legal_content (page_key VARCHAR(32) NOT NULL PRIMARY KEY, content_json LONGTEXT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function legal_validate($value): array {
 if(!is_array($value))throw new InvalidArgumentException('Invalid legal page content.');
 $intro=trim((string)($value['intro']??''));
 $sections=$value['sections']??null;
 if(mb_strlen($intro)>4000 || !is_array($sections) || count($sections)>40)throw new InvalidArgumentException('Invalid introduction or section count.');
 $clean=[];
 foreach($sections as $section){
  if(!is_array($section))throw new InvalidArgumentException('Invalid section.');
  $title=trim((string)($section['title']??''));$body=trim((string)($section['body']??''));
  if(!$title||!$body||mb_strlen($title)>200||mb_strlen($body)>10000)throw new InvalidArgumentException('Section title and text are required.');
  $clean[]=['title'=>strip_tags($title),'body'=>strip_tags($body)];
 }
 return ['intro'=>strip_tags($intro),'sections'=>$clean];
}
try{
 $pdo=get_db_connection();legal_init($pdo);
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $key=(string)($_GET['page']??'');
  if(!in_array($key,['terms','privacy'],true))json_response(false,'Invalid page.',422);
  $stmt=$pdo->prepare('SELECT content_json,updated_at FROM website_legal_content WHERE page_key=?');$stmt->execute([$key]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
  json_response(true,'Content loaded.',200,['data'=>$row?['content'=>json_decode($row['content_json'],true),'updated_at'=>$row['updated_at']]:null]);
 }
 require_admin_auth();
 if($_SERVER['REQUEST_METHOD']!=='POST')json_response(false,'Method not allowed.',405);
 $input=read_request_data();$key=(string)($input['page']??'');
 if(!in_array($key,['terms','privacy'],true))json_response(false,'Invalid page.',422);
 $content=legal_validate($input['content']??null);
 $stmt=$pdo->prepare('INSERT INTO website_legal_content(page_key,content_json) VALUES(?,?) ON DUPLICATE KEY UPDATE content_json=VALUES(content_json),updated_at=CURRENT_TIMESTAMP');
 $stmt->execute([$key,json_encode($content,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
 json_response(true,'Legal page published.',200);
}catch(InvalidArgumentException $e){json_response(false,$e->getMessage(),422);
}catch(Throwable $e){error_log('Legal content: '.$e->getMessage());json_response(false,'Unable to process legal content.',500);}
