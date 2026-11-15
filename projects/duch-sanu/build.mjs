// Duch Sanu – sklep demo. Uruchom: node projects/duch-sanu/build.mjs
// Wynik: public/duch-sanu.html + public/duch-sanu/** (adres: creative-vision.pl/duch-sanu)
import { mkdirSync, writeFileSync, copyFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { BEERS, MERCH, CRATES, FAMILIES } from './data.mjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = join(ROOT, 'public');
const B = '/duch-sanu';
const V = Date.now().toString(36);
const zl = n => n.toFixed(2).replace('.', ',') + ' zł';
const perL = p => zl(p * 2) + '/l';
const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
const by = s => BEERS.find(b => b.slug === s);
const famName = id => FAMILIES.find(f => f.id === id)?.name;
const word = n => n === 1 ? 'piwo' : (n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20)) ? 'piwa' : 'piw';

const I = {
  search: '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>', bag: '<path d="M5 8h14l-1 12H6L5 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>', menu: '<path d="M3 7h18M3 12h18M3 17h18"/>', arrow: '<path d="M4 12h16M14 6l6 6-6 6"/>',
  plus: '<path d="M12 5v14M5 12h14"/>', heart: '<path d="M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.5-7 10-7 10z"/>', close: '<path d="M6 6l12 12M18 6L6 18"/>',
  truck: '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>', pin: '<path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
  box: '<path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4M12 11v10"/>', lock: '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
  therm: '<path d="M14 14.8V5a2 2 0 0 0-4 0v9.8a4 4 0 1 0 4 0z"/>', glass: '<path d="M7 3h10l-1 18H8L7 3z"/><path d="M7.3 8h9.4"/>', check: '<path d="M5 12l4 4 10-10"/>', gift: '<path d="M4 10h16v10H4zM3 7h18v3H3zM12 7v13"/>',
  chev: '<path d="M9 6l6 6-6 6"/>', back: '<path d="M20 12H4M10 6l-6 6 6 6"/>', filter: '<path d="M4 6h16M7 12h10M10 18h4"/>',
};
const ic = (n, cls = 'ico') => `<svg class="${cls}" viewBox="0 0 24 24" aria-hidden="true">${I[n]}</svg>`;
const deer = (cls = 'deer') => `<svg class="${cls}" viewBox="50 50 726 910" aria-hidden="true"><use href="${B}/img/logo/jelen.svg#d"/></svg>`;
const img = (p, alt = '', extra = '') => `<img src="${B}/img/${p}" alt="${esc(alt)}" loading="lazy" decoding="async" ${extra}>`;

const DATA = JSON.stringify({ base: B, beers: BEERS.map(({ slug, name, style, abv, ext, price, color, fam, short }) => ({ slug, name, style, abv, ext, price, color, fam, short })), merch: MERCH, crates: CRATES });

const head = (title, desc) => `<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>${esc(title)}</title><meta name="description" content="${esc(desc)}"><meta name="robots" content="noindex,nofollow">
<link rel="icon" href="${B}/img/logo/jelen.svg"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="${B}/assets/style.css?v=${V}"></head><body>`;

const demoBar = `<div class="demo-bar">Projekt koncepcyjny<span class="hide-m"> sklepu Duch Sanu</span> · <a href="/">Creative Vision</a><span class="hide-m"> · zamówienia w tej wersji nie są realizowane</span></div>`;
const header = (photo = false) => `${demoBar}<div class="promo"><span>Warzone nad Sanem w otwartych kadziach</span><span>Wysyłka InPost w 24 h</span><span>Darmowa dostawa od 199 zł</span><span>Odbiór osobisty w Bachórzu</span></div>
<header class="hdr${photo ? ' on-photo' : ''}"><div class="wrap hdr-in">
<nav class="nav"><a href="${B}/piwa">Piwa</a><a href="${B}/prezenty">Prezenty</a><a href="${B}/kreator">Kreator</a><a href="${B}#bestiariusz">Bestiariusz</a><a href="${B}#legenda">Legenda</a></nav>
<button class="burger" aria-label="Menu" onclick="location.href='${B}/piwa'">${ic('menu')}</button>
<a class="brand" href="${B}" aria-label="Duch Sanu – strona główna">${deer()}<span><img class="wm" src="${B}/img/logo/napis-${photo ? 'jasny' : 'ciemny'}.webp" alt="Duch Sanu" width="120" height="30"><small>STĄD PŁYNIE NASZA DUMA</small></span></a>
<div class="act"><a class="search" href="${B}/piwa">${ic('search')}Szukaj piwa, stylu, smaku…</a><a class="acc" href="#" aria-label="Konto">${ic('user')}</a><a class="cart-btn" href="#" data-cart aria-label="Koszyk">${ic('bag')}<span class="cart-count" hidden>0</span></a></div>
</div><span class="progress" aria-hidden="true"></span></header>`;

const footer = () => `<footer class="ftr"><div class="wrap">
<div class="ftr-top"><div>${deer('deer-z')}<h2>Poczuj ducha Sanu.</h2><p class="muted" style="max-width:560px">Dołącz do Klubu Ducha – 10% na pierwsze zamówienie, wcześniejszy dostęp do nowych warek i zaproszenia na warzenie nad Sanem.</p></div>
<form class="news"><input type="email" required placeholder="Twój e-mail" aria-label="E-mail"><button class="btn btn-gold" style="height:46px">Zapisuję się ${ic('arrow')}</button></form></div>
<div class="ftr-cols">
<div><h4>Sklep</h4><a href="${B}/piwa">Wszystkie piwa</a><a href="${B}/piwa?smak=ipa">Seria Premium</a><a href="${B}/piwa?smak=zero">Bez alkoholu</a><a href="${B}/piwa?smak=bezglutenu">Bezglutenowe</a><a href="${B}/prezenty">Zestawy i prezenty</a><a href="${B}/kreator">Kreator skrzynki</a></div>
<div><h4>Browar</h4><a href="${B}#legenda">Legenda Ducha Sanu</a><a href="${B}#legenda">Jak warzymy</a><a href="${B}#mapa">Od Bieszczad po Bachórz</a><a href="${B}#mapa">Gdzie nas wypijesz</a><a href="#">Dla gastronomii</a></div>
<div><h4>Pomoc</h4><a href="#">Dostawa i odbiór</a><a href="#">Zwroty i reklamacje</a><a href="#">Regulamin</a><a href="#">Polityka prywatności</a><a href="#">Kontakt</a></div>
<div><h4>Kontakt</h4><span>Duch Sanu Sp. z o.o.</span><span>Bachórz 111, 36-068 Bachórz</span><span>sklep@duchsanu.com</span><span>Pn–Pt 8:00–16:00</span></div>
<div><h4>Społeczność</h4><a href="https://www.facebook.com/PiwoDuchSanu" target="_blank" rel="noopener">Facebook ↗</a><a href="https://www.instagram.com/duch_sanu/" target="_blank" rel="noopener">Instagram ↗</a><a href="#">TikTok ↗</a><a href="#">Untappd ↗</a></div>
</div>
<div class="ftr-legal"><span>© 2026 Duch Sanu · Sprzedaż alkoholu wyłącznie osobom pełnoletnim. Nie sprzedajemy alkoholu osobom nietrzeźwym. · Projekt: <a href="/" style="text-decoration:underline">Creative Vision</a></span><span class="pays"><span>BLIK</span><span>Przelewy24</span><span>Apple Pay</span><span>Google Pay</span><span>Visa</span><span>Mastercard</span><span>InPost</span></span></div>
<img class="ftr-big" src="${B}/img/logo/napis-jasny.webp" alt="" loading="lazy"></div></footer>`;

