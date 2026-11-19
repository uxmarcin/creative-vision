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
