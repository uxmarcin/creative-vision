<?php
// SMS przez SMSAPI (token OAuth z panelu SMSAPI). Polskie znaki zamieniamy, żeby SMS mieścił się w 160 znakach.
declare(strict_types=1);

function sms_enabled(): bool { return setting('sms_on') === '1' && setting('sms_token') !== ''; }

function sms_phone(string $p): ?string {
    $d = preg_replace('/\D/', '', $p) ?? '';
    if (str_starts_with($d, '00')) $d = substr($d, 2);
    if (strlen($d) === 9) $d = '48' . $d;
    return strlen($d) >= 11 && strlen($d) <= 15 ? $d : null;
}

/** Wysyła SMS. Zwraca [ok, opis]. */
function sms_send(string $to, string $text): array {
    $num = sms_phone($to);
    if (!$num) return [false, 'Niepoprawny numer telefonu: ' . $to];
    if (setting('sms_token') === '') return [false, 'Brak tokenu SMSAPI w ustawieniach.'];
    if (getenv('CV_PANEL_DEV') === '1') {
        $dir = CV_DATA . '/outbox'; if (!is_dir($dir)) mkdir($dir, 0700, true);
        file_put_contents($dir . '/sms-' . date('His') . '.txt', $num . "\n" . $text);
        return [true, 'SMS zapisany lokalnie'];
    }
    $p = ['to' => $num, 'message' => $text, 'format' => 'json', 'encoding' => 'utf-8', 'normalize' => '1'];
    if (trim(setting('sms_from')) !== '') $p['from'] = trim(setting('sms_from'));
    $ch = curl_init('https://' . (setting('sms_api') ?: 'api.smsapi.pl') . '/sms.do');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($p), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . setting('sms_token')]]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($res === false) return [false, 'Brak połączenia z SMSAPI: ' . $err];
    $j = json_decode((string)$res, true);
    if (!is_array($j)) return [false, 'Nieoczekiwana odpowiedź SMSAPI.'];
    if (isset($j['error'])) return [false, 'SMSAPI: ' . ($j['message'] ?? ('błąd ' . $j['error']))];
    $pts = $j['list'][0]['points'] ?? null;
    return [true, 'SMS wysłany na +' . $num . ($pts !== null ? ' (' . $pts . ' pkt)' : '')];
}

/** Krótka treść przypomnienia. */
function sms_text(array $inv, string $kind): string {
    $num = (string)$inv['number']; $amt = str_replace("\u{00A0}", ' ', money((int)$inv['gross'], $inv['currency'] ?? 'PLN'));
    $due = pl_date($inv['due_date']); $url = str_replace('https://', '', pay_url($inv));
    $left = days_between(today(), (string)$inv['due_date']);
    if ($kind === 'manual') $kind = $left > 0 ? 'before' : ($left === 0 ? 'due' : 'w1');
    $who = 'Creative Vision';
    return match ($kind) {
        'before' => $who . ': przypominamy, ze ' . $due . ' mija termin faktury ' . $num . ' na ' . $amt . '. ' . $url,
        'due' => $who . ': dzis mija termin faktury ' . $num . ' na ' . $amt . '. Dane do przelewu: ' . $url,
        'w2' => $who . ': ostateczne wezwanie do zaplaty faktury ' . $num . ' na ' . $amt . ' (termin minal ' . $due . '). ' . $url,
        default => $who . ': faktura ' . $num . ' na ' . $amt . ' czeka na wplate (termin ' . $due . '). ' . $url,
    };
}

/** SMS do klienta w sprawie faktury, jeśli włączony dla tego rodzaju wiadomości. */
function sms_invoice(array $inv, string $kind, bool $force = false): ?array {
    if (!sms_enabled()) return null;
    $k = $kind === 'manual' ? 'manual' : $kind;
    if (!$force && setting('sms_' . $k) !== '1') return null;
    $phone = trim((string)($inv['buyer']['phone'] ?? ''));
    if ($phone === '') return null;
    [$ok, $msg] = sms_send($phone, sms_text($inv, $kind));
    log_event((int)$inv['id'], $ok ? 'sms' : 'error', ($ok ? '' : 'SMS nie wyszedł: ') . $msg);
    return [$ok, $msg];
}