const shell = () => `<div class="scrim"></div><aside class="drawer" id="drawer" aria-label="Koszyk"><div class="dr-head"><h3>Koszyk</h3><span class="mono muted" id="dr-count"></span><button class="x" data-close aria-label="Zamknij">${ic('close')}</button></div>
<div class="ship"><span id="dr-ship"></span><div class="bar"><i id="dr-bar"></i></div></div><div class="dr-items" id="dr-items"></div>
<div class="dr-foot"><div class="row"><span>Produkty</span><span id="dr-sub"></span></div><div class="row"><span>Dostawa</span><span>od 15,99 zł · odbiór w browarze 0 zł</span></div>
<div class="total"><b>Do zapłaty (z VAT)</b><strong id="dr-total"></strong></div><a class="btn btn-gold btn-block" id="dr-go" href="${B}/kasa">${ic('lock')}Przejdź do kasy</a><p class="muted" id="dr-min" style="font-size:12px;text-align:center;margin:12px 0 0"></p></div></aside>
<div class="toast" id="toast" role="status"></div>
<div class="gate" id="gate" hidden style="background-image:url(${B}/img/foto/hero-san-mgla.webp)"><svg class="ghost" viewBox="50 50 726 910" aria-hidden="true"><use href="${B}/img/logo/jelen.svg#d"/></svg>
<div class="gate-card"><img class="sy" src="${B}/img/logo/sygnet-ciemny.webp" alt="Duch Sanu"><h2>Witaj nad Sanem.</h2><p class="muted" style="margin:0">Sprzedajemy piwo, więc wpuszczamy tylko osoby pełnoletnie.<br>Podaj rok urodzenia.</p>
<div class="year"><input inputmode="numeric" aria-label="Cyfra 1"><input inputmode="numeric" aria-label="Cyfra 2"><input inputmode="numeric" aria-label="Cyfra 3"><input inputmode="numeric" aria-label="Cyfra 4"></div>
<div class="err" id="gate-err"></div><button class="btn btn-gold btn-block" id="gate-go">Wchodzę – mam 18 lat ${ic('arrow')}</button><small class="muted">Zapamiętamy wybór na tym urządzeniu · Regulamin zaakceptujesz dopiero w kasie</small></div>
<div class="gate-legal">Pij odpowiedzialnie · Alkohol szkodzi zdrowiu · Nie sprzedajemy alkoholu osobom niepełnoletnim</div></div>
<script>window.DS=${DATA}</script><script src="${B}/assets/app.js?v=${V}" defer></script></body></html>`;

const card = (b, badge = b.badge) => `<article class="card" data-fam="${b.fam.join(' ')}" data-series="${b.series}" data-abv="${b.abv}" data-price="${b.price}" data-i="${BEERS.indexOf(b)}">
<a class="card-img" href="${B}/piwo/${b.slug}" style="--c:${b.color}">${badge ? `<span class="badge">${badge}</span>` : ''}${img(`piwa/${b.slug}.webp`, b.name)}</a>
<button class="fav" aria-label="Do ulubionych">${ic('heart')}</button>
<div><div class="sty">${b.style.replace('Premium · ', '')} · ${b.abv}%</div><h3><a href="${B}/piwo/${b.slug}">${b.name}</a></h3><p>${b.short}</p></div>
<div class="card-row"><div><div class="price">${zl(b.price)}</div><div class="unit">500 ml · ${perL(b.price)}</div></div><button class="add" data-add="${b.slug}" aria-label="Dodaj ${esc(b.name)} do koszyka">${ic('plus')}</button></div></article>`;

const write = (p, html) => { const f = join(OUT, p); mkdirSync(dirname(f), { recursive: true }); writeFileSync(f, html); };

