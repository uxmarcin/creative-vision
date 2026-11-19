<?php
// Giełdy zleceń: Useme (Polska) i Freelancer.com (zagranica). Zlecenia trafiają do tabeli tenders z src useme / freelancer.
declare(strict_types=1);

const JOB_SRCS = ['useme', 'freelancer'];
const JOB_FEEDS = [
    ['serwisy-internetowe,34/strony-internetowe,96', 'www', 'Strony internetowe'],
    ['serwisy-internetowe,34/sklepy-internetowe,97', 'shop', 'Sklepy internetowe'],
    ['serwisy-internetowe,34/obsuga-stron-internetowych,98', 'www', 'Obsługa stron'],
    ['serwisy-internetowe,34/obsuga-sklepow-internetowych,99', 'shop', 'Obsługa sklepów'],
    ['programowanie-i-it,35/aplikacje-webowe,102', 'app', 'Aplikacje webowe'],
    ['programowanie-i-it,35/oprogramowanie,100', 'app', 'Oprogramowanie'],
    ['programowanie-i-it,35/aplikacje-mobilne,101', 'app', 'Aplikacje mobilne'],
    ['design,38/projektowanie-ux-ui,121', 'design', 'Projektowanie UX/UI'],
];
const JOB_FL_SKILLS = ['Website Design' => 'www', 'Web Development' => 'www', 'WordPress' => 'www', 'Landing Pages' => 'www', 'eCommerce' => 'shop', 'Shopify' => 'shop', 'WooCommerce' => 'shop',
    'Prestashop' => 'shop', 'Magento' => 'shop', 'UX / User Experience' => 'design', 'User Interface / IA' => 'design', 'Figma' => 'design', 'Mobile App Development' => 'app', 'Web Application' => 'app', 'Software Development' => 'app'];

