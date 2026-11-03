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

/* Sekcje pojawiają się łagodnie przy przewijaniu. Treść jest widoczna bez JS,
   przy reduced motion i wszystko, co jest na ekranie przy wejściu, nie jest ukrywane. */
(() => {
  if (!('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const GRID = '.fx-grid,.sw-cases,.sw-posts,.cv-pr-g,.sw-faq,.sw-views,.sw-phones,.sw-gets,.fx-logos';
  const SEL = 'main .sw-sec .sw-w>*,main .sw-cta .sw-w>*,.fx-grid>*,.sw-cases>*,.sw-posts>*,.cv-pr-g>*,.sw-faq>details,.sw-views>*,.sw-phones>*,.sw-gets>*,main .sw-kt-adv,main .sw-next,main .sw-shot,main .sw-gal>*,.cv-pr-f';
  const start = () => {
    const vh = innerHeight, picked = [];
    for (const el of document.querySelectorAll(SEL)) {
      if (el.matches(GRID) || el.closest('.sw-ck,.sw-mnav')) continue;
      if (picked.some((p) => p.contains(el))) continue;
      if (el.getBoundingClientRect().top < vh * 0.95) continue;
      picked.push(el);
    }
    if (!picked.length) return;
    const io = new IntersectionObserver((es) => es.forEach((e) => {
      if (e.isIntersecting) { e.target.setAttribute('data-sr', 'in'); io.unobserve(e.target); }
    }), { rootMargin: '0px 0px -6% 0px' });
    for (const el of picked) {
      const sib = el.parentElement ? [...el.parentElement.children].indexOf(el) : 0;
      if (el.parentElement && el.parentElement.matches(GRID)) el.style.setProperty('--sr-d', Math.min(sib % 4, 3) * 0.08 + 's');
      el.setAttribute('data-sr', '');
      io.observe(el);
    }
    document.documentElement.classList.add('cv-sr');
  };
  document.readyState === 'loading' ? addEventListener('DOMContentLoaded', start) : start();
})();

/* Mobilny pasek kontaktu: pokazuje się dopiero, gdy przycisk z hero zniknie z ekranu. */
(() => {
  const bar = document.querySelector('.cv-mbar');
  if (!bar) return;
  const main = document.getElementById('tresc');
  const first = main && main.querySelector('section');
  const cta = first && (first.querySelector('.sw-ob') || first.querySelector('h1'));
  if (!('IntersectionObserver' in window) || !cta) { bar.classList.add('on'); return; }
  new IntersectionObserver(([e]) => bar.classList.toggle('on', !e.isIntersecting && e.boundingClientRect.top < 0)).observe(cta);
})();
