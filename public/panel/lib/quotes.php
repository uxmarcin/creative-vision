<?php
// Wyceny: zapytanie z poczty kontakt@ → wycena → faktura jednym kliknięciem.
declare(strict_types=1);

function next_quote_number(string $ym): array {
    $s = db()->prepare('SELECT MAX(seq) FROM quotes WHERE ym = ?'); $s->execute([$ym]);
    $n = (int)$s->fetchColumn() + 1;
    [$y, $m] = explode('-', $ym);
    return [$n, 'W/' . $n . '/' . (int)$m . '/' . $y];
}

function quote_labels(): array {
    return ['draft' => 'Zapisana', 'sent' => 'Wysłana', 'accepted' => 'Zaakceptowana', 'rejected' => 'Odrzucona', 'invoiced' => 'Faktura wystawiona', 'expired' => 'Wygasła'];
}
function quote_chip(array $q): string {
    $st = quote_state($q);
    $cls = ['draft' => 'draft', 'sent' => 'due', 'accepted' => 'decl', 'rejected' => 'cancelled', 'invoiced' => 'paid', 'expired' => 'late'][$st] ?? 'due';
    return '<span class="chip ' . $cls . '">' . quote_labels()[$st] . '</span>';
}

/** Zapis wyceny. $act: save | send. Zwraca [id, błąd]. */
function save_quote(?int $id, array $p, string $act): array {
    $old = $id ? quote_by_id($id) : null;
    if ($old && $old['status'] === 'invoiced') return [$id, 'Z tej wyceny jest już faktura, nie zmieniamy jej.'];
    $buyer = client_from($p);
    $items = [];
    foreach ((array)($p['items'] ?? []) as $it) {
        if (!is_array($it) || trim((string)($it['name'] ?? '')) === '') continue;
        $items[] = ['name' => (string)$it['name'], 'desc' => (string)($it['desc'] ?? ''), 'qty' => (string)($it['qty'] ?? '1'), 'unit' => (string)($it['unit'] ?? 'usł.'), 'price' => (string)($it['price'] ?? '0'), 'vat' => (string)($it['vat'] ?? '23')];
    }
    $c = calc_items($items);
    $store = array_map(fn($i) => ['name' => $i['name'], 'desc' => $i['desc'], 'qty' => $i['qty'], 'unit' => $i['unit'], 'price' => $i['price'], 'vat' => $i['vat']], $c['items']);
    $date = fn($k, $def) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($p[$k] ?? '')) ? (string)$p[$k] : $def;
    $issue = $date('issue_date', today());
    $valid = $date('due_date', date('Y-m-d', strtotime($issue . ' +' . (int)setting('quote_days') . ' days')));
    if ($buyer['name'] === '') return [$id, 'Podaj nazwę klienta.'];
    if (!$c['items']) return [$id, 'Dodaj co najmniej jedną pozycję.'];
    if ($act === 'send' && !filter_var($buyer['email'], FILTER_VALIDATE_EMAIL)) return [$id, 'Podaj e-mail klienta, żeby wysłać wycenę.'];
    $pdo = db(); $pdo->beginTransaction();
    $clientId = $old['client_id'] ?? null;
    if (!empty($p['save_client'])) {
        $cid = (int)($p['client_id'] ?? 0);
        if (!$cid && $buyer['nip'] !== '') { $s = $pdo->prepare('SELECT id FROM clients WHERE REPLACE(REPLACE(nip, "-", ""), " ", "") = ?'); $s->execute([nip_digits($buyer['nip'])]); $cid = (int)$s->fetchColumn(); }
        if (!$cid && $buyer['email'] !== '') { $s = $pdo->prepare('SELECT id FROM clients WHERE lower(email) = lower(?)'); $s->execute([$buyer['email']]); $cid = (int)$s->fetchColumn(); }
        if ($cid) $pdo->prepare('UPDATE clients SET nip=?, name=?, addr1=?, addr2=?, country=?, phone=?, email=?, cc=?, updated=? WHERE id=?')->execute([$buyer['nip'], $buyer['name'], $buyer['addr1'], $buyer['addr2'], $buyer['country'], $buyer['phone'], $buyer['email'], $buyer['cc'], now(), $cid]);
        else { $pdo->prepare('INSERT INTO clients (nip, name, addr1, addr2, country, phone, email, cc, created, updated) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$buyer['nip'], $buyer['name'], $buyer['addr1'], $buyer['addr2'], $buyer['country'], $buyer['phone'], $buyer['email'], $buyer['cc'], now(), now()]); $cid = (int)$pdo->lastInsertId(); }
        $clientId = $cid;
    } elseif ((int)($p['client_id'] ?? 0)) $clientId = (int)$p['client_id'];
    $f = [
        'client_id' => $clientId, 'buyer' => json_encode($buyer, JSON_UNESCAPED_UNICODE), 'issue_date' => $issue, 'due_date' => $valid,
        'term_days' => max(0, min(120, (int)($p['pay_days'] ?? setting('term_days')))), 'place' => trim((string)($p['place'] ?? setting('place'))),
        'items' => json_encode($store, JSON_UNESCAPED_UNICODE), 'net' => $c['net'], 'vat' => $c['vat'], 'gross' => $c['gross'],
        'currency' => isset(currencies()[(string)($p['currency'] ?? '')]) ? (string)$p['currency'] : 'PLN',
        'lang' => ($p['lang'] ?? '') === 'pl-en' ? 'pl-en' : 'pl', 'notes' => trim((string)($p['notes'] ?? '')), 'message' => trim(mb_substr((string)($p['message'] ?? ''), 0, 5000)), 'updated' => now(),
    ];
    if ($old) {
        $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($f)));
        $pdo->prepare("UPDATE quotes SET $set WHERE id = :id")->execute($f + ['id' => $id]);
    } else {
        $ym = substr($issue, 0, 7); [$seq, $num] = next_quote_number($ym);
        $src = json_decode((string)($p['src'] ?? ''), true);
        $f += ['number' => $num, 'seq' => $seq, 'ym' => $ym, 'status' => 'draft', 'src' => is_array($src) ? json_encode(array_intersect_key($src, array_flip(['acc', 'uid', 'from', 'email', 'subject', 'date', 'msgid', 'lead', 'tender'])), JSON_UNESCAPED_UNICODE) : null, 'created' => now()];
        $pdo->prepare('INSERT INTO quotes (' . implode(', ', array_keys($f)) . ') VALUES (:' . implode(', :', array_keys($f)) . ')')->execute($f);
        $id = (int)$pdo->lastInsertId();
        if (is_array($src) && !empty($src['lead'])) $pdo->prepare("UPDATE leads SET quote_id = ?, stage = CASE WHEN stage = 'new' THEN 'contact' ELSE stage END, value = COALESCE(value, ?), updated = ? WHERE id = ?")->execute([$id, $c['net'], now(), (int)$src['lead']]);
    }
    $pdo->commit();
    if ($act === 'send' && quote_is_tender(quote_by_id($id)['src'] ?? null)) { flash('Wycena zapisana. Do przetargu nie wysyłamy jej mailem, pobierz PDF i dołącz do oferty.'); return [$id, null]; }
    if ($act === 'send') {
        $q = quote_by_id($id);
        if (send_quote_mail($q)) flash('Wycena ' . $q['number'] . ' zapisana i wysłana do ' . $buyer['email'] . '.');
        else flash('!Wycena zapisana, ale maila nie udało się wysłać: ' . mail_last_error());
    } else flash('Wycena zapisana.');
    return [$id, null];
}

