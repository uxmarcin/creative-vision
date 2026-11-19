<?php
// Strona faktury dla klienta: /f/{token}
declare(strict_types=1);

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/doc.php';
require __DIR__ . '/lib/mail.php';

security_headers();
header('Cache-Control: no-store');

$t = (string)($_GET['t'] ?? '');
$inv = invoice_by_token($t);
if (!$inv || $inv['status'] === 'cancelled') {
    http_response_code(404);
    echo '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Nie znaleziono faktury</title><link rel="stylesheet" href="/panel/assets/panel.css"></head>'
        . '<body><div class="auth"><div class="box"><img src="/panel/assets/logo-black.svg" alt="creative vision" style="height:26px;width:auto;align-self:flex-start"><h1>Nie znaleźliśmy tej faktury</h1><p>Link mógł wygasnąć albo faktura została anulowana. Napisz do nas: <a href="mailto:kontakt@creative-vision.pl">kontakt@creative-vision.pl</a>.</p></div></div></body></html>';
    exit;
}
$id = (int)$inv['id'];
$url = '/f/' . $inv['token'];

if (isset($_GET['pdf'])) {
    $pdf = invoice_pdf($inv);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . invoice_pdf_name($inv) . '"');
    log_event($id, 'view', 'Klient pobrał PDF');
    echo $pdf; exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'declare') {
    if ($inv['status'] === 'issued' && empty($inv['declared_at'])) {
        db()->prepare('UPDATE invoices SET declared_at = ?, updated = ? WHERE id = ?')->execute([now(), now(), $id]);
        log_event($id, 'declare', 'Klient zgłosił, że zapłacił');
        [$s, $h, $tx] = admin_mail('Klient zgłosił wpłatę: ' . $inv['number'], 'Wpłata zgłoszona · ' . $inv['number'], ($inv['buyer']['name'] ?? 'Klient') . ' zgłasza',
            'Klient kliknął „Zapłaciłem” przy fakturze ' . e($inv['number']) . ' na kwotę ' . e(money((int)$inv['gross'], $inv['currency'] ?? 'PLN')) . '. Przypomnienia są wstrzymane. Gdy przelew dotrze, potwierdź wpłatę w panelu.', CV_SITE . '/panel?a=view&id=' . $id, 'wpłatę');
        send_mail([setting('mail_reply')], $s, $h, $tx, [], false);
    }
    header('Location: ' . $url . '?ok=1', true, 303); exit;
}

if (empty($inv['viewed_at'])) {
    db()->prepare('UPDATE invoices SET viewed_at = ? WHERE id = ?')->execute([now(), $id]);
    log_event($id, 'view', 'Klient otworzył fakturę online');
}

$s = seller(); $acc = nrb_digits($s['bank_account']);
$num = (string)$inv['number']; $gross = (int)$inv['gross'];
$st = inv_state($inv);
$left = days_between(today(), (string)$inv['due_date']);
$status = match ($st) {
    'paid' => ['Opłacona. Dziękujemy!', 'ok'],
    'late' => ['Termin minął ' . pl_date($inv['due_date']), 'late'],
    'today' => ['Termin płatności mija dziś', 'late'],
    default => ['Termin płatności ' . pl_date($inv['due_date']) . ' (za ' . $left . ' ' . ($left === 1 ? 'dzień' : 'dni') . ')', ''],
};
if ($st !== 'paid' && !empty($inv['declared_at'])) $status = ['Dziękujemy, zgłosiłeś wpłatę ' . pl_date($inv['declared_at']) . '. Potwierdzimy ją, gdy przelew dotrze.', 'ok'];
// kod QR standardu ZBP (kwota do 9 999,99 zł)
$qr = ($acc !== '' && $gross > 0 && $gross <= 999999 && $st !== 'paid')
    ? nip_digits($s['seller_nip']) . '|PL|' . $acc . '|' . sprintf('%06d', $gross) . '|' . mb_substr($s['seller_name'], 0, 20) . '|' . mb_substr('Faktura ' . $num, 0, 32) . '|||'
    : '';
