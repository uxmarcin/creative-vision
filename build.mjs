// Build: render stron do statycznego HTML (SSR) + bundle JS do hydracji (Preact)
// Uruchom: node build.mjs   → wynik w public/
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
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
  logo: SITE + '/img/5nu54lUTuTBKgAmsftppZDI0Y.png', image: SITE + '/img/og-creative-vision.png',
  description: 'Studio projektowe z Rzeszowa, które pracuje z firmami z całej Polski: strony internetowe, sklepy internetowe, aplikacje mobilne i audyty UX.',
  email: 'kontakt@creative-vision.pl', telephone: '+48534154230', taxID: '7952514299',
  address: { '@type': 'PostalAddress', streetAddress: 'ul. Pańska 13A', addressLocality: 'Rzeszów', addressRegion: 'podkarpackie', addressCountry: 'PL' },
  areaServed: [{ '@type': 'Country', name: 'Polska' }, { '@type': 'City', name: 'Rzeszów' }],
  knowsAbout: ['Projektowanie stron internetowych', 'Sklepy internetowe', 'PrestaShop', 'E-commerce', 'Projektowanie UX/UI', 'Aplikacje mobilne', 'Audyt UX'],
  priceRange: 'strona od 4 900 zł netto, sklep od 5 990 zł netto',
  founder: { '@id': SITE + '/#marcin' },
};
// katalog usług z cenami: te same fakty co na stronach usług
ORG.hasOfferCatalog = { '@type': 'OfferCatalog', name: 'Usługi Creative Vision', itemListElement: Object.entries(SVC).filter(([k]) => !/rzeszow|agencja/.test(k)).map(([k, v]) => { const m = /od ([\d ]+) zł/.exec(v.price || ''); const o = { '@type': 'Offer', itemOffered: { '@type': 'Service', name: v.t, url: SITE + '/uslugi/' + k } }; if (m) o.priceSpecification = { '@type': 'PriceSpecification', minPrice: +m[1].replace(/ /g, ''), priceCurrency: 'PLN', valueAddedTaxIncluded: false }; return o; }) };
const PERSON = { '@type': 'Person', '@id': SITE + '/#marcin', name: 'Marcin Bednarz', jobTitle: 'Projektant UX', email: 'marcin@creative-vision.pl', worksFor: { '@id': SITE + '/#org' }, image: SITE + '/img/1xr8IhxdxklrGfoPQf1BntgnNA.webp', sameAs: ['https://www.linkedin.com/in/marcin-bednarz-a3328910a/', 'https://marcinbednarz.pl'] };
const WEBSITE = { '@type': 'WebSite', '@id': SITE + '/#website', url: SITE + '/', name: 'Creative Vision', inLanguage: 'pl-PL', publisher: { '@id': SITE + '/#org' } };
const plain = (t) => String(t || '').replace(/\*/g, '');
const crumbs = (list) => ({ '@type': 'BreadcrumbList', itemListElement: list.map(([name, url], i) => ({ '@type': 'ListItem', position: i + 1, name, item: SITE + url })) });
// wstążka żałobna przy logo: żałoba narodowa 8-9.11.2026 (Kraków do 11.11); znika sama po 11.11
const MOURN_FROM = '2026-11-08', MOURN_TO = '2026-11-11';
const MOURN_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 34"><g fill="#fff" stroke="#0B0B0B" stroke-width="1.1" stroke-linejoin="round"><path d="M11 12 15.5 14 9 33 2.5 30.5Z"/><path d="M13 12 8.5 14 15 33 21.5 30.5Z"/><path fill-rule="evenodd" d="M12 13C2 7 4 .5 12 .5s10 6.5 0 12.5ZM12 9.8C9.2 7.8 9.6 5 12 5s2.8 2.8 0 4.8Z"/></g></svg>';
const MOURN = `<script>try{var d=new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Warsaw'}).format(new Date());if(d>='${MOURN_FROM}'&&d<='${MOURN_TO}')document.documentElement.setAttribute('data-zaloba','')}catch(e){}</script>`
  + `<style>html[data-zaloba] .sw-lg{position:relative}html[data-zaloba] .sw-lg::after{content:"";position:absolute;left:-30px;top:-16px;width:30px;height:42px;transform:rotate(-14deg);transform-origin:50% 90%;background:url("data:image/svg+xml,${encodeURIComponent(MOURN_SVG)}") center/contain no-repeat;pointer-events:none}@media (max-width:809px){html[data-zaloba] .sw-lg::after{left:-20px;top:-14px;width:23px;height:32px}}</style>`;