function quote_pdf(array $q): string {
    require_once __DIR__ . '/pdf.php';
    return render_pdf(invoice_doc($q, CV_ROOT . '/assets/', true));
}
function quote_pdf_name(array $q): string { return 'Wycena_' . str_replace('/', '_', (string)$q['number']) . '.pdf'; }

/** Mail z wyceną. Idzie z kontakt@, jeśli ta skrzynka jest podłączona, i jako odpowiedź na zapytanie. */
function send_quote_mail(array $q): bool {
    $b = $q['buyer']; $num = (string)$q['number']; $gross = money((int)$q['gross'], $q['currency'] ?? 'PLN');
    $to = array_merge([(string)($b['email'] ?? '')], preg_split('/[\s,;]+/', (string)($b['cc'] ?? '')) ?: []);
    $src = $q['src'] ?? [];
    $subj = !empty($src['subject']) ? (preg_match('/^re:/i', (string)$src['subject']) ? $src['subject'] : 'Re: ' . $src['subject']) . ' · wycena ' . $num : 'Wycena ' . $num . ' od Creative Vision';
    $pre = 'Wycena ' . $num . ' na ' . $gross . ', ważna do ' . pl_date($q['due_date']) . '.';
    $rows = m_kicker('Wycena · ' . $num) . m_h1('Wycena ' . $num . ' na:', $gross)
        . m_text(trim((string)($q['message'] ?? '')) !== '' ? nl2br(e(trim((string)$q['message']))) : 'Dzień dobry,<br><br>dziękujemy za zapytanie. W załączniku przesyłamy wycenę ' . e($num) . '. Jest ważna do ' . e(pl_date($q['due_date'])) . '.<br><br>Jeśli wszystko się zgadza, po prostu odpowiedz na tę wiadomość, a ustalimy termin startu. Chętnie też porozmawiamy online, jeśli coś trzeba doprecyzować.')
        . m_rows('Szczegóły wyceny', [['Wycena', $num], ['Ważna do', pl_date($q['due_date'])], ['Wartość netto', money((int)$q['net'], $q['currency'] ?? 'PLN')], ['Wartość brutto', $gross, true]])
        . m_sign();
    $html = mail_layout($subj, $pre, $rows);
    $text = html_to_text($rows);
    $useK = false;
    $cur = mail_acc(); mail_acc('k'); if (inbox_enabled()) $useK = true; mail_acc($useK ? 'k' : 'f');
    $ok = send_mail($to, $subj, $html, $text, [quote_pdf_name($q) => quote_pdf($q)], true, ['In-Reply-To' => (string)($src['msgid'] ?? ''), 'References' => (string)($src['msgid'] ?? '')]);
    mail_acc($cur);
    if ($ok) db()->prepare("UPDATE quotes SET sent_at = ?, status = CASE WHEN status = 'draft' THEN 'sent' ELSE status END, updated = ? WHERE id = ?")->execute([now(), now(), $q['id']]);
    if ($ok && !empty($src['lead'])) db()->prepare("UPDATE leads SET stage = 'offer', updated = ? WHERE id = ? AND stage IN ('new', 'contact')")->execute([now(), (int)$src['lead']]);
    return $ok;
}

