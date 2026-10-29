// Test interakcji creative-vision (wysyłki formularzy zablokowane)
const { chromium } = require('playwright');
const base = process.argv[2] || 'http://127.0.0.1:8091';
(async () => { const b = await chromium.launch(); const log = (...a) => console.log(...a); const errs = [];
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  await ctx.route(/make\.com|formsubmit\.co/, r => { log('  [zablokowano wysyłkę]', r.request().url().slice(0, 50), r.request().postData()?.slice(0, 120)); r.fulfill({ status: 200, contentType: 'application/json', body: '{"success":"true"}' }); });
  const p = await ctx.newPage(); p.on('pageerror', e => errs.push(e.message));
  await p.goto(base + '/', { waitUntil: 'networkidle' }); await p.waitForTimeout(2500);
  const ck = await p.locator('[aria-label="Ustawienia cookies"]').count(); log('baner cookies:', ck);
  const btn = p.getByRole('button', { name: /Akceptuj/i }).first(); if (await btn.count()) { await btn.click(); await p.waitForTimeout(400); log('po akceptacji baner:', await p.locator('[aria-label="Ustawienia cookies"]').isVisible().catch(() => false)); }
  log('localStorage:', await p.evaluate(() => Object.keys(localStorage).join(',')));
  // FAQ
  const det = p.locator('.sw-faq details, details').first();
  if (await det.count()) { await det.scrollIntoViewIfNeeded(); await det.locator('summary').click(); log('FAQ otwarte:', await det.evaluate(e => e.open)); }
  // kontakt
  const k = await ctx.newPage(); k.on('pageerror', e => errs.push(e.message)); await k.goto(base + '/kontakt', { waitUntil: 'networkidle' });
  const inputs = await k.locator('form input, form textarea, form select').evaluateAll(els => els.map(e => e.name + ':' + e.type + (e.required ? '*' : '')));
  log('pola formularza:', inputs.join(' '));
  for (const sel of await k.locator('form input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not([name=_honey]), form textarea').all()) { const t = await sel.getAttribute('type'); await sel.fill(t === 'email' ? 'test@example.com' : t === 'tel' ? '500600700' : 'Test'); }
  for (const c of await k.locator('form input[type=checkbox]').all()) await c.check().catch(() => {});
  const r = k.locator('form input[type=radio]').first(); if (await r.count()) await r.check().catch(() => {});
  await k.locator('form button[type=submit], form button:has-text("Wyślij")').first().click(); await k.waitForTimeout(1200);
  log('po wysłaniu:', (await k.locator('form').innerText().catch(() => '')).replace(/\s+/g, ' ').slice(-120));
  // mobile menu
  const m = await b.newContext({ viewport: { width: 390, height: 844 } }); const mp = await m.newPage(); mp.on('pageerror', e => errs.push(e.message));
  await mp.goto(base + '/', { waitUntil: 'networkidle' }); await mp.locator('.sw-burger').first().click(); await mp.waitForTimeout(700);
  log('menu mobilne otwarte:', await mp.locator('.sw-mnav').first().evaluate(e => e.classList.contains('on')));
  await mp.screenshot({ path: process.argv[3] + '/cv-menu.png' });
  log('błędy JS:', errs.length ? errs : 'brak'); await b.close(); })();
