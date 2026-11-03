# DESIGN.md · creative-vision.pl

Kierunek: **czarno-biały, typograficzny, spokojny**, w duchu framer.com, z **kolorowym gradientem marki** jako głównym znakiem rozpoznawczym. Mocny kontrast czerni i bieli, cienkie linie zamiast cieni, gradient w wyróżnionych słowach treści. Zapisane z obecnego kodu (`src/c/shared-lib.mjs`, styl `.sw…`) i decyzji właściciela z listopada 2026. Kierunek nie był porównywany z bazą UXKIN (narzędzia niepodłączone).

## Kolor

| Token | Wartość | Rola |
|---|---|---|
| `--bk` | `#0B0B0B` | tekst na jasnym tle, ciemne sekcje (hero, usługi, CTA, stopka) |
| black | `#000000` | tło ciemnych sekcji `.sw-dark` (bez mesh gradientu, decyzja 02.11) |
| white | `#FFFFFF` | tło jasnych sekcji, tekst na ciemnym, przycisk primary na ciemnym |
| `--be` | `#F2EEE9` | ciepły beż: hover przycisku primary, akcenty tła (rzadko) |
| line-dark | `rgba(255,255,255,.12)` | linie, ramki boxów na ciemnym |
| line-light | `rgba(11,11,11,.12)` | linie, ramki boxów na jasnym |
| muted-dark `--md` | `rgba(255,255,255,.62)` (min. .55) | opisy, podpisy na ciemnym |
| muted-light `--ml` | `rgba(11,11,11,.64)` (min. .62) | opisy, podpisy na jasnym; poniżej .62 tekst 13–17px nie spełnia 4.5:1 |
| `#FBFBFB` | `#FBFBFB` | tło jasnej sekcji `.sw-be` (platformy, „Czytaj dalej”) |
| błąd | `#B42A26` | komunikat błędu formularza (5.9:1 na bieli); ramka pola z błędem `#C42B27` |
| **gradient marki** | `#E9685F → #E03F3B → #AC2069 → #88409F → #3E63EF → #51BAD4` | **główny element tożsamości marki** (decyzja właściciela 03.11): wyróżnione słowa w treści i nagłówkach (`.sw-gr`, `*…*` w danych), cienkie akcenty (np. linia pod tytułem realizacji), poświata kursora (25%) |

Zasady: gradient zostaje i jest rozpoznawalnym znakiem marki — używamy go świadomie w treści: jedno wyróżnione słowo lub krótka fraza na nagłówek (strona, sekcja), akcenty liniowe. Nie w tłach sekcji (mesh usunięty 02.11) i nie na całych akapitach. Przyciski bez gradientu: primary = biel/czerń. Kontrast tekstu ≥ 4.5:1: szare opisy przez tokeny `--md` / `--ml`, nie przez `opacity` poniżej .62 na bieli.

## Typografia

- **Krój główny:** Satoshi (lokalnie, 300/400/500/700), stos `'Satoshi',system-ui,sans-serif` (`--serif`).
- **Utility:** IBM Plex Mono 400: tylko linia prawna w stopce (©, NIP). Etykiety formularza, tagi, stanowiska, linki stopki i etykieta nad H1 na głównej to Satoshi. Etykiety sekcji `.sw-k` to Satoshi 600, 12–13px, wersaliki, tracking .12–.14em.
- **Skala (desktop → mobile):**

| Rola | Rozmiar | Interlinia | Waga |
|---|---|---|---|
| H1 hero główna | clamp(40px, 4cqi, 60px) → 40px | 1.02–1.08 | 500, tracking -.035em |
| H1 podstron | clamp(36px, 3.7cqi, 56px) → 34px | 1.06 | 500, -.035em |
| H2 sekcji `.sw-h2` | clamp(32px, 3.3cqi, 48px) → 32px | 1.1 | 400–500, -.02em |
| Tekst-statement (reveal) | clamp(30px, 3.4cqi, 50px) | 1.15 | 500 |
| H3 kart | 21px → 19px | 1.3 | 500 |
| Lead / opis karty | 19–20px → 17px | 1.4–1.5 | 400, kolor muted |
| Body | 17px (min. 16px) | 1.6 | 400 |
| Etykiety, przyciski | 12–13px wersaliki, tracking .1em | — | 500–600 |

Nowe elementy biorą wyłącznie wartości z tabeli. Tytuły kart realizacji i poradników: 21px (H3 kart).

## Odstępy i siatka

- Szerokość treści: max 1440px, padding boczny 88px (desktop) / 24px (mobile) — `.sw-w`.
- Sekcje: 110px pion (desktop), ~72–80px (mobile). Skala odstępów w elementach: 4 · 8 · 12 · 16 · 24 · 32 · 40 · 48 · 56 · 72.
- Container queries (`.sw` ma `container-type:inline-size`): progi 1000px i 560px.