/* ================= HOME ================= */
const GLASSES = [['pils', 9, 'rzeskie'], ['dzika-roza', 24.2, 'owocowe'], ['czarne-zloto', 39.8, 'ciemne'], ['lesne-widmo', 56.6, 'ipa'], ['zero', 72.7, 'zero'], ['plaster-miodu', 89, 'bezglutenu']];
const famCount = id => BEERS.filter(b => b.fam.includes(id)).length;
const MARQ = ['Jasne', 'Czarne Złoto', 'Leśne Widmo', 'Plaster Miodu', 'Karmazynowy Odyniec', 'Pils', 'Nocna Warta', 'Legendarny Zakapior', 'Dziki Bez'];
const marq = MARQ.map(n => `<span>${n}</span>${deer()}`).join('');
const premium = BEERS.filter(b => b.premium).sort((a, b) => a.premium.localeCompare(b.premium));
const home = head('Duch Sanu – piwo, które ma duszę rzeki | sklep (projekt)', 'Browar nad Sanem w Bachórzu. 24 piwa warzone w otwartych kadziach – od Pilsa po porter Czarne Złoto. Projekt sklepu: Creative Vision.') + header(true) + `
<main>
<section class="hero" style="background-image:url(${B}/img/foto/hero-san-mgla.webp)">
<svg class="ghost" viewBox="50 50 726 910" aria-hidden="true"><use href="${B}/img/logo/jelen.svg#d"/></svg><div class="glow"></div>
<img class="bottle" src="${B}/img/piwa/lesne-widmo.webp" alt="Butelka Leśne Widmo" fetchpriority="high">
<div class="wrap" style="width:100%"><div class="hero-in"><p class="eb light" style="margin:0 0 24px">Browar nad Sanem · Bachórz<span class="hide-m">, Pogórze Dynowskie</span></p>
<h1 class="h1">Piwo, które ma <em class="it">duszę</em> rzeki.</h1>
<p>Warzymy powoli, w otwartych kadziach, tam gdzie San zwalnia przed Pogórzem. 24 piwa – od rześkiego Pilsa po porter Czarne Złoto leżakowany 180 dni.</p>
<div class="hero-cta"><a class="btn btn-gold" href="${B}/piwa">Odkryj nasze piwa ${ic('arrow')}</a><a class="btn btn-ghost" href="${B}/kreator">Złóż własną skrzynkę</a></div></div></div>
<div class="facts"><div class="wrap"><div><p><b>Otwarte kadzie</b><span>tradycyjna fermentacja</span></p><p><b>24 piwa</b><span>4 bez alkoholu, 2 bezglutenowe</span></p><p><b>Wysyłka w 24 h</b><span>bezpieczna paczka InPost</span></p><p><b>Odbiór osobisty</b><span>w browarze w Bachórzu</span></p></div></div></div>
</section>
<div class="marquee" aria-hidden="true"><div class="marquee-track">${marq}${marq}</div></div>

<section class="sec"><div class="wrap">
<div class="sec-head"><div><p class="eb">01 — Wybierz po smaku</p><h2 class="h2">Na co masz dziś ochotę?</h2></div><p>Podejdź do lady i wybierz szklankę. Każda to inne piwo Ducha Sanu.</p></div>
<div class="lada" id="lada">
<div class="lada-top"><div class="lada-bottle"><div class="lada-label" id="lada-label" role="img" aria-label="Etykieta wybranego piwa"></div></div>
<p class="eb light" id="lada-meta" style="margin:0"></p><h3 class="lada-name" id="lada-name"></h3><p class="lada-desc" id="lada-desc" style="margin:0"></p>
<div class="lada-act"><button class="btn btn-gold" id="lada-add" data-add="czarne-zloto">Do koszyka ${ic('arrow')}</button><a class="link" id="lada-more" href="#" style="color:var(--m50)">Poznaj piwo ${ic('arrow')}</a></div></div>
<div class="lada-img" style="background-image:url(${B}/img/foto/lada-szklanki.webp)"><div class="lada-spot"></div>
${GLASSES.map(([s, x, f]) => `<button class="glass" style="left:${x}%" data-slug="${s}" data-fam="${f}" aria-label="${by(s).name}"><b>${by(s).name}</b><small>${zl(by(s).price)}</small><i></i></button>`).join('')}
<span class="lada-hint">Kliknij szklankę, a pokażemy piwo</span></div></div>
<div class="tabs">${FAMILIES.map(f => `<button class="tab" data-fam="${f.id}">${f.name} <small>${famCount(f.id)}</small></button>`).join('')}</div>
</div></section>

<section class="sec" style="padding-top:0"><div class="wrap">
<div class="sec-head"><div><p class="eb">02 — Ulubione</p><h2 class="h2">Najczęściej wybierane</h2></div><a class="btn btn-line" href="${B}/piwa">Wszystkie piwa ${ic('arrow')}</a></div>
<div class="grid">${['czarne-zloto', 'plaster-miodu', 'pils', 'lesne-widmo'].map(s => card(by(s))).join('')}</div></div></section>

<section class="sec dark" id="bestiariusz" style="position:relative;overflow:hidden"><div class="wrap">
<div class="sec-head"><div><p class="eb light">03 — Seria Premium</p><h2 class="h2" style="font-size:clamp(44px,6vw,84px)">Bestiariusz <em class="it gold">Ducha Sanu</em></h2></div><p class="muted">Każde piwo z serii Premium to istota z nadsańskich lasów. Poznaj je, zanim zapadnie zmrok.</p></div>
<div class="beasts">${premium.map(b => `<article class="beast" style="--c:${b.color}"><a class="beast-scene" href="${B}/piwo/${b.slug}"><span class="no">${b.premium}</span>${img(`piwa/${b.slug}.webp`, b.name)}</a>
<div class="beast-body"><h3>${b.name}</h3><p>${b.legend}</p><div class="beast-foot"><div><span class="mono">${b.style.replace('Premium · ', '')} · ${b.abv}%</span><b>${zl(b.price)}</b></div><a class="pill-light" href="${B}/piwo/${b.slug}">Poznaj</a></div></div></article>`).join('')}</div></div></section>

<section class="sec soft"><div class="wrap split">
<div class="white-box">${img('merch/nosidelko-drewno-6.webp', 'Drewniane nosidełko na 6 piw')}</div>
<div><p class="eb">04 — Kreator skrzynki</p><h2 class="h2">Złóż skrzynkę <em class="it">po swojemu.</em></h2>
<p class="muted" style="font-size:17px;max-width:540px">Wybierz nosidełko, dobierz piwa i dodaj bilecik z życzeniami. Skrzynka 12 to pełna, bezpieczna paczka InPost – i 10% taniej.</p>
<div class="steps"><div><span class="mono">01</span><p style="margin:0"><b>Wybierz nosidełko</b><span>Karton Ryś lub Biesy na 3 · sklejka na 4 · drewno na 6</span></p></div><div><span class="mono">02</span><p style="margin:0"><b>Dobierz piwa</b><span>Albo pozwól nam wybrać „Niespodziankę Ducha”</span></p></div><div><span class="mono">03</span><p style="margin:0"><b>Dodaj bilecik</b><span>Odręczne życzenia gratis – idealne na prezent</span></p></div></div>
<a class="btn btn-gold" href="${B}/kreator">Otwórz kreator skrzynki ${ic('arrow')}</a></div></div></section>

<section class="sec" id="legenda"><div class="wrap split">
<div class="photo" style="aspect-ratio:620/720">${img('foto/warzelnia.webp', 'Warzelnia')}</div>
<div>${deer('legend-deer')}<p class="eb" style="margin-top:20px">05 — Legenda</p><h2 class="h2 it" style="margin:14px 0 24px">„Tam, gdzie czas płynie wolniej.”</h2>
<p class="muted" style="font-size:17px;max-width:540px">Warzymy w Bachórzu nad Sanem, na Pogórzu Dynowskim. Rzeka niesie tu wodę prosto z Bieszczad, a my zamykamy w butelce to, co płynie razem z nią: mgłę o świcie, zapach lasu i spokój, którego szuka się w górach.</p>
<p class="muted" style="font-size:17px;max-width:540px">Fermentujemy w otwartych kadziach – wolno, po staremu. Czarne Złoto leżakuje 180 dni, zanim trafi do butelki z pałąkiem.</p>
<div class="nums"><p><b>180</b><span>dni leżakowania</span></p><p><b>60 g</b><span>miodu w litrze</span></p><p><b>2020</b><span>pierwsza warka</span></p></div></div></div></section>

<section class="map" id="mapa"><div class="wrap"><div style="position:relative;z-index:2;max-width:520px"><p class="eb light">06 — Rzeka</p><h2 class="h2" style="margin:14px 0 18px">Od Bieszczad po Bachórz.</h2><p class="muted">San niesie wodę z Bieszczad aż pod nasz browar. Wkrótce: mapa szlaków, schronisk i miejsc, w których nalejesz Ducha Sanu.</p><a class="btn" style="background:var(--m50);color:var(--t1);margin-top:20px" href="#">Znajdź Ducha Sanu w okolicy ${ic('pin')}</a></div>
<div class="map-river"><svg viewBox="0 0 820 420" fill="none" style="width:100%"><path d="M20 380 C 120 360, 140 280, 230 270 S 360 320, 420 250 S 520 120, 600 150 S 720 90, 800 40" stroke="#6F98A0" stroke-width="10" stroke-linecap="round"/><path d="M20 380 C 120 360, 140 280, 230 270 S 360 320, 420 250 S 520 120, 600 150 S 720 90, 800 40" stroke="#E2C98F" stroke-width="1.5" stroke-dasharray="2 8" stroke-linecap="round"/></svg>
${[['Bieszczady · źródła Sanu', 100, 2, ' end'], ['Lesko', 73, 30, ''], ['Sanok', 51, 52, ''], ['Dynów', 28, 58, '']].map(([n, x, y, c]) => `<span class="pin${c}" style="left:${x}%;top:${y}%"><i></i>${n}</span>`).join('')}<span class="pin main start" style="left:0%;top:86%">${deer()}Bachórz · browar</span></div></div></section>

<section class="sec soft"><div class="wrap">
<div class="sec-head"><div><p class="eb">07 — Prezenty</p><h2 class="h2">Prezent z duszą.</h2></div><a class="btn btn-line" href="${B}/prezenty">Wszystkie zestawy ${ic('arrow')}</a></div>
<div class="gift-grid"><a class="tile" href="${B}/prezenty">${img('merch/zestaw-mis-wojtek.webp', 'Zestaw Miś Wojtek', 'class="wojtek-img"')}<div class="in wojtek-in"><div style="flex:1"><p class="eb">Zestaw · bestseller prezentowy</p><h3 class="h2" style="font-size:40px">Miś Wojtek</h3><p class="muted" style="margin:6px 0 0">Plaster Miodu, Borówka Zero i szklanka 250 ml – z historią niedźwiedzia, który przeszedł z żołnierzami pół świata.</p></div><div style="text-align:right"><b style="font-size:22px">39,99 zł</b><br><button class="add" style="margin-top:10px" data-add="zestaw-mis-wojtek" aria-label="Dodaj zestaw">${ic('plus')}</button></div></div></a>
<div style="display:grid;gap:20px"><div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">${['nosidelko-rys-3', 'kufel'].map(s => { const m = MERCH.find(x => x.slug === s); return `<div class="tile">${img(`merch/${m.img}.webp`, m.name, 'style="height:250px"')}<div class="in"><span class="mono muted" style="font-size:10px;text-transform:uppercase">${m.tag}</span><b class="h3" style="font-size:21px">${m.name}</b><span><b>${zl(m.price)}</b></span></div></div>`; }).join('')}</div>
<div class="tile" style="flex-direction:row;align-items:center;background:var(--n800);color:var(--m50)">${img('merch/tshirt-wilk.webp', 'Koszulka Wilk', 'style="width:240px;height:240px;object-fit:cover"')}<div class="in"><p class="eb light">Kolekcja „Bieszczady”</p><b style="font:300 italic 34px/1.05 var(--serif)">Wolność ma kierunek.</b><span class="muted">Koszulki: jeleń, wilk, miś, ryś · 54,99 zł</span></div></div></div></div></div></section>

<section class="sec" style="background:var(--n800);color:var(--m50)"><div class="wrap club">
<div><p class="eb light">08 — Klub Ducha</p><h2 class="h2" style="margin-top:14px">Co miesiąc skrzynka, której <em class="it gold">jeszcze</em> nie piłeś.</h2>
<ul class="checks"><li>−10% na każdą skrzynkę i darmowa dostawa</li><li>Nowe warki i limitki 7 dni przed premierą</li><li>Głosowanie nad kolejnym smakiem</li><li>Zaproszenie na warzenie w Bachórzu</li><li>Pauza lub rezygnacja jednym kliknięciem</li></ul>
<a class="btn btn-gold" href="${B}/kreator">Dołącz od 99 zł / mies. ${ic('arrow')}</a> <span class="muted club-note">Pierwsza skrzynka z kuflem gratis</span></div>
<div class="member"><span class="mono" style="letter-spacing:.2em;font-weight:500">KLUB DUCHA</span><img src="${B}/img/logo/napis-ciemny.webp" alt="Duch Sanu"><span class="mono" style="font-size:12px;opacity:.75">Członek No. 0427 · od listopada 2026</span></div></div></section>

<section class="sec"><div class="wrap">
<div class="sec-head"><div><p class="eb">09 — Opinie</p><h2 class="h2">Piją, wracają, polecają.</h2></div><p>Opinie z zamówień i oceny z Untappd.</p></div>
<div class="reviews"><div class="review"><span class="stars">★★★★★</span><q>„Najlepsza zerówka, jaką piłem. Smakuje jak prawdziwe piwo, a nie lemoniada.”</q><div><b>Jabłko-Gruszka Zero</b><p class="muted" style="margin:4px 0 0;font-size:13px">Klient sklepu · opinia z duchsanu.com</p></div></div>
<div class="review"><span class="stars">★★★★★</span><q>„Czarne Złoto na zimowy wieczór – śliwka, kakao, rozgrzewa jak kominek w bacówce.”</q><div><b>Czarne Złoto · Untappd 3,56/5</b><p class="muted" style="margin:4px 0 0;font-size:13px">Przykładowa opinia – do podmiany na realną</p></div></div>
<div class="review"><span class="stars">★★★★★</span><q>„Kupiłem skrzynkę na prezent dla taty. Drewniane nosidełko zrobiło robotę, bilecik też.”</q><div><b>Kreator skrzynki · 6 piw</b><p class="muted" style="margin:4px 0 0;font-size:13px">Przykładowa opinia – do podmiany na realną</p></div></div></div></div></section>

<section style="background:var(--m100);padding:40px 0"><div class="wrap promises">
<div><span class="ic">${ic('box')}</span><p style="margin:0"><b>Pakujemy jak szkło</b><span>Przegródki z tektury, zero stłuczek</span></p></div>
<div><span class="ic">${ic('truck')}</span><p style="margin:0"><b>Wysyłka w 24 h</b><span>Paczkomat 15,99 zł · kurier 19,99 zł</span></p></div>
<div><span class="ic">${ic('pin')}</span><p style="margin:0"><b>Odbiór w browarze</b><span>Bachórz 111 – bez kosztów dostawy</span></p></div>
<div><span class="ic">${ic('lock')}</span><p style="margin:0"><b>BLIK i szybkie płatności</b><span>Przelewy24, Apple Pay, Google Pay</span></p></div></div></section>
</main>` + footer() + shell();
write('duch-sanu.html', home);

