<?php
header('Content-Type: application/json');
$d = dirname(__DIR__);
echo json_encode([
 'php'=>PHP_VERSION, 'sapi'=>PHP_SAPI,
 'ext'=>array_values(array_intersect(get_loaded_extensions(),['pdo_sqlite','sqlite3','pdo_mysql','mysqli','openssl','curl','mbstring','gd','zip','intl','dom','xml','zlib','iconv','sodium','imagick','fileinfo'])),
 'mail'=>function_exists('mail'), 'sendmail'=>ini_get('sendmail_path'),
 'docroot'=>$_SERVER['DOCUMENT_ROOT'] ?? '', 'parent'=>$d, 'parent_writable'=>is_writable($d), 'here_writable'=>is_writable(__DIR__),
 'parent_list'=>@array_slice(scandir($d),0,30), 'disabled'=>ini_get('disable_functions'), 'mem'=>ini_get('memory_limit'), 'max_exec'=>ini_get('max_execution_time'),
 'allow_url_fopen'=>ini_get('allow_url_fopen'), 'tz'=>date_default_timezone_get(), 'server'=>$_SERVER['SERVER_SOFTWARE'] ?? ''
], JSON_PRETTY_PRINT);
