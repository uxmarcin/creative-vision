<?php
// Prosty klient IMAP (bez rozszerzenia imap) i parser wiadomości MIME dla skrzynki faktury@.
declare(strict_types=1);

final class Imap {
    private $s; private int $n = 0;

    public function __construct(string $host, int $port, string $user, string $pass) {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => getenv('CV_PANEL_DEV') !== '1', 'verify_peer_name' => getenv('CV_PANEL_DEV') !== '1', 'SNI_enabled' => true, 'peer_name' => $host]]);
        $this->s = @stream_socket_client('ssl://' . $host . ':' . $port, $en, $es, 12, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->s) throw new RuntimeException('Nie mogę połączyć się z serwerem poczty (' . $host . ':' . $port . '): ' . $es);
        stream_set_timeout($this->s, 25);
        $this->line();
        $this->cmd('LOGIN ' . $this->q($user) . ' ' . $this->q($pass));
    }
    public function __destruct() { if ($this->s) { @fwrite($this->s, 'Z LOGOUT' . "\r\n"); @fclose($this->s); } }

    private function q(string $s): string { return '"' . addcslashes($s, "\\\"") . '"'; }
    private function line(): string {
        $l = fgets($this->s);
        if ($l === false) throw new RuntimeException('Serwer poczty przerwał połączenie.');
        return $l;
    }
    /** Wysyła polecenie i zwraca odpowiedzi nieoznaczone; literały {n} są wklejane w treść. */
    public function cmd(string $c, ?string $literal = null): array {
        $tag = 'A' . (++$this->n);
        if ($literal !== null) {
            fwrite($this->s, $tag . ' ' . $c . ' {' . strlen($literal) . "}\r\n");
            $l = $this->line();
            if ($l[0] !== '+') throw new RuntimeException('Serwer poczty odrzucił polecenie: ' . trim($l));
            fwrite($this->s, $literal . "\r\n");
        } else fwrite($this->s, $tag . ' ' . $c . "\r\n");
        $out = []; $cur = null;
        while (true) {
            $l = $this->line();
            if (preg_match('/\{(\d+)\}\r\n$/', $l, $m)) {
                $len = (int)$m[1]; $data = '';
                while (strlen($data) < $len) { $chunk = fread($this->s, $len - strlen($data)); if ($chunk === false || $chunk === '') throw new RuntimeException('Przerwany odczyt wiadomości.'); $data .= $chunk; }
                $cur = ($cur ?? '') . $l . $data;
                continue;
            }
            if ($cur !== null) { $cur .= $l; if (str_starts_with($l, ')') || !str_starts_with(ltrim($l), '* ')) { $out[] = $cur; $cur = null; continue; } }
            if (str_starts_with($l, $tag . ' ')) {
                if (!preg_match('/^' . $tag . ' OK/i', $l)) throw new RuntimeException(stripos($c, 'LOGIN') === 0 ? 'Serwer poczty nie przyjął loginu lub hasła.' : 'Serwer poczty: ' . trim(substr($l, strlen($tag) + 1)));
                if ($cur !== null) $out[] = $cur;
                return $out;
            }
            $out[] = $l;
        }
    }
    public function select(string $box): int {
        foreach ($this->cmd('SELECT ' . $this->q($box)) as $l) if (preg_match('/^\* (\d+) EXISTS/i', $l, $m)) return (int)$m[1];
        return 0;
    }
    /** Zwraca nazwę folderu z flagą (np. \Sent) albo null. */
    /** Lista folderów: [[flagi, nazwa], …] */
    public function folders(): array {
        $o = [];
        foreach ($this->cmd('LIST "" "*"') as $l) if (preg_match('/^\* LIST \(([^)]*)\) (?:"[^"]*"|NIL) (?:"((?:[^"\\]|\\.)*)"|(\S+))\s*$/i', trim($l), $m)) $o[] = [$m[1], stripslashes(($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? ''))];
        return $o;
    }
    /** Przenosi wiadomości do kosza (MOVE, a gdy serwer go nie zna: COPY + usunięcie). */
    public function trash(array $uids, string $trash): void {
        if (!$uids) return;
        $set = implode(',', array_map('intval', $uids));
        try { $this->cmd('UID MOVE ' . $set . ' ' . $this->q($trash)); return; } catch (Throwable $e) { /* starszy serwer */ }
        $this->cmd('UID COPY ' . $set . ' ' . $this->q($trash));
        $this->cmd('UID STORE ' . $set . ' +FLAGS.SILENT (\\Deleted)');
        try { $this->cmd('UID EXPUNGE ' . $set); } catch (Throwable $e) { $this->cmd('EXPUNGE'); }
    }
    public function create(string $box): void { $this->cmd('CREATE ' . $this->q($box)); try { $this->cmd('SUBSCRIBE ' . $this->q($box)); } catch (Throwable $e) {} }
    public function special(string $flag): ?string {
        foreach ($this->cmd('LIST "" "*"') as $l) if (stripos($l, $flag) !== false && preg_match('/"([^"]+)"\s*$|\s(\S+)\s*$/', trim($l), $m)) return $m[1] !== '' ? $m[1] : $m[2];
        return null;
    }
    public function uids(string $criteria = 'ALL'): array {
        foreach ($this->cmd('UID SEARCH ' . $criteria) as $l) if (preg_match('/^\* SEARCH(.*)$/i', trim($l), $m)) return array_map('intval', preg_split('/\s+/', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY));
        return [];
    }
    /** Nagłówki wielu wiadomości: [uid => [flags, size, date, headers]] */
    public function headers(array $uids): array {
        if (!$uids) return [];
        $res = [];
        foreach ($this->cmd('UID FETCH ' . implode(',', $uids) . ' (UID FLAGS RFC822.SIZE BODY.PEEK[HEADER.FIELDS (FROM TO CC SUBJECT DATE CONTENT-TYPE)])') as $r) {
            if (!preg_match('/UID (\d+)/', $r, $m)) continue;
            $uid = (int)$m[1];
            preg_match('/FLAGS \(([^)]*)\)/', $r, $f);
            preg_match('/RFC822\.SIZE (\d+)/', $r, $sz);
            $hdr = (string)lit($r);
            $res[$uid] = ['flags' => $f[1] ?? '', 'size' => (int)($sz[1] ?? 0), 'h' => mime_headers($hdr)];
        }
        return $res;
    }
    public function raw(int $uid): string {
        foreach ($this->cmd('UID FETCH ' . $uid . ' (BODY.PEEK[])') as $r) { $l = lit($r); if ($l !== null) return $l; }
        throw new RuntimeException('Nie znaleziono wiadomości.');
    }
    public function flag(int $uid, string $flag, bool $on = true): void { $this->cmd('UID STORE ' . $uid . ' ' . ($on ? '+' : '-') . 'FLAGS (' . $flag . ')'); }
    public function append(string $box, string $msg, string $flags = '\\Seen'): void { $this->cmd('APPEND ' . $this->q($box) . ' (' . $flags . ')', $msg); }
}

