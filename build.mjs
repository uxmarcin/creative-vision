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
const SVC = lib.zs, ART = lib.za, CASES = lib.zb, IMG = lib.zw;
const ORG = {
  '@type': 'ProfessionalService', '@id': SITE + '/#org', name: 'Creative Vision', url: SITE + '/',
  logo: SITE + '/img/5nu54lUTuTBKgAmsftppZDI0Y.png', image: SITE + '/img/8UVLCs9mZlPZ2VeP799CS9tiSg.png',
  description: 'Studio projektowe z Rzeszowa: strony internetowe, sklepy PrestaShop, aplikacje mobilne i audyty UX.',
  email: 'kontakt@creative-vision.pl', telephone: '+48534154230', taxID: '7952514299',
  address: { '@type': 'PostalAddress', streetAddress: 'ul. Pańska 13', addressLocality: 'Rzeszów', addressRegion: 'podkarpackie', addressCountry: 'PL' },
  areaServed: [{ '@type': 'City', name: 'Rzeszów' }, { '@type': 'Country', name: 'Polska' }],
  knowsAbout: ['Projektowanie stron internetowych', 'Sklepy internetowe PrestaShop', 'Projektowanie UX/UI', 'Aplikacje mobilne', 'Audyt UX'],
};
const WEBSITE = { '@type': 'WebSite', '@id': SITE + '/#website', url: SITE + '/', name: 'Creative Vision', inLanguage: 'pl-PL', publisher: { '@id': SITE + '/#org' } };
const plain = (t) => String(t || '').replace(/\*/g, '');
const crumbs = (list) => ({ '@type': 'BreadcrumbList', itemListElement: list.map(([name, url], i) => ({ '@type': 'ListItem', position: i + 1, name, item: SITE + url })) });
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
    if (v.faq && v.faq.length) g.push({ '@type': 'FAQPage', mainEntity: v.faq.map(([q, a]) => ({ '@type': 'Question', name: q, acceptedAnswer: { '@type': 'Answer', text: a } })) });
  } else if (view === 'artykul') {
    const a = ART.find((x) => x.s === item);
    if (a) {
      g.push(crumbs([home, ['Wiedza', '/wiedza'], [a.t, p.path]]));
      g.push({ '@type': 'Article', '@id': url + '#article', headline: a.t, description: a.d, image: SITE + IMG[a.img], inLanguage: 'pl-PL', articleSection: a.k, author: { '@id': SITE + '/#org' }, publisher: { '@id': SITE + '/#org' }, mainEntityOfPage: url });
    }
  } else if (view === 'realizacja' && CASES[item]) {
    const c = CASES[item];
    g.push(crumbs([home, ['Realizacje', '/realizacje'], [c.n, p.path]]));
    g.push({ '@type': 'CreativeWork', '@id': url + '#work', name: c.n, description: c.intro, genre: c.k, image: SITE + c.d, creator: { '@id': SITE + '/#org' }, url });
  } else if (view !== 'home') {
    const names = { realizacje: 'Realizacje', 'o-nas': 'O nas', wiedza: 'Wiedza', kontakt: 'Kontakt' };
    if (names[view]) g.push(crumbs([home, [names[view], p.path]]));
  }
  return JSON.stringify({ '@context': 'https://schema.org', '@graph': g }).replace(/</g, '\\u003c');
}
for (const p of pages) {
  const key = p.path === '/' ? 'index' : p.path.slice(1).replace(/\//g, '__');
  const m = meta[key] || {}, s = seo[key] || {};
  const title = s.title || m.title, desc = s.description || m.description;
  const url = SITE + p.path, img = SITE + (m.og_image || '/img/8UVLCs9mZlPZ2VeP799CS9tiSg.png').replace('https://framerusercontent.com/images/', '/img/').replace('https://framerusercontent.com/assets/', '/img/');
  const html = renderToString(jsx(App, p.props));
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
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${esc(title)}">
<meta name="twitter:description" content="${esc(desc)}">
<meta name="twitter:image" content="${img}">
<link rel="icon" href="/img/5nu54lUTuTBKgAmsftppZDI0Y.png">
<link rel="preload" href="/fonts/satoshi-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/fonts/fonts.css">
<style>${css}</style>
<link rel="modulepreload" href="${js}">
<script type="application/ld+json">${jsonld(p, title, desc, url)}</script>
</head>
<body>
<div id="app">${html}</div>
<script type="application/json" id="cv-props">${JSON.stringify(p.props).replace(/</g, '\\u003c')}</script>
<script type="module" src="${js}"></script>
<script src="/fx/fluid.js" defer></script>
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
for (const [f, o] of Object.entries(res.metafile.outputs)) console.log('  js', path.basename(f), (o.bytes / 1024).toFixed(0) + ' KB');
