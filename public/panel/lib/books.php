<?php
// Księgowość: faktury zakupowe (z KSeF i wgrane ręcznie) i miesięczna paczka dla księgowej.
declare(strict_types=1);

const COST_DIR = CV_DATA . '/costs';
const COST_TYPES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'heic' => 'image/heic', 'webp' => 'image/webp', 'xml' => 'application/xml'];

function books_migrate(): void {
    static $done = false; if ($done) return; $done = true;
    db()->exec('CREATE TABLE IF NOT EXISTS costs (id INTEGER PRIMARY KEY, ym TEXT NOT NULL, src TEXT NOT NULL, fname TEXT, mime TEXT, path TEXT, size INTEGER,
        ksef TEXT UNIQUE, seller TEXT, nip TEXT, number TEXT, issue_date TEXT, gross INTEGER, currency TEXT, created TEXT NOT NULL)');
    $cols = array_column(db()->query('PRAGMA table_info(costs)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('vat', $cols, true)) { db()->exec('ALTER TABLE costs ADD COLUMN net INTEGER'); db()->exec('ALTER TABLE costs ADD COLUMN vat INTEGER'); }
    if (setting('seed_autobajka_1') === '') {
        // faktura zakupowa podana ręcznie: Autobajka Rzeszów, październik 2026, wymiana rozrządu, 2200 zł brutto (VAT 23%)
        $q = db()->query("SELECT COUNT(*) FROM costs WHERE ym = '2026-10' AND seller LIKE 'Autobajka%'");
        if (!(int)$q->fetchColumn()) db()->prepare('INSERT INTO costs (ym, src, fname, mime, path, size, seller, number, issue_date, gross, currency, created, net, vat) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute(['2026-10', 'manual', 'Wymiana rozrządu', null, '', 0, 'Autobajka Rzeszów', null, null, 220000, 'PLN', now(), 178862, 41138]);
        set_setting('seed_autobajka_1', '1');
    }
    if (setting('costs_vat_1') === '') {
        foreach (db()->query("SELECT id, path FROM costs WHERE vat IS NULL AND path LIKE '%.xml'")->fetchAll() as $c) {
            $f = COST_DIR . '/' . $c['path']; if (!is_file($f)) continue; $p = fa_parse((string)file_get_contents($f));
            if (isset($p['vat'])) db()->prepare('UPDATE costs SET net = ?, vat = ? WHERE id = ?')->execute([$p['net'], $p['vat'], $c['id']]);
        }
        set_setting('costs_vat_1', '1');
    }
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
    $sum = fn(array $k) => array_sum(array_map(fn($n) => (int)round(((float)$s($x->Fa->{$n} ?? '0')) * 100), $k));
    $o['net'] = $sum(['P_13_1', 'P_13_2', 'P_13_3', 'P_13_4', 'P_13_5', 'P_13_6_1', 'P_13_6_2', 'P_13_6_3', 'P_13_7', 'P_13_8', 'P_13_9', 'P_13_10', 'P_13_11']);
    $o['vat'] = $sum(['P_14_1', 'P_14_2', 'P_14_3', 'P_14_4', 'P_14_5']);
    if (!$o['net'] && $o['gross']) $o['net'] = $o['gross'] - $o['vat'];
    foreach (($x->Fa->FaWiersz ?? []) as $w) $o['items'][] = ['name' => $s($w->P_7), 'qty' => $s($w->P_8B), 'unit' => $s($w->P_8A), 'price' => $s($w->P_9A), 'net' => $s($w->P_11), 'vat' => $s($w->P_12)];
    return $o;
}

/** Zapis wgranego pliku. Zwraca błąd albo null. */
function cost_store(string $ym, string $name, string $data, string $src = 'upload', array $meta = []): ?string {
    books_migrate();
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!isset(COST_TYPES[$ext])) return 'Plik ' . $name . ': obsługujemy PDF, zdjęcia (JPG, PNG, HEIC) i XML.';
    if (strlen($data) > 25 * 1048576) return 'Plik ' . $name . ' ma ponad 25 MB.';
    if ($ext === 'xml') { $p = fa_parse($data); unset($p['items'], $p['buyer']); $meta = $meta ? $meta + array_intersect_key($p, ['net' => 1, 'vat' => 1]) : $p; }
    $dir = COST_DIR . '/' . $ym; if (!is_dir($dir)) mkdir($dir, 0700, true);
    $path = $ym . '/' . bin2hex(random_bytes(10)) . '.' . $ext;
    file_put_contents(COST_DIR . '/' . $path, $data);
    db()->prepare('INSERT INTO costs (ym, src, fname, mime, path, size, ksef, seller, nip, number, issue_date, gross, currency, created, net, vat) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$ym, $src, safe_name($name), COST_TYPES[$ext], $path, strlen($data), $meta['ksef'] ?? null, $meta['seller'] ?? null, $meta['nip'] ?? null, $meta['number'] ?? null, ($meta['issue_date'] ?? '') ?: null, isset($meta['gross']) ? (int)$meta['gross'] : null, $meta['currency'] ?? null, now(), isset($meta['net']) ? (int)$meta['net'] : null, isset($meta['vat']) ? (int)$meta['vat'] : null]);
    return null;
}

/** Pobiera z KSeF faktury zakupowe (my jako nabywca) wystawione w danym miesiącu. Zwraca [nowe, wszystkie]. */
function ksef_fetch_costs(string $ym): array {
    books_migrate();
    $tok = ksef_access_token();
    $from = date('c', strtotime($ym . '-01 00:00:00')); $to = date('c', strtotime($ym . '-01 00:00:00 +1 month') - 1);
    $new = 0; $all = 0; $page = 0; $left = 0; $busy = false;
    @set_time_limit(170);
    do {
        $r = ksef_http('POST', '/invoices/query/metadata?pageOffset=' . $page . '&pageSize=100&sortOrder=Asc', ['subjectType' => 'Subject2', 'dateRange' => ['dateType' => 'Issue', 'from' => $from, 'to' => $to]], $tok);
        $list = (array)($r['invoices'] ?? []);
        foreach ($list as $m) {
            $k = (string)($m['ksefNumber'] ?? ''); if ($k === '') continue;
            $all++;
            $q = db()->prepare('SELECT 1 FROM costs WHERE ksef = ?'); $q->execute([$k]); if ($q->fetchColumn()) continue;
            if ($busy) { $left++; continue; }
            try { $xml = ksef_download_xml($k, $tok); } catch (KsefBusy $e) { $busy = true; $left++; continue; }
            usleep(400000);
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
    return [$new, $all, $left];
}
/** Limit zapytań KSeF (429): czekamy tyle, ile każe serwer, najwyżej kilka razy. */
class KsefBusy extends RuntimeException {}
function ksef_download_xml(string $ksef, string $tok): string {
    for ($try = 0; $try < 4; $try++) {
        $wait = 0;
        $ch = curl_init(ksef_env()['api'] . '/invoices/ksef/' . rawurlencode($ksef));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Accept: application/xml', 'Authorization: Bearer ' . $tok],
            CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$wait) { if (stripos($h, 'retry-after:') === 0) $wait = (int)trim(substr($h, 12)); return strlen($h); }]);
        $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($res !== false && $code < 400) return (string)$res;
        if ($code !== 429) throw new RuntimeException('Nie udało się pobrać faktury ' . $ksef . ' z KSeF (' . $code . ').');
        $wait = $wait ?: 2 * ($try + 1);
        if ($wait > 12) break;
        sleep($wait);
    }
    throw new KsefBusy('KSeF ogranicza liczbę pobrań.');
}

/** Paczka ZIP za miesiąc: sprzedaż (PDF, XML z KSeF), zakupy, zestawienie CSV. Zwraca ścieżkę do pliku tymczasowego. */
function books_zip(string $ym, ?array $sales = null, ?array $costs = null, ?array $att = null): string {
    require_once __DIR__ . '/doc.php';
    $tmp = tempnam(sys_get_temp_dir(), 'cvz'); $z = new ZipArchive();
    if ($z->open($tmp, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Nie mogę przygotować paczki ZIP.');
    $csv = ["\xEF\xBB\xBF" . 'Rodzaj;Numer;Data wystawienia;Kontrahent;NIP;Netto;VAT;Brutto;Waluta;Kurs NBP;Data kursu;Brutto PLN;Numer KSeF;Plik'];
    $num = fn(int $c) => number_format($c / 100, 2, ',', '');
    $used = [];
    $uniq = function (string $n) use (&$used): string { $b = $n; $i = 2; while (isset($used[$n])) { $n = preg_replace('/(\.\w+)$/', ' (' . $i++ . ')$1', $b); } $used[$n] = 1; return $n; };
    foreach ($sales ?? sales_of($ym) as $inv) {
        $f = $uniq('Sprzedaz/' . invoice_pdf_name($inv)); $z->addFromString($f, invoice_pdf($inv));
        if (!empty($inv['ksef'])) $z->addFromString($uniq('Sprzedaz/' . str_replace('.pdf', '.xml', invoice_pdf_name($inv))), ksef_xml($inv));
        $csv[] = implode(';', ['sprzedaż', $inv['number'], $inv['issue_date'], str_replace(';', ',', (string)($inv['buyer']['name'] ?? '')), (string)($inv['buyer']['nip'] ?? ''), $num((int)$inv['net']), $num((int)$inv['vat']), $num((int)$inv['gross']), $inv['currency'] ?? 'PLN', ...(($inv['currency'] ?? 'PLN') !== 'PLN' && ($r = nbp_rate_before((string)$inv['currency'], (string)$inv['issue_date'])) ? [number_format($r['rate'], 4, ',', ''), $r['date'], $num((int)round((int)$inv['gross'] * $r['rate']))] : ['', '', ($inv['currency'] ?? 'PLN') === 'PLN' ? $num((int)$inv['gross']) : '']), (string)$inv['ksef'], $f]);
    }
    foreach ($costs ?? costs_of($ym) as $c) {
        $ext = pathinfo((string)$c['path'], PATHINFO_EXTENSION);
        $nm = $c['seller'] || $c['number'] ? safe_name(trim($c['seller'] . ' ' . $c['number'])) . '.' . $ext : (string)$c['fname'];
        if (!str_ends_with(strtolower($nm), '.' . $ext)) $nm .= '.' . $ext;
        $f = $uniq('Zakupy/' . $nm);
        if (is_file(COST_DIR . '/' . $c['path'])) $z->addFile(COST_DIR . '/' . $c['path'], $f);
        $csv[] = implode(';', ['zakup', (string)$c['number'], (string)$c['issue_date'], str_replace(';', ',', (string)$c['seller']), (string)$c['nip'], '', '', $c['gross'] !== null ? $num((int)$c['gross']) : '', (string)$c['currency'], '', '', '', (string)$c['ksef'], $f]);
    }
    $kinds = books_att_kinds($ym);
    foreach ($att ?? array_keys($kinds) as $k) if (isset($kinds[$k]) && ($a = books_att($ym, $k))) $z->addFile(COST_DIR . '/' . $a['path'], $uniq('Potwierdzenia/' . $kinds[$k][3] . '_' . $ym . '.' . pathinfo($a['path'], PATHINFO_EXTENSION)));
    $z->addFromString('Zestawienie_' . $ym . '.csv', implode("\r\n", $csv) . "\r\n");
    $z->close();
    return $tmp;
}
function books_zip_name(string $ym): string { return 'Creative_Vision_dokumenty_' . $ym . '.zip'; }

/** Okrągła flaga waluty (20 px). */
function cur_flag(string $cu): string {
    $in = match ($cu) {
        'PLN' => '<rect width="20" height="10" fill="#fff"/><rect y="10" width="20" height="10" fill="#DC143C"/>',
        'USD' => '<rect width="20" height="20" fill="#fff"/>' . implode('', array_map(fn($i) => '<rect y="' . ($i * 20 / 7) . '" width="20" height="' . (20 / 13) . '" fill="#B22234"/>', range(0, 6))) . '<rect width="10" height="' . (140 / 13) . '" fill="#3C3B6E"/>',
        'EUR' => '<rect width="20" height="20" fill="#003399"/>' . implode('', array_map(fn($a) => '<circle cx="' . round(10 + 5.6 * sin(deg2rad($a)), 2) . '" cy="' . round(10 - 5.6 * cos(deg2rad($a)), 2) . '" r=".9" fill="#FFCC00"/>', range(0, 330, 30))),
        'GBP' => '<rect width="20" height="20" fill="#012169"/><path d="M0 0l20 20M20 0L0 20" stroke="#fff" stroke-width="4"/><path d="M10 0v20M0 10h20" stroke="#fff" stroke-width="6"/><path d="M10 0v20M0 10h20" stroke="#C8102E" stroke-width="3.5"/>',
        default => '<rect width="20" height="20" fill="#E6E4DF"/>',
    };
    return '<svg class="flag" viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><clipPath id="fc' . $cu . '"><circle cx="10" cy="10" r="10"/></clipPath><g clip-path="url(#fc' . $cu . ')">' . $in . '</g><circle cx="10" cy="10" r="9.75" fill="none" stroke="rgba(0,0,0,.12)" stroke-width=".5"/></svg>';
}
/** Netto faktury w złotych (waluta po kursie NBP z dnia roboczego przed wystawieniem). */
function inv_net_pln(array $i): ?int {
    $cu = $i['currency'] ?? 'PLN'; if ($cu === 'PLN') return (int)$i['net'];
    $r = nbp_rate_before((string)$cu, (string)$i['issue_date']); return $r ? (int)round((int)$i['net'] * $r['rate']) : null;
}
/** Szacunek ryczałtu i składek za miesiąc. Kwoty w groszach. */
function books_tax(string $ym): array {
    $rate = (float)str_replace(',', '.', setting('tax_rate') ?: '12'); $zus = (int)setting('zus_social');
    $rev = 0; $ok = true; foreach (sales_of($ym) as $i) { $v = inv_net_pln($i); if ($v === null) $ok = false; else $rev += $v; }
    $ytd = 0; $y = substr($ym, 0, 4);
    for ($m = 1; $m <= (int)substr($ym, 5, 2); $m++) foreach (sales_of(sprintf('%s-%02d', $y, $m)) as $i) $ytd += (int)inv_net_pln($i);
    // składka zdrowotna na ryczałcie 2026: 60% / 100% / 180% przeciętnego wynagrodzenia (9 228,64 zł)
    [$health, $tier] = $ytd <= 6000000 ? [49835, 'przychód w roku do 60 tys. zł'] : ($ytd <= 30000000 ? [83058, 'przychód w roku 60 do 300 tys. zł'] : [149504, 'przychód w roku ponad 300 tys. zł']);
    $base = max(0, $rev - $zus - intdiv($health, 2)); $base = (int)round($base / 100) * 100;
    $tax = (int)round($base * $rate / 100 / 100) * 100;
    $pay = $tax + $health + $zus;
    return ['rate' => $rate, 'rev' => $rev, 'ok' => $ok, 'ytd' => $ytd, 'health' => $health, 'tier' => $tier, 'zus' => $zus, 'base' => $base, 'tax' => $tax, 'pay' => $pay, 'left' => max(0, $rev - $pay), 'due' => date('Y-m-20', strtotime($ym . '-01 +1 month'))];
}
function books_tax_html(string $ym): string {
    $t = books_tax($ym); $rs = rtrim(rtrim(number_format($t['rate'], 2, ',', ''), '0'), ',');
    if ($t['rev'] <= 0) return '<div class="tx"><span class="tx-k">Szacowany podatek</span><span class="tx-e">Brak sprzedaży w tym miesiącu, więc ryczałtu nie ma. Składka zdrowotna ' . money($t['health']) . ' i tak do zapłaty.</span></div>';
    $seg = [['tax', 'Ryczałt ' . $rs . '%', $t['tax'], 'od ' . money($t['base']) . ' po odliczeniu składek'], ['hl', 'Składka zdrowotna', $t['health'], $t['tier']]];
    if ($t['zus']) $seg[] = ['zus', 'ZUS społeczny', $t['zus'], 'z ustawień'];
    $bar = ''; $rows = '';
    foreach ($seg as [$c, $l, $v, $s]) { $bar .= '<i class="' . $c . '" style="flex:' . max(1, $v) . '"></i>'; $rows .= '<li><span class="d ' . $c . '"></span><span class="l">' . e($l) . '<small>' . e($s) . '</small></span><b>' . money($v) . '</b></li>'; }
    $bar .= '<i class="lf" style="flex:' . max(1, $t['left']) . '"></i>';
    $rows .= '<li class="sum"><span class="d lf"></span><span class="l">Zostaje z przychodu<small>z ' . money($t['rev']) . ' netto' . ($t['ok'] ? '' : ', część faktur czeka na kurs NBP') . '</small></span><b>' . money($t['left']) . '</b></li>';
    return '<div class="tx"><div class="tx-h"><span class="tx-k">Szacowany podatek i składki</span><span class="tx-v">' . money($t['pay']) . '</span><span class="tx-s">do zapłaty do ' . e(date('d.m.Y', strtotime($t['due']))) . '</span></div>'
        . '<div class="tx-bar" aria-hidden="true">' . $bar . '</div><ul class="tx-l">' . $rows . '</ul>'
        . '<details class="tx-ed"><summary>Stawka ' . $rs . '%' . ($t['zus'] ? ', ZUS ' . money($t['zus']) : '') . ' · zmień</summary><form method="post" action="' . P . '?a=books_tax">' . csrf_field() . '<input type="hidden" name="ym" value="' . $ym . '">'
        . '<label>Stawka ryczałtu<select class="in" name="tax_rate">' . implode('', array_map(fn($r) => '<option value="' . $r . '"' . ((string)$r === (string)$t['rate'] || (float)$r === $t['rate'] ? ' selected' : '') . '>' . str_replace('.', ',', $r) . '%</option>', ['2', '3', '5.5', '8.5', '10', '12', '14', '15', '17'])) . '</select></label>'
        . '<label>ZUS społeczny miesięcznie<input class="in" name="zus_social" inputmode="decimal" placeholder="0,00" value="' . ($t['zus'] ? number_format($t['zus'] / 100, 2, ',', '') : '') . '"></label><button class="btn sm">Zapisz</button></form></details>'
        . '<span class="tx-n">Szacunek. Ryczałt liczony od przychodu netto, po odjęciu ZUS społecznego i połowy składki zdrowotnej. Ostateczną kwotę potwierdza księgowa.</span></div>';
}
/** Potwierdzenia przelewów dołączane do paczki. */
function books_att_kinds(string $ym): array {
    $fx = array_values(array_unique(array_map(fn($i) => preg_replace('/,?\s*(Inc|LLC|Ltd|GmbH)\.?$/i', '', (string)($i['buyer']['name'] ?? 'klienta')), array_filter(sales_of($ym), fn($i) => ($i['currency'] ?? 'PLN') !== 'PLN'))));
    $k = [];
    if ($fx) $k['bank'] = ['Potwierdzenie przelewu od ' . implode(', ', $fx), 'Księgowa sprawdza na nim kurs z dnia wpływu.', true, 'Potwierdzenie_przelewu_' . safe_name(implode('_', $fx))];
    $k['zus'] = ['Potwierdzenie przelewu do ZUS', 'Opcjonalnie, jeśli księgowa ma je mieć w paczce.', false, 'Potwierdzenie_ZUS'];
    return $k;
}
function books_att(string $ym, string $k): ?array { $j = json_decode(setting('acc_att_' . $ym . '_' . $k), true); return is_array($j) && !empty($j['path']) && is_file(COST_DIR . '/' . $j['path']) ? $j : null; }
/** Brakujące wymagane potwierdzenia (etykiety). */
function books_att_missing(string $ym): array { $m = []; foreach (books_att_kinds($ym) as $k => $d) if ($d[2] && !books_att($ym, $k)) $m[] = $d[0]; return $m; }
/** Potwierdzenia dodane po wysłaniu paczki. */
function books_new_att(string $ym): array {
    if (setting('acc_sent_' . $ym) === '') return [];
    $sent = (array)(json_decode(setting('acc_ids_' . $ym), true)['a'] ?? []);
    return array_values(array_filter(array_keys(books_att_kinds($ym)), fn($k) => books_att($ym, $k) && !in_array($k, $sent, true)));
}
/** Dokumenty, które poszły już do księgowej: ['s' => [id faktur], 'c' => [id kosztów]]. */
function books_sent_ids(string $ym): array {
    $j = json_decode(setting('acc_ids_' . $ym), true);
    if (is_array($j)) return ['s' => array_map('intval', $j['s'] ?? []), 'c' => array_map('intval', $j['c'] ?? [])];
    $at = setting('acc_sent_' . $ym); if ($at === '') return ['s' => [], 'c' => []];
    // paczki wysłane, zanim zaczęliśmy zapisywać listę: wszystko, co powstało do chwili wysyłki
    return ['s' => array_values(array_map(fn($i) => (int)$i['id'], array_filter(sales_of($ym), fn($i) => (string)$i['created'] <= $at && (string)($i['issue_date'] ?? '') <= substr($at, 0, 10)))), 'c' => array_values(array_map(fn($c) => (int)$c['id'], array_filter(costs_of($ym), fn($c) => (string)$c['created'] <= $at)))];
}
/** Faktury sprzedażowe i zakupowe dodane po wysłaniu paczki. */
function books_new(string $ym): array {
    if (setting('acc_sent_' . $ym) === '') return [[], []];
    $ids = books_sent_ids($ym);
    return [array_values(array_filter(sales_of($ym), fn($i) => !in_array((int)$i['id'], $ids['s'], true))), array_values(array_filter(costs_of($ym), fn($c) => !in_array((int)$c['id'], $ids['c'], true)))];
}
/** Wysyła paczkę do księgowej, a po wysyłce tylko dosyła nowe dokumenty. Zwraca [ok, komunikat]. */
function books_send(string $ym): array {
    $to = trim(setting('acc_email'));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, 'Wpisz e-mail księgowej.'];
    if (ksef_enabled()) {
        try { [, , $left] = ksef_fetch_costs($ym); } catch (Throwable $e) { $left = 0; /* wyślemy to, co mamy */ }
        if ($left) return [false, 'KSeF jeszcze nie oddał ' . $left . ' ' . plural($left, 'faktury', 'faktur', 'faktur') . ' (limit pobrań). Spróbuj za minutę, żeby paczka była kompletna.', 'busy'];
    }
    $sup = setting('acc_sent_' . $ym) !== '';
    if ($miss = books_att_missing($ym)) return [false, 'Dodaj ' . mb_strtolower(implode(', ', $miss)) . '. Księgowa sprawdza na nim kurs z dnia wpływu, bez tego paczka nie wyjdzie.'];
    if ($sup) { $prevIds = books_sent_ids($ym); [$sales, $costs] = books_new($ym); $att = books_new_att($ym); if (!$sales && !$costs && !$att) return [false, 'Księgowa ma już wszystkie dokumenty za ' . ym_label($ym) . '.']; }
    else { $sales = sales_of($ym); $costs = costs_of($ym); $att = array_values(array_filter(array_keys(books_att_kinds($ym)), fn($k) => books_att($ym, $k))); }
    $kinds = books_att_kinds($ym); $attRows = array_map(fn($k) => [$kinds[$k][0], 'w załączniku'], $att);
    $zip = books_zip($ym, $sales, $costs, $att); $data = (string)file_get_contents($zip); @unlink($zip);
    if (strlen($data) > 20 * 1048576) return [false, 'Paczka ma ponad 20 MB, za dużo na maila. Pobierz ją i wyślij przez dysk.'];
    $label = ym_label($ym);
    $sum = 0; $byCur = []; $plnAll = 0; $plnOk = true;
    foreach ($sales as $i) {
        $cu = $i['currency'] ?? 'PLN'; $byCur[$cu] = ($byCur[$cu] ?? 0) + (int)$i['gross'];
        if ($cu === 'PLN') { $sum += (int)$i['gross']; $plnAll += (int)$i['gross']; }
        elseif ($r = nbp_rate_before($cu, (string)$i['issue_date'])) $plnAll += (int)round((int)$i['gross'] * $r['rate']); else $plnOk = false;
    }
    $saleRows = [];
    foreach ($byCur ?: ['PLN' => 0] as $cu => $v) $saleRows[] = ['Sprzedaż brutto' . (count($byCur) > 1 ? ' (' . $cu . ')' : ''), money($v, $cu), count($byCur) <= 1];
    if (array_diff(array_keys($byCur), ['PLN'])) { foreach ($saleRows as &$sr) $sr[2] = false; unset($sr); $saleRows[] = ['Razem w złotych (kurs NBP z dnia roboczego przed wystawieniem)', ($plnOk ? '' : 'ok. ') . money($plnAll), true]; }
    $subj = ($sup ? 'Uzupełnienie dokumentów Creative Vision za ' : 'Dokumenty Creative Vision za ') . $label;
    $rows = m_kicker('Dokumenty za ' . $label) . m_h1($sup ? 'Uzupełnienie paczki' : 'Paczka dla księgowej', ucfirst($label))
        . m_text('Dzień dobry,<br><br>' . ($sup ? 'dosyłam dokumenty za ' . e($label) . ', które doszły już po wysłaniu paczki. W załączniku są tylko te nowe faktury i ich zestawienie w pliku CSV.' : 'w załączniku przesyłam dokumenty za ' . e($label) . ': faktury sprzedażowe w PDF (i XML z KSeF, jeśli mają numer KSeF), faktury zakupowe oraz zestawienie w pliku CSV.') . (trim(setting('acc_note')) !== '' ? '<br><br>' . nl2br(e(setting('acc_note'))) : ''))
        . m_rows('W paczce', [['Faktury sprzedażowe', (string)count($sales)], ['Faktury zakupowe', (string)count($costs)], ...$attRows, ...$saleRows])
        . m_sign();
    $ok = send_mail([$to], $subj, mail_layout($subj, 'Dokumenty za ' . $label . ': ' . count($sales) . ' sprzedaży, ' . count($costs) . ' zakupów.', $rows), html_to_text($rows), [($sup ? str_replace('.zip', '_uzupelnienie.zip', books_zip_name($ym)) : books_zip_name($ym)) => $data], true);
    if ($ok) {
        $ids = $sup ? $prevIds : ['s' => [], 'c' => []]; $prevA = $sup ? (array)(json_decode(setting('acc_ids_' . $ym), true)['a'] ?? []) : [];
        foreach ($sales as $i) $ids['s'][] = (int)$i['id']; foreach ($costs as $c) $ids['c'][] = (int)$c['id'];
        set_setting('acc_ids_' . $ym, json_encode(['s' => array_values(array_unique($ids['s'])), 'c' => array_values(array_unique($ids['c'])), 'a' => array_values(array_unique(array_merge($prevA, $att)))]));
        if ($sup) set_setting('acc_sup_' . $ym, now()); else set_setting('acc_sent_' . $ym, now());
    }
    $n = count($sales) + count($costs) + count($att);
    return [$ok, $ok ? ($sup ? 'Dosłane do ' . $to . ': ' . $n . ' ' . plural($n, 'nowy dokument', 'nowe dokumenty', 'nowych dokumentów') . ' za ' . $label . '.' : 'Paczka za ' . $label . ' wysłana do ' . $to . '.') : 'Nie udało się wysłać: ' . mail_last_error()];
}

/** Raz w miesiącu, od wybranego dnia, sama wysyła paczkę za poprzedni miesiąc. */
function run_books_auto(): ?string {
    if (setting('acc_auto') !== '1' || !filter_var(trim(setting('acc_email')), FILTER_VALIDATE_EMAIL)) return null;
    if ((int)date('j') < max(1, min(28, (int)setting('acc_day')))) return null;
    $ym = prev_ym();
    if (setting('acc_sent_' . $ym) !== '' || (int)setting('acc_try_' . $ym) > time() - 20 * 3600) return null;
    set_setting('acc_try_' . $ym, (string)time());
    $r = books_send($ym); [$ok, $msg] = $r;
    if (!$ok && ($r[2] ?? '') === 'busy') { set_setting('acc_try_' . $ym, (string)(time() - 19 * 3600)); return $msg; }
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
        case 'cost_manual':
            $g = (int)round((float)str_replace([' ', ','], ['', '.'], (string)($_POST['gross'] ?? '0')) * 100); $rate = (string)($_POST['rate'] ?? '23');
            $seller = trim(mb_substr((string)($_POST['seller'] ?? ''), 0, 200));
            if ($seller === '' || $g <= 0) { flash('!Podaj sprzedawcę i kwotę brutto.'); redirect($back); }
            $vat = is_numeric($rate) ? (int)round($g - $g / (1 + (int)$rate / 100)) : 0;
            $d = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['issue_date'] ?? '')) ? (string)$_POST['issue_date'] : null;
            db()->prepare('INSERT INTO costs (ym, src, fname, mime, path, size, seller, number, issue_date, gross, currency, created, net, vat) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$ym, 'manual', trim(mb_substr((string)($_POST['what'] ?? ''), 0, 200)) ?: 'Faktura bez pliku', null, '', 0, $seller, trim((string)($_POST['number'] ?? '')) ?: null, $d, $g, 'PLN', now(), $g - $vat, $vat]);
            flash('Dodano fakturę od ' . $seller . ' za ' . ym_label($ym) . '. Plik możesz dograć później.'); redirect($back);
        case 'cost_edit':
            $c = cost_by_id((int)($_POST['id'] ?? 0));
            if ($c) db()->prepare('UPDATE costs SET seller = ?, number = ?, gross = ? WHERE id = ?')->execute([trim((string)($_POST['seller'] ?? '')) ?: null, trim((string)($_POST['number'] ?? '')) ?: null, trim((string)($_POST['gross'] ?? '')) !== '' ? to_cents((string)$_POST['gross']) : null, $c['id']]);
            redirect($back);
        case 'books_ksef':
            try { [$new, $all, $left] = ksef_fetch_costs($ym); flash($all ? 'KSeF: ' . $all . ' ' . plural($all, 'faktura zakupowa', 'faktury zakupowe', 'faktur zakupowych') . ' za ' . ym_label($ym) . ', pobrane teraz: ' . $new . '.' . ($left ? ' Jeszcze ' . $left . ' czeka, bo KSeF ogranicza liczbę pobrań na minutę. Kliknij „Pobierz z KSeF” ponownie za minutę.' : ' Wszystkie są w panelu.') : 'W KSeF nie ma faktur zakupowych za ' . ym_label($ym) . '.'); }
            catch (Throwable $e) { flash('!' . $e->getMessage()); }
            redirect($back);
        case 'books_tax':
            $r = (string)($_POST['tax_rate'] ?? '12'); if (in_array($r, ['2', '3', '5.5', '8.5', '10', '12', '14', '15', '17'], true)) set_setting('tax_rate', $r);
            set_setting('zus_social', (string)max(0, (int)round((float)str_replace([' ', ','], ['', '.'], (string)($_POST['zus_social'] ?? '0')) * 100)));
            flash('Zapisane. Podatek przeliczony.'); redirect($back);
        case 'books_att':
            $k = (string)($_POST['k'] ?? ''); $kinds = books_att_kinds($ym); if (!isset($kinds[$k])) redirect($back);
            if (!empty($_POST['del'])) { if ($a = books_att($ym, $k)) @unlink(COST_DIR . '/' . $a['path']); set_setting('acc_att_' . $ym . '_' . $k, ''); flash('Usunięto: ' . $kinds[$k][0] . '.'); redirect($back); }
            $f = $_FILES['file'] ?? null;
            if (!$f || ($f['error'] ?? 4) !== UPLOAD_ERR_OK) { flash('!Plik nie dotarł (za duży?).'); redirect($back); }
            $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION)); if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'heic', 'webp'], true)) { flash('!Dodaj PDF albo zdjęcie.'); redirect($back); }
            if ($old = books_att($ym, $k)) @unlink(COST_DIR . '/' . $old['path']);
            $dir = COST_DIR . '/' . $ym; if (!is_dir($dir)) mkdir($dir, 0700, true);
            $path = $ym . '/att-' . $k . '-' . bin2hex(random_bytes(8)) . '.' . $ext; move_uploaded_file($f['tmp_name'], COST_DIR . '/' . $path);
            set_setting('acc_att_' . $ym . '_' . $k, json_encode(['path' => $path, 'fname' => safe_name((string)$f['name']), 'mime' => COST_TYPES[$ext], 'at' => now()]));
            flash('Dodano: ' . $kinds[$k][0] . '.'); redirect($back);
        case 'books_send':
            [$ok, $msg] = books_send($ym); flash(($ok ? '' : '!') . $msg); redirect($back);
        case 'books_settings':
            set_setting('acc_email', trim(mb_substr((string)($_POST['acc_email'] ?? ''), 0, 200)));
            set_setting('acc_auto', !empty($_POST['acc_auto']) ? '1' : '0');
            set_setting('acc_day', (string)max(1, min(28, (int)($_POST['acc_day'] ?? 5))));
            set_setting('acc_note', trim(mb_substr((string)($_POST['acc_note'] ?? ''), 0, 1000)));
            flash('Ustawienia księgowej zapisane.'); redirect(($_POST['from'] ?? '') === 'settings' ? P . '?a=settings#acc' : $back);
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
        $mp .= '<a href="' . P . '?a=books&ym=' . $k . '" class="' . trim(($i === $mm ? 'on' : '') . (!empty($has[$i]) ? ' has' : '') . ($fut ? ' fut' : '') . ($k === date('Y-m') ? ' now' : '')) . '" title="' . pl_month($i) . ' ' . $yy . '"' . ($i === $mm ? ' aria-current="true"' : '') . '><b>' . sprintf('%02d', $i) . '</b><span>' . pl_mshort($i) . '</span></a>'; }
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
    $plnS = 0; $fxS = 0; $netS = 0; $fxOk = true; $rts = []; $fxAmt = [];
    foreach ($sales as $i) {
        $cu = $i['currency'] ?? 'PLN';
        if ($cu === 'PLN') { $plnS += (int)$i['gross']; $netS += (int)$i['net']; continue; }
        $fxAmt[$cu] = ($fxAmt[$cu] ?? 0) + (int)$i['gross'];
        if ($r = nbp_rate_month_end($cu, $ym)) { $fxS += (int)round((int)$i['gross'] * $r['rate']); $netS += (int)round((int)$i['net'] * $r['rate']); $rts[$cu] = number_format($r['rate'], 4, ',', '') . (!empty($r['live']) ? ', dziś' : ' z ' . date('d.m', strtotime($r['date']))); } else $fxOk = false;
    }
    $saleKpi = $fxAmt && $fxOk
        ? '<span class="v">' . money_split($plnS + $fxS) . '</span><span class="fxb">' . ($plnS ? money($plnS) . ' + ' : '') . money($fxS) . ' <span class="fxr">(kurs ' . e(implode(', ', $rts)) . ')</span></span>'
        : '<span class="v">' . money_split($plnS) . '</span>' . implode('', array_map(fn($k, $v) => '<span class="fxb">+ ' . money($v, $k) . '</span>', array_keys($fxAmt), $fxAmt));
    $saleKpi .= '<span class="s">netto ' . money($netS) . ' · ' . count($sales) . ' ' . plural(count($sales), 'faktura', 'faktury', 'faktur') . '</span>';
    $cG = 0; $cN = 0; $cV = 0; $cUnk = 0;
    foreach ($costs as $c) { if (($c['currency'] ?: 'PLN') !== 'PLN') continue; $cG += (int)$c['gross']; if ($c['vat'] === null) { $cUnk++; $cN += (int)$c['gross']; } else { $cV += (int)$c['vat']; $cN += (int)($c['net'] ?? ((int)$c['gross'] - (int)$c['vat'])); } }
    $vatOut = array_sum(array_map(fn($i) => ($i['currency'] ?? 'PLN') === 'PLN' ? (int)$i['vat'] : 0, $sales)); $vatDue = $vatOut - $cV;
    $costKpi = '<div class="kpi"><span class="t">Koszty: ' . e(ym_label($ym)) . '</span><span class="v">' . money_split($cG) . '</span><span class="fxb">netto ' . money($cN) . ' + VAT ' . money($cV) . '</span><span class="s">' . count($costs) . ' ' . plural(count($costs), 'faktura zakupowa', 'faktury zakupowe', 'faktur zakupowych') . ($cUnk ? ', ' . $cUnk . ' bez danych VAT (PDF lub zdjęcie)' : '') . '</span></div>'
        . '<div class="kpi"><span class="t">VAT za ' . e(ym_label($ym)) . '</span><span class="v' . ($vatDue < 0 ? ' ok-v' : '') . '">' . money_split(abs($vatDue)) . '</span><span class="fxb">należny ' . money($vatOut) . ' − do odliczenia ' . money($cV) . '</span><span class="s">' . ($vatDue >= 0 ? 'do zapłaty do 25.' . date('m', strtotime($ym . '-01 +1 month')) : 'nadwyżka do przeniesienia lub zwrotu') . '. Szacunek, odliczenie przy autach często tylko 50%.</span></div>';
    $sumHtml = $sum ? implode('', array_map(fn($k, $v) => '<span class="cur">' . cur_flag($k) . str_replace('zł', 'PLN', money($v, $k)) . '</span>', array_keys($sum), $sum)) : '<span class="cur">' . cur_flag('PLN') . str_replace('zł', 'PLN', money(0)) . '</span>';
    $ctr = '';
    foreach ($costs as $c) {
        $url = P . '?a=cost&id=' . $c['id'];
        $ctr .= '<tr class="row" data-href="' . $url . '"><td class="c-num nw"><span class="num" style="border:0">' . ($c['src'] === 'ksef' ? 'KSeF' : ($c['src'] === 'manual' ? 'Ręcznie' : strtoupper(pathinfo((string)$c['path'], PATHINFO_EXTENSION)))) . '</span></td>'
            . '<td class="c-cli"><span>' . e($c['seller'] ?: $c['fname']) . '</span><span class="sub">' . e($c['number'] ?: ($c['src'] === 'manual' ? (string)$c['fname'] : ($c['seller'] ? '' : 'dodano ' . date('d.m', strtotime($c['created']))))) . '</span></td>'
            . '<td class="c-due nw">' . e($c['issue_date'] ? pl_date($c['issue_date']) : '') . '</td><td class="c-amt r amt">' . ($c['gross'] !== null ? money((int)$c['gross'], $c['currency'] ?: 'PLN') : '') . '</td><td class="c-st"></td>'
            . '<td class="c-act r nw"><form method="post" action="' . P . '?a=cost_delete">' . csrf_field() . '<input type="hidden" name="id" value="' . $c['id'] . '"><button class="link" style="color:var(--red)">Usuń</button></form></td></tr>';
    }
    $sent = setting('acc_sent_' . $ym); $kAt = setting('books_ksef_' . $ym);
    $drop = '<form class="drop" id="drop" method="post" action="' . P . '?a=books_upload" enctype="multipart/form-data">' . csrf_field() . '<input type="hidden" name="ym" value="' . $ym . '">'
        . '<input type="file" name="files[]" id="dropin" multiple accept="application/pdf,image/*,.pdf,.jpg,.jpeg,.png,.heic,.xml">'
        . '<label for="dropin">' . icon('clip') . '<b>Przeciągnij tu faktury zakupowe</b><span>albo kliknij, żeby wybrać pliki lub zrobić zdjęcie. PDF, JPG, PNG, XML.</span></label></form>'
        . '<details class="more cman"><summary>' . icon('plus') . ' Dodaj fakturę bez pliku</summary><form class="grid3" method="post" action="' . P . '?a=cost_manual">' . csrf_field() . '<input type="hidden" name="ym" value="' . $ym . '">'
        . '<label class="f">Sprzedawca<input class="in" name="seller" required placeholder="np. Autobajka Rzeszów"></label><label class="f">Za co<input class="in" name="what" placeholder="np. wymiana rozrządu"></label><label class="f">Numer faktury<input class="in" name="number" placeholder="opcjonalnie"></label>'
        . '<label class="f">Kwota brutto (zł)<input class="in" name="gross" inputmode="decimal" required placeholder="2200,00"></label><label class="f">VAT<select class="in" name="rate"><option value="23">23%</option><option value="8">8%</option><option value="5">5%</option><option value="0">0%</option><option value="zw">zw.</option></select></label><label class="f">Data wystawienia<input class="in" type="date" name="issue_date"></label>'
        . '<button class="btn">Dodaj</button></form></details>';
    $body = $nav
        . '<section class="kpis"><div class="kpi dark"><span class="t">Paczka za ' . e(ym_label($ym)) . '</span><span class="v">' . count($sales) . ' + ' . count($costs) . '</span><span class="s">' . count($sales) . ' ' . plural(count($sales), 'faktura sprzedażowa', 'faktury sprzedażowe', 'faktur sprzedażowych') . ', ' . count($costs) . ' ' . plural(count($costs), 'zakupowa', 'zakupowe', 'zakupowych') . '</span><span class="s acc-s">' . icon($sent ? 'check' : 'send') . ' ' . ($sent ? 'Wysłana do księgowej ' . e(date('d.m', strtotime($sent))) : 'Jeszcze nie wysłana' . (setting('acc_auto') === '1' && setting('acc_email') !== '' ? ', sama wyjdzie ' . (int)setting('acc_day') . '. dnia miesiąca' : '')) . '</span></div><div class="kpis-r">'
        . '<div class="kpi"><span class="t">Sprzedaż: ' . e(ym_label($ym)) . '</span>' . $saleKpi . '</div>' . $costKpi . '</div></section>'
        . '<div class="cols"><div class="wide sec">'
        . '<section class="card"><div class="hrow pad" style="padding-bottom:12px"><h2>Zakupy</h2>' . (ksef_enabled() ? '<form method="post" action="' . P . '?a=books_ksef">' . csrf_field() . '<input type="hidden" name="ym" value="' . $ym . '"><button class="btn ghost sm" data-busy>' . icon('refresh') . ' Pobierz z KSeF</button></form>' : '') . '</div>'

        . '<div class="pad" style="padding-top:0">' . $drop . ($kAt ? '<span class="mut" style="font-size:13px;display:block;margin-top:8px">Ostatnio sprawdzone w KSeF: ' . e(date('d.m.Y H:i', strtotime($kAt))) . '</span>' : (ksef_enabled() ? '' : '<span class="mut" style="font-size:13px;display:block;margin-top:8px">Żeby pobierać faktury zakupowe z KSeF, wpisz token w <a href="' . P . '?a=settings#ksef">ustawieniach</a>.</span>')) . '</div>'
        . ($ctr ? '<div class="tw"><table class="list"><tbody>' . $ctr . '</tbody></table></div>' : '<div class="empty" style="padding-top:8px">Brak faktur zakupowych za ten miesiąc.</div>') . '</section>'
        . '<section class="card"><div class="hrow pad" style="padding-bottom:12px"><h2>Sprzedaż</h2><span class="mut" style="font-size:14px">' . e($sumTxt) . '</span></div>'
        . ($str ? '<div class="tw"><table class="list"><tbody>' . $str . '</tbody></table></div>' : '<div class="empty" style="padding-top:8px">Brak faktur sprzedażowych w tym miesiącu księgowym.</div>') . '</section>'
        . '</div><aside class="side">'
        . '<section class="card pad sec"><h2>Dla księgowej</h2><span class="mut" style="font-size:14px">Jeden ZIP: sprzedaż w PDF i XML z KSeF, zakupy i zestawienie CSV.</span>'
        . (function () use ($ym) {
            $o = '<div class="att-l">';
            foreach (books_att_kinds($ym) as $k => [$lbl, $hint, $req]) {
                $a = books_att($ym, $k);
                $o .= '<div class="att' . ($a ? ' has' : ($req ? ' req' : '')) . '"><div class="att-h"><b>' . e($lbl) . '</b><span class="att-c">' . ($a ? icon('check') . ' Dodane' : ($req ? 'Wymagane' : 'Opcjonalne')) . '</span></div>'
                    . ($a ? '<div class="att-f"><a href="' . P . '?a=books_att_file&ym=' . $ym . '&k=' . $k . '" target="_blank" rel="noopener">' . icon('clip') . ' ' . e($a['fname']) . '</a><form method="post" action="' . P . '?a=books_att">' . csrf_field() . '<input type="hidden" name="ym" value="' . $ym . '"><input type="hidden" name="k" value="' . $k . '"><input type="hidden" name="del" value="1"><button class="link" style="color:var(--red)">Usuń</button></form></div>'
                        : '<span class="att-s">' . e($hint) . '</span><form method="post" action="' . P . '?a=books_att" enctype="multipart/form-data">' . csrf_field() . '<input type="hidden" name="ym" value="' . $ym . '"><input type="hidden" name="k" value="' . $k . '"><label class="btn ghost sm att-up">' . icon('clip') . ' Dodaj plik<input type="file" name="file" accept="application/pdf,image/*" hidden onchange="this.form.submit()"></label></form>')
                    . '</div>';
            }
            return $o . '</div>';
        })()
        . '<a class="btn ghost" href="' . P . '?a=books_zip&ym=' . $ym . '">Pobierz paczkę ZIP</a>'
        . (function () use ($ym, $sent) {
            if (!$sent && setting('acc_email') === '') return '<span class="mut" style="font-size:14px">Żeby wysłać paczkę, wpisz e-mail księgowej w <a href="' . P . '?a=settings#acc">Ustawieniach</a>.</span>';
            if (!$sent) return '<form method="post" action="' . P . '?a=books_send">' . csrf_field() . '<input type="hidden" name="ym" value="' . $ym . '"><button class="btn" style="width:100%"' . (setting('acc_email') === '' || books_att_missing($ym) ? ' disabled title="Najpierw dodaj wymagane potwierdzenie"' : '') . '>Wyślij do księgowej ' . icon('arrow') . '</button></form>';
            $miss = books_att_missing($ym); if ($miss) return '<span class="att-warn">' . icon('clip') . ' Paczka poszła ' . e(date('d.m', strtotime($sent))) . '. Dodaj wyżej ' . e(mb_strtolower(implode(', ', $miss))) . ', a pojawi się przycisk Doślij.</span>';
            [$ns, $nc] = books_new($ym); $na = books_new_att($ym); $n = count($ns) + count($nc) + count($na); $sup = setting('acc_sup_' . $ym);
            return ($n ? '<form method="post" action="' . P . '?a=books_send">' . csrf_field() . '<input type="hidden" name="ym" value="' . $ym . '"><button class="btn" style="width:100%">Doślij ' . $n . ' ' . plural($n, 'nowy dokument', 'nowe dokumenty', 'nowych dokumentów') . ' ' . icon('arrow') . '</button></form><span class="mut" style="font-size:13px">Doszły po wysłaniu paczki: ' . e(implode(', ', array_merge(array_map(fn($i) => (string)$i['number'], $ns), array_map(fn($c) => trim((string)($c['number'] ?: $c['fname'])), $nc)))) . '</span>' : '')
                . '<span class="ok-t" style="font-size:14px">' . icon('check') . ' Wysłana ' . e(date('d.m.Y H:i', strtotime($sent))) . ' do ' . e(setting('acc_email')) . ($sup ? ', uzupełnienie ' . e(date('d.m H:i', strtotime($sup))) : '') . '</span>'
                . ($n ? '' : '<span class="mut" style="font-size:13px">Jeśli dojdzie jeszcze jakaś faktura za ten miesiąc, pojawi się tu przycisk, żeby ją dosłać.</span>');
        })() . '</section>'
        . '</aside></div>';
    page('Księgowość', 'books', $body, 'Miesiąc księgowy: ' . ym_label($ym));
}

