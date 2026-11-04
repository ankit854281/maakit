// Teacher app service worker.
// Owner, 5 Nov 2026: the app must open without internet. Only the app's own files (page, scripts, styles, logo,
// fonts) are kept here, always network-first so a new version is used as soon as there is internet.
// School data is NEVER cached by this worker: api.php / *_api.php always go to the server. The teacher's last data
// copy lives in the app itself (IndexedDB, teacher.js `IDB`) and unsent changes wait in the app's own queue.
const CACHE = 'snmsis-teacher-shell-v2';
const SHELL = /\/school\/(teacher\.php|teacher[-\w]*\.(js|css|json)|login-otp\.js|img\/.+|fonts\/.+)(\?.*)?$/;
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(caches.keys()
  .then(keys => Promise.all(keys.filter(k => k.startsWith('snmsis-teacher-shell') && k !== CACHE).map(k => caches.delete(k))))
  .then(() => self.clients.claim())));
self.addEventListener('fetch', e => {
  const req = e.request, url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== self.location.origin) return;
  const isPage = req.mode === 'navigate' && /\/school\/teacher\.php$/.test(url.pathname);
  if (!isPage && !SHELL.test(url.pathname + url.search)) return;          // APIs and everything else: untouched
  e.respondWith(fetch(req).then(res => {
    if (res.ok) { const copy = res.clone(); caches.open(CACHE).then(c => c.put(isPage ? url.origin + url.pathname : req, copy)); }
    return res;
  }).catch(() => caches.match(isPage ? url.origin + url.pathname : req).then(hit => hit ||
    new Response('<p style="font:16px sans-serif;padding:24px">No internet. Please connect and open the app again.</p>',
      { headers: { 'Content-Type': 'text/html' } }))));
});
