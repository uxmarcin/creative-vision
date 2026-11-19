<?php
// Księgowość: faktury zakupowe (z KSeF i wgrane ręcznie) i miesięczna paczka dla księgowej.
declare(strict_types=1);

const COST_DIR = CV_DATA . '/costs';
const COST_TYPES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'heic' => 'image/heic', 'webp' => 'image/webp', 'xml' => 'application/xml'];

function books_migrate(): void {
    static $done = false; if ($done) return; $done = true;
    db()->exec('CREATE TABLE IF NOT EXISTS costs (id INTEGER PRIMARY KEY, ym TEXT NOT NULL, src TEXT NOT NULL, fname TEXT, mime TEXT, path TEXT, size INTEGER,
        ksef TEXT UNIQUE, seller TEXT, nip TEXT, number TEXT, issue_date TEXT, gross INTEGER, currency TEXT, created TEXT NOT NULL)');
}
function cost_by_id(int $id): ?array { books_migrate(); $s = db()->prepare('SELECT * FROM costs WHERE id = ?'); $s->execute([$id]); return $s->fetch() ?: null; }
function costs_of(string $ym): array { books_migrate(); $s = db()->prepare('SELECT * FROM costs WHERE ym = ? ORDER BY COALESCE(issue_date, created), id'); $s->execute([$ym]); return $s->fetchAll(); }
function sales_of(string $ym): array {
    $s = db()->prepare("SELECT * FROM invoices WHERE status IN ('issued', 'paid') AND COALESCE(book_ym, substr(issue_date, 1, 7)) = ? ORDER BY seq, id"); $s->execute([$ym]);
    return array_map('hydrate', $s->fetchAll());
}
function ym_label(string $ym): string { [$y, $m] = explode('-', $ym); return pl_month((int)$m) . ' ' . $y; }
function prev_ym(?string $from = null): string { return date('Y-m', strtotime(($from ?? date('Y-m')) . '-01 -1 month')); }
function safe_name(string $s): string { $s = preg_replace('/[^\pL\pN._\- ]+/u', '_', $s) ?? 'plik'; return trim(mb_substr($s, 0, 90), ' ._') ?: 'plik'; }

/** Odczyt najważniejszych danych z faktury FA (XML z KSeF). */
function fa_parse(string $xml): array {
    $x = @simplexml_load_string(preg_replace('/(<\/?)[\w-]+:/', '$1', preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $xml) ?? '') ?? '');
    if (!$x) return [];
    $s = fn($n) => trim((string)$n);
    $o = ['seller' => $s($x->Podmiot1->DaneIdentyfikacyjne->Nazwa ?? ''), 'nip' => $s($x->Podmiot1->DaneIdentyfikacyjne->NIP ?? ''),
        'buyer' => $s($x->Podmiot2->DaneIdentyfikacyjne->Nazwa ?? ''), 'number' => $s($x->Fa->P_2 ?? ''), 'issue_date' => $s($x->Fa->P_1 ?? ''),
        'gross' => (int)round(((float)$s($x->Fa->P_15 ?? '0')) * 100), 'currency' => $s($x->Fa->KodWaluty ?? '') ?: 'PLN', 'items' => []];
    foreach (($x->Fa->FaWiersz ?? []) as $w) $o['items'][] = ['name' => $s($w->P_7), 'qty' => $s($w->P_8B), 'unit' => $s($w->P_8A), 'price' => $s($w->P_9A), 'net' => $s($w->P_11), 'vat' => $s($w->P_12)];
    return $o;
}

