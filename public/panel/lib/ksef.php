<?php
// KSeF 2.0: faktura FA(3), uwierzytelnienie tokenem KSeF, wysyłka w sesji interaktywnej, kod QR (KOD I).
declare(strict_types=1);

const KSEF_ENV = [
    'test' => ['api' => 'https://api-test.ksef.mf.gov.pl/v2', 'qr' => 'https://qr-test.ksef.mf.gov.pl', 'name' => 'testowe'],
    'demo' => ['api' => 'https://api-demo.ksef.mf.gov.pl/v2', 'qr' => 'https://qr-demo.ksef.mf.gov.pl', 'name' => 'przedprodukcyjne (demo)'],
    'prod' => ['api' => 'https://api.ksef.mf.gov.pl/v2', 'qr' => 'https://qr.ksef.mf.gov.pl', 'name' => 'produkcyjne'],
];

function ksef_env(): array { return KSEF_ENV[setting('ksef_env')] ?? KSEF_ENV['prod']; }
function ksef_enabled(): bool { return setting('ksef_token') !== ''; }

// ---------- kraje ----------
function country_code(string $name): string {
    $n = mb_strtolower(trim($name));
    if ($n === '' || str_contains($n, 'polska') || $n === 'pl') return 'PL';
    if (preg_match('/^[a-z]{2}$/', $n)) return strtoupper($n);
    $map = ['stany' => 'US', 'united states' => 'US', 'usa' => 'US', 'wielka brytania' => 'GB', 'united kingdom' => 'GB', 'anglia' => 'GB', 'niemcy' => 'DE', 'germany' => 'DE',
        'czech' => 'CZ', 'słowacja' => 'SK', 'slovakia' => 'SK', 'holandia' => 'NL', 'niderland' => 'NL', 'netherlands' => 'NL', 'francja' => 'FR', 'france' => 'FR', 'irlandia' => 'IE', 'ireland' => 'IE',
        'hiszpania' => 'ES', 'spain' => 'ES', 'włochy' => 'IT', 'italy' => 'IT', 'austria' => 'AT', 'szwajcaria' => 'CH', 'switzerland' => 'CH', 'norwegia' => 'NO', 'norway' => 'NO',
        'szwecja' => 'SE', 'sweden' => 'SE', 'dania' => 'DK', 'denmark' => 'DK', 'belgia' => 'BE', 'belgium' => 'BE', 'ukraina' => 'UA', 'ukraine' => 'UA', 'kanada' => 'CA', 'canada' => 'CA',
        'australia' => 'AU', 'litwa' => 'LT', 'łotwa' => 'LV', 'estonia' => 'EE', 'finlandia' => 'FI', 'portugalia' => 'PT', 'węgry' => 'HU', 'rumunia' => 'RO', 'bułgaria' => 'BG',
        'chorwacja' => 'HR', 'słowenia' => 'SI', 'grecja' => 'GR', 'cypr' => 'CY', 'malta' => 'MT', 'luksemburg' => 'LU', 'zjednoczone emiraty' => 'AE', 'izrael' => 'IL', 'japonia' => 'JP'];
    foreach ($map as $k => $c) if (str_contains($n, $k)) return $c;
    return '';
}
function eu_country(string $code): bool {
    return in_array($code, ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'GR', 'EL', 'ES', 'NL', 'IE', 'LT', 'LU', 'LV', 'MT', 'DE', 'PT', 'RO', 'SK', 'SI', 'SE', 'HU', 'IT'], true);
}

// ---------- FA(3) ----------
function fa_amount(int $c): string { return number_format($c / 100, 2, '.', ''); }
function fa_qty(float $q): string { $s = rtrim(rtrim(number_format($q, 6, '.', ''), '0'), '.'); return $s === '' ? '0' : $s; }
function x(string $s): string { return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }

