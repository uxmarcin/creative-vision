<?php
// Logowanie dwuetapowe: kody z aplikacji (TOTP, RFC 6238) i jednorazowe kody zapasowe. Sekret trzymany zaszyfrowany.
declare(strict_types=1);

function b32_enc(string $b): string { $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; foreach (str_split($b) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT); $o = ''; foreach (str_split($bits, 5) as $ch) $o .= $a[bindec(str_pad($ch, 5, '0'))]; return $o; }
function b32_dec(string $s): string { $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s)); $bits = ''; foreach (str_split($s) as $c) $bits .= str_pad(decbin((int)strpos($a, $c)), 5, '0', STR_PAD_LEFT); $o = ''; foreach (str_split($bits, 8) as $ch) if (strlen($ch) === 8) $o .= chr(bindec($ch)); return $o; }
function totp_at(string $secret, int $step): string {
    $h = hash_hmac('sha1', pack('J', $step), b32_dec($secret), true); $o = ord($h[19]) & 0xf;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}
/** Sprawdza kod z aplikacji (±30 s) i nie pozwala użyć tego samego kodu drugi raz. */
function totp_check(int $uid, string $secret, string $code): bool {
    $code = preg_replace('/\D/', '', $code); if (strlen($code) !== 6) return false;
    $now = intdiv(time(), 30); $last = (int)setting('totp_last_' . $uid);
    for ($d = -1; $d <= 1; $d++) if ($now + $d > $last && hash_equals(totp_at($secret, $now + $d), $code)) { set_setting('totp_last_' . $uid, (string)($now + $d)); return true; }
    return false;
}
function tfa_on(int $uid): bool { return setting('totp_' . $uid) !== ''; }
/** Kod z aplikacji albo jeden z kodów zapasowych (każdy działa raz). */
function tfa_verify(int $uid, string $code): bool {
    if (totp_check($uid, setting('totp_' . $uid), $code)) return true;
    $c = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $code)); if (strlen($c) !== 10) return false;
    $list = array_filter(explode(',', setting('totp_backup_' . $uid)));
    foreach ($list as $i => $h) if (hash_equals($h, hash('sha256', $c))) { unset($list[$i]); set_setting('totp_backup_' . $uid, implode(',', $list)); return true; }
    return false;
}
function tfa_new_backup(int $uid): array {
    $codes = []; $hs = [];
    for ($i = 0; $i < 8; $i++) { $c = substr(bin2hex(random_bytes(5)), 0, 10); $codes[] = substr($c, 0, 5) . '-' . substr($c, 5); $hs[] = hash('sha256', $c); }
    set_setting('totp_backup_' . $uid, implode(',', $hs));
    return $codes;
}
function tfa_uri(string $email, string $secret): string { return 'otpauth://totp/' . rawurlencode('Creative Vision:' . $email) . '?secret=' . $secret . '&issuer=' . rawurlencode('Creative Vision') . '&digits=6&period=30'; }

/** Mail o logowaniu z nowego urządzenia. Urządzenie rozpoznajemy po losowym ciasteczku. */
function login_alert(int $uid): void {
    $c = (string)($_COOKIE['cvdev'] ?? ''); $known = array_filter(explode(',', setting('devices_' . $uid)));
    if ($c !== '' && in_array(hash('sha256', $c), $known, true)) return;
    $c = bin2hex(random_bytes(16));
    setcookie('cvdev', $c, ['expires' => time() + 31536000 * 2, 'path' => '/panel', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    $known[] = hash('sha256', $c); set_setting('devices_' . $uid, implode(',', array_slice($known, -20)));
    if (count($known) === 1) return; // pierwsze urządzenie, nie straszymy
    $s = db()->prepare('SELECT email FROM users WHERE id = ?'); $s->execute([$uid]); $to = (string)$s->fetchColumn();
    if ($to === '' || !function_exists('admin_mail')) return;
    $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'nieznana przeglądarka'), 0, 160);
    try {
        [$sj, $h, $tx] = admin_mail('Nowe logowanie do panelu', 'Panel · bezpieczeństwo', 'Nowe urządzenie',
            'Ktoś zalogował się do panelu z nowego urządzenia: ' . e($ua) . ', adres IP ' . e(client_ip()) . ', ' . date('d.m.Y H:i') . '. Jeśli to Ty, nic nie rób. Jeśli nie, od razu zmień hasło w ustawieniach i włącz logowanie dwuetapowe.');
        send_mail([$to], $sj, $h, $tx, [], false);
    } catch (Throwable $e) {}
}
