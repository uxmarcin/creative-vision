(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var num = function (v) { v = String(v || '').replace(/\s| |zł/g, '').replace(',', '.'); var n = parseFloat(v); return isNaN(n) ? 0 : n; };
  var curr = function () { var c = document.getElementById('currency'); return c && c.value !== 'PLN' ? c.value : 'zł'; };
  var fmt = function (c) { return (c / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0') + '\u00a0' + curr(); };

  // kopiowanie
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = b.getAttribute('data-copy'), old = b.textContent;
      var done = function () { b.textContent = 'Skopiowano'; setTimeout(function () { b.textContent = old; }, 1600); };
      if (navigator.clipboard) navigator.clipboard.writeText(t).then(done, done); else { var i = document.createElement('input'); i.value = t; document.body.appendChild(i); i.select(); document.execCommand('copy'); i.remove(); done(); }
    });
  });

  // NIP z wykazu podatników VAT
  var nb = $('#nipbtn');
  if (nb) nb.addEventListener('click', function () {
    var msg = $('#nipmsg'), nip = $('#nip').value;
    msg.textContent = 'Szukam…'; nb.disabled = true;
    fetch('/panel?a=nip&nip=' + encodeURIComponent(nip), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      nb.disabled = false;
      if (j.error) { msg.textContent = j.error; return; }
      $('#nip').value = j.nip; $('#name').value = j.name; $('#addr1').value = j.addr1; $('#addr2').value = j.addr2;
      msg.textContent = 'Dane z wykazu podatników VAT' + (j.status ? ', status: ' + j.status : '') + '. Sprawdź nazwę, bywa bardzo długa.';
    }).catch(function () { nb.disabled = false; msg.textContent = 'Nie udało się połączyć. Wpisz dane ręcznie.'; });
  });

  $$('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form.submit(); }); });

  $$('tr[data-href]').forEach(function (tr) {
    tr.addEventListener('click', function (e) { if (e.target.closest('a,button,input,form,label,.c-chk')) return; if (e.metaKey || e.ctrlKey) window.open(tr.getAttribute('data-href')); else location.href = tr.getAttribute('data-href'); });
  });

  var mon = $('.months a.on'); if (mon && mon.parentNode.scrollWidth > mon.parentNode.clientWidth) mon.parentNode.scrollLeft = mon.offsetLeft - mon.parentNode.clientWidth / 2 + mon.clientWidth / 2;

  var bdg = document.getElementById('mailbdg');
  if (bdg && bdg.hasAttribute('data-poll')) {
    var last = parseInt(bdg.textContent || '0', 10) || 0, base = document.title;
    var show = function (n) {
      bdg.textContent = n; bdg.hidden = !n;
      document.title = (n ? '(' + n + ') ' : '') + base.replace(/^\(\d+\) /, '');
    };
    var poll = function () {
      fetch('/panel?a=unseen', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        var n = j.unseen || 0;
        if (n > last) { bdg.classList.remove('pop'); void bdg.offsetWidth; bdg.classList.add('pop'); }
        last = n; show(n);
      }).catch(function () {});
    };
    show(last); setTimeout(poll, 3000); setInterval(poll, 60000);
  }

  $$('[data-refresh]').forEach(function (a) { a.addEventListener('click', function () { a.classList.add('busy'); a.lastChild.textContent = ' Odbieram…'; }); });

  var mdb = $('.mdelbtn');
  if (mdb) document.addEventListener('change', function (e) {
    if (!e.target.matches('input[form=mdel]')) return;
    var n = $$('input[form=mdel]:checked').length; mdb.hidden = !n; mdb.textContent = 'Usuń zaznaczone (' + n + ')';
  });

  var pd = $('#plusdlg'), pb = $('[data-plus]');
  if (pd && pb) {
    pb.addEventListener('click', function (e) { if (!window.matchMedia('(max-width:900px)').matches) return; e.preventDefault(); pd.showModal(); });
    $$('[data-close]', pd).forEach(function (b) { b.addEventListener('click', function () { pd.close(); }); });
    pd.addEventListener('click', function (e) { if (e.target === pd) pd.close(); });
  }

  var ver = document.body.getAttribute('data-ver'), upd = $('#upd');
  if (ver && upd) {
    var chk = function () {
      fetch('/panel?a=ver', { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) { if (j.v && j.v !== ver) upd.hidden = false; }).catch(function () {});
    };
    setInterval(chk, 60000); setTimeout(chk, 5000);
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') chk(); });
    $('#updbtn').addEventListener('click', function () {
      this.textContent = 'Aktualizuję…';
      var done = function () { location.reload(); };
      if ('serviceWorker' in navigator) navigator.serviceWorker.getRegistration('/panel').then(function (r) { return r ? r.update() : null; }).then(done, done); else done();
    });
  }

  // powiadomienia push
  var canPush = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  var csrf = document.body.getAttribute('data-csrf');
  var u8 = function (b) { var p = '='.repeat((4 - b.length % 4) % 4), r = atob((b + p).replace(/-/g, '+').replace(/_/g, '/')), o = new Uint8Array(r.length); for (var i = 0; i < r.length; i++) o[i] = r.charCodeAt(i); return o; };
  var ab64 = function (buf) { return btoa(String.fromCharCode.apply(null, new Uint8Array(buf))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); };
  var post = function (a, data) { var f = new FormData(); f.append('csrf', csrf); for (var k in data) f.append(k, data[k]); return fetch('/panel?a=' + a, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }); };
  var swReg = function () { return navigator.serviceWorker.register('/panel?a=sw', { scope: '/panel' }).then(function () { return navigator.serviceWorker.ready; }); };
  var pushOn = function () {
    return Notification.requestPermission().then(function (p) {
      if (p !== 'granted') throw new Error('Nie zezwolono na powiadomienia. Zmienisz to w ustawieniach przeglądarki lub telefonu.');
      return Promise.all([swReg(), fetch('/panel?a=push_key', { credentials: 'same-origin' }).then(function (r) { return r.json(); })]);
    }).then(function (x) {
      return x[0].pushManager.getSubscription().then(function (s) { return s || x[0].pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: u8(x[1].key) }); });
    }).then(function (s) { var j = s.toJSON(); return post('push_sub', { endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth }); });
  };
  var st = $('#pushst'), bon = $('#pushon'), boff = $('#pushoff');
  var showSt = function () {
    if (!st) return;
    if (!canPush) { st.textContent = /iPhone|iPad/.test(navigator.userAgent) ? 'Na iPhonie dodaj panel do ekranu początkowego i otwórz go z ikony.' : 'Ta przeglądarka nie obsługuje powiadomień.'; bon.hidden = true; return; }
    navigator.serviceWorker.getRegistration('/panel').then(function (r) { return r ? r.pushManager.getSubscription() : null; }).then(function (s) {
      bon.hidden = !!s; boff.hidden = !s; st.textContent = s ? 'Włączone na tym urządzeniu.' : (Notification.permission === 'denied' ? 'Zablokowane w ustawieniach przeglądarki.' : '');
    });
  };
  var ask = $('#pushask');
  var noAsk = function () { try { return localStorage.getItem('cv-push-no') === '1'; } catch (e) { return false; } };
  if (ask && canPush && Notification.permission === 'default' && !noAsk()) setTimeout(function () { ask.hidden = false; }, 1500);
  $$('[data-pushon], #pushon').forEach(function (b) { b.addEventListener('click', function () {
    b.disabled = true;
    pushOn().then(function () { if (ask) ask.hidden = true; if (st) showSt(); else b.textContent = 'Włączone'; }).catch(function (e) { alert(e.message); }).then(function () { b.disabled = false; });
  }); });
  $$('[data-pushno]').forEach(function (b) { b.addEventListener('click', function () { ask.hidden = true; try { localStorage.setItem('cv-push-no', '1'); } catch (e) {} }); });
  if (boff) boff.addEventListener('click', function () {
    navigator.serviceWorker.getRegistration('/panel').then(function (r) { return r && r.pushManager.getSubscription(); }).then(function (s) { if (!s) return; var ep = s.endpoint; return s.unsubscribe().then(function () { return post('push_unsub', { endpoint: ep }); }); }).then(showSt);
  });
  showSt();

  var dz = $('#drop');
  if (dz) {
    var di = $('#dropin', dz), go = function () { if (di.files.length) { dz.classList.add('busy'); $('label b', dz).textContent = 'Wgrywam ' + di.files.length + '…'; dz.submit(); } };
    di.addEventListener('change', go);
    ['dragenter', 'dragover'].forEach(function (t) { document.addEventListener(t, function (e) { e.preventDefault(); dz.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (t) { document.addEventListener(t, function (e) { e.preventDefault(); if (t === 'dragleave' && e.relatedTarget) return; dz.classList.remove('over'); }); });
    document.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) { di.files = e.dataTransfer.files; go(); } });
  }
  $$('[data-busy]').forEach(function (b) { b.form && b.form.addEventListener('submit', function () { b.classList.add('busy'); b.lastChild.textContent = ' Pobieram…'; }); });

  var dlg = $('#maildlg');
  if (dlg) {
    $$('[data-mail]').forEach(function (b) { b.addEventListener('click', function () { $('input[name=next]', dlg).value = b.getAttribute('data-mail'); dlg.showModal(); $('input[type=email]', dlg).focus(); }); });
    $$('[data-close]', dlg).forEach(function (b) { b.addEventListener('click', function () { dlg.close(); }); });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
  }

  var form = $('#invform');
  if (!form) return;

  // wybór klienta
  var clients = []; try { clients = JSON.parse($('#clients').textContent); } catch (e) {}
  $('#client_id').addEventListener('change', function () {
    var c = clients.filter(function (x) { return String(x.id) === this.value; }, this)[0];
    ['nip', 'name', 'addr1', 'addr2', 'country', 'email', 'cc'].forEach(function (k) { var el = $('#' + k); if (el) el.value = c ? (c[k] || '') : ''; });
  });

  // pozycje
  var box = $('#items'), tpl = $('#itemtpl'), n = 1000;
  function recalc() {
    var net = 0, vat = 0;
    $$('[data-item]', box).forEach(function (it) {
      var q = num($('[data-q]', it).value) || 1, p = Math.round(num($('[data-p]', it).value) * 100), r = $('[data-v]', it).value;
      var rn = Math.round(p * q), rv = /^\d+$/.test(r) ? Math.round(rn * parseInt(r, 10) / 100) : 0;
      net += rn; vat += rv;
      $('[data-s]', it).textContent = p ? 'Netto ' + fmt(rn) + ' · brutto ' + fmt(rn + rv) : '';
    });
    $('[data-t="net"]').textContent = fmt(net); $('[data-t="vat"]').textContent = fmt(vat); $('[data-t="gross"]').textContent = fmt(net + vat);
  }
  box.addEventListener('input', recalc); box.addEventListener('change', recalc);
  box.addEventListener('click', function (e) {
    var d = e.target.closest('[data-del]'); if (!d) return;
    if ($$('[data-item]', box).length > 1) d.closest('[data-item]').remove(); else $$('input,textarea', d.closest('[data-item]')).forEach(function (i) { if (!i.hasAttribute('data-q')) i.value = ''; });
    recalc();
  var cs = $('#currency'); if (cs) cs.addEventListener('change', recalc);
  var co = $('#country'); if (co) co.addEventListener('change', function () { if (co.value.trim() && $('#lang').value === 'pl') $('#lang').value = 'pl-en'; });
  });
  $('#additem').addEventListener('click', function () {
    var w = document.createElement('div'); w.innerHTML = tpl.innerHTML.replace(/__N__/g, String(n++));
    var el = w.firstElementChild; box.appendChild(el); $('input', el).focus(); recalc();
  });
  recalc();

  // termin płatności
  var issue = $('#issue_date'), due = $('#due_date'), term = $('#term_days'), pills = $$('#pills button');
  function setDue() {
    var d = new Date(issue.value + 'T12:00:00'); if (isNaN(d)) return;
    d.setDate(d.getDate() + parseInt(term.value || '0', 10));
    due.value = d.toISOString().slice(0, 10);
  }
  function mark() { pills.forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-term') === term.value); }); }
  pills.forEach(function (b) { b.addEventListener('click', function () { term.value = b.getAttribute('data-term'); mark(); setDue(); }); });
  issue.addEventListener('change', function () {
    setDue();
    var b = $('#book_ym'), ym = issue.value.slice(0, 7);
    if (b && $$('option', b).some(function (o) { return o.value === ym; })) b.value = ym;
  });
  due.addEventListener('change', function () {
    var a = new Date(issue.value + 'T12:00:00'), b = new Date(due.value + 'T12:00:00');
    if (!isNaN(a) && !isNaN(b)) { term.value = String(Math.max(0, Math.round((b - a) / 864e5))); mark(); }
  });

  // potwierdzenie wysyłki
  form.addEventListener('submit', function (e) {
    var b = e.submitter; if (!b || b.value !== 'send') return;
    var el = $('#email');
    if (!el.value.trim()) { e.preventDefault(); el.setCustomValidity('Podaj e-mail klienta, żeby wysłać fakturę.'); el.reportValidity(); }
  });
  $('#email').addEventListener('input', function () { this.setCustomValidity(''); });
})();
