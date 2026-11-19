<?php
// Maile do klientów w stylu Creative Vision (ciemny box, białe logo, gradient, biały przycisk).
declare(strict_types=1);

const MF = "font-family:Jost,'Helvetica Neue',Arial,sans-serif;";

/** Wspólny szkielet maila. $rows: gotowe wiersze <tr>…</tr>. */
function mail_layout(string $title, string $pre, string $rows): string {
    $img = CV_SITE . '/img/mail/';
    $phone = setting('seller_phone'); $mail = setting('seller_email');
    return '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="dark"><meta name="supported-color-schemes" content="dark"><title>' . e($title) . '</title>'
        . '<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&display=swap" rel="stylesheet">'
        . '<style>body{margin:0;padding:0;background:#000000}a{color:#ffffff}@media (max-width:620px){.w{width:100%!important}.p{padding-left:24px!important;padding-right:24px!important}.h{font-size:30px!important;line-height:36px!important}.b a{display:block!important}}</style>'
        . '</head><body style="margin:0;padding:0;background:#000000">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . e($pre) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#000000" style="background:#000000"><tr><td align="center" style="padding:32px 12px">'
        . '<table role="presentation" class="w" width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#0B0B0B" style="width:600px;max-width:600px;background:#0B0B0B;border:1px solid #2A2A2A">'
        . '<tr><td style="font-size:0;line-height:0"><img src="' . $img . 'gradient.png" width="600" height="3" alt="" style="display:block;width:100%;height:3px;border:0"></td></tr>'
        . '<tr><td class="p" style="padding:36px 48px 0 48px"><a href="' . CV_SITE . '" style="text-decoration:none"><img src="' . $img . 'logo-white.png" width="190" alt="creative vision" style="display:block;width:190px;height:auto;border:0"></a></td></tr>'
        . $rows
        . '<tr><td style="font-size:0;line-height:0;border-top:1px solid #2A2A2A">&nbsp;</td></tr>'
        . '<tr><td class="p" style="padding:24px 48px 30px 48px;' . MF . 'font-size:14px;line-height:24px;color:#9A9A9A">'
        . '<a href="tel:+48' . e(preg_replace('/\D/', '', $phone)) . '" style="color:#FFFFFF;text-decoration:none">' . e($phone) . '</a> &nbsp;·&nbsp; '
        . '<a href="mailto:' . e($mail) . '" style="color:#FFFFFF;text-decoration:none">' . e($mail) . '</a> &nbsp;·&nbsp; '
        . '<a href="' . CV_SITE . '" style="color:#FFFFFF;text-decoration:none">creative-vision.pl</a><br>'
        . 'Masz pytanie do faktury? Odpisz na tę wiadomość.</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function m_kicker(string $t): string { return '<tr><td class="p" style="padding:44px 48px 0 48px;' . MF . 'font-size:12px;line-height:18px;letter-spacing:2px;text-transform:uppercase;color:#9A9A9A">' . e($t) . '</td></tr>'; }
function m_h1(string $t): string { return '<tr><td class="p h" style="padding:14px 48px 0 48px;' . MF . 'font-size:36px;line-height:42px;font-weight:500;color:#FFFFFF">' . e($t) . '</td></tr>'; }
function m_text(string $html, int $top = 26): string { return '<tr><td class="p" style="padding:' . $top . 'px 48px 0 48px;' . MF . 'font-size:17px;line-height:28px;color:#C9C9C9">' . $html . '</td></tr>'; }
function m_button(string $href, string $label): string {
    return '<tr><td class="p b" style="padding:34px 48px 0 48px"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#FFFFFF" style="background:#FFFFFF">'
        . '<a href="' . e($href) . '" style="display:inline-block;padding:18px 34px;' . MF . 'font-size:13px;line-height:16px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:#0B0B0B;text-decoration:none;text-align:center">' . e($label) . ' &rarr;</a>'
        . '</td></tr></table></td></tr>';
}
/** Ramka z wierszami [etykieta, wartość, wyróżnij?]. */
function m_box(array $rows, int $top = 34): string {
    $h = '';
    $n = count($rows);
    foreach ($rows as $i => $r) {
        $last = ($i === $n - 1);
        $big = !empty($r[2]);
        $pad = $i === 0 ? '22px 26px 0 26px' : ($big ? '14px 26px 22px 26px' : '12px 26px 0 26px');
        if ($last && !$big) $pad = str_replace(' 0 26px', ' 22px 26px', $pad);
        $border = $big ? 'border-top:1px solid #2A2A2A;' : '';
        $h .= '<tr><td style="padding:' . $pad . ';' . $border . MF . 'font-size:15px;line-height:22px;color:#9A9A9A">' . e($r[0]) . '</td>'
            . '<td align="right" style="padding:' . $pad . ';' . $border . MF . ($big ? 'font-size:22px;line-height:28px;font-weight:500;' : 'font-size:15px;line-height:22px;') . 'color:#FFFFFF;white-space:nowrap">' . e($r[1]) . '</td></tr>';
    }
    return '<tr><td class="p" style="padding:' . $top . 'px 48px 0 48px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#141414" style="background:#141414;border:1px solid #2A2A2A">' . $h . '</table></td></tr>';
}
function m_transfer(array $inv): string {
    $s = seller();
    if (nrb_digits($s['bank_account']) === '') return '';
    return '<tr><td class="p" style="padding:30px 48px 0 48px;' . MF . 'font-size:14px;line-height:24px;color:#9A9A9A">'
        . 'Dane do przelewu' . ($s['bank_name'] !== '' ? ' · ' . e($s['bank_name']) : '') . '<br>'
        . '<span style="color:#FFFFFF;font-size:16px;letter-spacing:.5px">' . e(nrb_fmt($s['bank_account'])) . '</span><br>'
        . e($s['seller_name']) . ', ' . e($s['seller_addr1']) . ', ' . e($s['seller_addr2']) . '<br>'
        . 'Tytuł: Faktura ' . e((string)$inv['number']) . '</td></tr>';
}
function m_sign(): string {
    return '<tr><td class="p" style="padding:36px 48px 44px 48px"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td width="64" valign="middle"><img src="' . CV_SITE . '/img/mail/marcin.jpg" width="64" height="64" alt="Marcin Bednarz" style="display:block;width:64px;height:64px;border:1px solid #2A2A2A"></td>'
        . '<td valign="middle" style="padding-left:18px;' . MF . 'font-size:17px;line-height:24px;color:#FFFFFF;font-weight:500">Marcin Bednarz<br><span style="font-size:14px;line-height:22px;color:#9A9A9A;font-weight:400">Behavioral Designer / UX Specialist</span></td>'
        . '</tr></table></td></tr>';
}

/** Treść maila dla faktury. $kind: new | before | due | w1 | w2 | manual. Zwraca [temat, html, tekst]. */
function invoice_mail(array $inv, string $kind): array {
    $num = (string)$inv['number']; $gross = money((int)$inv['gross']);
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
            $rows = m_kicker('Nowa faktura') . m_h1('Faktura ' . $num)
                . m_text($hello . 'dziękujemy za współpracę. W załączniku przesyłamy fakturę ' . e($num) . '. Możesz ją też zobaczyć i pobrać online, razem z danymi do przelewu.')
                . m_box($box) . m_button($url, 'Zobacz fakturę');
            break;
        case 'before':
            $subj = 'Przypomnienie: termin faktury ' . $num . ' mija ' . $due;
            $pre = 'Za ' . $left . ' ' . ($left === 1 ? 'dzień' : 'dni') . ' mija termin płatności faktury ' . $num . '.';
            $rows = m_kicker('Przypomnienie') . m_h1('Za ' . $left . ' ' . ($left === 1 ? 'dzień' : 'dni') . ' mija termin płatności')
                . m_text($hello . 'uprzejmie przypominamy, że ' . e($due) . ' mija termin płatności za fakturę ' . e($num) . '. Jeśli przelew już wyszedł, zignoruj tę wiadomość.')
                . m_box($box) . m_button($url, 'Zobacz fakturę');
            break;
        case 'due':
            $subj = 'Dziś mija termin faktury ' . $num;
            $pre = 'Dziś mija termin płatności faktury ' . $num . ' na ' . $gross . '.';
            $rows = m_kicker('Termin mija dziś') . m_h1('Dziś mija termin płatności')
                . m_text($hello . 'dziś mija termin płatności za fakturę ' . e($num) . '. Jeśli przelew już wyszedł, zignoruj tę wiadomość. Dziękujemy.')
                . m_box($box) . m_button($url, 'Zobacz fakturę');
            break;
        case 'w1':
            $late = -$left;
            $subj = 'Wezwanie do zapłaty: faktura ' . $num;
            $pre = 'Faktura ' . $num . ' jest ' . $late . ' ' . ($late === 1 ? 'dzień' : 'dni') . ' po terminie.';
            $rows = m_kicker('Wezwanie do zapłaty') . m_h1('Faktura ' . $num . ' czeka na wpłatę')
                . m_text($hello . 'nie odnotowaliśmy jeszcze wpłaty za fakturę ' . e($num) . ', której termin minął ' . e($due) . '. Prosimy o uregulowanie należności w ciągu 7 dni, do ' . e($deadline7) . '.<br><br>Jeśli płatność już wyszła, zignoruj tę wiadomość. Jeśli coś się nie zgadza, po prostu odpisz, wyjaśnimy to od ręki.')
                . m_box([['Faktura', $num], ['Termin płatności', $due], ['Dni po terminie', (string)$late], ['Do zapłaty', $gross, true]]) . m_button($url, 'Zobacz fakturę');
            break;
        case 'w2':
        default:
            $late = -$left;
            $int = interest((int)$inv['gross'], (string)$inv['due_date'], today());
            $eur = compensation_eur((int)$inv['gross']);
            $subj = 'Ostateczne wezwanie do zapłaty: faktura ' . $num;
            $pre = 'Ostateczne wezwanie do zapłaty faktury ' . $num . '.';
            $rows = m_kicker('Ostateczne wezwanie do zapłaty') . m_h1('Prosimy o pilną wpłatę')
                . m_text($hello . 'mimo wcześniejszego przypomnienia nie otrzymaliśmy wpłaty za fakturę ' . e($num) . ' z terminem płatności ' . e($due) . '. Wzywamy do zapłaty należności w terminie 7 dni, do ' . e($deadline7) . '.<br><br>'
                    . 'Zgodnie z ustawą o przeciwdziałaniu nadmiernym opóźnieniom w transakcjach handlowych od dnia następującego po terminie płatności przysługują nam odsetki ustawowe za opóźnienie w transakcjach handlowych oraz rekompensata za koszty odzyskiwania należności w wysokości równowartości ' . $eur . ' euro.<br><br>'
                    . 'Zależy nam na dobrej współpracy, dlatego jeśli jest jakiś problem z płatnością, odpisz. Na pewno znajdziemy rozwiązanie. Brak wpłaty i kontaktu w tym terminie oznacza, że podejmiemy dalsze kroki w celu odzyskania należności.')
                . m_box([['Faktura', $num], ['Termin płatności', $due], ['Dni po terminie', (string)$late], ['Należność główna', $gross], ['Odsetki do dziś (' . setting('interest_rate') . '% rocznie)', money($int)], ['Razem z odsetkami', money((int)$inv['gross'] + $int), true]])
                . m_button($url, 'Zobacz fakturę');
            break;
    }
    $rows .= m_transfer($inv) . m_sign();
    $html = mail_layout($subj, $pre, $rows);
    $text = html_to_text($rows) . "\n\n" . $url . "\n\n" . setting('seller_name') . ' · ' . setting('seller_phone') . ' · creative-vision.pl';
    return [$subj, $html, $text];
}

