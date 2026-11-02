#!/usr/bin/env python3
"""Design 2026-11: boxy z ikonami w stylu Framera (usługi, Co otrzymasz, Jak pracujemy),
karuzela logo klientów z podpisami, tekst „Creative Vision to studio projektowe…”
jako osobna sekcja z podświetlaniem słów przy przewijaniu. Idempotentny."""
import os, re, glob
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
P = os.path.join(ROOT, 'src/c/shared-lib.mjs')
LUCIDE = os.environ.get('LUCIDE', '')
s = open(P, encoding='utf-8').read()
MARK = '/*cv-fx1*/'
if MARK in s:
    print('już zastosowany'); raise SystemExit

def rep(old, new, count=1):
    global s
    assert old in s, 'brak: ' + old[:120]
    s = s.replace(old, new, count)

# ---------- ikony (Lucide, ISC) ----------
NAMES = ['shopping-bag', 'app-window', 'arrow-right-left', 'scan-search', 'smartphone', 'credit-card', 'search',
         'life-buoy', 'pen-tool', 'code-xml', 'package', 'route', 'refresh-cw', 'messages-square', 'file-text',
         'mouse-pointer-click', 'database-backup', 'rocket', 'sparkles', 'chart-line', 'graduation-cap',
         'layout-template', 'list-checks', 'map-pin', 'wrench', 'message-circle', 'plug', 'zap']
ico = {}
for n in NAMES:
    svg = open(os.path.join(LUCIDE, n + '.svg')).read()
    inner = re.search(r'<svg[^>]*>(.*)</svg>', svg, re.S).group(1)
    inner = re.sub(r'\s*\n\s*', '', inner).strip()
    assert '`' not in inner and '${' not in inner
    ico[n] = inner
js_ico = '{' + ','.join(f'"{k}":`{v}`' for k, v in ico.items()) + '}'
# dobór ikony po tytule (Co otrzymasz)
PICK = [
    (r'płatn', 'credit-card'), (r'migrac', 'arrow-right-left'), (r'import', 'package'), (r'^seo|seo ', 'search'),
    (r'szkolen', 'graduation-cap'), (r'opiek', 'life-buoy'), (r'audyt', 'scan-search'), (r'ścieżk|strategi', 'route'),
    (r'prototyp', 'mouse-pointer-click'), (r'projekt', 'pen-tool'), (r'wdroż', 'code-xml'), (r'framer', 'code-xml'),
    (r'aktualiz', 'refresh-cw'), (r'kopie', 'database-backup'), (r'nowe funkcje|rozwój', 'rocket'),
    (r'mvp', 'zap'), (r'raport', 'list-checks'), (r'pomiar|analityk|wynik', 'chart-line'),
    (r'rozmow|doradztw|warsztat', 'messages-square'), (r'treści|struktur', 'file-text'),
    (r'mobiln', 'smartphone'), (r'lokaln', 'map-pin'), (r'zmiany|poprawk', 'wrench'), (r'strony', 'app-window'),
    (r'publikac', 'rocket'), (r'formularz', 'message-circle'),
]
js_pick = '[' + ','.join(f'[/{p}/i,`{n}`]' for p, n in PICK) + ']'
HELP = (f',cvIco={js_ico},cvPick={js_pick},'
        'cvI=(n)=>l(`span`,{className:`fx-ic`,"aria-hidden":`true`,dangerouslySetInnerHTML:{__html:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">`+(cvIco[n]||cvIco.sparkles)+`</svg>`}}),'
        'cvIg=(t)=>{let p=cvPick.find(([r])=>r.test(t));return cvI(p?p[1]:`sparkles`)},'
        'cvW=(t)=>t.split(` `).map((w,i)=>l(`span`,{className:`w`,children:w+` `},i)),'
        'cvSvcI=[`shopping-bag`,`app-window`,`arrow-right-left`,`scan-search`,`smartphone`,`credit-card`,`search`,`life-buoy`]')
_imp = 'from"../fw/framer.mjs";'
_i = s.index(_imp) + len(_imp)
s = s[:_i] + 'const ' + HELP[1:] + ';' + s[_i:]

# ---------- usługi na stronie głównej ----------
rep("l(`div`,{className:`sw-svc`,children:oe.map(([e,n],r)=>c(`div`,{children:[l(`span`,{children:`0`+(r+1)}),c(`div`,{children:[l(`h3`,{children:e}),l(`p`,{children:n}),l(`a`,{className:`sw-more`,href:t+`/uslugi/`+(R[r]||`sklepy-internetowe`),children:`Dowiedz się więcej`})]})]},e))})",
    "l(`div`,{className:`fx-grid fx-4`,children:oe.map(([e,n],r)=>c(`a`,{className:`fx-cell`,href:t+`/uslugi/`+(R[r]||`sklepy-internetowe`),children:[cvI(cvSvcI[r]),c(`div`,{className:`fx-tx`,children:[l(`h3`,{children:e}),l(`p`,{children:n}),l(`span`,{className:`fx-more`,children:`Dowiedz się więcej`})]})]},e))})")

