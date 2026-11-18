// Build: render stron do statycznego HTML (SSR) + bundle JS do hydracji (Preact)
// Uruchom: node build.mjs   → wynik w public/
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { GTAG_HTML } from './src/gtag.mjs';
const require = createRequire(import.meta.url);
const esbuild = require(process.env.ESBUILD || 'esbuild');
const { renderToString } = await import('react-dom/server');
const { jsx } = await import('react/jsx-runtime');

const ROOT = path.dirname(new URL(import.meta.url).pathname);
const OUT = path.join(ROOT, 'public');
const SITE = 'https://creative-vision.pl';
const meta = JSON.parse(fs.readFileSync(path.join(ROOT, 'src/meta.json'), 'utf8'));
const seo = JSON.parse(fs.readFileSync(path.join(ROOT, 'src/seo.json'), 'utf8'));
const { pages } = await import('./src/pages.mjs');

const res = await esbuild.build({
  entryPoints: { cv: path.join(ROOT, 'src/apps/cv.client.mjs') },
  bundle: true, minify: true, format: 'esm', target: ['es2020'],
  outdir: path.join(OUT, 'js'), entryNames: '[name]-[hash]',
  define: { 'process.env.NODE_ENV': '"production"' }, legalComments: 'none', metafile: true,
  nodePaths: [path.join(ROOT, 'node_modules')],
  alias: { 'react/jsx-runtime': 'preact/jsx-runtime', 'react-dom/client': 'preact/compat/client', 'react-dom': 'preact/compat', react: 'preact/compat' },
});
for (const f of fs.readdirSync(path.join(OUT, 'js'))) if (!Object.keys(res.metafile.outputs).some((o) => path.resolve(o) === path.join(OUT, 'js', f))) fs.unlinkSync(path.join(OUT, 'js', f));
const js = '/js/' + path.basename(Object.keys(res.metafile.outputs).find((o) => o.endsWith('.js')));