/** Buduje XML faktury FA(3). Rzuca wyjątek z czytelnym opisem, gdy czegoś brakuje. */
function ksef_xml(array $inv): string {
    $s = seller(); $b = $inv['buyer']; $c = $inv['calc'];
    $cur = (string)($inv['currency'] ?: 'PLN');
    $nip = nip_digits($s['seller_nip']);
    if (strlen($nip) !== 10) throw new RuntimeException('W ustawieniach brakuje poprawnego NIP sprzedawcy.');
    if (!$c['items']) throw new RuntimeException('Faktura nie ma pozycji.');
    $cc = country_code((string)($b['country'] ?? ''));
    $foreign = trim((string)($b['country'] ?? '')) !== '';
    if ($foreign && $cc === '') throw new RuntimeException('Nie rozpoznaję kraju nabywcy. Wpisz w polu kraju dwuliterowy kod, np. US.');
    // stawki
    $map = ['23' => ['1', '23'], '8' => ['2', '8'], '5' => ['3', '5'], '0' => ['6_1', '0 KR'], 'zw' => ['7', 'zw']];
    $sum = []; $vat = []; $np = false; $zw = false;
    $rows = '';
    foreach ($c['items'] as $i => $it) {
        $r = (string)$it['vat'];
        if ($r === 'np') { $np = true; [$fld, $p12] = ($foreign && eu_country($cc)) ? ['9', 'np II'] : ['8', 'np I']; }
        else { if (!isset($map[$r])) throw new RuntimeException('Nieobsługiwana stawka VAT: ' . $r); [$fld, $p12] = $map[$r]; if ($r === 'zw') $zw = true; }
        $sum[$fld] = ($sum[$fld] ?? 0) + (int)$it['net'];
        if (in_array($fld, ['1', '2', '3'], true)) $vat[$fld] = ($vat[$fld] ?? 0) + (int)$it['vat_amount'];
        $rows .= '<FaWiersz><NrWierszaFa>' . ($i + 1) . '</NrWierszaFa><P_7>' . x($it['name']) . '</P_7><P_8A>' . x($it['unit']) . '</P_8A><P_8B>' . fa_qty((float)$it['qty']) . '</P_8B>'
            . '<P_9A>' . fa_amount((int)$it['price']) . '</P_9A><P_11>' . fa_amount((int)$it['net']) . '</P_11><P_12>' . $p12 . '</P_12></FaWiersz>';
    }
    if ($cur !== 'PLN' && $vat) throw new RuntimeException('Faktura w walucie obcej ze stawką VAT wymaga przeliczenia podatku po kursie NBP. Ta wersja panelu wysyła do KSeF faktury walutowe tylko ze stawką „np.”.');
    if ($zw && trim(setting('zw_basis')) === '') throw new RuntimeException('Dla stawki „zw.” wpisz w ustawieniach podstawę zwolnienia, np. art. 113 ust. 1 ustawy o VAT.');
    $totals = '';
    foreach (['1', '2', '3', '6_1', '7', '8', '9'] as $f) {
        if (!isset($sum[$f])) continue;
        $totals .= '<P_13_' . $f . '>' . fa_amount($sum[$f]) . '</P_13_' . $f . '>';
        if (isset($vat[$f])) $totals .= '<P_14_' . $f . '>' . fa_amount($vat[$f]) . '</P_14_' . $f . '>';
    }
    // nabywca
    $bnip = nip_digits((string)($b['nip'] ?? ''));
    if (!$foreign) {
        if (strlen($bnip) === 10) $bid = '<NIP>' . $bnip . '</NIP>';
        else $bid = '<BrakID>1</BrakID>';
    } elseif (eu_country($cc) && $bnip !== '') {
        $bid = '<KodUE>' . ($cc === 'GR' ? 'EL' : $cc) . '</KodUE><NrVatUE>' . x(preg_replace('/^[A-Z]{2}/', '', strtoupper(preg_replace('/[\s-]/', '', (string)$b['nip'])))) . '</NrVatUE>';
    } elseif (trim((string)($b['nip'] ?? '')) !== '') {
        $bid = '<KodKraju>' . $cc . '</KodKraju><NrID>' . x(trim((string)$b['nip'])) . '</NrID>';
    } else $bid = '<BrakID>1</BrakID>';
    $addr = fn(string $code, string $l1, string $l2) => '<Adres><KodKraju>' . $code . '</KodKraju><AdresL1>' . x($l1 !== '' ? $l1 : '-') . '</AdresL1>' . ($l2 !== '' ? '<AdresL2>' . x($l2) . '</AdresL2>' : '') . '</Adres>';
    $bemail = trim((string)($b['email'] ?? ''));
    // płatność
    $acc = nrb_digits($s['bank_account']);
    $pay = '<Platnosc>' . ($inv['status'] === 'paid' && !empty($inv['paid_at']) && substr((string)$inv['paid_at'], 0, 10) <= (string)$inv['issue_date'] ? '<Zaplacono>1</Zaplacono><DataZaplaty>' . substr((string)$inv['paid_at'], 0, 10) . '</DataZaplaty>' : '')
        . '<TerminPlatnosci><Termin>' . x((string)$inv['due_date']) . '</Termin></TerminPlatnosci><FormaPlatnosci>6</FormaPlatnosci>'
        . (strlen($acc) === 26 ? '<RachunekBankowy><NrRB>' . ($foreign || $cur !== 'PLN' ? 'PL' : '') . $acc . '</NrRB>' . (trim(setting('bank_swift')) !== '' ? '<SWIFT>' . x(trim(setting('bank_swift'))) . '</SWIFT>' : '') . ($s['bank_name'] !== '' ? '<NazwaBanku>' . x($s['bank_name']) . '</NazwaBanku>' : '') . '</RachunekBankowy>' : '')
        . '</Platnosc>';
    $sale = (string)$inv['sale_date'];
    $notes = trim((string)$inv['notes']);
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Faktura xmlns:etd="http://crd.gov.pl/xml/schematy/dziedzinowe/mf/2022/01/05/eD/DefinicjeTypy/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://crd.gov.pl/wzor/2025/06/25/13775/">'
        . '<Naglowek><KodFormularza kodSystemowy="FA (3)" wersjaSchemy="1-0E">FA</KodFormularza><WariantFormularza>3</WariantFormularza>'
        . '<DataWytworzeniaFa>' . gmdate('Y-m-d\TH:i:s\Z') . '</DataWytworzeniaFa><SystemInfo>Panel faktur Creative Vision</SystemInfo></Naglowek>'
        . '<Podmiot1><DaneIdentyfikacyjne><NIP>' . $nip . '</NIP><Nazwa>' . x($s['seller_name']) . '</Nazwa></DaneIdentyfikacyjne>'
        . $addr('PL', $s['seller_addr1'], $s['seller_addr2'])
        . (($s['seller_email'] !== '' || $s['seller_phone'] !== '') ? '<DaneKontaktowe>' . ($s['seller_email'] !== '' ? '<Email>' . x($s['seller_email']) . '</Email>' : '') . ($s['seller_phone'] !== '' ? '<Telefon>' . x(preg_replace('/\s/', '', $s['seller_phone'])) . '</Telefon>' : '') . '</DaneKontaktowe>' : '')
        . '</Podmiot1>'
        . '<Podmiot2><DaneIdentyfikacyjne>' . $bid . '<Nazwa>' . x((string)($b['name'] ?? '')) . '</Nazwa></DaneIdentyfikacyjne>'
        . $addr($foreign ? $cc : 'PL', (string)($b['addr1'] ?? ''), (string)($b['addr2'] ?? ''))
        . ($bemail !== '' && filter_var($bemail, FILTER_VALIDATE_EMAIL) ? '<DaneKontaktowe><Email>' . x($bemail) . '</Email></DaneKontaktowe>' : '')
        . '<JST>2</JST><GV>2</GV></Podmiot2>'
        . '<Fa><KodWaluty>' . x($cur) . '</KodWaluty><P_1>' . x((string)$inv['issue_date']) . '</P_1>' . (trim((string)$inv['place']) !== '' ? '<P_1M>' . x((string)$inv['place']) . '</P_1M>' : '')
        . '<P_2>' . x((string)$inv['number']) . '</P_2>' . ($sale !== '' && $sale !== $inv['issue_date'] ? '<P_6>' . x($sale) . '</P_6>' : '')
        . $totals . '<P_15>' . fa_amount((int)$inv['gross']) . '</P_15>'
        . '<Adnotacje><P_16>2</P_16><P_17>2</P_17><P_18>' . ($np ? '1' : '2') . '</P_18><P_18A>2</P_18A>'
        . '<Zwolnienie>' . ($zw ? '<P_19>1</P_19><P_19A>' . x(trim(setting('zw_basis'))) . '</P_19A>' : '<P_19N>1</P_19N>') . '</Zwolnienie>'
        . '<NoweSrodkiTransportu><P_22N>1</P_22N></NoweSrodkiTransportu><P_23>2</P_23><PMarzy><P_PMarzyN>1</P_PMarzyN></PMarzy></Adnotacje>'
        . '<RodzajFaktury>VAT</RodzajFaktury>'
        . ($notes !== '' ? '<DodatkowyOpis><Klucz>Uwagi</Klucz><Wartosc>' . x(mb_substr($notes, 0, 256)) . '</Wartosc></DodatkowyOpis>' : '')
        . $rows . $pay . '</Fa></Faktura>';
}

