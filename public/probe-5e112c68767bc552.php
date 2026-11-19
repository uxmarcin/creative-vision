<?php
ini_set('display_errors','1'); error_reporting(E_ALL);
header('Content-Type: text/plain');
echo "php ", PHP_VERSION, " ", PHP_SAPI, "\n";
echo "ext ", implode(',', array_values(array_intersect(get_loaded_extensions(),['pdo_sqlite','sqlite3','pdo_mysql','mysqli','openssl','curl','mbstring','gd','zip','intl','dom','xml','zlib','iconv','sodium','imagick','fileinfo','json']))), "\n";
echo "mail ", function_exists('mail') ? 'yes' : 'no', " sendmail=", ini_get('sendmail_path'), "\n";
echo "docroot ", $_SERVER['DOCUMENT_ROOT'] ?? '', "\n";
$d = dirname(__DIR__);
echo "parent ", $d, " writable=", @is_writable($d) ? 'y' : 'n', " here_writable=", is_writable(__DIR__) ? 'y' : 'n', "\n";
echo "open_basedir ", ini_get('open_basedir'), "\n";
$l = @scandir($d); echo "parent_list ", is_array($l) ? implode(' ', array_slice($l,0,30)) : 'n/a', "\n";
echo "disabled ", ini_get('disable_functions'), "\n";
echo "mem ", ini_get('memory_limit'), " exec ", ini_get('max_execution_time'), " url_fopen ", ini_get('allow_url_fopen'), " tz ", date_default_timezone_get(), "\n";
if (class_exists('PDO')) { echo "pdo_drivers ", implode(',', PDO::getAvailableDrivers()), "\n"; }