/* ================= LISTING ================= */
const cnt = (k, v) => BEERS.filter(b => b[k] === v).length;
const abvN = b => parseFloat(b.abv.replace(',', '.'));
const listing = head('Piwa – Duch Sanu | sklep (projekt)', '24 piwa z doliny Sanu: jasne, miodowe i owocowe, ciemne i mocne, IPA, bezalkoholowe i bezglutenowe.') + header() + `
<main class="wrap"><div class="list-head"><p class="eb">Sklep · ${BEERS.length} piw z doliny Sanu</p><h1 class="h1">Piwa</h1>
<div class="chips"><button class="chip" data-fam="all">Wszystkie <small>${BEERS.length}</small></button>${FAMILIES.map(f => `<button class="chip" data-fam="${f.id}"><i style="background:${f.color}"></i>${f.name} <small>${famCount(f.id)}</small></button>`).join('')}</div></div>
<div class="listing"><aside class="filters">
<div class="fg"><h4>Seria</h4>${['Klasyka', 'Smakowe i miodowe', 'Premium', 'Zero', 'Bez glutenu'].map(s => `<label><input type="checkbox" name="series" value="${s}">${s}<span>${cnt('series', s)}</span></label>`).join('')}</div>
<div class="fg"><h4>Zawartość alkoholu</h4>${[['0', '0,0% – bez alkoholu', b => abvN(b) === 0], ['5', 'do 5%', b => abvN(b) > 0 && abvN(b) <= 5], ['7', '5–7%', b => abvN(b) > 5 && abvN(b) <= 7], ['8', 'powyżej 7%', b => abvN(b) > 7]].map(([v, l, fn]) => `<label><input type="checkbox" name="alk" value="${v}">${l}<span>${BEERS.filter(fn).length}</span></label>`).join('')}</div>
<div class="fg"><h4>Dieta</h4><label><input type="checkbox" name="gl" value="1">Bez glutenu<span>${famCount('bezglutenu')}</span></label></div>
<div class="fg"><button class="link" id="clear" style="color:var(--buk)">Wyczyść filtry</button></div></aside>
<div><div class="toolbar"><button class="chip filter-btn">${ic('filter')}Filtry</button><span class="muted" id="count" style="flex:1"></span><select id="sort" aria-label="Sortowanie"><option value="rec">Polecane</option><option value="pa">Cena: od najniższej</option><option value="pd">Cena: od najwyższej</option><option value="alk">Najmocniejsze</option></select></div>
<div class="grid3" id="list">${BEERS.map(b => card(b)).join('')}</div></div></div>
<section style="background:var(--m100);border-radius:28px;padding:56px;margin-bottom:96px;display:grid;grid-template-columns:1fr 1.4fr;gap:60px"><h2 class="h3" style="font-size:36px;font-weight:300">Piwo z Bieszczad, warzone nad Sanem</h2><p class="muted" style="margin:0">Duch Sanu warzy w Bachórzu, na Pogórzu Dynowskim, w otwartych kadziach. W ofercie: jasne lagery i pils, piwa miodowe i owocowe, mocny lager, porter bałtycki leżakowany 180 dni, piwa bezalkoholowe i bezglutenowe oraz Seria Premium. Dawne kategorie sklepu działają jako filtry.</p></section>
</main>` + footer() + shell();
write('duch-sanu/piwa.html', listing);

