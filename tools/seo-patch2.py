#!/usr/bin/env python3
"""SEO + AI (GEO) etap 2: streszczenia „W skrócie”, usługa Opieka, artykuł „jak stworzyć stronę”,
H1 pod „strony internetowe dla firm”. Idempotentny."""
import json, os, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
P = os.path.join(ROOT, 'src/c/shared-lib.mjs')
s = open(P, encoding='utf-8').read()

def bt(x):
    if isinstance(x, str):
        assert '`' not in x and '${' not in x, x
        return '`' + x + '`'
    if isinstance(x, (int, float)) and not isinstance(x, bool):
        return str(x)
    if isinstance(x, list):
        return '[' + ','.join(bt(i) for i in x) + ']'
    if isinstance(x, dict):
        return '{' + ','.join(f'{k}:{v[1:] if isinstance(v, str) and v.startswith("@") else bt(v)}' for k, v in x.items()) + '}'
    raise TypeError(x)

def rep(old, new):
    global s
    if new in s: return
    assert old in s, 'brak: ' + old[:90]
    s = s.replace(old, new, 1)

# blok „W skrócie”
rep("e[0]===`tip`?c(`aside`,{className:`tip`,children:[l(`b`,{children:`Wskazówka`})",
    "e[0]===`sum`?c(`aside`,{className:`tip`,children:[l(`b`,{children:`W skrócie`}),l(`p`,{children:e[1]})]},t):e[0]===`tip`?c(`aside`,{className:`tip`,children:[l(`b`,{children:`Wskazówka`})")
# etykieta ceny przy abonamencie
rep("children:/^od /.test(e.price)?`Cena projektu`:`Cena`", "children:/^od /.test(e.price)&&!/mies/.test(e.price)?`Cena projektu`:`Cena`")
# H1 strony firmowe
rep("h:`Strona internetowa dla firmy, która zamienia odwiedzających *w zapytania*.`",
    "h:`Strony internetowe dla firm, które zamieniają odwiedzających *w zapytania*.`")

# usługa Opieka
OPIEKA = {
 "k": "Wzrost", "t": "Opieka nad stroną i sklepem",
 "h": "Opieka nad stroną i sklepem, żebyś *nie musiał się martwić*.",
 "lead": "Aktualizujemy, zabezpieczamy i rozwijamy strony oraz sklepy PrestaShop po starcie. Masz jeden kontakt do zmian, poprawek i nowych funkcji.",
 "img": "@w.sd3", "ph": "@w.sm2", "price": "od 490 zł / mies. netto",
 "gets": [
  ["Aktualizacje", "Aktualizujemy system, moduły i wtyczki, a zmiany najpierw sprawdzamy na kopii strony."],
  ["Kopie zapasowe", "Regularne kopie plików i bazy danych, żeby w razie problemu szybko wrócić do działającej wersji."],
  ["Zmiany i poprawki", "Nowe treści, podstrony, banery i poprawki błędów zgłaszasz w jednym miejscu."],
  ["Nowe funkcje", "Rozwijamy stronę lub sklep: integracje, moduły i usprawnienia ścieżki zakupu."],
  ["SEO techniczne", "Pilnujemy szybkości, przekierowań i danych strukturalnych po każdej zmianie."],
  ["Doradztwo", "Podpowiadamy, co warto poprawić, na podstawie analityki i doświadczenia z audytów UX."]],
 "faq": [
  ["Ile kosztuje opieka nad stroną lub sklepem?", "Od 490 zł netto miesięcznie. Zakres i cenę dopasowujemy do wielkości strony lub sklepu i liczby zmian."],
  ["Czy przejmiecie opiekę nad stroną, której nie robiliście?", "Tak, po przeglądzie technicznym. Najpierw sprawdzamy wersje, moduły i kopie zapasowe."],
  ["Dla jakich platform prowadzicie opiekę?", "Przede wszystkim dla sklepów PrestaShop i stron, które zaprojektowaliśmy. O innych platformach porozmawiamy przy wycenie."]]}
anchor = '"aplikacje-mobilne":{k:`Projekt`'
if '"opieka":' not in s:
    rep(anchor, '"opieka":' + bt(OPIEKA) + ',' + anchor)
