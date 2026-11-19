<?php
// Szanse sprzedażowe: lejek, przetargi (BZP i TED) i skaner stron firmowych.
declare(strict_types=1);

const LEAD_STAGES = ['new' => 'Nowe', 'contact' => 'W kontakcie', 'offer' => 'Wycena wysłana', 'nego' => 'Odpowiedź', 'won' => 'Wygrane', 'lost' => 'Przegrane'];
const LEAD_SOURCES = ['reczne' => 'Dodana ręcznie', 'mail' => 'Zapytanie mailowe', 'przetarg' => 'Przetarg', 'gielda' => 'Giełda zleceń', 'zapytanie' => 'Zapytanie ofertowe UE', 'skaner' => 'Skaner stron', 'polecenie' => 'Polecenie'];
const TENDER_CATS = ['www' => 'Strony i portale', 'shop' => 'Sklepy', 'app' => 'Aplikacje i systemy', 'design' => 'Grafika i marketing', 'other' => 'Inne'];
/** Rodzaj przetargu po tytule i kodach CPV. */
function tender_title(string $t): string { return preg_match('/^Polska\s*[–-]\s*[^–]{3,120}\s*[–-]\s*(.+)$/u', $t, $m) ? trim($m[1]) : $t; }
function tender_cat(string $title, string $cpv): string {
    $t = mb_strtolower(tender_title($title));
    if (preg_match('/sklep|e-commerce|ecommerce|sprzedaż internetow|platform[a-z]* sprzeda/u', $t)) return 'shop';
    if (preg_match('/stron[a-ząęy]* (www|internetow)|serwis[a-ząęuó]* internetow|portal|\bbip\b|biuletyn informacji|www|witryn|strony internetowej|stron internetowych/u', $t) || str_contains($cpv, '72413')) return 'www';
    if (preg_match('/doposażeni|sprzęt|dysk(ów|i) |macierz|serwer|komputer|drukark|mebl|wyposażeni/u', $t) && !preg_match('/aplikacj|oprogramowan|stron/u', $t)) return 'other';
    if (preg_match('/aplikacj|oprogramowan|system[a-z]* (informatycz|teleinformatycz|zarządzan|do )|platform|e-usług|wdrożeni[ea] system|licencj|ezd|crm|erp|moduł/u', $t) || preg_match('/48000|72212|7226|7221|7223/', $cpv)) return 'app';
    if (preg_match('/graficzn|identyfikacj|logo|kampani|promocj|reklam|marketing|film|wideo|video|social media|fotograf|album|druk|broszur|ulotk/u', $t) || preg_match('/79822|79342|79341|92111|79961|22100/', $cpv)) return 'design';
    return 'other';
}
const TENDER_KW_DEFAULT = "stron internetow\nstrony internetowej\nserwis internetow\nserwisu internetowego\nportal\nsklep internetow\ne-commerce\naplikacj mobiln\naplikacji internetowej\nplatform\nBIP\nidentyfikacj wizualn\nprojekt graficzn\nUX\nUI\nwww";

function leads_migrate(): void {
    static $d = false; if ($d) return; $d = true;
    db()->exec('CREATE TABLE IF NOT EXISTS leads (id INTEGER PRIMARY KEY, name TEXT, company TEXT, email TEXT, phone TEXT, website TEXT, source TEXT NOT NULL DEFAULT \'reczne\',
        stage TEXT NOT NULL DEFAULT \'new\', value INTEGER, next_at TEXT, next_note TEXT, notes TEXT, score INTEGER, audit TEXT, src TEXT, quote_id INTEGER, created TEXT NOT NULL, updated TEXT NOT NULL)');
    db()->exec('CREATE TABLE IF NOT EXISTS tenders (id INTEGER PRIMARY KEY, src TEXT NOT NULL, ref TEXT UNIQUE NOT NULL, title TEXT, buyer TEXT, city TEXT, province TEXT, cpv TEXT,
        deadline TEXT, published TEXT, url TEXT, score INTEGER, status TEXT NOT NULL DEFAULT \'new\', lead_id INTEGER, created TEXT NOT NULL)');
    db()->exec("UPDATE tenders SET city = '' WHERE city = 'Array'");
    $tc = array_column(db()->query('PRAGMA table_info(tenders)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('body', $tc, true)) { db()->exec('ALTER TABLE tenders ADD COLUMN body TEXT'); db()->exec('ALTER TABLE tenders ADD COLUMN nip TEXT');
        db()->exec("UPDATE leads SET stage = 'new' WHERE stage = 'offer' AND quote_id IN (SELECT id FROM quotes WHERE status = 'draft')"); }
    db()->exec("UPDATE tenders SET status = 'review' WHERE status = 'saved'");
    if (setting('bk_clean_1') === '') { foreach (db()->query("SELECT id, title, cpv FROM tenders WHERE src = 'bk' AND status = 'new'")->fetchAll() as $r) if (tender_cat((string)$r['title'], (string)$r['cpv']) === 'other' && !preg_match('/stron|sklep|aplikac|platform|portal|serwis|system|oprogramow|ux|ui|www|internet|e-commerce|wzornicz/iu', (string)$r['title'])) db()->prepare("UPDATE tenders SET status = 'hidden' WHERE id = ?")->execute([$r['id']]); set_setting('bk_clean_1', '1'); }
}
function lead_by_id(int $id): ?array { leads_migrate(); $s = db()->prepare('SELECT * FROM leads WHERE id = ?'); $s->execute([$id]); $r = $s->fetch(); if (!$r) return null; foreach (['name', 'company', 'email', 'phone', 'website', 'next_note', 'notes', 'source', 'stage'] as $k) $r[$k] = (string)($r[$k] ?? ''); $r['audit'] = json_decode((string)$r['audit'], true) ?: []; $r['src'] = json_decode((string)$r['src'], true) ?: []; return $r; }
function lead_chip(string $st): string { $c = ['new' => 'draft', 'contact' => 'due', 'offer' => 'sent', 'nego' => 'today', 'won' => 'paid', 'lost' => 'cancelled'][$st] ?? 'due'; return '<span class="chip ' . $c . '">' . (LEAD_STAGES[$st] ?? $st) . '</span>'; }
function lead_save(array $f, ?int $id = null): int {
    leads_migrate(); $f['updated'] = now();
    foreach (['audit', 'src'] as $k) if (isset($f[$k]) && is_array($f[$k])) $f[$k] = json_encode($f[$k], JSON_UNESCAPED_UNICODE);
    if ($id) { $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($f))); db()->prepare("UPDATE leads SET $set WHERE id = :id")->execute($f + ['id' => $id]); return $id; }
    $f['created'] = now();
    db()->prepare('INSERT INTO leads (' . implode(', ', array_keys($f)) . ') VALUES (:' . implode(', :', array_keys($f)) . ')')->execute($f);
    return (int)db()->lastInsertId();
}
function lead_stage(int $id, string $st): void { if (isset(LEAD_STAGES[$st])) db()->prepare('UPDATE leads SET stage = ?, updated = ? WHERE id = ?')->execute([$st, now(), $id]); }

function http_get(string $url, int $timeout = 15, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; CreativeVisionAudit/1.0; +https://creative-vision.pl)', CURLOPT_HTTPHEADER => $headers, CURLOPT_ENCODING => '']);
    $t = microtime(true); $body = curl_exec($ch); $ms = (int)round((microtime(true) - $t) * 1000);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); $err = curl_error($ch); curl_close($ch);
    return ['code' => $code, 'body' => $body === false ? '' : (string)$body, 'url' => $final, 'ms' => $ms, 'err' => $err];
}

