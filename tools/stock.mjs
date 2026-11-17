import { chromium } from 'playwright';
import fs from 'node:fs';
import { execSync } from 'node:child_process';
const Q = JSON.parse(fs.readFileSync('tools/stockq.json', 'utf8'));
fs.mkdirSync('stock', { recursive: true });
const b = await chromium.launch(); const ctx = await b.newContext({ viewport: { width: 1440, height: 2400 }, userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36' });
const meta = {};
for (const [slug, q] of Object.entries(Q)) {
  const p = await ctx.newPage();
  try {
    await p.goto('https://unsplash.com/s/photos/' + encodeURIComponent(q.replace(/ /g, '-')) + '?license=free&orientation=landscape', { waitUntil: 'domcontentloaded', timeout: 45000 });
    await p.waitForTimeout(3500);
    const items = await p.evaluate(() => { const o = []; document.querySelectorAll('figure a[href^="/photos/"]').forEach(a => { const im = a.querySelector('img[src*="images.unsplash.com/photo-"]'); if (im) o.push([a.getAttribute('href'), im.src.split('?')[0], im.alt]); }); return o; });
    let n = 0;
    for (const [href, src, alt] of items) { if (n >= 4) break; try { execSync(`curl -sS -f -L -o /tmp/s.jpg "${src}?w=1600&q=80&fm=jpg&fit=crop&ar=16:10"`); n++; execSync(`python3 -c "from PIL import Image;im=Image.open('/tmp/s.jpg').convert('RGB');th=round(im.width/1.6);t=max(0,(im.height-th)//2);im=im.crop((0,t,im.width,t+min(th,im.height)));im.resize((1600,1000)).save('stock/${slug}-${n}.webp',quality=84)"`); meta[`${slug}-${n}`] = [href, alt]; } catch (e) { } }
    console.log('got', slug, n, 'of', items.length);
  } catch (e) { console.log('fail', slug, e.message.slice(0, 120)); }
  await p.close();
}
fs.writeFileSync('stock/meta.json', JSON.stringify(meta, null, 1)); await b.close();