/** Pierwszy literał IMAP {n} z odpowiedzi, dokładnie n bajtów. */
function lit(string $r): ?string {
    if (!preg_match('/\{(\d+)\}\r\n/', $r, $m, PREG_OFFSET_CAPTURE)) return null;
    return substr($r, $m[0][1] + strlen($m[0][0]), (int)$m[1][0]);
}

// ---------- MIME ----------
function mime_hdr_decode(string $v): string {
    $v = trim($v);
    if (!mb_check_encoding($v, 'UTF-8')) $v = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-2');
    if (!str_contains($v, '=?')) return $v;
    $v = preg_replace('/\?=\s+=\?/', '?==?', $v);
    return trim((string)preg_replace_callback('/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/', function ($m) {
        $d = strtoupper($m[2]) === 'B' ? (string)base64_decode($m[3]) : quoted_printable_decode(str_replace('_', ' ', $m[3]));
        return mime_to_utf8($d, preg_replace('/\*.*$/', '', $m[1]));
    }, $v));
}
function mime_headers(string $raw): array {
    $raw = preg_replace("/\r?\n[ \t]+/", ' ', $raw);
    $h = [];
    foreach (preg_split("/\r?\n/", $raw) as $l) if (preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', $l, $m)) $h[strtolower($m[1])] = $h[strtolower($m[1])] ?? $m[2];
    return $h;
}
function mime_param(string $hdr, string $name): ?string {
    if (preg_match('/' . $name . '\*=(?:[^\']*\'[^\']*\')?("?)([^";]+)\1/i', $hdr, $m)) return rawurldecode($m[2]);
    if (preg_match('/' . $name . '="([^"]*)"|' . $name . '=([^;\s]+)/i', $hdr, $m)) return mime_hdr_decode($m[1] !== '' ? $m[1] : $m[2]);
    return null;
}
function mime_decode_body(string $body, string $enc, string $charset): string {
    $enc = strtolower(trim($enc));
    if ($enc === 'base64') $body = (string)base64_decode(preg_replace('/\s+/', '', $body));
    elseif ($enc === 'quoted-printable') $body = quoted_printable_decode($body);
    return $body;
}
function mime_to_utf8(string $s, ?string $cs): string {
    $cs = strtoupper(trim((string)$cs, " \"'"));
    if ($cs === '' || $cs === 'UTF-8' || $cs === 'US-ASCII') return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-2');
    $c = @mb_convert_encoding($s, 'UTF-8', $cs === 'WINDOWS-1250' ? 'Windows-1250' : $cs);
    return is_string($c) ? $c : $s;
}
/** Rozbiera wiadomość: ['h' => nagłówki, 'text' => ..., 'html' => ..., 'att' => [[name, type, data]]] */
function mime_parse(string $raw): array {
    $out = ['h' => [], 'text' => '', 'html' => '', 'att' => []];
    $walk = function (string $part, bool $top) use (&$walk, &$out): void {
        $sp = preg_split("/\r?\n\r?\n/", $part, 2);
        $h = mime_headers($sp[0]); $body = $sp[1] ?? '';
        if ($top) $out['h'] = $h;
        $ct = $h['content-type'] ?? 'text/plain';
        $type = strtolower(trim(explode(';', $ct)[0]));
        if (str_starts_with($type, 'multipart/')) {
            $b = mime_param($ct, 'boundary'); if ($b === null) return;
            foreach (preg_split('/\r?\n--' . preg_quote($b, '/') . '(?:--)?[ \t]*\r?\n?/', "\n" . $body) as $i => $p) if ($i > 0 && trim($p) !== '') $walk($p, false);
            return;
        }
        if ($type === 'message/rfc822') { $out['att'][] = ['wiadomosc.eml', $type, $body]; return; }
        $disp = $h['content-disposition'] ?? '';
        $name = mime_param($disp, 'filename') ?? mime_param($ct, 'name');
        $data = mime_decode_body($body, $h['content-transfer-encoding'] ?? '', '');
        if ($name !== null || stripos($disp, 'attachment') === 0 || !in_array($type, ['text/plain', 'text/html'], true)) {
            $out['att'][] = [$name ?? ('zalacznik.' . (explode('/', $type)[1] ?? 'bin')), $type, $data, trim((string)($h['content-id'] ?? ''), '<> ')];
            return;
        }
        $txt = mime_to_utf8($data, mime_param($ct, 'charset'));
        if ($type === 'text/html') { if ($out['html'] === '') $out['html'] = $txt; } elseif ($out['text'] === '') $out['text'] = $txt;
    };
    $walk($raw, true);
    if ($out['text'] === '' && $out['html'] !== '') $out['text'] = html_to_text_mail($out['html']);
    return $out;
}
function html_to_text_mail(string $h): string {
    $h = preg_replace('#<(script|style|head)[^>]*>.*?</\1>#is', '', $h);
    $h = preg_replace('#<br\s*/?>|</p>|</div>|</tr>|</h\d>|</li>#i', "\n", $h);
    $t = html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t\x{00A0}]+/u", ' ', $t);
    return trim(preg_replace("/\n\s*\n\s*\n+/", "\n\n", $t));
}
/** "Jan Kowalski <jan@x.pl>" → ['Jan Kowalski', 'jan@x.pl'] */
function mail_addr(string $v): array {
    $v = mime_hdr_decode($v);
    if (preg_match('/^\s*"?([^"<]*?)"?\s*<([^>]+)>/', $v, $m)) return [trim($m[1]) !== '' ? trim($m[1]) : $m[2], trim($m[2])];
    return [trim($v), trim($v)];
}