# karta „Opieka i rozwój” na stronie głównej prowadzi do nowej usługi (8. pozycja listy R)
rep("R=[`sklep-prestashop`,`strony-firmowe`,`sklep-prestashop`,`audyt-ux`,`aplikacje-mobilne`,`sklep-prestashop`,`audyt-ux`,`sklep-prestashop`]",
    "R=[`sklep-prestashop`,`strony-firmowe`,`sklep-prestashop`,`audyt-ux`,`aplikacje-mobilne`,`sklep-prestashop`,`audyt-ux`,`opieka`]")
# stopka
rep("l(`a`,{href:t+`/sklepy-internetowe-rzeszow`,children:`Sklepy internetowe Rzeszów`})]})]}),c(`div`,{className:`sw-f3-mid`",
    "l(`a`,{href:t+`/sklepy-internetowe-rzeszow`,children:`Sklepy internetowe Rzeszów`}),l(`a`,{href:t+`/uslugi/opieka`,children:`Opieka nad stroną`})]})]}),c(`div`,{className:`sw-f3-mid`")

# nowy artykuł
NEW = {
 "s": "jak-stworzyc-strone-internetowa", "k": "Strony",
 "t": "Jak stworzyć stronę internetową dla firmy: 8 kroków od pomysłu do startu",
 "seo": "Jak stworzyć stronę internetową dla firmy krok po kroku | Creative Vision",
 "d": "Cel, domena, struktura, treści, projekt, wdrożenie, SEO i start. Przewodnik dla firm, które chcą mieć stronę przynoszącą zapytania.",
 "r": 7, "img": "simD",
 "b": [
  ["h", "1. Ustal cel i odbiorców"],
  ["p", "Zanim wybierzesz kolory, odpowiedz na dwa pytania: kto ma odwiedzać stronę i co ma na niej zrobić. Dla firmy usługowej celem jest zwykle zapytanie o wycenę albo telefon. Od tego zależy cała reszta."],
  ["h", "2. Wybierz domenę i platformę"],
  ["p", "Domena powinna być krótka i łatwa do podyktowania przez telefon, a dla firmy działającej w Polsce najlepiej z końcówką .pl. Platformę dobierz do potrzeb: kreator lub gotowy szablon na prostą wizytówkę, system taki jak WordPress lub Framer na stronę z treściami, PrestaShop na sklep."],
  ["h", "3. Zaplanuj strukturę"],
  ["p", "Wypisz podstrony: strona główna, usługi (najlepiej każda osobno), realizacje, o firmie i kontakt. Osobne strony usług pomagają w Google, bo każda może odpowiadać na inne zapytanie."],
  ["h", "4. Przygotuj treści"],
  ["p", "Teksty pisz językiem klientów: co robisz, dla kogo, gdzie i od czego zależy cena. Zbierz zdjęcia z prawdziwych realizacji i opinie klientów. Treści to najczęściej niedoszacowany etap."],
  ["h", "5. Zaprojektuj układ i wygląd"],
  ["p", "Zacznij od makiet: kolejność sekcji, nagłówki, przyciski. Dopiero potem projekt graficzny. Równolegle projektuj wersję na telefon, bo z telefonów przychodzi duża część ruchu."],
  ["h", "6. Wdróż stronę"],
  ["p", "Strona powinna szybko się ładować, mieć poprawne nagłówki, działający formularz i certyfikat SSL. Sprawdź ją w kilku przeglądarkach i na telefonach."],
  ["h", "7. Zadbaj o SEO i analitykę"],
  ["ul", ["Unikalny tytuł i opis każdej podstrony.", "Dane strukturalne firmy: nazwa, adres, telefon.", "Mapa strony zgłoszona w Google Search Console.", "Wizytówka firmy w Google, jeśli działasz lokalnie.", "Google Analytics 4 i baner zgód cookies."]],
  ["h", "8. Opublikuj i rozwijaj"],
  ["p", "Po starcie obserwuj, skąd przychodzą odwiedzający i ile wysyłają zapytań. Dodawaj realizacje, artykuły i nowe usługi. Strona, która się rozwija, z czasem zbiera coraz więcej ruchu z Google."],
  ["h", "Sam czy z agencją?"],
  ["p", "Kreator wystarczy, gdy potrzebujesz prostej wizytówki szybko. Agencja ma sens, gdy strona ma być głównym źródłem klientów: zaprojektuje strukturę, treści i ścieżkę do kontaktu. U nas strona firmowa kosztuje od 4 900 zł netto."],
  ["more", "Szukasz wykonawcy?", "/uslugi/strony-firmowe", "Strony internetowe dla firm"]]}
