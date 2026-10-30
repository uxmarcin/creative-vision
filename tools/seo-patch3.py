#!/usr/bin/env python3
"""SEO 2026-10 etap 3: klienci z całej Polski.
Podstrona /agencja-prestashop, nagłówki usług pod frazy ogólnopolskie, adres Pańska 13A,
linki wewnętrzne. Idempotentny."""
import os, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
P = os.path.join(ROOT, 'src/c/shared-lib.mjs')
s = open(P, encoding='utf-8').read()

def bt(x):
    if isinstance(x, str):
        assert '`' not in x and '${' not in x, x
        return '`' + x + '`'
    if isinstance(x, bool):
        return '!0' if x else '!1'
    if isinstance(x, list):
        return '[' + ','.join(bt(i) for i in x) + ']'
    if isinstance(x, dict):
        return '{' + ','.join(f'{k}:{v[1:] if isinstance(v, str) and v.startswith("@") else bt(v)}' for k, v in x.items()) + '}'
    raise TypeError(x)

def rep(old, new, count=1):
    global s
    if new in s:
        return
    assert old in s, 'brak: ' + old[:90]
    s = s.replace(old, new, count)

# 1) nagłówki usług pod frazy ogólnopolskie
rep("h:`Tworzenie sklepów PrestaShop, które prowadzą klienta *do zakupu*.`",
    "h:`Tworzenie sklepów internetowych na PrestaShop, które prowadzą klienta *do zakupu*.`")
rep("h:`Tworzenie aplikacji mobilnych od pomysłu do *pierwszych użytkowników*.`",
    "h:`Projektowanie i tworzenie aplikacji mobilnych od pomysłu do *pierwszych użytkowników*.`")
rep("children:`Creative Vision · Studio projektowe`", "children:`Creative Vision · Studio projektowe · Cała Polska`")
rep("children:`Projektujemy strony, sklepy PrestaShop i aplikacje, które klienci rozumieją od pierwszego kliknięcia i w których chętnie kupują.`",
    "children:`Projektujemy strony, sklepy PrestaShop i aplikacje, które klienci rozumieją od pierwszego kliknięcia i w których chętnie kupują. Pracujemy z firmami z całej Polski.`")

# 2) adres zgodny z wizytówką Google
rep("[`ul. Pańska 13`,l(`br`,{}),`Rzeszów, Polska`]", "[`ul. Pańska 13A`,l(`br`,{}),`Rzeszów, Polska`]")
rep("children:`ul. Pańska 13, Rzeszów`", "children:`ul. Pańska 13A, Rzeszów`")

# 3) podstrona Agencja PrestaShop (szablon usługi)
AG = {
 "k": "Cała Polska", "t": "Agencja PrestaShop",
 "h": "Agencja PrestaShop, która prowadzi sklep *od wdrożenia po rozwój*.",
 "lead": "Projektujemy, wdrażamy i rozwijamy sklepy na PrestaShop dla firm z całej Polski. Pracujemy zdalnie, a przez cały projekt rozmawiasz bezpośrednio z osobą, która projektuje Twój sklep.",
 "img": "@w.szD", "ph": "@w.szM", "price": "od 5 990 zł netto",
 "gets": [
  ["Nowy sklep na PrestaShop", "Projekt UX/UI, szablon i konfiguracja pod Twój asortyment i sposób sprzedaży."],
  ["Migracja na PrestaShop", "Przenosimy produkty, klientów, zamówienia i adresy URL z przekierowaniami 301, żeby nie stracić pozycji w Google."],
  ["Moduły i integracje", "Płatności BLIK, karty i Apple Pay, InPost i kurierzy oraz marketplace’y."],
  ["Poprawki UX i konwersji", "Przechodzimy ścieżkę zakupu jak klient i poprawiamy to, co blokuje zamówienia."],
  ["SEO techniczne", "Szybkie ładowanie, poprawne nagłówki, dane strukturalne produktów i mapa strony."],
  ["Opieka i rozwój", "Aktualizacje, kopie zapasowe i nowe funkcje, od 490 zł netto miesięcznie."]],
 "faq": [
  ["Czy pracujecie z firmami spoza Rzeszowa?", "Tak. Jesteśmy z Rzeszowa, ale sklepy projektujemy dla firm z całej Polski. Rozmowy, warsztaty i odbiory prowadzimy online, a projekt komentujesz bezpośrednio w Figmie."],
  ["Ile kosztuje sklep na PrestaShop?", "Sklep na PrestaShop kosztuje u nas od 5 990 zł netto. Cena zależy od liczby produktów, integracji i zakresu projektu. Wycenę z harmonogramem otrzymasz w 2 dni robocze."],
  ["Dlaczego PrestaShop?", "To popularna w Polsce platforma open source: sklep jest Twój, nie płacisz abonamentu za oprogramowanie, a moduły pozwalają podłączyć polskie płatności, kurierów i marketplace’y."],
  ["Czy przeniesiecie sklep z innej platformy?", "Tak. Przenosimy produkty, klientów, zamówienia i adresy URL z przekierowaniami 301."],
  ["Co dzieje się po starcie sklepu?", "Pokazujemy, jak prowadzić sklep, a w ramach opieki aktualizujemy go, robimy kopie zapasowe i dodajemy nowe funkcje."]]}
anchor = '"aplikacje-mobilne":{k:`Projekt`'
if '"agencja-prestashop":' not in s:
    rep(anchor, '"agencja-prestashop":' + bt(AG) + ',' + anchor)

# 4) stopka: link do agencji
rep("l(`a`,{href:t+`/sklepy-internetowe-rzeszow`,children:`Sklepy internetowe Rzeszów`})",
    "l(`a`,{href:t+`/sklepy-internetowe-rzeszow`,children:`Sklepy internetowe Rzeszów`}),l(`a`,{href:t+`/agencja-prestashop`,children:`Agencja PrestaShop`})")

# 5) linki wewnętrzne z artykułów o PrestaShop
MORE = {
 "co-to-jest-prestashop": ["Szukasz wykonawcy sklepu?", "/agencja-prestashop", "Agencja PrestaShop"],
 "moduly-prestashop": ["Skonfigurujemy moduły w Twoim sklepie:", "/agencja-prestashop", "Agencja PrestaShop"],
}
for slug, m in MORE.items():
    i = s.index(f'{{s:`{slug}`')
    j = s.index('},{s:`', i) if '},{s:`' in s[i:] else s.index('}],P=[', i)
    if m[1] in s[i:j]:
        continue
    assert s[j - 1] == ']', s[j - 5:j + 5]
    s = s[:j - 1] + ',' + bt(['more'] + m) + s[j - 1:]

open(P, 'w', encoding='utf-8').write(s)
print('ok', len(s))
