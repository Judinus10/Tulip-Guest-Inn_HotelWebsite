<?php
declare(strict_types=1);
require_once __DIR__.'/../helpers.php';
apply_cors_headers();
if ($_SERVER['REQUEST_METHOD']==='OPTIONS') exit;
function legal_init(PDO $pdo): void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS website_legal_content (page_key VARCHAR(32) NOT NULL PRIMARY KEY, content_json LONGTEXT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function legal_safe_link(string $value): string {
 $href=trim($value);
 return !preg_match('/[\x00-\x20\x7f]/',$href) && preg_match('~^(https?://|mailto:|tel:|/(?!/)|\#)~i',$href) ? $href : '';
}
function legal_safe_style(string $style): string {
 $fonts=['arial'=>'Arial','verdana'=>'Verdana','georgia'=>'Georgia','times new roman'=>'Times New Roman','tahoma'=>'Tahoma','trebuchet ms'=>'Trebuchet MS','courier new'=>'Courier New','sans-serif'=>'sans-serif','serif'=>'serif','monospace'=>'monospace','arial black'=>'Arial Black','helvetica'=>'Helvetica','segoe ui'=>'Segoe UI','calibri'=>'Calibri','candara'=>'Candara','century gothic'=>'Century Gothic','lucida sans unicode'=>'Lucida Sans Unicode','cambria'=>'Cambria','garamond'=>'Garamond','palatino linotype'=>'Palatino Linotype','book antiqua'=>'Book Antiqua','consolas'=>'Consolas','lucida console'=>'Lucida Console','impact'=>'Impact','system-ui'=>'system-ui'];
 $clean=[];
 foreach(explode(';',$style) as $declaration){
  $pair=explode(':',$declaration,2);if(count($pair)!==2)continue;
  $property=strtolower(trim($pair[0]));$value=trim($pair[1]);$lower=strtolower($value);
  if($property==='font-family'){
   $family=strtolower(trim(explode(',',$value)[0]," \"'"));
   if(isset($fonts[$family]))$clean[$property]=$fonts[$family];
  }elseif($property==='font-size' && preg_match('/^(\d+(?:\.\d+)?)\s*(px|pt|em|rem|%)$/i',$value,$m)){
   $size=(float)$m[1];$unit=strtolower($m[2]);
   if($unit==='pt')$size*=4/3;
   if($unit==='em'||$unit==='rem')$size*=16;
   if($unit==='%')$size*=0.16;
   $clean[$property]=rtrim(rtrim(number_format(max(10,min(48,$size)),2,'.',''),'0'),'.').'px';
  }elseif($property==='text-align' && in_array($lower,['left','center','right','justify'],true))$clean[$property]=$lower;
  elseif($property==='font-weight' && preg_match('/^(normal|bold|[1-9]00)$/',$lower))$clean[$property]=$lower;
  elseif($property==='font-style' && in_array($lower,['normal','italic'],true))$clean[$property]=$lower;
  elseif(in_array($property,['text-decoration','text-decoration-line'],true) && in_array($lower,['none','underline','line-through','underline line-through'],true))$clean['text-decoration']=$lower;
 }
 $output=[];foreach($clean as $key=>$value)$output[]=$key.':'.$value;
 return implode(';',$output);
}
function legal_sanitize_html(string $html): string {
 if(strlen($html)>120000)throw new InvalidArgumentException('Document is too large.');
 if(!class_exists('DOMDocument'))throw new RuntimeException('PHP DOM extension is required.');
 $doc=new DOMDocument('1.0','UTF-8');
 $previous=libxml_use_internal_errors(true);
 // A full document wrapper keeps pasted document fragments inside one body.
 $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>'.$html.'</body></html>',LIBXML_NONET);
 libxml_clear_errors();libxml_use_internal_errors($previous);
 $root=$doc->getElementsByTagName('body')->item(0);
 if(!$root)throw new InvalidArgumentException('Invalid document.');
 $allowed=['p','div','span','h1','h2','h3','h4','h5','h6','strong','b','em','i','u','s','ul','ol','li','br','a','blockquote'];
 $discard=['script','style','iframe','svg','math','object','embed','form','img','video','audio','input','button','textarea','select','link','meta','base','template'];
 $sanitize=function(DOMNode $node)use(&$sanitize,$allowed,$discard,$doc):void{
  for($child=$node->firstChild;$child;){
   $next=$child->nextSibling;
   if($child instanceof DOMElement){
    $tag=strtolower($child->tagName);
    if(in_array($tag,$discard,true)){$node->removeChild($child);$child=$next;continue;}
    if($tag==='font'){
     $replacement=$doc->createElement('span');
     $legacyStyle='';
     if($child->hasAttribute('face'))$legacyStyle.='font-family:'.$child->getAttribute('face').';';
     $sizes=['1'=>'10px','2'=>'13px','3'=>'16px','4'=>'18px','5'=>'24px','6'=>'32px','7'=>'48px'];
     if(isset($sizes[$child->getAttribute('size')]))$legacyStyle.='font-size:'.$sizes[$child->getAttribute('size')].';';
     $replacement->setAttribute('style',$legacyStyle.$child->getAttribute('style'));
     while($child->firstChild)$replacement->appendChild($child->firstChild);
     $node->replaceChild($replacement,$child);$child=$replacement;$tag='span';
    }
    $sanitize($child);
    if(!in_array($tag,$allowed,true)){
     while($child->firstChild)$node->insertBefore($child->firstChild,$child);
     $node->removeChild($child);
    }else{
     $style=legal_safe_style($child->getAttribute('style'));
     $href=$tag==='a'?legal_safe_link($child->getAttribute('href')):'';
     while($child->attributes->length)$child->removeAttributeNode($child->attributes->item(0));
     if($style!=='')$child->setAttribute('style',$style);
     if($href!==''){$child->setAttribute('href',$href);$child->setAttribute('target','_blank');$child->setAttribute('rel','noopener noreferrer');}
     if(!$child->hasChildNodes() && in_array($tag,['p','div','h1','h2','h3','h4','h5','h6'],true))$child->appendChild($doc->createElement('br'));
    }
   }elseif(!($child instanceof DOMText))$node->removeChild($child);
   $child=$next;
  }
 };
 $sanitize($root);
 $output='';foreach($root->childNodes as $node)$output.=$doc->saveHTML($node);
 if(trim(html_entity_decode(strip_tags($output),ENT_QUOTES|ENT_HTML5,'UTF-8'))==='')throw new InvalidArgumentException('Document cannot be empty.');
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
  if(!in_array($key,['terms','privacy','refund'],true))json_response(false,'Invalid page.',422);
  $stmt=$pdo->prepare('SELECT content_json,updated_at FROM website_legal_content WHERE page_key=?');$stmt->execute([$key]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
  json_response(true,'Content loaded.',200,['data'=>$row?['content'=>legal_validate(json_decode($row['content_json'],true)),'updated_at'=>$row['updated_at']]:null]);
 }
 require_admin_auth();
 if($_SERVER['REQUEST_METHOD']!=='POST')json_response(false,'Method not allowed.',405);
 $input=read_request_data();$key=(string)($input['page']??'');
 if(!in_array($key,['terms','privacy','refund'],true))json_response(false,'Invalid page.',422);
 $content=legal_validate($input['content']??null);
 $stmt=$pdo->prepare('INSERT INTO website_legal_content(page_key,content_json) VALUES(?,?) ON DUPLICATE KEY UPDATE content_json=VALUES(content_json),updated_at=CURRENT_TIMESTAMP');
 $stmt->execute([$key,json_encode($content,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
 json_response(true,'Legal page published.',200);
}catch(InvalidArgumentException $e){json_response(false,$e->getMessage(),422);
}catch(Throwable $e){error_log('Legal content: '.$e->getMessage());json_response(false,'Unable to process legal content.',500);}
