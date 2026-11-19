<?php
// Maile do klientów w stylu Creative Vision (ciemny box, białe logo, gradient, biały przycisk).
declare(strict_types=1);

require_once __DIR__ . '/push.php';

const MF = "font-family:Jost,'Helvetica Neue',Helvetica,Arial,sans-serif;";
const MM = MF;

/** Wspólny szkielet maila: czarny nagłówek z logo, gradient, biała treść. $rows: gotowe wiersze <tr>…</tr>. */
function mail_layout(string $title, string $pre, string $rows, bool $system = false): string {
    $img = CV_SITE . '/img/mail/';
    $phone = setting('seller_phone'); $mail = setting('seller_email');
    return '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . e($title) . '</title>'
        . '<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&display=swap" rel="stylesheet">'
        . '<style>'
        . '@font-face{font-family:Jost;font-style:normal;font-weight:300 700;src:url(https://creative-vision.pl/fonts/jost-latin-ext.woff2) format("woff2");unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1E00-1E9F,U+20A0-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}'
        . '@font-face{font-family:Jost;font-style:normal;font-weight:300 700;src:url(https://creative-vision.pl/fonts/jost-latin.woff2) format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}'
        . 'body{margin:0;padding:0;background:#F1F1EF}a{color:#0B0B0B}'
        . '@media screen and (-webkit-min-device-pixel-ratio:0){.gt{background:linear-gradient(90deg,#E9685F,#E03F3B 20%,#AC2069 40%,#88409F 60%,#3E63EF 80%,#51BAD4);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent!important}}'
        . '@media (max-width:620px){.w{width:100%!important}.p{padding-left:24px!important;padding-right:24px!important}.h{font-size:34px!important;line-height:38px!important}.b a{display:block!important}.lab{display:block!important;width:auto!important;padding-bottom:0!important}.val{display:block!important;padding-top:4px!important;text-align:left!important;border-top:0!important}}</style>'
        . '</head><body style="margin:0;padding:0;background:#F1F1EF">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . e($pre) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F1F1EF" style="background:#F1F1EF"><tr><td align="center" style="padding:32px 12px">'
        . '<table role="presentation" class="w" width="640" cellpadding="0" cellspacing="0" border="0" bgcolor="#FFFFFF" style="width:640px;max-width:640px;background:#FFFFFF">'
        . '<tr><td class="p" bgcolor="#0B0B0B" style="background:#0B0B0B;padding:44px 48px"><a href="' . CV_SITE . '" style="text-decoration:none"><img src="' . $img . 'logo-white.png" width="230" alt="creative vision" style="display:block;width:230px;height:auto;border:0"></a></td></tr>'
        . '<tr><td style="font-size:0;line-height:0"><img src="' . $img . 'gradient.png" width="640" height="4" alt="" style="display:block;width:100%;height:4px;border:0"></td></tr>'
        . $rows
        . '<tr><td class="p" style="padding:0 48px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-top:1px solid #E6E4DF;font-size:0;line-height:0">&nbsp;</td></tr></table></td></tr>'
        . '<tr><td class="p" style="padding:24px 48px 40px 48px;' . MF . 'font-size:14px;line-height:24px;color:#6B6B6B">'
        . '<a href="tel:+48' . e(preg_replace('/\D/', '', $phone)) . '" style="color:#0B0B0B;text-decoration:none">' . e($phone) . '</a> &nbsp;·&nbsp; '
        . '<a href="mailto:' . e($mail) . '" style="color:#0B0B0B;text-decoration:none">' . e($mail) . '</a> &nbsp;·&nbsp; '
        . '<a href="' . CV_SITE . '" style="color:#0B0B0B;text-decoration:none">creative-vision.pl</a><br>'
        . ($system ? 'Wiadomość systemowa Creative Vision. Nie odpowiadaj na nią.' : 'Masz pytanie do faktury? Odpisz na tę wiadomość.') . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function m_kicker(string $t): string { return '<tr><td class="p" style="padding:52px 48px 0 48px;' . MF . 'font-size:15px;line-height:22px;color:#6B6B6B">' . e($t) . '</td></tr>'; }
/** Nagłówek: pierwsza linia czarna, druga w gradiencie marki. */
function m_h1(string $a, string $b = ''): string {
    return '<tr><td class="p h" style="padding:18px 48px 0 48px;' . MF . 'font-size:44px;line-height:50px;font-weight:500;letter-spacing:-0.5px;color:#0B0B0B">' . e($a)
        . ($b !== '' ? '<br><span class="gt" style="color:#AC2069">' . e($b) . '</span>' : '') . '</td></tr>';
}
function m_text(string $html, int $top = 30): string { return '<tr><td class="p" style="padding:' . $top . 'px 48px 0 48px;' . MF . 'font-size:18px;line-height:30px;color:#1F1F1F">' . $html . '</td></tr>'; }
function m_button(string $href, string $label): string {
    return '<tr><td class="p b" style="padding:36px 48px 0 48px"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#0B0B0B" style="background:#0B0B0B">'
        . '<a href="' . e($href) . '" style="display:inline-block;padding:19px 34px;' . MF . 'font-size:16px;line-height:20px;font-weight:500;color:#FFFFFF;text-decoration:none;text-align:center">' . e($label) . ' &rarr;</a>'
        . '</td></tr></table></td></tr>';
}
/** Sekcja z tytułem i wierszami [etykieta, wartość, wyróżnij?] jak „Dane zgłoszenia”. */
function m_rows(string $title, array $rows, int $top = 48): string {
    $h = '';
    foreach ($rows as $r) {
        $big = !empty($r[2]);
        $h .= '<tr><td class="lab" width="34%" valign="top" style="width:34%;padding:20px 16px 20px 0;border-top:1px solid #E6E4DF;' . MF . 'font-size:15px;line-height:22px;color:#6B6B6B">' . e($r[0]) . '</td>'
            . '<td class="val" valign="top" style="padding:' . ($big ? '16px' : '20px') . ' 0 20px 0;border-top:1px solid #E6E4DF;' . MF . ($big ? 'font-size:26px;line-height:30px;font-weight:600;' : 'font-size:18px;line-height:24px;') . 'color:#0B0B0B">' . e($r[1]) . '</td></tr>';
    }
    return '<tr><td class="p" style="padding:' . $top . 'px 48px 0 48px;' . MF . 'font-size:17px;line-height:24px;font-weight:500;color:#0B0B0B">' . e($title) . '</td></tr>'
        . '<tr><td class="p" style="padding:16px 48px 0 48px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-bottom:1px solid #E6E4DF">' . $h . '</table></td></tr>';
}
function m_box(array $rows, int $top = 48): string { return m_rows('Szczegóły faktury', $rows, $top); }
function m_transfer(array $inv): string {
    $s = seller();
    if (nrb_digits($s['bank_account']) === '') return '';
    return m_rows('Dane do przelewu', [
        ['Numer konta', nrb_fmt($s['bank_account'])],
        ['Odbiorca', $s['seller_name'] . ', ' . $s['seller_addr1'] . ', ' . $s['seller_addr2']],
        ['Tytuł', 'Faktura ' . $inv['number']],
    ]);
}
/** Stopka wiadomości automatycznej: bez podpisu osoby, z informacją, gdzie pisać. */
function m_auto(): string {
    $mail = setting('seller_email'); $phone = setting('seller_phone');
    return '<tr><td class="p" style="padding:40px 48px 40px 48px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F5F4F1" style="background:#F5F4F1"><tr><td style="padding:18px 22px;' . MF . 'font-size:14px;line-height:22px;color:#5F5F5F">'
        . '<span style="font-weight:500;color:#0B0B0B">Wiadomość automatyczna</span><br>'
        . 'Ta wiadomość została wygenerowana automatycznie, prosimy na nią nie odpowiadać. W sprawie faktury napisz na <a href="mailto:' . e($mail) . '" style="color:#0B0B0B">' . e($mail) . '</a>' . ($phone !== '' ? ' lub zadzwoń: <a href="tel:+48' . e(preg_replace('/\D/', '', $phone)) . '" style="color:#0B0B0B;text-decoration:none">' . e($phone) . '</a>' : '') . '.'
        . '</td></tr></table></td></tr>';
}
function m_sign(): string {
    return '<tr><td class="p" style="padding:44px 48px 44px 48px"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td width="64" valign="middle"><img src="' . CV_SITE . '/img/mail/marcin.jpg" width="64" height="64" alt="Marcin Bednarz" style="display:block;width:64px;height:64px;border:0"></td>'
        . '<td valign="middle" style="padding-left:18px;' . MF . 'font-size:17px;line-height:24px;color:#0B0B0B;font-weight:500">Marcin Bednarz<br><span style="font-size:14px;line-height:22px;color:#6B6B6B;font-weight:400">Behavioral Designer / UX Specialist</span></td>'
        . '</tr></table></td></tr>';
}

/** Treść maila dla faktury. $kind: new | before | due | w1 | w2 | manual. Zwraca [temat, html, tekst]. */
function invoice_mail(array $inv, string $kind, bool $track = false): array {
    $num = (string)$inv['number']; $gross = money((int)$inv['gross'], $inv['currency'] ?? 'PLN');
    $due = pl_date($inv['due_date']); $url = pay_url($inv);
    $left = days_between(today(), (string)$inv['due_date']);
    if ($kind === 'manual') $kind = $left > 0 ? 'before' : ($left === 0 ? 'due' : 'w1');
    $box = [['Faktura', $num], ['Termin płatności', $due], ['Do zapłaty', $gross, true]];
    $hello = 'Dzień dobry,<br><br>';
    $deadline7 = pl_date(date('Y-m-d', strtotime('+7 days')));
    switch ($kind) {
        case 'new':
            $subj = 'Faktura ' . $num . ' od Creative Vision';
            $pre = 'Faktura ' . $num . ' na ' . $gross . ', termin ' . $due . '.';
            $rows = m_kicker('Nowa faktura · ' . $num) . m_h1('Faktura ' . $num . ' na:', $gross)
                . m_text($hello . 'dziękujemy za współpracę. W załączniku przesyłamy fakturę ' . e($num) . '. Możesz ją też zobaczyć i pobrać online, razem z danymi do przelewu.')
                . m_box($box) . m_button($url, 'Zobacz fakturę');
            break;
        case 'before':
            $subj = 'Przypomnienie: termin faktury ' . $num . ' mija ' . $due;
            $pre = 'Za ' . $left . ' ' . ($left === 1 ? 'dzień' : 'dni') . ' mija termin płatności faktury ' . $num . '.';
            $rows = m_kicker('Przypomnienie · ' . $num) . m_h1('Termin płatności mija', 'za ' . $left . ' ' . ($left === 1 ? 'dzień' : 'dni'))
                . m_text($hello . 'uprzejmie przypominamy, że ' . e($due) . ' mija termin płatności za fakturę ' . e($num) . '. Jeśli przelew już wyszedł, zignoruj tę wiadomość.')
                . m_box($box) . m_button($url, 'Zobacz fakturę');
            break;
        case 'due':
            $subj = 'Dziś mija termin faktury ' . $num;
            $pre = 'Dziś mija termin płatności faktury ' . $num . ' na ' . $gross . '.';
            $rows = m_kicker('Termin mija dziś · ' . $num) . m_h1('Termin płatności mija', 'dzisiaj')
                . m_text($hello . 'dziś mija termin płatności za fakturę ' . e($num) . '. Jeśli przelew już wyszedł, zignoruj tę wiadomość. Dziękujemy.')
                . m_box($box) . m_button($url, 'Zobacz fakturę');
            break;
        case 'w1':
            $late = -$left;
            $subj = 'Wezwanie do zapłaty: faktura ' . $num;
            $pre = 'Faktura ' . $num . ' jest ' . $late . ' ' . ($late === 1 ? 'dzień' : 'dni') . ' po terminie.';
            $rows = m_kicker('Wezwanie do zapłaty · ' . $num) . m_h1('Faktura ' . $num, 'czeka na wpłatę')
                . m_text($hello . 'nie odnotowaliśmy jeszcze wpłaty za fakturę ' . e($num) . ', której termin minął ' . e($due) . '. Prosimy o uregulowanie należności w ciągu 7 dni, do ' . e($deadline7) . '.<br><br>Jeśli płatność już wyszła, zignoruj tę wiadomość. Jeśli coś się nie zgadza, napisz do nas na ' . e(setting('seller_email')) . ', wyjaśnimy to od ręki.')
                . m_box([['Faktura', $num], ['Termin płatności', $due], ['Dni po terminie', (string)$late], ['Do zapłaty', $gross, true]]) . m_button($url, 'Zobacz fakturę');
            break;
        case 'w2':
        default:
            $late = -$left;
            $int = interest((int)$inv['gross'], (string)$inv['due_date'], today());
            $eur = compensation_eur((int)$inv['gross']);
            $subj = 'Ostateczne wezwanie do zapłaty: faktura ' . $num;
            $pre = 'Ostateczne wezwanie do zapłaty faktury ' . $num . '.';
            $rows = m_kicker('Ostateczne wezwanie do zapłaty · ' . $num) . m_h1('Prosimy o pilną', 'wpłatę')
                . m_text($hello . 'mimo wcześniejszego przypomnienia nie otrzymaliśmy wpłaty za fakturę ' . e($num) . ' z terminem płatności ' . e($due) . '. Wzywamy do zapłaty należności w terminie 7 dni, do ' . e($deadline7) . '.<br><br>'
                    . 'Zgodnie z ustawą o przeciwdziałaniu nadmiernym opóźnieniom w transakcjach handlowych od dnia następującego po terminie płatności przysługują nam odsetki ustawowe za opóźnienie w transakcjach handlowych oraz rekompensata za koszty odzyskiwania należności w wysokości równowartości ' . $eur . ' euro.<br><br>'
                    . 'Zależy nam na dobrej współpracy, dlatego jeśli jest jakiś problem z płatnością, napisz do nas na ' . e(setting('seller_email')) . '. Na pewno znajdziemy rozwiązanie. Brak wpłaty i kontaktu w tym terminie oznacza, że podejmiemy dalsze kroki w celu odzyskania należności.')
                . m_box([['Faktura', $num], ['Termin płatności', $due], ['Dni po terminie', (string)$late], ['Należność główna', $gross], ['Odsetki do dziś (' . setting('interest_rate') . '% rocznie)', money($int, $inv['currency'] ?? 'PLN')], ['Razem z odsetkami', money((int)$inv['gross'] + $int, $inv['currency'] ?? 'PLN'), true]])
                . m_button($url, 'Zobacz fakturę');
            break;
    }
    $rows .= m_transfer($inv) . m_auto();
    if ($track && !empty($inv['token'])) $rows .= '<tr><td style="font-size:0;line-height:0"><img src="' . CV_SITE . '/panel/o.php?t=' . $inv['token'] . '&amp;k=' . $kind . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0"></td></tr>';
    $html = mail_layout($subj, $pre, $rows, true);
    $text = html_to_text($rows) . "\n\n" . $url . "\n\n" . setting('seller_name') . ' · ' . setting('seller_phone') . ' · creative-vision.pl';
    return [$subj, $html, $text];
}

/** Krótki mail dla nas (powiadomienie). */
function admin_mail(string $subject, string $kicker, string $h1, string $body, ?string $link = null, string $h1b = ''): array {
    $rows = m_kicker($kicker) . m_h1($h1, $h1b) . m_text($body) . ($link ? m_button($link, 'Otwórz w panelu') : '') . '<tr><td style="padding:0 0 40px 0"></td></tr>';
    return [$subject, mail_layout($subject, $h1, $rows), html_to_text($rows) . ($link ? "\n\n" . $link : '')];
}

function html_to_text(string $h): string {
    $h = preg_replace('#<br\s*/?>#i', "\n", $h);
    $h = preg_replace('#</tr>#i', "\n", $h);
    $h = preg_replace('#</td>#i', ' ', $h);
    $t = html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t]+/", ' ', $t);
    $t = preg_replace("/ *\n */", "\n", $t);
    return trim(preg_replace("/\n{3,}/", "\n\n", $t));
}

