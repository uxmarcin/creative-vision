<?php
// Dokument faktury w stylu wzoru Creative Vision. Jeden szablon: podgląd w panelu, PDF (dompdf) i strona klienta.
declare(strict_types=1);

/**
 * @param string $asset  prefiks ścieżek do grafik: '/panel/assets/' w przeglądarce, ścieżka plikowa w PDF
 */
function invoice_doc(array $inv, string $asset, bool $pdf = false, bool $online = true): string {
    $s = seller(); $b = $inv['buyer']; $c = $inv['calc'];
    $num = $inv['number'] ?: 'Szkic';
    $term = (int)($inv['term_days'] ?? 0);
    $rows = '';
    foreach ($c['items'] as $i => $it) {
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
        $vatRows .= '<tr><td>VAT ' . e(vat_rates()[$rate] ?? $rate) . '</td><td class="r">' . money($v) . '</td></tr>';
    }
    $acc = nrb_fmt($s['bank_account']);
    $payBox = '<td class="pb-l"><div class="lbl">Do zapłaty</div><div class="big">' . money((int)$inv['gross']) . '</div>'
        . '<div class="sm">Słownie: ' . e(slownie((int)$inv['gross'])) . '</div></td>'
        . '<td class="pb-r"><div class="lbl">Sposób płatności</div><div class="v">Przelew' . ($term ? ', termin ' . $term . ' dni' : '') . '</div>'
        . '<div class="lbl">Numer konta' . ($s['bank_name'] !== '' ? ' · ' . e($s['bank_name']) : '') . '</div><div class="v b">' . ($acc !== '' ? e($acc) : '[uzupełnij w ustawieniach]') . '</div>'
        . '<div class="lbl">Tytuł przelewu</div><div class="v">Faktura ' . e($num) . '</div></td>';
    $ksef = trim((string)($inv['ksef'] ?? ''));

    $foot = '<table class="ft"><tr><td class="ty">' . e(setting('footer_note')) . '</td><td class="r g">' . e($s['seller_name']) . ' · Projektujemy strony, sklepy i aplikacje · Rzeszów<br>creative-vision.pl</td></tr></table>';
    $css = invoice_css($asset);
    $h = '<div class="fv">'
        . '<div class="grad top"></div>'
        . '<table class="hd"><tr><td><img class="logo" src="' . $asset . 'logo-black.svg" alt="creative vision"></td>'
        . '<td class="r"><div class="sm g">Faktura VAT</div><div class="no">' . e($num) . '</div><div class="xs g">Oryginał</div></td></tr></table>'
        . '<table class="meta"><tr>'
        . '<td><div class="g">Data wystawienia</div>' . e(pl_date($inv['issue_date'])) . '</td>'
        . '<td><div class="g">Data sprzedaży</div>' . e(pl_date($inv['sale_date'])) . '</td>'
        . '<td><div class="g">Termin płatności</div>' . e(pl_date($inv['due_date'])) . ($term ? ' (' . $term . ' dni)' : '') . '</td>'
        . '<td><div class="g">Miejsce wystawienia</div>' . e((string)$inv['place']) . '</td></tr></table>'
        . '<table class="par"><tr>'
        . '<td><div class="g">Sprzedawca</div><div class="pn">' . e($s['seller_name']) . '</div>' . e($s['seller_addr1']) . '<br>' . e($s['seller_addr2']) . '<br><span class="gap"></span>NIP ' . e($s['seller_nip']) . '<br>' . e($s['seller_email']) . '</td>'
        . '<td><div class="g">Nabywca</div><div class="pn">' . e($b['name'] ?? '') . '</div>' . e($b['addr1'] ?? '') . '<br>' . e($b['addr2'] ?? '') . '<br><span class="gap"></span>' . (!empty($b['nip']) ? 'NIP ' . e($b['nip']) : '') . '</td>'
        . '</tr></table>'
        . '<table class="its"><thead><tr><th class="lp">Lp.</th><th>Nazwa usługi</th><th class="r">Ilość</th><th class="r">Cena netto</th><th class="r">VAT</th><th class="r">Netto</th><th class="r">Brutto</th></tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<table class="sum"><tr><td>Razem netto</td><td class="r">' . money((int)$inv['net']) . '</td></tr>' . $vatRows
        . '<tr class="tot"><td>Razem brutto</td><td class="r">' . money((int)$inv['gross']) . '</td></tr></table>'
        . '<table class="pay"><tr>' . $payBox . '</tr></table><div class="grad"></div>'
        . ($online && $inv['status'] !== 'draft' && !empty($inv['token']) ? '<table class="online"><tr><td class="ol">Fakturę, PDF i dane do przelewu masz też online.</td><td class="r"><a class="obtn" href="' . e(pay_url($inv)) . '">Zobacz fakturę online</a></td></tr></table>' : '')
        . (trim((string)$inv['notes']) !== '' ? '<div class="nt"><div class="g">Uwagi</div>' . nl2br(e((string)$inv['notes'])) . '</div>' : '')
        . ($ksef !== '' ? '<div class="nt"><div class="g">Numer KSeF</div>' . e($ksef) . '</div>' : '')
        . ($pdf ? '' : $foot)
        . '</div>';
    if (!$pdf) return '<style>' . $css . '</style>' . $h;
    return '<!doctype html><html lang="pl"><head><meta charset="utf-8"><title>Faktura ' . e($num) . '</title><style>'
        . '@page{margin:0}'
        . '@font-face{font-family:"Jost";font-weight:400;src:url("' . CV_ROOT . '/fonts/Jost-400.ttf") format("truetype")}'
        . '@font-face{font-family:"Jost";font-weight:500;src:url("' . CV_ROOT . '/fonts/Jost-500.ttf") format("truetype")}'
        . '@font-face{font-family:"Jost";font-weight:700;src:url("' . CV_ROOT . '/fonts/Jost-500.ttf") format("truetype")}'
        . 'body{margin:0;font-family:Jost}' . $css . '.fv,.fv *{font-family:Jost !important}' . '.fv{padding:15mm 14mm 12mm;box-shadow:none;max-width:none}.pf{position:fixed;left:14mm;width:182mm;bottom:10mm;border-top:1px solid #E6E4DF;padding-top:3mm;font-family:Jost;color:#0B0B0B}.pf .l{font-size:11pt;font-weight:500}.pf .rr{position:absolute;right:0;top:3mm;text-align:right;font-size:9pt;color:#6B6B6B;line-height:1.35}</style></head><body><div class="pf"><div class="l">' . e(setting('footer_note')) . '</div><div class="rr">' . e($s['seller_name']) . ' · Projektujemy strony, sklepy i aplikacje · Rzeszów<br>creative-vision.pl</div></div>' . $h . '</body></html>';
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
        . '.fv .ft{margin-top:22px;border-top:1px solid #E6E4DF}.fv .ft td{padding-top:12px;font-size:9pt}.fv .ft .ty{font-size:11pt;font-weight:500;color:#0B0B0B;vertical-align:bottom}';
}

/** PDF jako string. */
function invoice_pdf(array $inv): string {
    require_once __DIR__ . '/pdf.php';
    return render_pdf(invoice_doc($inv, CV_ROOT . '/assets/', true));
}
function invoice_pdf_name(array $inv): string {
    return 'Faktura_' . str_replace('/', '_', (string)($inv['number'] ?: 'szkic')) . '.pdf';
}