/** Zapis wgranego pliku. Zwraca błąd albo null. */
function cost_store(string $ym, string $name, string $data, string $src = 'upload', array $meta = []): ?string {
    books_migrate();
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!isset(COST_TYPES[$ext])) return 'Plik ' . $name . ': obsługujemy PDF, zdjęcia (JPG, PNG, HEIC) i XML.';
    if (strlen($data) > 25 * 1048576) return 'Plik ' . $name . ' ma ponad 25 MB.';
    if ($ext === 'xml' && !$meta) { $meta = fa_parse($data); unset($meta['items'], $meta['buyer']); }
    $dir = COST_DIR . '/' . $ym; if (!is_dir($dir)) mkdir($dir, 0700, true);
    $path = $ym . '/' . bin2hex(random_bytes(10)) . '.' . $ext;
    file_put_contents(COST_DIR . '/' . $path, $data);
    db()->prepare('INSERT INTO costs (ym, src, fname, mime, path, size, ksef, seller, nip, number, issue_date, gross, currency, created) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$ym, $src, safe_name($name), COST_TYPES[$ext], $path, strlen($data), $meta['ksef'] ?? null, $meta['seller'] ?? null, $meta['nip'] ?? null, $meta['number'] ?? null, ($meta['issue_date'] ?? '') ?: null, isset($meta['gross']) ? (int)$meta['gross'] : null, $meta['currency'] ?? null, now()]);
    return null;
}

/** Pobiera z KSeF faktury zakupowe (my jako nabywca) wystawione w danym miesiącu. Zwraca [nowe, wszystkie]. */
function ksef_fetch_costs(string $ym): array {
    books_migrate();
    $tok = ksef_access_token();
    $from = date('c', strtotime($ym . '-01 00:00:00')); $to = date('c', strtotime($ym . '-01 00:00:00 +1 month') - 1);
    $new = 0; $all = 0; $page = 0;
    do {
        $r = ksef_http('POST', '/invoices/query/metadata?pageOffset=' . $page . '&pageSize=100&sortOrder=Asc', ['subjectType' => 'Subject2', 'dateRange' => ['dateType' => 'Issue', 'from' => $from, 'to' => $to]], $tok);
        $list = (array)($r['invoices'] ?? []);
        foreach ($list as $m) {
            $k = (string)($m['ksefNumber'] ?? ''); if ($k === '') continue;
            $all++;
            $q = db()->prepare('SELECT 1 FROM costs WHERE ksef = ?'); $q->execute([$k]); if ($q->fetchColumn()) continue;
            $xml = ksef_download_xml($k, $tok);
            $p = fa_parse($xml);
            $seller = (string)($m['seller']['name'] ?? ($m['sellerName'] ?? ($p['seller'] ?? '')));
            $nip = (string)($m['seller']['nip'] ?? ($m['sellerNip'] ?? ($p['nip'] ?? '')));
            $num = (string)($m['invoiceNumber'] ?? ($p['number'] ?? ''));
            $gross = isset($m['grossAmount']) ? (int)round((float)$m['grossAmount'] * 100) : (isset($m['totalAmount']) ? (int)round((float)$m['totalAmount'] * 100) : (int)($p['gross'] ?? 0));
            $err = cost_store($ym, safe_name(($seller !== '' ? $seller . ' ' : '') . $num) . '.xml', $xml, 'ksef', ['ksef' => $k, 'seller' => $seller, 'nip' => $nip, 'number' => $num,
                'issue_date' => substr((string)($m['issueDate'] ?? ($p['issue_date'] ?? '')), 0, 10), 'gross' => $gross, 'currency' => (string)($m['currency'] ?? ($m['currencyCode'] ?? ($p['currency'] ?? 'PLN')))]);
            if (!$err) $new++;
        }
        $page++;
    } while (!empty($r['hasMore']) && $page < 20);
    set_setting('books_ksef_' . $ym, now());
    return [$new, $all];
}
function ksef_download_xml(string $ksef, string $tok): string {
    $ch = curl_init(ksef_env()['api'] . '/invoices/ksef/' . rawurlencode($ksef));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Accept: application/xml', 'Authorization: Bearer ' . $tok]]);
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($res === false || $code >= 400) throw new RuntimeException('Nie udało się pobrać faktury ' . $ksef . ' z KSeF (' . $code . ').');
    return (string)$res;
}