/* ================= PRODUCT PAGES ================= */
const FL = ['Goryczka', 'Słodowość', 'Owocowość', 'Ciało', 'Palone nuty'];
for (const b of BEERS) {
  const related = BEERS.filter(x => x !== b && x.fam.some(f => b.fam.includes(f))).slice(0, 3);
  while (related.length < 4) { const c = BEERS[(BEERS.indexOf(b) + related.length * 5 + 3) % BEERS.length]; if (c !== b && !related.includes(c)) related.push(c); else related.push(BEERS.find(x => x !== b && !related.includes(x))); }
  const six = Math.round(b.price * 6 * 0.95 * 100) / 100;
  const html = head(`${b.name} – ${b.style} ${b.abv}% | Duch Sanu (projekt)`, `${b.name}: ${b.short}. ${b.style}, ${b.abv}% alk., butelka 500 ml.`) + header() + `
<main class="wrap"><nav class="crumbs"><a href="${B}">Sklep</a>›<a href="${B}/piwa">Piwa</a>›<a href="${B}/piwa?smak=${b.fam[0]}">${famName(b.fam[0])}</a>›<span>${b.name}</span></nav>
<div class="pdp" id="pdp" data-slug="${b.slug}">
<div class="gal"><div class="thumbs"><button class="on" data-src="${B}/img/piwa/${b.slug}.webp" aria-label="Butelka">${img(`piwa/${b.slug}.webp`, '')}</button><button data-cover="1" data-src="${B}/img/foto/piwo-kamien.webp" aria-label="Zdjęcie klimatyczne" style="background-image:url(${B}/img/foto/piwo-kamien.webp)"></button><button data-cover="1" data-src="${B}/img/foto/warzelnia.webp" aria-label="Warzelnia" style="background-image:url(${B}/img/foto/warzelnia.webp)"></button></div>
<div class="main-img" style="--c:${b.color}">${b.badge ? `<span class="badge">${b.premium ? 'Seria Premium · ' + b.premium : b.badge}</span>` : ''}<img src="${B}/img/piwa/${b.slug}.webp" alt="${esc(b.name)}"></div></div>
<div class="buy"><p class="eb" style="margin:0">${b.style} · ${b.abv}%${b.ext ? ` · ${b.ext}° ekstraktu` : ''} · 500 ml</p><h1>${b.name}</h1><p class="lead">${b.desc}</p>
<div class="pr"><b>${zl(b.price)}</b><span>${perL(b.price)} · najniższa cena z 30 dni: ${zl(b.price)}</span></div>
<div class="tags"><span>${ic('therm')}Podawaj w ${b.temp}</span><span>${ic('glass')}Szkło: ${b.glass}</span><span>${ic('box')}Butelka z ${b.cap === 'kapsel' ? 'kapslem' : 'pałąkiem'}</span></div>
<div class="packs"><button class="on" data-pack="1"><b>1 butelka</b><span>${zl(b.price)}</span></button><button data-pack="3"><b>3 butelki</b><span>${zl(b.price * 3)}</span><small style="color:var(--t2)">w nosidełku +5,99 zł</small></button><button data-pack="6"><b>6 butelek</b><span>${zl(six)}</span><small>−5% · oszczędzasz ${zl(b.price * 6 - six)}</small></button></div>
<div class="buyrow"><span class="qty"><button id="pdp-minus" aria-label="Mniej">−</button><span id="pdp-q">1</span><button id="pdp-plus" aria-label="Więcej">+</button></span><button class="btn btn-gold" id="pdp-cta">Dodaj do koszyka ${ic('arrow')}</button><button class="fav x" style="position:static;width:58px;height:58px" aria-label="Do ulubionych">${ic('heart')}</button></div>
<a class="kr-link" href="${B}/kreator">${img('merch/nosidelko-drewno-6.webp', '')}<span style="flex:1"><b>Dodaj do skrzynki w kreatorze</b><small>Zmieszaj z innymi piwami · 12 szt. = −10% i pełna paczka</small></span>${ic('arrow')}</a>
<div class="deliv"><div>${ic('truck')}<p style="margin:0"><b>Wysyłka w 24 h</b><span>InPost Paczkomat 15,99 zł · kurier 19,99 zł · darmowa od 199 zł</span></p></div><div>${ic('pin')}<p style="margin:0"><b>Odbiór w browarze – 0 zł</b><span>Bachórz 111 · pn–pt 8:00–16:00</span></p></div><div>${ic('box')}<p style="margin:0"><b>Pakujemy jak szkło</b><span>Przegródki z tektury · minimum zamówienia 45 zł</span></p></div></div></div></div></main>
<section class="sec soft"><div class="wrap flavor"><div style="--c:${b.color}"><p class="eb">Profil smaku</p><h2 class="h2" style="margin:14px 0 24px">Jak smakuje<br>${b.name}?</h2>
${b.f.map((v, i) => `<div class="fl-row"><span>${FL[i]}</span><span class="segs">${[1, 2, 3, 4, 5].map(k => `<i class="${k <= v ? 'on' : ''}"></i>`).join('')}</span><span>${v}/5</span></div>`).join('')}<p class="muted" style="font-size:12px">Profil – propozycja do potwierdzenia z browarem.</p></div>
<div class="notes"><div><b>Opis</b><p>${b.short}.</p></div><div><b>Chmiele</b><p>${b.hops}</p></div><div><b>Podanie</b><p>${b.temp}, szkło: ${b.glass}. Nalewaj powoli, zostaw dwa palce piany.</p></div><div><b>Warzenie</b><p>Otwarte kadzie w Bachórzu nad Sanem.</p></div></div></div></section>
<section class="sec"><div class="wrap duo">${b.legend ? `<div class="legend" style="--c:${b.color}">${deer()}<p class="eb light">Z Bestiariusza · ${b.premium}</p><h2 class="h2" style="font-size:44px">${b.name}</h2><p>${b.legend}</p></div>` : `<div class="legend" style="--c:${b.color}">${deer()}<p class="eb light">Stąd płynie nasza duma</p><h2 class="h2" style="font-size:44px">${b.name}</h2><p>${b.desc}</p></div>`}
<div class="pair"><p class="eb">Do czego pasuje</p><h2 class="h2" style="font-size:44px;margin:12px 0 18px">Na stół</h2>${b.pair.map(p => `<div><b>${p}</b></div>`).join('')}</div></div>
<div class="wrap" style="display:grid;grid-template-columns:1fr 1fr;gap:64px;margin-top:64px"><div class="spec"><p class="eb">Specyfikacja</p>${[['Styl', b.style], ['Alkohol', b.abv + '% obj.'], ['Ekstrakt', b.ext ? b.ext + '% wag.' : '—'], ['Chmiele', b.hops], ['Opakowanie', `butelka 500 ml z ${b.cap === 'kapsel' ? 'kapslem' : 'pałąkiem'}, bezzwrotna`], ['Producent', 'Duch Sanu Sp. z o.o., Bachórz']].map(([k, v]) => `<div><span>${k}</span><b>${v}</b></div>`).join('')}</div>
<div><p class="eb">Opinie</p><div class="review" style="margin-top:16px"><b class="h3">Bądź pierwszym, który oceni ${b.name}</b><span class="muted">Opinię wystawisz bez zakładania konta – wyślemy link po dostawie.</span><span class="stars" style="color:var(--m200)">★★★★★</span></div></div></div></section>
<section class="sec soft"><div class="wrap"><div class="sec-head"><div><p class="eb">Skrzynka sama się nie złoży</p><h2 class="h2">Dobierz do kompletu</h2></div></div><div class="grid">${related.slice(0, 4).map(r => card(r)).join('')}</div></div></section>
<div class="sticky-buy"><img src="${B}/img/piwa/${b.slug}.webp" alt=""><div><b>${b.name}</b><br><span id="sticky-sub"></span></div><button class="btn btn-gold" id="sticky-cta">Do koszyka</button></div>` + footer() + shell();
  write(`duch-sanu/piwo/${b.slug}.html`, html);
}