// ---------- kryptografia ----------
/** RSAES-OAEP z SHA-256 i MGF1(SHA-256). PHP ma OAEP tylko z SHA-1, więc dopełnienie liczymy sami. */
function rsa_oaep_sha256(string $data, string $certDerB64): string {
    $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split($certDerB64, 64, "\n") . "-----END CERTIFICATE-----\n";
    $pub = openssl_pkey_get_public($pem);
    if (!$pub) throw new RuntimeException('Nie udało się odczytać klucza publicznego KSeF.');
    $k = (int)(openssl_pkey_get_details($pub)['bits'] / 8);
    $hLen = 32; $mLen = strlen($data);
    if ($mLen > $k - 2 * $hLen - 2) throw new RuntimeException('Za długie dane do szyfrowania.');
    $lHash = hash('sha256', '', true);
    $db = $lHash . str_repeat("\0", $k - $mLen - 2 * $hLen - 2) . "\x01" . $data;
    $seed = random_bytes($hLen);
    $mgf = function (string $seed, int $len): string { $t = ''; for ($i = 0; strlen($t) < $len; $i++) $t .= hash('sha256', $seed . pack('N', $i), true); return substr($t, 0, $len); };
    $maskedDb = $db ^ $mgf($seed, $k - $hLen - 1);
    $maskedSeed = $seed ^ $mgf($maskedDb, $hLen);
    $em = "\0" . $maskedSeed . $maskedDb;
    if (!openssl_public_encrypt($em, $out, $pub, OPENSSL_NO_PADDING)) throw new RuntimeException('Szyfrowanie RSA nie powiodło się.');
    return $out;
}

