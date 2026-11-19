<?php
// Wycena z AI: Claude (API Anthropic) układa pozycje wyceny na podstawie zapytania, szansy i cennika Creative Vision.
declare(strict_types=1);

const AI_MODELS = ['claude-sonnet-5-5' => 'Claude Sonnet 5.5 (szybki, tańszy)', 'claude-opus-5-5' => 'Claude Opus 5.5 (najdokładniejszy)'];
const AI_PRICING_DEFAULT = "Stawka godzinowa: 150 zł netto/h.
Strona firmowa: od 4 900 zł netto (projekt UX/UI ok. 33 h, wdrożenie, treści klienta, wersja mobilna, podstawowe SEO, szkolenie z panelu, 12 miesięcy gwarancji).
Sklep internetowy: od 5 990 zł netto (projekt ok. 40 h, ścieżka zakupu, płatności BLIK, dostawy).
Opieka: Podstawowy 490 zł/mies. (2 h zmian, aktualizacje i kopie raz w miesiącu), Standard 890 zł/mies. (5 h zmian, aktualizacje co 2 tygodnie, kopia codziennie).
Audyt UX strony usługowej lub sklepu: od 1 900 zł (13 h). Audyt UX aplikacji lub portalu: od 3 900 zł (26 h). Pojedynczy widok: 450 zł (3 h).
Analiza CRO: 1 500 zł (10 h). Makietowanie zmian: 450 zł za widok.
Projekt UX/UI: strona usługowa od 4 900 zł (33 h), sklep od 5 990 zł (40 h), aplikacja webowa od 12 000 zł (80 h), aplikacja mobilna od 9 000 zł (60 h), teaser projektu 1 500 zł (10 h).
Badania UX: wywiady pogłębione od 7 500 zł (50 h), testy użyteczności od 9 000 zł (60 h), card sorting i tree testing od 6 000 zł (40 h).
Dodatki do wyceny strony: copywriting, sesja zdjęciowa, wersje językowe, blog, rezerwacje, integracje (CRM, płatności, API) wyceniaj godzinowo.
Zasady: ceny netto, VAT 23%. Dziel wycenę na etapy (np. analiza i architektura, projekt UX/UI, wdrożenie, treści i SEO, testy i start). Podawaj realne godziny × 150 zł. Termin realizacji i zakres w opisie pozycji. Cena stała po akceptacji zakresu.";

function ai_enabled(): bool { return setting('ai_key') !== ''; }

