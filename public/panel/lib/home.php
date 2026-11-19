<?php
// Pulpit: na czym skupić się dziś, pieniądze, sprzedaż i leady w jednym miejscu.
declare(strict_types=1);

function view_home(): never {
    if (function_exists('leads_migrate')) leads_migrate();
    $today = today(); $ym = date('Y-m'); $prevYm = date('Y-m', strtotime('first day of last month'));
    $inv = array_map('hydrate', db()->query("SELECT * FROM invoices WHERE status != 'cancelled' ORDER BY id DESC")->fetchAll());
    $open = 0; $late = []; $soon = []; $drafts = 0; $ksefBad = []; $byMonth = []; $paidM = 0; $lastPaid = null;
    foreach ($inv as $i) {
        $st = inv_state($i); $pln = ($i['currency'] ?? 'PLN') === 'PLN';
        if ($st === 'draft') { $drafts++; continue; }
        $bym = (string)($i['book_ym'] ?: substr((string)($i['issue_date'] ?: $i['created']), 0, 7));
        if ($pln) $byMonth[$bym] = ($byMonth[$bym] ?? 0) + (int)$i['net'];
        if (in_array($st, ['due', 'today', 'late'], true) && $pln) $open += (int)$i['gross'];
        if ($st === 'late') $late[] = $i;
        if (in_array($st, ['due', 'today'], true) && days_between($today, (string)$i['due_date']) <= 7) $soon[] = $i;
        if (in_array((string)($i['ksef_state'] ?? ''), ['rejected', 'error'], true) && empty($i['ksef'])) $ksefBad[] = $i;
        if ($st === 'paid') { if (substr((string)$i['paid_at'], 0, 7) === $ym && $pln) $paidM += (int)$i['gross']; if (!$lastPaid || (string)$i['paid_at'] > (string)$lastPaid['paid_at']) $lastPaid = $i; }
    }
    $leads = db()->query("SELECT * FROM leads WHERE stage NOT IN ('won', 'lost') ORDER BY next_at IS NULL, next_at")->fetchAll();
    $pipe = array_sum(array_map(fn($l) => (int)$l['value'], $leads));
    $due = array_values(array_filter($leads, fn($l) => $l['next_at'] && $l['next_at'] <= $today));
    $replied = array_values(array_filter($leads, fn($l) => $l['stage'] === 'nego'));
    $qs = db()->query("SELECT * FROM quotes WHERE status = 'sent' AND sent_at IS NOT NULL ORDER BY sent_at")->fetchAll();
    $waiting = array_values(array_filter($qs, fn($q) => days_between(substr((string)$q['sent_at'], 0, 10), $today) >= 5));
    $qSentM = (int)db()->query("SELECT COUNT(*) FROM quotes WHERE substr(sent_at, 1, 7) = '" . $ym . "'")->fetchColumn();
    $qDone = (int)db()->query("SELECT COUNT(*) FROM quotes WHERE status IN ('accepted', 'invoiced')")->fetchColumn();
    $qAll = (int)db()->query("SELECT COUNT(*) FROM quotes WHERE status IN ('sent', 'accepted', 'invoiced', 'rejected', 'expired')")->fetchColumn();
    $tNew = count(array_filter(db()->query("SELECT * FROM tenders WHERE status = 'new' AND score >= 50 AND src IN ('bzp','ted','bk') AND (deadline IS NULL OR deadline = '' OR deadline >= date('now'))")->fetchAll(), fn($t) => !tender_quote($t)));
    $unread = unseen_total();

    // ---------- lista „skup się dziś”: najważniejsze najpierw ----------
    $todo = [];
    $add = function (int $w, string $ic, string $tone, string $title, string $sub, string $href, string $cta) use (&$todo) { $todo[] = compact('w', 'ic', 'tone', 'title', 'sub', 'href', 'cta'); };
    foreach ($replied as $l) $add(100, 'mail', 'hot', 'Klient odpisał: ' . ($l['company'] ?: $l['name']), (string)($l['next_note'] ?: 'Odpowiedz, póki temat jest ciepły.'), P . '?a=lead&id=' . $l['id'], 'Odpowiedz');
    foreach ($ksefBad as $i) $add(95, 'x', 'bad', 'KSeF odrzucił fakturę ' . $i['number'], 'Popraw i wyślij do KSeF ponownie, bez tego faktura nie jest ważna.', P . '?a=view&id=' . $i['id'], 'Popraw');
    foreach ($late as $i) { $d = days_between((string)$i['due_date'], $today); $add(90 + min(9, intdiv($d, 7)), 'banknote', 'bad', ($i['buyer']['name'] ?? 'Klient') . ' zalega ' . $d . ' ' . plural($d, 'dzień', 'dni', 'dni'), 'Faktura ' . $i['number'] . ' na ' . money((int)$i['gross'], $i['currency'] ?? 'PLN') . '. Przypomnienia idą same, przy dużej kwocie warto zadzwonić.', P . '?a=view&id=' . $i['id'], 'Zobacz'); }
    foreach ($due as $l) { $d = days_between((string)$l['next_at'], $today); $add(80 + min(9, $d), 'target', $d > 0 ? 'warn' : 'ok', ($l['company'] ?: $l['name']), (string)($l['next_note'] ?: 'Zaplanowany kontakt') . ($d > 0 ? ' · spóźnione ' . $d . ' ' . plural($d, 'dzień', 'dni', 'dni') : ''), P . '?a=lead&id=' . $l['id'], 'Otwórz'); }
    foreach ($waiting as $q) { $d = days_between(substr((string)$q['sent_at'], 0, 10), $today); $b = json_decode((string)$q['buyer'], true) ?: []; $add(70, 'send', 'warn', 'Wycena ' . $q['number'] . ' bez odpowiedzi od ' . $d . ' dni', ($b['name'] ?? 'Klient') . '. Krótkie „czy mogę coś doprecyzować?” często domyka temat.', P . '?a=quote&id=' . $q['id'], 'Przypomnij się'); }
    foreach ($soon as $i) { $d = days_between($today, (string)$i['due_date']); $add($d === 0 ? 75 : 40, 'list', 'ok', ($i['buyer']['name'] ?? 'Klient') . ': termin ' . ($d === 0 ? 'dziś' : 'za ' . $d . ' ' . plural($d, 'dzień', 'dni', 'dni')), 'Faktura ' . $i['number'] . ' na ' . money((int)$i['gross'], $i['currency'] ?? 'PLN') . '.', P . '?a=view&id=' . $i['id'], 'Zobacz'); }
    if ((int)date('j') >= (int)(setting('acc_day') ?: 5) && setting('acc_sent_' . $prevYm) === '' && setting('acc_email') !== '') $add(85, 'books', 'warn', 'Paczka dla księgowej za ' . pl_month((int)substr($prevYm, 5, 2)) . ' nie poszła', 'Sprawdź faktury zakupowe i wyślij paczkę jednym kliknięciem.', P . '?a=books&ym=' . $prevYm, 'Wyślij');
    if ($tNew) $add(50, 'spark', 'ok', $tNew . ' ' . plural($tNew, 'nowy przetarg pasuje', 'nowe przetargi pasują', 'nowych przetargów pasuje') . ' do Creative Vision', 'Ocena dopasowania 50 lub więcej. Przejrzyj i wybierz te, które wyceniamy.', P . '?a=leads&t=tenders', 'Przejrzyj');
    if ($unread) $add(45, 'mail', 'ok', $unread . ' ' . plural($unread, 'nieprzeczytany mail', 'nieprzeczytane maile', 'nieprzeczytanych maili'), 'W faktury@ i kontakt@.', P . '?a=inbox', 'Otwórz');
    if ($drafts) $add(30, 'pencil', 'ok', $drafts . ' ' . plural($drafts, 'szkic faktury', 'szkice faktur', 'szkiców faktur') . ' czeka na wystawienie', 'Dokończ albo usuń, żeby nie wisiały.', P . '?a=list&t=draft', 'Pokaż');
    usort($todo, fn($a, $b) => $b['w'] <=> $a['w']);
    $more = max(0, count($todo) - 8); $todo = array_slice($todo, 0, 8);

    // ---------- sprzedaż: ostatnie 6 miesięcy (netto, PLN) ----------
    $months = []; for ($k = 5; $k >= 0; $k--) { $mm = date('Y-m', strtotime("first day of -$k month")); $months[$mm] = $byMonth[$mm] ?? 0; }
    $max = max(1, max($months)); $cur = $months[$ym]; $prev = $months[$prevYm] ?? 0;
    $delta = $prev > 0 ? (int)round(($cur - $prev) / $prev * 100) : null;
    $bars = '';
    foreach ($months as $mm => $v) $bars .= '<div class="hb' . ($mm === $ym ? ' on' : '') . '"><span class="hb-v">' . ($v ? number_format($v / 100000, 1, ',', '') . ' tys.' : '0') . '</span><i style="height:' . max(3, (int)round($v / $max * 100)) . '%"></i><span class="hb-m">' . mb_substr(pl_month((int)substr($mm, 5, 2)), 0, 3) . '</span></div>';

    // ---------- widok ----------
    $h = (int)date('G'); $hi = $h < 12 ? 'Dzień dobry' : ($h < 18 ? 'Cześć' : 'Dobry wieczór');
    $focus = '';
    foreach ($todo as $t) $focus .= '<li class="hf-i t-' . $t['tone'] . '"><span class="hf-ic">' . icon($t['ic']) . '</span><a class="hf-tx" href="' . e($t['href']) . '"><b>' . e($t['title']) . '</b><span>' . e($t['sub']) . '</span></a><a class="btn ghost sm" href="' . e($t['href']) . '">' . e($t['cta']) . '</a><span class="hf-go">' . icon('arrow') . '</span></li>';
    $focusHtml = '<section class="card hf"><div class="hf-h"><h2>Skup się dziś</h2><span class="mut">' . ($todo ? count($todo) + $more . ' ' . plural(count($todo) + $more, 'sprawa', 'sprawy', 'spraw') : 'Czysto') . '</span></div>'
        . ($todo ? '<ul class="hf-l">' . $focus . '</ul>' . ($more ? '<p class="hf-more mut">I jeszcze ' . $more . ' mniej pilnych.</p>' : '') : '<div class="hf-empty">' . icon('check') . '<b>Na dziś nic pilnego.</b><span>Dobry moment, żeby przejrzeć przetargi albo napisać do klienta, który dawno się nie odzywał.</span><a class="btn sm" href="' . P . '?a=leads&t=tenders">Przejrzyj przetargi</a></div>') . '</section>';
    $kpi = fn($t, $v, $s, $href, $cls = '') => '<a class="card hk' . $cls . '" href="' . $href . '"><span class="hk-t">' . $t . '</span><span class="hk-v">' . $v . '</span><span class="hk-s">' . $s . '</span></a>';
    $kpis = '<div class="hks">'
        . $kpi('Do zapłaty od klientów', money($open), count($late) ? '<span class="red">' . count($late) . ' po terminie</span>' : 'Nikt nie zalega', P . '?a=list&t=open', ' dark')
        . $kpi('Wpłynęło w ' . mb_strtolower(pl_month((int)date('n'), true)), money($paidM), $lastPaid ? 'Ostatnio: ' . e(mb_strimwidth((string)($lastPaid['buyer']['name'] ?? ''), 0, 26, '…')) : 'Jeszcze nic', P . '?a=list&t=paid')
        . $kpi('Otwarte leady', money($pipe), count($leads) . ' ' . plural(count($leads), 'lead', 'leady', 'leadów') . (count($due) ? ' · <span class="red">' . count($due) . ' na dziś</span>' : ''), P . '?a=leads')
        . $kpi('Wyceny w tym miesiącu', (string)$qSentM, $qAll ? (int)round($qDone / $qAll * 100) . '% wszystkich przyjętych' : 'wysłane do klientów', P . '?a=quotes')
        . $kpi('Pasujące przetargi', (string)$tNew, 'ocena 50 lub więcej', P . '?a=leads&t=tenders')
        . '</div>';
    $sales = '<section class="card hs"><div class="hf-h"><h2>Sprzedaż netto</h2>' . ($delta !== null ? '<span class="hs-d ' . ($delta >= 0 ? 'up' : 'down') . '">' . ($delta >= 0 ? '+' : '') . $delta . '% do poprzedniego miesiąca</span>' : '') . '</div>'
        . '<div class="hs-v">' . money($cur) . '<span class="mut"> w tym miesiącu</span></div><div class="hb-w">' . $bars . '</div></section>';
    $pipeCols = ''; $stc = array_count_values(array_column($leads, 'stage'));
    foreach (['new', 'contact', 'offer', 'nego'] as $k) $pipeCols .= '<a class="hp-c" href="' . P . '?a=leads"><span class="hp-n">' . (int)($stc[$k] ?? 0) . '</span><span class="hp-l">' . e(LEAD_STAGES[$k]) . '</span></a>';
    $pipeHtml = '<section class="card hp"><div class="hf-h"><h2>Oferty</h2><a class="link" href="' . P . '?a=leads">Tablica ' . icon('arrow') . '</a></div><div class="hp-r">' . $pipeCols . '</div></section>';
    $quick = '<div class="hq"><a class="btn" href="' . P . '?a=new">' . icon('plus') . ' Faktura</a><a class="btn ghost" href="' . P . '?a=quote_new">' . icon('quote') . ' Wycena</a><a class="btn ghost" href="' . P . '?a=lead_new">' . icon('target') . ' Lead</a></div>';
    $body = '<div class="home"><div class="home-m">' . $focusHtml . $sales . '</div><div class="home-s">' . $kpis . $pipeHtml . '</div></div>';
    $GLOBALS['pgcls'] = 'pg-home';
    page($hi . ', Marcin', 'home', $body, pl_date($today) . ' · ' . ['niedziela', 'poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota'][(int)date('w')], $quick);
}

