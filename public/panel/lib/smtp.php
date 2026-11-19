<?php
// Wysyłka SMTP (SSL, AUTH LOGIN) przez skrzynkę faktury@, bez dodatkowych bibliotek.
declare(strict_types=1);

function smtp_enabled(): bool { return setting('smtp_on') === '1' && setting('imap_pass') !== '' && setting('imap_user') !== ''; }

/** Wysyła gotową wiadomość (nagłówki + treść). $rcpt: wszyscy odbiorcy, także ukryte kopie. */
function smtp_send(string $from, array $rcpt, string $message): void {
    $host = setting('smtp_host'); $port = (int)setting('smtp_port');
    $ctx = stream_context_create(['ssl' => ['verify_peer' => getenv('CV_PANEL_DEV') !== '1', 'verify_peer_name' => getenv('CV_PANEL_DEV') !== '1', 'peer_name' => $host]]);
    $s = @stream_socket_client('ssl://' . $host . ':' . $port, $en, $es, 12, STREAM_CLIENT_CONNECT, $ctx);
    if (!$s) throw new RuntimeException('Nie mogę połączyć się z serwerem SMTP: ' . $es);
    stream_set_timeout($s, 25);
    $read = function () use ($s): array {
        $code = 0; $txt = '';
        while (($l = fgets($s)) !== false) { $code = (int)substr($l, 0, 3); $txt .= $l; if (isset($l[3]) && $l[3] === ' ') break; }
        return [$code, trim($txt)];
    };
    $say = function (string $c, array $ok) use ($s, $read): string {
        fwrite($s, $c . "\r\n"); [$code, $t] = $read();
        if (!in_array($code, $ok, true)) throw new RuntimeException('Serwer SMTP: ' . $t);
        return $t;
    };
    try {
        [$code] = $read(); if ($code !== 220) throw new RuntimeException('Serwer SMTP nie odpowiada poprawnie.');
        $say('EHLO creative-vision.pl', [250]);
        $say('AUTH LOGIN', [334]);
        $say(base64_encode(setting('imap_user')), [334]);
        try { $say(base64_encode(setting('imap_pass')), [235]); } catch (RuntimeException $e) { throw new RuntimeException('Serwer SMTP nie przyjął loginu lub hasła.'); }
        $say('MAIL FROM:<' . $from . '>', [250]);
        foreach (array_unique($rcpt) as $r) $say('RCPT TO:<' . $r . '>', [250, 251]);
        $say('DATA', [354]);
        $msg = preg_replace("/(?<!\r)\n/", "\r\n", $message);
        $msg = preg_replace('/^\./m', '..', $msg);
        $say($msg . "\r\n.", [250]);
        @fwrite($s, "QUIT\r\n");
    } finally { @fclose($s); }
}