/** Z wyceny robimy szkic faktury i od razu go otwieramy. */
function quote_to_invoice(array $q): int {
    if (!empty($q['invoice_id']) && invoice_by_id((int)$q['invoice_id'])) return (int)$q['invoice_id'];
    $issue = today(); $term = max(0, (int)($q['term_days'] ?: setting('term_days')));
    $s = db()->prepare('SELECT items FROM quotes WHERE id = ?'); $s->execute([$q['id']]);
    $f = ['client_id' => $q['client_id'], 'buyer' => json_encode($q['buyer'], JSON_UNESCAPED_UNICODE), 'issue_date' => $issue, 'sale_date' => $issue,
        'due_date' => date('Y-m-d', strtotime($issue . " +$term days")), 'term_days' => $term, 'place' => $q['place'] ?: setting('place'), 'items' => (string)$s->fetchColumn(),
        'net' => $q['net'], 'vat' => $q['vat'], 'gross' => $q['gross'], 'notes' => $q['notes'], 'reminders' => 1,
        'currency' => $q['currency'] ?: 'PLN', 'lang' => $q['lang'] ?: 'pl', 'book_ym' => substr($issue, 0, 7), 'source' => 'wycena', 'created' => now(), 'updated' => now()];
    $pdo = db(); $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO invoices (' . implode(', ', array_keys($f)) . ') VALUES (:' . implode(', :', array_keys($f)) . ')')->execute($f);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE quotes SET status = 'invoiced', invoice_id = ?, updated = ? WHERE id = ?")->execute([$id, now(), $q['id']]);
    if (!empty($q['src']['lead'])) $pdo->prepare("UPDATE leads SET stage = 'won', updated = ? WHERE id = ?")->execute([now(), (int)$q['src']['lead']]);
    $pdo->commit();
    log_event($id, 'create', 'Faktura z wyceny ' . $q['number']);
    return $id;
}

function quote_post(string $a): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($a === 'quote_save') {
        [$id, $err] = save_quote($id ?: null, $_POST, (string)($_POST['act'] ?? 'save'));
        if ($err) { flash('!' . $err); $_SESSION['qform'] = $_POST; redirect(P . '?a=' . ($id ? 'quote_edit&id=' . $id : 'quote_new')); }
        unset($_SESSION['qform']);
        redirect(P . '?a=quote&id=' . $id);
    }
    $q = quote_by_id($id); if (!$q) { flash('!Nie ma takiej wyceny.'); redirect(P . '?a=quotes'); }
    switch ($a) {
        case 'quote_send':
            if (quote_is_tender($q['src'])) { flash('!Wyceny do przetargu nie wysyłamy mailem. Pobierz PDF i dołącz do oferty na platformie.'); redirect(P . '?a=quote&id=' . $id); }
            if (!filter_var(trim((string)($q['buyer']['email'] ?? '')), FILTER_VALIDATE_EMAIL)) { flash('!Dodaj e-mail klienta w edycji wyceny.'); redirect(P . '?a=quote_edit&id=' . $id); }
            flash(send_quote_mail($q) ? 'Wycena wysłana do ' . $q['buyer']['email'] . '.' : '!Nie udało się wysłać: ' . mail_last_error());
            redirect(P . '?a=quote&id=' . $id);
        case 'quote_status':
            $st = (string)($_POST['st'] ?? '');
            if (in_array($st, ['accepted', 'rejected', 'sent'], true) && $q['status'] !== 'invoiced') db()->prepare('UPDATE quotes SET status = ?, updated = ? WHERE id = ?')->execute([$st, now(), $id]);
            redirect(P . '?a=quote&id=' . $id);
        case 'quote_invoice':
            $iid = quote_to_invoice($q);
            flash('Faktura przygotowana z wyceny ' . $q['number'] . '. Sprawdź dane i wystaw.');
            redirect(P . '?a=edit&id=' . $iid);
        case 'quote_delete':
            db()->prepare('DELETE FROM quotes WHERE id = ?')->execute([$id]);
            flash('Wycena ' . $q['number'] . ' usunięta.');
            redirect(P . '?a=quotes');
    }
    redirect(P . '?a=quotes');
}

