<?php
// Codzienne przypomnienia i wezwania. Wywoływane rano w dni robocze; bezpieczne przy wielokrotnym wywołaniu.
declare(strict_types=1);

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/reminders.php';
require_once __DIR__ . '/lib/ksef.php';
require_once __DIR__ . '/lib/books.php';
require_once __DIR__ . '/lib/mail.php';
require_once __DIR__ . '/lib/leads.php';

header('Content-Type: application/json; charset=utf-8');
if (isset($_GET['mail'])) {
    require_once __DIR__ . '/lib/imap.php'; require_once __DIR__ . '/lib/mail.php';
    $n = 0; try { $n = push_mail_check(); } catch (Throwable $e) {}
    echo json_encode(['ok' => true, 'push' => $n]); exit;
}
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');
$r = run_reminders();
$paid = run_auto_paid();
$rec = run_recurring();
ksef_poll_pending();
try { $tn = run_tenders(); run_lead_reminders(); } catch (Throwable $e) { $tn = $e->getMessage(); }
try { $books = run_books_auto(); } catch (Throwable $e) { $books = $e->getMessage(); }
echo json_encode(['ok' => true, 'run' => $r['run'], 'sent' => count($r['sent'] ?? []), 'recurring' => count($rec), 'auto_paid' => $paid, 'books' => $books, 'tenders' => $tn]);