function mime_h(string $s): string { return '=?UTF-8?B?' . base64_encode($s) . '?='; }

/**
 * Wysyła mail. $to: lista adresów, $attach: [nazwa => treść]. Zwraca true, gdy przekazano do wysyłki.
 * W trybie lokalnym (CV_PANEL_DEV=1) zapisuje .eml do data/outbox zamiast wysyłać.
 */
function mail_last_error(?string $e = null): string { static $last = ''; if ($e !== null) $last = $e; return $last; }

/**
 * Wysyła mail. $to: lista adresów, $attach: [nazwa => treść]. $extra: dodatkowe nagłówki (np. In-Reply-To).
 * Gdy skrzynka faktury@ jest podłączona, idzie przez jej SMTP i trafia do folderu Wysłane.
 * W trybie lokalnym (CV_PANEL_DEV=1) zapisuje .eml do data/outbox zamiast wysyłać.
 */
function send_mail(array $to, string $subject, string $html, string $text, array $attach = [], bool $copy = true, array $extra = []): bool {
    require_once __DIR__ . '/smtp.php';
    $to = array_values(array_unique(array_filter(array_map('trim', $to), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL))));
    if (!$to) { mail_last_error('Brak poprawnego adresu odbiorcy.'); return false; }
    $smtp = smtp_enabled();
    $from = $smtp ? setting('imap_user') : (setting('mail_from') ?: setting('seller_email'));
    $reply = $smtp ? $from : (setting('mail_reply') ?: $from);
    $name = setting('seller_name');
    $b1 = 'cvm' . bin2hex(random_bytes(8)); $b2 = 'cva' . bin2hex(random_bytes(8));
    $h = [];
    $h[] = 'From: ' . mime_h($name) . ' <' . $from . '>';
    $h[] = 'Reply-To: ' . $reply;
    $bcc = (!$smtp && $copy && setting('mail_copy') === '1' && !in_array($reply, $to, true)) ? [$reply] : [];
    if ($bcc) $h[] = 'Bcc: ' . implode(', ', $bcc);
    $h[] = 'MIME-Version: 1.0';
    $h[] = 'Date: ' . date(DATE_RFC2822);
    $h[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@creative-vision.pl>';
    foreach ($extra as $k => $v) if ($v !== '') $h[] = $k . ': ' . preg_replace('/[\r\n]+/', ' ', $v);
    $alt = "--$b2\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$b2\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$b2--\r\n";
    if ($attach) {
        $h[] = "Content-Type: multipart/mixed; boundary=\"$b1\"";
        $body = "--$b1\r\nContent-Type: multipart/alternative; boundary=\"$b2\"\r\n\r\n" . $alt;
        foreach ($attach as $fn => $data) {
            $ct = str_ends_with(strtolower($fn), '.pdf') ? 'application/pdf' : 'application/octet-stream';
            $body .= "--$b1\r\nContent-Type: $ct; name=\"" . mime_h($fn) . "\"\r\nContent-Disposition: attachment; filename=\"" . mime_h($fn) . "\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($data));
        }
        $body .= "--$b1--\r\n";
    } else {
        $h[] = "Content-Type: multipart/alternative; boundary=\"$b2\"";
        $body = $alt;
    }
    $full = 'To: ' . implode(', ', $to) . "\r\nSubject: " . mime_h($subject) . "\r\n" . implode("\r\n", array_filter($h, fn($x) => !str_starts_with($x, 'Bcc:'))) . "\r\n\r\n" . $body;
    if (getenv('CV_PANEL_DEV') === '1' && !$smtp) {
        $dir = CV_DATA . '/outbox'; if (!is_dir($dir)) mkdir($dir, 0700, true);
        file_put_contents($dir . '/' . date('His') . '-' . bin2hex(random_bytes(3)) . '.eml', $full);
        return true;
    }
    if ($smtp) {
        try { smtp_send($from, array_merge($to, $bcc), $full); }
        catch (Throwable $e) { mail_last_error($e->getMessage()); return false; }
        try {
            require_once __DIR__ . '/imap.php';
            $im = imap_open_box(); $sent = sent_box($im);
            $im->append($sent, $full);
        } catch (Throwable $e) { /* kopia w Wysłanych nie jest konieczna */ }
        return true;
    }
    $ok = @mail(implode(', ', $to), mime_h($subject), $body, implode("\r\n", $h));
    if (!$ok) mail_last_error('Serwer nie przyjął wiadomości do wysyłki.');
    return $ok;
}

