/* Duch Sanu – sklep demo: koszyk, bramka wieku, lada, filtry, karta piwa, kreator, kasa */
(() => {
  const D = window.DS, B = D.base;
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const zl = n => n.toFixed(2).replace('.', ',') + ' zł';
  const beer = s => D.beers.find(b => b.slug === s);
  const FREE = 199, MIN = 45, SHIP = 15.99;
  const store = {
    get(k, d) { try { return JSON.parse(localStorage.getItem('ds_' + k)) ?? d } catch { return d } },
    set(k, v) { try { localStorage.setItem('ds_' + k, JSON.stringify(v)) } catch {} }
  };

  /* ---------- smart sticky header ---------- */
  const hdr = $('.hdr');
  if (hdr) {
    const onPhoto = hdr.classList.contains('on-photo');
    let lastY = scrollY, ticking = false;
    const baseTop = hdr.getBoundingClientRect().top + scrollY;
    const here = location.pathname.replace(/\/$/, '');
    $$('.nav a').forEach(a => { if (a.getAttribute('href') === here) a.classList.add('on'); });
    const update = () => {
      const y = Math.max(0, scrollY), dy = y - lastY, h = document.documentElement;
      const start = onPhoto ? 260 : baseTop + 120;
      hdr.classList.toggle('compact', y > (onPhoto ? 260 : 40));
      if (onPhoto) hdr.classList.toggle('is-fixed', y > 260);
      if (y <= start) hdr.classList.remove('hide');
      else if (dy > 6 && !$('#drawer')?.classList.contains('on')) hdr.classList.add('hide');
      else if (dy < -6) hdr.classList.remove('hide');
      hdr.style.setProperty('--p', (y / Math.max(1, h.scrollHeight - innerHeight)).toFixed(4));
      lastY = y; ticking = false;
    };
    addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
    update();
  }

  /* ---------- age gate ---------- */
  const gate = $('#gate');
  if (gate && !store.get('age', false)) {
    gate.hidden = false; document.body.style.overflow = 'hidden';
    const ins = $$('input', gate); ins[0].focus();
    ins.forEach((inp, i) => {
      inp.addEventListener('input', () => { inp.value = inp.value.replace(/\D/g, '').slice(0, 1); if (inp.value && ins[i + 1]) ins[i + 1].focus(); });
      inp.addEventListener('keydown', e => { if (e.key === 'Backspace' && !inp.value && ins[i - 1]) ins[i - 1].focus(); if (e.key === 'Enter') $('#gate-go').click(); });
    });
    $('#gate-go').addEventListener('click', () => {
      const y = +ins.map(i => i.value).join(''), now = new Date().getFullYear();
      if (!y || y < 1900 || y > now) return $('#gate-err').textContent = 'Wpisz pełny rok urodzenia, np. 1987.';
      if (now - y < 18) return $('#gate-err').textContent = 'Przykro nam – sklep jest tylko dla osób pełnoletnich.';
      store.set('age', true); gate.classList.add('bye'); gate.remove(); document.body.style.overflow = '';
    });
  } else if (gate) gate.remove();

  /* ---------- cart ---------- */
  let cart = store.get('cart', []);
  const save = () => { store.set('cart', cart); renderCart(); };
  const subtotal = () => cart.reduce((s, l) => s + l.price * l.qty, 0);
  const count = () => cart.reduce((s, l) => s + (l.bottles || 1) * l.qty, 0);
  function add(item, qty = 1, open = true) {
    const ex = cart.find(l => l.id === item.id);
    if (ex) ex.qty += qty; else cart.push({ ...item, qty });
    save(); toast(`Dodano: ${item.name}`); $$('.cart-count').forEach(c => { c.classList.remove('bump'); void c.offsetWidth; c.classList.add('bump'); }); if (open) openCart();
  }
  window.dsAdd = add;
  function beerItem(b, pack = 1) {
    const disc = pack === 6 ? 0.05 : 0;
    return { id: b.slug + (pack > 1 ? '-x' + pack : ''), name: b.name + (pack > 1 ? ` × ${pack}` : ''), price: Math.round(b.price * pack * (1 - disc) * 100) / 100, img: `${B}/img/piwa/${b.slug}.webp`, color: b.color, note: `${b.style} · ${b.abv}%` + (disc ? ' · pakiet −5%' : ''), bottles: pack, url: `${B}/piwo/${b.slug}` };
  }
  function renderCart() {
    $$('.cart-count').forEach(e => { const c = count(); e.textContent = c; e.hidden = !c; });
    const box = $('#dr-items'); if (!box) return;
    const sub = subtotal(), left = Math.max(0, FREE - sub);
    $('#dr-ship').innerHTML = left > 0 ? `Brakuje <b>${zl(left)}</b> do darmowej dostawy` : 'Masz <b>darmową dostawę</b> – Paczkomat i kurier 0 zł';
    $('#dr-bar').style.width = Math.min(100, sub / FREE * 100) + '%';
    $('#dr-count').textContent = count() + ' szt.';
    box.innerHTML = cart.length ? cart.map((l, i) => `
      <div class="line"><a class="th" href="${l.url || '#'}" style="--c:${l.color || '#C9A35B'}"><img src="${l.img}" alt=""></a>
      <div><h4>${l.name}</h4><small>${l.note || ''}</small>
      <span class="qty"><button data-q="${i}" data-d="-1" aria-label="Mniej">−</button>${l.qty}<button data-q="${i}" data-d="1" aria-label="Więcej">+</button></span></div>
      <div class="line-right"><b>${zl(l.price * l.qty)}</b><button class="rm" data-rm="${i}">Usuń</button></div></div>`).join('')
      : `<div class="empty"><p class="h3">Koszyk jest pusty</p><p>Zacznij od lady – wybierz szklankę.</p><a class="btn btn-dark" href="${B}/piwa">Zobacz piwa</a></div>`;
    $('#dr-sub').textContent = zl(sub);
    $('#dr-total').textContent = zl(sub);
    const go = $('#dr-go'), min = $('#dr-min');
    go.toggleAttribute('disabled', sub < MIN || !cart.length);
    min.textContent = cart.length && sub < MIN ? `Minimalna wartość zamówienia to ${zl(MIN)} – brakuje ${zl(MIN - sub)}.` : 'BLIK · Apple Pay · Google Pay · Przelewy24 · karta';
  }
  document.addEventListener('click', e => {
    const q = e.target.closest('[data-q]'); if (q) { const l = cart[+q.dataset.q]; l.qty += +q.dataset.d; if (l.qty < 1) cart.splice(+q.dataset.q, 1); save(); }
    const r = e.target.closest('[data-rm]'); if (r) { cart.splice(+r.dataset.rm, 1); save(); }
    const a = e.target.closest('[data-add]'); if (a) { e.preventDefault(); const b = beer(a.dataset.add); if (b) add(beerItem(b)); else { const m = D.merch.find(x => x.slug === a.dataset.add); if (m) add({ id: m.slug, name: m.name, price: m.price, img: `${B}/img/merch/${m.img}.webp`, color: '#C9A35B', note: m.note }); } }
    const f = e.target.closest('.fav'); if (f) { e.preventDefault(); f.classList.toggle('on'); toast(f.classList.contains('on') ? 'Dodano do ulubionych' : 'Usunięto z ulubionych'); }
    if (e.target.closest('[data-cart]')) { e.preventDefault(); openCart(); }
    if (e.target.closest('[data-close]') || e.target.classList.contains('scrim')) closeCart();
  });
  function openCart() { $('#drawer')?.classList.add('on'); $('.scrim')?.classList.add('on'); }
  function closeCart() { $('#drawer')?.classList.remove('on'); $('.scrim')?.classList.remove('on'); }
  let tt; function toast(t) { const el = $('#toast'); if (!el) return; el.textContent = t; el.classList.add('on'); clearTimeout(tt); tt = setTimeout(() => el.classList.remove('on'), 2200); }
  renderCart();

  /* ---------- bar counter (lada) ---------- */
  const lada = $('#lada');
  if (lada) {
    const glasses = $$('.glass', lada), tabs = $$('.tab');
    const set = i => {
      const g = glasses[i], b = beer(g.dataset.slug);
      glasses.forEach((x, j) => { x.classList.toggle('on', j === i); x.classList.toggle('near', Math.abs(j - i) === 1); });
      lada.style.setProperty('--x', g.style.left);
      const im = $('.lada-img', lada); if (matchMedia('(max-width:720px)').matches) { const Wc = im.clientWidth, Wi = im.clientHeight * ZOOM * 16 / 9; const gx = parseFloat(g.style.left) / 100 * Wi, pos = Math.round(Math.min(0, Math.max(Wc - Wi, Wc / 2 - gx))); im.style.backgroundPosition = `${pos}px 74%`; lada.style.setProperty('--x', ((gx + pos) / Wc * 100).toFixed(1) + '%'); } else im.style.backgroundPosition = '';
      $('#lada-label').style.backgroundImage = `url(${B}/img/piwa/${b.slug}.webp)`;
      $('#lada-meta').textContent = `${b.style} · ${b.abv}%` + (b.ext ? ` · ${b.ext}°` : '');
      $('#lada-name').textContent = b.name;
      $('#lada-desc').textContent = b.short + '.';
      $('#lada-add').dataset.add = b.slug; $('#lada-add').firstChild.textContent = `Do koszyka · ${zl(b.price)} `;
      $('#lada-more').href = `${B}/piwo/${b.slug}`;
      tabs.forEach(t => t.classList.toggle('on', t.dataset.fam === g.dataset.fam));
      cur = i;
      plus.dataset.add = b.slug; plus.setAttribute('aria-label', `Dodaj ${b.name} do koszyka`); tag.innerHTML = `<b>${b.name}</b><em>${zl(b.price)}</em>`;
      picks.forEach((x, j) => x.classList.toggle('on', j === i));
      const pk = picks[i]; if (pk) pick.scrollTo({ left: pk.offsetLeft - (pick.clientWidth - pk.offsetWidth) / 2, behavior: 'smooth' });
    };
    /* telefon: wybór piwa strzałkami, przesunięciem palca i listą nazw pod zdjęciem */
    let cur = 2;
    const ZOOM = 1.3;
    const plus = document.createElement('button'); plus.type = 'button'; plus.className = 'lada-plus';
    plus.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>';
    const tag = document.createElement('span'); tag.className = 'lada-tag';
    const img = $('.lada-img', lada), n = glasses.length, step = d => set((cur + d + n) % n);
    const pick = document.createElement('div'); pick.className = 'lada-pick';
    const picks = glasses.map((g, i) => { const x = document.createElement('button'); x.type = 'button'; x.textContent = beer(g.dataset.slug).name; x.addEventListener('click', () => set(i)); pick.appendChild(x); return x; });
    lada.appendChild(pick);
    img.appendChild(plus); img.appendChild(tag);
    [['prev', -1, 'Poprzednie piwo', 'M15 5l-7 7 7 7'], ['next', 1, 'Następne piwo', 'M9 5l7 7-7 7']].forEach(([c, d, l, path]) => {
      const x = document.createElement('button'); x.type = 'button'; x.className = 'lada-nav ' + c; x.setAttribute('aria-label', l);
      x.innerHTML = `<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="${path}"/></svg>`;
      x.addEventListener('click', e => { e.stopPropagation(); step(d); }); img.appendChild(x);
    });
    let tx = null, ty = 0;
    img.addEventListener('touchstart', e => { tx = e.touches[0].clientX; ty = e.touches[0].clientY; }, { passive: true });
    img.addEventListener('touchend', e => { if (tx === null) return; const dx = e.changedTouches[0].clientX - tx, dy = e.changedTouches[0].clientY - ty; tx = null; if (Math.abs(dx) > 36 && Math.abs(dx) > Math.abs(dy) * 1.3) step(dx < 0 ? 1 : -1); }, { passive: true });
    addEventListener('resize', () => set(cur));
    img.addEventListener('click', e => { if (!matchMedia('(max-width:720px)').matches || e.target.closest('.lada-nav,.lada-plus')) return;
      const r = img.getBoundingClientRect(), Wi = r.height * ZOOM * 16 / 9, x = (e.clientX - r.left - (parseFloat(img.style.backgroundPosition) || 0)) / Wi * 100;
      let best = 0; glasses.forEach((g, j) => { if (Math.abs(parseFloat(g.style.left) - x) < Math.abs(parseFloat(glasses[best].style.left) - x)) best = j; }); set(best); });
    glasses.forEach((g, i) => g.addEventListener('click', () => set(i)));
    tabs.forEach(t => t.addEventListener('click', () => { const i = glasses.findIndex(g => g.dataset.fam === t.dataset.fam); if (i > -1) set(i); else location.href = `${B}/piwa?smak=${t.dataset.fam}`; }));
    set(2);
  }

  /* ---------- listing filters ---------- */
  const list = $('#list');
  if (list) {
    const cards = $$('.card', list), chips = $$('.chip[data-fam]');
    let fam = new URLSearchParams(location.search).get('smak') || 'all';
    const apply = () => {
      const checked = n => $$(`input[name=${n}]:checked`).map(i => i.value);
      const st = checked('series'), al = checked('alk'), gl = checked('gl');
      let n = 0;
      cards.forEach(c => {
        const d = c.dataset, abv = parseFloat(d.abv.replace(',', '.'));
        const okF = fam === 'all' || d.fam.split(' ').includes(fam);
        const okS = !st.length || st.includes(d.series);
        const okA = !al.length || al.some(a => (a === '0' && abv === 0) || (a === '5' && abv > 0 && abv <= 5) || (a === '7' && abv > 5 && abv <= 7) || (a === '8' && abv > 7));
        const okG = !gl.length || d.fam.includes('bezglutenu');
        const show = okF && okS && okA && okG; c.hidden = !show; if (show) n++;
      });
      chips.forEach(c => c.classList.toggle('on', c.dataset.fam === fam));
      $('#count').textContent = n + (n === 1 ? ' piwo' : n < 5 && n > 1 ? ' piwa' : ' piw');
      const sort = $('#sort').value, arr = cards.slice().sort((a, b) => sort === 'pa' ? a.dataset.price - b.dataset.price : sort === 'pd' ? b.dataset.price - a.dataset.price : sort === 'alk' ? parseFloat(b.dataset.abv.replace(',', '.')) - parseFloat(a.dataset.abv.replace(',', '.')) : a.dataset.i - b.dataset.i);
      arr.forEach(c => list.appendChild(c));
    };
    chips.forEach(c => c.addEventListener('click', () => { fam = c.dataset.fam; history.replaceState(null, '', fam === 'all' ? location.pathname : `?smak=${fam}`); apply(); }));
    $$('.filters input, #sort').forEach(i => i.addEventListener('change', apply));
    $('#clear')?.addEventListener('click', () => { $$('.filters input').forEach(i => i.checked = false); fam = 'all'; apply(); });
    $('.filter-btn')?.addEventListener('click', () => $('.filters').classList.toggle('on'));
    apply();
  }

  /* ---------- product page ---------- */
  const pdp = $('#pdp');
  if (pdp) {
    const b = beer(pdp.dataset.slug); let pack = 1, qty = 1;
    const upd = () => { const it = beerItem(b, pack); $('#pdp-cta').firstChild.textContent = `Dodaj do koszyka · ${zl(it.price * qty)} `; $('#pdp-q').textContent = qty; $$('#sticky-cta').forEach(x => x.textContent = 'Do koszyka'); $('#sticky-sub').textContent = `${pack > 1 ? pack + ' szt. · ' : ''}${zl(it.price * qty)}`; };
    $$('.packs button').forEach(p => p.addEventListener('click', () => { $$('.packs button').forEach(x => x.classList.remove('on')); p.classList.add('on'); pack = +p.dataset.pack; upd(); }));
    $('#pdp-minus').onclick = () => { qty = Math.max(1, qty - 1); upd(); };
    $('#pdp-plus').onclick = () => { qty++; upd(); };
    const buy = () => add(beerItem(b, pack), qty);
    $('#pdp-cta').onclick = buy; $('#sticky-cta').onclick = buy;
    $$('.thumbs button').forEach(t => t.addEventListener('click', () => { $$('.thumbs button').forEach(x => x.classList.remove('on')); t.classList.add('on'); const im = $('.main-img img'); im.src = t.dataset.src; im.classList.toggle('cover', t.dataset.cover === '1'); }));
    upd();
  }

  /* ---------- crate builder ---------- */
  const kr = $('#kreator');
  if (kr) {
    let size = 6; const picks = {};
    const total = () => Object.values(picks).reduce((a, b) => a + b, 0);
    const crate = () => D.crates.find(c => c.n === size);
    const draw = () => {
      const c = crate(), n = total(), slots = $('#slots');
      slots.className = 'slots' + (size === 4 ? ' s4' : size === 12 ? ' s12' : '');
      const flat = Object.entries(picks).flatMap(([s, q]) => Array(q).fill(s));
      slots.innerHTML = Array.from({ length: size }, (_, i) => flat[i] ? `<div class="slot" style="--c:${beer(flat[i]).color}"><img src="${B}/img/piwa/${flat[i]}.webp" alt=""></div>` : '<div class="slot empty"></div>').join('');
      $('#kr-crate-img').src = `${B}/img/merch/${c.img}.webp`; $('#kr-crate-name').textContent = c.name;
      const beersSum = flat.reduce((s, x) => s + beer(x).price, 0), disc = Math.round(beersSum * c.disc * 100) / 100;
      $('#kr-list').innerHTML = Object.entries(picks).map(([s, q]) => `<div><span>${q} × ${beer(s).name}</span><span>${zl(beer(s).price * q)}</span></div>`).join('') + `<div><span>${c.name}</span><span>${zl(c.price)}</span></div>` + (disc ? `<div style="color:var(--ok)"><span>Rabat w skrzynce −${c.disc * 100}%</span><span>−${zl(disc)}</span></div>` : '');
      const sum = beersSum + c.price - disc;
      $('#kr-sum').textContent = zl(sum);
      $('#kr-bar').style.width = (n / size * 100) + '%';
      const btn = $('#kr-go'); btn.disabled = n !== size;
      btn.textContent = n === size ? `Dodaj skrzynkę do koszyka · ${zl(sum)}` : `Dobierz jeszcze ${size - n} ${size - n === 1 ? 'piwo' : size - n < 5 ? 'piwa' : 'piw'}`;
      $('#kr-hint').textContent = n < size && c.disc ? `Pełna skrzynka = −${c.disc * 100}% na piwa` : n === size ? 'Gotowe! Możesz jeszcze dodać bilecik w koszyku.' : '';
      $$('.pick').forEach(p => { const q = picks[p.dataset.slug] || 0; p.classList.toggle('on', q > 0); p.querySelector('.ctl').innerHTML = q ? `<div class="stepper"><button data-k="${p.dataset.slug}" data-v="-1">−</button><span>${q}</span><button data-k="${p.dataset.slug}" data-v="1">+</button></div>` : `<button class="add-sm" data-k="${p.dataset.slug}" data-v="1">+ Dodaj</button>`; });
      $('#kr-mini-n').textContent = `${n}/${size}`; $('#kr-mini-sum').textContent = zl(sum); $('#kr-mini-go').disabled = n !== size;
      kr._sum = sum; kr._flat = flat;
    };
    kr.addEventListener('click', e => {
      const k = e.target.closest('[data-k]'); if (k) { const s = k.dataset.k, v = +k.dataset.v; if (v > 0 && total() >= size) return toast(`Skrzynka na ${size} jest pełna`); picks[s] = (picks[s] || 0) + v; if (picks[s] <= 0) delete picks[s]; draw(); }
      const z = e.target.closest('[data-size]'); if (z) { size = +z.dataset.size; $$('[data-size]').forEach(x => x.classList.toggle('on', x === z)); while (total() > size) { const last = Object.keys(picks).pop(); picks[last]--; if (!picks[last]) delete picks[last]; } draw(); }
    });
    $('#kr-surprise').onclick = () => { const pool = D.beers.filter(b => +b.abv.replace(',', '.') > 0); while (total() < size) { const b = pool[Math.floor(Math.random() * pool.length)]; picks[b.slug] = (picks[b.slug] || 0) + 1; } draw(); toast('Duch Sanu dobrał resztę za Ciebie'); };
    $('#kr-go').onclick = () => { const c = crate(); add({ id: 'skrzynka-' + Date.now(), name: `Skrzynka · ${size} piw`, price: Math.round(kr._sum * 100) / 100, img: `${B}/img/merch/${c.img}.webp`, color: '#C9A35B', note: Object.entries(picks).map(([s, q]) => (q > 1 ? q + '× ' : '') + beer(s).name).join(', '), bottles: size }); };
    $('#kr-mini-go').onclick = () => $('#kr-go').click();
    $$('.fchip').forEach(c => c.addEventListener('click', () => { $$('.fchip').forEach(x => x.classList.toggle('on', x === c)); $$('.pick').forEach(p => p.hidden = c.dataset.fam !== 'all' && !p.dataset.fam.split(' ').includes(c.dataset.fam)); }));
    draw();
  }

  /* ---------- checkout ---------- */
  const co = $('#checkout');
  if (co) {
    const ship = () => +($('input[name=ship]:checked')?.dataset.price || 0);
    const draw = () => {
      const sub = subtotal(), free = sub >= FREE, s = free ? 0 : ship();
      $('#co-items').innerHTML = cart.length ? cart.map(l => `<div class="line" style="border:0;padding:8px 0"><span class="th" style="--c:${l.color};width:60px;height:72px"><img src="${l.img}" alt=""></span><div><h4 style="font-size:15px;font-family:var(--sans);font-weight:600">${l.name} ${l.qty > 1 ? '× ' + l.qty : ''}</h4><small>${l.note || ''}</small></div><div class="line-right"><b>${zl(l.price * l.qty)}</b></div></div>`).join('') : '<p class="muted">Koszyk jest pusty – <a class="link" href="' + B + '/piwa">wybierz piwa</a>.</p>';
      $('#co-sub').textContent = zl(sub); $('#co-ship').textContent = free ? '0,00 zł (darmowa)' : zl(s);
      $('#co-total').textContent = zl(sub + s); $('#co-pay').textContent = `Zamawiam i płacę ${zl(sub + s)}`;
      $('#co-free').textContent = free ? 'Masz darmową dostawę.' : `Dorzuć ${zl(FREE - sub)}, a dostawa będzie gratis.`;
      $$('.opt').forEach(o => o.classList.toggle('on', o.querySelector('input').checked));
      $('#blik-box').hidden = !$('#pay-blik').checked;
    };
    $$('input[name=ship], input[name=pay]').forEach(i => i.addEventListener('change', draw));
    const bl = $$('.blik input');
    bl.forEach((inp, i) => { inp.addEventListener('input', () => { inp.value = inp.value.replace(/\D/g, '').slice(0, 1); if (inp.value && bl[i + 1]) bl[i + 1].focus(); }); inp.addEventListener('keydown', e => { if (e.key === 'Backspace' && !inp.value && bl[i - 1]) bl[i - 1].focus(); }); });
    const finish = () => { const sub = subtotal(), s = sub >= FREE ? 0 : ship(); store.set('order', { no: 'DS-2026-' + String(Math.floor(1000 + Math.random() * 8999)), total: sub + s, name: $('#f-name').value.split(' ')[0] || 'Kliencie', email: $('#f-mail').value, ship: $('input[name=ship]:checked').dataset.label }); cart = []; save(); location.href = `${B}/potwierdzenie`; };
    $('#co-form').addEventListener('submit', e => {
      e.preventDefault();
      if (!cart.length) return toast('Koszyk jest pusty');
      if (subtotal() < MIN) return toast(`Minimum zamówienia to ${zl(MIN)}`);
      if (!$('#agree').checked) return toast('Zaakceptuj regulamin i potwierdź pełnoletność');
      if ($('#pay-blik').checked) {
        if (bl.map(i => i.value).join('').length < 6) return toast('Wpisz 6-cyfrowy kod BLIK');
        $('#blik-modal').classList.add('on'); const bar = $('#blik-bar'); bar.style.width = '0%'; requestAnimationFrame(() => { bar.style.transition = 'width 2.5s linear'; bar.style.width = '100%'; });
        setTimeout(finish, 2600);
      } else finish();
    });
    $$('[data-express]').forEach(b => b.addEventListener('click', () => { if (!cart.length) return toast('Koszyk jest pusty'); if (subtotal() < MIN) return toast(`Minimum zamówienia to ${zl(MIN)}`); finish(); }));
    draw();
  }
  const conf = $('#confirm');
  if (conf) { const o = store.get('order', null); if (o) { $('#c-name').textContent = o.name; $('#c-no').textContent = o.no; $('#c-total').textContent = zl(o.total); $('#c-ship').textContent = o.ship; if (o.email) $('#c-mail').textContent = o.email; } }

  /* newsletter */
  $$('.news').forEach(f => f.addEventListener('submit', e => { e.preventDefault(); toast('Dzięki! Kod −10% wysłaliśmy na Twój e-mail (demo).'); f.reset(); }));
})();