// ---------- API ----------
function ksef_http(string $method, string $path, ?array $body = null, ?string $bearer = null): array {
    $ch = curl_init(ksef_env()['api'] . $path);
    $h = ['Accept: application/json'];
    if ($body !== null) $h[] = 'Content-Type: application/json';
    if ($bearer) $h[] = 'Authorization: Bearer ' . $bearer;
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => $h]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($res === false) throw new RuntimeException('Brak połączenia z KSeF: ' . $err);
    $j = json_decode((string)$res, true);
    if ($code >= 400) throw new RuntimeException('KSeF odpowiedział błędem ' . $code . ': ' . ksef_err_text($j, (string)$res));
    return is_array($j) ? $j : [];
}
function ksef_err_text($j, string $raw): string {
    if (is_array($j)) {
        $ex = $j['exception']['exceptionDetailList'] ?? null;
        if (is_array($ex)) return implode('; ', array_map(fn($e) => trim(($e['exceptionDescription'] ?? '') . ' ' . implode(' ', (array)($e['details'] ?? []))), $ex));
        if (isset($j['title'])) return (string)$j['title'] . (isset($j['detail']) ? ': ' . $j['detail'] : '');
        if (isset($j['status']['description'])) return $j['status']['description'] . ' ' . implode(' ', (array)($j['status']['details'] ?? []));
    }
    return mb_substr(trim($raw), 0, 300);
}

/** Certyfikaty kluczy publicznych MF, z pamięcią na 12 godzin. */
function ksef_public_cert(string $usage): string {
    $cacheKey = 'ksef_pk_' . setting('ksef_env');
    $c = json_decode(setting($cacheKey), true);
    if (!is_array($c) || ($c['at'] ?? 0) < time() - 43200) {
        $list = ksef_http('GET', '/security/public-key-certificates');
        $c = ['at' => time(), 'list' => $list];
        set_setting($cacheKey, json_encode($c));
    }
    $now = gmdate('Y-m-d\TH:i:s');
    foreach ($c['list'] as $p) if (in_array($usage, (array)$p['usage'], true) && substr((string)$p['validFrom'], 0, 19) <= $now && substr((string)$p['validTo'], 0, 19) >= $now) return (string)$p['certificate'];
    throw new RuntimeException('KSeF nie udostępnił klucza publicznego do: ' . $usage);
}