// ---------- przetargi ----------
function tender_keywords(): array { return array_values(array_filter(array_map('trim', preg_split('/\R/', setting('tender_kw') ?: TENDER_KW_DEFAULT) ?: []))); }
function tender_score(string $title, string $cpv, string $prov): int {
    $s = 0; $t = ' ' . mb_strtolower($title) . ' ';
    foreach (tender_keywords() as $k) { $k = mb_strtolower($k); if ($k !== '' && (mb_strlen($k) <= 3 ? preg_match('/(?<![\pL])' . preg_quote($k, '/') . '(?![\pL])/u', $t) : str_contains($t, $k))) $s += 20; }
    if (preg_match('/7241|72212224|72413|79822|79342|72415/', $cpv)) $s += 25; elseif (str_contains($cpv, '722') || str_contains($cpv, '724')) $s += 10;
    if (in_array($prov, explode(',', setting('tender_prov') ?: 'PL18'), true)) $s += 25;
    if (preg_match('/roboty budowlane|dostawa sprzętu|komputer(y|ów) przenośn|serwer(y|ów)|licencj/u', $t)) $s -= 25;
    return max(0, min(100, $s));
}
function tender_store(array $t): bool {
    $q = db()->prepare('SELECT 1 FROM tenders WHERE ref = ?'); $q->execute([$t['ref']]); if ($q->fetchColumn()) return false;
    $t['score'] = tender_score($t['title'], $t['cpv'], $t['province']);
    if ($t['score'] < (int)(setting('tender_min') ?: 20)) return false;
    $t['created'] = now();
    db()->prepare('INSERT INTO tenders (' . implode(', ', array_keys($t)) . ') VALUES (:' . implode(', :', array_keys($t)) . ')')->execute($t);
    return true;
}
function html_text(string $h, int $max = 15000): string {
    $h = preg_replace('#<(style|script|head)[^>]*>.*?</\1>#is', ' ', $h) ?? '';
    $h = preg_replace('#<(br|/p|/h\d|/li|/tr|/div)[^>]*>#i', "\n", $h) ?? '';
    $t = html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t\x{00A0}]+/u", ' ', $t) ?? ''; $t = preg_replace("/\n\s*\n+/", "\n", $t) ?? '';
    return mb_substr(trim($t), 0, $max);
}
/** Dane zamawiającego wyczytane z treści ogłoszenia (BZP i TED): e-mail, adres, NIP, telefon. */
function tender_contact(int $id): array {
    $q = db()->prepare('SELECT * FROM tenders WHERE id = ?'); $q->execute([$id]); $t = $q->fetch(); if (!$t) return [];
    $b = tender_body($id); $g = function (string $re) use ($b): string { return preg_match($re, $b, $m) ? trim($m[1], " \t.,;:") : ''; };
    $mail = $g('/(?:poczty elektronicznej|e-?mail)[^:\n]*:\s*([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})/iu');
    if ($mail === '' && preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $b, $m)) foreach ($m[0] as $x) if (!preg_match('/creative-vision|ezamowienia|europa\.eu|uzp\.gov/i', $x)) { $mail = $x; break; }
    $street = $g('/Ulica[^:\n]*:\s*([^\n]+)/u'); $city = $g('/Miejscowo\x{15B}\x{107}[^:\n]*:\s*([^\n]+)/u') ?: (string)$t['city']; $zip = $g('/Kod pocztowy[^:\n]*:\s*(\d{2}-\d{3})/u');
    $nip = preg_replace('/\D/', '', (string)$t['nip']); if (strlen($nip) !== 10) $nip = preg_replace('/\D/', '', $g('/NIP[^:\n]*:?\s*([\d -]{10,13})/u'));
    return array_filter(['name' => (string)$t['buyer'], 'email' => strtolower($mail), 'addr1' => $street, 'addr2' => trim($zip . ' ' . $city), 'nip' => strlen($nip) === 10 ? $nip : '',
        'phone' => $g('/(?:Numer telefonu|Telefon)[^:\n]*:\s*(\+?[\d ()-]{7,20})/u')]);
}
/** Krótki opis (o czym to jest) i budżet wyczytane z treści ogłoszenia. Zapisywane w kolumnach excerpt i budget. */
function tender_summary(array &$t, bool $fetch = false): void {
    if (!empty($t['excerpt']) || (string)($t['excerpt'] ?? '') === '-') return;
    $b = (string)($t['body'] ?? '');
    if ($b === '' && $fetch) $b = tender_body((int)$t['id']);
    if ($b === '') return;
    $x = '';
    foreach (['/Krótki opis przedmiotu zamówienia\s*:?\s*(.{40,600}?)(?:\n\d+\.\d|\n[A-ZĄĆĘŁŃÓŚŹŻ ]{6,}\n|$)/su', '/CZĘŚĆ 1:[^\n]*\n(?:Szacunkowa wartość[^\n]*\n)?([^\n]{40,600})/u', '/(Przedmiotem zamówienia jest.{20,500}?[.;])/su'] as $re)
        if (preg_match($re, $b, $m)) { $x = trim(preg_replace('/\s+/u', ' ', $m[1])); break; }
    $x = $x !== '' ? mb_strimwidth($x, 0, 230, '…') : '-';
    $bud = '';
    if (preg_match('/(?:szacunkow\w* wartoś\w*|wartoś\w* zamówienia|budżet\w*|kwot\w*[^.\n]{0,60}?przeznacz\w*)[^0-9\n]{0,80}?(\d[\d  .]{2,}(?:,\d{2})?)\s*(zł|PLN|złotych|EUR|euro)/iu', $b, $m)) {
        $v = (float)str_replace([' ', ' ', '.', ','], ['', '', '', '.'], $m[1]);
        if ($v >= 500) $bud = number_format($v, 0, ',', ' ') . (preg_match('/EUR|euro/i', $m[2]) ? ' EUR' : ' zł') . (preg_match('/brutto/i', mb_substr($b, max(0, mb_strpos($b, $m[0]) ?: 0), 300)) ? ' brutto' : '');
    }
    $t['excerpt'] = $x; $t['budget'] = $bud;
    db()->prepare('UPDATE tenders SET excerpt = ?, budget = ? WHERE id = ?')->execute([$x, $bud, $t['id']]);
}
/** Treść ogłoszenia: z bazy, a gdy jej nie ma, pobieramy ją z BZP albo TED i zapisujemy. */
function tender_body(int $id): string {
    leads_migrate();
    $q = db()->prepare('SELECT * FROM tenders WHERE id = ?'); $q->execute([$id]); $t = $q->fetch(); if (!$t) return '';
    if (trim((string)$t['body']) !== '') return (string)$t['body'];
    $body = '';
    if (job_is($t)) $body = job_body_fetch($t);
    elseif ($t['src'] === 'bk') $body = bk_body((string)$t['ref']);
    elseif ($t['src'] === 'bzp' && $t['published']) {
        $r = http_get('https://ezamowienia.gov.pl/mo-board/api/v1/notice?NoticeType=ContractNotice&NoticeNumber=' . rawurlencode($t['ref']) . '&PublicationDateFrom=' . $t['published'] . 'T00:00:00&PublicationDateTo=' . $t['published'] . 'T23:59:59&PageSize=5', 30);
        foreach ((array)json_decode($r['body'], true) as $x) if (is_array($x) && ($x['noticeNumber'] ?? '') === $t['ref']) { $body = html_text((string)($x['htmlBody'] ?? '')); break; }
    } elseif ($t['src'] === 'ted') {
        $r = http_get('https://ted.europa.eu/pl/notice/' . rawurlencode(trim(substr($t['ref'], 4))) . '/html', 30);
        if ($r['code'] === 200) $body = html_text($r['body']);
    }
    if ($body !== '') db()->prepare('UPDATE tenders SET body = ? WHERE id = ?')->execute([$body, $id]);
    return $body;
}
/** Pobiera ogłoszenia z BZP (zamówienia krajowe) i TED (unijne) z ostatnich dni. Zwraca liczbę nowych. */
function tenders_fetch(int $days = 2): array {
    leads_migrate();
    $new = []; $from = date('Y-m-d', strtotime("-$days days")); $err = [];
    foreach (['72413000-8', '72400000-4', '72000000-5', '72212000-4', '79822500-7', '79342000-3', '72415000-2', '48000000-8'] as $cpv) {
        $r = http_get('https://ezamowienia.gov.pl/mo-board/api/v1/notice?NoticeType=ContractNotice&PublicationDateFrom=' . $from . 'T00:00:00&PublicationDateTo=' . date('Y-m-d') . 'T23:59:59&CpvCode=' . $cpv . '&PageSize=100', 30);
        $list = json_decode($r['body'], true);
        if (!is_array($list)) { $err[] = 'BZP ' . $r['code']; continue; }
        foreach ($list as $x) {
            $t = ['src' => 'bzp', 'ref' => (string)($x['noticeNumber'] ?? ''), 'title' => trim((string)($x['orderObject'] ?? '')), 'buyer' => (string)($x['organizationName'] ?? ''), 'city' => (string)($x['organizationCity'] ?? ''),
                'province' => (string)($x['organizationProvince'] ?? ''), 'cpv' => mb_substr((string)($x['cpvCode'] ?? ''), 0, 400), 'deadline' => substr((string)($x['submittingOffersDate'] ?? ''), 0, 16), 'published' => substr((string)($x['publicationDate'] ?? ''), 0, 10),
                'body' => html_text((string)($x['htmlBody'] ?? ''), 15000), 'nip' => (string)($x['organizationNationalId'] ?? ''),
                'url' => !empty($x['objectId']) ? 'https://ezamowienia.gov.pl/mo-client-board/bzp/notice-details/id/' . $x['objectId'] : 'https://ezamowienia.gov.pl/mo-client-board/bzp/list'];
            if ($t['ref'] !== '' && (!$t['deadline'] || $t['deadline'] >= date('Y-m-d')) && tender_store($t)) $new[] = $t;
        }
        usleep(500000);
    }
    $body = ['query' => 'classification-cpv IN (72413000 72400000 72212000 79822500 79342000 72415000 72000000) AND buyer-country=POL AND publication-date>=' . str_replace('-', '', $from),
        'fields' => ['publication-number', 'notice-title', 'buyer-name', 'buyer-city', 'deadline-receipt-tender-date-lot', 'classification-cpv', 'place-of-performance', 'publication-date'], 'limit' => 100, 'scope' => 'ACTIVE', 'paginationMode' => 'ITERATION'];
    $ch = curl_init('https://api.ted.europa.eu/v3/notices/search');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json']]);
    $res = json_decode((string)curl_exec($ch), true); curl_close($ch);
    if (is_array($res) && isset($res['notices'])) foreach ($res['notices'] as $n) {
        $pick = function ($v) { if (is_array($v)) { $v = $v['pol'] ?? (reset($v) ?: ''); while (is_array($v)) $v = reset($v) ?: ''; } return (string)$v; };
        $title = $n['notice-title'] ?? []; $title = is_array($title) ? (string)($title['pol'] ?? ($title['eng'] ?? reset($title))) : (string)$title;
        $place = (array)($n['place-of-performance'] ?? []); $prov = '';
        foreach ($place as $p) if (preg_match('/^PL(\d{2})/', (string)$p, $m)) { $prov = 'PL' . $m[1]; break; }
        $dl = $n['deadline-receipt-tender-date-lot'] ?? ''; $dl = is_array($dl) ? (string)reset($dl) : (string)$dl;
        $t = ['src' => 'ted', 'ref' => 'TED ' . (string)($n['publication-number'] ?? ''), 'title' => trim($title), 'buyer' => $pick($n['buyer-name'] ?? ''), 'city' => $pick($n['buyer-city'] ?? ''),
            'province' => $prov, 'cpv' => implode(',', array_unique((array)($n['classification-cpv'] ?? []))), 'deadline' => substr($dl, 0, 16), 'published' => substr((string)($n['publication-date'] ?? ''), 0, 10),
            'url' => 'https://ted.europa.eu/pl/notice/-/detail/' . ($n['publication-number'] ?? '')];
        if ($t['ref'] !== 'TED ' && (!$t['deadline'] || substr($t['deadline'], 0, 10) >= date('Y-m-d')) && tender_store($t)) $new[] = $t;
    } else $err[] = 'TED';
    try { [$bn, $be] = bk_fetch(); $new = array_merge($new, $bn); $err = array_merge($err, $be); } catch (Throwable $e) { $err[] = 'Baza Konkurencyjności'; }
    set_setting('tender_at', now());
    return [$new, $err];
}
const BK_URL = 'https://bazakonkurencyjnosci.funduszeeuropejskie.gov.pl';
const BK_PROV = ['dolnośląskie' => 'PL02', 'kujawsko-pomorskie' => 'PL04', 'lubelskie' => 'PL06', 'lubuskie' => 'PL08', 'łódzkie' => 'PL10', 'małopolskie' => 'PL12', 'mazowieckie' => 'PL14', 'opolskie' => 'PL16',
    'podkarpackie' => 'PL18', 'podlaskie' => 'PL20', 'pomorskie' => 'PL22', 'śląskie' => 'PL24', 'świętokrzyskie' => 'PL26', 'warmińsko-mazurskie' => 'PL28', 'wielkopolskie' => 'PL30', 'zachodniopomorskie' => 'PL32'];
