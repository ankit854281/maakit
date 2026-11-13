// Board Prep service worker: makes the app installable. Tests and results always come fresh from the server.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', e => { if (e.request.mode === 'navigate') e.respondWith(fetch(e.request).catch(() => new Response('<p style="font:16px sans-serif;padding:24px">No internet. Please connect and open Board Prep again.</p>', { headers: { 'Content-Type': 'text/html' } }))); });