/** Krótki mail dla nas (powiadomienie). */
function admin_mail(string $subject, string $kicker, string $h1, string $body, ?string $link = null): array {
    $rows = m_kicker($kicker) . m_h1($h1) . m_text($body) . ($link ? m_button($link, 'Otwórz w panelu') : '') . '<tr><td style="padding:0 0 40px 0"></td></tr>';
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
function send_mail(array $to, string $subject, string $html, string $text, array $attach = [], bool $copy = true): bool {
    $to = array_values(array_unique(array_filter(array_map('trim', $to), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL))));
    if (!$to) return false;
    $from = setting('mail_from') ?: setting('seller_email');
    $reply = setting('mail_reply') ?: $from;
    $name = setting('seller_name');
    $b1 = 'cvm' . bin2hex(random_bytes(8)); $b2 = 'cva' . bin2hex(random_bytes(8));
    $h = [];
    $h[] = 'From: ' . mime_h($name) . ' <' . $from . '>';
    $h[] = 'Reply-To: ' . $reply;
    if ($copy && setting('mail_copy') === '1' && !in_array($reply, $to, true)) $h[] = 'Bcc: ' . $reply;
    $h[] = 'MIME-Version: 1.0';
    $h[] = 'Date: ' . date(DATE_RFC2822);
    $h[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@creative-vision.pl>';
    $alt = "--$b2\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$b2\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$b2--\r\n";
    if ($attach) {
        $h[] = "Content-Type: multipart/mixed; boundary=\"$b1\"";
        $body = "--$b1\r\nContent-Type: multipart/alternative; boundary=\"$b2\"\r\n\r\n" . $alt;
        foreach ($attach as $fn => $data) {
            $body .= "--$b1\r\nContent-Type: application/pdf; name=\"$fn\"\r\nContent-Disposition: attachment; filename=\"$fn\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($data));
        }
        $body .= "--$b1--\r\n";
    } else {
        $h[] = "Content-Type: multipart/alternative; boundary=\"$b2\"";
        $body = $alt;
    }
    if (getenv('CV_PANEL_DEV') === '1') {
        $dir = CV_DATA . '/outbox'; if (!is_dir($dir)) mkdir($dir, 0700, true);
        file_put_contents($dir . '/' . date('His') . '-' . bin2hex(random_bytes(3)) . '.eml', 'To: ' . implode(', ', $to) . "\r\nSubject: " . mime_h($subject) . "\r\n" . implode("\r\n", $h) . "\r\n\r\n" . $body);
        return true;
    }
    return @mail(implode(', ', $to), mime_h($subject), $body, implode("\r\n", $h));
}

/** Wysyła mail do klienta w sprawie faktury i zapisuje w historii. */
function send_invoice_mail(array $inv, string $kind): bool {
    require_once __DIR__ . '/doc.php';
    $b = $inv['buyer'];
    $to = array_merge([(string)($b['email'] ?? '')], preg_split('/[\s,;]+/', (string)($b['cc'] ?? '')) ?: []);
    [$subj, $html, $text] = invoice_mail($inv, $kind);
    $att = in_array($kind, ['new', 'w1', 'w2'], true) ? [invoice_pdf_name($inv) => invoice_pdf($inv)] : [];
    $ok = send_mail($to, $subj, $html, $text, $att);
    log_event((int)$inv['id'], $ok ? 'mail' : 'error', ($ok ? '' : 'Nie udało się wysłać: ') . $subj . ' → ' . implode(', ', array_filter($to)));
    return $ok;
}
