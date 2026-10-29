// Pomiar układu: pozycje i style elementów wewnątrz komponentów (do porównania z oryginałem)
// node tools/probe.cjs <out.json> <base>
const { chromium } = require('playwright'); const fs = require('fs');
const out = process.argv[2], base = process.argv[3];
const paths = (process.env.PATHS || '/,/realizacje/sztuka-wykonczen,/realizacje/bud-mad,/uslugi/sklep-prestashop,/realizacje,/o-nas,/wiedza,/kontakt,/wiedza/ux-karty-produktu').split(',');
const probe = () => {
  const roots = [...document.querySelectorAll('.sw, .cvck, .cv-ck')].filter(r => !r.parentElement.closest('.sw, .cvck, .cv-ck'));
  const res = {};
  roots.forEach((root, ri) => {
    const rr = root.getBoundingClientRect(); const list = [];
    const all = [root, ...root.querySelectorAll('*')];
    for (const el of all) {
      if (el.closest('.uxcc')) continue;
      const r = el.getBoundingClientRect(); const cs = getComputedStyle(el);
      list.push([el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).filter(c => c !== 'in').join('.') : ''),
        Math.round(r.left - rr.left), Math.round(r.top - rr.top), Math.round(r.width), Math.round(r.height), cs.fontSize, cs.fontFamily.split(',')[0], cs.fontWeight, cs.lineHeight]);
    }
    res[(root.className || root.tagName) + '#' + ri] = { y: Math.round(rr.top + scrollY), x: Math.round(rr.left), w: Math.round(rr.width), n: list };
  });
  return res;
};
(async () => { const b = await chromium.launch(); const data = {};
  for (const [tag, w] of [['d', 1440], ['m', 390]]) {
    const ctx = await b.newContext({ viewport: { width: w, height: 900 }, locale: 'pl-PL', reducedMotion: 'reduce' });
    await ctx.addInitScript(() => { try {  } catch (e) {} });
    for (const p of paths) {
      const pg = await ctx.newPage();
      await pg.goto(base + p, { waitUntil: 'networkidle', timeout: 60000 }); await pg.waitForTimeout(2500);
      data[tag + ' ' + p] = await pg.evaluate(probe); await pg.close();
    }
    await ctx.close();
  }
  fs.writeFileSync(out, JSON.stringify(data)); await b.close(); })();
