// Zrzuty lokalne do porównania: node tools/shots.cjs <outdir> [base]
const { chromium } = require('playwright'); const fs = require('fs');
const out = process.argv[2], base = process.argv[3] || 'http://127.0.0.1:8090';
const paths = (process.argv[4] || '/,/przykladowy-raport,/regulamin,/polityka-prywatnosci,/audyt-sklepu-internetowego,/audyt-strony-internetowej,/audyt-sklepu-z-opieka,/wdrozenie-poprawek,/polityka-cookies,/audyt-ux,/porzucone-koszyki,/audyt-ux-prestashop,/audyt-ux-a-audyt-seo,/niska-konwersja-sklepu,/karta-produktu-checklista,/ux-sklepu-mobilnego,/audyt-ux-woocommerce,/audyt-ux-shopify,/jak-sprawdzic-ux-sklepu,/darmowy-audyt-sklepu,/zamowienie,/zamowienie-plan').split(',');
(async () => { const b = await chromium.launch(); const errs = [];
  for (const [tag, w, h] of [['d', 1440, 900], ['m', 390, 844]]) {
    const ctx = await b.newContext({ viewport: { width: w, height: h }, locale: 'pl-PL' });
    for (const p of paths) {
      const pg = await ctx.newPage(); const n = (p.replace(/^\//, '').replace(/[\/?=]/g, '_') || 'index');
      pg.on('console', m => { if (m.type() === 'error') errs.push(`${tag} ${p}: ${m.text().slice(0, 200)}`); });
      pg.on('pageerror', e => errs.push(`${tag} ${p} PAGEERROR: ${e.message.slice(0, 200)}`));
      await pg.goto(base + p, { waitUntil: 'networkidle' }); await pg.waitForTimeout(800);
      await pg.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 400) { scrollTo(0, y); await new Promise(r => setTimeout(r, 90)); } scrollTo(0, 0); await new Promise(r => setTimeout(r, 1500)); });
      await pg.screenshot({ path: `${out}/${n}-${tag}.jpg`, fullPage: true, type: 'jpeg', quality: 70 });
      await pg.close();
    }
    await ctx.close();
  }
  console.log(errs.join('\n') || 'no console errors'); await b.close(); })();
