<?php
declare(strict_types=1);
require_once __DIR__ . '/_business_links_helpers.php';
apply_cors_headers();
$admin = require_mail_super_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Only POST requests are allowed.', 405);

try {
    $pdo = get_db_connection();
    ensure_business_links_table($pdo);
    $data = read_request_data();
    $id = max(0, (int) ($data['id'] ?? 0));
    $title = clean_string($data['title'] ?? '', 120);
    $description = clean_string($data['description'] ?? '', 500);
    $url = validate_business_url((string) ($data['portal_url'] ?? ''));
    $category = strtolower(clean_string($data['category'] ?? 'other', 30));
    $enabled = !empty($data['is_enabled']) ? 1 : 0;
    if ($title === '') json_response(false, 'Enter a name for this business page.', 422, ['field' => 'title']);
    if (!in_array($category, BUSINESS_LINK_CATEGORIES, true)) $category = 'other';

    $isUpdate = $id > 0;
    if ($isUpdate) {
        $find = $pdo->prepare('SELECT * FROM external_portal_links WHERE id=:id LIMIT 1');
        $find->execute([':id' => $id]);
        if (!$find->fetch()) json_response(false, 'The selected business page no longer exists.', 404);
        $stmt = $pdo->prepare('UPDATE external_portal_links SET title=:title, description=:description, portal_url=:url, category=:category, is_enabled=:enabled WHERE id=:id');
        $stmt->execute([':title'=>$title, ':description'=>$description, ':url'=>$url, ':category'=>$category, ':enabled'=>$enabled, ':id'=>$id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO external_portal_links (portal_key,title,description,portal_url,category,is_enabled,is_system,created_by) VALUES (:key,:title,:description,:url,:category,:enabled,0,:admin)');
        $stmt->execute([':key'=>business_link_key($title), ':title'=>$title, ':description'=>$description, ':url'=>$url, ':category'=>$category, ':enabled'=>$enabled, ':admin'=>$admin['id'] ?? null]);
        $id = (int) $pdo->lastInsertId();
    }
    mail_settings_audit($pdo, $admin, $isUpdate ? 'updated' : 'created', 'business_link', (string) $id, ($isUpdate ? 'Updated ' : 'Created ') . $title . '. No external credentials were stored.');
    json_response(true, $isUpdate ? 'Business page updated.' : 'Business page added.', $isUpdate ? 200 : 201, ['data'=>['id'=>$id]]);
} catch (Throwable $e) {
    error_log('Business link save failed: ' . $e->getMessage());
    json_response(false, 'The business page could not be saved. Check the address and try again.', 500);
}
