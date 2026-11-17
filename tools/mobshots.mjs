// jednorazowo w GitHub Actions: czyste zrzuty z telefonu (bez animacji w trakcie)
import { chromium, devices } from 'playwright';
import { execSync } from 'node:child_process';
const sites = { 'bud-mad': ['https://bud-mad.pl'], 'bzimny': ['https://bzimny.com'], 'tekma': ['https://tekma.app'], '4clubbers': ['https://4clubbers.app'], 'sztuka-wykonczen': ['https://sztukawykonczen.pl', 'https://sztuka-wykonczen.pl', 'https://www.sztukawykonczen.pl'], 'jctp-academy': ['https://jctpacademy.pl', 'https://jctp-academy.pl', 'https://akademia.jacitopoprawie.pl', 'https://jacitopoprawie.pl'] };
const KILL = '*,*::before,*::after{transition:none!important;animation:none!important;scroll-behavior:auto!important}html,body{overflow-x:hidden!important}';
const b = await chromium.launch();
for (const [slug, urls] of Object.entries(sites)) {
  let done = false;
  for (const url of urls) {
    if (done) break;
    const ctx = await b.newContext({ ...devices['iPhone 13'], deviceScaleFactor: 3, reducedMotion: 'reduce' });
    const p = await ctx.newPage();
    try {
      const r = await p.goto(url, { waitUntil: 'networkidle', timeout: 40000 });
      if (!r || r.status() >= 400) throw new Error('status ' + (r && r.status()));
      await p.waitForTimeout(1500);
      for (const re of [/akceptuj/i, /zgadzam/i, /accept/i, /rozumiem/i, /zezwól/i, /allow all/i]) { const btn = p.getByRole('button', { name: re }).first(); if (await btn.isVisible().catch(() => false)) { await btn.click().catch(() => {}); await p.waitForTimeout(800); break; } }
      let H = await p.evaluate(() => document.documentElement.scrollHeight);
      for (let y = 0; y < H; y += 300) { await p.evaluate(y => scrollTo(0, y), y); await p.waitForTimeout(250); }
      await p.waitForTimeout(1500);
      await p.addStyleTag({ content: KILL });
      H = await p.evaluate(() => document.documentElement.scrollHeight);
      const step = 760; let n = 0;
      for (let k = 0; k < 14; k++) {
        const y = k * step; if (y > H - 700) break;
        await p.evaluate(y => scrollTo(0, y), y); await p.waitForTimeout(1200);
        const ox = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth);
        n++; const nn = String(n).padStart(2, '0'), f = `/tmp/${slug}-${nn}.png`;
        await p.screenshot({ path: f });
        execSync(`python3 -c "from PIL import Image;im=Image.open('${f}').convert('RGB');im=im.resize((1170,round(im.size[1]*1170/im.size[0])),Image.LANCZOS);im.save('public/img/mob2-${slug}-${nn}.webp','WEBP',quality=88,method=6)"`);
        console.log(slug, nn, 'y', y, 'overflowX', ox);
      }
      console.log('OK', slug, url, n); done = true;
    } catch (e) { console.log('fail', slug, url, e.message); }
    await ctx.close();
  }
}
await b.close();
