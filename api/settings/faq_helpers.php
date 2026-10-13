<?php
declare(strict_types=1);
function faq_init(PDO $pdo): void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS website_faqs (id TINYINT UNSIGNED NOT NULL PRIMARY KEY, published_json LONGTEXT NULL, draft_json LONGTEXT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("INSERT IGNORE INTO website_faqs(id) VALUES (1)");
}
function faq_validate($items): array {
 if (!is_array($items) || count($items)>30) throw new InvalidArgumentException('Maximum 30 FAQs allowed.');
 $result=[];
 foreach($items as $item){
  if(!is_array($item)) throw new InvalidArgumentException('Invalid FAQ.');
  $q=trim((string)($item['question']??''));$a=trim((string)($item['answer']??''));
  if($q===''||$a===''||mb_strlen($q)>250||mb_strlen($a)>2000) throw new InvalidArgumentException('Each FAQ requires a question and answer (250/2000 character limits).');
  $result[]=['question'=>strip_tags($q),'answer'=>strip_tags($a)];
 }
 return $result;
}
function faq_read(PDO $pdo,bool $admin=false): array {
 faq_init($pdo);
 $row=$pdo->query('SELECT published_json,draft_json FROM website_faqs WHERE id=1')->fetch(PDO::FETCH_ASSOC);
 $published=json_decode($row['published_json']??'null',true);
 $draft=json_decode($row['draft_json']??'null',true);
 $published=is_array($published)?$published:[];
 if(!$admin)return $published;
 return ['published'=>$published,'draft'=>is_array($draft)?$draft:$published];
}
