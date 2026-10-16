<?php
declare(strict_types=1);
function policy_defaults(): array {
 return [
  'check_in'=>'Available 24 hours', 'check_out'=>'From 06:00 to 12:00',
  'cancellation'=>'Cancellation and prepayment conditions depend on your selected room and booking terms.',
  'children'=>'Children of all ages are welcome.',
  'extra_beds'=>'Cots and extra beds are not available.',
  'minimum_age'=>'No minimum age restriction.',
  'pets'=>'Pets are not allowed.',
  'smoking'=>'Smoking is not allowed.',
  'payments'=>'Payment options are shown during checkout. Online payments are subject to availability.',
 ];
}
function policy_init(PDO $pdo): void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS website_policies (id TINYINT UNSIGNED PRIMARY KEY, published_json LONGTEXT NULL, draft_json LONGTEXT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("INSERT IGNORE INTO website_policies(id) VALUES (1)");
}
function policy_normalize($value): array {
 if (!is_array($value)) throw new InvalidArgumentException('Invalid policy data.');
 $result=policy_defaults();
 foreach($result as $key=>$fallback) {
  $text=$value[$key]??$fallback;
  if(!is_string($text) || mb_strlen($text)>2000) throw new InvalidArgumentException('Invalid policy text for '.$key);
  $result[$key]=trim(strip_tags($text));
 }
 return $result;
}
function policy_read(PDO $pdo, bool $admin=false): array {
 policy_init($pdo);
 $row=$pdo->query('SELECT published_json,draft_json FROM website_policies WHERE id=1')->fetch(PDO::FETCH_ASSOC);
 $published=json_decode($row['published_json']??'null',true);
 $draft=json_decode($row['draft_json']??'null',true);
 $published=is_array($published)?policy_normalize($published):policy_defaults();
 // Website Settings is the single source of truth for arrival/departure times.
 require_once __DIR__.'/contact_helpers.php';
 $stay=get_contact_settings($pdo);
 $custom=($stay['stay_time_mode']??'custom')==='custom';
 $published['check_in']=$custom ? ($stay['check_in_custom']??'Available 24 hours') : ($stay['check_in_time']??'13:00');
 $published['check_out']=$custom ? ($stay['check_out_custom']??'From 06:00 to 12:00') : ($stay['check_out_until']??'12:00');
 if(!$admin)return $published;
 return ['published'=>$published,'draft'=>array_merge(is_array($draft)?policy_normalize($draft):$published,['check_in'=>$published['check_in'],'check_out'=>$published['check_out']])];
}
