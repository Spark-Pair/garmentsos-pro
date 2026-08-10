const CACHE_NAME = 'garmentsos-pro-cache-v3';
const urlsToCache = [
  '/offline.html'
];

// Install event
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return Promise.all(
        urlsToCache.map(url =>
          fetch(url).then(response => {
            if (!response.ok) {
              throw new Error(`Request for ${url} failed with status ${response.status}`);
            }
            return cache.put(url, response.clone());
          }).catch(err => {
            console.warn(`Skipping cache for ${url}:`, err);
          })
        )
      );
    }).catch(err => {
      console.error('Caching failed during install:', err);
    })
  );
});

// Activate event
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames
          .filter(name => name !== CACHE_NAME)
          .map(name => caches.delete(name))
      );
    })
  );
});

// Fetch event
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request)
      .then(response => response)
      .catch(() =>
        caches.match(event.request).then(response =>
          response || caches.match('/offline.html')
        )
      )
  );
});
