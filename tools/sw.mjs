import { chromium, devices } from 'playwright';
import { execSync } from 'node:child_process';
const base = 'https://sztukawykonczen.pl';
const save = (f, out, w) => execSync(`python3 -c "from PIL import Image;im=Image.open('${f}').convert('RGB');im=im.resize((${w},round(im.size[1]*${w}/im.size[0])),Image.LANCZOS);im.save('public/img/${out}.webp','WEBP',quality=90,method=6)"`);
const cookies = async p => { for (const re of [/akceptuj/i, /zgadzam/i, /accept/i, /rozumiem/i, /zezwól/i, /allow all/i]) { const btn = p.getByRole('button', { name: re }).first(); if (await btn.isVisible().catch(() => false)) { await btn.click().catch(() => {}); await p.waitForTimeout(800); break; } } };
const reveal = async p => { let H = await p.evaluate(() => document.documentElement.scrollHeight); for (let y = 0; y < H; y += 250) { await p.evaluate(y => scrollTo(0, y), y); await p.waitForTimeout(350); } await p.evaluate(() => scrollTo(0, 0)); await p.waitForTimeout(1500); };
const CLEAN = 'html,body{overflow-x:hidden!important;max-width:100vw!important}';
const hideFloat = p => p.evaluate(() => { document.querySelectorAll('body *').forEach(e => { const c = getComputedStyle(e); if ((c.position === 'fixed' || c.position === 'sticky') && e.offsetHeight < innerHeight * .6) e.style.setProperty('display', 'none', 'important'); }); });
const hideWidgets = p => p.evaluate(() => { document.querySelectorAll('body *').forEach(e => { const c = getComputedStyle(e), r = e.getBoundingClientRect(); if (c.position === 'fixed' && r.top > innerHeight * .5 && r.width < 140) e.style.setProperty('display', 'none', 'important'); }); });
const b = await chromium.launch();
// desktop
{
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, reducedMotion: 'reduce' });
  const p = await ctx.newPage();
  await p.goto(base, { waitUntil: 'networkidle', timeout: 60000 }); await p.waitForTimeout(1500); await cookies(p);
  const links = await p.evaluate(() => [...new Set([...document.querySelectorAll('a[href]')].map(a => a.href).filter(h => h.startsWith(location.origin) && !/#|\.(pdf|jpg|png)|tel:|mailto:/.test(h)))].slice(0, 9));
  console.log('links', JSON.stringify(links));
  let n = 0;
  for (const u of [base, ...links.filter(l => l.replace(/\/$/, '') !== base)]) {
    try {
      await p.goto(u, { waitUntil: 'networkidle', timeout: 45000 }); await cookies(p); await p.addStyleTag({ content: CLEAN }); await reveal(p); await hideWidgets(p);
      n++; const nn = String(n).padStart(2, '0'); await p.screenshot({ path: `/tmp/d${nn}.png` }); save(`/tmp/d${nn}.png`, `sw3-d-${nn}`, 2880); console.log('desk', nn, u);
      if (n === 1) { for (const y of [900, 1800, 2700]) { await p.evaluate(y => scrollTo(0, y), y); await p.waitForTimeout(1800); await hideFloat(p); n++; const m = String(n).padStart(2, '0'); await p.screenshot({ path: `/tmp/d${m}.png` }); save(`/tmp/d${m}.png`, `sw3-d-${m}`, 2880); console.log('desk', m, 'home y', y); } }
    } catch (e) { console.log('fail', u, e.message); }
  }
  await ctx.close();
}
// mobile
{
  const ctx = await b.newContext({ ...devices['iPhone 13'], deviceScaleFactor: 3, reducedMotion: 'reduce' });
  const p = await ctx.newPage();
  await p.goto(base, { waitUntil: 'networkidle', timeout: 60000 }); await p.waitForTimeout(1500); await cookies(p); await p.addStyleTag({ content: CLEAN }); await reveal(p); await hideWidgets(p);
  const H = await p.evaluate(() => document.documentElement.scrollHeight); console.log('mobile H', H, 'vw', await p.evaluate(() => [innerWidth, innerHeight, document.documentElement.scrollWidth]));
  let n = 0;
  for (let k = 0; k < 14; k++) {
    const y = k * 700; if (y > H - 650) break;
    await p.evaluate(y => scrollTo(0, y), y); await p.waitForTimeout(2200);
    if (k === 1) await hideFloat(p);
    await p.evaluate(() => { document.documentElement.scrollLeft = 0; document.body.scrollLeft = 0; });
    n++; const nn = String(n).padStart(2, '0'); await p.screenshot({ path: `/tmp/m${nn}.png` }); save(`/tmp/m${nn}.png`, `sw3-m-${nn}`, 1170); console.log('mob', nn, y);
  }
  await ctx.close();
}
await b.close();
