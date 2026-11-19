<?php
// Generowanie PDF przez dołączony dompdf (bez Composera).
declare(strict_types=1);

spl_autoload_register(function (string $c): void {
    $v = __DIR__ . '/../vendor/';
    $map = ['Dompdf\\' => 'dompdf/src/', 'FontLib\\' => 'FontLib/', 'Svg\\' => 'Svg/', 'Masterminds\\' => 'Masterminds/', 'Sabberworm\\CSS\\' => 'Sabberworm-CSS/'];
    foreach ($map as $ns => $dir) {
        if (strncmp($c, $ns, strlen($ns)) === 0) {
            $f = $v . $dir . str_replace('\\', '/', substr($c, strlen($ns))) . '.php';
            if (is_file($f)) require $f;
            return;
        }
    }
});
require_once __DIR__ . '/../vendor/dompdf/lib/Cpdf.php';

function render_pdf(string $html): string {
    $cache = CV_DATA . '/pdf-fonts';
    if (!is_dir($cache)) @mkdir($cache, 0700, true);
    $o = new \Dompdf\Options();
    $o->setChroot([realpath(CV_ROOT)]);
    $o->setFontDir($cache);
    $o->setFontCache($cache);
    $o->setTempDir(CV_DATA);
    $o->setIsRemoteEnabled(false);
    $o->setIsPhpEnabled(false);
    $o->setIsJavascriptEnabled(false);
    $o->setDefaultFont('DejaVu Sans');
    $o->setDpi(96);
    $d = new \Dompdf\Dompdf($o);
    $d->loadHtml($html, 'UTF-8');
    $d->setPaper('A4', 'portrait');
    $d->render();
    return (string)$d->output();
}
