/* Creative Vision — słowa w tekście o studiu rozjaśniają się przy przewijaniu. */
(() => {
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  document.documentElement.classList.add('cv-rv');
  let els = [];
  const run = () => {
    if (!els.length) els = [...document.querySelectorAll('.cv-reveal')];
    const vh = innerHeight;
    for (const el of els) {
      const r = el.getBoundingClientRect();
      const p = Math.min(1, Math.max(0, (vh * 0.88 - r.top) / (r.height + vh * 0.42)));
      const ws = el.querySelectorAll('.w'), n = Math.round(p * ws.length);
      ws.forEach((w, i) => w.classList.toggle('on', i < n));
    }
  };
  let raf = 0;
  const tick = () => { if (!raf) raf = requestAnimationFrame(() => { raf = 0; run(); }); };
  addEventListener('scroll', tick, { passive: true });
  addEventListener('resize', tick);
  addEventListener('load', tick);
  document.readyState === 'loading' ? addEventListener('DOMContentLoaded', tick) : tick();
  setTimeout(() => { els = []; tick(); }, 1500);
})();