const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
const css = fs.readFileSync(path.join(ROOT, 'src/base.css'), 'utf8').replace(/\s*\n\s*/g, '');
const App = (await import('./src/apps/cv.mjs')).default;
const lib = await import('./src/c/shared-lib.mjs');
const SVC = lib.zs, ART = lib.za, CASES = lib.zb, IMG = lib.zw, HOME_FAQ = lib.zf, KONTAKT_FAQ = lib.zk;
const faqPage = (items) => ({ '@type': 'FAQPage', mainEntity: items.map(([q, a]) => ({ '@type': 'Question', name: q, acceptedAnswer: { '@type': 'Answer', text: a } })) });
const ORG = {
  '@type': 'ProfessionalService', '@id': SITE + '/#org', name: 'Creative Vision', url: SITE + '/',
  logo: SITE + '/img/5nu54lUTuTBKgAmsftppZDI0Y.png', image: SITE + '/img/og-creative-vision-2.png',
  description: 'Studio projektowe z Rzeszowa, które pracuje z firmami z całej Polski: strony internetowe, sklepy internetowe, aplikacje mobilne i audyty UX.',
  email: 'kontakt@creative-vision.pl', telephone: '+48534154230', taxID: '7952514299',
  address: { '@type': 'PostalAddress', streetAddress: 'ul. Pańska 13A', addressLocality: 'Rzeszów', addressRegion: 'podkarpackie', addressCountry: 'PL' },
  areaServed: [{ '@type': 'Country', name: 'Polska' }, { '@type': 'City', name: 'Rzeszów' }],
  knowsAbout: ['Projektowanie stron internetowych', 'Sklepy internetowe', 'PrestaShop', 'E-commerce', 'Projektowanie UX/UI', 'Aplikacje mobilne', 'Audyt UX'],
  priceRange: 'strona od 4 900 zł netto, sklep od 5 990 zł netto',
};
// katalog usług z cenami: te same fakty co na stronach usług
ORG.hasOfferCatalog = { '@type': 'OfferCatalog', name: 'Usługi Creative Vision', itemListElement: Object.entries(SVC).filter(([k]) => !/rzeszow|agencja/.test(k)).map(([k, v]) => { const m = /od ([\d ]+) zł/.exec(v.price || ''); const o = { '@type': 'Offer', itemOffered: { '@type': 'Service', name: v.t, url: SITE + '/uslugi/' + k } }; if (m) o.priceSpecification = { '@type': 'PriceSpecification', minPrice: +m[1].replace(/ /g, ''), priceCurrency: 'PLN', valueAddedTaxIncluded: false }; return o; }) };
const PERSON = { '@type': 'Person', '@id': SITE + '/#marcin', name: 'Marcin Bednarz', jobTitle: 'Projektant UX', email: 'kontakt@creative-vision.pl', worksFor: { '@id': SITE + '/#org' }, image: SITE + '/img/1xr8IhxdxklrGfoPQf1BntgnNA.webp', sameAs: ['https://www.linkedin.com/in/marcin-bednarz-a3328910a/', 'https://marcinbednarz.pl'] };
const WEBSITE = { '@type': 'WebSite', '@id': SITE + '/#website', url: SITE + '/', name: 'Creative Vision', inLanguage: 'pl-PL', publisher: { '@id': SITE + '/#org' } };
const plain = (t) => String(t || '').replace(/\*/g, '');
const crumbs = (list) => ({ '@type': 'BreadcrumbList', itemListElement: list.map(([name, url], i) => ({ '@type': 'ListItem', position: i + 1, name, item: SITE + url })) });
// mobilny pasek kontaktu: telefon jednym dotknięciem i wycena (tylko na telefonach)
const MBAR = '<nav class="cv-mbar" aria-label="Szybki kontakt"><a href="tel:+48534154230">Zadzwoń</a><a href="/kontakt" class="pri">Bezpłatna wycena</a></nav>'
  + '<style>.cv-mbar{display:none}@media (max-width:809px){.cv-mbar{display:grid;grid-template-columns:1fr 1.7fr;gap:8px;position:fixed;left:0;right:0;bottom:0;z-index:40;padding:10px 12px calc(10px + env(safe-area-inset-bottom));background:#000;border-top:1px solid rgba(255,255,255,.14);transform:translateY(110%);visibility:hidden;transition:transform .35s cubic-bezier(.2,.7,.2,1),visibility .35s}.cv-mbar.on{transform:none;visibility:visible}.cv-mbar a{display:flex;align-items:center;justify-content:center;gap:10px;min-height:48px;padding:0 12px;font:600 13px/1 Jost,system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase;color:#fff;text-decoration:none;border:1px solid rgba(255,255,255,.4);border-radius:0}.cv-mbar a.pri{background:#fff;color:#0B0B0B;border-color:#fff}.cv-mbar a.pri::after{content:"→";letter-spacing:0}.cv-mbar a:focus-visible{outline:2px solid #fff;outline-offset:3px}body{padding-bottom:calc(68px + env(safe-area-inset-bottom))}body:has(.sw-ck) .cv-mbar,body:has(.sw-mnav.on) .cv-mbar{display:none}}@media (prefers-reduced-motion:reduce){.cv-mbar{transition:none}}</style>';
