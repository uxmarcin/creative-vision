#!/usr/bin/env python3
"""SEO 2026-10 etap 4: agencja od stron i sklepów, bez stawiania na jedną platformę.
/uslugi/sklep-prestashop -> /uslugi/sklepy-internetowe, /agencja-prestashop -> /agencja-ecommerce
(przekierowania 301 w public/.htaccess). Idempotentny."""
import os, re
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
P = os.path.join(ROOT, 'src/c/shared-lib.mjs')
s = open(P, encoding='utf-8').read()

def bt(x):
    if isinstance(x, str):
        assert '`' not in x and '${' not in x, x
        return '`' + x + '`'
    if isinstance(x, list):
        return '[' + ','.join(bt(i) for i in x) + ']'
    if isinstance(x, dict):
        return '{' + ','.join(f'{k}:{v[1:] if isinstance(v, str) and v.startswith("@") else bt(v)}' for k, v in x.items()) + '}'
    raise TypeError(x)

def rep(old, new, count=1):
    global s
    if old not in s:
        assert new in s, 'brak: ' + old[:90]
        return
    s = s.replace(old, new) if count == 0 else s.replace(old, new, count)

# 1) adresy i etykiety
rep(',[`more`,`Skonfigurujemy moduły w Twoim sklepie:`,`/agencja-prestashop`,`Agencja PrestaShop`]', '', 0)
rep('`Tworzenie sklepów PrestaShop`]', '`Tworzenie sklepów internetowych`]', 0)
rep('sklep-prestashop', 'sklepy-internetowe', 0)
rep('/agencja-prestashop', '/agencja-ecommerce', 0)
rep('"agencja-prestashop":{', '"agencja-ecommerce":{')
rep('children:`Agencja PrestaShop`', 'children:`Agencja e-commerce`', 0)
rep('`Agencja PrestaShop`]', '`Agencja e-commerce`]', 0)
rep('children:`Sklep PrestaShop`', 'children:`Sklepy internetowe`', 0)
rep('[`Sklep PrestaShop`,`/uslugi/', '[`Sklepy internetowe`,`/uslugi/', 0)

# 2) teksty ogólne
rep('sklepy PrestaShop i aplikacje', 'sklepy internetowe i aplikacje', 0)
rep('Jako PrestaShop Expert projektujemy i wdrażamy sklepy oraz strony', 'Projektujemy i wdrażamy sklepy oraz strony')
rep('[`Nowy sklep PrestaShop`,', '[`Sklepy internetowe`,')
rep('[`Migracja na PrestaShop`,`Przenosimy produkty, treści i przekierowania, żeby nie stracić pozycji w Google.`]',
    '[`Migracja sklepu`,`Przenosimy produkty, treści i przekierowania z innej platformy, żeby nie stracić pozycji w Google.`]')

# 3) usługa Sklepy internetowe
rep('t:`Sklep PrestaShop`,h:`Tworzenie sklepów internetowych na PrestaShop, które prowadzą klienta *do zakupu*.`',
    't:`Sklepy internetowe`,h:`Tworzenie sklepów internetowych, które prowadzą klienta *do zakupu*.`')
rep('po płatność BLIK. Wdrażamy na PrestaShop, a po starcie zostajemy przy rozwoju sklepu.',
    'po płatność BLIK. Platformę dobieramy do Twojej sprzedaży, a po starcie zostajemy przy rozwoju sklepu.')
rep('[`Wdrożenie PrestaShop`,`Szablon, moduły i konfiguracja sklepu pod Twój asortyment.`]',
    '[`Wdrożenie sklepu`,`Platforma, szablon i konfiguracja sklepu pod Twój asortyment.`]')
rep('`Tak. Panel PrestaShop jest prosty, a po starcie pokażemy Ci, jak z niego korzystać.`',
    '`Tak. Po starcie pokażemy Ci, jak dodawać produkty i obsługiwać zamówienia w panelu sklepu.`')

# 4) Sklepy internetowe Rzeszów
rep('Projektujemy i tworzymy sklepy internetowe na PrestaShop dla firm z Rzeszowa i całej Polski.',
    'Projektujemy i tworzymy sklepy internetowe dla firm z Rzeszowa i całej Polski.')
rep('[`Wdrożenie na PrestaShop`,`Szablon, moduły i konfiguracja pod Twój asortyment i sposób sprzedaży.`]',
    '[`Wdrożenie sklepu`,`Platformę, szablon i konfigurację dobieramy pod Twój asortyment i sposób sprzedaży.`]')