// ---------- widoki ----------
function view_quotes(): never {
    $tab = (string)($_GET['t'] ?? 'open'); $q = trim((string)($_GET['q'] ?? ''));
    $all = array_map(function ($r) { $r = hydrate($r); $r['doc'] = 'quote'; return $r; }, db()->query('SELECT * FROM quotes ORDER BY issue_date DESC, id DESC')->fetchAll());
    $groups = ['open' => ['Otwarte', ['draft', 'sent']], 'accepted' => ['Zaakceptowane', ['accepted']], 'invoiced' => ['Zafakturowane', ['invoiced']], 'closed' => ['Zamknięte', ['rejected', 'expired']], 'all' => ['Wszystkie', null]];
    if (!isset($groups[$tab])) $tab = 'open';
    $cnt = array_fill_keys(array_keys($groups), 0); $openSum = 0; $accSum = 0;
    foreach ($all as $x) { $st = quote_state($x); foreach ($groups as $k => [, $sts]) if ($sts === null || in_array($st, $sts, true)) $cnt[$k]++;
        if (in_array($st, ['draft', 'sent'], true) && ($x['currency'] ?? 'PLN') === 'PLN') $openSum += (int)$x['net'];
        if ($st === 'accepted' && ($x['currency'] ?? 'PLN') === 'PLN') $accSum += (int)$x['net']; }
    $rows = array_filter($all, function ($x) use ($groups, $tab, $q) {
        $sts = $groups[$tab][1]; if ($sts !== null && !in_array(quote_state($x), $sts, true)) return false;
        return $q === '' || mb_stripos($x['number'] . ' ' . ($x['buyer']['name'] ?? '') . ' ' . ($x['buyer']['email'] ?? ''), $q) !== false;
    });
    $tabs = '';
    foreach ($groups as $k => [$l]) $tabs .= '<a href="' . P . '?a=quotes&t=' . $k . '"' . ($tab === $k ? ' class="on" aria-current="true"' : '') . '>' . $l . ' <span>' . $cnt[$k] . '</span></a>';
    $tr = '';
    foreach ($rows as $x) {
        $url = P . '?a=quote&id=' . $x['id']; $first = $x['calc']['items'][0]['name'] ?? '';
        $left = days_between(today(), (string)$x['due_date']);
        $w = in_array(quote_state($x), ['draft', 'sent'], true) ? ($left > 0 ? 'ważna jeszcze ' . $left . ' ' . plural($left, 'dzień', 'dni', 'dni') : ($left === 0 ? 'ważna do dziś' : '')) : '';
        $tr .= '<tr class="row" data-href="' . $url . '"><td class="c-num nw"><a class="num" href="' . $url . '">' . e($x['number']) . '</a></td>'
            . '<td class="c-cli"><span>' . e($x['buyer']['name'] ?? '') . '</span><span class="sub">' . e(mb_strimwidth($first, 0, 60, '…')) . '</span></td>'
            . '<td class="c-due nw">' . e(pl_date($x['due_date'])) . '<span class="sub mut">' . e($w) . '</span></td>'
            . '<td class="c-amt r amt">' . money((int)$x['gross'], $x['currency'] ?? 'PLN') . '</td>'
            . '<td class="c-st nw">' . quote_chip($x) . '</td><td class="c-act"></td></tr>';
    }
    $kpi = '<section class="kpis" aria-label="Podsumowanie wycen"><div class="kpi dark"><span class="t">Otwarte wyceny</span><span class="v">' . money($openSum) . '</span><span class="s">netto, ' . $cnt['open'] . ' ' . plural($cnt['open'], 'wycena czeka', 'wyceny czekają', 'wycen czeka') . ' na decyzję</span></div><div class="kpis-r">'
        . '<div class="kpi"><span class="t">Zaakceptowane</span><span class="v">' . money($accSum) . '</span><span class="s">netto, do zafakturowania</span></div>'
        . '<div class="kpi"><span class="t">Zafakturowane</span><span class="v">' . $cnt['invoiced'] . '</span><span class="s">' . plural($cnt['invoiced'], 'wycena zamieniona', 'wyceny zamienione', 'wycen zamienionych') . ' na fakturę</span></div></div></section>';
    $table = '<section class="card" aria-label="Lista wycen"><nav class="tabs">' . $tabs . '</nav>'
        . ($tr ? '<div class="tw"><table class="list"><thead><tr><th>Numer</th><th>Klient</th><th>Ważna do</th><th class="r">Kwota brutto</th><th>Status</th><th><span class="sr">Akcje</span></th></tr></thead><tbody>' . $tr . '</tbody></table></div>'
            : '<div class="empty">' . ($all ? 'Brak wycen w tym widoku.' : 'Tu pojawią się wyceny. Zrób pierwszą albo otwórz zapytanie w poczcie kontakt@ i kliknij „Utwórz wycenę”.') . '</div>') . '</section>';
    $acts = '<form class="search" role="search" method="get" action="' . P . '"><input type="hidden" name="a" value="quotes"><input type="hidden" name="t" value="all">' . icon('search') . '<label class="sr" for="q">Szukaj wyceny</label><input id="q" type="search" name="q" value="' . e($q) . '" placeholder="Szukaj klienta lub numeru"></form>'
        . '<a class="btn" href="' . P . '?a=quote_new">Nowa wycena ' . icon('plus') . '</a>';
    page('Wyceny', 'quotes', $kpi . $table, 'Zapytanie, wycena, faktura', $acts);
}

/** Dane zapytania z maila (skrzynka z mail_acc()). */
function inquiry_from_mail(int $uid): ?array {
    try { $im = imap_open_box(); $im->select('INBOX'); $raw = $im->raw($uid); unset($im); } catch (Throwable $e) { flash('!' . $e->getMessage()); return null; }
    $m = mime_parse($raw); $h = $m['h'];
    [$fn, $fa] = mail_addr((string)($h['from'] ?? ''));
    $rt = (string)($h['reply-to'] ?? '');
    if ($rt !== '') { [$rn, $ra] = mail_addr($rt); if (filter_var($ra, FILTER_VALIDATE_EMAIL)) { $fa = $ra; if ($rn !== '' && $rn !== $ra) $fn = $rn; } }
    $text = trim($m['text'] !== '' ? $m['text'] : html_to_text($m['html']));
    return ['acc' => mail_acc(), 'uid' => $uid, 'from' => $fn !== $fa ? $fn : '', 'email' => $fa, 'subject' => mime_hdr_decode((string)($h['subject'] ?? '')),
        'date' => ($ts = strtotime((string)($h['date'] ?? ''))) ? date('Y-m-d H:i', $ts) : '', 'msgid' => (string)($h['message-id'] ?? ''), 'text' => mb_substr($text, 0, 4000)];
}

