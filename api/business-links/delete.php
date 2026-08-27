<?php
declare(strict_types=1);
require_once __DIR__ . '/_business_links_helpers.php';
apply_cors_headers();
$admin = require_mail_super_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);
try {
    $pdo=get_db_connection(); ensure_business_links_table($pdo); $data=read_request_data(); $id=(int)($data['id']??0);
    if ($id < 1) json_response(false, 'Select a valid business page.', 422);
    $find=$pdo->prepare('SELECT title,is_system FROM external_portal_links WHERE id=:id LIMIT 1'); $find->execute([':id'=>$id]); $link=$find->fetch(PDO::FETCH_ASSOC);
    if (!$link) json_response(false, 'The selected business page no longer exists.', 404);
    if ((int)$link['is_system'] === 1) json_response(false, 'Built-in business pages cannot be deleted. Hide the page instead.', 409);
    $pdo->prepare('DELETE FROM external_portal_links WHERE id=:id')->execute([':id'=>$id]);
    mail_settings_audit($pdo,$admin,'deleted','business_link',(string)$id,'Deleted '.$link['title'].'.');
    json_response(true,'Business page deleted.');
} catch(Throwable $e){ error_log('Business link delete failed: '.$e->getMessage()); json_response(false,'The business page could not be deleted.',500); }
