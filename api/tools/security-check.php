<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../db.php';
$failed = false;
function report_check(string $name, bool $ok): void { global $failed; echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL; if (!$ok) $failed = true; }
report_check('production/staging environment', in_array(APP_ENV,['production','staging'],true));
foreach (['pdo_mysql','curl','openssl','fileinfo','gd','mbstring'] as $ext) report_check('PHP extension '.$ext, extension_loaded($ext));
report_check('signing key length', strlen(PUBLIC_TOKEN_SECRET)>=32);
report_check('Turnstile keys', (string)jebal_env_value('TURNSTILE_SITE_KEY','')!=='' && (string)jebal_env_value('TURNSTILE_SECRET_KEY','')!=='');
foreach ([FRONTEND_URL,ADMIN_APP_URL,API_BASE_URL] as $index=>$url) report_check('HTTPS URL '.($index+1),parse_url($url,PHP_URL_SCHEME)==='https');
try {
 $pdo=get_db_connection(); $pdo->query('SELECT id FROM security_audit_events LIMIT 0'); report_check('audit table',true);
 foreach (['payments'=>'status','bookings'=>'payment_status'] as $table=>$column) {
  $q=$pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");$row=$q->fetch();report_check($table.' status migration',strtolower((string)($row['Type']??''))==='varchar(40)');
 }
} catch (Throwable $e) { report_check('database/migration access (check server log)',false); }
exit($failed?1:0);
