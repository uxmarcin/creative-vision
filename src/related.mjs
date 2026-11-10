// Blok linków wewnętrznych pod stopką: każda strona dostaje odnośniki do poradników i usług
// z opisowymi anchorami (pomaga indeksowaniu artykułów /wiedza i wzmacnia strony lokalne).
const L = [
  ['Usługi', [
    ['/sklepy-internetowe-rzeszow', 'Sklepy internetowe Rzeszów'],
    ['/strony-internetowe-rzeszow', 'Strony internetowe Rzeszów'],
    ['/agencja-ecommerce', 'Agencja e-commerce'],
    ['/uslugi/sklepy-prestashop', 'Sklepy PrestaShop'],
    ['/uslugi/audyt-ux', 'Audyt UX sklepu'],
    ['/uslugi/strony-dla-firm-budowlanych', 'Strony dla firm budowlanych'],
  ]],
  ['Poradniki o sklepach', [
    ['/wiedza/jak-zalozyc-sklep-internetowy', 'Jak założyć sklep internetowy'],
    ['/wiedza/ile-kosztuje-sklep-internetowy', 'Ile kosztuje sklep internetowy'],
    ['/wiedza/jaka-platforma-sklepu-internetowego', 'Jaka platforma sklepu internetowego'],
    ['/wiedza/co-to-jest-prestashop', 'Co to jest PrestaShop'],
    ['/wiedza/moduly-prestashop', 'Moduły PrestaShop'],
    ['/wiedza/jak-wybrac-agencje-e-commerce', 'Jak wybrać agencję e-commerce'],
  ]],
  ['Poradniki o stronach i UX', [
    ['/wiedza/jak-stworzyc-strone-internetowa', 'Jak stworzyć stronę internetową'],
    ['/wiedza/ile-kosztuje-strona-internetowa', 'Ile kosztuje strona internetowa'],
    ['/wiedza/projektowanie-ux-ui', 'Projektowanie UX/UI'],
    ['/wiedza/ux-karty-produktu', 'UX karty produktu'],
    ['/wiedza/ai-w-sklepie-internetowym', 'AI w sklepie internetowym'],
    ['/wiedza/ile-kosztuje-aplikacja-mobilna', 'Ile kosztuje aplikacja mobilna'],
  ]],
];
const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;');
export const RELATED_CSS = '.cv-rel{background:#000;color:#fff;font-family:Satoshi,system-ui,sans-serif;border-top:1px solid rgba(255,255,255,.12)}.cv-rel-w{max-width:1440px;margin:0 auto;padding:56px 88px 64px;display:grid;grid-template-columns:repeat(3,1fr);gap:40px}.cv-rel h2{font-size:12px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;margin:0 0 18px;color:rgba(255,255,255,.62)}.cv-rel ul{list-style:none;margin:0;padding:0;display:grid;gap:10px}.cv-rel a{color:rgba(255,255,255,.85);text-decoration:none;font-size:15px;line-height:1.4}.cv-rel a:hover{color:#fff;text-decoration:underline}@media (max-width:809px){.cv-rel-w{grid-template-columns:1fr;padding:40px 24px 48px;gap:32px}}';
export const RELATED_HTML = `<style>${RELATED_CSS}</style><nav class="cv-rel" aria-label="Usługi i poradniki"><div class="cv-rel-w">` +
  L.map(([h, ls]) => `<div><h2>${esc(h)}</h2><ul>${ls.map(([u, t]) => `<li><a href="${u}">${esc(t)}</a></li>`).join('')}</ul></div>`).join('') +
  '</div></nav>';