// ---------- skrzynka panelu ----------
function inbox_enabled(): bool { return setting('imap_pass') !== '' && setting('imap_user') !== ''; }
/** Folder „Wysłane”: zapamiętany, oznaczony flagą \Sent, o typowej nazwie, a gdy go nie ma, zakładamy go. */
function sent_box(Imap $im): string {
    $fs = $im->folders(); $names = array_column($fs, 1);
    $saved = setting('imap_sent');
    if ($saved !== '' && in_array($saved, $names, true)) return $saved;
    $pick = null;
    foreach ($fs as [$fl, $n]) if (stripos($fl, '\\Sent') !== false) { $pick = $n; break; }
    if (!$pick) foreach ($names as $n) if (preg_match('/^(INBOX[.\/])?(Sent|Sent Items|Sent Messages|Wys(ł|l)ane)$/i', $n)) { $pick = $n; break; }
    if (!$pick) {
        $pref = false; foreach ($names as $n) if (stripos($n, 'INBOX.') === 0) $pref = true;
        $pick = $pref ? 'INBOX.Sent' : 'Sent';
        try { $im->create($pick); } catch (Throwable $e) { $pick = $pref ? 'Sent' : 'INBOX.Sent'; try { $im->create($pick); } catch (Throwable $e2) {} }
    }
    set_setting('imap_sent', $pick);
    return $pick;
}
/** Folder kosza: oznaczony flagą \\Trash, o typowej nazwie, a gdy go nie ma, zakładamy go. */
function trash_box(Imap $im): string {
    $fs = $im->folders(); $names = array_column($fs, 1);
    $saved = setting('imap_trash');
    if ($saved !== '' && in_array($saved, $names, true)) return $saved;
    $pick = null;
    foreach ($fs as [$fl, $n]) if (stripos($fl, '\\Trash') !== false) { $pick = $n; break; }
    if (!$pick) foreach ($names as $n) if (preg_match('/^(INBOX[.\/])?(Trash|Deleted Items|Deleted Messages|Kosz)$/i', $n)) { $pick = $n; break; }
    if (!$pick) {
        $pref = false; foreach ($names as $n) if (stripos($n, 'INBOX.') === 0) $pref = true;
        $pick = $pref ? 'INBOX.Trash' : 'Trash';
        try { $im->create($pick); } catch (Throwable $e) { $pick = $pref ? 'Trash' : 'INBOX.Trash'; try { $im->create($pick); } catch (Throwable $e2) {} }
    }
    set_setting('imap_trash', $pick);
    return $pick;
}
function imap_open_box(): Imap { return new Imap(setting('imap_host'), (int)setting('imap_port'), setting('imap_user'), setting('imap_pass')); }
