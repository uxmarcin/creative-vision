#!/usr/bin/env python3
"""SEO 2026-10: nowe podstrony lokalne, artykuły, linki wewnętrzne, eksport danych do JSON-LD.
Skrypt jest idempotentny: sprawdza, czy zmiana już jest, zanim ją wprowadzi."""
import json, os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
P = os.path.join(ROOT, 'src/c/shared-lib.mjs')
s = open(P, encoding='utf-8').read()

def bt(x):
    """wartość JS jako template literal / tablica / obiekt"""
    if isinstance(x, str):
        assert '`' not in x and '${' not in x, x
        return '`' + x + '`'
    if isinstance(x, bool):
        return '!0' if x else '!1'
    if isinstance(x, (int, float)):
        return str(x)
    if isinstance(x, list):
        return '[' + ','.join(bt(i) for i in x) + ']'
    if isinstance(x, dict):
        return '{' + ','.join(f'{k}:{v[1:] if isinstance(v, str) and v.startswith("@") else bt(v)}' for k, v in x.items()) + '}'
    raise TypeError(x)

def rep(old, new, count=1):
    global s
    if new in s:
        return
    assert s.count(old) >= 1, 'brak: ' + old[:80]
    s = s.replace(old, new, count)

# 1) linki „more” mogą prowadzić też wewnątrz serwisu (bez nowej karty)
rep("l(`a`,{href:e[2],target:`_blank`,rel:`noopener`,style:{textDecoration:`underline`",
    "l(`a`,{href:e[2],target:/^http/.test(e[2])?`_blank`:void 0,rel:`noopener`,style:{textDecoration:`underline`")

# 2) H1 usług pod frazy z Semrusha
rep("h:`Sklep na PrestaShop, który prowadzi klienta *do zakupu*.`",
    "h:`Tworzenie sklepów PrestaShop, które prowadzą klienta *do zakupu*.`")
rep("h:`Strona firmowa, która zamienia odwiedzających *w zapytania*.`",
    "h:`Strona internetowa dla firmy, która zamienia odwiedzających *w zapytania*.`")
rep("h:`Audyt UX, który pokazuje, gdzie sklep *traci klientów*.`",
    "h:`Audyt UX sklepu internetowego, który pokazuje, gdzie *tracisz klientów*.`")
rep("h:`Aplikacja mobilna od pomysłu do *pierwszych użytkowników*.`",
    "h:`Tworzenie aplikacji mobilnych od pomysłu do *pierwszych użytkowników*.`")

# 3) podstrony lokalne (szablon usługi)
LOCAL = {
 "strony-internetowe-rzeszow": {
  "k": "Rzeszów", "t": "Strony internetowe Rzeszów",
  "h": "Strony internetowe w Rzeszowie, które *przynoszą zapytania*.",
  "lead": "Projektujemy i tworzymy strony www dla firm z Rzeszowa i całego Podkarpacia. Zaczynamy od tego, czego szukają Twoi klienci, a kończymy na stronie, która szybko się ładuje, dobrze wygląda na telefonie i prowadzi do kontaktu.",
  "img": "@w.sd1", "ph": "@w.sm1", "price": "od 4 900 zł netto",
  "gets": [
   ["Rozmowa na start", "Poznajemy firmę, klientów i konkurencję, zanim powstanie pierwszy projekt."],
   ["Struktura i treści pod Google", "Podstrony i teksty układamy pod frazy, których szukają klienci, na przykład nazwę usługi z miastem."],
   ["Projekt UX i UI", "Makiety i projekt graficzny w Figmie, osobno na komputer i na telefon."],
   ["Szybkie wdrożenie", "Lekka strona, która szybko się ładuje i dobrze wypada w Core Web Vitals."],
   ["SEO lokalne na start", "Tytuły, opisy, dane strukturalne firmy, mapa strony i wskazówki do wizytówki Google."],
   ["Opieka po starcie", "Zmiany, nowe podstrony i rozwój, kiedy ich potrzebujesz."]],
  "faq": [
   ["Ile kosztuje strona internetowa w Rzeszowie?", "Strona firmowa kosztuje u nas od 4 900 zł netto. Dokładna cena zależy od liczby podstron, treści i integracji. Wycenę z harmonogramem otrzymasz w 2 dni robocze."],
   ["Czy pracujecie tylko z firmami z Rzeszowa?", "Nie. Jesteśmy z Rzeszowa, ale projektujemy strony dla firm z całej Polski. Większość pracy prowadzimy online."],
   ["Czy pomożecie z tekstami?", "Tak. Przygotujemy strukturę i teksty na podstawie rozmowy z Tobą, a zdjęcia dobierzemy z Twoich materiałów."],
   ["Czy strona będzie widoczna w Google?", "Przygotujemy ją pod wyszukiwarkę od pierwszego dnia: szybkie ładowanie, poprawne nagłówki, dane strukturalne i mapa strony. Pozycje zależą też od konkurencji i treści, dlatego po starcie podpowiemy, co rozwijać."]]},
 "sklepy-internetowe-rzeszow": {
  "k": "Rzeszów", "t": "Sklepy internetowe Rzeszów",
  "h": "Sklepy internetowe z Rzeszowa, które *sprzedają*.",
  "lead": "Projektujemy i tworzymy sklepy internetowe na PrestaShop dla firm z Rzeszowa i całej Polski. Zaczynamy od ścieżki zakupu klienta, a po starcie pomagamy sklepowi rosnąć.",
  "img": "@w.szD", "ph": "@w.szM", "price": "od 5 990 zł netto",
  "gets": [
   ["Analiza i ścieżka zakupu", "Sprawdzamy, jak kupują Twoi klienci i co robi konkurencja, zanim zaprojektujemy sklep."],
   ["Projekt UX i UI", "Strona główna, kategorie, karta produktu, koszyk i zamówienie, na komputer i na telefon."],
   ["Wdrożenie na PrestaShop", "Szablon, moduły i konfiguracja pod Twój asortyment i sposób sprzedaży."],
   ["Płatności i dostawy", "BLIK, karty, Apple Pay, InPost i kurierzy."],
   ["Migracja i SEO", "Przenosimy produkty i adresy ze starego sklepu z przekierowaniami 301."],
   ["Szkolenie i rozwój", "Pokażemy, jak prowadzić sklep, i pomożemy w kolejnych etapach."]],
  "faq": [
   ["Ile kosztuje sklep internetowy?", "Sklep na PrestaShop kosztuje u nas od 5 990 zł netto. Cena zależy od liczby produktów, integracji i zakresu projektu. Wycenę z harmonogramem otrzymasz w 2 dni robocze."],
   ["Dlaczego PrestaShop?", "To popularna w Polsce platforma open source: sklep jest Twój, nie płacisz abonamentu za oprogramowanie, a moduły pozwalają podłączyć polskie płatności, kurierów i marketplace’y."],
   ["Czy przeniesiecie sklep z innej platformy?", "Tak. Przenosimy produkty, klientów, zamówienia i adresy URL, żeby nie stracić pozycji w Google."],
   ["Czy zajmiecie się sklepem po starcie?", "Tak. W ramach opieki aktualizujemy sklep, robimy kopie zapasowe i dodajemy nowe funkcje, od 490 zł netto miesięcznie."]]},
}
anchor = '"aplikacje-mobilne":{k:`Projekt`'
for slug, d in LOCAL.items():
    if f'"{slug}":' in s:
        continue
    obj = f'"{slug}":' + bt(d) + ','
    rep(anchor, obj + anchor)

