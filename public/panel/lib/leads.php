<?php
// Szanse sprzedażowe: lejek, przetargi (BZP i TED) i skaner stron firmowych.
declare(strict_types=1);

const LEAD_STAGES = ['new' => 'Nowa', 'contact' => 'W kontakcie', 'offer' => 'Wycena wysłana', 'won' => 'Wygrana', 'lost' => 'Przegrana'];
const LEAD_SOURCES = ['reczne' => 'Dodana ręcznie', 'mail' => 'Zapytanie mailowe', 'przetarg' => 'Przetarg', 'skaner' => 'Skaner stron', 'polecenie' => 'Polecenie'];
const TENDER_KW_DEFAULT = "stron internetow\nstrony internetowej\nserwis internetow\nserwisu internetowego\nportal\nsklep internetow\ne-commerce\naplikacj mobiln\naplikacji internetowej\nplatform\nBIP\nidentyfikacj wizualn\nprojekt graficzn\nUX\nUI\nwww";

function leads_migrate(): void {
    static $d = false; if ($d) return; $d = true;
    db()->exec('CREATE TABLE IF NOT EXISTS leads (id INTEGER PRIMARY KEY, name TEXT, company TEXT, email TEXT, phone TEXT, website TEXT, source TEXT NOT NULL DEFAULT \'reczne\',
        stage TEXT NOT NULL DEFAULT \'new\', value INTEGER, next_at TEXT, next_note TEXT, notes TEXT, score INTEGER, audit TEXT, src TEXT, quote_id INTEGER, created TEXT NOT NULL, updated TEXT NOT NULL)');
    db()->exec('CREATE TABLE IF NOT EXISTS tenders (id INTEGER PRIMARY KEY, src TEXT NOT NULL, ref TEXT UNIQUE NOT NULL, title TEXT, buyer TEXT, city TEXT, province TEXT, cpv TEXT,
        deadline TEXT, published TEXT, url TEXT, score INTEGER, status TEXT NOT NULL DEFAULT \'new\', lead_id INTEGER, created TEXT NOT NULL)');
}
function lead_by_id(int $id): ?array { leads_migrate(); $s = db()->prepare('SELECT * FROM leads WHERE id = ?'); $s->execute([$id]); $r = $s->fetch(); if (!$r) return null; foreach (['name', 'company', 'email', 'phone', 'website', 'next_note', 'notes', 'source', 'stage'] as $k) $r[$k] = (string)($r[$k] ?? ''); $r['audit'] = json_decode((string)$r['audit'], true) ?: []; $r['src'] = json_decode((string)$r['src'], true) ?: []; return $r; }
function lead_chip(string $st): string { $c = ['new' => 'draft', 'contact' => 'due', 'offer' => 'decl', 'won' => 'paid', 'lost' => 'cancelled'][$st] ?? 'due'; return '<span class="chip ' . $c . '">' . (LEAD_STAGES[$st] ?? $st) . '</span>'; }
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
    set_setting('tender_at', now());
    return [$new, $err];
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
    push_send(count($rows) === 1 ? 'Dziś: ' . ($first['company'] ?: $first['name']) : 'Dziś ' . count($rows) . ' follow-upy w lejku', (string)($first['next_note'] ?: 'Zaplanowany kontakt') . (count($rows) > 1 ? ' i więcej' : ''), '/panel?a=lead&id=' . $first['id'], 'leads');
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
            redirect(P . '?a=lead&id=' . $id);
        case 'lead_delete':
            db()->prepare('DELETE FROM leads WHERE id = ?')->execute([$id]); db()->prepare("UPDATE tenders SET lead_id = NULL, status = 'new' WHERE lead_id = ?")->execute([$id]);
            flash('Szansa usunięta.'); redirect(P . '?a=leads');
        case 'lead_from_mail':
            mail_acc((string)($_POST['acc'] ?? 'f'));
            $inq = inquiry_from_mail((int)($_POST['uid'] ?? 0)); mail_acc('f');
            if (!$inq) redirect(P . '?a=leads');
            $id = lead_save(['name' => $inq['from'], 'email' => $inq['email'], 'company' => '', 'source' => 'mail', 'stage' => 'new', 'next_at' => today(), 'next_note' => 'Odpowiedzieć na zapytanie',
                'notes' => trim(($inq['subject'] !== '' ? $inq['subject'] . "\n\n" : '') . mb_substr($inq['text'], 0, 3000)), 'src' => array_diff_key($inq, ['text' => 1])]);
            flash('Zapytanie dodane do lejka.'); redirect(P . '?a=lead&id=' . $id);
        case 'tender_lead':
            $t = db()->prepare('SELECT * FROM tenders WHERE id = ?'); $t->execute([$id]); $t = $t->fetch();
            if (!$t) redirect(P . '?a=leads&t=tenders');
            if ($t['lead_id']) redirect(P . '?a=lead&id=' . $t['lead_id']);
            $lid = lead_save(['name' => '', 'company' => $t['buyer'], 'source' => 'przetarg', 'stage' => 'new', 'next_at' => $t['deadline'] ? date('Y-m-d', strtotime(substr($t['deadline'], 0, 10) . ' -3 days')) : today(),
                'next_note' => 'Przygotować ofertę do przetargu' . ($t['deadline'] ? ' (termin ' . date('d.m H:i', strtotime($t['deadline'])) . ')' : ''), 'website' => $t['url'], 'notes' => $t['title'] . "\n" . $t['ref'] . ' · ' . $t['city'] . "\n" . $t['url'], 'src' => ['tender' => (int)$t['id']], 'score' => (int)$t['score']]);
            db()->prepare("UPDATE tenders SET status = 'saved', lead_id = ? WHERE id = ?")->execute([$lid, $id]);
            flash('Przetarg dodany do lejka.'); redirect(P . '?a=lead&id=' . $lid);
        case 'tender_hide':
            db()->prepare("UPDATE tenders SET status = 'hidden' WHERE id = ?")->execute([$id]); redirect(P . '?a=leads&t=tenders');
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
    $tab = (string)($_GET['t'] ?? 'pipe'); if (!in_array($tab, ['pipe', 'tenders', 'scan'], true)) $tab = 'pipe';
    $tabs = '';
    $newT = (int)db()->query("SELECT COUNT(*) FROM tenders WHERE status = 'new' AND (deadline IS NULL OR deadline = '' OR deadline >= date('now'))")->fetchColumn();
    foreach (['pipe' => 'Lejek', 'tenders' => 'Przetargi', 'scan' => 'Skaner stron'] as $k => $l) $tabs .= '<a href="' . P . '?a=leads&t=' . $k . '"' . ($tab === $k ? ' class="on" aria-current="true"' : '') . '>' . $l . ($k === 'tenders' && $newT ? ' <span>' . $newT . '</span>' : '') . '</a>';
    $body = '';
    if ($tab === 'pipe') {
        $st = (string)($_GET['s'] ?? 'open');
        $all = db()->query('SELECT * FROM leads ORDER BY CASE WHEN next_at IS NULL THEN 1 ELSE 0 END, next_at, updated DESC')->fetchAll();
        $open = array_filter($all, fn($l) => !in_array($l['stage'], ['won', 'lost'], true));
        $val = array_sum(array_map(fn($l) => (int)$l['value'], $open));
        $due = array_filter($open, fn($l) => $l['next_at'] && $l['next_at'] <= today());
        $wonM = array_filter($all, fn($l) => $l['stage'] === 'won' && substr((string)$l['updated'], 0, 7) === date('Y-m'));
        $body .= '<section class="kpis"><div class="kpi dark"><span class="t">Otwarte szanse</span><span class="v">' . money($val) . '</span><span class="s">' . count($open) . ' ' . plural(count($open), 'szansa', 'szanse', 'szans') . ' w lejku, wartość netto</span></div><div class="kpis-r">'
            . '<div class="kpi"><span class="t">Na dziś</span><span class="v' . ($due ? ' red' : '') . '">' . count($due) . '</span><span class="s">' . plural(count($due), 'follow-up', 'follow-upy', 'follow-upów') . ' do zrobienia</span></div>'
            . '<div class="kpi"><span class="t">Wygrane w tym miesiącu</span><span class="v">' . count($wonM) . '</span><span class="s">' . money(array_sum(array_map(fn($l) => (int)$l['value'], $wonM))) . '</span></div></div></section>';
        $chips = '';
        foreach (['open' => 'Otwarte'] + LEAD_STAGES + ['all' => 'Wszystkie'] as $k => $l) { $n = count(array_filter($all, fn($x) => $k === 'all' || ($k === 'open' ? !in_array($x['stage'], ['won', 'lost'], true) : $x['stage'] === $k)));
            $chips .= '<a href="' . P . '?a=leads&s=' . $k . '"' . ($st === $k ? ' class="on"' : '') . '>' . $l . ' <span>' . $n . '</span></a>'; }
        $rows = array_filter($all, fn($x) => $st === 'all' || ($st === 'open' ? !in_array($x['stage'], ['won', 'lost'], true) : $x['stage'] === $st));
        $tr = '';
        foreach ($rows as $l) {
            $url = P . '?a=lead&id=' . $l['id']; $late = $l['next_at'] && $l['next_at'] <= today() && !in_array($l['stage'], ['won', 'lost'], true);
            $tr .= '<tr class="row" data-href="' . $url . '"><td class="c-num nw"><span class="num" style="border:0">' . e(LEAD_SOURCES[$l['source']] ?? $l['source']) . '</span></td>'
                . '<td class="c-cli"><span>' . e($l['company'] ?: $l['name'] ?: $l['website']) . '</span><span class="sub">' . e(trim(($l['company'] ? $l['name'] : '') . ' ' . ($l['next_note'] ?? ''))) . '</span></td>'
                . '<td class="c-due nw' . ($late ? ' late-t' : '') . '">' . ($l['next_at'] ? e(pl_date($l['next_at'])) : '') . '<span class="sub">' . ($late ? 'do zrobienia' : '') . '</span></td>'
                . '<td class="c-amt r amt">' . ($l['value'] ? money((int)$l['value']) : ($l['score'] !== null ? '<span class="mut">potencjał ' . (int)$l['score'] . '</span>' : '')) . '</td><td class="c-st nw">' . lead_chip($l['stage']) . '</td><td class="c-act"></td></tr>';
        }
        $body .= '<section class="card"><nav class="tabs">' . $tabs . '</nav><nav class="tabs sub">' . $chips . '</nav>'
            . ($tr ? '<div class="tw"><table class="list"><tbody>' . $tr . '</tbody></table></div>' : '<div class="empty">Pusto. Dodaj szansę, zapisz przetarg albo przeskanuj strony firm.</div>') . '</section>';
    } elseif ($tab === 'tenders') {
        $rows = db()->query("SELECT * FROM tenders WHERE status != 'hidden' AND (deadline IS NULL OR deadline = '' OR deadline >= date('now')) ORDER BY status = 'saved', score DESC, deadline LIMIT 100")->fetchAll();
        $tr = '';
        foreach ($rows as $t) {
            $left = $t['deadline'] ? days_between(today(), substr($t['deadline'], 0, 10)) : null;
            $f = fn($a, $l, $c = 'link') => '<form method="post" action="' . P . '?a=' . $a . '">' . csrf_field() . '<input type="hidden" name="id" value="' . $t['id'] . '"><button class="' . $c . '">' . $l . '</button></form>';
            $tr .= '<div class="tnd"><div class="tnd-h"><span class="sc' . ($t['score'] >= 50 ? ' hi' : '') . '">' . (int)$t['score'] . '</span><div><b>' . e($t['title']) . '</b><span class="mut">' . e($t['buyer'] . ' · ' . $t['city'] . ($t['province'] ? ' · ' . $t['province'] : '')) . '</span></div></div>'
                . '<div class="tnd-f"><span class="' . ($left !== null && $left <= 3 ? 'late-t' : 'mut') . '">' . ($t['deadline'] ? 'Oferty do ' . e(date('d.m.Y H:i', strtotime($t['deadline']))) . ($left !== null ? ' (' . ($left > 0 ? 'za ' . $left . ' ' . plural($left, 'dzień', 'dni', 'dni') : 'dziś') . ')' : '') : 'Bez terminu') . ' · ' . strtoupper($t['src']) . '</span>'
                . '<a class="link" href="' . e($t['url']) . '" target="_blank" rel="noopener">Ogłoszenie</a>'
                . ($t['lead_id'] ? '<a class="link" href="' . P . '?a=lead&id=' . $t['lead_id'] . '">W lejku</a>' : $f('tender_lead', 'Do lejka') . $f('tender_hide', 'Ukryj')) . '</div></div>';
        }
        $at = setting('tender_at');
        $body .= '<section class="card"><nav class="tabs">' . $tabs . '</nav><div class="hrow pad" style="flex-wrap:wrap"><span class="mut" style="font-size:14px">Ogłoszenia z Biuletynu Zamówień Publicznych i TED: strony, portale, sklepy, aplikacje, grafika. Sprawdzamy codziennie rano' . ($at ? ', ostatnio ' . e(date('d.m H:i', strtotime($at))) : '') . '.</span>'
            . '<form method="post" action="' . P . '?a=tenders_fetch">' . csrf_field() . '<input type="hidden" name="days" value="7"><button class="btn ghost sm" data-busy>' . icon('refresh') . ' Sprawdź teraz</button></form></div>'
            . ($tr ? '<div class="tnds">' . $tr . '</div>' : '<div class="empty">Brak pasujących przetargów. Kliknij „Sprawdź teraz” albo poszerz słowa kluczowe.</div>') . '</section>'
            . '<section class="card pad sec"><details class="more"><summary>Ustawienia przetargów</summary><form class="inl" method="post" action="' . P . '?a=tender_settings">' . csrf_field()
            . '<label class="f">Słowa kluczowe (jedno w linii, wystarczy początek słowa)<textarea class="in" name="tender_kw" rows="8">' . e(setting('tender_kw') ?: TENDER_KW_DEFAULT) . '</textarea></label>'
            . '<label class="f">Województwa z bonusem (kody NUTS, np. PL18 podkarpackie, PL21 małopolskie, PL81 lubelskie)<input class="in" name="tender_prov" value="' . e(setting('tender_prov') ?: 'PL18') . '"></label>'
            . '<label class="f">Minimalna ocena dopasowania (0 do 100)<input class="in" type="number" name="tender_min" min="0" max="100" value="' . e(setting('tender_min') ?: '20') . '"></label>'
            . '<label class="check"><input type="checkbox" name="tender_on" value="1"' . (setting('tender_on') !== '0' ? ' checked' : '') . '> Sprawdzaj codziennie i daj znać o najlepszych (powiadomienie)</label><button class="btn ghost sm">Zapisz</button></form></details></section>';
    } else {
        $rows = db()->query("SELECT * FROM leads WHERE audit IS NOT NULL AND audit != '' ORDER BY score DESC, updated DESC LIMIT 100")->fetchAll();
        $tr = '';
        foreach ($rows as $l) { $au = json_decode((string)$l['audit'], true) ?: []; $url = P . '?a=lead&id=' . $l['id'];
            $tr .= '<tr class="row" data-href="' . $url . '"><td class="c-num nw"><span class="num" style="border:0">' . (int)$l['score'] . '</span></td><td class="c-cli"><span>' . e($l['company'] ?: $l['website']) . '</span><span class="sub">' . e($l['website'] . (!empty($au['cms']) ? ' · ' . $au['cms'] : '')) . '</span></td>'
                . '<td class="c-due nw">' . (isset($au['psi']) && $au['psi'] !== null ? 'PageSpeed ' . (int)$au['psi'] : '') . '<span class="sub">' . (!empty($au['https']) ? 'HTTPS' : 'bez HTTPS') . ' · ' . (!empty($au['viewport']) ? 'mobile' : 'bez mobile') . '</span></td><td class="c-amt r amt">' . e(count($au['points'] ?? []) . ' uwag') . '</td><td class="c-st nw">' . lead_chip($l['stage']) . '</td><td class="c-act"></td></tr>'; }
        $body .= '<section class="card"><nav class="tabs">' . $tabs . '</nav><form class="pad sec" method="post" action="' . P . '?a=scan">' . csrf_field()
            . '<label class="f">Adresy stron firm, które chcesz sprawdzić (po jednym w linii, najwyżej kilka naraz)<textarea class="in" name="domains" rows="4" placeholder="firma-z-rzeszowa.pl&#10;innafirma.pl"></textarea></label>'
            . '<div class="hrow" style="flex-wrap:wrap"><span class="mut" style="font-size:14px">Sprawdzimy HTTPS, wersję mobilną, szybkość (PageSpeed), aktualność i dane kontaktowe. Każda strona trafi do lejka z oceną potencjału.</span><button class="btn" data-busy>' . icon('search') . ' Skanuj</button></div></form>'
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
    if (!$l) { $GLOBALS['back'] = [P . '?a=leads', 'Szanse']; page('Nowa szansa', 'leads', '<section class="card pad sec" style="max-width:860px">' . $form . '</section>', 'Lejek sprzedaży'); }
    $id = (int)$l['id'];
    $f = fn($a, $label, $cls = 'btn ghost', $extra = '') => '<form method="post" action="' . P . '?a=' . $a . '">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '">' . $extra . '<button class="' . $cls . '">' . $label . '</button></form>';
    $stages = ''; foreach (LEAD_STAGES as $k => $x) if ($k !== $l['stage']) $stages .= $f('lead_stage', $x, 'btn ghost sm', '<input type="hidden" name="stage" value="' . $k . '">');
    $quote = $l['quote_id'] ? quote_by_id((int)$l['quote_id']) : null;
    $acts = ($quote ? '<a class="btn" href="' . P . '?a=quote&id=' . $quote['id'] . '">Wycena ' . e($quote['number']) . ' ' . icon('arrow') . '</a>' : '<a class="btn" href="' . P . '?a=quote_new&lead=' . $id . '">' . icon('quote') . ' Utwórz wycenę</a>')
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
