<?php
// Piksel otwarcia maila z fakturą: zapisuje pierwsze otwarcie i daje nam znać.
declare(strict_types=1);

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/mail.php';

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex');
$gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
try {
    $inv = invoice_by_token((string)($_GET['t'] ?? ''));
    if ($inv && in_array($inv['status'], ['issued', 'paid'], true)) {
        $kind = preg_replace('/[^a-z0-9]/', '', (string)($_GET['k'] ?? ''));
        // Pobranie w pierwszej minucie po wysyłce to zwykle automat (np. ochrona prywatności Apple Mail), nie człowiek.
        $last = db()->prepare("SELECT MAX(at) FROM events WHERE invoice_id = ? AND kind IN ('mail', 'auto', 'manual')"); $last->execute([$inv['id']]);
        $sentAt = (string)$last->fetchColumn();
        $early = $sentAt !== '' && time() - strtotime($sentAt) < 60;
        $ev = 'open_' . ($kind ?: 'mail');
        $seen = db()->prepare('SELECT COUNT(*) FROM events WHERE invoice_id = ? AND kind = ?'); $seen->execute([$inv['id'], $ev]);
        if (!$early && (int)$seen->fetchColumn() === 0) {
            if (empty($inv['opened_at'])) db()->prepare('UPDATE invoices SET opened_at = ? WHERE id = ?')->execute([now(), $inv['id']]);
            notify_seen($inv, 'Klient otworzył maila: ' . mb_strtolower(reminder_label($kind ?: 'new')), $ev);
        }
    }
} catch (Throwable $e) { /* piksel zawsze się wyświetla */ }
echo $gif;