# ---------- Co otrzymasz ----------
rep("l(`div`,{className:`sw-gets`,children:e.gets.map(([e,t])=>c(`div`,{children:[l(`h3`,{children:e}),l(`p`,{children:t})]},e))})",
    "l(`div`,{className:`fx-grid fx-3 fx-lt`,children:e.gets.map(([e,t])=>c(`div`,{className:`fx-cell`,children:[cvIg(e),c(`div`,{className:`fx-tx`,children:[l(`h3`,{children:e}),l(`p`,{children:t})]})]},e))})")

# ---------- Jak pracujemy ----------
rep("l(`div`,{className:`sw-steps`,children:U.map(([e,t],n)=>c(`div`,{children:[l(`i`,{className:`si`,children:H[n]}),c(`div`,{className:`bd`,children:[l(`span`,{children:`0`+(n+1)}),l(`h3`,{children:e}),l(`p`,{children:t})]})]},e))})",
    "l(`div`,{className:`fx-grid fx-4 fx-steps`,children:U.map(([e,t],n)=>c(`div`,{className:`fx-cell`,children:[c(`div`,{className:`fx-top`,children:[l(`span`,{className:`fx-ic`,children:H[n]}),l(`span`,{className:`fx-n`,children:`0`+(n+1)})]}),c(`div`,{className:`fx-tx`,children:[l(`h3`,{children:e}),l(`p`,{children:t})]})]},e))})")

# ---------- tekst o studiu: osobna sekcja z podświetlaniem ----------
rep(",c(`div`,{className:`sw-about`,children:[c(`p`,{children:[`Creative Vision to studio projektowe. Projektujemy od UX `,l(`br`,{}),`i wdrażamy strony firmowe, sklepy internetowe i aplikacje mobilne, `,l(`br`,{}),`a po starcie zostajemy przy ich rozwoju.`]})]})", "")
rep("l(`div`,{className:`sw-w`,children:c(`div`,{className:`sw-trust`,children:[",
    "l(`div`,{className:`sw-w cv-intro`,children:l(`p`,{className:`cv-reveal`,children:cvW(`Creative Vision to studio projektowe. Projektujemy od UX i wdrażamy strony firmowe, sklepy internetowe i aplikacje mobilne, a po starcie zostajemy przy ich rozwoju.`)})}),l(`div`,{className:`sw-w`,children:c(`div`,{className:`sw-trust`,children:[")

# ---------- logo klientów: karuzela z podpisami ----------
old_lw = re.search(r"c\(`div`,\{className:`sw-lw`,children:\[.*?\]\}\),c\(`div`,\{className:`sw-stack`", s, re.S).group(0)
items = [
    ("l(`svg`,{viewBox:W[`sztuka-wykonczen`].vb,style:{height:38},\"aria-hidden\":`true`,children:l(`path`,{fill:`currentColor`,d:W[`sztuka-wykonczen`].d})})", 'Sztuka Wykończeń', 'Strona firmowa · projekt UX'),
    ("l(g,{k:`simrun`,h:32,n:`Simrun`})", 'Simrun', 'Strona firmowa · B2B'),
    ("l(g,{k:`marketvita`,h:24,n:`Marketvita`})", 'Marketvita', ''),
    ("l(g,{k:`c4`,h:18,n:`4clubbers`})", '4clubbers', 'Aplikacja mobilna · MVP'),
    ("l(g,{k:`budmad`,h:30,n:`Bud-Mad`})", 'Bud-Mad', 'Strona firmowa · usługi budowlane'),
    ("l(g,{k:`uxaudyt`,h:26,n:`uxaudyt.pl`})", 'uxaudyt.pl', 'Usługa online · płatności'),
]
def item(it, hidden):
    logo, name, cap = it
    cap_el = f",l(`small`,{{children:`{cap}`}})" if cap else ''
    aria = ',"aria-hidden":`true`' if hidden else ''
    return f"c(`div`,{{className:`it`{aria},children:[l(`span`,{{className:`lg`,role:`img`,\"aria-label\":`{name}`,children:{logo}}}){cap_el}]}})"
track = ','.join(item(it, False) for it in items) + ',' + ','.join(item(it, True) for it in items)
new_lw = "c(`div`,{className:`fx-logos`,children:c(`div`,{className:`fx-track`,children:[" + track + "]})}),c(`div`,{className:`sw-stack`"
s = s.replace(old_lw, new_lw, 1)

