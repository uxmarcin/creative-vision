<?php // Lokalny podgląd: php -S 127.0.0.1:8090 -t public tools/router.php
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($p !== '/' && is_file(__DIR__ . '/../public' . $p)) return false;
$f = __DIR__ . '/../public' . ($p === '/' ? '/index.html' : rtrim($p, '/') . '.html');
if (is_file($f)) { header('Content-Type: text/html; charset=utf-8'); readfile($f); return true; }
http_response_code(404); readfile(__DIR__ . '/../public/404.html');