/** Powiadomienia: to samo, co przychodzi na telefon, zebrane w jednym miejscu. */
function view_notifs(): never {
    notif_migrate();
    $rows = db()->query('SELECT * FROM notifs ORDER BY id DESC LIMIT 100')->fetchAll();
    db()->exec('UPDATE notifs SET seen = 1 WHERE seen = 0');
    $ic = fn($t) => str_starts_with($t, 'mail') ? 'mail' : (str_starts_with($t, 'reply') ? 'send' : (in_array($t, ['tenders', 'jobs'], true) ? 'spark' : ($t === 'leads' ? 'target' : 'bell')));
    $li = ''; $day = '';
    foreach ($rows as $r) {
        $d = substr((string)$r['at'], 0, 10);
        if ($d !== $day) { $day = $d; $li .= '<li class="nt-d">' . ($d === today() ? 'Dziś' : ($d === date('Y-m-d', strtotime('-1 day')) ? 'Wczoraj' : pl_date($d))) . '</li>'; }
        $li .= '<li class="nt-i' . ((int)$r['seen'] ? '' : ' new') . '"><span class="hf-ic">' . icon($ic((string)$r['tag'])) . '</span><a class="hf-tx" href="' . e((string)$r['url']) . '"><b>' . e((string)$r['title']) . '</b><span>' . e((string)$r['body']) . '</span></a><span class="nt-t">' . date('H:i', strtotime((string)$r['at'])) . '</span></li>';
    }
    $push = '<section class="card pad sec"><div class="hrow" style="flex-wrap:wrap"><span class="mut" style="font-size:14px;flex:1;min-width:220px">Te same powiadomienia mogą przychodzić na telefon i Maca, także przy zablokowanym ekranie. Włącz je na każdym urządzeniu.</span><button type="button" class="btn ghost sm" data-pushon>' . icon('bell') . ' Włącz na tym urządzeniu</button></div></section>';
    $body = '<section class="card"><ul class="nt-l">' . ($li ?: '<li class="hf-empty">' . icon('bell') . '<b>Na razie cisza.</b><span>Tu zobaczysz nowe maile, otwarcia i wpłaty faktur, odpowiedzi na wyceny, przypomnienia o leadach i nowe przetargi.</span></li>') . '</ul></section>' . $push;
    page('Powiadomienia', 'notif', $body, 'Co się dzieje');
}