function jobs_migrate(): void {
    static $d = false; if ($d) return; $d = true; leads_migrate();
    $tc = array_column(db()->query('PRAGMA table_info(tenders)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('budget', $tc, true)) { db()->exec('ALTER TABLE tenders ADD COLUMN budget TEXT'); db()->exec('ALTER TABLE tenders ADD COLUMN offers INTEGER'); db()->exec('ALTER TABLE tenders ADD COLUMN excerpt TEXT'); db()->exec('ALTER TABLE tenders ADD COLUMN cat TEXT'); }
}
function job_is(array $t): bool { return in_array($t['src'] ?? '', JOB_SRCS, true); }
function job_get(string $url, int $timeout = 20): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36', CURLOPT_HTTPHEADER => ['Accept-Language: pl-PL,pl;q=0.9']]);
    $b = curl_exec($ch); $c = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['code' => $c, 'body' => $b === false ? '' : (string)$b];
}
function job_txt(string $h): string { return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''); }
/** Największa kwota z budżetu w złotych (EUR i USD po kursie orientacyjnym). */
function job_budget_pln(string $b): int {
    if (!preg_match_all('/(\d[\d\s.]*)(?:,\d{2})?/u', str_replace("\u{00A0}", ' ', $b), $m)) return 0;
    $n = max(array_map(fn($x) => (int)preg_replace('/\D/', '', $x), $m[1]) ?: [0]);
    if (stripos($b, 'EUR') !== false || str_contains($b, '€')) $n *= 4.3; elseif (stripos($b, 'USD') !== false || str_contains($b, '$')) $n *= 3.7; elseif (stripos($b, 'GBP') !== false || str_contains($b, '£')) $n *= 5;
    return (int)$n;
}
function job_score(string $cat, string $title, string $excerpt, string $budget, int $offers, string $src): int {
    $s = ['www' => 35, 'shop' => 35, 'design' => 30, 'app' => 25][$cat] ?? 15;
    $t = mb_strtolower($title . ' ' . $excerpt); $pln = job_budget_pln($budget);
    if ($pln >= 8000) $s += 30; elseif ($pln >= 3000) $s += 20; elseif ($pln >= 1200) $s += 10; elseif ($pln > 0 && $pln < 500) $s -= 20; elseif ($pln === 0) $s += 5;
    if ($offers <= 10) $s += 20; elseif ($offers <= 40) $s += 10; elseif ($offers > 150) $s -= 10;
    if (preg_match('/wordpress|woocommerce|prestashop|shopify|figma|\bux\b|\bui\b|redesign|projekt graficzny strony|sklep internetowy|nowa strona|nowej strony|landing|strony firmowej|strona firmowa/u', $t)) $s += 15;
    if (preg_match('/pozycjonowan|linkowan|artykuł|copywriting|tłumacz|\bbot\b|minecraft/u', mb_strtolower($title)) || preg_match('/na etat|umow[aęy] o pracę|praca stacjonarn/u', $t)) $s -= 15;
    if ($src === 'freelancer') $s -= 15;
    return max(0, min(100, $s));
}
function job_store(array $j): bool {
    $q = db()->prepare('SELECT id FROM tenders WHERE ref = ?'); $q->execute([$j['ref']]);
    if ($id = $q->fetchColumn()) { db()->prepare('UPDATE tenders SET offers = ?, deadline = ?, budget = ? WHERE id = ?')->execute([$j['offers'], $j['deadline'], $j['budget'], $id]); return false; }
    $j['score'] = job_score($j['cat'], $j['title'], $j['excerpt'], $j['budget'], (int)$j['offers'], $j['src']);
    $j += ['city' => '', 'province' => '', 'nip' => '', 'status' => 'new', 'created' => now()];
    db()->prepare('INSERT INTO tenders (' . implode(', ', array_keys($j)) . ') VALUES (:' . implode(', :', array_keys($j)) . ')')->execute($j);
    return true;
}
/** Lista zleceń z jednej strony kategorii Useme. */
function useme_parse(string $h, string $cat, string $label): array {
    $out = [];
    foreach (array_slice(explode('<article class="job', $h), 1) as $a) {
        if (!preg_match('#href="(/pl/jobs/[^"]+,(\d+)/)"[^>]*job__title[^>]*>\s*(.*?)\s*</a>#s', $a, $t)) continue;
        $left = preg_match('/Znika za (\d+)\s*(dni|dzie|godz|min)/u', $a, $z) ? (str_starts_with($z[2], 'dz') || $z[2] === 'dni' ? (int)$z[1] : 0) : 14;
        $out[] = ['src' => 'useme', 'ref' => 'useme-' . $t[2], 'title' => job_txt($t[3]), 'url' => 'https://useme.com' . $t[1], 'cat' => $cat, 'cpv' => $label,
            'excerpt' => preg_match('#</a>\s*<p[^>]*>(.*?)</p>#s', $a, $p) ? mb_substr(job_txt($p[1]), 0, 400) : '',
            'budget' => preg_match('#job__budget-value">\s*(.*?)\s*</span>#s', $a, $b) ? job_txt($b[1]) : '',
            'offers' => preg_match('#job__header-details--offers.*?<span>(\d+)</span>#s', $a, $o) ? (int)$o[1] : 0,
            'buyer' => preg_match('#<strong aria-label="([^"]*)"#', $a, $u) ? html_entity_decode($u[1], ENT_QUOTES) : '',
            'deadline' => date('Y-m-d 23:59', strtotime('+' . $left . ' days')), 'published' => today()];
    }
    return $out;
}
function freelancer_fetch(): array {
    $out = []; $seen = [];
    foreach (['website design', 'ecommerce website', 'web app', 'ux ui design'] as $qq) {
        $r = job_get('https://www.freelancer.com/api/projects/0.1/projects/active/?query=' . rawurlencode($qq) . '&limit=30&full_description=true&job_details=true&sort_field=time_updated', 25);
        $p = json_decode($r['body'], true)['result']['projects'] ?? null; if (!is_array($p)) continue;
        foreach (freelancer_parse($p) as $j) if (!isset($seen[$j['ref']])) { $seen[$j['ref']] = 1; $out[] = $j; }
    }
    return $out;
}
function freelancer_parse(array $p): array {
    $out = []; $seen = [];
    {
        foreach ($p as $x) {
            if (isset($seen[$x['id']]) || ($x['type'] ?? '') === 'hourly') continue; $seen[$x['id']] = 1;
            $cat = ''; foreach ((array)($x['jobs'] ?? []) as $jb) if (isset(JOB_FL_SKILLS[$jb['name'] ?? ''])) { $cat = JOB_FL_SKILLS[$jb['name']]; break; }
            if ($cat === '') continue;
            $usd = (float)($x['budget']['maximum'] ?? $x['budget']['minimum'] ?? 0) * (float)($x['currency']['exchange_rate'] ?? 1);
            if ($usd < 500) continue;
            $cur = (string)($x['currency']['code'] ?? 'USD');
            $out[] = ['src' => 'freelancer', 'ref' => 'fl-' . $x['id'], 'title' => (string)$x['title'], 'url' => 'https://www.freelancer.com/projects/' . $x['seo_url'], 'cat' => $cat,
                'cpv' => implode(', ', array_slice(array_column((array)$x['jobs'], 'name'), 0, 5)), 'excerpt' => mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$x['description'])), 0, 400),
                'budget' => number_format((float)$x['budget']['minimum'], 0, ',', ' ') . ' do ' . number_format((float)($x['budget']['maximum'] ?? 0), 0, ',', ' ') . ' ' . $cur . ' (ok. ' . number_format($usd * 3.7, 0, ',', ' ') . ' zł)',
                'offers' => (int)($x['bid_stats']['bid_count'] ?? 0), 'buyer' => 'Freelancer.com', 'body' => mb_substr((string)$x['description'], 0, 15000),
                'deadline' => date('Y-m-d 23:59', ((int)($x['time_submitted'] ?? time())) + 14 * 86400), 'published' => date('Y-m-d', (int)($x['time_submitted'] ?? time()))];
        }
    }
    return $out;
}
/** Pobiera nowe zlecenia z giełd. Zwraca [nowe, błędy]. */
function jobs_fetch(): array {
    jobs_migrate(); $new = []; $err = [];
    foreach (JOB_FEEDS as [$path, $cat, $label]) {
        $r = job_get('https://useme.com/pl/jobs/category/' . $path . '/');
        if ($r['code'] !== 200) { $err[] = 'Useme ' . $r['code']; continue; }
        foreach (useme_parse($r['body'], $cat, $label) as $j) if (job_store($j)) $new[] = $j;
        usleep(400000);
    }
    if (setting('jobs_fl') !== '0') { try { foreach (freelancer_fetch() as $j) if (job_store($j)) $new[] = $j; } catch (Throwable $e) { $err[] = 'Freelancer'; } }
    set_setting('jobs_at', now());
    return [$new, $err];
}
/** Co pół godziny w godzinach 7 do 22, powiadomienie o najlepszych nowych zleceniach. */
function run_jobs(): int {
    if (setting('jobs_on') === '0') return 0;
    $h = (int)date('G'); if ($h < 7 || $h >= 22) return 0;
    if (time() - strtotime(setting('jobs_at') ?: '2000-01-01') < 1700) return 0;
    $first = setting('jobs_at') === '';
    [$new] = jobs_fetch();
    if ($first) return count($new);
    $top = array_values(array_filter($new, fn($j) => job_score($j['cat'], $j['title'], $j['excerpt'], $j['budget'], (int)$j['offers'], $j['src']) >= (int)(setting('jobs_push') ?: 50)));
    if ($top && function_exists('push_send')) push_send(count($top) === 1 ? 'Nowe zlecenie dla Creative Vision' : count($top) . ' nowe zlecenia dla Creative Vision', mb_strimwidth($top[0]['title'], 0, 110, '…') . ($top[0]['budget'] ? ' · ' . $top[0]['budget'] : ''), '/panel?a=leads&t=jobs', 'jobs');
    return count($new);
}
/** Pełny opis zlecenia z Useme (JSON-LD na stronie ogłoszenia). */
function job_body_fetch(array $t): string {
    if ($t['src'] !== 'useme') return '';
    $r = job_get((string)$t['url']); if ($r['code'] !== 200) return '';
    $out = '';
    if (preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $r['body'], $m) && ($d = json_decode($m[1], true)) && !empty($d['description'])) $out = html_text((string)$d['description']);
    foreach (['Wymagane funkcje', 'Preferowane rozwiązanie', 'Prawa autorskie', 'Opublikowano'] as $k) if (preg_match('#jobs-summary__item-label">' . preg_quote($k, '#') . '</div>\s*<div class="[^"]*">(.*?)</div>#s', $r['body'], $mm) && ($v = job_txt($mm[1])) !== '') $out .= "\n" . $k . ': ' . $v;
    return trim($out);
}