$copy = fn($v, $label) => '<button type="button" class="cp" data-copy="' . e($v) . '" aria-label="Kopiuj ' . e($label) . '">Kopiuj</button>';
$v = substr((string)@md5_file(__DIR__ . '/assets/panel.css'), 0, 10);
?><!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0B0B0B">
<title>Faktura <?= e($num) ?> · Creative Vision</title>
<link rel="icon" href="/favicon.ico">
<link rel="stylesheet" href="/panel/assets/panel.css?v=<?= $v ?>">
<style>
.pg{max-width:1080px;margin:0 auto;padding:28px 24px 64px;display:flex;flex-direction:column;gap:24px}
.ph{display:flex;align-items:center;justify-content:space-between;gap:16px}
.ph img{height:26px;width:auto;display:block}
.ph a{font-size:14px;color:var(--mut);text-decoration:none}
.hero{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1fr);background:var(--ink);color:#fff;position:relative}
.hero::before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:var(--grad)}
.hero .l{padding:40px 40px 36px;display:flex;flex-direction:column;gap:14px}
.hero .k{font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#9A9A9A}
.hero h1{margin:0;font-size:56px;line-height:1.02;font-weight:500;letter-spacing:-.01em}
.hero .who{color:#C9C9C9;font-size:16px}
.st{display:inline-flex;align-self:flex-start;align-items:center;gap:8px;height:32px;padding:0 12px;background:#1C1C1C;color:#fff;font-size:14px}
.st.ok{background:#14301F;color:#9FD8B5}.st.late{background:#3A1512;color:#F4B4AC}
.hero .r{padding:32px 40px 36px;border-left:1px solid #262626;display:flex;flex-direction:column;gap:16px}
.row2{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;border-bottom:1px solid #262626;padding-bottom:14px}
.row2 .lb{font-size:13px;color:#9A9A9A;display:block;margin-bottom:2px}
.row2 .vl{font-size:17px;font-variant-numeric:tabular-nums;word-break:break-word}
.row2 .vl.acc{font-size:18px;font-weight:500}
.cp{flex:none;height:34px;padding:0 12px;background:transparent;color:#fff;border:1px solid #3A3A3A;cursor:pointer;font-size:12px;font-weight:600;letter-spacing:.12em;text-transform:uppercase}
.cp:hover{border-color:#fff}
.qrw{display:flex;gap:18px;align-items:center}
.qrw #qr{background:#fff;padding:10px;flex:none}
.qrw #qr img,.qrw #qr canvas{display:block;width:132px;height:132px}
.qrw p{margin:0;font-size:14px;color:#BDBDBD;line-height:1.5}
.btns{display:flex;flex-wrap:wrap;gap:10px}
.btn.w{background:#fff;color:var(--ink);border-color:#fff}.btn.w:hover{background:#EDEDED;color:var(--ink)}
.btn.o{background:transparent;color:#fff;border-color:#3A3A3A}.btn.o:hover{border-color:#fff;color:#fff}
.doc-wrap{box-shadow:0 30px 60px -40px rgba(0,0,0,.35)}
.pfoot{display:flex;flex-wrap:wrap;gap:8px 24px;justify-content:space-between;font-size:14px;color:var(--mut)}
.pfoot a{color:var(--ink);text-decoration:none}
@media (max-width:860px){
  .pg{padding:16px 12px 40px;gap:16px}
  .hero{grid-template-columns:1fr}
  .hero .l{padding:32px 22px 24px}
  .hero h1{font-size:42px}
  .hero .r{padding:22px;border-left:0;border-top:1px solid #262626}
  .btns .btn{flex:1 1 auto}
}
</style>
</head>
<body>
<div class="pg">
  <header class="ph"><a href="https://creative-vision.pl"><img src="/panel/assets/logo-black.svg" alt="creative vision"></a><a href="mailto:<?= e($s['seller_email']) ?>">Pytanie do faktury? Napisz</a></header>
  <?php if (isset($_GET['ok'])): ?><div class="flash" role="status">Dziękujemy! Daliśmy znać, że płatność jest w drodze.</div><?php endif ?>
  <section class="hero" aria-label="Płatność">
    <div class="l">
      <span class="k">Faktura <?= e($num) ?> · Do zapłaty</span>
      <h1><?= e(money($gross, $inv['currency'] ?? 'PLN')) ?></h1>
      <span class="who">dla <?= e($inv['buyer']['name'] ?? '') ?>, wystawiona <?= e(pl_date($inv['issue_date'])) ?></span>
      <span class="st <?= $status[1] ?>"><?= e($status[0]) ?></span>
      <div class="btns" style="margin-top:auto;padding-top:12px">
        <a class="btn w" href="<?= e($url) ?>?pdf=1">Pobierz PDF</a>
        <?php if ($st !== 'paid' && empty($inv['declared_at'])): ?>
        <form method="post" action="<?= e($url) ?>"><input type="hidden" name="do" value="declare"><button class="btn o">Zapłaciłem</button></form>
        <?php endif ?>
      </div>
    </div>
    <div class="r">
      <?php if ($acc !== ''): ?>
      <div class="row2"><div><span class="lb">Numer konta<?= $s['bank_name'] !== '' ? ' · ' . e($s['bank_name']) : '' ?></span><span class="vl acc"><?= e(nrb_fmt($acc)) ?></span></div><?= $copy($acc, 'numer konta') ?></div>
      <div class="row2"><div><span class="lb">Odbiorca</span><span class="vl"><?= e($s['seller_name']) ?>, <?= e($s['seller_addr1']) ?>, <?= e($s['seller_addr2']) ?></span></div></div>
      <div class="row2"><div><span class="lb">Tytuł przelewu</span><span class="vl">Faktura <?= e($num) ?></span></div><?= $copy('Faktura ' . $num, 'tytuł') ?></div>
      <div class="row2" style="border:0;padding:0"><div><span class="lb">Kwota</span><span class="vl"><?= e(money($gross, $inv['currency'] ?? 'PLN')) ?></span></div><?= $copy(number_format($gross / 100, 2, ',', ''), 'kwotę') ?></div>
      <?php if ($qr): ?>
      <div class="qrw"><div id="qr" data-qr="<?= e($qr) ?>" role="img" aria-label="Kod QR do przelewu"></div><p>Zeskanuj kod w aplikacji banku, a dane przelewu uzupełnią się same.</p></div>
      <?php endif ?>
      <?php else: ?>
      <p style="margin:0;color:#BDBDBD">Dane do przelewu znajdziesz na fakturze poniżej.</p>
      <?php endif ?>
    </div>
  </section>
  <div class="doc-wrap"><?= invoice_doc($inv, '/panel/assets/', false, false) ?></div>
  <footer class="pfoot"><span><?= e($s['seller_name']) ?> · <a href="tel:+48<?= e(preg_replace('/\D/', '', $s['seller_phone'])) ?>"><?= e($s['seller_phone']) ?></a> · <a href="mailto:<?= e($s['seller_email']) ?>"><?= e($s['seller_email']) ?></a></span><a href="https://creative-vision.pl">creative-vision.pl</a></footer>
</div>
<script src="/panel/assets/panel.js?v=<?= substr((string)@md5_file(__DIR__ . '/assets/panel.js'), 0, 10) ?>"></script>
<?php if ($qr): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>(function(){var el=document.getElementById('qr');if(!el||!window.QRCode)return;new QRCode(el,{text:el.getAttribute('data-qr'),width:264,height:264,correctLevel:QRCode.CorrectLevel.M});})();</script>
<?php endif ?>
</body>
</html>