/** Token dostępowy (accessToken). Odnawia się sam: refresh, a w razie potrzeby pełne uwierzytelnienie tokenem KSeF. */
function ksef_access_token(bool $fresh = false): string {
    $env = setting('ksef_env');
    $st = json_decode(setting('ksef_session'), true) ?: [];
    if (!$fresh && ($st['env'] ?? '') === $env && !empty($st['access']) && strtotime((string)$st['access_until']) > time() + 60) return $st['access'];
    if (!$fresh && ($st['env'] ?? '') === $env && !empty($st['refresh']) && strtotime((string)$st['refresh_until']) > time() + 60) {
        try {
            $r = ksef_http('POST', '/auth/token/refresh', null, $st['refresh']);
            $st['access'] = $r['accessToken']['token']; $st['access_until'] = $r['accessToken']['validUntil'];
            set_setting('ksef_session', json_encode($st));
            return $st['access'];
        } catch (Throwable $e) { /* pełne logowanie poniżej */ }
    }
    $token = setting('ksef_token');
    if ($token === '') throw new RuntimeException('Wpisz token KSeF w ustawieniach.');
    $nip = nip_digits(setting('seller_nip'));
    $ch = ksef_http('POST', '/auth/challenge');
    $enc = rsa_oaep_sha256($token . '|' . $ch['timestampMs'], ksef_public_cert('KsefTokenEncryption'));
    $init = ksef_http('POST', '/auth/ksef-token', ['challenge' => $ch['challenge'], 'contextIdentifier' => ['type' => 'Nip', 'value' => $nip], 'encryptedToken' => base64_encode($enc)]);
    $op = $init['authenticationToken']['token'];
    for ($i = 0; $i < 20; $i++) {
        $s = ksef_http('GET', '/auth/' . rawurlencode($init['referenceNumber']), null, $op);
        $code = (int)($s['status']['code'] ?? 0);
        if ($code === 200) break;
        if ($code >= 400) throw new RuntimeException('KSeF nie przyjął tokena: ' . ($s['status']['description'] ?? '') . ' ' . implode(' ', (array)($s['status']['details'] ?? [])));
        usleep(700000);
    }
    if (($code ?? 0) !== 200) throw new RuntimeException('KSeF nie potwierdził logowania w czasie. Spróbuj za chwilę.');
    $t = ksef_http('POST', '/auth/token/redeem', null, $op);
    $st = ['env' => $env, 'access' => $t['accessToken']['token'], 'access_until' => $t['accessToken']['validUntil'], 'refresh' => $t['refreshToken']['token'], 'refresh_until' => $t['refreshToken']['validUntil']];
    set_setting('ksef_session', json_encode($st));
    return $st['access'];
}

/** Sprawdzenie połączenia: logowanie tokenem. */
function ksef_check(): string {
    ksef_access_token(true);
    return 'Połączenie z KSeF (' . ksef_env()['name'] . ') działa. Token jest poprawny.';
}

/**
 * Wysyła fakturę do KSeF w sesji interaktywnej i czeka na numer KSeF (do ok. 30 s).
 * Zwraca ['state' => accepted|pending|rejected, 'ksef' => numer|null, 'msg' => opis].
 */