/* ================= CRATE BUILDER ================= */
const kreator = head('Kreator skrzynki – Duch Sanu (projekt)', 'Złóż własną skrzynkę 3, 4, 6 lub 12 piw Duch Sanu w nosidełku z bilecikiem.') + header() + `
<main class="wrap" id="kreator"><div class="list-head" style="display:flex;justify-content:space-between;align-items:flex-end;gap:24px;flex-wrap:wrap"><div><p class="eb">Kreator skrzynki</p><h1 class="h2">Złóż skrzynkę po swojemu</h1></div><p class="muted" style="max-width:420px;margin:0">Wybierz rozmiar, dobierz piwa i dodaj do koszyka. Pełna szóstka to −5%, dwunastka −10% i pełna paczka InPost.</p></div>
<div class="kr"><div class="kr-box"><div class="kr-seg">${CRATES.map(c => `<button data-size="${c.n}" class="${c.n === 6 ? 'on' : ''}">${c.n} ${word(c.n)}${c.disc ? `<small>−${c.disc * 100}%</small>` : ''}</button>`).join('')}</div>
<div class="slots" id="slots"></div>
<div style="display:flex;gap:14px;align-items:center"><img id="kr-crate-img" src="" alt="" style="width:64px;height:64px;object-fit:contain;background:var(--m100);border-radius:12px"><div><b id="kr-crate-name"></b><br><span class="muted" style="font-size:13px">celofan, sznurek i bilecik w komplecie</span></div></div>
<div class="kr-list" id="kr-list"></div>
<div style="display:flex;justify-content:space-between;align-items:flex-end;border-top:1px solid var(--m200);padding-top:14px"><div><b>Razem</b><br><span style="color:var(--ok);font-size:13px" id="kr-hint"></span></div><b style="font-size:24px" id="kr-sum"></b></div>
<div class="bar"><i id="kr-bar"></i></div><button class="btn btn-gold btn-block" id="kr-go"></button>
<button class="link" id="kr-surprise" style="justify-content:center">✦ Niespodzianka Ducha – uzupełnij za mnie</button></div>
<div><div class="chips" style="margin:0 0 20px"><button class="chip fchip on" data-fam="all">Wszystkie</button>${FAMILIES.map(f => `<button class="chip fchip" data-fam="${f.id}"><i style="background:${f.color}"></i>${f.name}</button>`).join('')}</div>
<div class="kr-pick">${BEERS.map(b => `<div class="pick" data-slug="${b.slug}" data-fam="${b.fam.join(' ')}"><div class="im" style="--c:${b.color}">${img(`piwa/${b.slug}.webp`, b.name)}</div><b>${b.name}</b><small>${b.abv}% · ${zl(b.price)}</small><div class="ctl"></div></div>`).join('')}</div></div></div>
<div class="kr-mini"><div><b id="kr-mini-n">0/6</b><span id="kr-mini-sum"></span></div><button class="btn btn-gold" id="kr-mini-go">Do koszyka</button></div></main>` + footer() + shell();
write('duch-sanu/kreator.html', kreator);

