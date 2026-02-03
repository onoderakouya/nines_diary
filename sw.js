self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open('nines-diary-v1').then(cache => cache.addAll([
      './index.php',
      './login.php',
      './manifest.webmanifest'
    ]))
  );
});
self.addEventListener('fetch', (e) => {
  e.respondWith(
    caches.match(e.request).then(r => r || fetch(e.request))
  );
});
