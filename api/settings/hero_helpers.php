<?php
declare(strict_types=1);
function hero_init(PDO $pdo): void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS website_hero (id TINYINT UNSIGNED NOT NULL PRIMARY KEY, published_json LONGTEXT NULL, draft_json LONGTEXT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("INSERT IGNORE INTO website_hero (id) VALUES (1)");
}
function hero_read(PDO $pdo, bool $admin=false): array {
 hero_init($pdo);
 $row=$pdo->query("SELECT published_json,draft_json FROM website_hero WHERE id=1")->fetch(PDO::FETCH_ASSOC);
 $published=json_decode($row['published_json'] ?? 'null',true);
 $draft=json_decode($row['draft_json'] ?? 'null',true);
 $empty=['mode'=>'images','images'=>[],'video'=>'','poster'=>''];
 return $admin ? ['published'=>$published ?: $empty,'draft'=>$draft ?: ($published ?: $empty)] : ($published ?: $empty);
}
function hero_valid_media(string $url, string $type): bool {
 if ($url==='') return true;
 $path=parse_url($url,PHP_URL_PATH) ?: '';
 return str_contains($path,'/uploads/hero/') && (bool)preg_match($type==='video' ? '/\.(mp4|webm)$/i' : '/\.(jpg|jpeg|png|webp)$/i',$path);
}
function hero_validate($data): array {
 if(!is_array($data)) throw new InvalidArgumentException('Invalid hero content.');
 $mode=$data['mode']??'images';
 $images=$data['images']??[];
 $video=(string)($data['video']??'');
 $poster=(string)($data['poster']??'');
 if(!in_array($mode,['images','video'],true) || !is_array($images) || count($images)>3) throw new InvalidArgumentException('Select up to three images or one video.');
 foreach($images as $url) if(!is_string($url)||!hero_valid_media($url,'image')) throw new InvalidArgumentException('Invalid hero image.');
 if(!hero_valid_media($video,'video')||!hero_valid_media($poster,'image')) throw new InvalidArgumentException('Invalid hero video or poster.');
 if($mode==='video' && !$video) throw new InvalidArgumentException('Upload a video before selecting video mode.');
 return ['mode'=>$mode,'images'=>array_values($images),'video'=>$video,'poster'=>$poster];
}
