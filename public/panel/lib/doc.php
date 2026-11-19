<?php
// Dokument faktury w stylu wzoru Creative Vision. Jeden szablon: podgląd w panelu, PDF (dompdf) i strona klienta.
declare(strict_types=1);

require_once __DIR__ . '/ksef.php';

/**
 * @param string $asset  prefiks ścieżek do grafik: '/panel/assets/' w przeglądarce, ścieżka plikowa w PDF
 */
function invoice_doc(array $inv, string $asset, bool $pdf = false, bool $online = true): string {
    $s = seller(); $b = $inv['buyer']; $c = $inv['calc'];
    $cur = (string)($inv['currency'] ?? 'PLN') ?: 'PLN';
    $bi = ($inv['lang'] ?? 'pl') === 'pl-en';
    $L = fn(string $pl, string $en) => $bi ? $pl . '<span class="en"> / ' . $en . '</span>' : $pl;
    $Q = ($inv['doc'] ?? '') === 'quote';
    $num = $inv['number'] ?: 'Szkic';
    $term = (int)($inv['term_days'] ?? 0);
    $foreign = trim((string)($b['country'] ?? '')) !== '';
    $rows = ''; $np = false;
    foreach ($c['items'] as $i => $it) {
        if ($it['vat'] === 'np') $np = true;
        $rows .= '<tr class="it"><td class="lp">' . ($i + 1) . '</td><td class="nm"><strong>' . e($it['name']) . '</strong>'
            . ($it['desc'] !== '' ? '<br><span class="ds">' . nl2br(e($it['desc'])) . '</span>' : '') . '</td>'
            . '<td class="r nw">' . e(qty_fmt((float)$it['qty'])) . ' ' . e($it['unit']) . '</td>'
            . '<td class="r nw">' . money($it['price'], false) . '</td>'
            . '<td class="r nw">' . e(vat_rates()[$it['vat']] ?? $it['vat']) . '</td>'
            . '<td class="r nw">' . money($it['net'], false) . '</td>'
            . '<td class="r nw">' . money($it['gross'], false) . '</td></tr>';
    }
    $vatRows = '';
    foreach ($c['by_rate'] as $rate => $v) {
        $vatRows .= '<tr><td>VAT ' . e(vat_rates()[$rate] ?? $rate) . '</td><td class="r">' . money($v, $cur) . '</td></tr>';
    }
    $acc = nrb_fmt($s['bank_account']);
    if ($acc !== '' && ($foreign || $cur !== 'PLN')) $acc = 'PL' . $acc;
    $swift = bank_swift();
    if ($Q) $payBox = '<td class="pb-l"><div class="lbl">' . $L('Wartość wyceny', 'Quoted total') . '</div><div class="big">' . money((int)$inv['gross'], $cur) . '</div>'
        . '<div class="sm">' . $L('Netto', 'Net') . ' ' . money((int)$inv['net'], $cur) . ' · ' . $L('Słownie', 'In words') . ': ' . e(slownie((int)$inv['gross'], $cur)) . '</div></td>'
        . '<td class="pb-r"><div class="lbl">' . $L('Wycena ważna do', 'Valid until') . '</div><div class="v b">' . e(pl_date($inv['due_date'])) . '</div>'
        . '<div class="lbl">' . $L('Płatność', 'Payment') . '</div><div class="v">' . ($bi ? 'Bank transfer based on VAT invoice' : 'Przelew na podstawie faktury VAT') . ($term ? ', ' . ($bi ? $term . ' days' : 'termin ' . $term . ' dni') : '') . '</div>'
        . '<div class="lbl">' . $L('Kontakt', 'Contact') . '</div><div class="v">' . e(setting('seller_email')) . (setting('seller_phone') !== '' ? ' · ' . e(setting('seller_phone')) : '') . '</div></td>';
    else $payBox = '<td class="pb-l"><div class="lbl">' . $L('Do zapłaty', 'Amount due') . '</div><div class="big">' . money((int)$inv['gross'], $cur) . '</div>'
        . '<div class="sm">' . $L('Słownie', 'In words') . ': ' . e(slownie((int)$inv['gross'], $cur)) . ($bi ? '<br>' . e(words_en((int)$inv['gross'], $cur)) : '') . '</div></td>'
        . '<td class="pb-r"><div class="lbl">' . $L('Sposób płatności', 'Payment method') . '</div><div class="v">' . ($bi ? 'Przelew / Bank transfer' : 'Przelew') . ($term ? ', ' . ($bi ? $term . ' dni / days' : 'termin ' . $term . ' dni') : '') . '</div>'
        . '<div class="lbl">' . $L('Numer konta', 'Bank account') . ($s['bank_name'] !== '' ? ' · ' . e($s['bank_name']) : '') . '</div><div class="v b">' . ($acc !== '' ? e($acc) : '[uzupełnij w ustawieniach]') . '</div>'
        . ($swift !== '' && ($foreign || $cur !== 'PLN') ? '<div class="lbl">SWIFT / BIC</div><div class="v">' . e($swift) . '</div>' : '')
        . '<div class="lbl">' . $L('Tytuł przelewu', 'Payment reference') . '</div><div class="v">' . ($bi ? 'Invoice ' : 'Faktura ') . e($num) . '</div></td>';
    $ksef = trim((string)($inv['ksef'] ?? ''));
    $taxLbl = $foreign ? 'VAT no.' : 'NIP';
    $foot = '<table class="ft"><tr><td class="ty">' . e(setting('footer_note')) . ($bi ? '<span class="en"> Thank you.</span>' : '') . '</td><td class="r g">' . e($s['seller_name']) . ' · Projektujemy strony, sklepy i aplikacje · Rzeszów<br>creative-vision.pl</td></tr></table>';
    $css = invoice_css($asset);
    $h = '<div class="fv">'
        . '<div class="grad top"></div>'
        . '<table class="hd"><tr><td><img class="logo" src="' . $asset . 'logo-black.svg" alt="creative vision"></td>'
        . '<td class="r"><div class="sm g">' . ($Q ? $L('Wycena', 'Quotation') : $L('Faktura VAT', 'VAT Invoice')) . '</div><div class="no">' . e($num) . '</div>' . ($Q ? '' : '<div class="xs g">' . $L('Oryginał', 'Original') . '</div>') . '</td></tr></table>'
        . '<table class="meta"><tr>'
        . '<td><div class="g">' . $L('Data wystawienia', 'Issue date') . '</div>' . e(pl_date($inv['issue_date'])) . '</td>'
        . ($Q ? '<td><div class="g">' . $L('Ważna do', 'Valid until') . '</div>' . e(pl_date($inv['due_date'])) . '</td>'
            . '<td><div class="g">' . $L('Waluta', 'Currency') . '</div>' . e($cur) . '</td>'
          : '<td><div class="g">' . $L('Data sprzedaży', 'Date of sale') . '</div>' . e(pl_date($inv['sale_date'])) . '</td>'
            . '<td><div class="g">' . $L('Termin płatności', 'Due date') . '</div>' . e(pl_date($inv['due_date'])) . ($term && !$bi ? ' (' . $term . ' dni)' : '') . '</td>')
        . '<td><div class="g">' . $L('Miejsce wystawienia', 'Place of issue') . '</div>' . e((string)$inv['place']) . '</td></tr></table>'
        . '<table class="par"><tr>'
        . '<td><div class="g">' . ($Q ? $L('Wykonawca', 'Contractor') : $L('Sprzedawca', 'Seller')) . '</div><div class="pn">' . e($s['seller_name']) . '</div>' . e($s['seller_addr1']) . '<br>' . e($s['seller_addr2']) . ($foreign ? '<br>Polska / Poland' : '') . '<br><span class="gap"></span>' . ($foreign ? 'VAT no. PL' . e(nip_digits($s['seller_nip'])) : 'NIP ' . e($s['seller_nip'])) . '<br>' . e($s['seller_email']) . '</td>'
        . '<td><div class="g">' . ($Q ? $L('Klient', 'Client') : $L('Nabywca', 'Buyer')) . '</div><div class="pn">' . e($b['name'] ?? '') . '</div>' . e($b['addr1'] ?? '') . '<br>' . e($b['addr2'] ?? '') . ($foreign ? '<br>' . e((string)$b['country']) : '') . '<br><span class="gap"></span>' . (!empty($b['nip']) ? $taxLbl . ' ' . e($b['nip']) : '') . '</td>'
        . '</tr></table>'
        . '<table class="its"><thead><tr><th class="lp">' . $L('Lp.', 'No.') . '</th><th>' . $L('Nazwa usługi', 'Service') . '</th><th class="r">' . $L('Ilość', 'Qty') . '</th><th class="r">' . $L('Cena netto', 'Unit price') . '</th><th class="r">VAT</th><th class="r">' . $L('Netto', 'Net') . '</th><th class="r">' . $L('Brutto', 'Gross') . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<table class="sum"><tr><td>' . $L('Razem netto', 'Total net') . '</td><td class="r">' . money((int)$inv['net'], $cur) . '</td></tr>' . $vatRows
        . '<tr class="tot"><td>' . $L('Razem brutto', 'Total') . '</td><td class="r">' . money((int)$inv['gross'], $cur) . '</td></tr></table>'
        . '<table class="pay"><tr>' . $payBox . '</tr></table><div class="grad"></div>'
        . (!$Q && $online && $inv['status'] !== 'draft' && !empty($inv['token']) ? '<table class="online"><tr><td class="ol">' . $L('Fakturę, PDF i dane do przelewu masz też online.', 'View the invoice online.') . '</td><td class="r"><a class="obtn" href="' . e(pay_url($inv)) . '">' . ($bi ? 'View invoice online' : 'Zobacz fakturę online') . '</a></td></tr></table>' : '')
        . ($np ? '<div class="nt"><div class="g">' . $L('Odwrotne obciążenie', 'Reverse charge') . '</div>Do rozliczenia podatku VAT zobowiązany jest nabywca usługi (odwrotne obciążenie).' . ($bi ? '<br><span class="en2">VAT to be accounted for by the recipient of the service (reverse charge).</span>' : '') . '</div>' : '')
        . (trim((string)$inv['notes']) !== '' ? '<div class="nt"><div class="g">' . $L('Uwagi', 'Notes') . '</div>' . nl2br(e((string)$inv['notes'])) . '</div>' : '')
        . (!$Q && ($qrUrl = ksef_qr_url($inv)) ? '<table class="qr"><tr><td class="qi"><img src="' . qr_png_datauri($qrUrl, 4) . '" alt="Kod QR KSeF"><div class="ql">' . e($ksef !== '' ? $ksef : 'OFFLINE') . '</div></td><td class="qt"><div class="g">' . $L('Weryfikacja w KSeF', 'KSeF verification') . '</div>' . ($ksef !== '' ? $L('Numer KSeF', 'KSeF number') . ': <strong>' . e($ksef) . '</strong><br>' : '') . '<span class="g">' . $L('Zeskanuj kod, aby sprawdzić fakturę w Krajowym Systemie e-Faktur.', 'Scan the code to verify the invoice in the National e-Invoicing System.') . '</span></td></tr></table>'
            : ($ksef !== '' ? '<div class="nt"><div class="g">Numer KSeF</div>' . e($ksef) . '</div>' : ''))
        . ($pdf ? '' : $foot)
        . '</div>';
    if (!$pdf) return '<style>' . $css . '</style>' . $h;
    return '<!doctype html><html lang="pl"><head><meta charset="utf-8"><title>' . ($Q ? 'Wycena ' : 'Faktura ') . e($num) . '</title><style>'
        . '@page{margin:0}'
        . '@font-face{font-family:"Jost";font-weight:400;src:url("' . CV_ROOT . '/fonts/Jost-400.ttf") format("truetype")}'
        . '@font-face{font-family:"Jost";font-weight:500;src:url("' . CV_ROOT . '/fonts/Jost-500.ttf") format("truetype")}'
        . '@font-face{font-family:"Jost";font-weight:700;src:url("' . CV_ROOT . '/fonts/Jost-500.ttf") format("truetype")}'
        . 'body{margin:0;font-family:Jost}' . $css . '.fv,.fv *{font-family:Jost !important}' . '.fv{padding:13mm 14mm 24mm;box-shadow:none;max-width:none;background:transparent}.fv .hd td{padding-bottom:12px}.fv .meta td{padding:10px 10px 10px 0}.fv .par td{padding:12px 16px 10px 0}.fv .its td{padding:8px 0 8px 8px}.fv .sum{margin:10px 0 12px auto}.fv .pay td{padding:15px 20px}.fv .online{margin-top:10px}.fv .nt{margin-top:10px;padding-top:8px}.pf{position:fixed;left:14mm;width:182mm;bottom:10mm;border-top:1px solid #E6E4DF;padding-top:3mm;font-family:Jost;color:#0B0B0B}.pf .l{font-size:11pt;font-weight:500}.pf .rr{position:absolute;right:0;top:3mm;text-align:right;font-size:9pt;color:#6B6B6B;line-height:1.35}</style></head><body>' . $h . '<div class="pf"><div class="l">' . e(setting('footer_note')) . (($inv['lang'] ?? 'pl') === 'pl-en' ? ' Thank you.' : '') . '</div><div class="rr">' . e($s['seller_name']) . ' · Projektujemy strony, sklepy i aplikacje · Rzeszów<br>creative-vision.pl</div></div></body></html>';
}

