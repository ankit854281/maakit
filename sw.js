/* ============================================================
   Maakit — service worker
   Kaam: dheeme net par bhi website turant khule, aur net na ho
   to bhi saaman ki list dikhe.

   Niyam:
   - design/icon (css, png, manifest): pehle cache se, peeche se
     naya bhi le aate hain
   - page (html): pehle net se (taaki naya order/status dikhe),
     net na ho to cache se, wo bhi na ho to "net nahi hai" page
   - panel, login, POST: kabhi cache nahi (hamesha taaza)
   ============================================================ */

const V     = 'maakit-v1';
const SHELL = V + '-shell';
const PAGES = V + '-pages';

const CORE = [
  '/',
  '/order.php',
  '/assets/style.css',
  '/assets/app.css',
  '/assets/icon-192.png',
  '/manifest.json',
  '/offline.html',
];

// jin raaston ko kabhi cache nahi karna
const NEVER = /^\/(admin|bpo|delivery|login\.php|logout\.php|shop\.php|account\.php)/;

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(SHELL)
      .then(c => Promise.allSettled(CORE.map(u => c.add(u))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys()
      .then(ks => Promise.all(ks.filter(k => !k.startsWith(V)).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;   // bahar ki cheez chhod do
  if (NEVER.test(url.pathname)) return;              // panel/login kabhi cache nahi

  // ---- design aur icon: pehle cache se ----
  if (/\.(css|js|png|jpg|jpeg|webp|svg|woff2?|json)$/i.test(url.pathname)) {
    e.respondWith(
      caches.match(req).then(hit => {
        const live = fetch(req).then(res => {
          if (res && res.ok) { caches.open(SHELL).then(c => c.put(req, res.clone())); }
          return res;
        }).catch(() => hit);
        return hit || live;
      })
    );
    return;
  }

  // ---- page: pehle net se, warna cache se ----
  e.respondWith(
    fetch(req)
      .then(res => {
        if (res && res.ok && res.type === 'basic') {
          const copy = res.clone();
          caches.open(PAGES).then(c => c.put(req, copy));
        }
        return res;
      })
      .catch(() =>
        caches.match(req)
          .then(hit => hit || caches.match('/order.php'))
          .then(hit => hit || caches.match('/offline.html'))
      )
  );
});

// naya version turant lagane ke liye
self.addEventListener('message', e => { if (e.data === 'skip') self.skipWaiting(); });
