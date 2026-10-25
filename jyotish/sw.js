/* ============================================================
   Maakit Jyotish — app ka service worker (sirf /jyotish/ ke liye)
   - css/js/icons: cache se turant, peeche se naya le aate hain
   - page: pehle net (naya version), net na ho to cache wala page
   - ai.php, track.php aur koi bhi POST: kabhi cache nahi
   Naya version bhejte waqt V badaliye.
   ============================================================ */
const V = 'mj-v20';
const CORE = ['/jyotish/book.js?v=2', '/jyotish/pandit.js?v=1', '/jyotish/', '/jyotish/jyotish.css?v=20', '/jyotish/i18n.js?v=13', '/jyotish/app.js?v=18', '/jyotish/daily.js?v=2',
  '/jyotish/engine.js?v=1', '/jyotish/astronomy.min.js?v=2.1.19', '/jyotish/manifest.json', '/jyotish/icons/icon-192.png'];
self.addEventListener('install', e => {
  e.waitUntil(caches.open(V).then(c => Promise.allSettled(CORE.map(u => c.add(u)))).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k.startsWith('mj-') && k !== V).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const req = e.request, url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin || !url.pathname.startsWith('/jyotish/')) return;
  if (/\/(ai|track)\.php$/.test(url.pathname)) return;
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).then(r => { if (r.ok) { const c = r.clone(); caches.open(V).then(x => x.put('/jyotish/', c)); } return r; })
      .catch(() => caches.match('/jyotish/')));
    return;
  }
  e.respondWith(caches.match(req).then(hit => {
    const net = fetch(req).then(r => { if (r.ok) { const c = r.clone(); caches.open(V).then(x => x.put(req, c)); } return r; }).catch(() => hit);
    return hit || net;
  }));
});

