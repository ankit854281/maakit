// Chairman app service worker: only makes the app installable. It keeps nothing offline on purpose —
// fee figures must always come fresh from the server, never from an old copy on the phone.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
