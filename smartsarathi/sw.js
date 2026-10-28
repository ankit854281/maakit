// Smart Sarathi service worker — only an offline fallback page.
// Pages are NOT cached: bookings, OTP and jobs must always be fresh.
const OFFLINE = '/smartsarathi/offline.html';
self.addEventListener('install', (e) => {
  e.waitUntil(caches.open('sr-offline-v1').then((c) => c.add(OFFLINE)));
  self.skipWaiting();
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== 'sr-offline-v1').map((k) => caches.delete(k)))));
  self.clients.claim();
});
self.addEventListener('fetch', (e) => {
  if (e.request.mode !== 'navigate') return;
  e.respondWith(fetch(e.request).catch(() => caches.match(OFFLINE)));
});