/* ================= GIFTS ================= */
const prezenty = head('Zestawy i prezenty – Duch Sanu (projekt)', 'Zestaw Miś Wojtek, nosidełka, kufel i koszulki z kolekcji Bieszczady.') + header() + `
<main class="wrap"><div class="list-head"><p class="eb">Zestawy, nosidełka i merch</p><h1 class="h1">Prezent z duszą</h1><p class="muted" style="max-width:560px">Gotowe zestawy, nosidełka do skompletowania w kreatorze i kolekcja „Bieszczady”. Bilecik z życzeniami dodamy gratis.</p></div>
<div class="grid" style="padding-bottom:96px">${MERCH.map(m => `<article class="card"><div class="card-img plain"><span class="badge">${m.tag}</span><img src="${B}/img/merch/${m.img}.webp" alt="${esc(m.name)}" loading="lazy" style="inset:0;width:100%;height:100%;object-fit:${m.tag === 'Merch' && m.img.startsWith('tshirt') ? 'cover' : 'contain'}"></div><div><h3 style="font-size:24px">${m.name}</h3><p>${m.note}</p></div><div class="card-row"><div class="price">${zl(m.price)}</div>${m.tag === 'Nosidełko' ? `<a class="btn btn-line" style="margin-left:auto;height:44px;padding:0 18px" href="${B}/kreator">Skomponuj</a>` : `<button class="add" data-add="${m.slug}" aria-label="Dodaj">${ic('plus')}</button>`}</div></article>`).join('')}</div></main>` + footer() + shell();
write('duch-sanu/prezenty.html', prezenty);

