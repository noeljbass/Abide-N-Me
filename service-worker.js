const CACHE_NAME = 'feed-my-sheep-shell-v9a';
const APP_SHELL = [
  './', './index.html', './offline.html', './manifest.webmanifest',
  './assets/css/app.css', './assets/js/app.js', './assets/js/router.js',
  './assets/js/pwa.js', './assets/js/auth.js', './assets/js/api.js', './assets/js/groups.js', './assets/js/bible.js', './assets/js/plans.js', './assets/js/today.js', './assets/js/audio.js', './assets/js/audio-state.js'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(caches.keys().then((keys) => Promise.all(
    keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
  )));
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET' || new URL(event.request.url).origin !== self.location.origin) return;

  event.respondWith(fetch(event.request).then((response) => {
    if (response.ok && ['style', 'script', 'image'].includes(event.request.destination)) {
      const copy = response.clone();
      caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
    }
    return response;
  }).catch(async () => {
    const cached = await caches.match(event.request);
    if (cached) return cached;
    if (event.request.mode === 'navigate') return caches.match('./offline.html');
    return Response.error();
  }));
});
