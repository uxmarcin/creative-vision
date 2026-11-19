<?php
// Codzienne przypomnienia i wezwania. Wywoływane rano w dni robocze; bezpieczne przy wielokrotnym wywołaniu.
declare(strict_types=1);

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/reminders.php';
require_once __DIR__ . '/lib/ksef.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');
$r = run_reminders();
$rec = run_recurring();
ksef_poll_pending();
echo json_encode(['ok' => true, 'run' => $r['run'], 'sent' => count($r['sent'] ?? []), 'recurring' => count($rec)]);
