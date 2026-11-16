/* Creative Vision — cennik: po wyborze zakładki strona podjeżdża do jej nagłówka. */
(() => {
  document.addEventListener('change', (e) => {
    const r = e.target;
    if (!r.classList || !r.classList.contains('cv-tab-r')) return;
    const p = document.querySelector('.cv-tab-p.p' + r.id.slice(3));
    if (!p) return;
    const mob = matchMedia('(max-width: 900px)').matches;
    const bar = document.querySelector('.cv-tab-l');
    const off = mob ? 64 + (bar ? bar.offsetHeight : 0) + 16 : 116;
    const y = p.getBoundingClientRect().top + scrollY - off;
    if (Math.abs(y - scrollY) < 4) return;
    scrollTo({ top: y, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  });
})();