/** Zwraca wycenę: ['items' => [...], 'notes' => '', 'valid_days' => 14, 'pay_days' => 7, 'summary' => '', 'questions' => [...]]. */
function ai_quote(string $context, string $brief, string $client): array {
    if (!ai_enabled()) throw new RuntimeException('Wpisz klucz API Anthropic w ustawieniach (sekcja AI do wycen).');
    $model = isset(AI_MODELS[setting('ai_model')]) ? setting('ai_model') : 'claude-sonnet-5-5';
    $system = "Jesteś doświadczonym project managerem w Creative Vision, agencji UX/UI z Rzeszowa, która projektuje i wdraża strony internetowe, sklepy i aplikacje. "
        . "Przygotowujesz wycenę dla klienta na podstawie zapytania. Piszesz po polsku, ciepło i konkretnie, bez żargonu, bez myślników typu — i –, bez wielkich liter dla ozdoby. "
        . "Nie wymyślaj faktów o kliencie. Jeśli czegoś brakuje, przyjmij rozsądne założenie i dopisz je do założeń oraz pytań do klienta.\n\nCENNIK I ZASADY CREATIVE VISION:\n" . (trim(setting('ai_pricing')) ?: AI_PRICING_DEFAULT);
    $user = "Klient: " . ($client !== '' ? $client : 'nieznany') . "\n\nZAPYTANIE I KONTEKST:\n" . ($context !== '' ? mb_substr($context, 0, 12000) : '(brak, opieraj się na opisie poniżej)')
        . ($brief !== '' ? "\n\nWSKAZÓWKI OD MARCINA:\n" . mb_substr($brief, 0, 3000) : '')
        . "\n\nPrzygotuj wycenę i zapisz ją, wywołując narzędzie zapisz_wycene (zawsze użyj narzędzia, nie odpowiadaj samym tekstem). Pozycje mają być etapami prac z liczbą godzin w opisie (np. „ok. 16 h”) i ceną netto za pozycję. Ilość 1, jednostka usł., chyba że opieka miesięczna (jednostka mies.).";
    $tool = ['name' => 'zapisz_wycene', 'description' => 'Zapisuje gotową wycenę w panelu.', 'input_schema' => ['type' => 'object', 'required' => ['items', 'notes', 'summary'], 'properties' => [
        'items' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 12, 'items' => ['type' => 'object', 'required' => ['name', 'price_net'], 'properties' => [
            'name' => ['type' => 'string', 'description' => 'Krótka nazwa etapu lub usługi'],
            'desc' => ['type' => 'string', 'description' => 'Co obejmuje, w 1 do 3 zdaniach, z liczbą godzin'],
            'qty' => ['type' => 'number'], 'unit' => ['type' => 'string'],
            'price_net' => ['type' => 'number', 'description' => 'Cena netto za jednostkę w złotych'],
            'vat' => ['type' => 'string', 'enum' => ['23', '8', '0', 'zw', 'np']]]]],
        'notes' => ['type' => 'string', 'description' => 'Uwagi na wycenie dla klienta: termin realizacji, co jest w cenie, etapy płatności, gwarancja. 3 do 6 krótkich zdań.'],
        'valid_days' => ['type' => 'integer', 'description' => 'Ważność wyceny w dniach, zwykle 14'],
        'pay_days' => ['type' => 'integer', 'description' => 'Termin płatności faktury w dniach, zwykle 7'],
        'summary' => ['type' => 'string', 'description' => 'Dla Marcina: przyjęte założenia i skąd taka cena, 2 do 4 zdań'],
        'questions' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Pytania do klienta, które doprecyzują wycenę']]]];
    $body = ['model' => $model, 'max_tokens' => 3000, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $user]], 'tools' => [$tool], 'tool_choice' => ['type' => 'auto']];
    $ch = curl_init(getenv('CV_AI_URL') ?: 'https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . setting('ai_key'), 'anthropic-version: 2023-06-01']]);
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($res === false) throw new RuntimeException('Brak połączenia z API Anthropic: ' . $err);
    $j = json_decode((string)$res, true);
    if ($code >= 400) throw new RuntimeException('API Anthropic: ' . ($j['error']['message'] ?? ('błąd ' . $code)));
    if ($r = ai_parse($j, $model)) return $r;
    foreach ((array)($j['content'] ?? []) as $c) if (($c['type'] ?? '') === 'text' && preg_match('/\{.*\}/s', (string)$c['text'], $m) && is_array($in = json_decode($m[0], true)) && !empty($in['items'])) {
        $j['content'] = [['type' => 'tool_use', 'input' => $in]]; return ai_parse($j, $model);
    }
    throw new RuntimeException('AI nie zwróciło wyceny. Spróbuj jeszcze raz.');
}

function ai_parse(array $j, string $model): ?array {
    foreach ((array)($j['content'] ?? []) as $c) if (($c['type'] ?? '') === 'tool_use' && is_array($c['input'] ?? null)) {
        $in = $c['input'];
        $items = [];
        foreach ((array)($in['items'] ?? []) as $it) $items[] = ['name' => mb_substr((string)($it['name'] ?? ''), 0, 200), 'desc' => mb_substr((string)($it['desc'] ?? ''), 0, 800),
            'qty' => (float)($it['qty'] ?? 1) ?: 1, 'unit' => (string)($it['unit'] ?? 'usł.') ?: 'usł.', 'price' => round((float)($it['price_net'] ?? 0), 2), 'vat' => in_array((string)($it['vat'] ?? '23'), ['23', '8', '0', 'zw', 'np'], true) ? (string)($it['vat'] ?? '23') : '23'];
        return ['items' => $items, 'notes' => (string)($in['notes'] ?? ''), 'valid_days' => max(1, min(60, (int)($in['valid_days'] ?? 14))), 'pay_days' => max(0, min(60, (int)($in['pay_days'] ?? 7))),
            'summary' => (string)($in['summary'] ?? ''), 'questions' => array_values(array_map('strval', (array)($in['questions'] ?? []))), 'model' => $model];
    }
    return null;
}