/** Zakładka Zlecenia w Szansach: kafelki z giełd, od najświeższych i najlepiej dopasowanych. */
function view_jobs_tab(string $tabs): string {
    jobs_migrate();
    $all = db()->query("SELECT * FROM tenders WHERE status = 'new' AND src IN ('useme','freelancer') AND deadline >= date('now') ORDER BY substr(created, 1, 10) DESC, score DESC, id DESC LIMIT 300")->fetchAll();
    $cat = (string)($_GET['c'] ?? 'all'); $cats = TENDER_CATS + ['abroad' => 'Zagranica'];
    if ($cat !== 'all' && !isset($cats[$cat])) $cat = 'all';
    $cc = array_fill_keys(array_keys($cats), 0);
    foreach ($all as $t) { if ($t['src'] === 'freelancer') $cc['abroad']++; else $cc[$t['cat'] ?: 'other'] = ($cc[$t['cat'] ?: 'other'] ?? 0) + 1; }
    $rows = $cat === 'all' ? array_values(array_filter($all, fn($t) => $t['src'] !== 'freelancer')) : array_values(array_filter($all, fn($t) => $cat === 'abroad' ? $t['src'] === 'freelancer' : ($t['src'] !== 'freelancer' && ($t['cat'] ?: 'other') === $cat)));
    $pl = count($all) - $cc['abroad'];
    $ctabs = '<a href="' . P . '?a=leads&t=jobs"' . ($cat === 'all' ? ' class="on" aria-current="true"' : '') . '>Wszystkie w Polsce <span>' . $pl . '</span></a>';
    foreach ($cats as $k => $l) if ($cc[$k] || $cat === $k) $ctabs .= '<a href="' . P . '?a=leads&t=jobs&c=' . $k . '"' . ($cat === $k ? ' class="on" aria-current="true"' : '') . '>' . $l . ' <span>' . $cc[$k] . '</span></a>';
    $tiles = ''; $today = today();
    foreach ($rows as $t) {
        $c = $t['cat'] ?: 'other'; $fresh = substr((string)$t['created'], 0, 10) === $today;
        $left = days_between($today, substr((string)$t['deadline'], 0, 10));
        $meta = ($fresh ? 'Nowe, znalezione ' . date('H:i', strtotime($t['created'])) : 'Znalezione ' . date('d.m', strtotime($t['created']))) . ' · ofert: ' . (int)$t['offers'] . ' · znika za ' . $left . ' ' . plural($left, 'dzień', 'dni', 'dni');
        $tiles .= '<article class="kcard tile"><div class="kc-h"><span class="sc' . ($t['score'] >= 60 ? ' hi' : '') . '">' . (int)$t['score'] . '</span><span class="kc-cat c-' . $c . '">' . e(TENDER_CATS[$c] ?? 'Inne') . '</span><span class="kc-src" style="margin-left:auto">' . ($t['src'] === 'useme' ? 'USEME' : 'FREELANCER') . '</span></div>'
            . '<b class="kc-t">' . e((string)$t['title']) . '</b>' . ($t['excerpt'] ? '<span class="kc-x">' . e((string)$t['excerpt']) . '</span>' : '')
            . '<span class="kc-b"><b>' . e((string)($t['budget'] ?: 'Budżet do negocjacji')) . '</b>' . ($t['buyer'] && $t['src'] === 'useme' ? ' · ' . e((string)$t['buyer']) : '') . '</span>'
            . '<span class="kc-d' . ((int)$t['offers'] > 100 ? ' mut' : '') . '">' . e($meta) . '</span>'
            . '<div class="kc-f"><form method="post" action="' . P . '?a=tender_lead">' . csrf_field() . '<input type="hidden" name="id" value="' . $t['id'] . '"><button class="btn sm">' . icon('target') . ' Do ofert</button></form><div class="ias">'
            . ia('eye', 'Podgląd zlecenia', P . '?a=tender&id=' . $t['id']) . ia('external', 'Zlecenie na giełdzie', (string)$t['url'], ['blank' => true]) . ia('x', 'Pomiń', '', ['post' => 'tender_hide', 'id' => $t['id']]) . '</div></div></article>';
    }
    $at = setting('jobs_at');
    return '<section class="card"><nav class="tabs">' . $tabs . '</nav><div class="hrow pad" style="flex-wrap:wrap"><span class="mut" style="font-size:14px">Zlecenia na strony, sklepy, aplikacje i UX z Useme, a w zakładce Zagranica z Freelancer.com. Sprawdzamy co pół godziny od 7 do 22' . ($at ? ', ostatnio ' . e(date('d.m H:i', strtotime($at))) : '') . '. Na giełdzie liczy się szybkość, więc najlepsze zgłaszaj tego samego dnia.</span>'
        . '<form method="post" action="' . P . '?a=jobs_fetch">' . csrf_field() . '<button class="btn ghost sm" data-busy>' . icon('refresh') . ' Sprawdź teraz</button></form></div>'
        . ($all ? '<nav class="tabs sub" aria-label="Rodzaj zlecenia">' . $ctabs . '</nav>' : '') . '</section>'
        . ($tiles ? '<div class="ktiles">' . $tiles . '</div>' : '<section class="card"><div class="empty">Brak nowych zleceń. Kliknij „Sprawdź teraz”.</div></section>')
        . '<section class="card pad sec"><details class="more"><summary>Ustawienia zleceń</summary><form class="inl" method="post" action="' . P . '?a=jobs_settings">' . csrf_field()
        . '<label class="check"><input type="checkbox" name="jobs_on" value="1"' . (setting('jobs_on') !== '0' ? ' checked' : '') . '> Sprawdzaj co pół godziny i daj znać o najlepszych (powiadomienie)</label>'
        . '<label class="check"><input type="checkbox" name="jobs_fl" value="1"' . (setting('jobs_fl') !== '0' ? ' checked' : '') . '> Szukaj też zleceń zagranicznych na Freelancer.com (po angielsku, od ok. 2 000 zł)</label>'
        . '<label class="f">Powiadamiaj od oceny (0 do 100)<input class="in" type="number" name="jobs_push" min="0" max="100" value="' . e(setting('jobs_push') ?: '50') . '"></label><button class="btn ghost sm">Zapisz</button></form></details></section>';
}