function bk_get(string $path): ?array {
    $r = http_get(BK_URL . $path, 25, ['Content-type: application/json; charset=utf-8', 'Accept: application/json']);
    $j = json_decode($r['body'], true); return is_array($j) && ($j['status'] ?? '') === 'OK' ? $j['data'] : null;
}
/** Zapytania ofertowe z Bazy Konkurencyjności (firmy z dotacjami UE): zawsze z e-mailem osoby do kontaktu. */
function bk_fetch(): array {
    $new = []; $err = []; $seen = [];
    $qs = [];
    foreach (['72413000-8', '72400000-4', '72212000-4', '72200000-7', '72000000-5', '48000000-8', '79342000-3', '79822500-7', '72415000-2', '72420000-0'] as $c) $qs[] = ['cpvItem%5B0%5D=' . $c, $c];
    foreach (['strony internetowej', 'sklepu internetowego', 'sklep internetowy', 'e-commerce', 'platformy sprzedażowej', 'platformy B2B', 'PrestaShop', 'WooCommerce', 'Shopify', 'Magento', 'serwisu internetowego', 'aplikacji mobilnej', 'aplikacji webowej', 'platformy internetowej', 'UX'] as $q) $qs[] = ['query=' . rawurlencode($q), ''];
    foreach ($qs as [$q, $cpv]) {
        $d = bk_get('/api/announcements/search?page=1&limit=50&sort=default&status%5B0%5D=PUBLISHED&' . $q);
        if ($d === null) { $err[] = 'Baza Konkurencyjności'; continue; }
        foreach ((array)($d['advertisements'] ?? []) as $a) {
            $id = (int)($a['id'] ?? 0); if (!$id || isset($seen[$id])) continue; $seen[$id] = 1;
            $dl = substr((string)($a['submission_deadline'] ?? ''), 0, 16); if ($dl !== '' && $dl < date('Y-m-d H:i')) continue;
            $place = (string)($a['fulfillment_place'] ?? ''); $voiv = trim((string)(explode(',', $place)[1] ?? ''));
            $t = ['src' => 'bk', 'ref' => 'BK ' . $id, 'title' => trim(preg_replace('/\s+/u', ' ', (string)$a['title'])), 'buyer' => (string)($a['advertiser_name'] ?? ''), 'city' => trim((string)explode(',', $place)[0]),
                'province' => BK_PROV[mb_strtolower($voiv)] ?? '', 'cpv' => $cpv, 'deadline' => $dl, 'published' => (string)($a['publication_date'] ?? ''), 'url' => BK_URL . '/ogloszenia/' . $id];
            if (tender_cat($t['title'], $cpv) === 'other' && !preg_match('/stron|sklep|aplikac|platform|portal|serwis|system|oprogramow|ux|ui|www|internet|e-commerce|wzornicz/iu', $t['title'])) continue;
            if (tender_store($t)) { $new[] = $t; $b = bk_body($t['ref']); if ($b !== '') db()->prepare('UPDATE tenders SET body = ? WHERE ref = ?')->execute([$b, $t['ref']]); }
        }
        usleep(300000);
    }
    return [$new, array_unique($err)];
}
/** Treść zapytania z Bazy Konkurencyjności: opis zamówień, kody CPV i osoby do kontaktu (e-mail, telefon). */
function bk_body(string $ref): string {
    $d = bk_get('/api/announcements/' . (int)substr($ref, 3) . '/compact'); $a = $d['advertisement'] ?? null; if (!$a) return '';
    $ad = $a['advertiser_address_details'] ?? []; $ad_a = $ad['address'] ?? [];
    $o = (string)$a['title'] . "\n\nZAMAWIAJĄCY\nNazwa: " . ($ad['name'] ?? '') . "\nNIP: " . ($ad['identification_number'] ?? '') . "\nUlica: " . trim(($ad_a['street'] ?? '') . ' ' . ($ad_a['number_description'] ?? '')) . "\nKod pocztowy: " . ($ad_a['postcode'] ?? '') . "\nMiejscowość: " . ($ad_a['locality'] ?? '');
    foreach ((array)($a['contact_persons'] ?? []) as $i => $p) $o .= "\n\nOSOBA DO KONTAKTU\n" . trim(($p['forename'] ?? '') . ' ' . ($p['surname'] ?? '')) . "\nAdres poczty elektronicznej: " . ($p['email'] ?? '') . (!empty($p['phone_number']) ? "\nNumer telefonu: " . $p['phone_number'] : '');
    foreach ((array)($a['orders'] ?? []) as $n => $or) {
        $o .= "\n\nCZĘŚĆ " . ($n + 1) . ': ' . ($or['title'] ?? '') . (!empty($or['estimated_value']) ? "\nSzacunkowa wartość: " . number_format((float)$or['estimated_value'], 2, ',', ' ') . ' zł' : '');
        foreach ((array)($or['order_items'] ?? []) as $it) { $o .= "\n" . trim((string)($it['description'] ?? '')); $c = array_map(fn($x) => $x['code'] . ' ' . $x['name'], (array)($it['cpv_items'] ?? [])); if ($c) $o .= "\nCPV: " . implode('; ', $c); }
    }
    $o .= "\n\nTermin składania ofert: " . ($a['submission_deadline'] ?? '');
    return mb_substr(trim($o), 0, 15000);
}
/** Raz dziennie: nowe przetargi i powiadomienie o najlepszych. */
function run_tenders(): int {
    if (setting('tender_on') === '0' || substr(setting('tender_at'), 0, 10) === today()) return 0;
    [$new] = tenders_fetch(3);
    $top = array_filter($new, fn($t) => tender_score($t['title'], $t['cpv'], $t['province']) >= 50);
    if ($top && function_exists('push_send')) { $t = reset($top); push_send(count($top) === 1 ? 'Nowy przetarg dla Creative Vision' : count($top) . ' nowe przetargi dla Creative Vision', mb_strimwidth($t['title'], 0, 120, '…') . ' · ' . $t['city'], '/panel?a=leads&t=tenders', 'tenders'); }
    return count($new);
}
/** Przypomnienia o następnym kroku w lejku (rano, raz dziennie). */
function run_lead_reminders(): int {
    leads_migrate();
    if (setting('lead_push_at') === today()) return 0;
    set_setting('lead_push_at', today());
    $s = db()->prepare("SELECT * FROM leads WHERE next_at IS NOT NULL AND next_at <= ? AND stage NOT IN ('won', 'lost') ORDER BY next_at"); $s->execute([today()]);
    $rows = $s->fetchAll(); if (!$rows || !function_exists('push_send')) return 0;
    $first = $rows[0];
    push_send(count($rows) === 1 ? 'Dziś: ' . ($first['company'] ?: $first['name']) : 'Dziś ' . count($rows) . ' follow-upy w ofertach', (string)($first['next_note'] ?: 'Zaplanowany kontakt') . (count($rows) > 1 ? ' i więcej' : ''), '/panel?a=lead&id=' . $first['id'], 'leads');
    return count($rows);
}

