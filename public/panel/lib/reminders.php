<?php
// Automatyczne przypomnienia i wezwania. Idempotentne: każdy rodzaj idzie do faktury najwyżej raz.
declare(strict_types=1);

require_once __DIR__ . '/mail.php';

function reminders_sent(int $id): array {
    $s = db()->prepare('SELECT kind FROM reminders WHERE invoice_id = ?'); $s->execute([$id]);
    return array_column($s->fetchAll(), 'kind');
}
function mark_reminder(int $id, string $kind, string $at): void {
    db()->prepare('INSERT OR IGNORE INTO reminders (invoice_id, kind, sent_at) VALUES (?, ?, ?)')->execute([$id, $kind, $at]);
}

/** Co dziś powinno pójść do tej faktury (albo null). Starsze, przegapione rodzaje oznacza jako pominięte. */
function reminder_due_today(array $inv): ?string {
    if ($inv['status'] !== 'issued' || (int)$inv['reminders'] !== 1 || empty($inv['sent_at']) || !empty($inv['declared_at'])) return null;
    if (trim((string)($inv['buyer']['email'] ?? '')) === '') return null;
    $t = today(); $due = (string)$inv['due_date'];
    $sent = reminders_sent((int)$inv['id']);
    $pick = null;
    foreach (reminder_plan($inv) as $kind => $date) {
        if (in_array($kind, $sent, true) || $date > $t) continue;
        $ok = match ($kind) {
            'before' => $t < $due,
            'due' => $t === $due,
            default => $date >= date('Y-m-d', strtotime($t . ' -5 days')),
        };
        if ($pick !== null) mark_reminder((int)$inv['id'], $pick, 'pominięte');
        $pick = $ok ? $kind : null;
        if (!$ok) mark_reminder((int)$inv['id'], $kind, 'pominięte');
    }
    return $pick;
}

/** Uruchamia wysyłkę. Bez $force najwyżej raz na 50 minut i tylko w godzinach 8 do 20. */
function run_reminders(bool $force = false): array {
    if (!$force) {
        if (time() - (int)setting('last_cron') < 3000) return ['run' => false, 'why' => 'za wcześnie'];
        $h = (int)date('G'); if ($h < 8 || $h >= 20) return ['run' => false, 'why' => 'poza godzinami'];
    }
    set_setting('last_cron', (string)time());
    $out = ['run' => true, 'sent' => []];
    $rows = db()->query("SELECT * FROM invoices WHERE status = 'issued' AND reminders = 1 AND sent_at IS NOT NULL")->fetchAll();
    foreach ($rows as $r) {
        $inv = hydrate($r);
        $kind = reminder_due_today($inv);
        if (!$kind) continue;
        if (send_invoice_mail($inv, $kind)) {
            mark_reminder((int)$inv['id'], $kind, now());
            log_event((int)$inv['id'], 'auto', reminder_label($kind) . ' wysłane automatycznie');
            $out['sent'][] = $inv['number'] . ' ' . $kind;
        }
    }
    return $out;
}

/** Najbliższe zaplanowane wiadomości do panelu: [[data, kind, faktura], …]. */
function upcoming_reminders(int $limit = 5): array {
    $rows = db()->query("SELECT * FROM invoices WHERE status = 'issued' AND reminders = 1 AND sent_at IS NOT NULL AND declared_at IS NULL")->fetchAll();
    $list = [];
    foreach ($rows as $r) {
        $inv = hydrate($r);
        $sent = reminders_sent((int)$inv['id']);
        foreach (reminder_plan($inv) as $k => $d) {
            if (in_array($k, $sent, true) || $d < today()) continue;
            $list[] = [$d, $k, $inv];
            break;
        }
    }
    usort($list, fn($a, $b) => strcmp($a[0], $b[0]));
    return array_slice($list, 0, $limit);
}

// ---------- faktury cykliczne ----------
function last_workday(string $ym): string {
    $d = date('Y-m-t', strtotime($ym . '-01'));
    while ((int)date('N', strtotime($d)) >= 6) $d = date('Y-m-d', strtotime($d . ' -1 day'));
    return $d;
}

/** W ostatni dzień roboczy miesiąca tworzy szkic kolejnej faktury cyklicznej i daje nam znać mailem. Zaległych miesięcy nie nadrabia. */
function run_recurring(): array {
    $made = [];
    $now = date('Y-m');
    foreach (db()->query("SELECT * FROM invoices WHERE recurring = 1 AND status != 'cancelled'")->fetchAll() as $r) {
        $inv = hydrate($r);
        $base = (string)($inv['book_ym'] ?: substr((string)$inv['issue_date'], 0, 7));
        if ($base >= $now || today() < last_workday($now)) continue;
        $issue = date('Y-m-t', strtotime($now . '-01'));
        $term = max(0, (int)$inv['term_days']);
        $pdo = db(); $pdo->beginTransaction();
        $f = ['client_id' => $inv['client_id'], 'buyer' => json_encode($inv['buyer'], JSON_UNESCAPED_UNICODE), 'issue_date' => $issue, 'sale_date' => $issue,
            'due_date' => date('Y-m-d', strtotime($issue . " +$term days")), 'term_days' => $term, 'place' => $inv['place'], 'items' => $r['items'],
            'net' => $inv['net'], 'vat' => $inv['vat'], 'gross' => $inv['gross'], 'notes' => $inv['notes'], 'reminders' => $inv['reminders'],
            'currency' => $inv['currency'] ?: 'PLN', 'lang' => $inv['lang'] ?: 'pl', 'recurring' => 1, 'book_ym' => $now, 'source' => 'cykliczna', 'created' => now(), 'updated' => now()];
        $pdo->prepare('INSERT INTO invoices (' . implode(', ', array_keys($f)) . ') VALUES (:' . implode(', :', array_keys($f)) . ')')->execute($f);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE invoices SET recurring = 0 WHERE id = ?')->execute([$inv['id']]);
        $pdo->commit();
        log_event($id, 'create', 'Szkic faktury cyklicznej na podstawie ' . $inv['number']);
        $name = (string)($inv['buyer']['name'] ?? '');
        [$s, $h, $t] = admin_mail('Szkic faktury cyklicznej: ' . $name, 'Faktura cykliczna · ' . $now, 'Szkic dla ' . $name, 'Przygotowaliśmy szkic faktury za ' . e(pl_date($issue)) . ' na kwotę ' . e(money((int)$inv['gross'], $inv['currency'] ?: 'PLN')) . ', na podstawie faktury ' . e((string)$inv['number']) . '. Sprawdź go i wystaw jednym kliknięciem.', CV_SITE . '/panel?a=view&id=' . $id, 'jest gotowy');
        send_mail([setting('mail_reply')], $s, $h, $t, [], false);
        $made[] = $id;
    }
    return $made;
}