/** Paczka ZIP za miesiąc: sprzedaż (PDF, XML z KSeF), zakupy, zestawienie CSV. Zwraca ścieżkę do pliku tymczasowego. */
function books_zip(string $ym): string {
    require_once __DIR__ . '/doc.php';
    $tmp = tempnam(sys_get_temp_dir(), 'cvz'); $z = new ZipArchive();
    if ($z->open($tmp, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Nie mogę przygotować paczki ZIP.');
    $csv = ["\xEF\xBB\xBF" . 'Rodzaj;Numer;Data wystawienia;Kontrahent;NIP;Netto;VAT;Brutto;Waluta;Numer KSeF;Plik'];
    $num = fn(int $c) => number_format($c / 100, 2, ',', '');
    $used = [];
    $uniq = function (string $n) use (&$used): string { $b = $n; $i = 2; while (isset($used[$n])) { $n = preg_replace('/(\.\w+)$/', ' (' . $i++ . ')$1', $b); } $used[$n] = 1; return $n; };
    foreach (sales_of($ym) as $inv) {
        $f = $uniq('Sprzedaz/' . invoice_pdf_name($inv)); $z->addFromString($f, invoice_pdf($inv));
        if (!empty($inv['ksef'])) $z->addFromString($uniq('Sprzedaz/' . str_replace('.pdf', '.xml', invoice_pdf_name($inv))), ksef_xml($inv));
        $csv[] = implode(';', ['sprzedaż', $inv['number'], $inv['issue_date'], str_replace(';', ',', (string)($inv['buyer']['name'] ?? '')), (string)($inv['buyer']['nip'] ?? ''), $num((int)$inv['net']), $num((int)$inv['vat']), $num((int)$inv['gross']), $inv['currency'] ?? 'PLN', (string)$inv['ksef'], $f]);
    }
    foreach (costs_of($ym) as $c) {
        $ext = pathinfo((string)$c['path'], PATHINFO_EXTENSION);
        $nm = $c['seller'] || $c['number'] ? safe_name(trim($c['seller'] . ' ' . $c['number'])) . '.' . $ext : (string)$c['fname'];
        if (!str_ends_with(strtolower($nm), '.' . $ext)) $nm .= '.' . $ext;
        $f = $uniq('Zakupy/' . $nm);
        if (is_file(COST_DIR . '/' . $c['path'])) $z->addFile(COST_DIR . '/' . $c['path'], $f);
        $csv[] = implode(';', ['zakup', (string)$c['number'], (string)$c['issue_date'], str_replace(';', ',', (string)$c['seller']), (string)$c['nip'], '', '', $c['gross'] !== null ? $num((int)$c['gross']) : '', (string)$c['currency'], (string)$c['ksef'], $f]);
    }
    $z->addFromString('Zestawienie_' . $ym . '.csv', implode("\r\n", $csv) . "\r\n");
    $z->close();
    return $tmp;
}
function books_zip_name(string $ym): string { return 'Creative_Vision_dokumenty_' . $ym . '.zip'; }

/** Wysyła paczkę do księgowej. Zwraca [ok, komunikat]. */
function books_send(string $ym): array {
    $to = trim(setting('acc_email'));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, 'Wpisz e-mail księgowej.'];
    if (ksef_enabled()) { try { ksef_fetch_costs($ym); } catch (Throwable $e) { /* wyślemy to, co mamy */ } }
    $sales = sales_of($ym); $costs = costs_of($ym);
    $zip = books_zip($ym); $data = (string)file_get_contents($zip); @unlink($zip);
    if (strlen($data) > 20 * 1048576) return [false, 'Paczka ma ponad 20 MB, za dużo na maila. Pobierz ją i wyślij przez dysk.'];
    $label = ym_label($ym);
    $sum = 0; foreach ($sales as $i) if (($i['currency'] ?? 'PLN') === 'PLN') $sum += (int)$i['gross'];
    $subj = 'Dokumenty Creative Vision za ' . $label;
    $rows = m_kicker('Dokumenty za ' . $label) . m_h1('Paczka dla księgowej', ucfirst($label))
        . m_text('Dzień dobry,<br><br>w załączniku przesyłam dokumenty za ' . e($label) . ': faktury sprzedażowe w PDF (i XML z KSeF, jeśli mają numer KSeF), faktury zakupowe oraz zestawienie w pliku CSV.' . (trim(setting('acc_note')) !== '' ? '<br><br>' . nl2br(e(setting('acc_note'))) : ''))
        . m_rows('W paczce', [['Faktury sprzedażowe', (string)count($sales)], ['Faktury zakupowe', (string)count($costs)], ['Sprzedaż brutto (PLN)', money($sum), true]])
        . m_sign();
    $ok = send_mail([$to], $subj, mail_layout($subj, 'Dokumenty za ' . $label . ': ' . count($sales) . ' sprzedaży, ' . count($costs) . ' zakupów.', $rows), html_to_text($rows), [books_zip_name($ym) => $data], true);
    if ($ok) set_setting('acc_sent_' . $ym, now());
    return [$ok, $ok ? 'Paczka za ' . $label . ' wysłana do ' . $to . '.' : 'Nie udało się wysłać: ' . mail_last_error()];
}

/** Raz w miesiącu, od wybranego dnia, sama wysyła paczkę za poprzedni miesiąc. */
function run_books_auto(): ?string {
    if (setting('acc_auto') !== '1' || !filter_var(trim(setting('acc_email')), FILTER_VALIDATE_EMAIL)) return null;
    if ((int)date('j') < max(1, min(28, (int)setting('acc_day')))) return null;
    $ym = prev_ym();
    if (setting('acc_sent_' . $ym) !== '' || (int)setting('acc_try_' . $ym) > time() - 20 * 3600) return null;
    set_setting('acc_try_' . $ym, (string)time());
    [$ok, $msg] = books_send($ym);
    if (!$ok) { [$s, $h, $t] = admin_mail('Paczka dla księgowej nie wyszła', 'Księgowość · ' . ym_label($ym), 'Paczka nie wyszła', e($msg) . ' Spróbujemy jutro, możesz też wysłać ją ręcznie w panelu.', CV_SITE . '/panel?a=books&ym=' . $ym); send_mail([setting('mail_reply')], $s, $h, $t, [], false); }
    return $msg;
}

// ---------- widok ----------
function books_post(string $a): never {
    $ym = preg_match('/^\d{4}-\d{2}$/', (string)($_POST['ym'] ?? '')) ? (string)$_POST['ym'] : prev_ym();
    $back = P . '?a=books&ym=' . $ym;
    switch ($a) {
        case 'books_upload':
            $f = $_FILES['files'] ?? null; $n = 0; $errs = [];
            if ($f && is_array($f['name'])) foreach ($f['name'] as $i => $nm) {
                if (($f['error'][$i] ?? 4) === 4) continue;
                if ($f['error'][$i] !== UPLOAD_ERR_OK) { $errs[] = $nm . ': nie dotarł (za duży?)'; continue; }
                $e = cost_store($ym, (string)$nm, (string)file_get_contents($f['tmp_name'][$i]));
                if ($e) $errs[] = $e; else $n++;
            }
            flash(($errs ? '!' : '') . ($n ? 'Dodano ' . $n . ' ' . plural($n, 'plik', 'pliki', 'plików') . ' do zakupów za ' . ym_label($ym) . '. ' : '') . implode(' ', $errs) ?: '!Nie wybrano plików.');
            redirect($back);
        case 'cost_delete':
            $c = cost_by_id((int)($_POST['id'] ?? 0));
            if ($c) { @unlink(COST_DIR . '/' . $c['path']); db()->prepare('DELETE FROM costs WHERE id = ?')->execute([$c['id']]); flash('Usunięto ' . ($c['number'] ?: $c['fname']) . '.'); $back = P . '?a=books&ym=' . $c['ym']; }
            redirect($back);
        case 'cost_edit':
            $c = cost_by_id((int)($_POST['id'] ?? 0));
            if ($c) db()->prepare('UPDATE costs SET seller = ?, number = ?, gross = ? WHERE id = ?')->execute([trim((string)($_POST['seller'] ?? '')) ?: null, trim((string)($_POST['number'] ?? '')) ?: null, trim((string)($_POST['gross'] ?? '')) !== '' ? to_cents((string)$_POST['gross']) : null, $c['id']]);
            redirect($back);
        case 'books_ksef':
            try { [$new, $all] = ksef_fetch_costs($ym); flash($all ? 'KSeF: ' . $all . ' ' . plural($all, 'faktura zakupowa', 'faktury zakupowe', 'faktur zakupowych') . ' za ' . ym_label($ym) . ', nowych: ' . $new . '.' : 'W KSeF nie ma faktur zakupowych za ' . ym_label($ym) . '.'); }
            catch (Throwable $e) { flash('!' . $e->getMessage()); }
            redirect($back);
        case 'books_send':
            [$ok, $msg] = books_send($ym); flash(($ok ? '' : '!') . $msg); redirect($back);
        case 'books_settings':
            set_setting('acc_email', trim(mb_substr((string)($_POST['acc_email'] ?? ''), 0, 200)));
            set_setting('acc_auto', !empty($_POST['acc_auto']) ? '1' : '0');
            set_setting('acc_day', (string)max(1, min(28, (int)($_POST['acc_day'] ?? 5))));
            set_setting('acc_note', trim(mb_substr((string)($_POST['acc_note'] ?? ''), 0, 1000)));
            flash('Ustawienia wysyłki do księgowej zapisane.'); redirect($back);
    }
    redirect($back);
}

function view_books(): never {
    books_migrate();
    $ym = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['ym'] ?? '')) ? (string)$_GET['ym'] : prev_ym();
    $sales = sales_of($ym); $costs = costs_of($ym);
    [$yy, $mm] = array_map('intval', explode('-', $ym));
    $has = [];
    $q = db()->prepare("SELECT COALESCE(book_ym, substr(issue_date, 1, 7)) AS m, COUNT(*) FROM invoices WHERE status IN ('issued', 'paid') AND COALESCE(book_ym, substr(issue_date, 1, 7)) LIKE ? GROUP BY m"); $q->execute([$yy . '-%']);
    foreach ($q->fetchAll(PDO::FETCH_NUM) as [$m, $n]) $has[(int)substr($m, 5, 2)] = ($has[(int)substr($m, 5, 2)] ?? 0) + (int)$n;
    $q = db()->prepare('SELECT ym, COUNT(*) FROM costs WHERE ym LIKE ? GROUP BY ym'); $q->execute([$yy . '-%']);
    foreach ($q->fetchAll(PDO::FETCH_NUM) as [$m, $n]) $has[(int)substr($m, 5, 2)] = ($has[(int)substr($m, 5, 2)] ?? 0) + (int)$n;
    $mp = '';
    for ($i = 1; $i <= 12; $i++) { $k = sprintf('%04d-%02d', $yy, $i); $fut = $k > date('Y-m');
        $mp .= '<a href="' . P . '?a=books&ym=' . $k . '" class="' . trim(($i === $mm ? 'on' : '') . (!empty($has[$i]) ? ' has' : '') . ($fut ? ' fut' : '')) . '" title="' . pl_month($i) . ' ' . $yy . '"' . ($i === $mm ? ' aria-current="true"' : '') . '>' . $i . '</a>'; }
    $yo = ''; for ($y = (int)date('Y'); $y >= 2025; $y--) $yo .= '<option value="' . $y . '-' . sprintf('%02d', min($mm, $y === (int)date('Y') ? (int)date('n') : 12)) . '"' . ($y === $yy ? ' selected' : '') . '>' . $y . '</option>';
    $nav = '<section class="card"><div class="period"><div class="pl"><span class="k">Miesiąc księgowy</span><b>' . e(ucfirst(ym_label($ym))) . '</b></div>'
        . '<nav class="months" aria-label="Miesiąc księgowy">' . $mp . '</nav>'
        . '<form method="get" action="' . P . '" class="yearf"><input type="hidden" name="a" value="books"><label class="sr" for="yy">Rok</label><select class="in" id="yy" name="ym" data-autosubmit>' . $yo . '</select></form></div></section>';
    $sum = []; $str = '';
    foreach ($sales as $i) {
        $cu = $i['currency'] ?? 'PLN'; $sum[$cu] = ($sum[$cu] ?? 0) + (int)$i['gross'];
        $str .= '<tr class="row" data-href="' . P . '?a=view&id=' . $i['id'] . '"><td class="c-num nw"><a class="num" href="' . P . '?a=view&id=' . $i['id'] . '">' . e($i['number']) . '</a></td><td class="c-cli"><span>' . e($i['buyer']['name'] ?? '') . '</span></td>'
            . '<td class="c-due nw">' . e(pl_date($i['issue_date'])) . '<span class="sub">' . (!empty($i['ksef']) ? 'KSeF' : 'bez KSeF') . '</span></td><td class="c-amt r amt">' . money((int)$i['gross'], $cu) . '</td><td class="c-st"></td><td class="c-act"></td></tr>';
    }
    $sumTxt = implode(' + ', array_map(fn($k, $v) => money($v, $k), array_keys($sum), $sum)) ?: money(0);
    $ctr = '';
    foreach ($costs as $c) {
        $url = P . '?a=cost&id=' . $c['id'];
        $ctr .= '<tr class="row" data-href="' . $url . '"><td class="c-num nw"><span class="num" style="border:0">' . ($c['src'] === 'ksef' ? 'KSeF' : strtoupper(pathinfo((string)$c['path'], PATHINFO_EXTENSION))) . '</span></td>'
            . '<td class="c-cli"><span>' . e($c['seller'] ?: $c['fname']) . '</span><span class="sub">' . e($c['number'] ?: ($c['seller'] ? '' : 'dodano ' . date('d.m', strtotime($c['created'])))) . '</span></td>'
            . '<td class="c-due nw">' . e($c['issue_date'] ? pl_date($c['issue_date']) : '') . '</td><td class="c-amt r amt">' . ($c['gross'] !== null ? money((int)$c['gross'], $c['currency'] ?: 'PLN') : '') . '</td><td class="c-st"></td>'
            . '<td class="c-act r nw"><form method="post" action="' . P . '?a=cost_delete">' . csrf_field() . '<input type="hidden" name="id" value="' . $c['id'] . '"><button class="link" style="color:var(--red)">Usuń</button></form></td></tr>';
    }
    $sent = setting('acc_sent_' . $ym); $kAt = setting('books_ksef_' . $ym);
    $drop = '<form class="drop" id="drop" method="post" action="' . P . '?a=books_upload" enctype="multipart/form-data">' . csrf_field() . '<input type="hidden" name="ym" value="' . $ym . '">'
        . '<input type="file" name="files[]" id="dropin" multiple accept="application/pdf,image/*,.pdf,.jpg,.jpeg,.png,.heic,.xml">'
        . '<label for="dropin">' . icon('clip') . '<b>Przeciągnij tu faktury zakupowe</b><span>albo kliknij, żeby wybrać pliki lub zrobić zdjęcie. PDF, JPG, PNG, XML.</span></label></form>';
    $body = $nav
        . '<section class="kpis"><div class="kpi dark"><span class="t">Paczka za ' . e(ym_label($ym)) . '</span><span class="v">' . count($sales) . ' + ' . count($costs) . '</span><span class="s">' . count($sales) . ' ' . plural(count($sales), 'faktura sprzedażowa', 'faktury sprzedażowe', 'faktur sprzedażowych') . ', ' . count($costs) . ' ' . plural(count($costs), 'zakupowa', 'zakupowe', 'zakupowych') . '</span></div><div class="kpis-r">'
        . '<div class="kpi"><span class="t">Sprzedaż brutto</span><span class="v">' . $sumTxt . '</span><span class="s">wg miesiąca księgowego</span></div>'
        . '<div class="kpi"><span class="t">Księgowa</span><span class="v" style="font-size:20px">' . ($sent ? 'Wysłana ' . e(date('d.m', strtotime($sent))) : 'Jeszcze nie wysłana') . '</span><span class="s">' . (setting('acc_auto') === '1' && setting('acc_email') !== '' ? 'sama wyjdzie ' . (int)setting('acc_day') . '. dnia miesiąca' : 'wysyłka ręczna') . '</span></div></div></section>'
        . '<div class="cols"><div class="wide sec">'
        . '<section class="card"><div class="hrow pad" style="padding-bottom:12px"><h2>Zakupy</h2>' . (ksef_enabled() ? '<form method="post" action="' . P . '?a=books_ksef">' . csrf_field() . '<input type="hidden" name="ym" value="' . $ym . '"><button class="btn ghost sm" data-busy>' . icon('refresh') . ' Pobierz z KSeF</button></form>' : '') . '</div>'
        . '<div class="pad" style="padding-top:0">' . $drop . ($kAt ? '<span class="mut" style="font-size:13px;display:block;margin-top:8px">Ostatnio sprawdzone w KSeF: ' . e(date('d.m.Y H:i', strtotime($kAt))) . '</span>' : (ksef_enabled() ? '' : '<span class="mut" style="font-size:13px;display:block;margin-top:8px">Żeby pobierać faktury zakupowe z KSeF, wpisz token w <a href="' . P . '?a=settings#ksef">ustawieniach</a>.</span>')) . '</div>'
        . ($ctr ? '<div class="tw"><table class="list"><tbody>' . $ctr . '</tbody></table></div>' : '<div class="empty" style="padding-top:8px">Brak faktur zakupowych za ten miesiąc.</div>') . '</section>'
        . '<section class="card"><div class="hrow pad" style="padding-bottom:12px"><h2>Sprzedaż</h2><span class="mut" style="font-size:14px">' . e($sumTxt) . '</span></div>'
        . ($str ? '<div class="tw"><table class="list"><tbody>' . $str . '</tbody></table></div>' : '<div class="empty" style="padding-top:8px">Brak faktur sprzedażowych w tym miesiącu księgowym.</div>') . '</section>'
        . '</div><aside class="side">'
        . '<section class="card pad sec"><h2>Dla księgowej</h2><span class="mut" style="font-size:14px">Jeden ZIP: sprzedaż w PDF i XML z KSeF, zakupy i zestawienie CSV.</span>'
        . '<a class="btn ghost" href="' . P . '?a=books_zip&ym=' . $ym . '">Pobierz paczkę ZIP</a>'
        . '<form method="post" action="' . P . '?a=books_send">' . csrf_field() . '<input type="hidden" name="ym" value="' . $ym . '"><button class="btn" style="width:100%"' . (setting('acc_email') === '' ? ' disabled' : '') . '>' . ($sent ? 'Wyślij ponownie' : 'Wyślij do księgowej') . ' ' . icon('arrow') . '</button></form>'
        . ($sent ? '<span class="ok-t" style="font-size:14px">Wysłana ' . e(date('d.m.Y H:i', strtotime($sent))) . ' do ' . e(setting('acc_email')) . '</span>' : '') . '</section>'
        . '<section class="card pad sec" id="acc"><h2>Wysyłka co miesiąc</h2><form class="inl" method="post" action="' . P . '?a=books_settings">' . csrf_field() . '<input type="hidden" name="ym" value="' . $ym . '">'
        . '<label class="f">E-mail księgowej<input class="in" type="email" name="acc_email" value="' . e(setting('acc_email')) . '" placeholder="biuro@ksiegowa.pl"></label>'
        . '<label class="check"><input type="checkbox" name="acc_auto" value="1"' . (setting('acc_auto') === '1' ? ' checked' : '') . '> Wysyłaj paczkę za poprzedni miesiąc automatycznie</label>'
        . '<label class="f">Którego dnia miesiąca<input class="in" type="number" name="acc_day" min="1" max="28" value="' . e(setting('acc_day')) . '"></label>'
        . '<label class="f">Dopisek w mailu (opcjonalnie)<textarea class="in" name="acc_note" rows="3" placeholder="np. Proszę o potwierdzenie odbioru.">' . e(setting('acc_note')) . '</textarea></label>'
        . '<button class="btn ghost">Zapisz</button><span class="mut" style="font-size:13px">Przed wysyłką pobierzemy jeszcze faktury zakupowe z KSeF. Wysyłamy w dni robocze rano.</span></form></section>'
        . '</aside></div>';
    page('Księgowość', 'books', $body, 'Dokumenty dla księgowej');
}

