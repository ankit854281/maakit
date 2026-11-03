// Teacher app service worker: only makes the app installable. Nothing is kept offline on purpose —
// attendance and diaries must always come fresh from the server (unsent changes wait in the app itself).
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', e => { if (e.request.mode === 'navigate') e.respondWith(fetch(e.request).catch(() => new Response('<p style="font:16px sans-serif;padding:24px">No internet. Please connect and open the app again.</p>', { headers: { 'Content-Type': 'text/html' } }))); });