function ksef_send(array $inv): array {
    $xml = ksef_xml($inv);
    $access = ksef_access_token();
    $key = random_bytes(32); $iv = random_bytes(16);
    $sym = ksef_public_cert('SymmetricKeyEncryption');
    $sess = ksef_http('POST', '/sessions/online', ['formCode' => ['systemCode' => 'FA (3)', 'schemaVersion' => '1-0E', 'value' => 'FA'], 'encryption' => ['encryptedSymmetricKey' => base64_encode(rsa_oaep_sha256($key, $sym)), 'initializationVector' => base64_encode($iv)]], $access);
    $ref = $sess['referenceNumber'];
    $enc = openssl_encrypt($xml, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    $hash = base64_encode(hash('sha256', $xml, true));
    $sent = ksef_http('POST', '/sessions/online/' . rawurlencode($ref) . '/invoices', [
        'invoiceHash' => $hash, 'invoiceSize' => strlen($xml),
        'encryptedInvoiceHash' => base64_encode(hash('sha256', $enc, true)), 'encryptedInvoiceSize' => strlen($enc),
        'encryptedInvoiceContent' => base64_encode($enc),
    ], $access);
    $iref = $sent['referenceNumber'];
    db()->prepare('UPDATE invoices SET ksef_state = ?, ksef_session = ?, ksef_ref = ?, ksef_hash = ?, ksef_env = ?, ksef_error = NULL, ksef_at = ?, updated = ? WHERE id = ?')
        ->execute(['pending', $ref, $iref, $hash, setting('ksef_env'), now(), now(), $inv['id']]);
    file_put_contents(CV_DATA . '/ksef-' . (int)$inv['id'] . '.xml', $xml);
    $r = ksef_poll((int)$inv['id'], 40);
    try { ksef_http('POST', '/sessions/online/' . rawurlencode($ref) . '/close', null, $access); } catch (Throwable $e) { /* sesja zamknie się sama po 12 h */ }
    return $r;
}

/** Sprawdza stan wysłanej faktury i zapisuje wynik. */
function ksef_poll(int $id, int $tries = 1): array {
    $row = db()->prepare('SELECT * FROM invoices WHERE id = ?'); $row->execute([$id]); $inv = $row->fetch();
    if (!$inv || empty($inv['ksef_ref'])) return ['state' => 'none', 'ksef' => null, 'msg' => 'Faktura nie była wysyłana do KSeF.'];
    $access = ksef_access_token();
    for ($i = 0; $i < $tries; $i++) {
        $s = ksef_http('GET', '/sessions/' . rawurlencode((string)$inv['ksef_session']) . '/invoices/' . rawurlencode((string)$inv['ksef_ref']), null, $access);
        $code = (int)($s['status']['code'] ?? 0);
        if ($code === 200 && !empty($s['ksefNumber'])) {
            db()->prepare("UPDATE invoices SET ksef = ?, ksef_state = 'accepted', ksef_error = NULL, updated = ? WHERE id = ?")->execute([$s['ksefNumber'], now(), $id]);
            log_event($id, 'ksef', 'KSeF przyjął fakturę, numer ' . $s['ksefNumber']);
            return ['state' => 'accepted', 'ksef' => $s['ksefNumber'], 'msg' => 'KSeF przyjął fakturę. Numer KSeF: ' . $s['ksefNumber']];
        }
        if ($code >= 400) {
            $msg = trim(($s['status']['description'] ?? 'Odrzucona') . ' ' . implode(' ', (array)($s['status']['details'] ?? [])));
            db()->prepare("UPDATE invoices SET ksef_state = 'rejected', ksef_error = ?, updated = ? WHERE id = ?")->execute([$msg, now(), $id]);
            log_event($id, 'error', 'KSeF odrzucił fakturę: ' . $msg);
            return ['state' => 'rejected', 'ksef' => null, 'msg' => 'KSeF odrzucił fakturę: ' . $msg];
        }
        if ($i < $tries - 1) usleep(800000);
    }
    return ['state' => 'pending', 'ksef' => null, 'msg' => 'KSeF przetwarza fakturę. Numer pojawi się za chwilę, sprawdzimy go sami.'];
}

/** Sprawdza faktury czekające na numer KSeF (z crona i przy otwarciu listy). */
function ksef_poll_pending(): void {
    if (!ksef_enabled()) return;
    foreach (db()->query("SELECT id FROM invoices WHERE ksef_state = 'pending' AND ksef_at > datetime('now', '-3 days')")->fetchAll() as $r) {
        try { ksef_poll((int)$r['id']); } catch (Throwable $e) { /* następnym razem */ }
    }
}

// ---------- kod QR (KOD I) ----------
function ksef_qr_url(array $inv): ?string {
    if (empty($inv['ksef_hash'])) return null;
    $env = KSEF_ENV[(string)($inv['ksef_env'] ?? '')] ?? ksef_env();
    $h = rtrim(strtr((string)$inv['ksef_hash'], '+/', '-_'), '=');
    return $env['qr'] . '/invoice/' . nip_digits(setting('seller_nip')) . '/' . date('d-m-Y', strtotime((string)$inv['issue_date'])) . '/' . $h;
}
function qr_png_datauri(string $text, int $scale = 5): string {
    static $loaded = false;
    if (!$loaded) {
        spl_autoload_register(function (string $c): void {
            foreach (['chillerlan\\QRCode\\' => 'qrcode/', 'chillerlan\\Settings\\' => 'settings-container/'] as $ns => $d)
                if (strncmp($c, $ns, strlen($ns)) === 0) { $f = __DIR__ . '/../vendor/' . $d . str_replace('\\', '/', substr($c, strlen($ns))) . '.php'; if (is_file($f)) require $f; return; }
        });
        $loaded = true;
    }
    $o = new \chillerlan\QRCode\QROptions(['outputType' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG, 'scale' => $scale, 'outputBase64' => true, 'quietzoneSize' => 1]);
    return (new \chillerlan\QRCode\QRCode($o))->render($text);
}
