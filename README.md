# creative-vision.pl

Strona Creative Vision przeniesiona z Framera na własny hosting (cyber_Folks).

## Jak to działa

- `src/c/shared-lib.mjs` — komponent strony (React): wszystkie widoki (główna, usługi, realizacje, wiedza, kontakt),
  menu, formularz kontaktowy (Make.com + FormSubmit), baner cookies.
- `src/fw/` — małe adaptery zastępujące API Framera i Reacta.
- `src/pages.mjs` — lista adresów i parametry widoków (`view`, `item`).
- `src/meta.json` + `src/seo.json` — tytuły, opisy, adresy kanoniczne.
- `build.mjs` — renderuje każdą stronę do statycznego HTML i buduje `public/js/` (Preact w przeglądarce).
- `public/` — gotowa strona, ten katalog trafia na serwer. Fonty (Satoshi, IBM Plex Mono) i grafiki są lokalne.

## Zmiany

```bash
npm install && npm run build   # przebudowuje public/
npm run dev                    # podgląd: http://127.0.0.1:8091
```

Commit + push na `main` → GitHub Actions wysyła `public/` przez FTPS do `public_html/` domeny.
`diag.yml` (uruchamiany przy zmianie pliku) sprawdza stronę na serwerze i porównuje ją z oryginałem.