function invoice_css(string $asset): string {
    return '.fv{font-family:Jost,"Helvetica Neue",Arial,sans-serif;color:#0B0B0B;background:#fff;font-size:10pt;line-height:1.35;position:relative;padding:44px 48px 36px;box-sizing:border-box}'
        . '.fv table{width:100%;border-collapse:collapse}.fv td,.fv th{vertical-align:top;padding:0}'
        . '.fv .r{text-align:right}.fv .nw{white-space:nowrap}.fv .g{color:#6B6B6B}.fv .sm{font-size:10pt}.fv .xs{font-size:8.5pt}'
        . '.fv .grad{height:3px;background:url("' . $asset . 'gradient.png") 0 0/100% 100% no-repeat}'
        . '.fv .grad.top{position:absolute;left:0;right:0;top:0;height:4px}'
        . '.fv .hd td{vertical-align:bottom;padding-bottom:16px;border-bottom:1px solid #0B0B0B}.fv .logo{height:30px;width:auto}.fv .no{font-size:24pt;font-weight:500;line-height:1.1}'
        . '.fv .meta td{width:25%;padding:14px 10px 14px 0;border-bottom:1px solid #E6E4DF}.fv .meta .g{font-size:9pt;margin-bottom:3px}'
        . '.fv .par td{width:50%;padding:16px 16px 16px 0}.fv .par .g{font-size:9pt;margin-bottom:6px}.fv .gap{display:block;height:8px}.fv .pn{font-size:12pt;font-weight:500;margin-bottom:2px}'
        . '.fv .its{margin-top:6px}.fv .its th{white-space:nowrap;font-weight:400;color:#6B6B6B;font-size:9pt;padding:0 0 8px 8px;text-align:left;border-bottom:1px solid #0B0B0B}.fv .its th.r{text-align:right}.fv .its th.lp{padding-left:0;width:22px}'
        . '.fv .its td{padding:10px 0 10px 8px;border-bottom:1px solid #E6E4DF}.fv .its td.lp{padding-left:0}.fv .its .ds{color:#5F5F5F;font-size:9.5pt}.fv .its strong{font-weight:500}'
        . '.fv .sum{width:46%;margin:14px 0 18px auto}.fv .sum td{padding:4px 0;color:#5F5F5F}.fv .sum td.r{color:#0B0B0B}.fv .sum .tot td{border-top:1px solid #0B0B0B;padding-top:9px;color:#0B0B0B;font-weight:500;font-size:11.5pt}'
        . '.fv .pay{background:#0B0B0B;color:#fff}.fv .pay td{padding:20px 22px}.fv .pay .pb-l{width:48%}.fv .lbl{color:#BDBDBD;font-size:9pt;margin-top:6px}.fv .lbl:first-child{margin-top:0}.fv .big{font-size:24pt;font-weight:500;line-height:1.15;margin:2px 0 6px}.fv .pay .sm{color:#BDBDBD;font-size:9pt}.fv .v{font-size:10.5pt}.fv .v.b{font-weight:500;letter-spacing:.02em}'
        . '.fv .online{margin-top:14px}.fv .online td{vertical-align:middle}.fv .online .ol{font-size:9.5pt;color:#5F5F5F}.fv .obtn{display:inline-block;padding:9px 16px;border:1px solid #0B0B0B;color:#0B0B0B;text-decoration:none;font-size:8.5pt;font-weight:500;letter-spacing:.12em;text-transform:uppercase;white-space:nowrap}.fv .obtn:hover{background:#0B0B0B;color:#fff}'
        . '.fv .nt{margin-top:14px;padding-top:10px;border-top:1px solid #E6E4DF;font-size:10pt}.fv .nt .g{font-size:9pt;margin-bottom:3px}'
        . '.fv .qr{margin-top:14px;border-top:1px solid #E6E4DF}.fv .qr td{padding-top:12px;vertical-align:top}.fv .qr .qi{width:120px;padding-right:16px}.fv .qr img{width:110px;height:110px;display:block}.fv .qr .ql{font-size:6.5pt;color:#5F5F5F;margin-top:2px;word-break:break-all;width:110px;text-align:center}.fv .qr .qt{font-size:9.5pt}.fv .qr strong{font-weight:500}.fv .en{color:#8A8A8A;font-weight:400}.fv .en2{color:#6B6B6B}.fv .ft{margin-top:22px;border-top:1px solid #E6E4DF}.fv .ft td{padding-top:12px;font-size:9pt}.fv .ft .ty{font-size:11pt;font-weight:500;color:#0B0B0B;vertical-align:bottom}';
}

/** PDF jako string. */
function invoice_pdf(array $inv): string {
    require_once __DIR__ . '/pdf.php';
    return render_pdf(invoice_doc($inv, CV_ROOT . '/assets/', true));
}
function invoice_pdf_name(array $inv): string {
    return 'Faktura_' . str_replace('/', '_', (string)($inv['number'] ?: 'szkic')) . '.pdf';
}
