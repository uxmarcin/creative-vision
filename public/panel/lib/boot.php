<?php
// Panel faktur Creative Vision: wspólny start (baza, sesja, zabezpieczenia, pomocnicze).
declare(strict_types=1);

date_default_timezone_set('Europe/Warsaw');
mb_internal_encoding('UTF-8');

const CV_ROOT = __DIR__ . '/..';
const CV_DATA = __DIR__ . '/../data';
const CV_SITE = 'https://creative-vision.pl';

if (!is_dir(CV_DATA)) { @mkdir(CV_DATA, 0700, true); }

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . CV_DATA . '/panel.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 4000;');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, email TEXT UNIQUE NOT NULL, pass TEXT NOT NULL, created TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS attempts (ip TEXT NOT NULL, at INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS clients (
        id INTEGER PRIMARY KEY, nip TEXT, name TEXT NOT NULL, addr1 TEXT, addr2 TEXT, email TEXT, cc TEXT,
        created TEXT NOT NULL, updated TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY, number TEXT, seq INTEGER, ym TEXT,
        client_id INTEGER REFERENCES clients(id), buyer TEXT NOT NULL,
        issue_date TEXT, sale_date TEXT, due_date TEXT, term_days INTEGER, place TEXT,
        items TEXT NOT NULL, net INTEGER NOT NULL DEFAULT 0, vat INTEGER NOT NULL DEFAULT 0, gross INTEGER NOT NULL DEFAULT 0,
        notes TEXT, status TEXT NOT NULL DEFAULT "draft", token TEXT UNIQUE,
        sent_at TEXT, viewed_at TEXT, declared_at TEXT, paid_at TEXT, paid_amount INTEGER, paid_method TEXT,
        ksef TEXT, reminders INTEGER NOT NULL DEFAULT 1, created TEXT NOT NULL, updated TEXT NOT NULL)');
    $cols = array_column($pdo->query('PRAGMA table_info(invoices)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach (['proforma' => 'INTEGER NOT NULL DEFAULT 0', 'currency' => "TEXT NOT NULL DEFAULT 'PLN'", 'lang' => "TEXT NOT NULL DEFAULT 'pl'", 'recurring' => 'INTEGER NOT NULL DEFAULT 0', 'source' => 'TEXT', 'ksef_state' => 'TEXT', 'ksef_session' => 'TEXT', 'ksef_ref' => 'TEXT', 'ksef_hash' => 'TEXT', 'ksef_error' => 'TEXT', 'ksef_at' => 'TEXT', 'ksef_env' => 'TEXT', 'opened_at' => 'TEXT'] as $c => $def)
        if (!in_array($c, $cols, true)) $pdo->exec("ALTER TABLE invoices ADD COLUMN $c $def");
    $ccols = array_column($pdo->query('PRAGMA table_info(clients)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('country', $ccols, true)) $pdo->exec('ALTER TABLE clients ADD COLUMN country TEXT');
    if (!in_array('phone', $ccols, true)) $pdo->exec('ALTER TABLE clients ADD COLUMN phone TEXT');
    if (!in_array('no_remind', $ccols, true)) {
        $pdo->exec('ALTER TABLE clients ADD COLUMN no_remind INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('ALTER TABLE clients ADD COLUMN auto_paid INTEGER NOT NULL DEFAULT 0');
        // Klient z cykliczną fakturą w walucie obcej płaci sam ostatniego dnia miesiąca: bez przypomnień, wpłata oznaczana automatycznie.
        $pdo->exec("UPDATE clients SET no_remind = 1, auto_paid = 1 WHERE id IN (SELECT client_id FROM invoices WHERE currency != 'PLN' AND client_id IS NOT NULL)");
        $pdo->exec("UPDATE invoices SET reminders = 0 WHERE client_id IN (SELECT id FROM clients WHERE no_remind = 1)");
    }
    $qc = array_column($pdo->query('PRAGMA table_info(quotes)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if ($qc && !in_array('message', $qc, true)) $pdo->exec('ALTER TABLE quotes ADD COLUMN message TEXT');
    if (!in_array('no_mail', $ccols, true)) {
        $pdo->exec('ALTER TABLE clients ADD COLUMN no_mail INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('UPDATE clients SET no_mail = 1 WHERE auto_paid = 1');
    }
    if (!in_array('no_ksef', $ccols, true)) {
        $pdo->exec('ALTER TABLE clients ADD COLUMN no_ksef INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('ALTER TABLE clients ADD COLUMN tpl TEXT');
        // IngeniousIO: faktury nie idą do KSeF, a nowa stawka to 4250 USD
        $pdo->exec("UPDATE clients SET no_ksef = 1 WHERE name LIKE '%ngenious%'");
        $pdo->exec("UPDATE invoices SET ksef_state = 'skip' WHERE (ksef IS NULL OR ksef = '') AND client_id IN (SELECT id FROM clients WHERE no_ksef = 1)");
        foreach ($pdo->query("SELECT id FROM clients WHERE name LIKE '%ngenious%'")->fetchAll() as $c) {
            $l = $pdo->prepare("SELECT * FROM invoices WHERE client_id = ? AND status != 'draft' ORDER BY id DESC LIMIT 1"); $l->execute([$c['id']]); $l = $l->fetch();
            if (!$l) continue;
            $items = json_decode((string)$l['items'], true) ?: [];
            foreach ($items as &$it) { $it['price'] = 425000; } unset($it);
            if (count($items) > 1) $items = [$items[0]];
            $pdo->prepare('UPDATE clients SET tpl = ? WHERE id = ?')->execute([json_encode(['items' => $items, 'currency' => $l['currency'] ?? 'PLN', 'lang' => $l['lang'] ?? 'pl', 'term_days' => (int)$l['term_days'], 'notes' => (string)$l['notes']], JSON_UNESCAPED_UNICODE), $c['id']]);
        }
    }
    if (!in_array('book_ym', $cols, true)) {
        $pdo->exec('ALTER TABLE invoices ADD COLUMN book_ym TEXT');
        $pdo->exec('UPDATE invoices SET book_ym = substr(COALESCE(issue_date, created), 1, 7)');
    }
    // Wezwanie do zapłaty 14 dni po terminie, ostateczne po kolejnych 7 dniach na wpłatę.
    if ($pdo->query("SELECT v FROM settings WHERE k = 'mig_w1_14'")->fetchColumn() !== '1') {
        $pdo->exec("UPDATE settings SET v = '14' WHERE k = 'r_w1_days' AND CAST(v AS INTEGER) < 14");
        $pdo->exec("UPDATE settings SET v = '21' WHERE k = 'r_w2_days' AND CAST(v AS INTEGER) < 21");
        $pdo->exec("INSERT OR REPLACE INTO settings (k, v) VALUES ('mig_w1_14', '1')");
    }
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS inv_number ON invoices(number) WHERE number IS NOT NULL');
    $pdo->exec('CREATE TABLE IF NOT EXISTS quotes (
        id INTEGER PRIMARY KEY, number TEXT UNIQUE, seq INTEGER, ym TEXT, client_id INTEGER, buyer TEXT NOT NULL,
        issue_date TEXT, due_date TEXT, term_days INTEGER, place TEXT, items TEXT NOT NULL,
        net INTEGER NOT NULL DEFAULT 0, vat INTEGER NOT NULL DEFAULT 0, gross INTEGER NOT NULL DEFAULT 0,
        currency TEXT NOT NULL DEFAULT \'PLN\', lang TEXT NOT NULL DEFAULT \'pl\', notes TEXT,
        status TEXT NOT NULL DEFAULT \'draft\', invoice_id INTEGER, src TEXT, sent_at TEXT, created TEXT NOT NULL, updated TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS events (id INTEGER PRIMARY KEY, invoice_id INTEGER NOT NULL, kind TEXT NOT NULL, msg TEXT, at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS reminders (invoice_id INTEGER NOT NULL, kind TEXT NOT NULL, sent_at TEXT NOT NULL, PRIMARY KEY (invoice_id, kind))');
}

// ---------- ustawienia ----------
const DEFAULTS = [
    'seller_name' => 'Creative Vision',
    'seller_addr1' => 'ul. Pańska 13A/1',
    'seller_addr2' => '35-213 Rzeszów',
    'seller_nip' => '795-251-42-99',
    'seller_email' => 'kontakt@creative-vision.pl',
    'seller_phone' => '534 154 230',
    'bank_name' => '',
    'bank_swift' => '',
    'imap_host' => 's65.cyber-folks.pl',
    'imap_port' => '993',
    'smtp_host' => 's65.cyber-folks.pl',
    'smtp_port' => '465',
    'imap_user' => 'faktury@creative-vision.pl',
    'imap_pass' => '',
    'imap_sent' => '',
    'smtp_on' => '1',
    'notify_open' => '1',
    'sms_on' => '0', 'sms_token' => '', 'sms_from' => '', 'sms_api' => 'api.smsapi.pl',
    'sms_before' => '0', 'sms_due' => '1', 'sms_w1' => '1', 'sms_w2' => '1', 'sms_manual' => '1',
    'ksef_env' => 'prod',
    'ksef_token' => '',
    'ksef_auto' => '1',
    'zw_basis' => '',
    'bank_account' => '',
    'place' => 'Rzeszów',
    'term_days' => '7',
    'next_seq' => '',
    'mail_from' => 'kontakt@creative-vision.pl',
    'mail_reply' => 'kontakt@creative-vision.pl',
    'mail_copy' => '1',
    'r_before_on' => '1', 'r_before_days' => '3',
    'r_due_on' => '1',
    'r_w1_on' => '1', 'r_w1_days' => '14',
    'r_w2_on' => '1', 'r_w2_days' => '21',
    'interest_rate' => '13,75',
    'footer_note' => 'Dziękujemy za współpracę.',
    'k_imap_user' => 'kontakt@creative-vision.pl',
    'quote_days' => '14',
    'acc_auto' => '1', 'acc_day' => '5',
];

/** Skrzynki pocztowe: f = faktury@, k = kontakt@. Ustawienia skrzynki k mają przedrostek k_. */
const ACC_KEYS = ['imap_user', 'imap_pass', 'imap_sent', 'imap_trash', 'imap_unseen', 'imap_unseen_at', 'imap_append_err'];
function mail_acc(?string $a = null): string { static $cur = 'f'; if ($a !== null) $cur = $a === 'k' ? 'k' : 'f'; return $cur; }
function acc_key(string $k): string { return mail_acc() === 'k' && in_array($k, ACC_KEYS, true) ? 'k_' . $k : $k; }
function &settings_cache(): array {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $plain = [];
        foreach (db()->query('SELECT k, v FROM settings') as $r) { $v = (string)$r['v']; if (str_starts_with($v, 'enc1:') || str_starts_with($v, 'enc2:')) $v = sec_dec($v); elseif ($v !== '' && is_secret_key((string)$r['k'])) $plain[] = $r['k']; $cache[$r['k']] = $v; }
        // sekrety zapisane wcześniej jawnym tekstem: szyfrujemy przy pierwszym odczycie
        foreach ($plain as $k) { try { $e = sec_enc($cache[$k]); if ($e !== '' && sec_dec($e) === $cache[$k]) db()->prepare('UPDATE settings SET v = ? WHERE k = ?')->execute([$e, $k]); } catch (Throwable $x) {} }
    }
    return $cache;
}
// ---------- szyfrowanie sekretów w bazie (hasła poczty, tokeny KSeF, SMS, klucze API, 2FA) ----------
function is_secret_key(string $k): bool { return (bool)preg_match('/^(k_)?(imap_pass|ksef_token|ksef_session|ai_key|sms_token|psi_key|vapid_priv|totp_\d+|totp_backup_\d+)$/', $k); }
/** Klucz szyfrowania trzymany poza katalogiem strony (poza public_html), a gdy się nie da, w chronionym katalogu data. */
function sec_key(): string {
    static $k = null; if ($k !== null) return $k;
    $root = realpath(CV_ROOT) ?: CV_ROOT; $paths = [dirname($root, 2) . '/.cvpanel-key', $root . '/data/.cvpanel-key'];
    foreach ($paths as $p) { $v = @file_get_contents($p); if (is_string($v) && strlen($v) >= 64) return $k = hex2bin(trim($v)) ?: ''; }
    $new = bin2hex(random_bytes(32));
    foreach ($paths as $p) { if (@file_put_contents($p, $new, LOCK_EX) === strlen($new)) { @chmod($p, 0600); return $k = hex2bin($new); } }
    return $k = '';
}
function sec_enc(string $v): string {
    $key = sec_key(); if ($key === '' || $v === '') return '';
    if (function_exists('sodium_crypto_secretbox')) { $n = random_bytes(24); return 'enc1:' . base64_encode($n . sodium_crypto_secretbox($v, $n, $key)); }
    $n = random_bytes(12); $c = openssl_encrypt($v, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $n, $tag);
    return $c === false ? '' : 'enc2:' . base64_encode($n . $tag . $c);
}
function sec_dec(string $v): string {
    $key = sec_key(); $raw = base64_decode(substr($v, 5), true); if ($key === '' || $raw === false) return '';
    if (str_starts_with($v, 'enc1:')) { if (!function_exists('sodium_crypto_secretbox_open') || strlen($raw) < 40) return ''; $p = sodium_crypto_secretbox_open(substr($raw, 24), substr($raw, 0, 24), $key); return $p === false ? '' : $p; }
    if (strlen($raw) < 29) return '';
    $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $p === false ? '' : $p;
}
function setting(string $k): string {
    $k = acc_key($k); $c = &settings_cache();
    return $c[$k] ?? (DEFAULTS[$k] ?? '');
}
function set_setting(string $k, string $v): void {
    $k = acc_key($k);
    $store = $v !== '' && is_secret_key($k) ? sec_enc($v) : $v;
    if ($v !== '' && $store === '') throw new RuntimeException('Nie mogę bezpiecznie zapisać sekretu (brak klucza szyfrowania).');
    db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v')->execute([$k, $store]);
    $c = &settings_cache(); $c[$k] = $v;
}

// ---------- sesja i logowanie ----------
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('cvpanel');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/panel', 'secure' => !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'), 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.save_path', CV_DATA);
    ini_set('session.gc_maxlifetime', '2592000');
    session_start();
    $limit = !empty($_SESSION['remember']) ? 2592000 : 43200;
    if (isset($_SESSION['uid'], $_SESSION['seen']) && time() - $_SESSION['seen'] > $limit) { $_SESSION = []; session_regenerate_id(true); }
    $_SESSION['seen'] = time();
    if (!empty($_SESSION['remember'])) remember_cookie();
}
/** Sesja na 30 dni od ostatniego użycia (aplikacja na telefonie nie wylogowuje po zamknięciu). */
function remember_session(): void { $_SESSION['remember'] = 1; remember_cookie(); }
function remember_cookie(): void {
    setcookie(session_name(), session_id(), ['expires' => time() + 2592000, 'path' => '/panel', 'secure' => !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'), 'httponly' => true, 'samesite' => 'Lax']);
}
function user_id(): ?int { return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null; }
function has_users(): bool { return (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0; }
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64); }
function too_many_attempts(): bool {
    $pdo = db();
    $pdo->prepare('DELETE FROM attempts WHERE at < ?')->execute([time() - 900]);
    $s = $pdo->prepare('SELECT COUNT(*) FROM attempts WHERE ip = ?'); $s->execute([client_ip()]);
    return (int)$s->fetchColumn() >= 6;
}
function note_attempt(): void { db()->prepare('INSERT INTO attempts (ip, at) VALUES (?, ?)')->execute([client_ip(), time()]); }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Sesja wygasła. Wróć i spróbuj ponownie.'); }
}

function security_headers(): void {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

// ---------- pomocnicze ----------
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now(): string { return date('Y-m-d H:i:s'); }
function today(): string { return date('Y-m-d'); }
function redirect(string $to): void { header('Location: ' . $to, true, 303); exit; }
function flash(?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}

/** "1 234,56" → grosze */
function to_cents(string $v): int {
    $v = str_replace(["\xc2\xa0", ' ', 'zł'], '', trim($v));
    $v = str_replace(',', '.', $v);
    if ($v === '' || !is_numeric($v)) return 0;
    return (int)round(((float)$v) * 100);
}
/** Kwota z groszy. $cur: true = zł, false = bez waluty, kod waluty (PLN, USD, EUR). */
function money(int $c, bool|string $cur = true): string {
    $s = number_format(abs($c) / 100, 2, ',', ' ');
    $s = str_replace(' ', "\u{00A0}", $s);
    $sym = $cur === true || $cur === 'PLN' ? 'zł' : ($cur === false ? '' : $cur);
    return ($c < 0 ? '-' : '') . $s . ($sym !== '' ? "\u{00A0}" . $sym : '');
}
function currencies(): array { return ['PLN' => 'PLN (zł)', 'USD' => 'USD ($)', 'EUR' => 'EUR (€)']; }
function pl_date(?string $d): string { return $d ? date('d.m.Y', strtotime($d)) : ''; }
function days_between(string $a, string $b): int { return (int)round((strtotime($b) - strtotime($a)) / 86400); }

function vat_rates(): array { return ['23' => '23%', '8' => '8%', '5' => '5%', '0' => '0%', 'zw' => 'zw.', 'np' => 'np.']; }

/** Liczy pozycje i sumy (w groszach) według stawek. */
function calc_items(array $items): array {
    $out = []; $net = 0; $vat = 0; $by = [];
    foreach ($items as $it) {
        $name = trim((string)($it['name'] ?? ''));
        if ($name === '') continue;
        $qty = (float)str_replace(',', '.', (string)($it['qty'] ?? '1')); if ($qty <= 0) $qty = 1;
        $unit = trim((string)($it['unit'] ?? 'usł.')) ?: 'usł.';
        $price = to_cents((string)($it['price'] ?? '0'));
        $rate = (string)($it['vat'] ?? '23'); if (!isset(vat_rates()[$rate])) $rate = '23';
        $n = (int)round($price * $qty);
        $v = is_numeric($rate) ? (int)round($n * ((int)$rate) / 100) : 0;
        $out[] = ['name' => $name, 'desc' => trim((string)($it['desc'] ?? '')), 'qty' => $qty, 'unit' => $unit, 'price' => $price, 'vat' => $rate, 'net' => $n, 'vat_amount' => $v, 'gross' => $n + $v];
        $net += $n; $vat += $v;
        $by[$rate] = ($by[$rate] ?? 0) + $v;
    }
    return ['items' => $out, 'net' => $net, 'vat' => $vat, 'gross' => $net + $vat, 'by_rate' => $by];
}
function qty_fmt(float $q): string { return rtrim(rtrim(number_format($q, 2, ',', ''), '0'), ','); }

/** Kwota słownie po polsku, np. "dwanaście tysięcy sto pięćdziesiąt dwa złote 40/100". */
function slownie(int $cents, string $currency = 'PLN'): string {
    $zl = intdiv($cents, 100); $gr = $cents % 100;
    $j = ['', 'jeden', 'dwa', 'trzy', 'cztery', 'pięć', 'sześć', 'siedem', 'osiem', 'dziewięć'];
    $n = ['dziesięć', 'jedenaście', 'dwanaście', 'trzynaście', 'czternaście', 'piętnaście', 'szesnaście', 'siedemnaście', 'osiemnaście', 'dziewiętnaście'];
    $d = ['', '', 'dwadzieścia', 'trzydzieści', 'czterdzieści', 'pięćdziesiąt', 'sześćdziesiąt', 'siedemdziesiąt', 'osiemdziesiąt', 'dziewięćdziesiąt'];
    $s = ['', 'sto', 'dwieście', 'trzysta', 'czterysta', 'pięćset', 'sześćset', 'siedemset', 'osiemset', 'dziewięćset'];
    $forms = [['', '', ''], ['tysiąc', 'tysiące', 'tysięcy'], ['milion', 'miliony', 'milionów']];
    $form = function (int $x, array $f): string {
        if ($x === 1) return $f[0];
        $u = $x % 10; $t = $x % 100;
        return ($u >= 2 && $u <= 4 && !($t >= 12 && $t <= 14)) ? $f[1] : $f[2];
    };
    $three = function (int $x) use ($j, $n, $d, $s): string {
        $w = [];
        if ($x >= 100) $w[] = $s[intdiv($x, 100)];
        $r = $x % 100;
        if ($r >= 10 && $r < 20) $w[] = $n[$r - 10];
        else { if ($r >= 20) $w[] = $d[intdiv($r, 10)]; if ($r % 10) $w[] = $j[$r % 10]; }
        return implode(' ', array_filter($w));
    };
    if ($zl === 0) $words = 'zero';
    else {
        $parts = []; $i = 0; $x = $zl;
        while ($x > 0 && $i < 3) {
            $g = $x % 1000;
            if ($g) {
                $txt = ($i > 0 && $g === 1) ? '' : $three($g);
                $parts[] = trim($txt . ' ' . ($i ? $form($g, $forms[$i]) : ''));
            }
            $x = intdiv($x, 1000); $i++;
        }
        $words = implode(' ', array_reverse($parts));
    }
    $u = $zl % 10; $t = $zl % 100;
    $cur = ($zl === 1) ? 'złoty' : (($u >= 2 && $u <= 4 && !($t >= 12 && $t <= 14)) ? 'złote' : 'złotych');
    if ($currency !== 'PLN') return $words . ' ' . $currency . ' ' . sprintf('%02d', $gr) . '/100';
    return $words . ' ' . $cur . ' ' . sprintf('%02d', $gr) . '/100';
}

/** Kwota słownie po angielsku, np. "three thousand five hundred USD 00/100". */
function words_en(int $cents, string $currency): string {
    $n = intdiv($cents, 100); $gr = $cents % 100;
    $o = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    $t = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    $three = function (int $x) use ($o, $t): string {
        $w = [];
        if ($x >= 100) $w[] = $o[intdiv($x, 100)] . ' hundred';
        $r = $x % 100;
        if ($r >= 20) $w[] = $t[intdiv($r, 10)] . ($r % 10 ? '-' . $o[$r % 10] : '');
        elseif ($r > 0) $w[] = $o[$r];
        return implode(' ', $w);
    };
    if ($n === 0) $words = 'zero';
    else {
        $parts = []; $sc = ['', ' thousand', ' million']; $i = 0;
        while ($n > 0 && $i < 3) { $g = $n % 1000; if ($g) array_unshift($parts, $three($g) . $sc[$i]); $n = intdiv($n, 1000); $i++; }
        $words = implode(' ', $parts);
    }
    return $words . ' ' . $currency . ' ' . sprintf('%02d', $gr) . '/100';
}

/** SWIFT/BIC: 8 albo 11 znaków (np. INGBPLPW). Inna wartość (np. numer konta) jest pomijana. */
function bank_swift(): string { $v = strtoupper(preg_replace('/\s+/', '', setting('bank_swift')) ?? ''); return preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $v) ? $v : ''; }
function nip_digits(string $nip): string { return preg_replace('/\D/', '', $nip) ?? ''; }
function nrb_digits(string $a): string { return preg_replace('/\D/', '', $a) ?? ''; }
function nrb_fmt(string $a): string {
    $d = nrb_digits($a);
    if (strlen($d) !== 26) return $a;
    return substr($d, 0, 2) . ' ' . implode(' ', str_split(substr($d, 2), 4));
}

function seller(): array {
    $k = ['seller_name', 'seller_addr1', 'seller_addr2', 'seller_nip', 'seller_email', 'seller_phone', 'bank_name', 'bank_account'];
    $o = []; foreach ($k as $x) $o[$x] = setting($x); return $o;
}

function invoice_by_id(int $id): ?array {
    $s = db()->prepare('SELECT * FROM invoices WHERE id = ?'); $s->execute([$id]);
    $r = $s->fetch(); return $r ? hydrate($r) : null;
}
function invoice_by_token(string $t): ?array {
    if (!preg_match('/^[A-Za-z0-9]{24,64}$/', $t)) return null;
    $s = db()->prepare("SELECT * FROM invoices WHERE token = ? AND status != 'draft'"); $s->execute([$t]);
    $r = $s->fetch(); return $r ? hydrate($r) : null;
}
function hydrate(array $r): array {
    $r['buyer'] = json_decode($r['buyer'] ?: '{}', true) ?: [];
    $r['items'] = json_decode($r['items'] ?: '[]', true) ?: [];
    $r['calc'] = calc_items(array_map(fn($i) => ['name' => $i['name'], 'desc' => $i['desc'] ?? '', 'qty' => $i['qty'], 'unit' => $i['unit'], 'price' => money((int)$i['price'], false), 'vat' => $i['vat']], $r['items']));
    return $r;
}
/** Wersja panelu: zmienia się przy każdym wdrożeniu. */
function app_ver(): string {
    $h = '';
    foreach (array_merge([__DIR__ . '/../index.php', __DIR__ . '/../assets/panel.css', __DIR__ . '/../assets/panel.js'], glob(__DIR__ . '/*.php') ?: []) as $f) $h .= $f . @filesize($f) . @md5_file($f);
    return substr(md5($h), 0, 12);
}
function client_no_ksef(?int $cid): bool { if (!$cid) return false; $q = db()->prepare('SELECT no_ksef FROM clients WHERE id = ?'); $q->execute([$cid]); return (int)$q->fetchColumn() === 1; }
/** Zapamiętuje ostatnią fakturę klienta jako wzór do kolejnej (pozycje, waluta, język, termin, uwagi). */
function client_tpl_save(array $inv): void { if (empty($inv['client_id'])) return; db()->prepare('UPDATE clients SET tpl = ? WHERE id = ?')->execute([json_encode(['items' => is_array($inv['items']) ? $inv['items'] : (json_decode((string)$inv['items'], true) ?: []), 'currency' => $inv['currency'] ?? 'PLN', 'lang' => $inv['lang'] ?? 'pl', 'term_days' => (int)($inv['term_days'] ?? 14), 'notes' => (string)($inv['notes'] ?? '')], JSON_UNESCAPED_UNICODE), (int)$inv['client_id']]); }
function client_no_mail(?int $cid): bool { if (!$cid) return false; $q = db()->prepare('SELECT no_mail FROM clients WHERE id = ?'); $q->execute([$cid]); return (int)$q->fetchColumn() === 1; }
function quote_by_id(int $id): ?array {
    $s = db()->prepare('SELECT * FROM quotes WHERE id = ?'); $s->execute([$id]);
    $r = $s->fetch(); if (!$r) return null;
    $r = hydrate($r); $r['doc'] = 'quote'; $r['src'] = json_decode((string)($r['src'] ?? ''), true) ?: [];
    return $r;
}
function quote_state(array $q): string {
    if (in_array($q['status'], ['draft', 'sent'], true) && !empty($q['due_date']) && $q['due_date'] < today()) return 'expired';
    return $q['status'];
}
function log_event(int $id, string $kind, string $msg): void {
    db()->prepare('INSERT INTO events (invoice_id, kind, msg, at) VALUES (?, ?, ?, ?)')->execute([$id, $kind, $msg, now()]);
}
function pay_url(array $inv): string { return CV_SITE . '/f/' . $inv['token']; }

/** Stan faktury do wyświetlania: due / soon / today / late / paid / draft */
function inv_state(array $inv): string {
    if ($inv['status'] === 'draft') return !empty($inv['proforma']) ? 'proforma' : 'draft';
    if ($inv['status'] === 'paid') return 'paid';
    if ($inv['status'] === 'cancelled') return 'cancelled';
    $d = days_between(today(), (string)$inv['due_date']);
    if ($d < 0) return 'late';
    if ($d === 0) return 'today';
    return 'due';
}

/** Harmonogram automatycznych wiadomości dla faktury: [kind => data]. */
function reminder_plan(array $inv): array {
    $due = (string)$inv['due_date']; $p = [];
    $add = fn(string $d, int $n) => date('Y-m-d', strtotime($d . ' ' . ($n >= 0 ? '+' : '') . $n . ' days'));
    if (setting('r_before_on') === '1') $p['before'] = $add($due, -max(1, (int)setting('r_before_days')));
    if (setting('r_due_on') === '1') $p['due'] = $due;
    if (setting('r_w1_on') === '1') $p['w1'] = $add($due, max(1, (int)setting('r_w1_days')));
    if (setting('r_w2_on') === '1') $p['w2'] = $add($due, max(2, (int)setting('r_w2_days')));
    return $p;
}
function reminder_label(string $k): string {
    return ['new' => 'Mail z fakturą', 'before' => 'Przypomnienie przed terminem', 'due' => 'Termin mija dziś', 'w1' => 'Wezwanie do zapłaty', 'w2' => 'Ostateczne wezwanie do zapłaty', 'manual' => 'Przypomnienie wysłane ręcznie'][$k] ?? $k;
}

/** Odsetki ustawowe za opóźnienie w transakcjach handlowych (proste, dziennie), w groszach. */
function interest(int $cents, string $due, string $on): int {
    $days = days_between($due, $on); if ($days <= 0) return 0;
    $rate = (float)str_replace(',', '.', setting('interest_rate'));
    return (int)round($cents * $rate / 100 * $days / 365);
}
/** Rekompensata za koszty odzyskiwania należności (art. 10 ustawy o przeciwdziałaniu nadmiernym opóźnieniom): 40/70/100 euro. */
function compensation_eur(int $cents): int { return $cents < 500000 ? 40 : ($cents < 5000000 ? 70 : 100); }

// ---------- kursy NBP (tabela A, średni kurs) do podglądu kwot w złotych, tylko w panelu ----------
/** Zwraca ['rate' => float, 'date' => 'Y-m-d'] albo null. Kurs pobieramy z api.nbp.pl najwyżej co 2 godziny. */
function nbp_rate(string $cur): ?array {
    $cur = strtoupper($cur); if ($cur === 'PLN' || !preg_match('/^[A-Z]{3}$/', $cur)) return null;
    static $mem = []; if (isset($mem[$cur])) return $mem[$cur];
    $c = json_decode(setting('nbp_' . $cur), true) ?: [];
    if ((empty($c['rate']) && time() - (int)($c['fail'] ?? 0) > 1800) || (!empty($c['rate']) && time() - (int)($c['at'] ?? 0) > 7200)) {
        $ch = curl_init('https://api.nbp.pl/api/exchangerates/rates/a/' . strtolower($cur) . '/?format=json');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $j = json_decode((string)curl_exec($ch), true); curl_close($ch);
        $r = $j['rates'][0] ?? null;
        if (!empty($r['mid'])) { $c = ['rate' => (float)$r['mid'], 'date' => (string)$r['effectiveDate'], 'at' => time()]; set_setting('nbp_' . $cur, json_encode($c)); }
        elseif (!empty($c['rate'])) { $c['at'] = time() - 6000; set_setting('nbp_' . $cur, json_encode($c)); }
        else set_setting('nbp_' . $cur, json_encode(['fail' => time()]));
    }
    return $mem[$cur] = (!empty($c['rate']) ? ['rate' => (float)$c['rate'], 'date' => (string)$c['date']] : null);
}
/** Kwota w groszach po kursie NBP albo null. */
function to_pln(int $cents, string $cur): ?int { $r = nbp_rate($cur); return $r ? (int)round($cents * $r['rate']) : null; }
/** Podpowiedź pod kwotą w walucie: „≈ 17 680,00 zł · kurs NBP 4,1600 z 19.11”. */
/** Kwota w zł z groszami w osobnym, jaśniejszym spanie. */
function money_split(int $c): string { $m = money($c); return preg_replace('/,(\d{2})(.*)$/u', '<span class="gr">,$1$2</span>', $m, 1); }
function pln_hint(int $cents, ?string $cur, bool $short = false): string {
    $cur = $cur ?: 'PLN'; if ($cur === 'PLN') return ''; $r = nbp_rate($cur); if (!$r) return '';
    return '<span class="sub fxp" title="Średni kurs NBP (tabela A) z ' . pl_date($r['date']) . ', tylko do podglądu w panelu">≈ ' . money((int)round($cents * $r['rate'])) . ($short ? '' : ' · NBP ' . number_format($r['rate'], 4, ',', '') . ' z ' . date('d.m', strtotime($r['date']))) . '</span>';
}
/** Historia średniego kursu NBP z ostatnich $days dni: [[data, kurs], ...]. Cache 1 h. */
function nbp_hist(string $cur, int $days): array {
    $k = 'nbpr_' . $cur . '_' . $days; $c = json_decode(setting($k), true) ?: [];
    if (!empty($c['p']) && time() - (int)($c['at'] ?? 0) < 3600) return $c['p'];
    $ch = curl_init('https://api.nbp.pl/api/exchangerates/rates/a/' . strtolower($cur) . '/' . date('Y-m-d', strtotime('-' . $days . ' days')) . '/' . date('Y-m-d') . '/?format=json');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $j = json_decode((string)curl_exec($ch), true); curl_close($ch);
    $p = array_map(fn($r) => [(string)$r['effectiveDate'], (float)$r['mid']], is_array($j['rates'] ?? null) ? $j['rates'] : []);
    if ($p) set_setting($k, json_encode(['p' => $p, 'at' => time()])); elseif (!empty($c['p'])) return $c['p'];
    return $p;
}
/** Kurs NBP z ostatniego dnia roboczego miesiąca (weekend lub święto: dzień roboczy wcześniej). Dla trwającego miesiąca kurs bieżący, 'live' => true. */
function nbp_rate_month_end(string $cur, string $ym): ?array {
    $next = date('Y-m-d', strtotime($ym . '-01 +1 month'));
    if ($next > date('Y-m-d')) { $r = nbp_rate($cur); return $r ? $r + ['live' => true] : null; }
    return nbp_rate_before($cur, $next);
}
/** Kurs NBP z ostatniego dnia roboczego przed podaną datą (zasada do księgowania faktur w walucie). ['rate', 'date', 'table'] albo null. */
function nbp_rate_before(string $cur, string $date): ?array {
    $cur = strtoupper($cur); if ($cur === 'PLN' || !preg_match('/^[A-Z]{3}$/', $cur) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
    $k = 'nbph_' . $cur . '_' . $date; $c = json_decode(setting($k), true);
    if (!empty($c['rate'])) return $c;
    $to = date('Y-m-d', strtotime($date . ' -1 day')); $from = date('Y-m-d', strtotime($date . ' -12 days'));
    $ch = curl_init('https://api.nbp.pl/api/exchangerates/rates/a/' . strtolower($cur) . '/' . $from . '/' . $to . '/?format=json');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $j = json_decode((string)curl_exec($ch), true); curl_close($ch);
    $r = is_array($j['rates'] ?? null) ? end($j['rates']) : null;
    if (empty($r['mid'])) return null;
    $c = ['rate' => (float)$r['mid'], 'date' => (string)$r['effectiveDate'], 'table' => (string)($r['no'] ?? '')];
    set_setting($k, json_encode($c)); return $c;
}