# 4) stopka: linki do stron lokalnych
rep("l(`a`,{href:t+`/uslugi/aplikacje-mobilne`,children:`Aplikacje mobilne`})]})]}),c(`div`,{className:`sw-f3-mid`",
    "l(`a`,{href:t+`/uslugi/aplikacje-mobilne`,children:`Aplikacje mobilne`}),l(`a`,{href:t+`/strony-internetowe-rzeszow`,children:`Strony internetowe Rzeszów`}),l(`a`,{href:t+`/sklepy-internetowe-rzeszow`,children:`Sklepy internetowe Rzeszów`})]})]}),c(`div`,{className:`sw-f3-mid`")

# 5) nowe artykuły
ART = json.load(open(os.path.join(ROOT, 'tools/seo-articles.json'), encoding='utf-8'))
for a in ART:
    if f'{{s:`{a["s"]}`' in s:
        continue
    rep("],P=[...N.map(", "," + bt(a) + "],P=[...N.map(")

# 6) linki wewnętrzne w istniejących artykułach (blok „Więcej na ten temat”)
MORE = {
 "ile-kosztuje-strona-internetowa": ["Szukasz wykonawcy w Rzeszowie?", "/strony-internetowe-rzeszow", "Strony internetowe Rzeszów"],
 "strona-firmowa-ktora-zbiera-zapytania": ["Zaprojektujemy taką stronę dla Ciebie:", "/uslugi/strony-firmowe", "Strona internetowa dla firmy"],
 "ux-karty-produktu": ["Sprawdzimy kartę produktu w Twoim sklepie:", "/uslugi/audyt-ux", "Audyt UX sklepu internetowego"],
 "ui-a-ux-roznice": ["Zobacz, jak wygląda cały proces:", "/wiedza/projektowanie-ux-ui", "Projektowanie UX/UI krok po kroku"],
}
for slug, m in MORE.items():
    i = s.index(f'{{s:`{slug}`')
    j = s.index('},{s:`', i) if '},{s:`' in s[i:] else s.index('}],P=[', i)
    blk = s[i:j]
    if m[1] in blk:
        continue
    assert s[j - 1] == ']', s[j - 5:j + 5]
    s = s[:j - 1] + ',' + bt(['more'] + m) + s[j - 1:]

# 7) eksport danych do JSON-LD w build.mjs
rep("export{ue as i,de as n,S as r,fe as t};", "export{ue as i,de as n,S as r,fe as t,z as zs,N as za,B as zb,w as zw};")

open(P, 'w', encoding='utf-8').write(s)
print('ok', len(s))