if f'{{s:`{NEW["s"]}`' not in s:
    rep("],P=[...N.map(", "," + bt(NEW) + "],P=[...N.map(")

SUM = {
 "strona-firmowa-ktora-zbiera-zapytania": "Strona firmowa zbiera zapytania, gdy ma: nagłówek mówiący, co robisz i dla kogo, jedno wyraźne wezwanie do działania, ofertę opisaną językiem klienta, prawdziwe realizacje i opinie, cenę lub widełki, prosty formularz oraz szybką wersję na telefon.",
 "ile-kosztuje-strona-internetowa": "Cena strony internetowej zależy od zakresu: strategii, treści, projektu UX i UI, wdrożenia, SEO, integracji i utrzymania. Szablon jest tańszy, projekt na miarę droższy, ale lepiej sprzedaje. W Creative Vision strona firmowa kosztuje od 4 900 zł netto, a opieka od 490 zł netto miesięcznie.",
 "ux-karty-produktu": "Klient klika „Dodaj do koszyka”, gdy karta produktu ma dobre zdjęcia, widoczną cenę z kosztem i czasem dostawy, wyraźny przycisk zakupu, opis odpowiadający na pytania oraz elementy zaufania: opinie, zasady zwrotów i znane metody płatności.",
 "ui-a-ux-roznice": "UX to to, jak strona działa i czy użytkownik łatwo osiąga cel. UI to to, jak wygląda: kolory, typografia, przyciski. Dobry projekt zaczyna od UX, czyli struktury i makiet, a potem dopracowuje UI.",
 "ai-w-sklepie-internetowym": "W małym i średnim sklepie AI realnie pomaga w opisach produktów, wyszukiwarce rozumiejącej pytania, odpowiadaniu na powtarzalne pytania klientów i obróbce zdjęć. Rekomendacje działają dopiero przy większej ilości danych. Uważaj na RODO, błędne fakty i rosnące koszty.",
 "sklep-a-zakupy-przez-asystentow-ai": "Asystenci AI, jak ChatGPT i Gemini, polecają sklepy na podstawie danych: opisów, cen, dostępności i opinii. Żeby sklep był polecany, uzupełnij dane produktów, dodaj dane strukturalne schema.org, prowadź feed produktowy i nie blokuj botów w robots.txt.",
 "ile-kosztuje-sklep-internetowy": "Cena sklepu internetowego zależy od wybranej drogi (platforma abonamentowa, PrestaShop lub WooCommerce, sklep pisany od zera), zakresu projektu, integracji i migracji. Po starcie dochodzą hosting, moduły, prowizje płatności i opieka. W Creative Vision sklep na PrestaShop kosztuje od 5 990 zł netto.",
 "co-to-jest-prestashop": "PrestaShop to darmowe oprogramowanie open source do prowadzenia sklepu internetowego. Instalujesz je na własnym serwerze, rozbudowujesz modułami i nie płacisz abonamentu ani prowizji twórcy platformy. Płacisz za hosting, wdrożenie, płatne moduły i opiekę.",
 "moduly-prestashop": "W polskim sklepie na PrestaShop najczęściej przydają się moduły płatności (BLIK, szybkie przelewy), dostaw (InPost, kurierzy), SEO i szybkości, opinii oraz integracji z marketplace’ami. Instaluj tylko potrzebne moduły, najpierw na kopii sklepu.",
 "projektowanie-ux-ui": "Projektowanie UX/UI ma siedem etapów: rozmowa i cele, analiza, architektura informacji, makiety, prototyp i testy, projekt graficzny oraz przekazanie do wdrożenia. Dzięki temu błędy poprawia się na makietach, a nie w gotowej stronie.",
 "jak-stworzyc-strone-internetowa": "Żeby stworzyć stronę internetową dla firmy: ustal cel i odbiorców, wybierz domenę i platformę, zaplanuj podstrony, przygotuj treści, zaprojektuj układ i wygląd, wdróż stronę, zadbaj o SEO i analitykę, a potem opublikuj ją i rozwijaj. Możesz to zrobić sam w kreatorze albo zlecić agencji.",
}
for slug, txt in SUM.items():
    key = f'{{s:`{slug}`'
    i = s.index(key)
    j = s.index('b:[', i) + 3
    if s[j:j + 7] == '[`sum`,':
        continue
    s = s[:j] + bt(['sum', txt]) + ',' + s[j:]

open(P, 'w', encoding='utf-8').write(s)
print('ok')