function view_job(array $t, string $body): never {
    $id = (int)$t['id']; $c = $t['cat'] ?: 'other'; $left = days_between(today(), substr((string)$t['deadline'], 0, 10));
    $f = fn($a, $l, $cl = 'btn') => '<form method="post" action="' . P . '?a=' . $a . '">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><button class="' . $cl . '" style="width:100%">' . $l . '</button></form>';
    $site = $t['src'] === 'useme' ? 'Useme' : 'Freelancer.com';
    $acts = ($t['lead_id'] ? '<a class="btn" href="' . P . '?a=lead&id=' . $t['lead_id'] . '">Szansa w ofertach ' . icon('arrow') . '</a>' . (ai_enabled() ? '<a class="btn ghost" href="' . P . '?a=quote_new&lead=' . $t['lead_id'] . '&ai=1">' . icon('spark') . ' Przygotuj wycenę z AI</a>' : '') : $f('tender_lead', icon('target') . ' Do ofert'))
        . '<a class="btn ghost" href="' . e($t['url']) . '" target="_blank" rel="noopener">Złóż ofertę na ' . $site . ' ↗</a>';
    $kv = '<div class="kv"><div><span>Budżet</span><span>' . e((string)($t['budget'] ?: 'Do negocjacji')) . '</span></div><div><span>Ofert</span><span>' . (int)$t['offers'] . '</span></div>'
        . ($t['src'] === 'useme' && $t['buyer'] ? '<div><span>Zleceniodawca</span><span>' . e((string)$t['buyer']) . '</span></div>' : '')
        . '<div><span>Kategoria</span><span style="text-align:right">' . e(mb_strimwidth((string)$t['cpv'], 0, 120, '…')) . '</span></div><div><span>Znalezione</span><span>' . e(date('d.m.Y H:i', strtotime($t['created']))) . '</span></div></div>';
    $hero = '<div class="ihero"><span class="ik">' . e(TENDER_CATS[$c] ?? 'Inne') . ' · ' . $site . ' · dopasowanie ' . (int)$t['score'] . '/100</span><span class="iv" style="font-size:22px;line-height:1.3">' . e((string)$t['title']) . '</span>'
        . '<span class="is"><span class="mut">Znika za ' . $left . ' ' . plural($left, 'dzień', 'dni', 'dni') . '</span></span></div>';
    $html = '<div class="cols"><div class="wide"><section class="card pad sec"><h2>Opis zlecenia</h2>' . ($body !== '' ? '<div class="mtext tbody">' . nl2br(e($body)) . '</div>' : '<div class="mtext tbody">' . nl2br(e((string)$t['excerpt'])) . '</div><span class="mut" style="font-size:14px">Pełny opis jest na ' . $site . '.</span>') . '</section></div>'
        . '<aside class="side"><div class="card pad sec">' . $hero . '<div class="acts-col">' . $acts . '</div>' . $kv . '</div></aside></div>';
    $GLOBALS['back'] = [P . '?a=leads&t=jobs', 'Zlecenia']; $GLOBALS['pgcls'] = 'pg-inv';
    page('Zlecenie', 'leads', $html, $site);
}
