<?php
// Codzienne przypomnienia i wezwania. Wywoływane rano w dni robocze; bezpieczne przy wielokrotnym wywołaniu.
declare(strict_types=1);

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/reminders.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');
$r = run_reminders();
$rec = run_recurring();
echo json_encode(['ok' => true, 'run' => $r['run'], 'sent' => count($r['sent'] ?? []), 'recurring' => count($rec)]);