function view_quote_form(?array $q): never {
    if ($q && $q['status'] === 'invoiced') { flash('!Z tej wyceny jest już faktura.'); redirect(P . '?a=quote&id=' . $q['id']); }
    $f = $_SESSION['qform'] ?? null; unset($_SESSION['qform']);
    $src = $q['src'] ?? null; $inq = null;
    if (!$q && !$f && ($uid = (int)($_GET['uid'] ?? 0))) { $inq = inquiry_from_mail($uid); $src = $inq; }
    if ($f && !empty($f['src'])) $src = json_decode((string)$f['src'], true);
    $clients = db()->query('SELECT id, nip, name, addr1, addr2, country, phone, email, cc FROM clients ORDER BY name')->fetchAll();
    $b = $f ? client_from($f) : ($q['buyer'] ?? ['nip' => '', 'name' => '', 'addr1' => '', 'addr2' => '', 'country' => '', 'email' => '', 'cc' => '', 'phone' => '']);
    $cidSel = (int)($f['client_id'] ?? ($q['client_id'] ?? 0));
    if ($inq) {
        $b['email'] = $inq['email']; $b['name'] = $inq['from'];
        foreach ($clients as $c) if ($c['email'] !== '' && strcasecmp((string)$c['email'], $inq['email']) === 0) { $b = $c; $cidSel = (int)$c['id']; }
    }
    if (!$q && !$f && ($cid = (int)($_GET['client'] ?? 0))) foreach ($clients as $c) if ((int)$c['id'] === $cid) { $b = $c; $cidSel = $cid; }
    if (!$q && !$f && ($lid = (int)($_GET['lead'] ?? 0)) && function_exists('lead_by_id') && ($ld = lead_by_id($lid))) {
        $b = array_merge($b, ['name' => $ld['company'] ?: $ld['name'], 'email' => $ld['email'], 'phone' => $ld['phone']]);
        foreach ($clients as $c) if ($ld['email'] !== '' && strcasecmp((string)$c['email'], $ld['email']) === 0) { $b = $c; $cidSel = (int)$c['id']; }
        $src = array_merge($ld['src'] ?: [], ['lead' => $lid]); $ldNotes = trim($ld['notes'] . "\n" . $ld['next_note'] . (!empty($ld['audit']['points']) ? "\nAudyt strony " . $ld['website'] . ': ' . implode(' ', $ld['audit']['points']) : '') . (!empty($ld['src']['tender']) && function_exists('tender_body') ? "\n\nTREŚĆ OGŁOSZENIA PRZETARGOWEGO:\n" . tender_body((int)$ld['src']['tender']) : ''));
    }
    $opts = '<option value="">Nowy klient</option>';
    foreach ($clients as $c) $opts .= '<option value="' . $c['id'] . '"' . ($cidSel === (int)$c['id'] ? ' selected' : '') . '>' . e($c['name']) . '</option>';
    $vat = fn($sel) => implode('', array_map(fn($k, $l) => '<option value="' . $k . '"' . ((string)$sel === (string)$k ? ' selected' : '') . '>' . $l . '</option>', array_keys(vat_rates()), vat_rates()));
    $items = $f ? array_values((array)($f['items'] ?? [])) : ($q ? array_map(fn($i) => ['name' => $i['name'], 'desc' => $i['desc'], 'qty' => qty_fmt((float)$i['qty']), 'unit' => $i['unit'], 'price' => money((int)$i['price'], false), 'vat' => $i['vat']], $q['calc']['items']) : []);
    if (!$items) $items = [['name' => '', 'desc' => '', 'qty' => '1', 'unit' => 'usł.', 'price' => '', 'vat' => '23']];
    $rows = ''; foreach ($items as $n => $it) $rows .= item_row((string)$n, $it, $vat);
    $tpl = item_row('__N__', ['name' => '', 'desc' => '', 'qty' => '1', 'unit' => 'usł.', 'price' => '', 'vat' => '23'], $vat);
    $issue = $f['issue_date'] ?? ($q['issue_date'] ?? today());
    $vd = (int)setting('quote_days');
    $valid = $f['due_date'] ?? ($q['due_date'] ?? date('Y-m-d', strtotime($issue . " +$vd days")));
    $vdays = max(0, days_between($issue, $valid));
    $pills = ''; foreach ([7, 14, 30] as $d) $pills .= '<button type="button" data-term="' . $d . '"' . ($vdays === $d ? ' class="on"' : '') . '>' . $d . ' dni</button>';
    $v = fn($k) => e((string)($b[$k] ?? ''));
    $cur = (string)($f['currency'] ?? ($q['currency'] ?? 'PLN'));
    $inqCard = (!empty($src['email']) || !empty($src['subject'])) ? '<section class="card pad sec inq"><h2>Zapytanie</h2><div class="kv"><div><span>Od</span><span>' . e(trim(($src['from'] ?? '') . ' ' . ($src['email'] ?? ''))) . '</span></div>' . (!empty($src['date']) ? '<div><span>Data</span><span>' . e(date('d.m.Y H:i', strtotime((string)$src['date']))) . '</span></div>' : '') . '</div>'
        . (!empty($src['subject']) ? '<b style="font-weight:500">' . e((string)$src['subject']) . '</b>' : '') . (!empty($inq['text']) ? '<div class="mtext inq-t">' . nl2br(e($inq['text'])) . '</div>' : '') . '</section>' : '';
    $srcJson = $src ? json_encode(array_diff_key($src, ['text' => 1]), JSON_UNESCAPED_UNICODE) : '';
    $body = '<form class="sec" method="post" action="' . P . '?a=quote_save" id="invform" autocomplete="off">' . csrf_field() . '<input type="hidden" name="id" value="' . ($q['id'] ?? '') . '"><input type="hidden" name="src" value="' . e((string)$srcJson) . '">'
        . '<script type="application/json" id="clients">' . json_encode($clients, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>'
        . '<div class="cols"><div class="wide sec">' . $inqCard . quote_ai_card($src, $inq, $ldNotes ?? '')
        . '<section class="card pad sec"><div class="hrow"><h2>Klient</h2><label class="f" style="flex-direction:row;align-items:center;gap:10px"><span>Z listy</span><select class="in" name="client_id" id="client_id" style="height:40px;min-width:200px">' . $opts . '</select></label></div>'
        . '<div class="grid2"><label class="f">NIP (opcjonalnie)<div class="nipw"><input class="in" name="nip" id="nip" value="' . $v('nip') . '" inputmode="numeric"><button type="button" class="btn ghost sm" id="nipbtn" style="height:46px">Pobierz dane</button></div><span id="nipmsg" class="mut" style="font-size:13px"></span></label>'
        . '<label class="f">Nazwa firmy lub imię i nazwisko<input class="in" name="name" id="name" value="' . $v('name') . '" required></label>'
        . '<label class="f">Ulica i numer<input class="in" name="addr1" id="addr1" value="' . $v('addr1') . '"></label>'
        . '<label class="f">Kod pocztowy i miasto<input class="in" name="addr2" id="addr2" value="' . $v('addr2') . '"></label>'
        . '<label class="f">Kraj (tylko spoza Polski)<input class="in" name="country" id="country" value="' . $v('country') . '"></label>'
        . '<label class="f">E-mail<input class="in" type="email" name="email" id="email" value="' . $v('email') . '"></label>'
        . '<label class="f">Kopia do (opcjonalnie)<input class="in" name="cc" id="cc" value="' . $v('cc') . '"></label>'
        . '<label class="f">Telefon (opcjonalnie)<input class="in" type="tel" name="phone" id="phone" value="' . $v('phone') . '"></label></div>'
        . '<label class="check"><input type="checkbox" name="save_client" value="1" checked> Zapisz lub zaktualizuj klienta na liście</label></section>'
        . '<section class="card pad sec"><h2>Zakres i ceny <small>ceny netto</small></h2><div class="items" id="items">' . $rows . '</div><template id="itemtpl">' . $tpl . '</template>'
        . '<div class="hrow" style="align-items:flex-start;flex-wrap:wrap"><button type="button" class="btn ghost sm" id="additem">' . icon('plus') . ' Dodaj pozycję</button>'
        . '<div class="totals" id="totals"><div><span>Razem netto</span><b data-t="net">0,00 zł</b></div><div><span>VAT</span><b data-t="vat">0,00 zł</b></div><div class="g"><span>Razem brutto</span><b data-t="gross">0,00 zł</b></div></div></div></section>'
        . '<section class="card pad sec"><h2>Uwagi do wyceny</h2><textarea class="in" name="notes" rows="4" placeholder="Np. termin realizacji, co jest w cenie, etapy płatności">' . e((string)($f['notes'] ?? ($q['notes'] ?? ''))) . '</textarea></section>'
        . '<section class="card pad sec"><h2>Wiadomość do klienta <small>w mailu z wyceną</small></h2><textarea class="in" name="message" id="qmsg" rows="7" placeholder="Dzień dobry, dziękujemy za rozmowę… (zostaw puste, a wyślemy krótką standardową wiadomość)">' . e((string)($f['message'] ?? ($q['message'] ?? ''))) . '</textarea><span class="mut" style="font-size:13px">Wycena w PDF będzie w załączniku. AI przygotuje tę wiadomość razem z wyceną.</span></section>'
        . '</div><aside class="side">'
        . '<section class="card pad sec"><h2>Ważność</h2><label class="f">Data wyceny<input class="in" type="date" name="issue_date" id="issue_date" value="' . e($issue) . '"></label>'
        . '<div class="f" style="display:flex;flex-direction:column;gap:8px;font-size:13px;color:var(--mut)">Ważna przez<div class="pills" id="pills" style="grid-template-columns:repeat(3,1fr)">' . $pills . '</div></div>'
        . '<input type="hidden" id="term_days" value="' . $vdays . '"><label class="f">Ważna do<input class="in" type="date" name="due_date" id="due_date" value="' . e($valid) . '"></label>'
        . '<label class="f">Termin płatności faktury (dni)<input class="in" type="number" name="pay_days" min="0" max="120" value="' . e((string)($f['pay_days'] ?? ($q['term_days'] ?? setting('term_days')))) . '"></label>'
        . '<label class="f">Miejsce wystawienia<input class="in" name="place" value="' . e((string)($f['place'] ?? ($q['place'] ?? setting('place')))) . '"></label></section>'
        . '<section class="card pad sec"><h2>Waluta i język</h2><label class="f">Waluta<select class="in" name="currency" id="currency">' . implode('', array_map(fn($k, $l) => '<option value="' . $k . '"' . ($k === $cur ? ' selected' : '') . '>' . $l . '</option>', array_keys(currencies()), currencies())) . '</select></label>'
        . '<label class="f">Język wyceny<select class="in" name="lang" id="lang"><option value="pl">Polski</option><option value="pl-en"' . ((string)($f['lang'] ?? ($q['lang'] ?? 'pl')) === 'pl-en' ? ' selected' : '') . '>Polski i angielski</option></select></label></section>'
        . '<section class="card pad sec"><h2>Numer</h2><span>' . ($q ? e($q['number']) : 'Nadamy przy zapisie, kolejny: <b style="font-weight:500">' . e(next_quote_number(substr($issue, 0, 7))[1]) . '</b>') . '</span></section>'
        . '</aside></div>'
        . '<div class="bar"><span class="note">Wycenę wyślemy z ' . e(quote_from_addr()) . ', z PDF w załączniku' . ($src ? ', jako odpowiedź na zapytanie' : '') . '.</span>'
        . '<a class="btn ghost" href="' . ($q ? P . '?a=quote&id=' . $q['id'] : ($src && !empty($src['uid']) ? P . '?a=mail&box=INBOX&uid=' . (int)$src['uid'] . (($src['acc'] ?? '') === 'k' ? '&acc=k' : '') : P . '?a=quotes')) . '">Anuluj</a>'
        . '<button class="btn ghost" name="act" value="save">Zapisz wycenę</button>'
        . (quote_is_tender($src) ? '' : '<button class="btn" name="act" value="send" data-confirm="Wysłać wycenę do {email}?">Zapisz i wyślij ' . icon('arrow') . '</button>') . '</div></form>';
    $GLOBALS['pgcls'] = 'pg-form';
    $GLOBALS['back'] = $q ? [P . '?a=quote&id=' . $q['id'], 'Wycena ' . $q['number']] : [P . '?a=quotes', 'Wyceny'];
    page($q ? 'Wycena ' . $q['number'] : 'Nowa wycena', 'quotes', $body, $q ? 'Edycja' : ($src ? 'Z zapytania' : 'Wycena w kilka minut'));
}
function quote_from_addr(): string { $c = mail_acc(); mail_acc('k'); $a = inbox_enabled() ? setting('imap_user') : ''; mail_acc('f'); if ($a === '') $a = smtp_enabled() ? setting('imap_user') : setting('mail_from'); mail_acc($c); return $a; }

function view_quote(array $q): never {
    $id = (int)$q['id']; $st = quote_state($q);
    $f = fn($action, $label, $cls = 'btn ghost', $extra = '', $conf = '') => '<form method="post" action="' . P . '?a=' . $action . '">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '">' . $extra . '<button class="' . $cls . '"' . ($conf !== '' ? ' data-confirm="' . e($conf) . '"' : '') . '>' . $label . '</button></form>';
    $acts = '';
    if ($q['status'] === 'invoiced') {
        $inv = invoice_by_id((int)$q['invoice_id']);
        if ($inv) $acts .= '<a class="btn" href="' . P . '?a=' . ($inv['status'] === 'draft' ? 'edit' : 'view') . '&id=' . $inv['id'] . '">' . ($inv['status'] === 'draft' ? 'Dokończ fakturę' : 'Faktura ' . e($inv['number'])) . ' ' . icon('arrow') . '</a>';
    } else {
        $acts .= $f('quote_invoice', 'Zamień na fakturę ' . icon('arrow'), 'btn');
        if (quote_is_tender($q['src'])) $acts .= '<span class="mut" style="font-size:14px">Wycena do przetargu: ofertę składasz na platformie zamawiającego, więc nie wysyłamy jej mailem. Pobierz PDF i dołącz do oferty.</span>';
        else $acts .= $f('quote_send', empty($q['sent_at']) ? 'Wyślij do klienta' : 'Wyślij ponownie', 'btn ghost', '', 'Wysłać wycenę ' . $q['number'] . ' do ' . trim((string)($q['buyer']['email'] ?? '')) . '?');
        $acts .= '<a class="btn ghost" href="' . P . '?a=quote_edit&id=' . $id . '">Edytuj</a>';
    }
    $acts .= '<a class="btn ghost" href="' . P . '?a=quote_pdf&id=' . $id . '&dl=1">Pobierz PDF</a>';
    $status = '';
    if ($q['status'] !== 'invoiced') {
        $status = '<details class="more"><summary>Zmień status</summary><div class="inl">'
            . ($q['status'] !== 'accepted' ? $f('quote_status', 'Klient zaakceptował', 'btn ghost sm', '<input type="hidden" name="st" value="accepted">') : '')
            . ($q['status'] !== 'rejected' ? $f('quote_status', 'Klient odrzucił', 'btn ghost sm', '<input type="hidden" name="st" value="rejected">') : '')
            . (in_array($q['status'], ['accepted', 'rejected'], true) ? $f('quote_status', 'Wróć do wysłanej', 'btn ghost sm', '<input type="hidden" name="st" value="sent">') : '') . '</div></details>';
    }
    $left = days_between(today(), (string)$q['due_date']);
    $hero = '<div class="ihero"><span class="ik">' . e($q['buyer']['name'] ?? '') . '</span><span class="iv">' . money((int)$q['gross'], $q['currency'] ?? 'PLN') . '</span>'
        . '<span class="is">' . quote_chip($q) . (in_array($st, ['draft', 'sent'], true) ? '<span class="mut">' . ($left > 0 ? 'ważna jeszcze ' . $left . ' ' . plural($left, 'dzień', 'dni', 'dni') : 'ważna do dziś') . '</span>' : '') . '</span></div>'
        . '<div class="kv"><div><span>Netto</span><span>' . money((int)$q['net'], $q['currency'] ?? 'PLN') . '</span></div><div><span>Ważna do</span><span>' . e(pl_date($q['due_date'])) . '</span></div>'
        . ($q['sent_at'] ? '<div><span>Wysłana</span><span>' . e(date('d.m.Y H:i', strtotime($q['sent_at']))) . '</span></div>' : '') . '</div>';
    $src = $q['src'];
    $inq = $src ? '<div class="card pad sec"><h2>Zapytanie</h2><div class="kv"><div><span>Od</span><span>' . e((string)($src['from'] ?? '') ?: (string)($src['email'] ?? '')) . '</span></div>' . (!empty($src['date']) ? '<div><span>Data</span><span>' . e(date('d.m.Y H:i', strtotime((string)$src['date']))) . '</span></div>' : '') . '</div>'
        . (!empty($src['subject']) ? '<span>' . e((string)$src['subject']) . '</span>' : '') . (!empty($src['uid']) ? '<a class="link" href="' . P . '?a=mail&box=INBOX&uid=' . (int)$src['uid'] . (($src['acc'] ?? '') === 'k' ? '&acc=k' : '') . '">Otwórz wiadomość</a>' : '') . '</div>' : '';
    $danger = $q['status'] !== 'invoiced' ? '<details class="more"><summary>Usuń wycenę</summary>' . $f('quote_delete', 'Usuń wycenę ' . $q['number'], 'btn danger') . '</details>' : '';
    $body = '<div class="cols"><div class="wide"><div class="doc-wrap">' . invoice_doc($q, P . '/assets/') . '</div></div>'
        . '<aside class="side"><div class="card pad sec">' . $hero . '<div class="acts-col">' . $acts . '</div>' . $status . $danger . '</div>' . $inq . '</aside></div>';
    $GLOBALS['pgcls'] = 'pg-inv';
    $GLOBALS['back'] = [P . '?a=quotes', 'Wyceny'];
    page('Wycena ' . $q['number'], 'quotes', $body, $q['buyer']['name'] ?? '', '<a class="btn ghost" href="' . P . '?a=quotes">Wszystkie wyceny</a>');
}

/** Karta w widoku maila: utwórz wycenę albo przejdź do istniejących. */
function quote_from_mail_box(int $uid): string {
    $s = db()->prepare("SELECT id, number, status FROM quotes WHERE json_extract(src, '$.uid') = ? AND COALESCE(json_extract(src, '$.acc'), 'f') = ?");
    $s->execute([$uid, mail_acc()]);
    $ex = '';
    foreach ($s->fetchAll() as $r) $ex .= '<a class="link" href="' . P . '?a=quote&id=' . $r['id'] . '">Wycena ' . e($r['number']) . '</a>';
    return '<section class="card pad sec"><h2>Zapytanie od klienta?</h2><span class="mut" style="font-size:14px">Zrób z tej wiadomości wycenę. Dane nadawcy wpiszemy same, a wycenę wyślesz jako odpowiedź.</span>'
        . '<a class="btn" href="' . P . '?a=quote_new&uid=' . $uid . aq() . '">' . icon('quote') . ' Utwórz wycenę</a>'
        . '<form method="post" action="' . P . '?a=lead_from_mail">' . csrf_field() . acc_field() . '<input type="hidden" name="uid" value="' . $uid . '"><button class="btn ghost" style="width:100%">' . icon('target') . ' Dodaj do lejka</button></form>' . $ex . '</section>';
}

/** Karta „Przygotuj z AI” w formularzu wyceny. */
function quote_ai_card(?array $src, ?array $inq, string $leadNotes): string {
    $ctx = trim((!empty($src['subject']) ? 'Temat: ' . $src['subject'] . "\n" : '') . (!empty($inq['text']) ? $inq['text'] : '') . ($leadNotes !== '' ? "\n" . $leadNotes : ''));
    if (!ai_enabled()) return '<section class="card pad sec aicard"><div class="hrow"><h2>' . icon('spark') . ' Przygotuj z AI</h2></div><span class="mut" style="font-size:14px">AI ułoży etapy, godziny i ceny z Twojego cennika. Wpisz klucz API Anthropic w <a href="' . P . '?a=settings#ai">ustawieniach</a>.</span></section>';
    return '<section class="card pad sec aicard" id="aicard"><div class="hrow"><h2>' . icon('spark') . ' Przygotuj z AI</h2></div>'
        . '<textarea hidden id="ai_ctx">' . e($ctx) . '</textarea>'
        . '<label class="f">' . ($ctx !== '' ? 'Zapytanie klienta weźmiemy pod uwagę. Dopisz, co wiesz (opcjonalnie)' : 'Opisz, czego potrzebuje klient') . '<textarea class="in" id="ai_brief" rows="3" placeholder="np. strona dla kancelarii, 6 podstron, teksty od klienta, termin do końca stycznia, budżet ok. 8 tys."></textarea></label>'
        . '<div class="hrow" style="justify-content:flex-start;flex-wrap:wrap"><button type="button" class="btn" id="aibtn"' . (!empty($_GET['ai']) ? ' data-auto' : '') . '>' . icon('spark') . ' Przygotuj wycenę</button><span class="mut" id="aist" style="font-size:14px"></span></div>'
        . '<div id="aiout" hidden class="aiout"></div></section>';
}

/** Wycena do przetargu: składana na platformie, nie wysyłamy jej mailem. */
function quote_is_tender(?array $src): bool {
    if (!$src) return false;
    if (!empty($src['tender'])) return true;
    if (!empty($src['lead']) && function_exists('lead_by_id') && ($l = lead_by_id((int)$src['lead']))) return $l['source'] === 'przetarg' || !empty($l['src']['tender']);
    return false;
}
