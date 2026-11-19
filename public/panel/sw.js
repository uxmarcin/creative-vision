// Service worker panelu: dane zawsze z sieci (nic poufnego w pamięci), statyczne pliki z pamięci, a bez internetu ekran offline.
const V = 'cvpanel-3';
const STATIC = ['/panel/offline.htm', '/panel/assets/logo-white.svg', '/panel/assets/icon-192.png'];
self.addEventListener('install', e => { e.waitUntil(caches.open(V).then(c => c.addAll(STATIC)).then(() => self.skipWaiting())); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(k => Promise.all(k.filter(x => x !== V).map(x => caches.delete(x)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', e => {
  const r = e.request, u = new URL(r.url);
  if (r.method !== 'GET' || u.origin !== location.origin) return;
  if (/^\/(panel\/assets|fonts)\//.test(u.pathname)) {
    e.respondWith(caches.open(V).then(c => c.match(r).then(hit => hit || fetch(r).then(res => { if (res.ok) c.put(r, res.clone()); return res; }))));
    return;
  }
  if (r.mode === 'navigate') e.respondWith(fetch(r).catch(() => caches.match('/panel/offline.htm')));
});
self.addEventListener('push', e => {
  let d = {}; try { d = e.data ? e.data.json() : {}; } catch (x) { d = { title: 'Panel Creative Vision', body: e.data ? e.data.text() : '' }; }
  e.waitUntil(self.registration.showNotification(d.title || 'Panel Creative Vision', { body: d.body || '', icon: '/panel/assets/icon-192.png', badge: '/panel/assets/icon-192.png', tag: d.tag || 'cv', data: { url: d.url || '/panel' } }));
});
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = new URL((e.notification.data && e.notification.data.url) || '/panel', location.origin).href;
  e.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
    for (const c of list) if (c.url.indexOf('/panel') !== -1 && 'focus' in c) { c.navigate(url); return c.focus(); }
    return clients.openWindow(url);
  }));
});