## Kształt i głębia

- Promienie: przyciski **0** (prostokątne), boxy/karty siatki i formularz 28px (mobile 22px), obrazy w kartach 20px, duże zrzuty realizacji 24px, pigułki/plakietki/tagi 999px.
- Cienie: brak w treści. Głębia z cienkich linii 1px. Jedyne cienie: mockupy urządzeń w hero (`0 40px 90px rgba(0,0,0,.55)`).

## Komponenty

- **Przycisk primary** `.sw-ob.sw-ob-w` / `.sw-cta-btn`: białe tło, czarny tekst, wersaliki 13–14px, tracking .1em, strzałka →, padding 15–24px × 24–40px, promień 0. Hover: przezroczyste tło + biały tekst (na ciemnym) / beż.
- **Przycisk secondary** `.sw-ob`: obrys 1px currentColor, przezroczysty, ten sam tekst. Hover: wypełnienie białe.
- **Link tekstowy** `.sw-tl`: wersaliki, podkreślenie 1px, strzałka.
- **Siatka boxów (styl Framer)** `.fx-grid` + `.fx-cell`: ramka 1px + promień 28px, komórki rozdzielone liniami 1px (box-shadow), ikona Lucide 26px u góry, na dole H3 + szary opis; całość klikalna, hover tło +3.5% bieli i ikona -4px. Wariant jasny `.fx-lt`.
- **Karta hero usługi** `.fh-card`: ramka 1px, promień 28px; góra 2 kolumny (opis + cena | zrzut), dół pasek 4 komórek (logo + podpis).
- **Karuzela logo** `.fx-logos`: logo + szary podpis, przesuw 46s, maska zanikania na brzegach, pauza na hover.
- **Nawigacja**: logo + linki zaraz za nim (18px, białe, podkreślenie animowane), CTA primary po prawej; mobile: burger + pełnoekranowe menu.
- **Hero podstron**: wyrównany do lewej; breadcrumb, etykieta szara wersalikami, H1, jeden przycisk primary.
- **FAQ** `.sw-faq`: `details` z linią dolną, „+” obracany do „×”.
- **Cennik** `.cv-pr`: 3 komórki w ramce 1px / 28px (strona, sklep, opieka), cena 36–48px 500, „od” i „netto” 15px muted; cienka linia gradientu u góry wyróżnionej komórki i na hover; pod spodem 4 fakty (wycena w 2 dni, stała cena, 12 mies. gwarancji, szkolenie w cenie) z ikoną check i przycisk primary. Na ciemnym na głównej, na jasnym w `/wiedza/cennik`.
- **Pas CTA** `.sw-cta`: jeden na stronę, wyrównany do lewej, cienka linia gradientu u góry, H2 z jednym słowem w gradiencie, przycisk primary i czas odpowiedzi obok. Stopka nie powtarza CTA.
- **Formularz**: etykiety Satoshi 600 13px wersaliki, pola z linią 1px; błąd pod polem (`.sw-err`, `aria-invalid`, `aria-describedby`), sukces zastępuje formularz komunikatem `role=status` z linią gradientu.
- **Fokus**: `outline 2px currentColor`, offset 4px, bez zaokrągleń; w komórkach siatki obrys wewnętrzny (`box-shadow inset`). Link „Przejdź do treści” na początku każdej strony.
- **Ruch**: sekcje poniżej pierwszego ekranu wjeżdżają (opacity + 28px, 0.9s) dopiero z JS; bez JS i przy `prefers-reduced-motion` wszystko widoczne od razu. Hover kart: zoom zdjęcia 1.04, linia gradientu pod tytułem.
- **Mobilny pasek kontaktu** `.cv-mbar`: na całą szerokość przy dolnej krawędzi, czarny z linią 1px, przyciski prostokątne wersalikami (primary biały); pojawia się, gdy przycisk z hero zniknie z ekranu, chowa się przy otwartym menu i banerze cookies.

## Obrazy i ikony

- Ikony: **Lucide**, obrys 1.5 (siatki), 1–1.2 (proces). Bez emoji.
- Zrzuty realizacji w ramce przeglądarki/telefonu albo pełne w kartach; zdjęcia prawdziwe, bez stockowych ludzi.
- Grafiki w WebP (maks. 1600px szerokości), z `width`/`height`; poniżej pierwszej sekcji `loading="lazy"`.
- Loga klientów i platform w bieli (filtr `brightness(0) invert(1)` na ciemnym).

## Czego unikamy

Gradientów w tłach sekcji (mesh usunięty), innych gradientów niż gradient marki, szklanych efektów, trzech identycznych kart „feature”, wszystkiego wyśrodkowanego, gradientu na całych akapitach, zmyślonych liczb i opinii.