// wymiary obrazów z nagłówków plików (PNG, JPEG, WebP), żeby przeglądarka znała proporcje przed pobraniem
const dimCache = new Map();
function imgDims(src) {
  if (dimCache.has(src)) return dimCache.get(src);
  let d = null;
  try {
    const b = fs.readFileSync(path.join(OUT, src));
    if (b.toString('ascii', 1, 4) === 'PNG') d = [b.readUInt32BE(16), b.readUInt32BE(20)];
    else if (b.toString('ascii', 0, 4) === 'RIFF' && b.toString('ascii', 8, 12) === 'WEBP') {
      const t = b.toString('ascii', 12, 16);
      if (t === 'VP8X') d = [1 + b.readUIntLE(24, 3), 1 + b.readUIntLE(27, 3)];
      else if (t === 'VP8 ') d = [b.readUInt16LE(26) & 0x3fff, b.readUInt16LE(28) & 0x3fff];
      else if (t === 'VP8L') { const n = b.readUInt32LE(21); d = [(n & 0x3fff) + 1, ((n >> 14) & 0x3fff) + 1]; }
    } else if (b[0] === 0xff && b[1] === 0xd8) {
      for (let i = 2; i < b.length;) {
        const m = b[i + 1], len = b.readUInt16BE(i + 2);
        if (m >= 0xc0 && m <= 0xcf && ![0xc4, 0xc8, 0xcc].includes(m)) { d = [b.readUInt16BE(i + 7), b.readUInt16BE(i + 5)]; break; }
        i += 2 + len;
      }
    }
  } catch {}
  dimCache.set(src, d);
  return d;
}
// <img> z /img/: width/height, decoding=async; poniżej pierwszej sekcji loading=lazy
function imgAttrs(html) {
  const fold = html.indexOf('</section>');
  return html.replace(/<img\b([^>]*?)\/?>/g, (tag, attrs, at) => {
    const m = /\ssrc="(\/img\/[^"]+)"/.exec(attrs);
    if (!m) return tag;
    let a = attrs;
    const d = imgDims(m[1]);
    if (d && !/\swidth=/.test(a)) a += ` width="${d[0]}" height="${d[1]}"`;
    if (!/\sdecoding=/.test(a)) a += ' decoding="async"';
    if (!/\sloading=/.test(a) && fold > -1 && at > fold) a += ' loading="lazy"';
    return '<img' + a + '>';
  });
}
function jsonld(p, title, desc, url) {
  const { view, item } = p.props;
  const g = [ORG, WEBSITE, { '@type': 'WebPage', '@id': url + '#page', url, name: title, description: desc, inLanguage: 'pl-PL', isPartOf: { '@id': SITE + '/#website' }, about: { '@id': SITE + '/#org' } }];
  const home = ['Strona główna', '/'];
  if (view === 'usluga' && SVC[item]) {
    const v = SVC[item];
    g.push(crumbs([home, [v.t, p.path]]));
    const svc = { '@type': 'Service', '@id': url + '#service', name: v.t, serviceType: v.t, description: v.lead, provider: { '@id': SITE + '/#org' }, areaServed: ORG.areaServed, url };
    const m = /od ([\d ]+) zł/.exec(v.price || '');
    if (m) svc.offers = { '@type': 'Offer', priceCurrency: 'PLN', priceSpecification: { '@type': 'PriceSpecification', minPrice: +m[1].replace(/ /g, ''), priceCurrency: 'PLN', valueAddedTaxIncluded: false } };
    g.push(svc);
    if (v.faq && v.faq.length) g.push(faqPage(v.faq));
  } else if (view === 'artykul') {
    const a = ART.find((x) => x.s === item);
    if (a) {
      g.push(crumbs([home, ['Wiedza', '/wiedza'], [a.t, p.path]]));
      g.push({ '@type': 'Article', '@id': url + '#article', headline: a.t, description: a.d, image: SITE + IMG[a.img], inLanguage: 'pl-PL', articleSection: a.k, abstract: (a.b.find((x) => x[0] === 'sum') || [])[1], author: { '@id': SITE + '/#org' }, publisher: { '@id': SITE + '/#org' }, mainEntityOfPage: url });
    }
  } else if (view === 'realizacja' && CASES[item]) {
    const c = CASES[item];
    g.push(crumbs([home, ['Realizacje', '/realizacje'], [c.n, p.path]]));
    g.push({ '@type': 'CreativeWork', '@id': url + '#work', name: c.n, headline: plain(c.h || c.n), description: c.intro, genre: c.k, image: SITE + c.d, creator: { '@id': SITE + '/#org' }, url });
  } else if (view !== 'home') {
    const names = { realizacje: 'Realizacje', 'o-nas': 'O nas', wiedza: 'Wiedza', kontakt: 'Kontakt' };
    if (names[view]) g.push(crumbs([home, [names[view], p.path]]));
    if (view === 'kontakt' && KONTAKT_FAQ) g.push(faqPage(KONTAKT_FAQ));
  } else if (HOME_FAQ) {
    g.push(faqPage(HOME_FAQ));
  }
  return JSON.stringify({ '@context': 'https://schema.org', '@graph': g }).replace(/</g, '\\u003c');
}
const MAPA = [];
for (const p of pages) {
  const key = p.path === '/' ? 'index' : p.path.slice(1).replace(/\//g, '__');
  const m = meta[key] || {}, s = seo[key] || {};
  const title = s.title || m.title, desc = s.description || m.description;
  MAPA.push([p.path, p.props.view, p.props.item, title]);
  // stara grafika z Framera (8UVL…) pokazywała nieaktualny projekt strony, więc wszędzie nowa
  const url = SITE + p.path, img = SITE + '/img/og-creative-vision-2.png';
  const html = imgAttrs(renderToString(jsx(App, p.props)));
  const doc = `<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
${GTAG_HTML}
<title>${esc(title)}</title>
<meta name="description" content="${esc(desc)}">
<link rel="canonical" href="${url}">
<meta name="robots" content="max-image-preview:large">
<meta property="og:type" content="${p.props.view === 'artykul' ? 'article' : 'website'}">
<meta property="og:site_name" content="Creative Vision">
<meta property="og:locale" content="pl_PL">
<meta property="og:url" content="${url}">
<meta property="og:title" content="${esc(title)}">
<meta property="og:description" content="${esc(desc)}">
<meta property="og:image" content="${img}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Creative Vision – strony i sklepy, w których łatwo kupić">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${esc(title)}">
<meta name="twitter:description" content="${esc(desc)}">
<meta name="twitter:image" content="${img}">
<link rel="icon" href="/img/5nu54lUTuTBKgAmsftppZDI0Y.png">
<link rel="preload" href="/fonts/jost-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/fonts/fonts.css?v=2611170300">
${p.path === '/' ? '<script>if(!matchMedia("(prefers-reduced-motion: reduce)").matches){document.documentElement.classList.add("cv-mhjs");setTimeout(function(){var h=document.querySelector(".cv-mh");if(h)h.classList.add("go")},2500)}</script>' : ''}
<style>${css}</style>
<link rel="modulepreload" href="${js}">
<script type="application/ld+json">${jsonld(p, title, desc, url)}</script>
</head>
<body>
<div id="app">${html}</div>
<script type="application/json" id="cv-props">${JSON.stringify(p.props).replace(/</g, '\\u003c')}</script>
<script type="module" src="${js}"></script>
<script src="/fx/fluid.js?v=2611171220" defer></script>
<script src="/fx/reveal.js?v=2611032300" defer></script>
<script src="/fx/mesh.js?v=2611162300" defer></script>
${p.path === '/wiedza/cennik' ? '<script src="/fx/tabs.js?v=2611162330" defer></script>' : ''}
<script>document.addEventListener('click',function(e){if(window.__cvH)return;var b=e.target.closest&&e.target.closest('.sw-burger');if(!b)return;var n=document.querySelector('.sw-mnav');if(!n)return;var o=!n.classList.contains('on');n.classList.toggle('on',o);b.classList.toggle('on',o);b.setAttribute('aria-expanded',o);document.documentElement.classList.toggle('cv-lock',o);if(o)window.__cvY=scrollY},true)</script>
${p.path === '/' ? '<script src="/fx/offer.js?v=2611171225" defer></script><script src="/fx/greview.js?v=2611171120" defer></script>' : ''}
<script src="/fx/case.js?v=2611171435" defer></script><script src="/fx/callback.js?v=2611171832" defer></script>
${p.path === '/kontakt' ? '' : MBAR}
</body>
</html>
`;
  const file = path.join(OUT, p.path === '/' ? 'index.html' : p.path.slice(1) + '.html');
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, doc);
  console.log('✓', p.path.padEnd(48), (doc.length / 1024).toFixed(0) + ' KB');
}
fs.copyFileSync(path.join(ROOT, 'src/404.html'), path.join(OUT, '404.html'));
fs.copyFileSync(path.join(ROOT, 'src/polityka-cookies.html'), path.join(OUT, 'polityka-cookies.html'));
// Mapa strony: wszystkie podstrony w jednym miejscu (zamiast długiej listy linków pod stopką)
{
  const NM = { '/': 'Strona główna', '/uslugi/sklepy-internetowe': 'Sklepy internetowe', '/uslugi/sklepy-prestashop': 'Sklepy PrestaShop', '/uslugi/strony-firmowe': 'Strony firmowe', '/uslugi/strony-dla-firm-budowlanych': 'Strony dla firm budowlanych', '/uslugi/audyt-ux': 'Audyt UX', '/uslugi/aplikacje-mobilne': 'Aplikacje mobilne', '/uslugi/opieka': 'Opieka nad stroną i sklepem', '/strony-internetowe-rzeszow': 'Strony internetowe Rzeszów', '/sklepy-internetowe-rzeszow': 'Sklepy internetowe Rzeszów', '/agencja-ecommerce': 'Agencja e-commerce', '/wiedza/cennik': 'Cennik', '/realizacje': 'Wszystkie realizacje', '/wiedza': 'Wszystkie poradniki', '/o-nas': 'O nas', '/kontakt': 'Kontakt i bezpłatna wycena' };
  const ORD = ['/', '/uslugi/sklepy-internetowe', '/uslugi/sklepy-prestashop', '/sklepy-internetowe-rzeszow', '/agencja-ecommerce', '/uslugi/strony-firmowe', '/strony-internetowe-rzeszow', '/uslugi/strony-dla-firm-budowlanych', '/uslugi/audyt-ux', '/uslugi/aplikacje-mobilne', '/uslugi/opieka', '/wiedza/cennik', '/realizacje', '/wiedza'];
  MAPA.sort((a, b) => { const x = ORD.indexOf(a[0]), y = ORD.indexOf(b[0]); return (x < 0 ? 99 : x) - (y < 0 ? 99 : y); });
  const label = ([pt, view, item, t]) => NM[pt] ? NM[pt]
    : view === 'artykul' ? (ART.find((x) => x.s === item) || {}).t || t
    : view === 'realizacja' && CASES[item] ? CASES[item].n
    : String(t || pt).split(' | ')[0];
  const grp = [
    ['Oferta', (x) => x[0] === '/' || x[1] === 'usluga' || /^\/(strony-internetowe-rzeszow|sklepy-internetowe-rzeszow|agencja-ecommerce|wiedza\/cennik)$/.test(x[0])],
    ['Realizacje', (x) => x[0].startsWith('/realizacje')],
    ['Poradniki', (x) => x[0].startsWith('/wiedza')],
    ['Firma', () => true],
  ];
  const used = new Set();
  const cols = grp.map(([h, f]) => {
    const it = MAPA.filter((x) => !used.has(x[0]) && f(x));
    it.forEach((x) => used.add(x[0]));
    if (h === 'Firma') it.push(['/polityka-cookies', '', '', 'Polityka cookies']);
    return `<section><h2>${h}</h2><ul>${it.map((x) => `<li><a href="${x[0]}">${esc(label(x))}</a></li>`).join('')}</ul></section>`;
  }).join('');
  let t = fs.readFileSync(path.join(ROOT, 'src/polityka-cookies.html'), 'utf8');
  t = t.replace(/<title>[^<]*<\/title>/, '<title>Mapa strony | Creative Vision</title>')
    .replace(/<meta name="description" content="[^"]*">/, '<meta name="description" content="Wszystkie podstrony creative-vision.pl w jednym miejscu: oferta, realizacje, poradniki i informacje o firmie.">')
    .replace('https://creative-vision.pl/polityka-cookies', 'https://creative-vision.pl/mapa-strony')
    .replace('</style>', '.mp{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:40px 56px;margin-top:40px}.mp h2{margin-top:0}.mp ul{list-style:none;padding:0;margin:0}.mp li{margin:0 0 10px}@media (max-width:700px){.mp{grid-template-columns:1fr}}</style>')
    .replace(/<main>[\s\S]*<\/main>/, `<main><p class="k">Creative Vision</p><h1>Mapa strony</h1><p>Wszystkie podstrony w jednym miejscu: oferta, realizacje, poradniki i informacje o firmie.</p><div class="mp">${cols}</div><div class="b"><a class="p" href="/kontakt">Bezpłatna wycena</a><a class="s" href="/">Strona główna</a></div></main>`)
    .replace(/<script>document\.getElementById\('ck'\)[\s\S]*?<\/script>\n?/, '');
  fs.writeFileSync(path.join(OUT, 'mapa-strony.html'), t);
}
const today = new Date().toISOString().slice(0, 10);
fs.writeFileSync(path.join(OUT, 'sitemap.xml'), `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${pages.map((p) => `  <url><loc>${SITE}${p.path}</loc><lastmod>${today}</lastmod></url>`).join('\n')}
  <url><loc>${SITE}/mapa-strony</loc><lastmod>${today}</lastmod></url>
</urlset>
`);
// robots.txt: wprost wpuszczamy wyszukiwarki i asystentów AI
fs.writeFileSync(path.join(OUT, 'robots.txt'), ['User-agent: *', 'Allow: /', '',
  ...['Googlebot', 'Google-Extended', 'Bingbot', 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-User', 'Claude-SearchBot', 'PerplexityBot', 'Perplexity-User', 'Applebot-Extended', 'CCBot']
    .flatMap((b) => ['User-agent: ' + b, 'Allow: /', '']),
  'Sitemap: ' + SITE + '/sitemap.xml', ''].join('\n'));
// llms.txt: zwięzły opis firmy dla modeli językowych (llmstxt.org)
const price = (v) => (v.price || '').replace(/ netto$/, ' netto');
fs.writeFileSync(path.join(OUT, 'llms.txt'), [
  '# Creative Vision',
  '',
  '> Studio projektowe z Rzeszowa (ul. Pańska 13A). Projektujemy od UX i wdrażamy strony internetowe dla firm, sklepy internetowe i aplikacje mobilne, prowadzimy audyty UX sklepów i opiekę po starcie. Pracujemy zdalnie z firmami z całej Polski, w Rzeszowie także na miejscu. Bezpłatna wycena z harmonogramem tego samego dnia.',
  '',
  'Kontakt: kontakt@creative-vision.pl, +48 534 154 230. NIP 7952514299.',
  'Audyty UX sklepów prowadzimy w serwisie https://www.uxaudyt.pl',
  '',
  '## Najważniejsze fakty',
  '- Obszar działania: cała Polska (praca zdalna). Spotkania prowadzimy online.',
  '- Ceny: strona firmowa od 4 900 zł netto, sklep internetowy od 5 990 zł netto, opieka od 490 zł netto miesięcznie; aplikacje mobilne i audyty UX wyceniane indywidualnie. Szczegóły: ' + SITE + '/wiedza/cennik',
  '- Wycena: bezpłatna, z harmonogramem, tego samego dnia. Odpowiedź na wiadomość: tego samego dnia (pn–pt 9:00–18:00).',
  '- Cena jest stała po ustaleniu zakresu. Na wdrożoną stronę lub sklep: 12 miesięcy gwarancji na naprawę błędów. Szkolenie z obsługi panelu jest w cenie strony i sklepu.',
  '- Sklepy wdrażamy na PrestaShop (status PrestaShop Expert w programie partnerskim PrestaShop). Przy wyborze platformy doradzamy też inne rozwiązania (np. Shopify, Shoper, WooCommerce), ale na nich nie wdrażamy sklepów.',
  '- Każdy projekt prowadzi jeden projektant UX; studio ma 13 lat doświadczenia w projektowaniu produktów cyfrowych (m.in. B2B: budownictwo, logistyka, medycyna).',
  '- Proces: rozmowa, projekt UX/UI w Figmie (osobno na komputer i telefon), wdrożenie, testy, szkolenie, opieka po starcie.',
  '- Godziny: poniedziałek–piątek 9:00–18:00.',
  '',
  '## Usługi',
  ...pages.filter((p) => p.props.view === 'usluga' && SVC[p.props.item]).map((p) => { const v = SVC[p.props.item]; return `- [${v.t}](${SITE}${p.path}): ${v.lead} Cena: ${price(v)}.`; }),
  '',
  '## Realizacje',
  ...pages.filter((p) => p.props.view === 'realizacja' && CASES[p.props.item]).map((p) => { const c = CASES[p.props.item]; return `- [${c.n}](${SITE}${p.path}): ${c.k}. ${c.intro}`; }),
  '',
  '## Wiedza',
  ...pages.filter((p) => p.props.view === 'artykul').map((p) => { const a = ART.find((x) => x.s === p.props.item); return a ? `- [${a.t}](${SITE}${p.path}): ${a.d}` : ''; }).filter(Boolean),
  '',
  '## Firma',
  `- [O nas](${SITE}/o-nas)`,
  `- [Kontakt i bezpłatna wycena](${SITE}/kontakt)`,
  `- [Realizacje](${SITE}/realizacje)`,
  '',
].join('\n'));
for (const [f, o] of Object.entries(res.metafile.outputs)) console.log('  js', path.basename(f), (o.bytes / 1024).toFixed(0) + ' KB');
