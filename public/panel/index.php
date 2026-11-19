<?php
// Panel faktur Creative Vision.
declare(strict_types=1);

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/doc.php';
require __DIR__ . '/lib/reminders.php';
require_once __DIR__ . '/lib/ksef.php';
require_once __DIR__ . '/lib/imap.php';
require_once __DIR__ . '/lib/smtp.php';
require_once __DIR__ . '/lib/sms.php';

security_headers();
header('Cache-Control: no-store');
start_session();

$a = (string)($_GET['a'] ?? 'list');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
const P = '/panel';

// ---------- pierwsze uruchomienie i logowanie ----------
if (!has_users()) {
    if ($post && $a === 'setup') {
        check_csrf();
        $hash = @include __DIR__ . '/lib/setup-hash.php';
        $email = trim((string)($_POST['email'] ?? '')); $pass = (string)($_POST['pass'] ?? '');
        if (too_many_attempts()) { flash('Za dużo prób. Spróbuj za 15 minut.'); redirect(P . '?a=setup'); }
        if (!is_string($hash) || !password_verify(trim((string)($_POST['code'] ?? '')), $hash)) { note_attempt(); flash('Kod konfiguracji się nie zgadza.'); redirect(P . '?a=setup'); }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($pass) < 10) { flash('Podaj poprawny e-mail i hasło z co najmniej 10 znaków.'); redirect(P . '?a=setup'); }
        db()->prepare('INSERT INTO users (email, pass, created) VALUES (?, ?, ?)')->execute([$email, password_hash($pass, PASSWORD_DEFAULT), now()]);
        session_regenerate_id(true); $_SESSION['uid'] = (int)db()->lastInsertId();
        flash('Konto gotowe. Uzupełnij numer konta bankowego w ustawieniach.');
        redirect(P . '?a=settings');
    }
    auth_page('Pierwsze uruchomienie', 'Załóż konto do panelu faktur. Kod konfiguracji dostałeś ode mnie w wiadomości.', 'setup', true);
}
if ($a === 'logout' && $post) { check_csrf(); $_SESSION = []; session_regenerate_id(true); redirect(P); }
if (!user_id()) {
    if ($post && $a === 'login') {
        check_csrf();
        if (too_many_attempts()) { flash('Za dużo prób. Spróbuj za 15 minut.'); redirect(P); }
        $s = db()->prepare('SELECT * FROM users WHERE email = ?'); $s->execute([trim((string)($_POST['email'] ?? ''))]);
        $u = $s->fetch();
        if ($u && password_verify((string)($_POST['pass'] ?? ''), $u['pass'])) {
            session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id'];
            if (password_needs_rehash($u['pass'], PASSWORD_DEFAULT)) db()->prepare('UPDATE users SET pass = ? WHERE id = ?')->execute([password_hash((string)$_POST['pass'], PASSWORD_DEFAULT), $u['id']]);
            redirect(P);
        }
        note_attempt(); flash('Nieprawidłowy e-mail lub hasło.'); redirect(P);
    }
    auth_page('Faktury', 'Zaloguj się do panelu Creative Vision.', 'login', false);
}