// ---------- skaner stron ----------
function normalize_domain(string $s): string {
    $s = trim(mb_strtolower($s)); if ($s === '') return '';
    if (!preg_match('#^https?://#', $s)) $s = 'https://' . $s;
    $h = parse_url($s, PHP_URL_HOST); return $h ? preg_replace('/^www\./', '', $h) : '';
}
/** Szybki audyt strony: co widać z zewnątrz i co możemy poprawić. */
function site_audit(string $domain): array {
    $a = ['domain' => $domain, 'at' => now(), 'points' => [], 'emails' => [], 'phones' => []];
    // ochrona przed odpytywaniem adresów wewnętrznych serwera (SSRF): tylko publiczne adresy IP
    $ips = @gethostbynamel($domain) ?: [];
    foreach ($ips as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { $a['code'] = 0; $a['points'][] = 'Ten adres prowadzi do sieci wewnętrznej, nie sprawdzamy go.'; $a['score'] = 0; return $a; }
    $r = http_get('https://' . $domain, 20);
    if ($r['code'] === 0 || $r['code'] >= 400) { $r2 = http_get('http://' . $domain, 15); if ($r2['code'] && $r2['code'] < 400) $r = $r2; }
    $a['code'] = $r['code']; $a['url'] = $r['url']; $a['ms'] = $r['ms'];
    if ($r['code'] === 0 || $r['code'] >= 400) { $a['points'][] = 'Strona nie odpowiada (' . ($r['code'] ?: $r['err']) . '). Firma może w ogóle nie mieć działającej strony.'; $a['score'] = 90; return $a; }
    $h = $r['body']; $low = mb_strtolower($h);
    $a['https'] = str_starts_with($r['url'], 'https://');
    $a['viewport'] = (bool)preg_match('/<meta[^>]+name=["\']viewport/i', $h);
    $a['title'] = preg_match('/<title[^>]*>(.*?)<\/title>/is', $h, $m) ? trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES)) : '';
    $a['desc'] = (bool)preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'][^"\']{20,}/i', $h);
    $gen = preg_match('/<meta[^>]+name=["\']generator["\'][^>]+content=["\']([^"\']+)/i', $h, $m) ? $m[1] : '';
    if (!$gen && str_contains($low, 'wp-content')) $gen = 'WordPress';
    if (!$gen && str_contains($low, 'wix.com')) $gen = 'Wix';
    if (!$gen && str_contains($low, 'shoper')) $gen = 'Shoper';
    if (!$gen && str_contains($low, 'prestashop')) $gen = 'PrestaShop';
    if (!$gen && str_contains($low, 'woocommerce')) $gen = 'WooCommerce';
    $a['cms'] = $gen;
    $years = preg_match_all('/(?:©|&copy;|copyright)\s*(?:\d{4}\s*[-–]\s*)?(20\d{2}|19\d{2})/iu', $h, $m) ? array_map('intval', $m[1]) : [];
    $a['year'] = $years ? max($years) : null;
    $a['shop'] = (bool)preg_match('/koszyk|add-to-cart|dodaj do koszyka|woocommerce|shoper|prestashop/i', $h);
    $a['analytics'] = (bool)preg_match('/gtag\(|googletagmanager|google-analytics|fbq\(/i', $h);
    $a['jquery_old'] = (bool)preg_match('/jquery[.-]?1\.\d/i', $h);
    $a['flash'] = (bool)preg_match('/\.swf|<embed/i', $h);
    $a['size_kb'] = (int)round(strlen($h) / 1024);
    if (preg_match_all('/mailto:([a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,})/i', $h, $m)) $a['emails'] = array_values(array_unique(array_map('strtolower', $m[1])));
    if (!$a['emails'] && preg_match_all('/\b([a-z0-9._%+-]+@' . preg_quote($domain, '/') . ')\b/i', $h, $m)) $a['emails'] = array_values(array_unique(array_map('strtolower', $m[1])));
    if (preg_match_all('/tel:\+?([\d\s-]{9,})/i', $h, $m)) $a['phones'] = array_values(array_unique(array_map(fn($p) => trim($p), $m[1])));
    // PageSpeed (mobile)
    $ps = http_get('https://www.googleapis.com/pagespeedonline/v5/runPagespeed?strategy=mobile&category=performance&url=' . rawurlencode($r['url']) . (setting('psi_key') !== '' ? '&key=' . rawurlencode(setting('psi_key')) : ''), 60);
    $pj = json_decode($ps['body'], true);
    $a['psi'] = isset($pj['lighthouseResult']['categories']['performance']['score']) ? (int)round($pj['lighthouseResult']['categories']['performance']['score'] * 100) : null;
    $a['lcp'] = isset($pj['lighthouseResult']['audits']['largest-contentful-paint']['numericValue']) ? round($pj['lighthouseResult']['audits']['largest-contentful-paint']['numericValue'] / 1000, 1) : null;
    $s = 0; $P = &$a['points'];
    if (!$a['https']) { $s += 20; $P[] = 'Brak HTTPS: przeglądarka pokazuje „Niezabezpieczona”, to odstrasza klientów i obniża pozycję w Google.'; }
    if (!$a['viewport']) { $s += 25; $P[] = 'Strona nie jest przygotowana na telefon (brak responsywności). Większość ruchu to dziś mobile.'; }
    if ($a['psi'] !== null && $a['psi'] < 50) { $s += 20; $P[] = 'Wolna na telefonie: PageSpeed ' . $a['psi'] . '/100' . ($a['lcp'] ? ', główna treść ładuje się ' . $a['lcp'] . ' s' : '') . '.'; }
    elseif ($a['psi'] !== null && $a['psi'] < 75) { $s += 8; $P[] = 'Szybkość do poprawy: PageSpeed ' . $a['psi'] . '/100.'; }
    if ($a['year'] && $a['year'] < (int)date('Y') - 2) { $s += 15; $P[] = 'W stopce rok ' . $a['year'] . ': strona wygląda na nieaktualizowaną od kilku lat.'; }
    if (!$a['desc']) { $s += 8; $P[] = 'Brak opisu meta: w wynikach Google pojawia się przypadkowy fragment tekstu.'; }
    if (!$a['analytics']) { $s += 5; $P[] = 'Brak analityki: firma nie wie, ilu klientów przychodzi ze strony.'; }
    if ($a['jquery_old'] || $a['flash']) { $s += 10; $P[] = 'Przestarzała technologia na stronie (stare biblioteki), większe ryzyko błędów i włamań.'; }
    if ($gen && preg_match('/wordpress\s*([0-5]\.)/i', $gen)) { $s += 10; $P[] = 'Stara wersja WordPressa (' . $gen . '): ryzyko bezpieczeństwa.'; }
    if ($a['ms'] > 3000) { $s += 5; $P[] = 'Serwer odpowiada wolno (' . round($a['ms'] / 1000, 1) . ' s).'; }
    if ($a['shop']) $P[] = 'Ma sklep internetowy: temat na UX koszyka, szybkość i konwersję.';
    if (!$P) $P[] = 'Strona jest w niezłym stanie technicznym. Szansa raczej na redesign, UX albo kampanie, nie na ratunek.';
    $a['score'] = min(100, $s);
    return $a;
}

