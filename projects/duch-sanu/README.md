# Duch Sanu – sklep (projekt Creative Vision)

Adres demo: **https://creative-vision.pl/duch-sanu** (noindex, oznaczone jako projekt koncepcyjny).
Figma: https://www.figma.com/design/o3oq29VBHRiwUNF4ucmEoR

## Co jest w demo
- Bramka wieku (rok urodzenia, zapamiętana w przeglądarce).
- Strona główna: hero z konturem jelenia z logo, pasek nazw (krój Sancreek), lada ze szklankami (klik = wybór piwa),
  bestsellery, Bestiariusz (Seria Premium), kreator skrzynki, legenda, mapa Sanu, prezenty, Klub Ducha, opinie.
- `/duch-sanu/piwa` – 24 piwa, rodziny smaku, filtry (seria, % alkoholu, bez glutenu), sortowanie.
- `/duch-sanu/piwo/<slug>` – 24 karty piw: galeria, pakiety 1/3/6 (−5%), profil smaku, nuty, legenda, parowanie, specyfikacja.
- `/duch-sanu/kreator` – skrzynka 3/4/6/12, sloty, rabat, „Niespodzianka Ducha”.
- `/duch-sanu/prezenty` – zestawy, nosidełka, merch.
- Koszyk boczny (localStorage), próg darmowej dostawy 199 zł, minimum 45 zł.
- `/duch-sanu/kasa` – kasa na jednej stronie (gość, BLIK z kodem, Apple/Google Pay, paczkomat/kurier/odbiór w browarze)
  → ekran BLIK → `/duch-sanu/potwierdzenie`. Zamówienia NIE są realizowane.

## Pliki
- `data.mjs` – produkty (ceny, %, ekstrakt, chmiele ze sklepu duchsanu.com; profile smaku i legendy = propozycje).
- `src/style.css`, `src/app.js` – wygląd i logika (bez zależności).
- `build.mjs` – generuje `public/duch-sanu.html` i `public/duch-sanu/**`.
- Grafiki: `public/duch-sanu/img/` (butelki i merch z duchsanu.com, logo wycięte z banera, jeleń jako wektor SVG, zdjęcia AI).

```bash
node projects/duch-sanu/build.mjs   # przebudowa
npm run dev                         # podgląd: http://127.0.0.1:8091/duch-sanu
```
Push na `main` → GitHub Actions wysyła `public/` na serwer (jak cała strona Creative Vision).

## Następny krok: PrestaShop 9
Motyw potomny (Hummingbird) + moduł „Duch Sanu – wygląd sklepu” na wzór GoldenGun (`~/prestashop`):
ten sam HTML/CSS z demo jako szablony Smarty, produkty z `data.mjs` jako import CSV, kreator skrzynki jako moduł
(pakiet produktów + rabat), kasa jednostronicowa z Przelewy24 (BLIK) i InPost Geowidget.
Potrzebne: baza MySQL na hostingu (dane jako sekrety GitHuba – repo jest publiczne) albo osobny hosting sklepu.
