#!/usr/bin/env python3
"""Copy 2026-11: nowe teksty creative-vision.pl (rdzeń, usługi, realizacje, wiedza), szablony
(sec/steps/x w usługach, decyzje i efekt w realizacjach, FAQ na głównej i w kontakcie, wybór usługi
w formularzu), nowe strony (sklepy-prestashop, strony-dla-firm-budowlanych, 3 artykuły), trasy,
seo.json, build.mjs (FAQPage, jobTitle, llms.txt). Dane: tools/copy-2026-11.json.

Idempotentny: każda zamiana sprawdza, czy stary literał istnieje (z oczekiwaną liczbą wystąpień),
a jeśli nowy tekst już jest w pliku, pomija ją. Po wdrożeniu NIE uruchamiać tools/seo-patch*.py.
"""
import json, os, re, sys
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA = json.load(open(os.path.join(ROOT, 'tools/copy-2026-11.json'), encoding='utf-8'))
LIB = os.path.join(ROOT, 'src/c/shared-lib.mjs')
s = open(LIB, encoding='utf-8').read()
stats = {'applied': 0, 'skipped': 0}

def bt(x):
    if isinstance(x, str):
        assert '`' not in x and '${' not in x, x
        return '`' + x + '`'
    if isinstance(x, (int, float)): return str(x)
    if isinstance(x, list): return '[' + ','.join(bt(i) for i in x) + ']'
    if isinstance(x, dict): return '{' + ','.join(f'{k}:{bt(v)}' for k, v in x.items()) + '}'
    raise TypeError(x)

def match_close(t, i):
    """t[i] to '{' lub '['; zwraca indeks odpowiadającego nawiasu (pomija treść w backtickach)."""
    depth, instr = 0, False
    for j in range(i, len(t)):
        ch = t[j]
        if ch == '`': instr = not instr
        elif not instr:
            if ch in '{[(': depth += 1
            elif ch in '}])':
                depth -= 1
                if depth == 0: return j
    raise ValueError('brak domknięcia')

def rep(t, o, n, cnt, first, note):
    if n in t and (first or o not in t or o in n and t.count(o) <= t.count(n)):
        stats['skipped'] += 1
        if os.environ.get('DEBUG'): print('pominięto:', note, o[:80])
        return t
    c = t.count(o)
    assert c == cnt, f'[{note}] oczekiwano {cnt}× literału, jest {c}: {o[:120]!r}'
    stats['applied'] += 1
    return t.replace(o, n, 1 if first else -1)

def block_end(t, pos):
    """koniec bloku artykułu ([`typ`,...]) zawierającego pozycję pos"""
    st = max(t.rfind(m, 0, pos) for m in ('[`p`,', '[`ul`,', '[`ol`,', '[`tip`,', '[`sum`,', '[`h`,', '[`more`,'))
    assert st >= 0
    return match_close(t, st)

for op in DATA['ops']:
    if op['op'] == 'rep':
        s = rep(s, op['old'], op['new'], op['n'], op['first'], op['note'])
    elif op['op'] == 'ins':
        add = ',' + ','.join(op['blocks'])
        assert s.count(op['anchor']) == 1, f"[{op['note']}] kotwica ×{s.count(op['anchor'])}: {op['anchor'][:100]!r}"
        e = block_end(s, s.index(op['anchor']))
        if s[e + 1:e + 1 + len(add)] == add: stats['skipped'] += 1; continue
        s = s[:e + 1] + add + s[e + 1:]; stats['applied'] += 1
    elif op['op'] == 'svc':
        key, js = op['key'], op['js']
        if js in s: stats['skipped'] += 1; continue
        head = f'"{key}":{{k:'
        if head in s:
            i = s.index(head); j = match_close(s, i + len(f'"{key}":'))
            s = s[:i] + js + s[j + 1:]
        else:
            assert s.count('}},B={') == 1
            s = s.replace('}},B={', '},' + js + '},B={', 1)
        stats['applied'] += 1
    elif op['op'] == 'art':
        a = op['article']; head = f'{{s:`{a["s"]}`'
        js = bt(a)
        if js in s: stats['skipped'] += 1; continue
        if head in s:
            i = s.index(head); j = match_close(s, i); s = s[:i] + js + s[j + 1:]
        else:
            assert s.count('],P=[...N.map(') == 1
            s = s.replace('],P=[...N.map(', ',' + js + '],P=[...N.map(', 1)
        stats['applied'] += 1
open(LIB, 'w', encoding='utf-8').write(s)

# trasy
P = os.path.join(ROOT, 'src/pages.mjs'); pg = open(P, encoding='utf-8').read()
for path, view, item in DATA['pages']:
    line = f"  p('{path}', '{view}', '{item}'),\n"
    if f"p('{path}'," not in pg:
        pg = pg.replace('];\n', line + '];\n', 1)
open(P, 'w', encoding='utf-8').write(pg)

