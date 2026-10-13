<?php
declare(strict_types=1);
require_once __DIR__.'/../helpers.php';
apply_cors_headers();
if ($_SERVER['REQUEST_METHOD']==='OPTIONS') exit;
function legal_init(PDO $pdo): void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS website_legal_content (page_key VARCHAR(32) NOT NULL PRIMARY KEY, content_json LONGTEXT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function legal_sanitize_html(string $html): string {
 if(strlen($html)>120000)throw new InvalidArgumentException('Document is too large.');
 if(!class_exists('DOMDocument'))throw new RuntimeException('PHP DOM extension is required.');
 $doc=new DOMDocument('1.0','UTF-8');
 $previous=libxml_use_internal_errors(true);
 $doc->loadHTML('<?xml encoding="UTF-8"><div id="legal-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_NONET);
 libxml_clear_errors();libxml_use_internal_errors($previous);
 $root=$doc->getElementById('legal-root');
 if(!$root)throw new InvalidArgumentException('Invalid document.');
 $allowed=['p','h2','strong','b','em','i','ul','ol','li','br'];
 $sanitize=function(DOMNode $node)use(&$sanitize,$allowed,$doc):void{
  for($child=$node->firstChild;$child;){
   $next=$child->nextSibling;
   if($child instanceof DOMElement){
    $tag=strtolower($child->tagName);
    if(!in_array($tag,$allowed,true)){
     // Discard unsafe embedded elements completely.
     if(in_array($tag,['script','style','iframe','svg','object','embed','form','img','video','audio'],true)){
      $node->removeChild($child);$child=$next;continue;
     }
     $sanitize($child);
     while($child->firstChild)$node->insertBefore($child->firstChild,$child);
     $node->removeChild($child);
    }else{
     while($child->attributes->length)$child->removeAttributeNode($child->attributes->item(0));
     $sanitize($child);
    }
   }elseif(!($child instanceof DOMText)){
    $node->removeChild($child);
   }
   $child=$next;
  }
 };
 $sanitize($root);
 $output='';
 foreach($root->childNodes as $node)$output.=$doc->saveHTML($node);
 if(trim(strip_tags($output))==='')throw new InvalidArgumentException('Document cannot be empty.');
 return $output;
}
function legal_validate($value): array {
 if(!is_array($value))throw new InvalidArgumentException('Invalid legal page content.');
 if(isset($value['html'])){
  if(!is_string($value['html']))throw new InvalidArgumentException('Invalid document.');
  return ['html'=>legal_sanitize_html($value['html'])];
 }
 // Backward compatibility with the previously published section-based documents.
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