// ---------- akcje POST ----------
if ($post) {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    switch ($a) {
        case 'save':
            $act = (string)($_POST['act'] ?? 'draft');
            [$id, $err] = save_invoice($id ?: null, $_POST, $act);
            if ($err) { flash('!' . $err); $_SESSION['form'] = $_POST; redirect(P . '?a=' . ($id ? 'edit&id=' . $id : 'new')); }
            unset($_SESSION['form']);
            redirect(P . '?a=view&id=' . $id);
        case 'preview_mail':
            $inv = must_invoice($id);
            $kind = in_array($_POST['kind'] ?? '', ['new', 'manual', 'w1', 'w2'], true) ? (string)$_POST['kind'] : 'manual';
            $to = trim((string)($_POST['to'] ?? '')) ?: (setting('imap_user') ?: setting('mail_reply'));
            [$sj, $h, $t] = invoice_mail($inv, $kind);
            $att = in_array($kind, ['new', 'w1', 'w2'], true) ? [invoice_pdf_name($inv) => invoice_pdf($inv)] : [];
            $ok = send_mail([$to], '[Podgląd] ' . $sj, $h, $t, $att, false);
            if ($ok) log_event($id, 'preview', 'Podgląd maila („' . $sj . '”) wysłany na ' . $to);
            flash($ok ? 'Podgląd wysłany na ' . $to . '. Klient nic nie dostał.' : '!Nie udało się wysłać podglądu: ' . mail_last_error());
            redirect(P . '?a=view&id=' . $id);
        case 'set_email':
            $inv = must_invoice($id);
            $em = trim((string)($_POST['email'] ?? ''));
            if (!filter_var($em, FILTER_VALIDATE_EMAIL)) { flash('!Ten adres e-mail wygląda na niepoprawny.'); redirect(P . '?a=view&id=' . $id); }
            $b = $inv['buyer']; $b['email'] = $em;
            db()->prepare('UPDATE invoices SET buyer = ?, updated = ? WHERE id = ?')->execute([json_encode($b, JSON_UNESCAPED_UNICODE), now(), $id]);
            if (!empty($_POST['save_client']) && $inv['client_id']) db()->prepare('UPDATE clients SET email = ?, updated = ? WHERE id = ?')->execute([$em, now(), $inv['client_id']]);
            log_event($id, 'edit', 'Dodano e-mail klienta: ' . $em);
            if (($_POST['next'] ?? '') === 'remind') { $a = 'remind'; goto remind; }
            goto send;
        case 'send':
            send:
            $inv = must_invoice($id);
            if ($inv['status'] !== 'issued' && $inv['status'] !== 'paid') { flash('!Najpierw wystaw fakturę.'); redirect(P . '?a=view&id=' . $id); }
            if (trim((string)($inv['buyer']['email'] ?? '')) === '') { flash('!Dodaj e-mail klienta, żeby wysłać fakturę.'); redirect(P . '?a=edit&id=' . $id); }
            if (ksef_required($inv)) { $kr = ksef_try($inv); if ($kr['state'] !== 'accepted') { flash('!' . $kr['msg'] . ' Mail do klienta wyślemy, gdy KSeF nada numer.'); redirect(P . '?a=view&id=' . $id); } $inv = invoice_by_id($id); }
            if (send_invoice_mail($inv, 'new')) {
                db()->prepare('UPDATE invoices SET sent_at = COALESCE(sent_at, ?), updated = ? WHERE id = ?')->execute([now(), now(), $id]);
                flash('Faktura wysłana do ' . $inv['buyer']['email'] . '.');
            } else flash('!Nie udało się wysłać maila. Spróbuj ponownie za chwilę.');
            redirect(P . '?a=view&id=' . $id);
        case 'remind':
            remind:
            $inv = must_invoice($id);
            if ($inv['status'] !== 'issued') redirect(P . '?a=view&id=' . $id);
            $ok = send_invoice_mail($inv, 'manual');
            if ($ok) log_event($id, 'manual', reminder_label('manual'));
            $sr = sms_invoice($inv, 'manual');
            flash(($ok ? '' : '!') . ($ok ? 'Przypomnienie wysłane mailem.' : 'Nie udało się wysłać maila z przypomnieniem.') . ($sr ? ' ' . $sr[1] . '.' : ''));
            redirect(P . '?a=view&id=' . $id);
        case 'paid':
            $inv = must_invoice($id);
            $d = (string)($_POST['paid_date'] ?? today()); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $d = today();
            $amt = to_cents((string)($_POST['paid_amount'] ?? '')) ?: (int)$inv['gross'];
            db()->prepare("UPDATE invoices SET status = 'paid', paid_at = ?, paid_amount = ?, paid_method = ?, updated = ? WHERE id = ?")->execute([$d . ' 12:00:00', $amt, 'przelew', now(), $id]);
            log_event($id, 'paid', 'Oznaczona jako opłacona: ' . money($amt, $inv['currency'] ?? 'PLN') . ', ' . pl_date($d));
            flash('Faktura ' . $inv['number'] . ' oznaczona jako opłacona.');
            redirect(P . '?a=view&id=' . $id);
        case 'unpaid':
            must_invoice($id);
            db()->prepare("UPDATE invoices SET status = 'issued', paid_at = NULL, paid_amount = NULL, paid_method = NULL, declared_at = NULL, updated = ? WHERE id = ?")->execute([now(), $id]);
            log_event($id, 'edit', 'Cofnięto oznaczenie wpłaty');
            redirect(P . '?a=view&id=' . $id);
        case 'undeclare':
            must_invoice($id);
            db()->prepare('UPDATE invoices SET declared_at = NULL, updated = ? WHERE id = ?')->execute([now(), $id]);
            log_event($id, 'edit', 'Wpłata nie dotarła, przypomnienia wznowione');
            redirect(P . '?a=view&id=' . $id);
        case 'cancel':
            $inv = must_invoice($id);
            db()->prepare("UPDATE invoices SET status = 'cancelled', updated = ? WHERE id = ?")->execute([now(), $id]);
            log_event($id, 'edit', 'Faktura anulowana');
            flash('Faktura ' . $inv['number'] . ' anulowana. Przypomnienia wyłączone.');
            redirect(P . '?a=view&id=' . $id);
        case 'delete':
            $inv = must_invoice($id);
            if ($inv['status'] === 'draft' || can_delete($inv)) {
                foreach (['events', 'reminders'] as $t) db()->prepare("DELETE FROM $t WHERE invoice_id = ?")->execute([$id]);
                db()->prepare('DELETE FROM invoices WHERE id = ?')->execute([$id]);
                flash($inv['status'] === 'draft' ? 'Szkic usunięty.' : 'Faktura ' . $inv['number'] . ' usunięta. Ten numer wróci do użycia.');
            }
            redirect(P);
        case 'ksef_send':
            $inv = must_invoice($id);
            if (!in_array($inv['status'], ['issued', 'paid'], true) || ($inv['ksef_state'] ?? '') === 'accepted' || !empty($inv['ksef'])) redirect(P . '?a=view&id=' . $id);
            $kr = ksef_try($inv);
            flash(($kr['state'] === 'accepted' ? '' : ($kr['state'] === 'pending' ? '' : '!')) . $kr['msg']);
            redirect(P . '?a=view&id=' . $id);
        case 'ksef_poll':
            $inv = must_invoice($id);
            try { $kr = ksef_poll($id, 3); flash(($kr['state'] === 'rejected' ? '!' : '') . $kr['msg']); } catch (Throwable $e) { flash('!' . $e->getMessage()); }
            redirect(P . '?a=view&id=' . $id);
        case 'ksef_check':
            try { flash(ksef_check()); } catch (Throwable $e) { flash('!' . $e->getMessage()); }
            redirect(P . '?a=settings#ksef');
        case 'ksef_xml':
            $inv = must_invoice($id);
            try { $xml = ksef_xml($inv); } catch (Throwable $e) { flash('!' . $e->getMessage()); redirect(P . '?a=view&id=' . $id); }
            $f = CV_DATA . '/ksef-' . $id . '.xml';
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . str_replace('.pdf', '.xml', invoice_pdf_name($inv)) . '"');
            echo is_file($f) && ($inv['ksef_state'] ?? '') !== '' ? file_get_contents($f) : $xml; exit;
        case 'ksef':
            must_invoice($id);
            $k = trim(mb_substr((string)($_POST['ksef'] ?? ''), 0, 80));
            db()->prepare('UPDATE invoices SET ksef = ?, updated = ? WHERE id = ?')->execute([$k !== '' ? $k : null, now(), $id]);
            if ($k !== '') log_event($id, 'edit', 'Numer KSeF: ' . $k);
            flash('Zapisano numer KSeF.');
            redirect(P . '?a=view&id=' . $id);
        case 'auto':
            must_invoice($id);
            $on = !empty($_POST['on']) ? 1 : 0;
            db()->prepare('UPDATE invoices SET reminders = ?, updated = ? WHERE id = ?')->execute([$on, now(), $id]);
            log_event($id, 'edit', $on ? 'Automatyczne przypomnienia włączone' : 'Automatyczne przypomnienia wyłączone');
            redirect(P . '?a=view&id=' . $id);
        case 'client_save':
            $c = client_from($_POST);
            if ($c['name'] === '') { flash('!Podaj nazwę klienta.'); redirect(P . '?a=clients'); }
            if ($id) db()->prepare('UPDATE clients SET nip=?, name=?, addr1=?, addr2=?, country=?, phone=?, email=?, cc=?, updated=? WHERE id=?')->execute([$c['nip'], $c['name'], $c['addr1'], $c['addr2'], $c['country'], $c['phone'], $c['email'], $c['cc'], now(), $id]);
            else db()->prepare('INSERT INTO clients (nip, name, addr1, addr2, country, phone, email, cc, created, updated) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$c['nip'], $c['name'], $c['addr1'], $c['addr2'], $c['country'], $c['phone'], $c['email'], $c['cc'], now(), now()]);
            $cid = $id ?: (int)db()->lastInsertId();
            db()->prepare('UPDATE clients SET no_remind = ?, auto_paid = ? WHERE id = ?')->execute([!empty($_POST['no_remind']) ? 1 : 0, !empty($_POST['auto_paid']) ? 1 : 0, $cid]);
            if (!empty($_POST['no_remind'])) db()->prepare("UPDATE invoices SET reminders = 0 WHERE client_id = ? AND status = 'issued'")->execute([$cid]);
            flash('Klient zapisany.');
            redirect(P . '?a=clients');
        case 'client_delete':
            $n = db()->prepare('SELECT COUNT(*) FROM invoices WHERE client_id = ?'); $n->execute([$id]);
            if ((int)$n->fetchColumn() > 0) { flash('!Ten klient ma faktury, więc zostaje na liście.'); redirect(P . '?a=clients'); }
            db()->prepare('DELETE FROM clients WHERE id = ?')->execute([$id]);
            flash('Klient usunięty.');
            redirect(P . '?a=clients');
        case 'settings_save':
            $keys = ['seller_name', 'seller_addr1', 'seller_addr2', 'seller_nip', 'seller_email', 'seller_phone', 'bank_name', 'bank_account', 'bank_swift', 'place', 'term_days', 'mail_from', 'mail_reply', 'r_before_days', 'r_w1_days', 'r_w2_days', 'interest_rate', 'footer_note'];
            foreach ($keys as $k) if (isset($_POST[$k])) set_setting($k, trim(mb_substr((string)$_POST[$k], 0, 300)));
            if (!empty($_POST['_main'])) foreach (['mail_copy', 'r_before_on', 'r_due_on', 'r_w1_on', 'r_w2_on', 'notify_open'] as $k) set_setting($k, !empty($_POST[$k]) ? '1' : '0');
            if (!empty($_POST['_ksef'])) set_setting('ksef_auto', !empty($_POST['ksef_auto']) ? '1' : '0');
            if (isset(KSEF_ENV[(string)($_POST['ksef_env'] ?? '')]) && $_POST['ksef_env'] !== setting('ksef_env')) { set_setting('ksef_env', (string)$_POST['ksef_env']); set_setting('ksef_session', ''); }
            if (isset($_POST['zw_basis'])) set_setting('zw_basis', trim(mb_substr((string)$_POST['zw_basis'], 0, 200)));
            $tok = trim((string)($_POST['ksef_token'] ?? ''));
            if ($tok !== '') { set_setting('ksef_token', $tok); set_setting('ksef_session', ''); }
            if (!empty($_POST['ksef_token_clear'])) { set_setting('ksef_token', ''); set_setting('ksef_session', ''); }
            $acc = nrb_digits((string)($_POST['bank_account'] ?? ''));
            if ($acc !== '' && strlen($acc) !== 26) { flash('!Numer konta powinien mieć 26 cyfr. Sprawdź go jeszcze raz.'); redirect(P . '?a=settings'); }
            $ns = (int)($_POST['next_seq'] ?? 0);
            if ($ns > 0) { set_setting('next_seq', (string)$ns); set_setting('next_seq_ym', date('Y-m')); }
            flash('Ustawienia zapisane.');
            redirect(P . '?a=settings');
        case 'password':
            $s = db()->prepare('SELECT pass FROM users WHERE id = ?'); $s->execute([user_id()]);
            $new = (string)($_POST['new'] ?? '');
            if (!password_verify((string)($_POST['old'] ?? ''), (string)$s->fetchColumn())) { flash('!Obecne hasło się nie zgadza.'); redirect(P . '?a=settings'); }
            if (mb_strlen($new) < 10) { flash('!Nowe hasło musi mieć co najmniej 10 znaków.'); redirect(P . '?a=settings'); }
            db()->prepare('UPDATE users SET pass = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), user_id()]);
            flash('Hasło zmienione.');
            redirect(P . '?a=settings');
        case 'sms_settings':
            foreach (['sms_from', 'sms_api'] as $k) if (isset($_POST[$k])) set_setting($k, trim(mb_substr((string)$_POST[$k], 0, 60)));
            if (trim((string)($_POST['sms_token'] ?? '')) !== '') set_setting('sms_token', trim((string)$_POST['sms_token']));
            if (!empty($_POST['sms_token_clear'])) set_setting('sms_token', '');
            foreach (['sms_on', 'sms_before', 'sms_due', 'sms_w1', 'sms_w2', 'sms_manual'] as $k) set_setting($k, !empty($_POST[$k]) ? '1' : '0');
            flash('Ustawienia SMS zapisane.');
            redirect(P . '?a=settings#sms');
        case 'sms_test':
            [$ok, $msg] = sms_send((string)($_POST['to'] ?? ''), 'Creative Vision: test SMS z panelu faktur. Jesli to czytasz, przypomnienia SMS dzialaja.');
            flash(($ok ? '' : '!') . $msg);
            redirect(P . '?a=settings#sms');
        case 'mail_settings':
            foreach (['imap_user', 'imap_host', 'imap_port', 'smtp_host', 'smtp_port'] as $k) if (isset($_POST[$k])) set_setting($k, trim(mb_substr((string)$_POST[$k], 0, 200)));
            $pw = (string)($_POST['imap_pass'] ?? '');
            if ($pw !== '') set_setting('imap_pass', $pw);
            if (!empty($_POST['imap_pass_clear'])) set_setting('imap_pass', '');
            set_setting('smtp_on', !empty($_POST['smtp_on']) ? '1' : '0');
            set_setting('imap_sent', '');
            flash('Ustawienia poczty zapisane.');
            redirect(P . '?a=settings#poczta');
        case 'mail_check':
            try {
                $im = imap_open_box(); $n = $im->select('INBOX'); sent_box($im); unset($im);
                $msg = 'Skrzynka działa: ' . $n . ' ' . plural($n, 'wiadomość', 'wiadomości', 'wiadomości') . ' w odebranych.';
                try { smtp_check(); $msg .= ' Wysyłka przez SMTP też działa.'; } catch (Throwable $e) { $msg .= ' Ale wysyłka SMTP: ' . $e->getMessage(); }
                flash($msg);
            } catch (Throwable $e) { flash('!' . $e->getMessage()); }
            redirect(P . '?a=settings#poczta');
        case 'mail_send':
            $to = array_filter(preg_split('/[\s,;]+/', (string)($_POST['to'] ?? '')) ?: []);
            $subj = trim((string)($_POST['subject'] ?? '')); $txt = trim((string)($_POST['body'] ?? ''));
            if (!$to || $subj === '' || $txt === '') { flash('!Uzupełnij adresata, temat i treść.'); redirect($_SERVER['HTTP_REFERER'] ?? P . '?a=inbox'); }
            $quote = trim((string)($_POST['quote'] ?? ''));
            $html = mail_layout($subj, mb_substr($txt, 0, 120), m_text(nl2br(e($txt)), 44) . ($quote !== '' ? m_quote($quote) : '') . m_sign());
            $att = [];
            if (!empty($_POST['inv']) && ($ai = invoice_by_id((int)$_POST['inv'])) && $ai['status'] !== 'draft') $att[invoice_pdf_name($ai)] = invoice_pdf($ai);
            $ok = send_mail($to, $subj, $html, $txt . ($quote !== '' ? "\n\n" . preg_replace('/^/m', '> ', $quote) : ''), $att, false, ['In-Reply-To' => (string)($_POST['irt'] ?? ''), 'References' => trim((string)($_POST['refs'] ?? '') . ' ' . (string)($_POST['irt'] ?? ''))]);
            flash($ok ? 'Wiadomość wysłana do ' . implode(', ', $to) . '.' : '!Nie udało się wysłać: ' . mail_last_error());
            redirect(P . '?a=inbox');
        case 'mail_delete':
            $box = ($_POST['box'] ?? '') === 'sent' ? 'sent' : 'INBOX';
            $uids = array_values(array_filter(array_map('intval', (array)($_POST['uids'] ?? [$_POST['uid'] ?? 0]))));
            try { $im = imap_open_box(); $im->select(real_box($im, $box)); $im->trash($uids, trash_box($im)); if ($box === 'INBOX') set_setting('imap_unseen', (string)count($im->uids('UNSEEN'))); flash(count($uids) === 1 ? 'Wiadomość przeniesiona do kosza.' : 'Przeniesiono do kosza: ' . count($uids) . '.'); }
            catch (Throwable $e) { flash('!' . $e->getMessage()); }
            redirect(P . '?a=inbox&box=' . $box);
        case 'mail_unseen':
            try { $im = imap_open_box(); $im->select(real_box($im, (string)($_POST['box'] ?? 'INBOX'))); $im->flag((int)($_POST['uid'] ?? 0), '\\Seen', false); } catch (Throwable $e) { flash('!' . $e->getMessage()); }
            redirect(P . '?a=inbox');
        case 'testmail':
            [$sj, $h, $t] = admin_mail('Test maila z panelu faktur', 'Test wysyłki · panel faktur', 'Maile z panelu', 'Jeśli to czytasz, wysyłka z panelu faktur działa poprawnie. Sprawdź też, czy wiadomość nie wpadła do spamu.', null, 'działają');
            $to = setting('mail_reply');
            flash(send_mail([$to], $sj, $h, $t, [], false) ? 'Wysłano test na ' . $to . '. Sprawdź skrzynkę i folder spam.' : '!Nie udało się wysłać testu.');
            redirect(P . '?a=settings');
        case 'import':
            $raw = is_uploaded_file($_FILES['file']['tmp_name'] ?? '') ? (string)file_get_contents($_FILES['file']['tmp_name']) : '';
            $j = json_decode($raw, true);
            if (!is_array($j) || !isset($j['invoices']) || !is_array($j['invoices'])) { flash('!To nie wygląda na plik importu faktur.'); redirect(P . '?a=settings#import'); }
            [$ok, $skip] = import_invoices($j['invoices']);
            flash('Zaimportowano ' . $ok . ' ' . plural($ok, 'fakturę', 'faktury', 'faktur') . ($skip ? '. Pominięte, bo już są w panelu: ' . implode(', ', $skip) : '') . '.');
            redirect(P . '?a=list&y=' . date('Y') . '&m=0');
        case 'run':
            $r = run_reminders(true);
            flash('Sprawdzono przypomnienia. Wysłano: ' . count($r['sent'] ?? []) . '.');
            redirect(P);
    }
    redirect(P);
}

// ---------- widoki GET ----------
switch ($a) {
    case 'nip': nip_lookup((string)($_GET['nip'] ?? ''));
    case 'pdf':
        $inv = must_invoice((int)($_GET['id'] ?? 0));
        $pdf = invoice_pdf($inv);
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . (isset($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . invoice_pdf_name($inv) . '"');
        echo $pdf; exit;
    case 'new': case 'edit': view_form($a === 'edit' ? must_invoice((int)($_GET['id'] ?? 0)) : null);
    case 'view': view_invoice(must_invoice((int)($_GET['id'] ?? 0)));
    case 'clients': view_clients();
    case 'settings': view_settings();
    case 'unseen':
        header('Content-Type: application/json; charset=utf-8');
        $n = (int)setting('imap_unseen');
        if (inbox_enabled() && time() - (int)setting('imap_unseen_at') > 50) {
            set_setting('imap_unseen_at', (string)time());
            try { $im = imap_open_box(); $im->select('INBOX'); $n = count($im->uids('UNSEEN')); set_setting('imap_unseen', (string)$n); } catch (Throwable $e) { /* zostaje ostatnia wartość */ }
        }
        echo json_encode(['unseen' => $n]); exit;
    case 'inbox': view_inbox();
    case 'mail': view_mail();
    case 'compose': view_compose();
    case 'mail_att':
        try {
            $im = imap_open_box(); $box = (string)($_GET['box'] ?? 'INBOX'); $im->select(real_box($im, $box));
            $m = mime_parse($im->raw((int)($_GET['uid'] ?? 0)));
            $at = $m['att'][(int)($_GET['i'] ?? -1)] ?? null;
            if (!$at) { http_response_code(404); exit('Nie ma takiego załącznika.'); }
            $name = preg_replace('/[^\w.\- ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/u', '_', (string)$at[0]);
            header('Content-Type: application/octet-stream');
            header("Content-Disposition: attachment; filename=\"" . $name . "\"; filename*=UTF-8''" . rawurlencode($name));
            header('X-Content-Type-Options: nosniff');
            echo $at[2]; exit;
        } catch (Throwable $e) { flash('!' . $e->getMessage()); redirect(P . '?a=inbox'); }
    default: run_auto_paid(); run_reminders(); run_recurring(); ksef_poll_pending(); view_list();
}

// =====================================================================

function must_invoice(int $id): array {
    $inv = invoice_by_id($id);
    if (!$inv) { http_response_code(404); flash('!Nie ma takiej faktury.'); redirect(P); }
    return $inv;
}

function client_from(array $p): array {
    $f = fn($k) => trim(mb_substr((string)($p[$k] ?? ''), 0, 300));
    return ['nip' => $f('nip'), 'name' => $f('name'), 'addr1' => $f('addr1'), 'addr2' => $f('addr2'), 'country' => $f('country'), 'phone' => $f('phone'), 'email' => $f('email'), 'cc' => $f('cc')];
}

function next_number(string $ym): array {
    $s = db()->prepare('SELECT MAX(seq) FROM invoices WHERE ym = ?'); $s->execute([$ym]);
    $n = (int)$s->fetchColumn() + 1;
    if (setting('next_seq_ym') === $ym) $n = max($n, (int)setting('next_seq'));
    [$y, $m] = explode('-', $ym);
    return [$n, $n . '/' . (int)$m . '/' . $y];
}

/** Zapis faktury z formularza. $act: draft | issue | send. Zwraca [id, błąd]. */
function save_invoice(?int $id, array $p, string $act): array {
    $old = $id ? invoice_by_id($id) : null;
    if ($old && !can_edit($old)) return [$id, 'Tej faktury nie można już edytować.'];
    $buyer = client_from($p);
    $items = [];
    foreach ((array)($p['items'] ?? []) as $it) {
        if (!is_array($it) || trim((string)($it['name'] ?? '')) === '') continue;
        $items[] = ['name' => (string)$it['name'], 'desc' => (string)($it['desc'] ?? ''), 'qty' => (string)($it['qty'] ?? '1'), 'unit' => (string)($it['unit'] ?? 'usł.'), 'price' => (string)($it['price'] ?? '0'), 'vat' => (string)($it['vat'] ?? '23')];
    }
    $c = calc_items($items);
    $store = array_map(fn($i) => ['name' => $i['name'], 'desc' => $i['desc'], 'qty' => $i['qty'], 'unit' => $i['unit'], 'price' => $i['price'], 'vat' => $i['vat']], $c['items']);
    $date = fn($k, $def) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($p[$k] ?? '')) ? (string)$p[$k] : $def;
    $issue = $date('issue_date', today()); $sale = $date('sale_date', $issue);
    $term = max(0, min(365, (int)($p['term_days'] ?? setting('term_days'))));
    $due = $date('due_date', date('Y-m-d', strtotime($issue . " +$term days")));
    $term = max(0, days_between($issue, $due));
    if ($act !== 'draft') {
        if ($buyer['name'] === '') return [$id, 'Podaj nazwę nabywcy.'];
        if (!$c['items']) return [$id, 'Dodaj co najmniej jedną pozycję.'];
        if ($act === 'send' && !filter_var($buyer['email'], FILTER_VALIDATE_EMAIL)) return [$id, 'Podaj e-mail klienta, żeby wysłać fakturę.'];
    }
    $pdo = db();
    $pdo->beginTransaction();
    $clientId = $old['client_id'] ?? null;
    if (!empty($p['save_client']) && $buyer['name'] !== '') {
        $cid = (int)($p['client_id'] ?? 0);
        if (!$cid && $buyer['nip'] !== '') { $s = $pdo->prepare('SELECT id FROM clients WHERE REPLACE(REPLACE(nip, "-", ""), " ", "") = ?'); $s->execute([nip_digits($buyer['nip'])]); $cid = (int)$s->fetchColumn(); }
        if ($cid) $pdo->prepare('UPDATE clients SET nip=?, name=?, addr1=?, addr2=?, country=?, phone=?, email=?, cc=?, updated=? WHERE id=?')->execute([$buyer['nip'], $buyer['name'], $buyer['addr1'], $buyer['addr2'], $buyer['country'], $buyer['phone'], $buyer['email'], $buyer['cc'], now(), $cid]);
        else { $pdo->prepare('INSERT INTO clients (nip, name, addr1, addr2, country, phone, email, cc, created, updated) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$buyer['nip'], $buyer['name'], $buyer['addr1'], $buyer['addr2'], $buyer['country'], $buyer['phone'], $buyer['email'], $buyer['cc'], now(), now()]); $cid = (int)$pdo->lastInsertId(); }
        $clientId = $cid;
    } elseif ((int)($p['client_id'] ?? 0)) $clientId = (int)$p['client_id'];
    $f = [
        'client_id' => $clientId, 'buyer' => json_encode($buyer, JSON_UNESCAPED_UNICODE), 'issue_date' => $issue, 'sale_date' => $sale, 'due_date' => $due, 'term_days' => $term,
        'place' => trim((string)($p['place'] ?? setting('place'))), 'items' => json_encode($store, JSON_UNESCAPED_UNICODE),
        'book_ym' => preg_match('/^\d{4}-\d{2}$/', (string)($p['book_ym'] ?? '')) ? (string)$p['book_ym'] : substr($issue, 0, 7),
        'currency' => isset(currencies()[(string)($p['currency'] ?? '')]) ? (string)$p['currency'] : 'PLN',
        'lang' => ($p['lang'] ?? '') === 'pl-en' ? 'pl-en' : 'pl',
        'recurring' => !empty($p['recurring']) ? 1 : 0,
        'net' => $c['net'], 'vat' => $c['vat'], 'gross' => $c['gross'], 'notes' => trim((string)($p['notes'] ?? '')), 'reminders' => !empty($p['reminders']) ? 1 : 0, 'updated' => now(),
    ];
    if ($old) {
        $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($f)));
        $pdo->prepare("UPDATE invoices SET $set WHERE id = :id")->execute($f + ['id' => $id]);
        log_event($id, 'edit', 'Zmiany zapisane');
    } else {
        $f['created'] = now();
        $pdo->prepare('INSERT INTO invoices (' . implode(', ', array_keys($f)) . ') VALUES (:' . implode(', :', array_keys($f)) . ')')->execute($f);
        $id = (int)$pdo->lastInsertId();
        log_event($id, 'create', 'Utworzono szkic');
    }
    if ($act !== 'draft' && (!$old || $old['status'] === 'draft')) {
        $ym = substr($issue, 0, 7);
        [$seq, $num] = next_number($ym);
        $pdo->prepare("UPDATE invoices SET status = 'issued', seq = ?, ym = ?, number = ?, token = ? WHERE id = ?")->execute([$seq, $ym, $num, bin2hex(random_bytes(16)), $id]);
        if (setting('next_seq_ym') === $ym && (int)setting('next_seq') <= $seq) set_setting('next_seq', '');
        log_event($id, 'issue', 'Wystawiono fakturę ' . $num);
    }
    $pdo->commit();
    $kmsg = '';
    if ($act !== 'draft') {
        $inv = invoice_by_id($id);
        if (ksef_required($inv) && setting('ksef_auto') === '1') {
            $kr = ksef_try($inv);
            $kmsg = ' ' . $kr['msg'];
            if ($kr['state'] !== 'accepted') {
                flash('!Faktura ' . $inv['number'] . ' zapisana.' . $kmsg . ($act === 'send' ? ' Mail do klienta wyślemy, gdy KSeF nada numer.' : ''));
                return [$id, null];
            }
        }
    }
    if ($act === 'send') {
        $inv = invoice_by_id($id);
        if (send_invoice_mail($inv, 'new')) {
            db()->prepare('UPDATE invoices SET sent_at = COALESCE(sent_at, ?) WHERE id = ?')->execute([now(), $id]);
            flash('Faktura ' . $inv['number'] . ' wystawiona i wysłana do ' . $buyer['email'] . '.' . $kmsg);
        } else flash('!Faktura wystawiona, ale maila nie udało się wysłać. Spróbuj przyciskiem „Wyślij”.');
    } elseif ($act === 'issue') flash('Faktura wystawiona.' . $kmsg);
    else flash('Szkic zapisany.');
    return [$id, null];
}

/** Wystawioną fakturę można usunąć tylko, gdy ma ostatni numer w miesiącu (bez dziury w numeracji) i nie jest opłacona. */
function can_delete(array $inv): bool {
    if ($inv['status'] === 'draft') return true;
    if ($inv['status'] === 'paid' || empty($inv['ym']) || in_array((string)($inv['ksef_state'] ?? ''), ['accepted', 'pending'], true) || !empty($inv['ksef'])) return false;
    $s = db()->prepare('SELECT MAX(seq) FROM invoices WHERE ym = ?'); $s->execute([$inv['ym']]);
    return (int)$s->fetchColumn() === (int)$inv['seq'];
}
/** Import faktur wystawionych wcześniej (np. z iFirmy). Numery zostają bez zmian. Zwraca [ile dodano, pominięte numery]. */
function import_invoices(array $list): array {
    $pdo = db(); $ok = 0; $skip = [];
    $chk = $pdo->prepare('SELECT COUNT(*) FROM invoices WHERE number = ?');
    foreach ($list as $x) {
        $num = trim((string)($x['number'] ?? ''));
        if (!preg_match('#^(\d+)/(\d{1,2})/(\d{4})$#', $num, $m)) continue;
        $chk->execute([$num]); if ((int)$chk->fetchColumn() > 0) { $skip[] = $num; continue; }
        $buyer = client_from((array)($x['buyer'] ?? []));
        $c = calc_items((array)($x['items'] ?? []));
        $store = array_map(fn($i) => ['name' => $i['name'], 'desc' => $i['desc'], 'qty' => $i['qty'], 'unit' => $i['unit'], 'price' => $i['price'], 'vat' => $i['vat']], $c['items']);
        $d = fn($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($x[$k] ?? '')) ? (string)$x[$k] : null;
        $issue = $d('issue_date') ?? today(); $due = $d('due_date') ?? $issue;
        $ym = sprintf('%04d-%02d', (int)$m[3], (int)$m[2]);
        $pdo->beginTransaction();
        $cid = null;
        if ($buyer['name'] !== '') {
            $q = $buyer['nip'] !== '' ? $pdo->prepare('SELECT id FROM clients WHERE REPLACE(REPLACE(REPLACE(nip, "-", ""), " ", ""), "PL", "") = ?') : $pdo->prepare('SELECT id FROM clients WHERE name = ?');
            $q->execute([$buyer['nip'] !== '' ? str_replace('PL', '', preg_replace('/[\s-]/', '', $buyer['nip'])) : $buyer['name']]);
            $cid = (int)$q->fetchColumn() ?: null;
            if (!$cid) { $pdo->prepare('INSERT INTO clients (nip, name, addr1, addr2, country, phone, email, cc, created, updated) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$buyer['nip'], $buyer['name'], $buyer['addr1'], $buyer['addr2'], $buyer['country'], $buyer['phone'], $buyer['email'], $buyer['cc'], now(), now()]); $cid = (int)$pdo->lastInsertId(); }
        }
        $paid = ($x['status'] ?? '') === 'paid';
        $f = ['number' => $num, 'seq' => (int)$m[1], 'ym' => $ym, 'book_ym' => preg_match('/^\d{4}-\d{2}$/', (string)($x['book_ym'] ?? '')) ? $x['book_ym'] : $ym,
            'client_id' => $cid, 'buyer' => json_encode($buyer, JSON_UNESCAPED_UNICODE), 'issue_date' => $issue, 'sale_date' => $d('sale_date') ?? $issue, 'due_date' => $due,
            'term_days' => max(0, days_between($issue, $due)), 'place' => (string)($x['place'] ?? setting('place')), 'items' => json_encode($store, JSON_UNESCAPED_UNICODE),
            'net' => $c['net'], 'vat' => $c['vat'], 'gross' => $c['gross'], 'notes' => (string)($x['notes'] ?? ''), 'status' => $paid ? 'paid' : 'issued', 'token' => bin2hex(random_bytes(16)),
            'sent_at' => $issue . ' 12:00:00', 'paid_at' => $paid ? (($d('paid_date') ?? $due) . ' 12:00:00') : null, 'paid_amount' => $paid ? $c['gross'] : null, 'paid_method' => $paid ? 'przelew' : null,
            'reminders' => !empty($x['reminders']) ? 1 : 0, 'currency' => isset(currencies()[(string)($x['currency'] ?? '')]) ? $x['currency'] : 'PLN', 'lang' => ($x['lang'] ?? '') === 'pl-en' ? 'pl-en' : 'pl',
            'recurring' => !empty($x['recurring']) ? 1 : 0, 'source' => (string)($x['source'] ?? 'import'), 'created' => now(), 'updated' => now()];
        $pdo->prepare('INSERT INTO invoices (' . implode(', ', array_keys($f)) . ') VALUES (:' . implode(', :', array_keys($f)) . ')')->execute($f);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
        log_event($id, 'create', 'Zaimportowano fakturę ' . $num . ' (' . $f['source'] . ')' . ($paid ? ', opłacona' : ''));
        $ok++;
    }
    $pdo->exec("UPDATE clients SET no_remind = 1, auto_paid = 1 WHERE id IN (SELECT client_id FROM invoices WHERE currency != 'PLN' AND recurring = 1 AND client_id IS NOT NULL)");
    $pdo->exec("UPDATE invoices SET reminders = 0 WHERE client_id IN (SELECT id FROM clients WHERE no_remind = 1)");
    return [$ok, $skip];
}

function can_edit(array $inv): bool { return $inv['status'] === 'draft' || ($inv['status'] === 'issued' && empty($inv['sent_at']) && !in_array((string)($inv['ksef_state'] ?? ''), ['accepted', 'pending'], true)); }

/** Czy faktura powinna trafić do KSeF: jest token, faktura wystawiona w panelu i nie ma jeszcze numeru KSeF. */
function ksef_required(array $inv): bool {
    return ksef_enabled() && in_array($inv['status'], ['issued', 'paid'], true) && empty($inv['ksef']) && ($inv['ksef_state'] ?? '') !== 'accepted' && empty($inv['source']);
}
/** Wysyłka do KSeF z obsługą błędów. */
function ksef_try(array $inv): array {
    try { return ksef_send($inv); }
    catch (Throwable $e) {
        db()->prepare("UPDATE invoices SET ksef_state = CASE WHEN ksef_state = 'pending' THEN 'pending' ELSE 'error' END, ksef_error = ?, updated = ? WHERE id = ?")->execute([$e->getMessage(), now(), $inv['id']]);
        log_event((int)$inv['id'], 'error', 'KSeF: ' . $e->getMessage());
        return ['state' => 'error', 'ksef' => null, 'msg' => 'Nie udało się wysłać do KSeF: ' . $e->getMessage()];
    }
}

function nip_lookup(string $nip): void {
    header('Content-Type: application/json; charset=utf-8');
    $n = nip_digits($nip);
    if (strlen($n) !== 10) { echo json_encode(['error' => 'NIP powinien mieć 10 cyfr.']); exit; }
    $ch = curl_init('https://wl-api.mf.gov.pl/api/search/nip/' . $n . '?date=' . today());
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = is_string($res) ? json_decode($res, true) : null;
    $sub = $j['result']['subject'] ?? null;
    if ($code !== 200 || !$sub) { echo json_encode(['error' => 'Nie znalazłem firmy w wykazie VAT. Wpisz dane ręcznie.']); exit; }
    $addr = (string)($sub['workingAddress'] ?: $sub['residenceAddress'] ?: '');
    $pos = mb_strrpos($addr, ',');
    $a1 = $pos !== false ? trim(mb_substr($addr, 0, $pos)) : $addr; $a2 = $pos !== false ? trim(mb_substr($addr, $pos + 1)) : '';
    $nice = fn($s) => preg_replace_callback('/\b(Ul|Al|Os|Pl)\. /u', fn($m) => mb_strtolower($m[1]) . '. ', mb_convert_case(mb_strtolower($s), MB_CASE_TITLE));
    echo json_encode(['name' => (string)$sub['name'], 'addr1' => $nice($a1), 'addr2' => $nice($a2), 'nip' => substr($n, 0, 3) . '-' . substr($n, 3, 3) . '-' . substr($n, 6, 2) . '-' . substr($n, 8), 'status' => (string)($sub['statusVat'] ?? '')], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- szablony ----------
function icon(string $n): string {
    $p = [
        'list' => '<path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h8M8 17h5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.8c2 .7 3.2 2.5 3.6 5.2"/>',
        'cog' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'out' => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.9-3.9M4 5v4h4M4 13a8 8 0 0 0 14.9 3.9M20 19v-4h-4"/>',
        'mail' => '<path d="M3 5h18v14H3z"/><path d="m3 6 9 7 9-7"/>',
        'clip' => '<path d="M21 11.5 12.5 20a5 5 0 0 1-7-7L14 4.5a3.5 3.5 0 0 1 5 5L10.5 18a2 2 0 0 1-3-3L15 7.5"/>',
    ][$n] ?? '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="square" aria-hidden="true">' . $p . '</svg>';
}

function head(string $title): string {
    $v = substr((string)@md5_file(__DIR__ . '/assets/panel.css'), 0, 10);
    return '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
        . '<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0B0B0B"><title>' . e($title) . ' · Faktury Creative Vision</title>'
        . '<link rel="icon" href="/favicon.ico"><link rel="stylesheet" href="' . P . '/assets/panel.css?v=' . $v . '"></head>';
}

function flash_html(): string {
    $m = flash(); if (!$m) return '';
    $err = $m[0] === '!'; if ($err) $m = substr($m, 1);
    return '<div class="flash' . ($err ? ' err' : '') . '" role="status">' . e($m) . '</div>';
}

function page(string $title, string $active, string $body, string $kicker = '', string $acts = ''): never {
    $nav = [['inbox', 'Poczta <b class="bdg" id="mailbdg"' . (inbox_enabled() ? ' data-poll' : '') . ((int)setting('imap_unseen') > 0 ? '>' . (int)setting('imap_unseen') : ' hidden>') . '</b>', 'mail', P . '?a=inbox'], ['list', 'Faktury', 'list', P], ['new', 'Nowa faktura', 'plus', P . '?a=new'], ['clients', 'Klienci', 'users', P . '?a=clients'], ['settings', 'Ustawienia', 'cog', P . '?a=settings']];
    $links = '';
    foreach ($nav as [$k, $l, $i, $h]) $links .= '<a href="' . $h . '"' . ($active === $k ? ' class="on" aria-current="page"' : '') . ' title="' . strip_tags($l) . '">' . icon($i) . '<span class="tx">' . $l . '</span></a>';
    $v = substr((string)@md5_file(__DIR__ . '/assets/panel.js'), 0, 10);
    echo head($title) . '<body><div class="app"><nav class="nav" aria-label="Menu panelu">'
        . '<a class="brand" href="' . P . '"><img src="' . P . '/assets/logo-white.svg" alt="creative vision"></a>'
        . '<div class="grp"><p class="lbl">Finanse</p>' . $links . '</div>'
        . '<div class="bot"><span>Przypomnienia wysyłamy sami, w dni robocze rano.</span>'
        . '<form method="post" action="' . P . '?a=logout">' . csrf_field() . '<button class="link" style="color:#C9C9C9">Wyloguj</button></form></div></nav>'
        . '<main class="main" id="main">'
        . '<header class="top"><div>' . ($kicker ? '<p class="k">' . e($kicker) . '</p>' : '') . '<h1>' . e($title) . '</h1></div>' . ($acts ? '<div class="acts">' . $acts . '</div>' : '') . '</header>'
        . flash_html() . $body . '</main></div><script src="' . P . '/assets/panel.js?v=' . $v . '"></script></body></html>';
    exit;
}

function auth_page(string $title, string $lead, string $action, bool $setup): never {
    echo head($title) . '<body><div class="auth"><form class="box" method="post" action="' . P . '?a=' . $action . '">'
        . '<img src="' . P . '/assets/logo-black.svg" alt="creative vision" style="height:26px;width:auto;align-self:flex-start">'
        . '<h1>' . e($title) . '</h1><p>' . e($lead) . '</p>' . flash_html() . csrf_field()
        . '<label class="f">E-mail<input class="in" type="email" name="email" autocomplete="username" required></label>'
        . '<label class="f">Hasło' . ($setup ? ' (min. 10 znaków)' : '') . '<input class="in" type="password" name="pass" autocomplete="' . ($setup ? 'new-password' : 'current-password') . '" minlength="' . ($setup ? 10 : 1) . '" required></label>'
        . ($setup ? '<label class="f">Kod konfiguracji<input class="in" name="code" autocomplete="off" required></label>' : '')
        . '<button class="btn">' . ($setup ? 'Załóż konto' : 'Zaloguj się') . icon('arrow') . '</button></form></div></body></html>';
    exit;
}

function pl_month(int $m, bool $loc = false): string {
    $n = ['styczeń', 'luty', 'marzec', 'kwiecień', 'maj', 'czerwiec', 'lipiec', 'sierpień', 'wrzesień', 'październik', 'listopad', 'grudzień'];
    $l = ['styczniu', 'lutym', 'marcu', 'kwietniu', 'maju', 'czerwcu', 'lipcu', 'sierpniu', 'wrześniu', 'październiku', 'listopadzie', 'grudniu'];
    return ($loc ? $l : $n)[$m - 1];
}
function plural(int $n, string $one, string $few, string $many): string {
    if ($n === 1) return $one;
    $u = $n % 10; $t = $n % 100;
    return ($u >= 2 && $u <= 4 && !($t >= 12 && $t <= 14)) ? $few : $many;
}
function state_chip(array $inv): string {
    $st = inv_state($inv);
    if ($st === 'due' || $st === 'today' || $st === 'late') if (!empty($inv['declared_at'])) return '<span class="chip decl">Klient zgłosił wpłatę</span>';
    $l = ['draft' => 'Szkic', 'due' => 'Czeka na wpłatę', 'today' => 'Termin dziś', 'late' => 'Po terminie', 'paid' => 'Opłacona', 'cancelled' => 'Anulowana'][$st];
    return '<span class="chip ' . $st . '">' . $l . '</span>';
}
function when_text(array $inv): array {
    $st = inv_state($inv);
    if ($st === 'paid') return ['Zapłacona ' . pl_date($inv['paid_at']), 'ok-t'];
    if ($st === 'draft' || $st === 'cancelled') return ['', 'mut'];
    $d = days_between(today(), (string)$inv['due_date']);
    if ($d > 0) return ['za ' . $d . ' ' . plural($d, 'dzień', 'dni', 'dni'), 'mut'];
    if ($d === 0) return ['dziś', 'late-t'];
    return [-$d . ' ' . plural(-$d, 'dzień', 'dni', 'dni') . ' po terminie', 'late-t'];
}

// ---------- lista ----------
function view_list(): never {
    $tab = (string)($_GET['t'] ?? 'all'); $q = trim((string)($_GET['q'] ?? ''));
    $y = (int)($_GET['y'] ?? date('Y')); if ($y < 2000 || $y > 2100) $y = (int)date('Y');
    $m = isset($_GET['m']) ? max(0, min(12, (int)$_GET['m'])) : (int)date('n');
    $period = $m ? sprintf('%04d-%02d', $y, $m) : (string)$y;
    $periodTxt = $m ? pl_month($m) . ' ' . $y : 'cały ' . $y;
    $all = array_map('hydrate', db()->query('SELECT * FROM invoices ORDER BY COALESCE(issue_date, created) DESC, id DESC')->fetchAll());
    $bym = fn($i) => (string)($i['book_ym'] ?: substr((string)($i['issue_date'] ?: $i['created']), 0, 7));
    $inPeriod = fn($i) => str_starts_with($bym($i), $period);
    $cnt = ['all' => 0, 'open' => 0, 'late' => 0, 'paid' => 0, 'draft' => 0];
    $fxOpen = []; $fxLate = []; $fxSale = []; $open = 0; $openN = 0; $late = 0; $lateN = 0; $sale = 0; $saleNet = 0; $saleN = 0; $days = []; $months = []; $years = [(int)date('Y') => 1, $y => 1];
    foreach ($all as $i) {
        $st = inv_state($i); $b = $bym($i);
        $years[(int)substr($b, 0, 4)] = 1;
        if (substr($b, 0, 4) === (string)$y) $months[(int)substr($b, 5, 2)] = ($months[(int)substr($b, 5, 2)] ?? 0) + 1;
        $pln = ($i['currency'] ?? 'PLN') === 'PLN'; $cu = $i['currency'] ?? 'PLN';
        if (in_array($st, ['due', 'today', 'late'], true)) { if ($pln) $open += (int)$i['gross']; else $fxOpen[$cu] = ($fxOpen[$cu] ?? 0) + (int)$i['gross']; $openN++; }
        if ($st === 'late') { if ($pln) $late += (int)$i['gross']; else $fxLate[$cu] = ($fxLate[$cu] ?? 0) + (int)$i['gross']; $lateN++; }
        if ($st === 'paid') $days[] = max(0, days_between((string)$i['issue_date'], substr((string)$i['paid_at'], 0, 10)));
        if (!$inPeriod($i) && $q === '') continue;
        $cnt['all']++;
        if (in_array($st, ['due', 'today', 'late'], true)) $cnt['open']++;
        if ($st === 'late') $cnt['late']++;
        if ($st === 'paid') $cnt['paid']++;
        if ($st === 'draft') $cnt['draft']++;
        if ($inPeriod($i) && !in_array($st, ['draft', 'cancelled'], true)) { if ($pln) { $sale += (int)$i['gross']; $saleNet += (int)$i['net']; } else $fxSale[$cu] = ($fxSale[$cu] ?? 0) + (int)$i['gross']; $saleN++; }
    }
    $rows = array_filter($all, function ($i) use ($tab, $q, $inPeriod) {
        if ($q === '' && !$inPeriod($i)) return false;
        $st = inv_state($i);
        $ok = match ($tab) { 'open' => in_array($st, ['due', 'today', 'late'], true), 'late' => $st === 'late', 'paid' => $st === 'paid', 'draft' => $st === 'draft', default => true };
        if ($ok && $q !== '') $ok = mb_stripos(($i['number'] ?? '') . ' ' . ($i['buyer']['name'] ?? '') . ' ' . ($i['buyer']['nip'] ?? ''), $q) !== false;
        return $ok;
    });
    $avg = $days ? (int)round(array_sum($days) / count($days)) : null;
    $fx = function (array $a): string { $o = ''; foreach ($a as $k => $v) $o .= '<span class="fx">+ ' . money($v, $k) . '</span>'; return $o; };
    $kpi = '<section class="kpis" aria-label="Podsumowanie">'
        . '<div class="kpi dark"><span class="t">Do zapłaty</span><span class="v">' . money($open) . '</span>' . $fx($fxOpen) . '<span class="s">' . ($openN ? $openN . ' ' . plural($openN, 'faktura czeka', 'faktury czekają', 'faktur czeka') . ' na wpłatę' : 'Wszystko opłacone') . '</span></div>'
        . '<div class="kpi"><span class="t">Po terminie</span><span class="v' . ($late ? ' red' : '') . '">' . money($late) . '</span>' . $fx($fxLate) . '<span class="s">' . ($lateN ? $lateN . ' ' . plural($lateN, 'faktura', 'faktury', 'faktur') . ', wezwania idą same' : 'Nikt nie zalega') . '</span></div>'
        . '<div class="kpi"><span class="t">Sprzedaż: ' . e($periodTxt) . '</span><span class="v">' . money($sale) . '</span>' . $fx($fxSale) . '<span class="s">netto ' . money($saleNet) . ' · ' . $saleN . ' ' . plural($saleN, 'faktura', 'faktury', 'faktur') . '</span></div>'
        . '<div class="kpi"><span class="t">Średni czas zapłaty</span><span class="v">' . ($avg === null ? '–' : $avg . ' ' . plural($avg, 'dzień', 'dni', 'dni')) . '</span><span class="s">od wystawienia do wpłaty</span></div></section>';
    $qs = fn(array $o) => P . '?' . http_build_query(array_merge(['y' => $y, 'm' => $m, 't' => $tab], $o));
    $mp = '';
    for ($i = 1; $i <= 12; $i++) $mp .= '<a href="' . $qs(['m' => $i, 'q' => null]) . '" class="' . trim(($m === $i ? 'on' : '') . (!empty($months[$i]) ? ' has' : '')) . '" title="' . pl_month($i) . ' ' . $y . (!empty($months[$i]) ? ': ' . $months[$i] . ' ' . plural($months[$i], 'faktura', 'faktury', 'faktur') : '') . '"' . ($m === $i ? ' aria-current="true"' : '') . '>' . $i . '</a>';
    krsort($years); $yo = '';
    foreach (array_keys($years) as $yy) $yo .= '<option value="' . $yy . '"' . ($yy === $y ? ' selected' : '') . '>' . $yy . '</option>';
    $per = '<div class="period"><div class="pl"><span class="k">Miesiąc księgowy</span><b>' . e(ucfirst($periodTxt)) . '</b></div>'
        . '<nav class="months" aria-label="Miesiąc księgowy">' . $mp . '<a href="' . $qs(['m' => 0, 'q' => null]) . '" class="yr' . ($m === 0 ? ' on' : '') . '">Cały rok</a></nav>'
        . '<form method="get" action="' . P . '" class="yearf"><input type="hidden" name="m" value="' . $m . '"><input type="hidden" name="t" value="' . e($tab) . '"><label class="sr" for="yy">Rok</label><select class="in" id="yy" name="y" data-autosubmit>' . $yo . '</select></form></div>'
        . ($q !== '' ? '<div class="qnote">Wyniki wyszukiwania „' . e($q) . '” ze wszystkich miesięcy. <a class="link" href="' . $qs(['q' => null]) . '">Wyczyść</a></div>' : '');
    $tabs = '';
    foreach (['all' => 'Wszystkie', 'open' => 'Do zapłaty', 'late' => 'Po terminie', 'paid' => 'Opłacone', 'draft' => 'Szkice'] as $k => $l)
        $tabs .= '<a href="' . $qs(['t' => $k, 'q' => $q !== '' ? $q : null]) . '"' . ($tab === $k ? ' class="on" aria-current="true"' : '') . '>' . $l . ' <span>' . $cnt[$k] . '</span></a>';
    $tr = '';
    foreach ($rows as $i) {
        [$w, $wc] = when_text($i);
        $first = $i['calc']['items'][0]['name'] ?? '';
        $url = P . '?a=view&id=' . $i['id'];
        $act = match (inv_state($i)) { 'draft' => 'Dokończ', 'late' => 'Przypomnij', 'paid', 'cancelled' => '', default => empty($i['sent_at']) ? 'Wyślij' : '' };
        $tr .= '<tr class="row" data-href="' . $url . '"><td class="c-num nw"><a class="num" href="' . $url . '">' . e($i['number'] ?: 'Szkic') . '</a></td>'
            . '<td class="c-cli"><span>' . e($i['buyer']['name'] ?? 'Bez nabywcy') . '</span><span class="sub">' . e(mb_strimwidth($first, 0, 60, '…')) . '</span></td>'
            . '<td class="c-due nw">' . e(pl_date($i['due_date'])) . '<span class="sub ' . $wc . '">' . e($w) . '</span></td>'
            . '<td class="c-amt r amt">' . money((int)$i['gross'], $i['currency'] ?? 'PLN') . '</td>'
            . '<td class="c-st nw">' . state_chip($i) . '</td>'
            . '<td class="c-act r nw">' . ($act !== '' ? '<a class="link" href="' . $url . '">' . $act . '</a>' : '') . '</td></tr>';
    }
    $table = '<section class="card wide" aria-label="Lista faktur">' . $per . '<nav class="tabs" aria-label="Filtr faktur">' . $tabs . '</nav>'
        . ($tr ? '<div class="tw"><table class="list"><thead><tr><th>Numer</th><th>Klient</th><th>Termin</th><th class="r">Kwota brutto</th><th>Status</th><th><span class="sr">Akcje</span></th></tr></thead><tbody>' . $tr . '</tbody></table></div>'
            : '<div class="empty">' . ($cnt['all'] ? 'Brak faktur w tym widoku.' : ($all ? 'Brak faktur w tym okresie.' : 'Tu pojawią się Twoje faktury. Zacznij od pierwszej.')) . '</div>') . '</section>';
    $up = '';
    foreach (upcoming_reminders() as [$d, $k, $inv]) {
        $dl = $d === today() ? 'Dziś' : ($d === date('Y-m-d', strtotime('+1 day')) ? 'Jutro' : date('d.m', strtotime($d)));
        $up .= '<div class="it"><span class="d">' . $dl . '</span><div><b' . (in_array($k, ['w1', 'w2'], true) ? ' class="late-t"' : '') . '>' . e(reminder_label($k)) . '</b><small>' . e(($inv['buyer']['name'] ?? '') . ' · ' . $inv['number']) . '</small></div></div>';
    }
    $lastPaid = db()->query("SELECT * FROM invoices WHERE status = 'paid' ORDER BY paid_at DESC LIMIT 1")->fetch();
    $side = '<aside class="side" aria-label="Automatyczne wiadomości"><div class="card pad sec"><div class="hrow"><h2>Wyślemy sami</h2><a class="link" href="' . P . '?a=settings#auto">Ustawienia</a></div>'
        . '<div class="sched">' . ($up ?: '<span class="mut">Nic nie czeka w kolejce. Przypomnienia pojawią się tu, gdy wyślesz fakturę.</span>') . '</div></div>'
        . ($lastPaid ? '<div class="dark-card"><span class="k">Ostatnia wpłata</span><span class="v">' . e(json_decode($lastPaid['buyer'], true)['name'] ?? '') . ' · ' . money((int)($lastPaid['paid_amount'] ?: $lastPaid['gross']), $lastPaid['currency'] ?? 'PLN') . '</span><span class="s">Faktura ' . e($lastPaid['number']) . ', ' . e(pl_date($lastPaid['paid_at'])) . '</span></div>' : '')
        . '</aside>';
    $acts = '<form class="search" role="search" method="get" action="' . P . '"><input type="hidden" name="t" value="' . e($tab) . '"><input type="hidden" name="y" value="' . $y . '"><input type="hidden" name="m" value="' . $m . '">' . icon('search') . '<label class="sr" for="q">Szukaj faktury</label><input id="q" type="search" name="q" value="' . e($q) . '" placeholder="Szukaj klienta lub numeru"></form>'
        . '<a class="btn" href="' . P . '?a=new">Nowa faktura ' . icon('plus') . '</a>';
    page('Faktury', 'list', $kpi . '<div class="cols">' . $table . $side . '</div>', ucfirst($periodTxt), $acts);
}

// ---------- formularz ----------
function view_form(?array $inv): never {
    if ($inv && !can_edit($inv)) { flash('!Wysłanej faktury nie edytujemy. Jeśli coś trzeba zmienić, wystaw korektę.'); redirect(P . '?a=view&id=' . $inv['id']); }
    $f = $_SESSION['form'] ?? null; unset($_SESSION['form']);
    $b = $f ? client_from($f) : ($inv['buyer'] ?? ['nip' => '', 'name' => '', 'addr1' => '', 'addr2' => '', 'email' => '', 'cc' => '']);
    $items = $f ? array_values((array)($f['items'] ?? [])) : ($inv ? array_map(fn($i) => ['name' => $i['name'], 'desc' => $i['desc'], 'qty' => qty_fmt((float)$i['qty']), 'unit' => $i['unit'], 'price' => money((int)$i['price'], false), 'vat' => $i['vat']], $inv['calc']['items']) : []);
    if (!$items) $items = [['name' => '', 'desc' => '', 'qty' => '1', 'unit' => 'usł.', 'price' => '', 'vat' => '23']];
    $issue = $f['issue_date'] ?? ($inv['issue_date'] ?? today());
    $term = (int)($f['term_days'] ?? ($inv['term_days'] ?? setting('term_days')));
    $due = $f['due_date'] ?? ($inv['due_date'] ?? date('Y-m-d', strtotime($issue . " +$term days")));
    $clients = db()->query('SELECT id, nip, name, addr1, addr2, country, phone, email, cc FROM clients ORDER BY name')->fetchAll();
    if (!$inv && !$f && ($cid = (int)($_GET['client'] ?? 0))) foreach ($clients as $c) if ((int)$c['id'] === $cid) $b = $c;
    $opts = '<option value="">Nowy klient</option>';
    foreach ($clients as $c) $opts .= '<option value="' . $c['id'] . '"' . ((int)($inv['client_id'] ?? ($b['id'] ?? 0)) === (int)$c['id'] ? ' selected' : '') . '>' . e($c['name']) . '</option>';
    $vat = fn($sel) => implode('', array_map(fn($k, $l) => '<option value="' . $k . '"' . ((string)$sel === (string)$k ? ' selected' : '') . '>' . $l . '</option>', array_keys(vat_rates()), vat_rates()));
    $rows = '';
    foreach ($items as $n => $it) $rows .= item_row((string)$n, $it, $vat);
    $tpl = item_row('__N__', ['name' => '', 'desc' => '', 'qty' => '1', 'unit' => 'usł.', 'price' => '', 'vat' => '23'], $vat);
    $pills = '';
    foreach ([7, 14, 21, 30] as $d) $pills .= '<button type="button" data-term="' . $d . '"' . ($term === $d ? ' class="on"' : '') . '>' . $d . ' dni</button>';
    $v = fn($k) => e((string)($b[$k] ?? ''));
    $num = $inv['number'] ?? null;
    $body = '<form class="sec" method="post" action="' . P . '?a=save" id="invform" autocomplete="off">' . csrf_field() . '<input type="hidden" name="id" value="' . ($inv['id'] ?? '') . '">'
        . '<script type="application/json" id="clients">' . json_encode($clients, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>'
        . '<div class="cols"><div class="wide sec">'
        // nabywca
        . '<section class="card pad sec"><div class="hrow"><h2>Nabywca</h2><label class="f" style="flex-direction:row;align-items:center;gap:10px"><span>Klient</span><select class="in" name="client_id" id="client_id" style="height:40px;min-width:200px">' . $opts . '</select></label></div>'
        . '<div class="grid2"><label class="f">NIP<div class="nipw"><input class="in" name="nip" id="nip" value="' . $v('nip') . '" inputmode="numeric" placeholder="np. 527-319-96-87"><button type="button" class="btn ghost sm" id="nipbtn" style="height:46px">Pobierz dane</button></div><span id="nipmsg" class="mut" style="font-size:13px"></span></label>'
        . '<label class="f">Nazwa firmy<input class="in" name="name" id="name" value="' . $v('name') . '" required></label>'
        . '<label class="f">Ulica i numer<input class="in" name="addr1" id="addr1" value="' . $v('addr1') . '"></label>'
        . '<label class="f">Kod pocztowy i miasto<input class="in" name="addr2" id="addr2" value="' . $v('addr2') . '"></label>'
        . '<label class="f">Kraj (tylko spoza Polski)<input class="in" name="country" id="country" value="' . $v('country') . '" placeholder="np. Stany Zjednoczone / United States"></label>'
        . '<label class="f">E-mail do faktur<input class="in" type="email" name="email" id="email" value="' . $v('email') . '"></label>'
        . '<label class="f">Kopia do (opcjonalnie)<input class="in" name="cc" id="cc" value="' . $v('cc') . '" placeholder="adresy po przecinku"></label>'
        . '<label class="f">Telefon do przypomnień SMS (opcjonalnie)<input class="in" type="tel" name="phone" id="phone" value="' . $v('phone') . '" placeholder="np. 534 154 230"></label></div>'
        . '<label class="check"><input type="checkbox" name="save_client" value="1" checked> Zapisz lub zaktualizuj klienta na liście</label></section>'
        // pozycje
        . '<section class="card pad sec"><h2>Pozycje <small>ceny netto</small></h2><div class="items" id="items">' . $rows . '</div>'
        . '<template id="itemtpl">' . $tpl . '</template>'
        . '<div class="hrow" style="align-items:flex-start;flex-wrap:wrap"><button type="button" class="btn ghost sm" id="additem">' . icon('plus') . ' Dodaj pozycję</button>'
        . '<div class="totals" id="totals"><div><span>Razem netto</span><b data-t="net">0,00 zł</b></div><div><span>VAT</span><b data-t="vat">0,00 zł</b></div><div class="g"><span>Razem brutto</span><b data-t="gross">0,00 zł</b></div></div></div></section>'
        . '<section class="card pad sec"><h2>Uwagi na fakturze</h2><textarea class="in" name="notes" rows="3" placeholder="Opcjonalnie, np. zakres prac lub numer zamówienia">' . e((string)($f['notes'] ?? ($inv['notes'] ?? ''))) . '</textarea></section>'
        . '</div><aside class="side">'
        . '<section class="card pad sec"><h2>Daty</h2>'
        . '<label class="f">Data wystawienia<input class="in" type="date" name="issue_date" id="issue_date" value="' . e($issue) . '"></label>'
        . '<label class="f">Data sprzedaży<input class="in" type="date" name="sale_date" value="' . e((string)($f['sale_date'] ?? ($inv['sale_date'] ?? $issue))) . '"></label>'
        . '<div class="f" style="display:flex;flex-direction:column;gap:8px;font-size:13px;color:var(--mut)">Termin płatności<div class="pills" id="pills">' . $pills . '</div></div>'
        . '<input type="hidden" name="term_days" id="term_days" value="' . $term . '">'
        . '<label class="f">Płatne do<input class="in" type="date" name="due_date" id="due_date" value="' . e($due) . '"></label>'
        . '<label class="f">Miesiąc księgowy<select class="in" name="book_ym" id="book_ym">' . book_opts((string)($f['book_ym'] ?? ($inv['book_ym'] ?? substr($issue, 0, 7)))) . '</select></label>'
        . '<label class="f">Miejsce wystawienia<input class="in" name="place" value="' . e((string)($f['place'] ?? ($inv['place'] ?? setting('place')))) . '"></label></section>'
        . '<section class="card pad sec"><h2>Waluta i język</h2>'
        . '<label class="f">Waluta<select class="in" name="currency" id="currency">' . implode('', array_map(fn($k, $l) => '<option value="' . $k . '"' . ($k === (string)($f['currency'] ?? ($inv['currency'] ?? 'PLN')) ? ' selected' : '') . '>' . $l . '</option>', array_keys(currencies()), currencies())) . '</select></label>'
        . '<label class="f">Język faktury<select class="in" name="lang" id="lang"><option value="pl">Polski</option><option value="pl-en"' . ((string)($f['lang'] ?? ($inv['lang'] ?? 'pl')) === 'pl-en' ? ' selected' : '') . '>Polski i angielski (klient zagraniczny)</option></select></label>'
        . '<span class="mut" style="font-size:13px">Dla klienta spoza UE wybierz stawkę „np.”: na fakturze pojawi się adnotacja o odwrotnym obciążeniu.</span>'
        . '<label class="check"><input type="checkbox" name="recurring" value="1"' . ((int)($f['recurring'] ?? ($inv['recurring'] ?? 0)) ? ' checked' : '') . '> Faktura cykliczna: co miesiąc przygotuj szkic kolejnej</label></section>'
        . '<section class="card pad sec"><h2>Przypomnienia</h2><label class="check"><input type="checkbox" name="reminders" value="1"' . ((int)($f['reminders'] ?? ($inv['reminders'] ?? 1)) ? ' checked' : '') . '> Wyślij przypomnienia i wezwania automatycznie</label>'
        . '<span class="mut" style="font-size:13px">Według harmonogramu z ustawień. Zatrzymają się same, gdy oznaczysz wpłatę.</span></section>'
        . '<section class="card pad sec"><h2>Numer</h2><span>' . ($num ? e($num) : 'Nadamy przy wystawieniu, kolejny: <b style="font-weight:500">' . e(next_number(substr($issue, 0, 7))[1]) . '</b>') . '</span></section>'
        . '</aside></div>'
        . '<div class="bar"><span class="note">Wyślemy z ' . e(setting('mail_from')) . ', z PDF w załączniku i linkiem do faktury online.</span>'
        . '<button class="btn ghost" name="act" value="draft">Zapisz szkic</button>'
        . '<button class="btn ghost" name="act" value="issue">' . ($num ? 'Zapisz' : 'Wystaw bez wysyłki') . '</button>'
        . '<button class="btn" name="act" value="send">' . ($num ? 'Zapisz i wyślij' : 'Wystaw i wyślij') . ' ' . icon('arrow') . '</button></div>'
        . '</form>';
    page($inv ? ($num ? 'Faktura ' . $num : 'Szkic faktury') : 'Nowa faktura', $inv ? 'list' : 'new', $body, $inv ? 'Edycja' : 'Wystaw w minutę');
}

function book_opts(string $sel): string {
    $o = ''; $base = strtotime(date('Y-m-01'));
    $list = [];
    for ($i = -6; $i <= 2; $i++) $list[] = date('Y-m', strtotime("$i months", $base));
    if (!in_array($sel, $list, true)) $list[] = $sel;
    sort($list);
    foreach ($list as $ym) { [$y, $m] = explode('-', $ym); $o .= '<option value="' . $ym . '"' . ($ym === $sel ? ' selected' : '') . '>' . pl_month((int)$m) . ' ' . $y . '</option>'; }
    return $o;
}

function item_row(string $n, array $it, callable $vat): string {
    $v = fn($k) => e((string)($it[$k] ?? ''));
    return '<div class="item" data-item>'
        . '<label class="f nm">Nazwa usługi<input class="in" name="items[' . $n . '][name]" value="' . $v('name') . '" placeholder="np. Projekt UI/UX aplikacji"></label>'
        . '<label class="f">Ilość<input class="in" name="items[' . $n . '][qty]" value="' . $v('qty') . '" inputmode="decimal" data-q></label>'
        . '<label class="f">Jedn.<input class="in" name="items[' . $n . '][unit]" value="' . $v('unit') . '"></label>'
        . '<label class="f">Cena netto<input class="in" name="items[' . $n . '][price]" value="' . $v('price') . '" inputmode="decimal" placeholder="0,00" data-p></label>'
        . '<label class="f">VAT<select class="in" name="items[' . $n . '][vat]" data-v>' . $vat($it['vat'] ?? '23') . '</select></label>'
        . '<button type="button" class="del" data-del aria-label="Usuń pozycję">×</button>'
        . '<label class="f full">Opis (opcjonalnie)<textarea class="in" name="items[' . $n . '][desc]" rows="2" style="min-height:64px">' . $v('desc') . '</textarea></label>'
        . '<span class="sum" data-s></span></div>';
}

// ---------- podgląd faktury ----------
function view_invoice(array $inv): never {
    $id = (int)$inv['id']; $st = inv_state($inv);
    $ev = db()->prepare('SELECT * FROM events WHERE invoice_id = ? ORDER BY at DESC, id DESC'); $ev->execute([$id]);
    $hist = '';
    foreach ($ev->fetchAll() as $e) $hist .= '<div class="it"><span class="d">' . date('d.m H:i', strtotime($e['at'])) . '</span><span' . ($e['kind'] === 'error' ? ' class="late-t"' : '') . '>' . e($e['msg']) . '</span></div>';
    $f = fn($action, $label, $cls = 'btn ghost', $extra = '') => '<form method="post" action="' . P . '?a=' . $action . '">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '">' . $extra . '<button class="' . $cls . '">' . $label . '</button></form>';
    $acts = '';
    if ($inv['status'] === 'draft') {
        $acts .= '<a class="btn" href="' . P . '?a=edit&id=' . $id . '">Dokończ i wystaw ' . icon('arrow') . '</a>';
    } elseif ($inv['status'] === 'issued') {
        $noMail = !filter_var(trim((string)($inv['buyer']['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $mb = fn($act, $label, $cls) => $noMail ? '<button type="button" class="' . $cls . '" data-mail="' . $act . '">' . $label . '</button>' : $f($act, $label, $cls);
        $acts .= $mb('send', empty($inv['sent_at']) ? 'Wyślij do klienta' : 'Wyślij ponownie', empty($inv['sent_at']) ? 'btn' : 'btn ghost');
        if (!empty($inv['sent_at'])) $acts .= $mb('remind', 'Przypomnij teraz', 'btn ghost');
        if ($noMail) $acts .= '<dialog class="mdlg" id="maildlg"><form method="post" action="' . P . '?a=set_email" class="sec">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="next" value="send">'
            . '<div class="hrow"><h2>Na jaki adres wysłać?</h2><button type="button" class="x" data-close aria-label="Zamknij">×</button></div>'
            . '<p class="mut" style="margin:0;font-size:14px">Przy tej fakturze nie ma e-maila klienta. Wpisz go, a od razu wyślemy wiadomość.</p>'
            . '<label class="f">E-mail klienta<input class="in" type="email" name="email" required autocomplete="email"></label>'
            . ($inv['client_id'] ? '<label class="check"><input type="checkbox" name="save_client" value="1" checked> Zapisz ten adres u klienta na przyszłość</label>' : '')
            . '<button class="btn">Zapisz i wyślij ' . icon('arrow') . '</button></form></dialog>';
        $acts .= '<details class="more"><summary>Oznacz jako opłaconą</summary><form class="inl" method="post" action="' . P . '?a=paid">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '">'
            . '<label class="f">Data wpłaty<input class="in" type="date" name="paid_date" value="' . today() . '"></label>'
            . '<label class="f">Kwota<input class="in" name="paid_amount" value="' . money((int)$inv['gross'], false) . '" inputmode="decimal"></label><button class="btn">Zapisz wpłatę</button></form></details>';
    }
    if (in_array($inv['status'], ['issued', 'paid'], true)) {
        $kinds = ['manual' => 'Przypomnienie (jakie pójdzie dziś)', 'new' => 'Mail z fakturą', 'w1' => 'Wezwanie do zapłaty', 'w2' => 'Ostateczne wezwanie'];
        $acts .= '<details class="more"><summary>Wyślij podgląd do mnie</summary><form class="inl" method="post" action="' . P . '?a=preview_mail">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '">'
            . '<label class="f">Który mail<select class="in" name="kind">' . implode('', array_map(fn($k, $l) => '<option value="' . $k . '">' . $l . '</option>', array_keys($kinds), $kinds)) . '</select></label>'
            . '<label class="f">Na adres<input class="in" type="email" name="to" value="' . e(setting('imap_user') ?: setting('mail_reply')) . '"></label>'
            . '<button class="btn ghost sm">Wyślij podgląd</button><span class="mut" style="font-size:13px">Klient nic nie dostanie. W temacie będzie dopisek [Podgląd].</span></form></details>';
    }
    if ($inv['status'] !== 'draft') {
        $acts .= '<a class="btn ghost" href="' . P . '?a=pdf&id=' . $id . '&dl=1">Pobierz PDF</a>';
        $acts .= '<button type="button" class="btn ghost" data-copy="' . e(pay_url($inv)) . '">Kopiuj link dla klienta</button>';
    }
    if (can_edit($inv) && $inv['status'] !== 'draft') $acts .= '<a class="btn ghost" href="' . P . '?a=edit&id=' . $id . '">Edytuj</a>';
    if ($inv['status'] === 'paid') $acts .= $f('unpaid', 'Cofnij oznaczenie wpłaty');
    $kv = '<div class="kv">'
        . '<div><span>Status</span>' . state_chip($inv) . '</div>'
        . '<div><span>Do zapłaty</span><b style="font-weight:500">' . money((int)$inv['gross'], $inv['currency'] ?? 'PLN') . '</b></div>'
        . '<div><span>Termin</span><span>' . e(pl_date($inv['due_date'])) . '</span></div>'
        . ($inv['sent_at'] ? '<div><span>Wysłana</span><span>' . e(date('d.m.Y H:i', strtotime($inv['sent_at']))) . '</span></div>' : '')
        . (!empty($inv['opened_at']) ? '<div><span>Mail otwarty</span><span>' . e(date('d.m.Y H:i', strtotime($inv['opened_at']))) . '</span></div>' : '')
        . ($inv['viewed_at'] ? '<div><span>Faktura online</span><span>' . e(date('d.m.Y H:i', strtotime($inv['viewed_at']))) . '</span></div>' : '')
        . ($inv['paid_at'] ? '<div><span>Wpłata</span><span class="ok-t">' . e(pl_date($inv['paid_at'])) . ', ' . money((int)$inv['paid_amount'], $inv['currency'] ?? 'PLN') . '</span></div>' : '')
        . '</div>';
    $decl = '';
    if (!empty($inv['declared_at']) && $inv['status'] === 'issued') {
        $decl = '<div class="card pad sec" style="border-color:#C9D3FB"><h2>Klient zgłosił wpłatę</h2><span class="mut" style="font-size:14px">' . e(date('d.m.Y H:i', strtotime($inv['declared_at']))) . '. Przypomnienia wstrzymane. Gdy pieniądze dotrą, potwierdź wpłatę.</span>'
            . $f('paid', 'Potwierdź wpłatę', 'btn', '<input type="hidden" name="paid_date" value="' . today() . '">') . $f('undeclare', 'Wpłata nie dotarła') . '</div>';
    }
    $auto = '';
    if ($inv['status'] === 'issued') {
        $sent = reminders_sent($id); $pl = '';
        foreach (reminder_plan($inv) as $k => $d) {
            $done = in_array($k, $sent, true);
            $pl .= '<div class="it"><span class="d">' . date('d.m', strtotime($d)) . '</span><div><b' . ($done ? ' class="mut"' : '') . '>' . e(reminder_label($k)) . '</b><small>' . ($done ? 'załatwione' : ($inv['reminders'] ? 'wyślemy rano' : 'wyłączone')) . '</small></div></div>';
        }
        $auto = '<div class="card pad sec"><h2>Przypomnienia</h2><div class="sched">' . $pl . '</div>'
            . $f('auto', $inv['reminders'] ? 'Wyłącz dla tej faktury' : 'Włącz dla tej faktury', 'link', $inv['reminders'] ? '' : '<input type="hidden" name="on" value="1">')
            . (empty($inv['sent_at']) ? '<span class="mut" style="font-size:13px">Ruszą po wysłaniu faktury do klienta.</span>' : '') . '</div>';
    }
    $ksef = '';
    if ($inv['status'] !== 'draft') {
        $ks = (string)($inv['ksef_state'] ?? '');
        $kbody = '';
        if (!empty($inv['ksef'])) $kbody .= '<div class="kv"><div><span>Status</span><span class="chip paid">Przyjęta</span></div><div><span>Numer KSeF</span></div><div><b style="font-weight:500;word-break:break-all">' . e((string)$inv['ksef']) . '</b></div></div>';
        elseif ($ks === 'pending') $kbody .= '<div class="kv"><div><span>Status</span><span class="chip today">Przetwarzanie</span></div></div>' . $f('ksef_poll', 'Sprawdź numer KSeF', 'btn ghost sm');
        elseif ($ks === 'rejected' || $ks === 'error') $kbody .= '<div class="kv"><div><span>Status</span><span class="chip late">' . ($ks === 'rejected' ? 'Odrzucona' : 'Błąd wysyłki') . '</span></div></div><span class="late-t" style="font-size:13px">' . e((string)$inv['ksef_error']) . '</span>';
        if (empty($inv['ksef']) && $ks !== 'pending' && in_array($inv['status'], ['issued', 'paid'], true)) {
            if (ksef_enabled()) $kbody .= $f('ksef_send', $ks === '' ? 'Wyślij do KSeF' : 'Wyślij do KSeF ponownie', 'btn' . ($ks === '' && empty($inv['source']) ? '' : ' ghost') . ' sm')
                . (!empty($inv['source']) ? '<span class="mut" style="font-size:13px">Faktura zaimportowana. Wyślij ją tylko wtedy, gdy nie trafiła do KSeF z innego programu.</span>' : '');
            else $kbody .= '<span class="mut" style="font-size:13px">Wpisz token KSeF w <a href="' . P . '?a=settings#ksef">ustawieniach</a>, a faktury będą trafiać do KSeF przy wystawieniu.</span>';
            $kbody .= '<details class="more"><summary>Mam już numer KSeF</summary><form class="inl" method="post" action="' . P . '?a=ksef">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><label class="f">Numer KSeF faktury<input class="in" name="ksef" value="' . e((string)$inv['ksef']) . '"></label><button class="btn ghost sm">Zapisz</button></form></details>';
        }
        $kbody .= '<a class="link" href="' . P . '?a=ksef_xml&id=' . $id . '" style="font-size:11px">Pobierz XML (FA3)</a>';
        $ksef = '<div class="card pad sec" id="ksef"><h2>KSeF</h2>' . $kbody . '</div>';
    }
    $danger = '';
    if ($inv['status'] === 'draft') $danger = $f('delete', 'Usuń szkic', 'link', '');
    elseif (can_delete($inv)) $danger = '<details class="more"><summary>Usuń fakturę</summary><span class="mut" style="font-size:13px;display:block;margin-bottom:10px">To ostatni numer w miesiącu, więc możesz go usunąć bez dziury w numeracji, np. po fakturze testowej. Jeśli klient już ją dostał, lepiej wystaw korektę.</span>' . $f('delete', 'Usuń fakturę ' . $inv['number'], 'btn danger') . '</details>';
    if ($inv['status'] === 'issued') $danger .= '<details class="more"><summary>Anuluj fakturę</summary><span class="mut" style="font-size:13px;display:block;margin-bottom:10px">Faktura zostaje w historii z numerem, ale przestaje być do zapłaty. Wysłanej fakturę lepiej skorygować.</span>' . $f('cancel', 'Anuluj fakturę', 'btn danger') . '</details>';
    $body = '<div class="cols"><div class="wide"><div class="doc-wrap">' . invoice_doc($inv, P . '/assets/') . '</div></div>'
        . '<aside class="side">' . $decl . '<div class="card pad sec">' . $kv . '<div class="acts-col">' . $acts . '</div>' . $danger . '</div>' . $auto . $ksef
        . '<div class="card pad sec"><h2>Historia</h2><div class="hist">' . ($hist ?: '<span class="mut">Brak zdarzeń.</span>') . '</div></div></aside></div>';
    page($inv['number'] ? 'Faktura ' . $inv['number'] : 'Szkic faktury', 'list', $body, $inv['buyer']['name'] ?? '', '<a class="btn ghost" href="' . P . '">Wszystkie faktury</a>');
}

// ---------- klienci ----------
function view_clients(): never {
    $rows = db()->query('SELECT c.*, COUNT(i.id) AS n FROM clients c LEFT JOIN invoices i ON i.client_id = c.id GROUP BY c.id ORDER BY c.name')->fetchAll();
    $sums = [];
    foreach (db()->query("SELECT client_id, currency, status, SUM(gross) AS g FROM invoices WHERE status IN ('paid', 'issued') GROUP BY client_id, currency, status") as $r)
        $sums[(int)$r['client_id']][$r['status']][$r['currency']] = (int)$r['g'];
    $sumtxt = function (int $cid, string $st) use ($sums): string {
        $a = $sums[$cid][$st] ?? []; if (!$a) return money(0);
        return implode('<br>', array_map(fn($k, $v) => money($v, $k), array_keys($a), $a));
    };
    $edit = (int)($_GET['id'] ?? 0); $ec = ['id' => 0, 'nip' => '', 'name' => '', 'addr1' => '', 'addr2' => '', 'email' => '', 'cc' => '', 'phone' => ''];
    $tr = '';
    foreach ($rows as $c) {
        if ($c['id'] == $edit) $ec = $c;
        $tr .= '<tr class="row"><td class="c-cli"><span style="font-weight:500">' . e($c['name']) . '</span><span class="sub">' . e(trim($c['nip'] . ' · ' . $c['email'], ' ·')) . '</span></td>'
            . '<td class="c-due nw">' . (int)$c['n'] . ' ' . plural((int)$c['n'], 'faktura', 'faktury', 'faktur') . '</td>'
            . '<td class="c-amt r amt">' . $sumtxt((int)$c['id'], 'issued') . '<span class="sub">do zapłaty</span></td>'
            . '<td class="c-st r amt">' . $sumtxt((int)$c['id'], 'paid') . '<span class="sub">opłacone</span></td>'
            . '<td class="c-act r nw"><a class="link" href="' . P . '?a=new&client=' . $c['id'] . '">Faktura</a> &nbsp; <a class="link" href="' . P . '?a=clients&id=' . $c['id'] . '#edit">Edytuj</a></td></tr>';
    }
    $v = fn($k) => e((string)$ec[$k]);
    $form = '<section class="card pad sec side" id="edit"><h2>' . ($ec['id'] ? 'Edytuj klienta' : 'Nowy klient') . '</h2><form class="inl" method="post" action="' . P . '?a=client_save">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$ec['id'] . '">'
        . '<label class="f">NIP<div class="nipw"><input class="in" name="nip" id="nip" value="' . $v('nip') . '"><button type="button" class="btn ghost sm" id="nipbtn" style="height:46px">Pobierz</button></div><span id="nipmsg" class="mut" style="font-size:13px"></span></label>'
        . '<label class="f">Nazwa firmy<input class="in" name="name" id="name" value="' . $v('name') . '" required></label>'
        . '<label class="f">Ulica i numer<input class="in" name="addr1" id="addr1" value="' . $v('addr1') . '"></label>'
        . '<label class="f">Kod pocztowy i miasto<input class="in" name="addr2" id="addr2" value="' . $v('addr2') . '"></label>'
        . '<label class="f">E-mail do faktur<input class="in" type="email" name="email" value="' . $v('email') . '"></label>'
        . '<label class="f">Kopia do<input class="in" name="cc" value="' . $v('cc') . '"></label>'
        . '<label class="f">Telefon do SMS<input class="in" type="tel" name="phone" value="' . $v('phone') . '"></label>'
        . '<label class="check"><input type="checkbox" name="no_remind" value="1"' . (!empty($ec['no_remind']) ? ' checked' : '') . '> Nie wysyłaj temu klientowi przypomnień ani wezwań</label>'
        . '<label class="check"><input type="checkbox" name="auto_paid" value="1"' . (!empty($ec['auto_paid']) ? ' checked' : '') . '> Płaci sam ostatniego dnia miesiąca: oznaczaj faktury jako opłacone automatycznie</label>'
        . '<button class="btn">Zapisz klienta</button></form>'
        . ($ec['id'] ? '<form method="post" action="' . P . '?a=client_delete">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$ec['id'] . '"><button class="link" style="color:var(--red)">Usuń klienta</button></form><a class="link" href="' . P . '?a=clients">Anuluj</a>' : '') . '</section>';
    $list = '<section class="card wide">' . ($tr ? '<div class="tw"><table class="list"><thead><tr><th>Klient</th><th>Faktury</th><th class="r">Do zapłaty</th><th class="r">Opłacone</th><th><span class="sr">Akcje</span></th></tr></thead><tbody>' . $tr . '</tbody></table></div>' : '<div class="empty">Klienci zapiszą się sami przy pierwszej fakturze. Możesz też dodać ich tutaj.</div>') . '</section>';
    page('Klienci', 'clients', '<div class="cols">' . $list . $form . '</div>', count($rows) . ' ' . plural(count($rows), 'klient', 'klientów', 'klientów'), '<a class="btn" href="' . P . '?a=new">Nowa faktura ' . icon('plus') . '</a>');
}

// ---------- ustawienia ----------
function view_settings(): never {
    $v = fn($k) => e(setting($k));
    $ch = fn($k) => setting($k) === '1' ? ' checked' : '';
    $in = fn($k, $l, $t = 'text', $x = '') => '<label class="f">' . $l . '<input class="in" type="' . $t . '" name="' . $k . '" value="' . $v($k) . '"' . $x . '></label>';
    [, $nextNum] = next_number(date('Y-m'));
    $last = (int)setting('last_cron');
    $body = '<form class="sec" method="post" action="' . P . '?a=settings_save">' . csrf_field() . '<input type="hidden" name="_main" value="1">'
        . '<div class="cols"><div class="wide sec">'
        . '<section class="card pad sec"><h2>Sprzedawca</h2><div class="grid2">' . $in('seller_name', 'Nazwa') . $in('seller_nip', 'NIP') . $in('seller_addr1', 'Ulica i numer') . $in('seller_addr2', 'Kod pocztowy i miasto') . $in('seller_email', 'E-mail na fakturze', 'email') . $in('seller_phone', 'Telefon') . '</div></section>'
        . '<section class="card pad sec"><h2>Konto bankowe</h2><div class="grid2">' . $in('bank_account', 'Numer konta (26 cyfr)', 'text', ' inputmode="numeric" placeholder="00 0000 0000 0000 0000 0000 0000"') . $in('bank_name', 'Nazwa banku') . $in('bank_swift', 'SWIFT / BIC (dla klientów zagranicznych)') . '</div>'
        . (nrb_digits(setting('bank_account')) === '' ? '<span class="late-t" style="font-size:14px">Uzupełnij numer konta, zanim wyślesz pierwszą fakturę. Pojawi się na fakturze, w mailach i na stronie płatności.</span>' : '') . '</section>'
        . '<section class="card pad sec"><h2>Faktury</h2><div class="grid3">' . $in('place', 'Miejsce wystawienia') . $in('term_days', 'Domyślny termin (dni)', 'number', ' min="0" max="120"')
        . '<label class="f">Następny numer w tym miesiącu<input class="in" type="number" name="next_seq" min="1" placeholder="' . e(explode('/', $nextNum)[0]) . '"></label></div>'
        . '<span class="mut" style="font-size:13px">Numeracja: kolejny numer / miesiąc / rok, np. ' . e($nextNum) . '. Zmień tylko, gdy w tym miesiącu wystawiłeś już faktury poza panelem.</span>'
        . $in('footer_note', 'Podziękowanie na dole faktury') . '</section>'
        . '<section class="card pad sec" id="auto"><h2>Przypomnienia i wezwania</h2>'
        . '<label class="check"><input type="checkbox" name="r_before_on" value="1"' . $ch('r_before_on') . '> Przypomnienie <input class="in" type="number" name="r_before_days" value="' . $v('r_before_days') . '" min="1" max="14" style="width:72px;height:34px;padding:0 8px"> dni przed terminem</label>'
        . '<label class="check"><input type="checkbox" name="r_due_on" value="1"' . $ch('r_due_on') . '> W dniu terminu: „Dziś mija termin płatności”</label>'
        . '<label class="check"><input type="checkbox" name="r_w1_on" value="1"' . $ch('r_w1_on') . '> Wezwanie do zapłaty <input class="in" type="number" name="r_w1_days" value="' . $v('r_w1_days') . '" min="1" max="30" style="width:72px;height:34px;padding:0 8px"> dni po terminie, z 7 dniami na wpłatę</label>'
        . '<label class="check"><input type="checkbox" name="r_w2_on" value="1"' . $ch('r_w2_on') . '> Ostateczne wezwanie <input class="in" type="number" name="r_w2_days" value="' . $v('r_w2_days') . '" min="2" max="60" style="width:72px;height:34px;padding:0 8px"> dni po terminie, z odsetkami i rekompensatą</label>'
        . '<div class="grid3">' . $in('interest_rate', 'Odsetki w transakcjach handlowych (% rocznie)') . '</div>'
        . '<span class="mut" style="font-size:13px">Od 1 lipca 2026 odsetki ustawowe za opóźnienie w transakcjach handlowych wynoszą 13,75% rocznie. Rekompensata: 40, 70 lub 100 euro zależnie od kwoty faktury. Wiadomości idą w dni robocze rano, a gdy klient zgłosi wpłatę albo oznaczysz fakturę jako opłaconą, przestają same.</span>'
        . '<span class="mut" style="font-size:13px">Ostatnie sprawdzenie: ' . ($last ? date('d.m.Y H:i', $last) : 'jeszcze nie było') . '</span></section>'
        . '</div><aside class="side">'
        . '<section class="card pad sec"><h2>Wysyłka maili</h2>' . $in('mail_from', 'Nadawca', 'email') . $in('mail_reply', 'Odpowiedzi i kopie na', 'email')
        . '<label class="check"><input type="checkbox" name="mail_copy" value="1"' . $ch('mail_copy') . '> Wysyłaj mi ukrytą kopię każdego maila</label>'
        . '<label class="check"><input type="checkbox" name="notify_open" value="1"' . $ch('notify_open') . '> Daj mi znać mailem, gdy klient otworzy maila lub fakturę online</label></section>'
        . '<button class="btn">Zapisz ustawienia</button></aside></div></form>'
        . '<section class="card pad sec" id="poczta"><h2>Poczta faktury@</h2>'
        . '<span class="mut" style="font-size:14px">Skrzynka, z której wychodzą faktury, przypomnienia i wezwania. Odpowiedzi klientów zobaczysz w zakładce Poczta. Hasło zapisujemy tylko w bazie panelu na serwerze.</span>'
        . '<form class="sec" method="post" action="' . P . '?a=mail_settings">' . csrf_field()
        . '<div class="grid2"><label class="f">Adres skrzynki<input class="in" type="email" name="imap_user" value="' . e(setting('imap_user')) . '"></label>'
        . '<label class="f">Hasło' . (setting('imap_pass') !== '' ? ' (zapisane, wpisz nowe, aby zmienić)' : '') . '<input class="in" type="password" name="imap_pass" autocomplete="new-password" placeholder="' . (setting('imap_pass') !== '' ? '••••••••••••' : 'hasło do skrzynki') . '"></label></div>'
        . '<details class="more"><summary>Serwer poczty</summary><div class="grid2"><label class="f">IMAP (odbieranie)<input class="in" name="imap_host" value="' . e(setting('imap_host')) . '"></label><label class="f">Port IMAP (SSL)<input class="in" name="imap_port" value="' . e(setting('imap_port')) . '"></label>'
        . '<label class="f">SMTP (wysyłka)<input class="in" name="smtp_host" value="' . e(setting('smtp_host')) . '"></label><label class="f">Port SMTP (SSL)<input class="in" name="smtp_port" value="' . e(setting('smtp_port')) . '"></label></div></details>'
        . '<label class="check"><input type="checkbox" name="smtp_on" value="1"' . (setting('smtp_on') === '1' ? ' checked' : '') . '> Wysyłaj faktury i przypomnienia z tej skrzynki (kopie trafią do folderu Wysłane)</label>'
        . '<div class="hrow" style="justify-content:flex-start;flex-wrap:wrap"><button class="btn ghost">Zapisz pocztę</button>' . (setting('imap_pass') !== '' ? '<label class="check" style="font-size:13px"><input type="checkbox" name="imap_pass_clear" value="1"> Usuń zapisane hasło</label>' : '') . '</div></form>'
        . (setting('imap_pass') !== '' ? '<form method="post" action="' . P . '?a=mail_check">' . csrf_field() . '<button class="link">Sprawdź połączenie ze skrzynką</button></form>' : '') . '</section>'
        . '<section class="card pad sec" id="sms"><h2>Przypomnienia SMS</h2>'
        . '<span class="mut" style="font-size:14px">SMS-y wysyłamy przez SMSAPI.pl. Załóż tam konto, w sekcji tokenów OAuth wygeneruj token z uprawnieniem do wysyłki SMS i wklej go tutaj. SMS idzie tylko do klientów z wpisanym telefonem.</span>'
        . '<form class="sec" method="post" action="' . P . '?a=sms_settings">' . csrf_field()
        . '<div class="grid2"><label class="f">Token SMSAPI' . (setting('sms_token') !== '' ? ' (zapisany, wpisz nowy, aby zmienić)' : '') . '<input class="in" type="password" name="sms_token" autocomplete="off" placeholder="' . (setting('sms_token') !== '' ? '••••••••••••' : 'wklej token') . '"></label>'
        . '<label class="f">Nazwa nadawcy (zarejestrowana w SMSAPI, puste = Test)<input class="in" name="sms_from" maxlength="11" value="' . e(setting('sms_from')) . '" placeholder="np. CreativeVis"></label></div>'
        . '<label class="check"><input type="checkbox" name="sms_on" value="1"' . (setting('sms_on') === '1' ? ' checked' : '') . '> Wysyłaj przypomnienia SMS</label>'
        . '<div class="grid2"><label class="check"><input type="checkbox" name="sms_before" value="1"' . (setting('sms_before') === '1' ? ' checked' : '') . '> Przed terminem</label><label class="check"><input type="checkbox" name="sms_due" value="1"' . (setting('sms_due') === '1' ? ' checked' : '') . '> W dniu terminu</label>'
        . '<label class="check"><input type="checkbox" name="sms_w1" value="1"' . (setting('sms_w1') === '1' ? ' checked' : '') . '> Wezwanie do zapłaty</label><label class="check"><input type="checkbox" name="sms_w2" value="1"' . (setting('sms_w2') === '1' ? ' checked' : '') . '> Ostateczne wezwanie</label>'
        . '<label class="check"><input type="checkbox" name="sms_manual" value="1"' . (setting('sms_manual') === '1' ? ' checked' : '') . '> Przy „Przypomnij teraz”</label></div>'
        . '<input type="hidden" name="sms_api" value="' . e(setting('sms_api')) . '">'
        . '<div class="hrow" style="justify-content:flex-start;flex-wrap:wrap"><button class="btn ghost">Zapisz SMS</button>' . (setting('sms_token') !== '' ? '<label class="check" style="font-size:13px"><input type="checkbox" name="sms_token_clear" value="1"> Usuń zapisany token</label>' : '') . '</div></form>'
        . (setting('sms_token') !== '' ? '<form class="hrow" method="post" action="' . P . '?a=sms_test" style="justify-content:flex-start;flex-wrap:wrap">' . csrf_field() . '<input class="in" type="tel" name="to" value="534154230" style="max-width:220px"><button class="btn ghost sm">Wyślij testowy SMS</button></form>' : '') . '</section>'
        . '<section class="card pad sec" id="ksef"><h2>KSeF</h2>'
        . '<span class="mut" style="font-size:14px">Panel łączy się z KSeF tokenem z Aplikacji Podatnika (uprawnienia: wystawianie i przeglądanie faktur). Token zapisujemy tylko w bazie panelu na serwerze.</span>'
        . '<form class="sec" method="post" action="' . P . '?a=settings_save">' . csrf_field()
        . '<div class="grid2"><label class="f">Środowisko<select class="in" name="ksef_env">' . implode('', array_map(fn($k) => '<option value="' . $k . '"' . (setting('ksef_env') === $k ? ' selected' : '') . '>' . ucfirst(KSEF_ENV[$k]['name']) . '</option>', array_keys(KSEF_ENV))) . '</select></label>'
        . '<label class="f">Token KSeF' . (setting('ksef_token') !== '' ? ' (zapisany, wpisz nowy, aby zmienić)' : '') . '<input class="in" type="password" name="ksef_token" autocomplete="off" placeholder="' . (setting('ksef_token') !== '' ? '••••••••••••' : 'wklej token') . '"></label></div>'
        . '<label class="check"><input type="checkbox" name="ksef_auto" value="1"' . (setting('ksef_auto') === '1' ? ' checked' : '') . '> Wysyłaj do KSeF automatycznie przy wystawieniu faktury</label>'
        . '<label class="f">Podstawa zwolnienia dla stawki „zw.” (jeśli używasz)<input class="in" name="zw_basis" value="' . e(setting('zw_basis')) . '" placeholder="np. art. 113 ust. 1 ustawy o VAT"></label>'
        . '<input type="hidden" name="_ksef" value="1">'
        . '<div class="hrow" style="justify-content:flex-start;flex-wrap:wrap"><button class="btn ghost">Zapisz KSeF</button>' . (setting('ksef_token') !== '' ? '<label class="check" style="font-size:13px"><input type="checkbox" name="ksef_token_clear" value="1"> Usuń zapisany token</label>' : '') . '</div></form>'
        . (setting('ksef_token') !== '' ? '<form method="post" action="' . P . '?a=ksef_check">' . csrf_field() . '<button class="link">Sprawdź połączenie z KSeF</button></form>' : '') . '</section>'
        . '<section class="card pad sec" id="import"><h2>Import faktur</h2><span class="mut" style="font-size:14px">Wgraj plik z fakturami wystawionymi wcześniej (przygotowuję go z PDF-ów). Numery zostają bez zmian, a faktury, które już są w panelu, zostaną pominięte.</span>'
        . '<form class="hrow" method="post" action="' . P . '?a=import" enctype="multipart/form-data" style="justify-content:flex-start;flex-wrap:wrap">' . csrf_field() . '<input class="in" type="file" name="file" accept=".json,application/json" required style="max-width:360px;padding-top:10px"><button class="btn ghost">Importuj</button></form></section>'
        . '<div class="cols"><section class="card pad sec wide"><h2>Hasło</h2><form class="grid3" method="post" action="' . P . '?a=password" style="align-items:end">' . csrf_field()
        . '<label class="f">Obecne hasło<input class="in" type="password" name="old" autocomplete="current-password" required></label><label class="f">Nowe hasło<input class="in" type="password" name="new" minlength="10" autocomplete="new-password" required></label><button class="btn ghost">Zmień hasło</button></form></section>'
        . '<section class="card pad sec side"><h2>Test wysyłki</h2><span class="mut" style="font-size:14px">Wyśle krótką wiadomość na adres z pola „Odpowiedzi i kopie na”.</span><form method="post" action="' . P . '?a=testmail">' . csrf_field() . '<button class="btn ghost">Wyślij test</button></form>'
        . '<form method="post" action="' . P . '?a=run">' . csrf_field() . '<button class="link">Sprawdź przypomnienia teraz</button></form></section></div>';
    page('Ustawienia', 'settings', $body, 'Panel faktur');
}


// ---------- poczta ----------
function smtp_check(): void {
    $host = setting('smtp_host'); $port = (int)setting('smtp_port');
    $ctx = stream_context_create(['ssl' => ['verify_peer' => getenv('CV_PANEL_DEV') !== '1', 'verify_peer_name' => getenv('CV_PANEL_DEV') !== '1', 'peer_name' => $host]]);
    $sk = @stream_socket_client('ssl://' . $host . ':' . $port, $en, $es, 10, STREAM_CLIENT_CONNECT, $ctx);
    if (!$sk) throw new RuntimeException('brak połączenia z ' . $host . ':' . $port);
    stream_set_timeout($sk, 15);
    $rd = function () use ($sk) { $c = 0; while (($l = fgets($sk)) !== false) { $c = (int)substr($l, 0, 3); if (isset($l[3]) && $l[3] === ' ') break; } return $c; };
    $rd(); fwrite($sk, "EHLO creative-vision.pl\r\n"); $rd();
    fwrite($sk, "AUTH LOGIN\r\n"); $rd(); fwrite($sk, base64_encode(setting('imap_user')) . "\r\n"); $rd(); fwrite($sk, base64_encode(setting('imap_pass')) . "\r\n");
    $c = $rd(); fwrite($sk, "QUIT\r\n"); fclose($sk);
    if ($c !== 235) throw new RuntimeException('serwer nie przyjął loginu lub hasła');
}
function m_quote(string $q): string {
    return '<tr><td class="p" style="padding:26px 48px 0 48px"><div style="border-left:3px solid #E6E4DF;padding:4px 0 4px 16px;' . MF . 'font-size:14px;line-height:22px;color:#6B6B6B">' . nl2br(e(mb_substr($q, 0, 4000))) . '</div></td></tr>';
}
function mail_boxes(): array { return ['INBOX' => 'Odebrane', 'sent' => 'Wysłane']; }
function real_box(Imap $im, string $box): string { return $box === 'sent' ? sent_box($im) : 'INBOX'; }
function inv_links(string $txt): string {
    if (!preg_match_all('#\b(\d{1,4}/\d{1,2}/20\d{2})\b#', $txt, $m)) return '';
    $o = ''; $st = db()->prepare('SELECT id, number FROM invoices WHERE number = ?');
    foreach (array_unique($m[1]) as $n) { $st->execute([$n]); if ($r = $st->fetch()) $o .= '<a class="chip due" style="text-decoration:none" href="' . P . '?a=view&id=' . $r['id'] . '">Faktura ' . e($r['number']) . '</a> '; }
    return $o;
}
function inbox_off(): never {
    page('Poczta', 'inbox', '<section class="card pad sec"><h2>Podłącz skrzynkę faktury@creative-vision.pl</h2><p class="mut" style="margin:0">Wpisz hasło do skrzynki w ustawieniach. Zobaczysz tu odpowiedzi klientów, a maile z fakturami będą wychodzić z tego adresu.</p><a class="btn" style="align-self:flex-start" href="' . P . '?a=settings#poczta">Przejdź do ustawień</a></section>', 'faktury@creative-vision.pl');
}
function view_inbox(): never {
    if (!inbox_enabled()) inbox_off();
    $box = (string)($_GET['box'] ?? 'INBOX'); if (!isset(mail_boxes()[$box])) $box = 'INBOX';
    $pg = max(1, (int)($_GET['p'] ?? 1)); $per = 30;
    try {
        $im = imap_open_box(); $im->select(real_box($im, $box));
        $uids = $im->uids('ALL'); rsort($uids);
        $total = count($uids);
        $hs = $im->headers(array_slice($uids, ($pg - 1) * $per, $per));
        if ($box === 'INBOX') set_setting('imap_unseen', (string)count($im->uids('UNSEEN')));
        unset($im);
    } catch (Throwable $e) {
        page('Poczta', 'inbox', '<div class="flash err">' . e($e->getMessage()) . '</div><a class="btn ghost" style="align-self:flex-start" href="' . P . '?a=settings#poczta">Ustawienia poczty</a>', 'faktury@creative-vision.pl');
    }
    krsort($hs);
    $tr = '';
    foreach ($hs as $uid => $m) {
        $h = $m['h']; $unseen = stripos($m['flags'], '\\Seen') === false;
        [$nm, $ad] = mail_addr((string)($box === 'INBOX' ? ($h['from'] ?? '') : ($h['to'] ?? '')));
        $subj = mime_hdr_decode((string)($h['subject'] ?? '(bez tematu)'));
        $ts = strtotime((string)($h['date'] ?? '')) ?: 0;
        $when = $ts ? (date('Y-m-d', $ts) === today() ? date('H:i', $ts) : date('d.m.Y', $ts)) : '';
        $multi = stripos((string)($h['content-type'] ?? ''), 'multipart/mixed') !== false;
        $url = P . '?a=mail&box=' . urlencode($box) . '&uid=' . $uid;
        $tr .= '<tr class="row' . ($unseen ? ' unseen' : '') . '" data-href="' . $url . '"><td class="c-chk"><input type="checkbox" name="uids[]" value="' . $uid . '" form="mdel" aria-label="Zaznacz"></td><td class="c-cli"><span' . ($unseen ? ' style="font-weight:500"' : '') . '>' . e($nm) . '</span><span class="sub">' . e($ad) . '</span></td>'
            . '<td class="c-num"><a class="mlink" href="' . $url . '"' . ($unseen ? ' style="font-weight:500"' : '') . '>' . e($subj) . '</a> ' . ($multi ? '<span class="mut" title="Załącznik">' . icon('clip') . '</span>' : '') . ' ' . inv_links($subj) . '</td>'
            . '<td class="c-amt r nw mut">' . e($when) . '</td></tr>';
    }
    $tabs = '';
    foreach (mail_boxes() as $k => $l) $tabs .= '<a href="' . P . '?a=inbox&box=' . urlencode($k) . '"' . ($k === $box ? ' class="on"' : '') . '>' . $l . ($k === 'INBOX' && (int)setting('imap_unseen') ? ' <span>' . (int)setting('imap_unseen') . '</span>' : '') . '</a>';
    $pages = $total > $per ? '<div class="hrow pad" style="justify-content:flex-end;gap:16px">' . ($pg > 1 ? '<a class="link" href="' . P . '?a=inbox&box=' . urlencode($box) . '&p=' . ($pg - 1) . '">Nowsze</a>' : '') . '<span class="mut" style="font-size:13px">' . (($pg - 1) * $per + 1) . '–' . min($total, $pg * $per) . ' z ' . $total . '</span>' . ($pg * $per < $total ? '<a class="link" href="' . P . '?a=inbox&box=' . urlencode($box) . '&p=' . ($pg + 1) . '">Starsze</a>' : '') . '</div>' : '';
    $body = '<form id="mdel" method="post" action="' . P . '?a=mail_delete">' . csrf_field() . '<input type="hidden" name="box" value="' . e($box) . '"></form>'
        . '<section class="card"><nav class="tabs">' . $tabs . '<button class="link mdelbtn" form="mdel" hidden>Usuń zaznaczone</button></nav>' . ($tr ? '<div class="tw"><table class="list mails"><tbody>' . $tr . '</tbody></table></div>' : '<div class="empty">Brak wiadomości.</div>') . $pages . '</section>';
    page('Poczta', 'inbox', $body, setting('imap_user'), '<a class="btn ghost" href="' . P . '?a=inbox&box=' . urlencode($box) . '" data-refresh>' . icon('refresh') . ' Odbierz</a><a class="btn" href="' . P . '?a=compose">Napisz ' . icon('plus') . '</a>');
}
function view_mail(): never {
    if (!inbox_enabled()) inbox_off();
    $box = ($_GET['box'] ?? '') === 'sent' ? 'sent' : 'INBOX'; $uid = (int)($_GET['uid'] ?? 0);
    try {
        $im = imap_open_box(); $im->select(real_box($im, $box));
        $raw = $im->raw($uid);
        if ($box === 'INBOX') { $im->flag($uid, '\\Seen'); set_setting('imap_unseen', (string)count($im->uids('UNSEEN'))); }
        unset($im);
    } catch (Throwable $e) { flash('!' . $e->getMessage()); redirect(P . '?a=inbox'); }
    $m = mime_parse($raw); $h = $m['h'];
    [$fn, $fa] = mail_addr((string)($h['from'] ?? '')); $subj = mime_hdr_decode((string)($h['subject'] ?? '(bez tematu)'));
    $ts = strtotime((string)($h['date'] ?? '')) ?: 0;
    $att = '';
    foreach ($m['att'] as $i => $a) if (empty($a[3]) || stripos($m['html'], 'cid:' . $a[3]) === false) $att .= '<a class="btn ghost sm" href="' . P . '?a=mail_att&box=' . urlencode($box) . '&uid=' . $uid . '&i=' . $i . '">' . icon('clip') . ' ' . e((string)$a[0]) . ' <span class="mut">' . e(size_fmt(strlen((string)$a[2]))) . '</span></a>';
    if ($m['html'] !== '') {
        $html = $m['html'];
        foreach ($m['att'] as $a) if (!empty($a[3]) && str_starts_with((string)$a[1], 'image/')) $html = str_ireplace('cid:' . $a[3], 'data:' . $a[1] . ';base64,' . base64_encode((string)$a[2]), $html);
        $html = preg_replace('#<(script|iframe|object|embed|form)[^>]*>.*?</\1>#is', '', $html);
        $content = '<iframe class="mframe" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" srcdoc="' . e('<base target="_blank"><style>body{font-family:Jost,Arial,sans-serif;margin:0;padding:0;color:#0B0B0B}img{max-width:100%;height:auto}</style>' . $html) . '"></iframe>';
    } else $content = '<div class="mtext">' . nl2br(e($m['text'])) . '</div>';
    $quote = 'Dnia ' . ($ts ? date('d.m.Y H:i', $ts) : '') . ' ' . $fn . ' <' . $fa . '> napisał(a):' . "\n" . mb_substr($m['text'], 0, 3000);
    $re = preg_match('/^re:/i', $subj) ? $subj : 'Re: ' . $subj;
    $body = '<div class="cols"><section class="card pad sec wide"><div class="mhead"><div><b style="font-weight:500">' . e($fn) . '</b> <span class="mut">&lt;' . e($fa) . '&gt;</span><div class="mut" style="font-size:13px">' . ($ts ? e(date('d.m.Y H:i', $ts)) : '') . (!empty($h['to']) ? ' · do ' . e(mime_hdr_decode((string)$h['to'])) : '') . '</div></div><div>' . inv_links($subj . ' ' . $m['text']) . '</div></div>'
        . ($att !== '' ? '<div class="hrow" style="justify-content:flex-start;flex-wrap:wrap;gap:8px">' . $att . '</div>' : '') . $content . '</section>'
        . '<aside class="side"><section class="card pad sec" id="odp"><h2>Odpowiedz</h2><form class="inl" method="post" action="' . P . '?a=mail_send">' . csrf_field()
        . '<input type="hidden" name="irt" value="' . e((string)($h['message-id'] ?? '')) . '"><input type="hidden" name="refs" value="' . e((string)($h['references'] ?? '')) . '"><input type="hidden" name="quote" value="' . e($quote) . '">'
        . '<label class="f">Do<input class="in" name="to" value="' . e((string)($h['reply-to'] ?? '') !== '' ? mail_addr((string)$h['reply-to'])[1] : $fa) . '"></label>'
        . '<label class="f">Temat<input class="in" name="subject" value="' . e($re) . '"></label>'
        . '<label class="f">Treść<textarea class="in" name="body" rows="8" required>Dzień dobry,' . "\n\n\n" . 'Pozdrawiam' . "\n" . 'Marcin Bednarz</textarea></label>'
        . inv_attach_select() . '<button class="btn">Wyślij ' . icon('arrow') . '</button></form></section>'
        . '<form method="post" action="' . P . '?a=mail_delete">' . csrf_field() . '<input type="hidden" name="uid" value="' . $uid . '"><input type="hidden" name="box" value="' . e($box) . '"><button class="link" style="color:var(--red)">Usuń wiadomość</button></form>'
        . ($box === 'INBOX' ? '<form method="post" action="' . P . '?a=mail_unseen">' . csrf_field() . '<input type="hidden" name="uid" value="' . $uid . '"><input type="hidden" name="box" value="' . e($box) . '"><button class="link">Oznacz jako nieprzeczytaną</button></form>' : '')
        . '</aside></div>';
    page($subj, 'inbox', $body, $box === 'INBOX' ? 'Odebrane' : 'Wysłane', '<a class="btn ghost" href="' . P . '?a=inbox&box=' . urlencode($box) . '">Wszystkie wiadomości</a>');
}
function inv_attach_select(int $sel = 0): string {
    $o = '<option value="">Bez załącznika</option>';
    foreach (db()->query("SELECT id, number, buyer FROM invoices WHERE status IN ('issued', 'paid') ORDER BY COALESCE(issue_date, created) DESC, id DESC LIMIT 40") as $r)
        $o .= '<option value="' . $r['id'] . '"' . ($sel === (int)$r['id'] ? ' selected' : '') . '>Faktura ' . e($r['number']) . ' · ' . e(mb_strimwidth((string)(json_decode($r['buyer'], true)['name'] ?? ''), 0, 40, '…')) . '</option>';
    return '<label class="f">Załącz fakturę (PDF)<select class="in" name="inv">' . $o . '</select></label>';
}
function size_fmt(int $b): string { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' kB'; }
function view_compose(): never {
    if (!inbox_enabled()) inbox_off();
    $inv = isset($_GET['inv']) ? invoice_by_id((int)$_GET['inv']) : null;
    $to = $inv ? (string)($inv['buyer']['email'] ?? '') : '';
    $body = '<section class="card pad sec" style="max-width:760px"><form class="inl" method="post" action="' . P . '?a=mail_send">' . csrf_field()
        . '<label class="f">Do<input class="in" name="to" value="' . e($to) . '" required placeholder="adres@firma.pl"></label>'
        . '<label class="f">Temat<input class="in" name="subject" value="' . e($inv ? 'Faktura ' . $inv['number'] : '') . '" required></label>'
        . '<label class="f">Treść<textarea class="in" name="body" rows="10" required>Dzień dobry,' . "\n\n\n" . 'Pozdrawiam' . "\n" . 'Marcin Bednarz</textarea></label>'
        . inv_attach_select($inv ? (int)$inv['id'] : 0) . '<button class="btn" style="align-self:flex-start">Wyślij ' . icon('arrow') . '</button></form></section>';
    page('Nowa wiadomość', 'inbox', $body, 'z ' . setting('imap_user'));
}