# seo.json (ma pierwszeństwo przed meta.json w build.mjs)
SP = os.path.join(ROOT, 'src/seo.json'); seo = json.load(open(SP, encoding='utf-8'))
for k, v in DATA['seo'].items():
    assert len(v['title']) <= 60 and len(v['description']) <= 155, k
    seo[k] = v
json.dump(seo, open(SP, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
open(SP, 'a', encoding='utf-8').write('\n')

# build.mjs: FAQPage na głównej i w kontakcie, tytuł Marcina, cennik, headline realizacji, fakty w llms.txt
BP = os.path.join(ROOT, 'build.mjs'); b = open(BP, encoding='utf-8').read()
def brep(o, n):
    global b
    if n in b: return
    assert b.count(o) == 1, o
    b = b.replace(o, n)
brep("const SVC = lib.zs, ART = lib.za, CASES = lib.zb, IMG = lib.zw;",
     "const SVC = lib.zs, ART = lib.za, CASES = lib.zb, IMG = lib.zw, HOME_FAQ = lib.zf, KONTAKT_FAQ = lib.zk;\nconst faqPage = (items) => ({ '@type': 'FAQPage', mainEntity: items.map(([q, a]) => ({ '@type': 'Question', name: q, acceptedAnswer: { '@type': 'Answer', text: a } })) });")
brep("knowsAbout: ['Projektowanie stron internetowych', 'Sklepy internetowe', 'E-commerce', 'Projektowanie UX/UI', 'Aplikacje mobilne', 'Audyt UX'],",
     "knowsAbout: ['Projektowanie stron internetowych', 'Sklepy internetowe', 'PrestaShop', 'E-commerce', 'Projektowanie UX/UI', 'Aplikacje mobilne', 'Audyt UX'],")
brep("priceRange: 'od 490 zł',", "priceRange: 'strona od 4 900 zł netto, sklep od 5 990 zł netto',")
brep("jobTitle: 'Behavioral Designer / UX Specialist'", "jobTitle: 'Projektant UX'")
brep("    if (v.faq && v.faq.length) g.push({ '@type': 'FAQPage', mainEntity: v.faq.map(([q, a]) => ({ '@type': 'Question', name: q, acceptedAnswer: { '@type': 'Answer', text: a } })) });",
     "    if (v.faq && v.faq.length) g.push(faqPage(v.faq));")
brep("g.push({ '@type': 'CreativeWork', '@id': url + '#work', name: c.n, description: c.intro,",
     "g.push({ '@type': 'CreativeWork', '@id': url + '#work', name: c.n, headline: plain(c.h || c.n), description: c.intro,")
brep("  } else if (view !== 'home') {\n    const names = { realizacje: 'Realizacje', 'o-nas': 'O nas', wiedza: 'Wiedza', kontakt: 'Kontakt' };\n    if (names[view]) g.push(crumbs([home, [names[view], p.path]]));\n  }",
     "  } else if (view !== 'home') {\n    const names = { realizacje: 'Realizacje', 'o-nas': 'O nas', wiedza: 'Wiedza', kontakt: 'Kontakt' };\n    if (names[view]) g.push(crumbs([home, [names[view], p.path]]));\n    if (view === 'kontakt' && KONTAKT_FAQ) g.push(faqPage(KONTAKT_FAQ));\n  } else if (HOME_FAQ) {\n    g.push(faqPage(HOME_FAQ));\n  }")
brep("  '- Wycena: bezpłatna, z harmonogramem, w 2 dni robocze.',\n  '- Platforma sklepu: dobierana do asortymentu, budżetu i sposobu sprzedaży.',",
     "  '- Wycena: bezpłatna, z harmonogramem, w 2 dni robocze. Odpowiedź na wiadomość: w ciągu 1 dnia roboczego.',\n  '- Cena jest stała po ustaleniu zakresu. Na wdrożoną stronę lub sklep: 12 miesięcy gwarancji na naprawę błędów. Szkolenie z obsługi panelu jest w cenie strony i sklepu.',\n  '- Sklepy wdrażamy na PrestaShop (status PrestaShop Expert w programie partnerskim PrestaShop). Przy wyborze platformy doradzamy też inne rozwiązania (np. Shopify, Shoper, WooCommerce), ale na nich nie wdrażamy sklepów.',\n  '- Projekt prowadzi Marcin Bednarz: 13 lat projektowania produktów cyfrowych (m.in. B2B: budownictwo, logistyka, medycyna). Portfolio: https://marcinbednarz.pl',")
brep("  '- Obszar działania: cała Polska (praca zdalna), w Rzeszowie i na Podkarpaciu także spotkania na miejscu.',", "  '- Obszar działania: cała Polska (praca zdalna). Spotkania online albo w biurze w Rzeszowie (ul. Pańska 13A), po wcześniejszym umówieniu.',")
brep("Osoba kontaktowa: Marcin Bednarz (Behavioral Designer / UX Specialist).", "Osoba kontaktowa: Marcin Bednarz (projektant UX, założyciel).")
open(BP, 'w', encoding='utf-8').write(b)
print(f"ok: zastosowano {stats['applied']}, pominięto (już są) {stats['skipped']}")