PLAT = ['Na jakiej platformie powstanie sklep?', 'Dobieramy ją do asortymentu, budżetu i sposobu sprzedaży. Rekomendację z uzasadnieniem otrzymasz razem z wyceną.']
rep('[`Dlaczego PrestaShop?`,`To popularna w Polsce platforma open source: sklep jest Twój, nie płacisz abonamentu za oprogramowanie, a moduły pozwalają podłączyć polskie płatności, kurierów i marketplace’y.`]',
    bt(PLAT), 0)
rep('`Sklep na PrestaShop kosztuje u nas od 5 990 zł netto.', '`Sklep internetowy kosztuje u nas od 5 990 zł netto.', 0)

# 5) Opieka
rep('rozwijamy strony oraz sklepy PrestaShop po starcie.', 'rozwijamy strony oraz sklepy internetowe po starcie.')
rep('`Przede wszystkim dla sklepów PrestaShop i stron, które zaprojektowaliśmy.', '`Przede wszystkim dla stron i sklepów, które zaprojektowaliśmy.')

# 6) Agencja e-commerce (zamiast Agencji PrestaShop)
AG = {
 "k": "Cała Polska", "t": "Agencja e-commerce",
 "h": "Agencja e-commerce, która projektuje strony i sklepy *od UX po sprzedaż*.",
 "lead": "Projektujemy i wdrażamy sklepy internetowe oraz strony dla firm z całej Polski. Zaczynamy od tego, jak Twoi klienci szukają i kupują, platformę dobieramy do Twojej sprzedaży, a po starcie pomagamy sklepowi rosnąć.",
 "img": "@w.szD", "ph": "@w.szM", "price": "od 5 990 zł netto",
 "gets": [
  ["Strategia i ścieżka zakupu", "Sprawdzamy, jak kupują Twoi klienci i co robi konkurencja, zanim powstanie projekt."],
  ["Projekt UX i UI", "Strona główna, kategorie, karta produktu, koszyk i zamówienie, osobno na komputer i telefon."],
  ["Wdrożenie sklepu", "Platformę, szablon i konfigurację dobieramy pod Twój asortyment i sposób sprzedaży."],
  ["Strony firmowe", "Strony, które w kilka sekund mówią, co sprzedajesz, i prowadzą do zapytania."],
  ["Płatności, dostawy i SEO", "BLIK, karty i Apple Pay, kurierzy, szybkie ładowanie i dane strukturalne."],
  ["Audyt UX i opieka", "Po starcie sprawdzamy, co blokuje zakupy, i rozwijamy sklep, od 490 zł netto miesięcznie."]],
 "faq": [
  ["Czy pracujecie z firmami spoza Rzeszowa?", "Tak. Jesteśmy z Rzeszowa, ale strony i sklepy projektujemy dla firm z całej Polski. Rozmowy, warsztaty i odbiory prowadzimy online, a projekt komentujesz bezpośrednio w Figmie."],
  ["Ile kosztuje sklep internetowy?", "Sklep internetowy kosztuje u nas od 5 990 zł netto, a strona firmowa od 4 900 zł netto. Cena zależy od zakresu, integracji i liczby produktów. Wycenę z harmonogramem otrzymasz w 2 dni robocze."],
  PLAT,
  ["Czy przeniesiecie sklep z innej platformy?", "Tak. Przenosimy produkty, klientów, zamówienia i adresy URL z przekierowaniami 301, żeby nie stracić pozycji w Google."],
  ["Co dzieje się po starcie sklepu?", "Pokazujemy, jak prowadzić sklep, a w ramach opieki aktualizujemy go, robimy kopie zapasowe i dodajemy nowe funkcje."]]}
if '"agencja-ecommerce":{k:`Cała Polska`,t:`Agencja e-commerce`' not in s:
    i = s.index('"agencja-ecommerce":{')
    j = s.index(']]}', i) + 3
    s = s[:i] + '"agencja-ecommerce":' + bt(AG) + s[j:]

open(P, 'w', encoding='utf-8').write(s)
# kontrola: PrestaShop poza artykułami i logotypami
art_a = s.find('{s:`strona-firmowa-ktora-zbiera-zapytania`'); art_b = s.find('],P=[...N.map(')
left = [s[m.start()-60:m.start()+30] for m in re.finditer('PrestaShop', s) if not (art_a <= m.start() <= art_b)]
print('ok', len(s)); print('\n'.join(left))

# 7) bez plakietki PrestaShop Expert (decyzja 30.10)
s = open(P, encoding='utf-8').read()
for t in [',l(`div`,{style:{marginTop:28},children:l(Z,{h:52})})', ',l(`div`,{className:`sw-badge`,children:l(Z,{h:64})})', ',l(`div`,{className:`sw-f3-badge`,children:l(Z,{h:44})})']:
    s = s.replace(t, '')
open(P, 'w', encoding='utf-8').write(s)
