<?php
// Powiadomienia push (Web Push, VAPID, szyfrowanie aes128gcm wg RFC 8291) bez zewnętrznych bibliotek.
declare(strict_types=1);

function b64u(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function b64u_dec(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }

function push_migrate(): void {
    static $d = false; if ($d) return; $d = true;
    db()->exec('CREATE TABLE IF NOT EXISTS push_subs (id INTEGER PRIMARY KEY, endpoint TEXT UNIQUE NOT NULL, p256dh TEXT NOT NULL, auth TEXT NOT NULL, ua TEXT, created TEXT NOT NULL)');
}
function ec_raw_pub($key): string { $d = openssl_pkey_get_details($key); return "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT); }
function ec_pub_pem(string $raw): string { return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw), 64, "\n") . "-----END PUBLIC KEY-----\n"; }

/** Klucze VAPID: tworzone raz, trzymane w bazie panelu. */
function vapid(): array {
    if (setting('vapid_priv') === '') {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($k, $pem);
        set_setting('vapid_priv', $pem); set_setting('vapid_pub', b64u(ec_raw_pub($k)));
    }
    return [setting('vapid_pub'), setting('vapid_priv')];
}

function der_to_raw(string $der): string {
    $o = 3; $lr = ord($der[$o]); $r = substr($der, $o + 1, $lr); $o += 1 + $lr + 1; $ls = ord($der[$o]); $s = substr($der, $o + 1, $ls);
    return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
}
function vapid_jwt(string $endpoint): string {
    [, $priv] = vapid(); $u = parse_url($endpoint);
    $h = b64u('{"typ":"JWT","alg":"ES256"}');
    $c = b64u(json_encode(['aud' => $u['scheme'] . '://' . $u['host'], 'exp' => time() + 12 * 3600, 'sub' => 'mailto:kontakt@creative-vision.pl']));
    openssl_sign($h . '.' . $c, $sig, $priv, OPENSSL_ALGO_SHA256);
    return $h . '.' . $c . '.' . b64u(der_to_raw($sig));
}
function hkdf32(string $salt, string $ikm, string $info, int $len): string { return substr(hash_hmac('sha256', $info . "\x01", hash_hmac('sha256', $ikm, $salt, true), true), 0, $len); }

/** Szyfruje treść dla jednej subskrypcji (RFC 8291). */
function push_encrypt(string $payload, string $uaPub, string $auth): string {
    $eph = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $asPub = ec_raw_pub($eph);
    $secret = openssl_pkey_derive(openssl_pkey_get_public(ec_pub_pem($uaPub)), $eph, 32);
    if ($secret === false) throw new RuntimeException('Nie mogę zaszyfrować powiadomienia.');
    $ikm = hkdf32($auth, $secret, "WebPush: info\0" . $uaPub . $asPub, 32);
    $salt = random_bytes(16);
    $cek = hkdf32($salt, $ikm, "Content-Encoding: aes128gcm\0", 16);
    $nonce = hkdf32($salt, $ikm, "Content-Encoding: nonce\0", 12);
    $ct = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    return $salt . pack('N', 4096) . chr(65) . $asPub . $ct . $tag;
}

/** Wysyła powiadomienie na wszystkie zapisane urządzenia. Zwraca liczbę udanych wysyłek. */
function notif_migrate(): void { static $d = false; if ($d) return; $d = true; db()->exec('CREATE TABLE IF NOT EXISTS notifs (id INTEGER PRIMARY KEY, title TEXT, body TEXT, url TEXT, tag TEXT, at TEXT NOT NULL, seen INTEGER NOT NULL DEFAULT 0)'); }
function notif_log(string $title, string $body, string $url, string $tag): void {
    notif_migrate();
    db()->prepare('INSERT INTO notifs (title, body, url, tag, at) VALUES (?, ?, ?, ?, ?)')->execute([$title, $body, $url, $tag, now()]);
    db()->exec("DELETE FROM notifs WHERE id NOT IN (SELECT id FROM notifs ORDER BY id DESC LIMIT 300)");
}
function notif_unseen(): int { try { notif_migrate(); return (int)db()->query('SELECT COUNT(*) FROM notifs WHERE seen = 0')->fetchColumn(); } catch (Throwable $e) { return 0; } }
function push_send(string $title, string $body, string $url = '/panel', string $tag = 'cv'): int {
    try { notif_log($title, $body, $url, $tag); } catch (Throwable $e) {}
    push_migrate();
    $subs = db()->query('SELECT * FROM push_subs')->fetchAll(); if (!$subs) return 0;
    [$pub] = vapid(); $ok = 0;
    $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url, 'tag' => $tag], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    foreach ($subs as $s) {
        try { $data = push_encrypt($payload, b64u_dec($s['p256dh']), b64u_dec($s['auth'])); } catch (Throwable $e) { continue; }
        $ch = curl_init($s['endpoint']);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $data, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm', 'TTL: 86400', 'Urgency: high', 'Topic: ' . substr(preg_replace('/[^A-Za-z0-9_-]/', '', $tag), 0, 32), 'Authorization: vapid t=' . vapid_jwt($s['endpoint']) . ', k=' . $pub]]);
        curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code >= 200 && $code < 300) $ok++;
        elseif (in_array($code, [404, 410], true)) db()->prepare('DELETE FROM push_subs WHERE id = ?')->execute([$s['id']]);
    }
    return $ok;
}

/** Nowe nieprzeczytane maile w obu skrzynkach: powiadomienie o każdym nowym (najwyżej 3 naraz). */
function push_mail_check(): int {
    push_migrate();
    if (!(int)db()->query('SELECT COUNT(*) FROM push_subs')->fetchColumn()) return 0;
    if (time() - (int)setting('push_mail_at') < 50) return 0;
    set_setting('push_mail_at', (string)time());
    $n = 0; $cur = mail_acc();
    foreach (['f', 'k'] as $ac) {
        mail_acc($ac); if (!inbox_enabled()) continue;
        try {
            $im = imap_open_box(); $im->select('INBOX');
            $un = $im->uids('UNSEEN'); set_setting('imap_unseen', (string)count($un));
            $last = (int)setting('push_uid_' . $ac);
            $max = max(array_merge([$last], $un));
            if ($last === 0) { $all = $im->uids('ALL'); set_setting('push_uid_' . $ac, (string)max(array_merge([0], $all))); continue; }
            $new = array_values(array_filter($un, fn($u) => $u > $last)); sort($new);
            if ($new) {
                $hs = $im->headers(array_slice($new, -3));
                foreach ($hs as $uid => $m) {
                    [$fn, $fa] = mail_addr((string)($m['h']['from'] ?? ''));
                    $subj = mime_hdr_decode((string)($m['h']['subject'] ?? '(bez tematu)'));
                    $n += push_send(($fn ?: $fa) . ($ac === 'k' ? ' · kontakt@' : ''), $subj, '/panel?a=mail&box=INBOX&uid=' . $uid . ($ac === 'k' ? '&acc=k' : ''), 'mail-' . $ac . '-' . $uid) ? 1 : 0;
                }
            }
            set_setting('push_uid_' . $ac, (string)$max);
            unset($im);
        } catch (Throwable $e) { /* następnym razem */ }
    }
    mail_acc($cur);
    return $n;
}