/** Wysyła mail do klienta w sprawie faktury i zapisuje w historii. */
function send_invoice_mail(array $inv, string $kind): bool {
    require_once __DIR__ . '/doc.php';
    if (client_no_mail(isset($inv['client_id']) ? (int)$inv['client_id'] : null)) { mail_last_error('Ten klient ma wyłączone maile z fakturami.'); return false; }
    $b = $inv['buyer'];
    $to = array_merge([(string)($b['email'] ?? '')], preg_split('/[\s,;]+/', (string)($b['cc'] ?? '')) ?: []);
    [$subj, $html, $text] = invoice_mail($inv, $kind, true);
    $att = in_array($kind, ['new', 'w1', 'w2'], true) ? [invoice_pdf_name($inv) => invoice_pdf($inv)] : [];
    $ok = send_mail($to, $subj, $html, $text, $att);
    log_event((int)$inv['id'], $ok ? 'mail' : 'error', ($ok ? '' : 'Nie udało się wysłać: ') . $subj . ' → ' . implode(', ', array_filter($to)));
    return $ok;
}

/** Pierwsze otwarcie maila albo faktury online: wpis w historii i krótki mail do nas. */
function notify_seen(array $inv, string $what, string $ev = 'open'): void {
    $name = (string)($inv['buyer']['name'] ?? 'Klient');
    log_event((int)$inv['id'], $ev, $what);
    if (function_exists('push_send')) { try { push_send($name . ' · ' . $inv['number'], $what, '/panel?a=view&id=' . $inv['id'], 'inv-' . $inv['id']); } catch (Throwable $e) {} }
    if (setting('notify_open') !== '1') return;
    [$s, $h, $t] = admin_mail($name . ': ' . mb_strtolower(mb_substr($what, 0, 1)) . mb_substr($what, 1) . ' (' . $inv['number'] . ')', 'Faktura ' . $inv['number'], $name, $what . ' · ' . date('d.m.Y H:i') . '. Kwota ' . e(money((int)$inv['gross'], $inv['currency'] ?? 'PLN')) . ', termin ' . e(pl_date($inv['due_date'])) . '.', CV_SITE . '/panel?a=view&id=' . $inv['id'], 'zobaczył wiadomość');
    send_mail([setting('mail_reply') ?: setting('seller_email')], $s, $h, $t, [], false);
}
