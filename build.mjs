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
for (const p of pages) {
  const key = p.path === '/' ? 'index' : p.path.slice(1).replace(/\//g, '__');
  const m = meta[key] || {}, s = seo[key] || {};
  const title = s.title || m.title, desc = s.description || m.description;
  const url = SITE + p.path, img = SITE + (m.og_image || '').replace('https://framerusercontent.com/images/', '/img/').replace('https://framerusercontent.com/assets/', '/img/');
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
<meta property="og:type" content="website">
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
</head>
<body>
<div id="app">${html}</div>
<script type="application/json" id="cv-props">${JSON.stringify(p.props).replace(/</g, '\\u003c')}</script>
<script type="module" src="${js}"></script>
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