/** Ustawienia księgowej (w Ustawieniach). */
function books_settings_section(): string {
    $ym = prev_ym();
    return '<section class="card pad sec" id="acc"><h2>Księgowa</h2><form class="inl" method="post" action="' . P . '?a=books_settings">' . csrf_field() . '<input type="hidden" name="from" value="settings"><input type="hidden" name="ym" value="' . $ym . '">'
        . '<label class="f">E-mail księgowej<input class="in" type="email" name="acc_email" value="' . e(setting('acc_email')) . '" placeholder="biuro@ksiegowa.pl"></label>'
        . '<label class="check"><input type="checkbox" name="acc_auto" value="1"' . (setting('acc_auto') === '1' ? ' checked' : '') . '> Wysyłaj paczkę za poprzedni miesiąc automatycznie</label>'
        . '<label class="f">Którego dnia miesiąca<input class="in" type="number" name="acc_day" min="1" max="28" value="' . e(setting('acc_day')) . '"></label>'
        . '<label class="f">Dopisek w mailu (opcjonalnie)<textarea class="in" name="acc_note" rows="3" placeholder="np. Proszę o potwierdzenie odbioru.">' . e(setting('acc_note')) . '</textarea></label>'
        . '<button class="btn ghost">Zapisz</button><span class="mut" style="font-size:13px">Przed wysyłką pobierzemy jeszcze faktury zakupowe z KSeF. Wysyłamy w dni robocze rano.</span></form></section>';
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
