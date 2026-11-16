// jednorazowo w GitHub Actions: zrzuty ekranów z telefonu z działających stron realizacji
import { chromium, devices } from 'playwright';
import { execSync } from 'node:child_process';
const sites = { 'bud-mad': 'https://bud-mad.pl', 'bzimny': 'https://bzimny.com', 'tekma': 'https://tekma.app', '4clubbers': 'https://4clubbers.app' };
const b = await chromium.launch();
for (const [slug, url] of Object.entries(sites)) {
  const ctx = await b.newContext({ ...devices['iPhone 13'], deviceScaleFactor: 3 });
  const p = await ctx.newPage();
  let n = 0;
  const dismiss = async () => { for (const re of [/akceptuj/i, /zgadzam/i, /accept/i, /rozumiem/i, /zezwól/i, /allow all/i, /ok$/i]) { const btn = p.getByRole('button', { name: re }).first(); if (await btn.isVisible().catch(() => false)) { await btn.click().catch(() => {}); await p.waitForTimeout(600); return; } } };
  try {
    await p.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
    await p.waitForTimeout(1500); await dismiss();
    const host = new URL(url).host;
    let links = await p.$$eval('a[href]', as => as.map(a => a.href));
    links = [];void [...new Set(links.filter(h => { try { const u = new URL(h); return u.host === location.host && u.pathname !== '/' && !/\.(pdf|jpg|png)$/i.test(u.pathname) && !u.hash; } catch { return false } }).map(h => h.split('#')[0]))].slice(0, 5);
    for (const u of [url, ...links]) {
      try {
        if (u !== url) { await p.goto(u, { waitUntil: 'networkidle', timeout: 30000 }); await p.waitForTimeout(1200); await dismiss(); }
        const H = await p.evaluate(() => document.documentElement.scrollHeight);
        for (let k = 0; k < 10; k++) {
          const y = k * 760; if (y > H - 844) break;
          await p.evaluate(y => scrollTo(0, y), y); await p.waitForTimeout(900);
          n++; const f = `/tmp/${slug}-${String(n).padStart(2, '0')}.png`;
          await p.screenshot({ path: f });
          execSync(`python3 -c "from PIL import Image;im=Image.open('${f}').convert('RGB');im=im.resize((1170,round(im.size[1]*1170/im.size[0])),Image.LANCZOS);im.save('public/img/mob-${slug}-${String(n).padStart(2, '0')}.webp','WEBP',quality=88,method=6)"`);
          if (n >= 9) break;
        }
        if (n >= 10) break;
      } catch (e) { console.log('skip', u, e.message); }
    }
  } catch (e) { console.log('fail', slug, e.message); }
  console.log(slug, n);
  await ctx.close();
}
await b.close();