function view_cost(array $c): never {
    $file = COST_DIR . '/' . $c['path'];
    $ext = strtolower(pathinfo((string)$c['path'], PATHINFO_EXTENSION));
    $dl = '<a class="btn" href="' . P . '?a=cost_file&id=' . $c['id'] . '&dl=1">Pobierz plik</a>';
    $preview = '';
    if ($ext === 'xml' && is_file($file)) {
        $p = fa_parse((string)file_get_contents($file)); $rows = '';
        foreach ($p['items'] ?? [] as $i => $it) $rows .= '<tr class="it"><td class="lp">' . ($i + 1) . '</td><td class="nm"><strong>' . e($it['name']) . '</strong></td><td class="r nw">' . e($it['qty'] . ' ' . $it['unit']) . '</td><td class="r nw">' . e($it['price']) . '</td><td class="r nw">' . e($it['vat']) . '</td><td class="r nw">' . e($it['net']) . '</td></tr>';
        $preview = '<style>' . invoice_css(P . '/assets/') . '</style><div class="fv"><div class="grad top"></div><table class="hd"><tr><td><div class="sm g">Faktura zakupowa</div><div class="no">' . e($p['number'] ?? '') . '</div></td><td class="r"><div class="sm g">Data wystawienia</div>' . e(pl_date($p['issue_date'] ?? '')) . '</td></tr></table>'
            . '<table class="par"><tr><td><div class="g">Sprzedawca</div><div class="pn">' . e($p['seller'] ?? '') . '</div>NIP ' . e($p['nip'] ?? '') . '</td><td><div class="g">Nabywca</div><div class="pn">' . e($p['buyer'] ?? '') . '</div></td></tr></table>'
            . '<table class="its"><thead><tr><th class="lp">Lp.</th><th>Nazwa</th><th class="r">Ilość</th><th class="r">Cena netto</th><th class="r">VAT</th><th class="r">Netto</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<table class="sum"><tr class="tot"><td>Razem brutto</td><td class="r">' . money((int)($p['gross'] ?? 0), $p['currency'] ?? 'PLN') . '</td></tr></table>' . ($c['ksef'] ? '<div class="nt"><div class="g">Numer KSeF</div>' . e($c['ksef']) . '</div>' : '') . '</div>';
    } elseif ($ext === 'pdf') $preview = '<iframe class="mframe" src="' . P . '?a=cost_file&id=' . $c['id'] . '" title="Podgląd"></iframe>';
    elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) $preview = '<img src="' . P . '?a=cost_file&id=' . $c['id'] . '" alt="" style="max-width:100%;display:block">';
    else $preview = '<div class="empty">Podgląd niedostępny, pobierz plik.</div>';
    $edit = '<form class="inl" method="post" action="' . P . '?a=cost_edit">' . csrf_field() . '<input type="hidden" name="id" value="' . $c['id'] . '"><input type="hidden" name="ym" value="' . e($c['ym']) . '">'
        . '<label class="f">Sprzedawca<input class="in" name="seller" value="' . e((string)$c['seller']) . '"></label><label class="f">Numer faktury<input class="in" name="number" value="' . e((string)$c['number']) . '"></label>'
        . '<label class="f">Kwota brutto<input class="in" name="gross" inputmode="decimal" value="' . ($c['gross'] !== null ? money((int)$c['gross'], false) : '') . '"></label><button class="btn ghost sm">Zapisz opis</button></form>';
    $body = '<div class="cols"><div class="wide"><div class="doc-wrap">' . $preview . '</div></div><aside class="side"><div class="card pad sec"><div class="ihero"><span class="ik">' . e($c['seller'] ?: 'Faktura zakupowa') . '</span><span class="iv">' . ($c['gross'] !== null ? money((int)$c['gross'], $c['currency'] ?: 'PLN') : e($c['fname'])) . '</span><span class="is"><span class="chip due">' . ($c['src'] === 'ksef' ? 'Z KSeF' : 'Wgrana') . '</span><span class="mut">' . e(ym_label($c['ym'])) . '</span></span></div>'
        . '<div class="acts-col">' . $dl . '</div><details class="more"><summary>Opis faktury</summary>' . $edit . '</details>'
        . '<details class="more"><summary>Usuń</summary><form method="post" action="' . P . '?a=cost_delete">' . csrf_field() . '<input type="hidden" name="id" value="' . $c['id'] . '"><button class="btn danger">Usuń ten plik</button></form></details></div></aside></div>';
    $GLOBALS['back'] = [P . '?a=books&ym=' . $c['ym'], 'Księgowość']; $GLOBALS['pgcls'] = 'pg-inv';
    page($c['number'] ?: (string)$c['fname'], 'books', $body, (string)($c['seller'] ?? ''));
}