/* ================= CHECKOUT ================= */
const opt = (name, id, title, sub, price, on, data = '') => `<label class="opt"><input type="radio" name="${name}" id="${id}" ${on ? 'checked' : ''} ${data}><span><b>${title}</b><small>${sub}</small></span>${price ? `<span class="pr2">${price}</span>` : ''}</label>`;
const kasa = head('Kasa – Duch Sanu (projekt)', 'Zamówienie na jednej stronie.') + demoBar + `
<header class="wrap co-hdr"><a class="link" href="${B}/piwa">${ic('back')}<span class="hide-m">Wróć do sklepu</span></a><a href="${B}"><img class="wm" src="${B}/img/logo/napis-ciemny.webp" alt="Duch Sanu" height="30"></a><span class="link" style="justify-content:flex-end">${ic('lock')}<span class="hide-m">Bezpieczna płatność</span></span></header>
<main class="wrap co" id="checkout"><form id="co-form" novalidate><h1>Kasa</h1>
<div class="express"><button type="button" data-express>Apple Pay</button><button type="button" data-express>Google Pay</button><button type="button" onclick="document.getElementById('pay-blik').click();document.querySelector('.blik input').focus()">BLIK</button></div>
<div class="or">albo zamów jako gość – konto nie jest potrzebne</div>
<div class="blk"><div class="blk-h"><i>1</i><h2>Kontakt</h2><a class="link" href="#" style="color:var(--buk);font-size:13px">Masz konto? Zaloguj się</a></div><div class="fields"><div class="f"><label for="f-mail">E-mail</label><input id="f-mail" type="email" autocomplete="email" placeholder="anna.nowak@poczta.pl"></div><div class="f"><label for="f-tel">Telefon (SMS od InPost)</label><input id="f-tel" type="tel" autocomplete="tel" placeholder="+48 600 000 000"></div></div></div>
<div class="blk"><div class="blk-h"><i>2</i><h2>Dostawa</h2></div>
${opt('ship', 's1', 'InPost Paczkomat 24/7', 'Wysyłka w 24 h · wybór paczkomatu na mapie', '15,99 zł', 1, 'data-price="15.99" data-label="Paczkomat InPost"')}${opt('ship', 's2', 'Kurier InPost', 'Dostawa pod drzwi w 1–2 dni robocze', '19,99 zł', 0, 'data-price="19.99" data-label="Kurier InPost"')}${opt('ship', 's3', 'Odbiór w browarze – Bachórz 111', 'Gotowe w 24 h · pn–pt 8:00–16:00', '0 zł', 0, 'data-price="0" data-label="Odbiór w browarze"')}</div>
<div class="blk"><div class="blk-h"><i>3</i><h2>Dane do zamówienia</h2></div><div class="fields"><div class="f"><label for="f-name">Imię i nazwisko</label><input id="f-name" autocomplete="name" placeholder="Anna Nowak"></div><div class="f"><label for="f-addr">Adres (dla kuriera)</label><input id="f-addr" autocomplete="street-address" placeholder="ul. Nadsańska 1, Dynów"></div></div><label class="agree"><input type="checkbox">Chcę fakturę na firmę (NIP)</label></div>
<div class="blk"><div class="blk-h"><i>4</i><h2>Płatność</h2></div>
<label class="opt on"><input type="radio" name="pay" id="pay-blik" checked><span><b>BLIK</b><small>Wpisz 6-cyfrowy kod z aplikacji banku</small></span></label><div class="blik" id="blik-box">${'<input inputmode="numeric" aria-label="Cyfra BLIK">'.repeat(6)}</div>
${opt('pay', 'p2', 'Przelewy24', 'Szybki przelew z Twojego banku', '', 0)}${opt('pay', 'p3', 'Karta płatnicza', 'Visa, Mastercard', '', 0)}${opt('pay', 'p4', 'PayPo', 'Kup teraz, zapłać za 30 dni', '', 0)}</div>
<label class="agree"><input type="checkbox" id="agree">Akceptuję regulamin sklepu i oświadczam, że mam ukończone 18 lat *</label>
<label class="agree"><input type="checkbox">Dołączam do Klubu Ducha – 10% rabatu na kolejne zamówienie</label>
<button class="btn btn-gold btn-block" style="height:64px;font-size:17px;margin-top:12px">${ic('lock')}<span id="co-pay">Zamawiam i płacę</span></button>
<p class="muted" style="font-size:13px">Wersja demo – zamówienie nie zostanie złożone, a płatność nie zostanie pobrana. Konto zaproponujemy dopiero po zamówieniu.</p></form>
<aside class="sum"><h3>Twoje zamówienie</h3><div id="co-items"></div><div class="fields" style="grid-template-columns:1fr auto;margin:16px 0"><div class="f"><input placeholder="Kod rabatowy" aria-label="Kod rabatowy"></div><button type="button" class="btn btn-dark" style="height:50px;border-radius:12px">Użyj</button></div>
<div class="row"><span>Produkty</span><span id="co-sub"></span></div><div class="row"><span>Dostawa</span><span id="co-ship"></span></div><div class="total"><b>Do zapłaty (z VAT)</b><strong id="co-total"></strong></div><p class="muted" style="font-size:13px;margin:0" id="co-free"></p></aside></main>
<div class="blik-modal" id="blik-modal"><div class="blik-card"><div class="phone"><span>BLIK</span><i></i></div><h2 class="h3" style="font-size:32px;font-weight:300">Potwierdź płatność<br>w aplikacji banku</h2><p class="muted" style="margin:0">Masz na to 2 minuty. Nie zamykaj tej strony – przeniesiemy Cię dalej automatycznie.</p><div class="bar"><i id="blik-bar"></i></div></div></div>
<div class="toast" id="toast"></div><script>window.DS=${DATA}</script><script src="${B}/assets/app.js?v=${V}" defer></script></body></html>`;
write('duch-sanu/kasa.html', kasa);

/* ================= CONFIRMATION ================= */
const conf = head('Dziękujemy! – Duch Sanu (projekt)', 'Potwierdzenie zamówienia.') + header() + `
<main class="wrap" id="confirm" style="padding:88px var(--pad) 120px"><div class="split"><div><div class="ok">${ic('check')}</div><p class="eb" style="margin-top:24px">Zamówienie <span id="c-no">DS-2026-0427</span> · opłacone</p>
<h1 class="h2" style="font-size:clamp(44px,5.5vw,72px);margin:14px 0 22px">Dziękujemy, <span id="c-name">Anno</span>!<br>Duch Sanu już pakuje Twoje piwa.</h1>
<p class="muted" style="font-size:17px;max-width:600px">Potwierdzenie wysłaliśmy na <b id="c-mail">Twój e-mail</b>. Kwota: <b id="c-total">186,91 zł</b> · Dostawa: <b id="c-ship">Paczkomat InPost</b>. Gdy paczka wyjedzie z Bachórza, dostaniesz SMS.</p>
<div class="timeline"><div class="on"><b>Opłacone</b><span>przed chwilą</span></div><div><b>Pakujemy</b><span>do jutra 14:00</span></div><div><b>W drodze</b><span>InPost</span></div><div><b>Odbiór</b><span>paczkomat / browar</span></div></div></div>
<div class="white-box">${img('merch/nosidelko-drewno-6.webp', '')}</div></div>
<div class="trio"><div class="legend" style="--c:var(--n800)"><p class="eb light">Zapisz się w 1 klik</p><h3 class="h3" style="font-size:30px;font-weight:300">Załóż konto z tego zamówienia</h3><p style="font-style:normal;font-family:var(--sans);font-size:14px">Hasło ustawisz w mailu. Śledzenie paczki i szybsze zakupy.</p><a class="btn btn-gold" href="#">Załóż konto</a></div>
<div class="pair"><p class="eb">Klub Ducha</p><h3 class="h3" style="font-size:30px;font-weight:300;margin:10px 0">Następna skrzynka 10% taniej</h3><a class="btn btn-line" href="${B}/kreator">Dołącz do Klubu</a></div>
<div class="pair"><p class="eb">Po degustacji</p><h3 class="h3" style="font-size:30px;font-weight:300;margin:10px 0">Oceń piwa – bez logowania</h3><span class="stars">★★★★★</span></div></div></main>` + footer() + shell();
write('duch-sanu/potwierdzenie.html', conf);

/* assets */
mkdirSync(join(OUT, 'duch-sanu/assets'), { recursive: true });
for (const f of ['style.css', 'app.js']) copyFileSync(join(ROOT, 'projects/duch-sanu/src', f), join(OUT, 'duch-sanu/assets', f));
console.log(`Duch Sanu: ${BEERS.length} kart piw + 6 stron → public/duch-sanu`);
