/* Creative Vision — kursor z poświatą w kolorach gradientu marki */
(() => {
  if (!matchMedia('(hover: hover) and (pointer: fine)').matches) return;
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const STOPS = [[233, 104, 95], [224, 63, 59], [172, 32, 105], [136, 64, 159], [62, 99, 239], [81, 186, 212]];
  // kolor z gradientu marki, t w 0..1 tam i z powrotem (bez skoku między cyjanem a koralem)
  const pick = (t) => {
    t = ((t % 2) + 2) % 2; if (t > 1) t = 2 - t;
    const f = t * (STOPS.length - 1), i = Math.min(Math.floor(f), STOPS.length - 2), k = f - i;
    const a = STOPS[i], b = STOPS[i + 1];
    return a.map((v, j) => Math.round(v + (b[j] - v) * k));
  };

  const css = document.createElement('style');
  css.textContent = `
html.cv-cur, html.cv-cur *{cursor:none!important}
html.cv-cur input, html.cv-cur textarea, html.cv-cur select, html.cv-cur [contenteditable]{cursor:text!important}
.cv-glow,.cv-dot{position:fixed;left:0;top:0;pointer-events:none;z-index:2147483646;opacity:0;transition:opacity .35s ease}
.cv-glow{width:640px;height:640px;margin:-320px 0 0 -320px;border-radius:50%;filter:blur(30px);will-change:transform,background}
.cv-dot{width:10px;height:10px;margin:-5px 0 0 -5px;border-radius:50%;background:#fff;
  transition:opacity .35s ease,width .25s cubic-bezier(.2,.7,.2,1),height .25s cubic-bezier(.2,.7,.2,1),margin .25s cubic-bezier(.2,.7,.2,1),background .25s,border-color .25s;border:1.5px solid transparent;will-change:transform}
.cv-dot.is-link{width:46px;height:46px;margin:-23px 0 0 -23px;background:transparent!important}
.cv-dot.is-down{width:30px;height:30px;margin:-15px 0 0 -15px}
.cv-on .cv-glow,.cv-on .cv-dot{opacity:1}`;
  document.head.appendChild(css);

  const glow = document.createElement('div'); glow.className = 'cv-glow'; glow.setAttribute('aria-hidden', 'true');
  const dot = document.createElement('div'); dot.className = 'cv-dot'; dot.setAttribute('aria-hidden', 'true');
  document.body.append(glow, dot);
  document.documentElement.classList.add('cv-cur');

  let mx = innerWidth / 2, my = innerHeight / 2, gx = mx, gy = my, seen = false;
  let light = false, link = false, energy = 0, lastX = mx, lastY = my;

  // jasność tła pod kursorem: na jasnych sekcjach poświata jest delikatniejsza
  const bgLum = (el) => {
    for (let e = el; e && e !== document.documentElement; e = e.parentElement) {
      const c = getComputedStyle(e).backgroundColor.match(/[\d.]+/g);
      if (c && (c.length < 4 || +c[3] > 0.5)) return (0.299 * c[0] + 0.587 * c[1] + 0.114 * c[2]) / 255;
    }
    return 0.05;
  };
  let probeT = 0;
  const probe = () => {
    const el = document.elementFromPoint(mx, my);
    if (!el) return;
    light = bgLum(el) > 0.6;
    link = !!el.closest('a,button,[role=button],label,summary,select,input[type=submit],input[type=checkbox]');
    dot.classList.toggle('is-link', link);
  };

  addEventListener('pointermove', (e) => {
    mx = e.clientX; my = e.clientY;
    if (!seen) { seen = true; gx = mx; gy = my; document.documentElement.classList.add('cv-on'); }
    const now = performance.now(); if (now - probeT > 60) { probeT = now; probe(); }
  }, { passive: true });
  addEventListener('scroll', () => { if (seen) probe(); }, { passive: true });
  document.addEventListener('pointerleave', () => document.documentElement.classList.remove('cv-on'));
  document.addEventListener('pointerenter', () => { if (seen) document.documentElement.classList.add('cv-on'); });
  addEventListener('pointerdown', () => dot.classList.add('is-down'));
  addEventListener('pointerup', () => dot.classList.remove('is-down'));

  const t0 = performance.now();
  const tick = (now) => {
    gx += (mx - gx) * 0.14; gy += (my - gy) * 0.14;
    const v = Math.hypot(mx - lastX, my - lastY); lastX = mx; lastY = my;
    energy += (Math.min(v / 40, 1) - energy) * 0.08;

    // kolor zależy od miejsca na stronie (x + pozycja w dokumencie) i powoli płynie w czasie
    const docY = my + scrollY;
    const t = mx / innerWidth * 0.55 + docY / 1400 + (now - t0) / 24000;
    const a = pick(t), b = pick(t + 0.35), c = pick(t + 0.7);
    const base = light ? 0.32 : 0.7, boost = (link ? 0.18 : 0) + energy * 0.15;
    const al = Math.min(base + boost, 0.85);
    glow.style.background =
      `radial-gradient(circle at 42% 40%, rgba(${a},${al}) 0%, rgba(${a},0) 46%),` +
      `radial-gradient(circle at 62% 58%, rgba(${b},${al * 0.9}) 0%, rgba(${b},0) 48%),` +
      `radial-gradient(circle at 50% 50%, rgba(${c},${al * 0.55}) 0%, rgba(${c},0) 62%)`;
    glow.style.mixBlendMode = light ? 'multiply' : 'screen';
    const s = 0.85 + energy * 0.35 + (link ? 0.15 : 0);
    glow.style.transform = `translate3d(${gx}px,${gy}px,0) scale(${s})`;
    dot.style.transform = `translate3d(${mx}px,${my}px,0)`;
    dot.style.background = `rgb(${a})`;
    dot.style.borderColor = `rgb(${a})`;
    dot.style.boxShadow = `0 0 12px 2px rgba(${a},.9), 0 0 34px 6px rgba(${b},.55)`;
    requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
})();