// ---------- akcje ----------
function leads_post(string $a): never {
    leads_migrate();
    $id = (int)($_POST['id'] ?? 0);
    switch ($a) {
        case 'lead_save':
            $v = fn($k, $n = 300) => trim(mb_substr((string)($_POST[$k] ?? ''), 0, $n));
            $f = ['name' => $v('name'), 'company' => $v('company'), 'email' => $v('email'), 'phone' => $v('phone'), 'website' => $v('website'), 'source' => isset(LEAD_SOURCES[$_POST['source'] ?? '']) ? (string)$_POST['source'] : 'reczne',
                'stage' => isset(LEAD_STAGES[$_POST['stage'] ?? '']) ? (string)$_POST['stage'] : 'new', 'value' => $v('value') !== '' ? to_cents($v('value')) : null,
                'next_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v('next_at')) ? $v('next_at') : null, 'next_note' => $v('next_note'), 'notes' => $v('notes', 5000)];
            if ($f['name'] === '' && $f['company'] === '') { flash('!Wpisz osobę albo firmę.'); redirect(P . '?a=' . ($id ? 'lead&id=' . $id : 'lead_new')); }
            $id = lead_save($f, $id ?: null);
            flash('Szansa zapisana.'); redirect(P . '?a=lead&id=' . $id);
        case 'lead_stage':
            lead_stage($id, (string)($_POST['stage'] ?? '')); redirect(P . '?a=lead&id=' . $id);
        case 'lead_next':
            $d = (string)($_POST['next_at'] ?? ''); $d = preg_match('/^\+(\d+)$/', $d, $m) ? date('Y-m-d', strtotime('+' . $m[1] . ' days')) : (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null);
            db()->prepare('UPDATE leads SET next_at = ?, next_note = ?, updated = ? WHERE id = ?')->execute([$d, trim((string)($_POST['next_note'] ?? '')), now(), $id]);
            if (($_POST['back'] ?? '') === 'due') { flash($d ? 'Przesunięte na ' . date('d.m', strtotime($d)) . '.' : 'Zrobione.'); redirect(P . '?a=leads&f=due#due'); }
            redirect(P . '?a=lead&id=' . $id);
        case 'lead_delete':
            db()->prepare('DELETE FROM leads WHERE id = ?')->execute([$id]); db()->prepare("UPDATE tenders SET lead_id = NULL, status = CASE WHEN src IN ('useme','freelancer') THEN 'hidden' ELSE 'new' END WHERE lead_id = ?")->execute([$id]);
            flash('Usunięte z ofert.'); redirect(P . '?a=leads');
        case 'lead_from_mail':
            mail_acc((string)($_POST['acc'] ?? 'f'));
            $inq = inquiry_from_mail((int)($_POST['uid'] ?? 0)); mail_acc('f');
            if (!$inq) redirect(P . '?a=leads');
            $id = lead_save(['name' => $inq['from'], 'email' => $inq['email'], 'company' => '', 'source' => 'mail', 'stage' => 'new', 'next_at' => today(), 'next_note' => 'Odpowiedzieć na zapytanie',
                'notes' => trim(($inq['subject'] !== '' ? $inq['subject'] . "\n\n" : '') . mb_substr($inq['text'], 0, 3000)), 'src' => array_diff_key($inq, ['text' => 1])]);
            flash('Zapytanie dodane do ofert.'); redirect(P . '?a=lead&id=' . $id);
        case 'tender_lead': case 'tender_quote':
            $t = db()->prepare('SELECT * FROM tenders WHERE id = ?'); $t->execute([$id]); $t = $t->fetch();
            if (!$t) redirect(P . '?a=leads&t=tenders');
            $go = fn($lid) => redirect($a === 'tender_quote' ? P . '?a=quote_new&lead=' . $lid . '&ai=1' : P . '?a=lead&id=' . $lid);
            if ($t['lead_id']) $go((int)$t['lead_id']);
            if (job_is($t)) {
                $lid = lead_save(['name' => (string)$t['buyer'], 'company' => '', 'source' => 'gielda', 'stage' => 'new', 'next_at' => today(), 'next_note' => 'Złożyć ofertę na ' . ($t['src'] === 'useme' ? 'Useme' : 'Freelancer.com') . ' (dziś, zanim zbierze się dużo ofert)',
                    'website' => $t['url'], 'notes' => $t['title'] . "\n" . ($t['budget'] ? 'Budżet: ' . $t['budget'] . "\n" : '') . $t['url'], 'src' => ['tender' => (int)$t['id'], 'job' => 1], 'score' => (int)$t['score']]);
                db()->prepare("UPDATE tenders SET status = 'saved', lead_id = ? WHERE id = ?")->execute([$lid, $id]);
                flash('Zlecenie dodane do ofert.'); $go($lid);
            }
            $lid = lead_save(['name' => '', 'company' => $t['buyer'], 'source' => $t['src'] === 'bk' ? 'zapytanie' : 'przetarg', 'stage' => 'new', 'next_at' => $t['deadline'] ? date('Y-m-d', strtotime(substr($t['deadline'], 0, 10) . ' -3 days')) : today(),
                'next_note' => 'Przygotować ofertę do przetargu' . ($t['deadline'] ? ' (termin ' . date('d.m H:i', strtotime($t['deadline'])) . ')' : ''), 'website' => $t['url'], 'notes' => $t['title'] . "\n" . $t['ref'] . ' · ' . $t['city'] . "\n" . $t['url'], 'src' => ['tender' => (int)$t['id']] + ($t['src'] === 'bk' ? ['bk' => 1] : []), 'score' => (int)$t['score']]);
            db()->prepare("UPDATE tenders SET status = 'saved', lead_id = ? WHERE id = ?")->execute([$lid, $id]);
            flash($t['src'] === 'bk' ? 'Zapytanie dodane do ofert.' : 'Przetarg dodany do ofert.'); $go($lid);
        case 'lead_move':
            header('Content-Type: application/json');
            $st = (string)($_POST['st'] ?? '');
            if (!isset(LEAD_STAGES[$st]) || !lead_by_id($id)) { echo '{"ok":false}'; exit; }
            lead_stage($id, $st); echo '{"ok":true}'; exit;
        case 'tender_hide':
            db()->prepare("UPDATE tenders SET status = 'hidden' WHERE id = ?")->execute([$id]);
            $s = db()->prepare('SELECT src FROM tenders WHERE id = ?'); $s->execute([$id]); redirect(P . '?a=leads&t=' . (in_array($s->fetchColumn(), JOB_SRCS, true) ? 'jobs' . (!empty($_POST['c']) ? '&c=' . rawurlencode((string)$_POST['c']) : '') : 'tenders'));
        case 'jobs_fetch':
            @set_time_limit(120);
            [$new, $err] = jobs_fetch();
            flash(($err && !$new ? '!' : '') . 'Nowe zlecenia: ' . count($new) . '.' . ($err ? ' Nie odpowiedziało: ' . implode(', ', array_unique($err)) . '.' : ''));
            redirect(P . '?a=leads&t=jobs');
        case 'jobs_settings':
            set_setting('jobs_on', !empty($_POST['jobs_on']) ? '1' : '0'); set_setting('jobs_fl', !empty($_POST['jobs_fl']) ? '1' : '0');
            set_setting('jobs_push', (string)max(0, min(100, (int)($_POST['jobs_push'] ?? 50))));
            flash('Ustawienia zleceń zapisane.'); redirect(P . '?a=leads&t=jobs');
        case 'tenders_fetch':
            @set_time_limit(120);
            [$new, $err] = tenders_fetch((int)($_POST['days'] ?? 7));
            flash(($err && !$new ? '!' : '') . 'Nowe przetargi: ' . count($new) . '.' . ($err ? ' Nie odpowiedziało: ' . implode(', ', array_unique($err)) . '.' : ''));
            redirect(P . '?a=leads&t=tenders');
        case 'tender_settings':
            set_setting('tender_kw', trim(mb_substr((string)($_POST['tender_kw'] ?? ''), 0, 3000)));
            set_setting('tender_prov', preg_replace('/[^A-Z0-9,]/', '', strtoupper((string)($_POST['tender_prov'] ?? 'PL18'))) ?: 'PL18');
            set_setting('tender_min', (string)max(0, min(100, (int)($_POST['tender_min'] ?? 20))));
            set_setting('tender_on', !empty($_POST['tender_on']) ? '1' : '0');
            flash('Ustawienia przetargów zapisane.'); redirect(P . '?a=leads&t=tenders');
        case 'scan':
            @set_time_limit(170);
            $doms = array_values(array_unique(array_filter(array_map('normalize_domain', preg_split('/[\s,;]+/', (string)($_POST['domains'] ?? '')) ?: []))));
            $done = 0; $t0 = time(); $left = [];
            foreach ($doms as $d) {
                if (time() - $t0 > 120) { $left[] = $d; continue; }
                $q = db()->prepare('SELECT id FROM leads WHERE website = ? OR website = ?'); $q->execute([$d, 'https://' . $d]); $ex = (int)$q->fetchColumn();
                $au = site_audit($d);
                $f = ['website' => $d, 'audit' => $au, 'score' => $au['score'], 'email' => $au['emails'][0] ?? '', 'phone' => $au['phones'][0] ?? ''];
                if ($ex) lead_save(['audit' => $au, 'score' => $au['score']], $ex);
                else lead_save($f + ['company' => !empty($au['title']) ? mb_strimwidth($au['title'], 0, 80, '…') : $d, 'name' => '', 'source' => 'skaner', 'stage' => 'new', 'notes' => '']);
                $done++;
            }
            flash('Przeskanowane strony: ' . $done . '.' . ($left ? ' Nie zdążyliśmy z: ' . implode(', ', $left) . '. Dodaj je jeszcze raz.' : ''));
            redirect(P . '?a=leads&t=scan');
        case 'lead_rescan':
            $l = lead_by_id($id); if ($l && $l['website'] !== '') { $au = site_audit(normalize_domain($l['website'])); lead_save(['audit' => $au, 'score' => $au['score']], $id); flash('Audyt strony odświeżony.'); }
            redirect(P . '?a=lead&id=' . $id);
    }
    redirect(P . '?a=leads');
}

// ---------- widoki ----------
function view_leads(): never {
    leads_migrate();
    $tab = (string)($_GET['t'] ?? 'pipe'); if (!in_array($tab, ['pipe', 'tenders', 'scan'], true)) $tab = 'pipe'; jobs_migrate();
    $tabs = '';
    $newT = (int)db()->query("SELECT COUNT(*) FROM tenders WHERE status = 'new' AND src IN ('bzp','ted','bk') AND (deadline IS NULL OR deadline = '' OR deadline >= date('now'))")->fetchColumn();
    $newJ = (int)db()->query("SELECT COUNT(*) FROM tenders WHERE status = 'new' AND src IN ('useme','freelancer') AND deadline >= date('now') AND created >= datetime('now', '-1 day')")->fetchColumn();
    foreach (['pipe' => 'Oferty', 'tenders' => 'Przetargi', 'scan' => 'Skaner stron'] as $k => $l) $tabs .= '<a href="' . P . '?a=leads&t=' . $k . '"' . ($tab === $k ? ' class="on" aria-current="true"' : '') . '>' . $l . ($k === 'tenders' && $newT ? ' <span>' . $newT . '</span>' : '') . ($k === 'jobs' && $newJ ? ' <span>' . $newJ . '</span>' : '') . '</a>';
    $body = '';
    if ($tab === 'pipe') {
        $st = (string)($_GET['s'] ?? 'open');
        $all = db()->query('SELECT * FROM leads ORDER BY CASE WHEN next_at IS NULL THEN 1 ELSE 0 END, next_at, updated DESC')->fetchAll();
        $open = array_filter($all, fn($l) => !in_array($l['stage'], ['won', 'lost'], true));
        $val = array_sum(array_map(fn($l) => (int)$l['value'], $open));
        $due = array_filter($open, fn($l) => $l['next_at'] && $l['next_at'] <= today());
        $wonM = array_filter($all, fn($l) => $l['stage'] === 'won' && substr((string)$l['updated'], 0, 7) === date('Y-m'));
        $body .= '<section class="kpis"><div class="kpi dark"><span class="t">Otwarte szanse</span><span class="v">' . money($val) . '</span><span class="s">' . count($open) . ' ' . plural(count($open), 'szansa', 'szanse', 'szans') . ' w ofertach, wartość netto</span></div><div class="kpis-r">'
            . '<div class="kpi"><span class="t">Na dziś</span><span class="v' . ($due ? ' red' : '') . '">' . count($due) . '</span><span class="s">' . plural(count($due), 'follow-up', 'follow-upy', 'follow-upów') . ' do zrobienia</span>' . ($due ? '<a class="kpi-a" href="' . P . '?a=leads' . (($_GET['f'] ?? '') === 'due' ? '' : '&f=due#due') . '">' . (($_GET['f'] ?? '') === 'due' ? 'Ukryj' : 'Zobacz') . ' ' . icon('arrow') . '</a>' : '') . '</div>'
            . '<div class="kpi"><span class="t">Wygrane w tym miesiącu</span><span class="v">' . count($wonM) . '</span><span class="s">' . money(array_sum(array_map(fn($l) => (int)$l['value'], $wonM))) . '</span></div></div></section>';
        if (($_GET['f'] ?? '') === 'due') {
            $rows = '';
            foreach ($due as $l) {
                $late = days_between((string)$l['next_at'], today());
                $mv = fn($v, $lab, $cls = 'btn ghost sm') => '<form method="post" action="' . P . '?a=lead_next">' . csrf_field() . '<input type="hidden" name="id" value="' . $l['id'] . '"><input type="hidden" name="back" value="due"><input type="hidden" name="next_at" value="' . $v . '"><input type="hidden" name="next_note" value="' . e((string)$l['next_note']) . '"><button class="' . $cls . '">' . $lab . '</button></form>';
                $rows .= '<li class="due-r"><a class="due-t" href="' . P . '?a=lead&id=' . $l['id'] . '"><b>' . e($l['company'] ?: $l['name']) . '</b><span>' . e((string)($l['next_note'] ?: 'Zaplanowany kontakt')) . '</span><span class="' . ($late > 0 ? 'late-t' : 'mut') . '">' . ($late > 0 ? 'Spóźnione ' . $late . ' ' . plural($late, 'dzień', 'dni', 'dni') : 'Na dziś') . ' · ' . e(LEAD_STAGES[$l['stage']] ?? '') . '</span></a>'
                    . '<div class="due-a"><a class="btn sm" href="' . P . '?a=lead&id=' . $l['id'] . '">Otwórz</a>' . $mv('+1', 'Jutro') . $mv('+7', 'Za tydzień') . $mv('', icon('check') . ' Zrobione') . '</div></li>';
            }
            $body .= '<section class="card pad sec" id="due"><div class="hrow"><h2>Follow-upy na dziś</h2><a class="link" href="' . P . '?a=leads">Ukryj</a></div>' . ($rows ? '<ul class="due-l">' . $rows . '</ul>' : '<span class="mut">Wszystko zrobione na dziś.</span>') . '</section>';
        }
        $by = array_fill_keys(array_keys(LEAD_STAGES), ''); $cnt = array_fill_keys(array_keys(LEAD_STAGES), 0); $sum = array_fill_keys(array_keys(LEAD_STAGES), 0);
        $opts = fn($cur) => implode('', array_map(fn($k, $x) => '<option value="' . $k . '"' . ($k === $cur ? ' selected' : '') . '>' . $x . '</option>', array_keys(LEAD_STAGES), LEAD_STAGES));
        foreach ($all as $l) {
            $st = isset(LEAD_STAGES[$l['stage']]) ? $l['stage'] : 'new';
            if (in_array($st, ['won', 'lost'], true) && substr((string)$l['updated'], 0, 10) < date('Y-m-d', strtotime('-90 days'))) continue;
            $url = P . '?a=lead&id=' . $l['id']; $late = $l['next_at'] && $l['next_at'] <= today() && !in_array($st, ['won', 'lost'], true);
            $by[$st] .= '<article class="kcard" data-id="' . $l['id'] . '" data-href="' . $url . '"><div class="kc-h"><span class="kc-src">' . e(LEAD_SOURCES[$l['source']] ?? $l['source']) . '</span>' . ($l['score'] !== null ? '<span class="sc' . ($l['score'] >= 50 ? ' hi' : '') . '">' . (int)$l['score'] . '</span>' : '') . '</div>'
                . '<a class="kc-t" href="' . $url . '">' . e($l['company'] ?: ($l['name'] ?: $l['website'])) . '</a>'
                . (($l['company'] && $l['name']) ? '<span class="kc-b">' . e($l['name']) . '</span>' : '')
                . ($l['value'] ? '<span class="kc-v">' . money((int)$l['value']) . '</span>' : '')
                . ($l['next_at'] ? '<span class="kc-d' . ($late ? ' late-t' : '') . '">' . ($late ? 'Dziś: ' : e(date('d.m', strtotime($l['next_at']))) . ': ') . e($l['next_note'] ?: 'kontakt') . '</span>' : '')
                . '<div class="kc-f"><div class="ias">' . ia('eye', !empty($srcT = json_decode((string)$l['src'], true)['tender'] ?? null) ? 'Podgląd ogłoszenia' : 'Otwórz szansę', !empty($srcT) ? P . '?a=tender&id=' . (int)$srcT : $url)
                . ($l['website'] !== '' && $l['website'] !== null ? ia('external', $l['source'] === 'przetarg' ? 'Ogłoszenie w serwisie' : 'Strona firmy', preg_match('#^https?://#', (string)$l['website']) ? (string)$l['website'] : 'https://' . $l['website'], ['blank' => true]) : '')
                . ($l['quote_id'] ? ia('quote', 'Wycena', P . '?a=quote&id=' . (int)$l['quote_id']) : ia('spark', 'Przygotuj wycenę', P . '?a=quote_new&lead=' . $l['id'] . (ai_enabled() ? '&ai=1' : ''))) . ia('trash', 'Usuń', '', ['post' => 'lead_delete', 'id' => $l['id'], 'confirm' => 'Usunąć „' . ($l['company'] ?: $l['name'] ?: 'tę szansę') . '” z ofert?']) . '</div>'
                . '<label class="kc-mv"><span class="sr">Przenieś do</span><select data-move>' . $opts($st) . '</select></label></div></article>';
            $cnt[$st]++; $sum[$st] += (int)$l['value'];
        }
        $hint = ['new' => 'Zapytania, przetargi i firmy ze skanera.', 'contact' => 'Rozmawiamy, zbieramy wymagania.', 'offer' => 'Wycena u klienta.', 'nego' => 'Klient odpisał, ustalamy szczegóły.', 'won' => 'Do startu i faktury.', 'lost' => 'Nie tym razem.'];
        $board = '';
        foreach (LEAD_STAGES as $k => $lbl) $board .= '<section class="kcol" data-st="' . $k . '"><header><b>' . $lbl . '</b><span class="kc-n">' . $cnt[$k] . '</span></header><p class="kc-hint">' . ($sum[$k] ? money($sum[$k]) . ' netto' : $hint[$k]) . '</p><div class="kc-list">' . $by[$k] . '</div></section>';
        $body .= '<section class="card"><nav class="tabs">' . $tabs . '</nav><div class="pad" style="padding-top:14px;padding-bottom:14px"><span class="mut" style="font-size:14px">Przeciągnij kartę do innej kolumny (na telefonie przytrzymaj kartę albo wybierz etap z listy na karcie).</span></div></section>'
            . ($all ? '<div class="kboard" id="kboard" data-move-url="lead_move">' . $board . '</div>' : '<section class="card"><div class="empty">Pusto. Dodaj szansę, zapisz przetarg albo przeskanuj strony firm.</div></section>');
    } elseif ($tab === 'tenders') {
        $all = db()->query("SELECT * FROM tenders WHERE status = 'new' AND src IN ('bzp','ted','bk') AND (deadline IS NULL OR deadline = '' OR deadline >= date('now')) ORDER BY score DESC, deadline LIMIT 300")->fetchAll();
        $cat = (string)($_GET['c'] ?? 'all'); if ($cat !== 'all' && !isset(TENDER_CATS[$cat])) $cat = 'all';
        $cc = array_fill_keys(array_keys(TENDER_CATS), 0);
        foreach ($all as &$t) { $t['cat'] = tender_cat((string)$t['title'], (string)$t['cpv']); $cc[$t['cat']]++; } unset($t);
        $rows = $cat === 'all' ? $all : array_values(array_filter($all, fn($t) => $t['cat'] === $cat));
        $ctabs = '<a href="' . P . '?a=leads&t=tenders"' . ($cat === 'all' ? ' class="on" aria-current="true"' : '') . '>Wszystkie <span>' . count($all) . '</span></a>';
        foreach (TENDER_CATS as $k => $l) if ($cc[$k] || $cat === $k) $ctabs .= '<a href="' . P . '?a=leads&t=tenders&c=' . $k . '"' . ($cat === $k ? ' class="on" aria-current="true"' : '') . '>' . $l . ' <span>' . $cc[$k] . '</span></a>';
        $inLead = (int)db()->query("SELECT COUNT(*) FROM tenders WHERE lead_id IS NOT NULL AND src IN ('bzp','ted','bk')")->fetchColumn();
        $tiles = '';
        $lazy = 0; $pn = array_flip(BK_PROV);
        foreach ($rows as $t) {
            if (empty($t['excerpt'])) tender_summary($t, $t['src'] === 'bk' && trim((string)$t['body']) === '' && $lazy++ < 4);
            $left = $t['deadline'] ? days_between(today(), substr($t['deadline'], 0, 10)) : null;
            $dl = $t['deadline'] ? ($left === 0 ? 'Oferty dziś do ' . date('H:i', strtotime($t['deadline'])) : 'Oferty do ' . date('d.m H:i', strtotime($t['deadline'])) . ' · za ' . $left . ' ' . plural((int)$left, 'dzień', 'dni', 'dni')) : 'Bez terminu';
            $vurl = P . '?a=tender&id=' . $t['id'];
            $ex = (string)($t['excerpt'] ?? ''); $bud = (string)($t['budget'] ?? '');
            $tiles .= '<article class="kcard tile" data-tile="' . $vurl . '"><div class="kc-h"><span class="sc' . ($t['score'] >= 50 ? ' hi' : '') . '" title="Dopasowanie do Creative Vision (0 do 100)">' . (int)$t['score'] . '</span><span class="kc-cat c-' . $t['cat'] . '">' . TENDER_CATS[$t['cat']] . '</span>'
                . '<span class="kc-src" style="margin-left:auto" title="' . (['bk' => 'Baza Konkurencyjności', 'bzp' => 'Biuletyn Zamówień Publicznych', 'ted' => 'TED, zamówienia unijne'][$t['src']] ?? '') . '">' . (['bk' => 'BAZA', 'bzp' => 'BZP', 'ted' => 'TED'][$t['src']] ?? strtoupper($t['src'])) . '</span></div>'
                . '<a class="kc-t" href="' . $vurl . '">' . e(tender_title((string)$t['title'])) . '</a>'
                . ($ex !== '' && $ex !== '-' ? '<span class="kc-x">' . e($ex) . '</span>' : '')
                . '<span class="kc-b">' . e(trim(preg_replace('/\s+,/', ',', trim((string)$t['buyer']) . ($t['city'] ? ', ' . $t['city'] : '') . (isset($pn[$t['province']]) ? ' · ' . $pn[$t['province']] : '')))) . '</span>'
                . '<div class="kc-m">' . ($bud !== '' ? '<span class="kc-bud">Budżet ' . e($bud) . '</span>' : '') . '<span class="kc-d' . ($left !== null && $left <= 3 ? ' late-t' : '') . '">' . e($dl) . '</span></div>'
                . '<div class="kc-f kc-f2"><a class="btn sm" href="' . $vurl . '">' . icon('eye') . ' Zobacz</a>'
                . '<form method="post" action="' . P . '?a=tender_quote">' . csrf_field() . '<input type="hidden" name="id" value="' . $t['id'] . '"><button class="btn ghost sm">' . icon('spark') . ' Wyceń</button></form>'
                . imore([['target', 'Do ofert', ['post' => 'tender_lead', 'id' => $t['id']]], ['external', 'Ogłoszenie w serwisie', (string)$t['url'], ['blank' => true]], ['x', 'Ignoruj', ['post' => 'tender_hide', 'id' => $t['id']], ['danger' => true]]]) . '</div></article>';
        }
        $at = setting('tender_at');
        $body .= '<section class="card"><nav class="tabs">' . $tabs . '</nav><div class="hrow pad" style="flex-wrap:wrap"><span class="mut" style="font-size:14px">Przetargi z BZP i TED oraz zapytania ofertowe firm z Bazy Konkurencyjności (zawsze z e-mailem do kontaktu) na strony, portale, sklepy, aplikacje i grafikę, od najlepiej dopasowanych. Te, które bierzemy, dodaj do ofert' . ($inLead ? ' (już w ofertach: ' . $inLead . ')' : '') . '. Sprawdzamy codziennie rano' . ($at ? ', ostatnio ' . e(date('d.m H:i', strtotime($at))) : '') . '.</span>'
            . '<form method="post" action="' . P . '?a=tenders_fetch">' . csrf_field() . '<input type="hidden" name="days" value="7"><button class="btn ghost sm" data-busy>' . icon('refresh') . ' Sprawdź teraz</button></form></div>'
            . ($all ? '<nav class="tabs sub" aria-label="Rodzaj przetargu">' . $ctabs . '</nav>' : '') . '</section>'
            . ($tiles ? '<div class="ktiles">' . $tiles . '</div>' : '<section class="card"><div class="empty">Brak nowych przetargów. Kliknij „Sprawdź teraz” albo poszerz słowa kluczowe.</div></section>')
            . '<section class="card pad sec"><details class="more"><summary>Ustawienia przetargów</summary><form class="inl" method="post" action="' . P . '?a=tender_settings">' . csrf_field()
            . '<label class="f">Słowa kluczowe (jedno w linii, wystarczy początek słowa)<textarea class="in" name="tender_kw" rows="8">' . e(setting('tender_kw') ?: TENDER_KW_DEFAULT) . '</textarea></label>'
            . '<label class="f">Województwa z bonusem (kody NUTS, np. PL18 podkarpackie, PL21 małopolskie, PL81 lubelskie)<input class="in" name="tender_prov" value="' . e(setting('tender_prov') ?: 'PL18') . '"></label>'
            . '<label class="f">Minimalna ocena dopasowania (0 do 100)<input class="in" type="number" name="tender_min" min="0" max="100" value="' . e(setting('tender_min') ?: '20') . '"></label>'
            . '<label class="check"><input type="checkbox" name="tender_on" value="1"' . (setting('tender_on') !== '0' ? ' checked' : '') . '> Sprawdzaj codziennie i daj znać o najlepszych (powiadomienie)</label><button class="btn ghost sm">Zapisz</button></form></details></section>';
    } elseif ($tab === 'jobs') {
        $body .= view_jobs_tab($tabs);
    } else {
        $rows = db()->query("SELECT * FROM leads WHERE audit IS NOT NULL AND audit != '' ORDER BY score DESC, updated DESC LIMIT 100")->fetchAll();
        $tr = '';
        foreach ($rows as $l) { $au = json_decode((string)$l['audit'], true) ?: []; $url = P . '?a=lead&id=' . $l['id'];
            $tr .= '<tr class="row" data-href="' . $url . '"><td class="c-num nw"><span class="num" style="border:0">' . (int)$l['score'] . '</span></td><td class="c-cli"><span>' . e($l['company'] ?: $l['website']) . '</span><span class="sub">' . e($l['website'] . (!empty($au['cms']) ? ' · ' . $au['cms'] : '')) . '</span></td>'
                . '<td class="c-due nw">' . (isset($au['psi']) && $au['psi'] !== null ? 'PageSpeed ' . (int)$au['psi'] : '') . '<span class="sub">' . (!empty($au['https']) ? 'HTTPS' : 'bez HTTPS') . ' · ' . (!empty($au['viewport']) ? 'mobile' : 'bez mobile') . '</span></td><td class="c-amt r amt">' . e(count($au['points'] ?? []) . ' uwag') . '</td><td class="c-st nw">' . lead_chip($l['stage']) . '</td><td class="c-act"></td></tr>'; }
        $body .= '<section class="card"><nav class="tabs">' . $tabs . '</nav><form class="pad sec" method="post" action="' . P . '?a=scan">' . csrf_field()
            . '<label class="f">Adresy stron firm, które chcesz sprawdzić (po jednym w linii, najwyżej kilka naraz)<textarea class="in" name="domains" rows="4" placeholder="firma-z-rzeszowa.pl&#10;innafirma.pl"></textarea></label>'
            . '<div class="hrow" style="flex-wrap:wrap"><span class="mut" style="font-size:14px">Sprawdzimy HTTPS, wersję mobilną, szybkość (PageSpeed), aktualność i dane kontaktowe. Każda strona trafi do ofert z oceną potencjału.</span><button class="btn" data-busy>' . icon('search') . ' Skanuj</button></div></form>'
            . ($tr ? '<div class="tw"><table class="list"><tbody>' . $tr . '</tbody></table></div>' : '') . '</section>';
    }
    page('Szanse', 'leads', $body, 'Nowi klienci dla Creative Vision', '<a class="btn" href="' . P . '?a=lead_new">Nowa szansa ' . icon('plus') . '</a>');
}

function view_lead(?array $l): never {
    $v = fn($k) => e((string)($l[$k] ?? ''));
    $opt = fn(array $o, string $sel) => implode('', array_map(fn($k, $x) => '<option value="' . $k . '"' . ($k === $sel ? ' selected' : '') . '>' . $x . '</option>', array_keys($o), $o));
    $form = '<form class="inl" method="post" action="' . P . '?a=lead_save">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)($l['id'] ?? 0) . '">'
        . '<div class="grid2"><label class="f">Firma<input class="in" name="company" value="' . $v('company') . '"></label><label class="f">Osoba<input class="in" name="name" value="' . $v('name') . '"></label>'
        . '<label class="f">E-mail<input class="in" type="email" name="email" value="' . $v('email') . '"></label><label class="f">Telefon<input class="in" type="tel" name="phone" value="' . $v('phone') . '"></label>'
        . '<label class="f">Strona lub link<input class="in" name="website" value="' . $v('website') . '"></label><label class="f">Wartość netto (szacunkowo)<input class="in" name="value" inputmode="decimal" value="' . (!empty($l['value']) ? money((int)$l['value'], false) : '') . '"></label>'
        . '<label class="f">Źródło<select class="in" name="source">' . $opt(LEAD_SOURCES, (string)($l['source'] ?? 'reczne')) . '</select></label><label class="f">Etap<select class="in" name="stage">' . $opt(LEAD_STAGES, (string)($l['stage'] ?? 'new')) . '</select></label>'
        . '<label class="f">Następny krok, kiedy<input class="in" type="date" name="next_at" value="' . $v('next_at') . '"></label><label class="f">Co zrobić<input class="in" name="next_note" value="' . $v('next_note') . '" placeholder="np. zadzwonić z propozycją"></label></div>'
        . '<label class="f">Notatki<textarea class="in" name="notes" rows="6">' . $v('notes') . '</textarea></label><button class="btn">Zapisz szansę</button></form>';
    if (!$l) { $GLOBALS['back'] = [P . '?a=leads', 'Szanse']; page('Nowa szansa', 'leads', '<section class="card pad sec" style="max-width:860px">' . $form . '</section>', 'Oferty'); }
    $id = (int)$l['id'];
    $f = fn($a, $label, $cls = 'btn ghost', $extra = '') => '<form method="post" action="' . P . '?a=' . $a . '">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '">' . $extra . '<button class="' . $cls . '">' . $label . '</button></form>';
    $stages = ''; foreach (LEAD_STAGES as $k => $x) if ($k !== $l['stage']) $stages .= $f('lead_stage', $x, 'btn ghost sm', '<input type="hidden" name="stage" value="' . $k . '">');
    $quote = $l['quote_id'] ? quote_by_id((int)$l['quote_id']) : null;
    $acts = ($quote ? '<a class="btn" href="' . P . '?a=quote&id=' . $quote['id'] . '">Wycena ' . e($quote['number']) . ' ' . icon('arrow') . '</a>' : '<a class="btn" href="' . P . '?a=quote_new&lead=' . $id . (ai_enabled() ? '&ai=1' : '') . '">' . icon(ai_enabled() ? 'spark' : 'quote') . (ai_enabled() ? ' Przygotuj wycenę z AI' : ' Utwórz wycenę') . '</a>')
        . ($l['email'] !== '' ? '<a class="btn ghost" href="' . P . '?a=compose&acc=k&to=' . rawurlencode($l['email']) . '&lead=' . $id . '">' . icon('mail') . ' Napisz z kontakt@</a>' : '')
        . ($l['phone'] !== '' ? '<a class="btn ghost" href="tel:' . e(preg_replace('/[^\d+]/', '', $l['phone'])) . '">Zadzwoń ' . e($l['phone']) . '</a>' : '')
        . (!empty($l['src']['uid']) ? '<a class="btn ghost" href="' . P . '?a=mail&box=INBOX&uid=' . (int)$l['src']['uid'] . (($l['src']['acc'] ?? '') === 'k' ? '&acc=k' : '') . '">Otwórz zapytanie</a>' : '');
    $next = '<form class="inl" method="post" action="' . P . '?a=lead_next">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><div class="pills" style="grid-template-columns:repeat(3,1fr)">'
        . '<button name="next_at" value="+1">Jutro</button><button name="next_at" value="+3">Za 3 dni</button><button name="next_at" value="+7">Za tydzień</button></div>'
        . '<input class="in" name="next_note" value="' . $v('next_note') . '" placeholder="Co zrobić"></form>';
    $au = $l['audit']; $audit = '';
    if ($au) {
        $pts = ''; foreach ($au['points'] ?? [] as $p) $pts .= '<li>' . e($p) . '</li>';
        $audit = '<section class="card pad sec"><div class="hrow"><h2>Audyt strony</h2><span class="sc' . ((int)$l['score'] >= 50 ? ' hi' : '') . '">' . (int)$l['score'] . '</span></div>'
            . '<div class="kv"><div><span>Adres</span><a href="' . e($au['url'] ?? ('https://' . $au['domain'])) . '" target="_blank" rel="noopener">' . e($au['domain'] ?? '') . '</a></div>'
            . (isset($au['psi']) && $au['psi'] !== null ? '<div><span>PageSpeed (mobile)</span><span>' . (int)$au['psi'] . '/100</span></div>' : '') . (!empty($au['cms']) ? '<div><span>System</span><span>' . e($au['cms']) . '</span></div>' : '')
            . (!empty($au['year']) ? '<div><span>Rok w stopce</span><span>' . (int)$au['year'] . '</span></div>' : '') . (!empty($au['emails']) ? '<div><span>E-mail na stronie</span><span>' . e(implode(', ', $au['emails'])) . '</span></div>' : '')
            . '<div><span>Sprawdzone</span><span>' . e(date('d.m.Y', strtotime($au['at'] ?? 'now'))) . '</span></div></div>'
            . '<b style="font-weight:500">O czym porozmawiać</b><ul class="pts">' . $pts . '</ul>' . $f('lead_rescan', 'Sprawdź ponownie', 'link') . '</section>';
    }
    $hero = '<div class="ihero"><span class="ik">' . e(LEAD_SOURCES[$l['source']] ?? '') . ' · od ' . e(date('d.m.Y', strtotime($l['created']))) . '</span><span class="iv" style="font-size:30px">' . e($l['company'] ?: $l['name'] ?: $l['website']) . '</span>'
        . '<span class="is">' . lead_chip($l['stage']) . ($l['value'] ? '<span>' . money((int)$l['value']) . ' netto</span>' : '') . ($l['next_at'] ? '<span class="' . ($l['next_at'] <= today() ? 'late-t' : 'mut') . '">' . e(pl_date($l['next_at'])) . ': ' . e((string)$l['next_note']) . '</span>' : '') . '</span></div>';
    $body = '<div class="cols"><div class="wide sec">' . $audit . '<section class="card pad sec"><h2>Dane i notatki</h2>' . $form . '</section></div>'
        . '<aside class="side"><div class="card pad sec">' . $hero . '<div class="acts-col">' . $acts . '</div><details class="more"><summary>Następny krok</summary>' . $next . '</details><details class="more"><summary>Zmień etap</summary><div class="inl">' . $stages . '</div></details>'
        . '<details class="more"><summary>Usuń</summary>' . $f('lead_delete', 'Usuń szansę', 'btn danger') . '</details></div></aside></div>';
    $GLOBALS['back'] = [P . '?a=leads', 'Szanse']; $GLOBALS['pgcls'] = 'pg-inv';
    page($l['company'] ?: ($l['name'] ?: 'Szansa'), 'leads', $body, 'Szansa sprzedaży');
}

function view_tender(int $id): never {
    leads_migrate();
    $q = db()->prepare('SELECT * FROM tenders WHERE id = ?'); $q->execute([$id]); $t = $q->fetch();
    if (!$t) redirect(P . '?a=leads&t=tenders');
    $body = tender_body($id);
    if (job_is($t)) view_job($t, $body);
    $left = $t['deadline'] ? days_between(today(), substr($t['deadline'], 0, 10)) : null;
    $f = fn($a, $l, $c = 'btn') => '<form method="post" action="' . P . '?a=' . $a . '">' . csrf_field() . '<input type="hidden" name="id" value="' . $t['id'] . '"><button class="' . $c . '" style="width:100%">' . $l . '</button></form>';
    $acts = $f('tender_quote', icon('spark') . ' Wyceń' . (ai_enabled() ? ' z AI' : ''))
        . ($t['lead_id'] ? '<a class="btn ghost" href="' . P . '?a=lead&id=' . $t['lead_id'] . '">Szansa w ofertach ' . icon('arrow') . '</a>' : $f('tender_lead', icon('target') . ' Do ofert', 'btn ghost'))
        . '<a class="btn ghost" href="' . e($t['url']) . '" target="_blank" rel="noopener">Otwórz ogłoszenie ' . (['bk' => 'w Bazie Konkurencyjności', 'bzp' => 'w BZP', 'ted' => 'w TED'][$t['src']] ?? '') . ' ↗</a>'
        . ($t['status'] !== 'hidden' && !$t['lead_id'] ? $f('tender_hide', icon('x') . ' Ignoruj', 'btn ghost ignore') : '');
    $t['body'] = $body; tender_summary($t);
    $kv = '<div class="kv">' . (!empty($t['budget']) ? '<div><span>Budżet</span><span><b style="font-weight:500">' . e((string)$t['budget']) . '</b></span></div>' : '<div><span>Budżet</span><span class="mut">nie podano</span></div>') . '<div><span>Zamawiający</span><span>' . e($t['buyer']) . '</span></div><div><span>Miejscowość</span><span>' . e($t['city'] . ($t['province'] ? ' · ' . $t['province'] : '')) . '</span></div>'
        . (!empty($t['nip']) ? '<div><span>NIP</span><span>' . e($t['nip']) . '</span></div>' : '') . '<div><span>Numer</span><span>' . e($t['ref']) . '</span></div><div><span>Opublikowano</span><span>' . e(pl_date($t['published'])) . '</span></div><div><span>CPV</span><span style="text-align:right">' . e(mb_strimwidth((string)$t['cpv'], 0, 160, '…')) . '</span></div></div>';
    $hero = '<div class="ihero"><span class="ik">' . e(TENDER_CATS[tender_cat((string)$t['title'], (string)$t['cpv'])]) . ' · dopasowanie ' . (int)$t['score'] . '/100</span><span class="iv" style="font-size:22px;line-height:1.3">' . e(tender_title((string)$t['title'])) . '</span>'
        . '<span class="is"><span class="' . ($left !== null && $left <= 3 ? 'late-t' : 'mut') . '">' . ($t['deadline'] ? 'Oferty do ' . e(date('d.m.Y H:i', strtotime($t['deadline']))) . ($left !== null ? ' (' . ($left > 0 ? 'za ' . $left . ' ' . plural($left, 'dzień', 'dni', 'dni') : ($left === 0 ? 'dziś' : 'po terminie')) . ')' : '') : 'Bez terminu') . '</span></span>' . (!empty($t['excerpt']) && $t['excerpt'] !== '-' ? '<span class="is" style="margin-top:10px;line-height:1.5">' . e((string)$t['excerpt']) . '</span>' : '') . '</div>';
    $body = '<div class="cols"><div class="wide"><section class="card pad sec"><h2>Treść ogłoszenia</h2>' . ($body !== '' ? '<div class="mtext tbody">' . nl2br(e($body)) . '</div>' : '<span class="mut">Nie udało się pobrać treści. Otwórz ogłoszenie w serwisie.</span>') . '</section></div>'
        . '<aside class="side"><div class="card pad sec">' . $hero . '<div class="acts-col">' . $acts . '</div>' . $kv . '</div></aside></div>';
    $GLOBALS['back'] = [P . '?a=leads&t=tenders', 'Przetargi']; $GLOBALS['pgcls'] = 'pg-inv';
    page($t['src'] === 'bk' ? 'Zapytanie ofertowe' : 'Przetarg', 'leads', $body, $t['buyer']);
}
require_once __DIR__ . '/jobs.php';

/** Gdy klient odpisze na wysłaną wycenę (z adresu, na który poszła), karta przechodzi do kolumny Odpowiedź. Sprawdzane co 5 minut w obu skrzynkach. */
function lead_reply_check(): int {
    leads_migrate();
    if (time() - (int)setting('reply_chk_at') < 50) return 0;
    set_setting('reply_chk_at', (string)time());
    $map = [];
    foreach (db()->query("SELECT q.buyer, q.src, q.number FROM quotes q WHERE q.sent_at IS NOT NULL AND q.status = 'sent'")->fetchAll() as $q) {
        $src = json_decode((string)$q['src'], true) ?: []; $lid = (int)($src['lead'] ?? 0); if (!$lid) continue;
        $b = json_decode((string)$q['buyer'], true) ?: [];
        foreach (array_merge([(string)($b['email'] ?? '')], preg_split('/[\s,;]+/', (string)($b['cc'] ?? '')) ?: []) as $em) { $em = strtolower(trim($em)); if ($em !== '') $map[$em][$lid] = (string)$q['number']; }
    }
    foreach (db()->query("SELECT id, email FROM leads WHERE stage = 'offer' AND email IS NOT NULL AND email != ''")->fetchAll() as $l) $map[strtolower(trim((string)$l['email']))][(int)$l['id']] = '';
    $n = 0; $cur = mail_acc();
    foreach (['f', 'k'] as $ac) {
        mail_acc($ac); if (!inbox_enabled()) continue;
        try {
            $im = imap_open_box(); $im->select('INBOX');
            $all = $im->uids('ALL'); $max = max(array_merge([0], $all)); $last = (int)setting('reply_uid_' . $ac);
            set_setting('reply_uid_' . $ac, (string)$max);
            if ($last === 0 || !$map) continue;
            $new = array_values(array_filter($all, fn($u) => $u > $last)); if (!$new) continue;
            foreach ($im->headers(array_slice($new, -30)) as $m) {
                [, $from] = mail_addr((string)($m['h']['from'] ?? '')); $from = strtolower($from);
                if ($from === '' || str_ends_with($from, '@creative-vision.pl') || empty($map[$from])) continue;
                $subj = mime_hdr_decode((string)($m['h']['subject'] ?? ''));
                foreach ($map[$from] as $lid => $num) {
                    $s = db()->prepare("UPDATE leads SET stage = 'nego', next_at = ?, next_note = ?, updated = ? WHERE id = ? AND stage = 'offer'");
                    $s->execute([today(), 'Klient odpisał' . ($num ? ' na wycenę ' . $num : '') . ': ' . mb_strimwidth($subj, 0, 90, '…'), now(), $lid]);
                    if ($s->rowCount()) { $n++; $l = lead_by_id($lid); if ($l && function_exists('push_send')) push_send('Klient odpisał na wycenę', ($l['company'] ?: $l['name']) . ': ' . mb_strimwidth($subj, 0, 80, '…'), '/panel?a=lead&id=' . $lid, 'reply-' . $lid); }
                }
            }
            unset($im);
        } catch (Throwable $e) { /* następnym razem */ }
    }
    mail_acc($cur);
    return $n;
}