# ---------- CSS ----------
CSS = MARK + r"""
#uslugi.sw-dark{background:#000}
.fx-grid{--ln:rgba(255,255,255,.12);display:grid;margin-top:72px;border:1px solid var(--ln);border-radius:28px;overflow:hidden}
.fx-4{grid-template-columns:repeat(4,minmax(0,1fr))}.fx-3{grid-template-columns:repeat(3,minmax(0,1fr))}
.fx-lt{--ln:rgba(11,11,11,.12);margin-top:0}
.fx-cell{position:relative;display:flex;flex-direction:column;justify-content:space-between;gap:56px;min-height:300px;padding:40px 36px 38px;box-shadow:1px 0 0 0 var(--ln),0 1px 0 0 var(--ln);color:inherit;text-decoration:none;transition:background .4s}
.fx-lt .fx-cell{min-height:250px}
a.fx-cell:hover{background:rgba(255,255,255,.035)}
.fx-ic{display:block;width:26px;height:26px;line-height:0}.fx-ic svg{width:100%;height:100%;display:block}
.fx-top{display:flex;align-items:center;justify-content:space-between}.fx-n{font-size:13px;letter-spacing:.06em;opacity:.45}
.fx-tx h3{font-family:var(--serif);font-weight:500;font-size:21px;line-height:1.3;letter-spacing:-.01em}
.fx-tx p{font-family:var(--serif);font-size:19px;line-height:1.4;letter-spacing:-.01em;opacity:.5;margin-top:4px!important;max-width:34ch}
.fx-lt .fx-tx p{opacity:.55}
.fx-more{display:inline-block;margin-top:22px;font-size:12px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;opacity:.55;transition:opacity .3s}
a.fx-cell:hover .fx-more{opacity:1}
a.fx-cell .fx-ic{transition:transform .5s cubic-bezier(.2,.7,.2,1)}a.fx-cell:hover .fx-ic{transform:translateY(-4px)}
.fx-steps{margin-top:56px}.fx-steps .fx-ic{width:30px;height:30px}.fx-steps .fx-ic svg{stroke-width:1.2}
.fx-logos{position:relative;overflow:hidden;padding:44px 0 8px;-webkit-mask-image:linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent);mask-image:linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent)}
.fx-track{display:flex;width:max-content;animation:fx-mq 46s linear infinite}
.fx-logos:hover .fx-track{animation-play-state:paused}
.fx-track .it{display:flex;flex-direction:column;align-items:flex-start;gap:16px;width:300px;flex:none;padding-right:56px;color:#fff}
.fx-track .lg{display:flex;align-items:center;height:44px;line-height:0}.fx-track .lg svg{display:block;width:auto;max-width:220px}
.fx-track small{font-family:var(--serif);font-size:16px;line-height:1.35;opacity:.45}
@keyframes fx-mq{to{transform:translateX(-50%)}}
.sw-hero{padding-bottom:40px}.cv-intro{padding-top:0;padding-bottom:24px}
@container (min-width: 1001px){.sw-in .sw-hero{min-height:920px}}
.cv-reveal{font-family:var(--serif);font-weight:500!important;font-size:clamp(32px,4.6cqi,66px);line-height:1.12;letter-spacing:-.035em;max-width:24ch}
.cv-rv .cv-reveal .w{color:rgba(255,255,255,.2);transition:color .35s}.cv-rv .cv-reveal .w.on{color:#fff}
@media (prefers-reduced-motion:reduce){.fx-track{animation:none}}
@container (max-width: 1000px){.fx-4,.fx-3{grid-template-columns:repeat(2,minmax(0,1fr))}.fx-cell{min-height:260px;padding:32px 28px}.cv-intro{padding-top:40px}}
@container (max-width: 560px){.fx-4,.fx-3{grid-template-columns:1fr}.fx-grid{margin-top:40px;border-radius:22px}.fx-lt{margin-top:0}.fx-cell{min-height:0;gap:36px;padding:28px 24px}.fx-tx h3{font-size:19px}.fx-tx p{font-size:17px}.fx-track .it{width:240px;padding-right:36px}.fx-track .lg svg{max-width:170px}.fx-track small{font-size:14px}.cv-intro{padding-top:24px;padding-bottom:8px}.cv-reveal{font-size:32px}}
"""
assert '`' not in CSS and '${' not in CSS
rep("`}}),l(ae,{}),c(`div`,{className:`sw-top`", CSS + "`}}),l(ae,{}),c(`div`,{className:`sw-top`")

open(P, 'w', encoding='utf-8').write(s)
print('ok', len(s))
