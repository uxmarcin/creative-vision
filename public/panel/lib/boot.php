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
    foreach (['currency' => "TEXT NOT NULL DEFAULT 'PLN'", 'lang' => "TEXT NOT NULL DEFAULT 'pl'", 'recurring' => 'INTEGER NOT NULL DEFAULT 0', 'source' => 'TEXT'] as $c => $def)
        if (!in_array($c, $cols, true)) $pdo->exec("ALTER TABLE invoices ADD COLUMN $c $def");
    if (!in_array('country', array_column($pdo->query('PRAGMA table_info(clients)')->fetchAll(PDO::FETCH_ASSOC), 'name'), true)) $pdo->exec('ALTER TABLE clients ADD COLUMN country TEXT');
    if (!in_array('book_ym', $cols, true)) {
        $pdo->exec('ALTER TABLE invoices ADD COLUMN book_ym TEXT');
        $pdo->exec('UPDATE invoices SET book_ym = substr(COALESCE(issue_date, created), 1, 7)');
    }
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS inv_number ON invoices(number) WHERE number IS NOT NULL');
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
    'bank_account' => '',
    'place' => 'Rzeszów',
    'term_days' => '7',
    'next_seq' => '',
    'mail_from' => 'kontakt@creative-vision.pl',
    'mail_reply' => 'kontakt@creative-vision.pl',
    'mail_copy' => '1',
    'r_before_on' => '1', 'r_before_days' => '3',
    'r_due_on' => '1',
    'r_w1_on' => '1', 'r_w1_days' => '3',
    'r_w2_on' => '1', 'r_w2_days' => '10',
    'interest_rate' => '13,75',
    'footer_note' => 'Dziękujemy za współpracę.',
];

function setting(string $k): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT k, v FROM settings') as $r) $cache[$r['k']] = (string)$r['v'];
    }
    return $cache[$k] ?? (DEFAULTS[$k] ?? '');
}
function set_setting(string $k, string $v): void {
    db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v')->execute([$k, $v]);
}

// ---------- sesja i logowanie ----------
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('cvpanel');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/panel', 'secure' => !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'), 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.save_path', CV_DATA);
    ini_set('session.gc_maxlifetime', '43200');
    session_start();
    if (isset($_SESSION['uid'], $_SESSION['seen']) && time() - $_SESSION['seen'] > 43200) { $_SESSION = []; session_regenerate_id(true); }
    $_SESSION['seen'] = time();
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
function log_event(int $id, string $kind, string $msg): void {
    db()->prepare('INSERT INTO events (invoice_id, kind, msg, at) VALUES (?, ?, ?, ?)')->execute([$id, $kind, $msg, now()]);
}
function pay_url(array $inv): string { return CV_SITE . '/f/' . $inv['token']; }

/** Stan faktury do wyświetlania: due / soon / today / late / paid / draft */
function inv_state(array $inv): string {
    if ($inv['status'] === 'draft') return 'draft';
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
    return ['before' => 'Przypomnienie przed terminem', 'due' => 'Termin mija dziś', 'w1' => 'Wezwanie do zapłaty', 'w2' => 'Ostateczne wezwanie do zapłaty', 'manual' => 'Przypomnienie wysłane ręcznie'][$k] ?? $k;
}

/** Odsetki ustawowe za opóźnienie w transakcjach handlowych (proste, dziennie), w groszach. */
function interest(int $cents, string $due, string $on): int {
    $days = days_between($due, $on); if ($days <= 0) return 0;
    $rate = (float)str_replace(',', '.', setting('interest_rate'));
    return (int)round($cents * $rate / 100 * $days / 365);
}
/** Rekompensata za koszty odzyskiwania należności (art. 10 ustawy o przeciwdziałaniu nadmiernym opóźnieniom): 40/70/100 euro. */
function compensation_eur(int $cents): int { return $cents < 500000 ? 40 : ($cents < 5000000 ? 70 : 100); }