// mobilny pasek kontaktu: telefon jednym dotknięciem i wycena (tylko na telefonach)
const MBAR = '<nav class="cv-mbar" aria-label="Szybki kontakt"><a href="tel:+48534154230">Zadzwoń</a><a href="/kontakt" class="pri">Bezpłatna wycena</a></nav>'
  + '<style>.cv-mbar{display:none}@media (max-width:809px){.cv-mbar{display:grid;grid-template-columns:1fr 1.7fr;gap:8px;position:fixed;left:0;right:0;bottom:0;z-index:40;padding:10px 12px calc(10px + env(safe-area-inset-bottom));background:#000;border-top:1px solid rgba(255,255,255,.14);transform:translateY(110%);visibility:hidden;transition:transform .35s cubic-bezier(.2,.7,.2,1),visibility .35s}.cv-mbar.on{transform:none;visibility:visible}.cv-mbar a{display:flex;align-items:center;justify-content:center;gap:10px;min-height:48px;padding:0 12px;font:600 13px/1 Satoshi,system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase;color:#fff;text-decoration:none;border:1px solid rgba(255,255,255,.4);border-radius:0}.cv-mbar a.pri{background:#fff;color:#0B0B0B;border-color:#fff}.cv-mbar a.pri::after{content:"→";letter-spacing:0}.cv-mbar a:focus-visible{outline:2px solid #fff;outline-offset:3px}body{padding-bottom:calc(68px + env(safe-area-inset-bottom))}body:has(.sw-ck) .cv-mbar,body:has(.sw-mnav.on) .cv-mbar{display:none}}@media (prefers-reduced-motion:reduce){.cv-mbar{transition:none}}</style>';
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
  const g = [ORG, PERSON, WEBSITE, { '@type': 'WebPage', '@id': url + '#page', url, name: title, description: desc, inLanguage: 'pl-PL', isPartOf: { '@id': SITE + '/#website' }, about: { '@id': SITE + '/#org' } }];
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
for (const p of pages) {
  const key = p.path === '/' ? 'index' : p.path.slice(1).replace(/\//g, '__');
  const m = meta[key] || {}, s = seo[key] || {};
  const title = s.title || m.title, desc = s.description || m.description;
  // stara grafika z Framera (8UVL…) pokazywała nieaktualny projekt strony, więc wszędzie nowa
  const url = SITE + p.path, img = SITE + '/img/og-creative-vision.png';
  const html = imgAttrs(renderToString(jsx(App, p.props)));
  const doc = `<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
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
<link rel="preload" href="/fonts/satoshi-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/fonts/fonts.css">
<style>${css}</style>
${MOURN}
<link rel="modulepreload" href="${js}">
<script type="application/ld+json">${jsonld(p, title, desc, url)}</script>
</head>
<body>
<div id="app">${html}</div>
<script type="application/json" id="cv-props">${JSON.stringify(p.props).replace(/</g, '\\u003c')}</script>
<script type="module" src="${js}"></script>
<script src="/fx/fluid.js?v=2611022125" defer></script>
<script src="/fx/reveal.js?v=2611032300" defer></script>
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
const today = new Date().toISOString().slice(0, 10);
fs.writeFileSync(path.join(OUT, 'sitemap.xml'), `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${pages.map((p) => `  <url><loc>${SITE}${p.path}</loc><lastmod>${today}</lastmod></url>`).join('\n')}
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
  '> Studio projektowe z Rzeszowa (ul. Pańska 13A). Projektujemy od UX i wdrażamy strony internetowe dla firm, sklepy internetowe i aplikacje mobilne, prowadzimy audyty UX sklepów i opiekę po starcie. Pracujemy zdalnie z firmami z całej Polski, w Rzeszowie także na miejscu. Bezpłatna wycena z harmonogramem w 2 dni robocze.',
  '',
  'Kontakt: kontakt@creative-vision.pl, +48 534 154 230. NIP 7952514299. Osoba kontaktowa: Marcin Bednarz (projektant UX, założyciel).',
  'Audyty UX sklepów prowadzimy w serwisie https://www.uxaudyt.pl',
  '',
  '## Najważniejsze fakty',
  '- Obszar działania: cała Polska (praca zdalna). Spotkania online albo w biurze w Rzeszowie (ul. Pańska 13A), po wcześniejszym umówieniu.',
  '- Ceny: strona firmowa od 4 900 zł netto, sklep internetowy od 5 990 zł netto, opieka od 490 zł netto miesięcznie; aplikacje mobilne i audyty UX wyceniane indywidualnie. Szczegóły: ' + SITE + '/wiedza/cennik',
  '- Wycena: bezpłatna, z harmonogramem, w 2 dni robocze. Odpowiedź na wiadomość: w ciągu 1 dnia roboczego.',
  '- Cena jest stała po ustaleniu zakresu. Na wdrożoną stronę lub sklep: 12 miesięcy gwarancji na naprawę błędów. Szkolenie z obsługi panelu jest w cenie strony i sklepu.',
  '- Sklepy wdrażamy na PrestaShop (status PrestaShop Expert w programie partnerskim PrestaShop). Przy wyborze platformy doradzamy też inne rozwiązania (np. Shopify, Shoper, WooCommerce), ale na nich nie wdrażamy sklepów.',
  '- Projekt prowadzi Marcin Bednarz: 13 lat projektowania produktów cyfrowych (m.in. B2B: budownictwo, logistyka, medycyna). Portfolio: https://marcinbednarz.pl',
  '- Proces: rozmowa, projekt UX/UI w Figmie (osobno na komputer i telefon), wdrożenie, testy, szkolenie, opieka po starcie.',
  '- Godziny: poniedziałek–piątek 9:00–17:00.',
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
