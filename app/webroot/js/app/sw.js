const CACHE_NAME = 'offline-cache-activity-card';

self.addEventListener('install', event => {
    // Skip waiting to activate the new service worker immediately
    self.skipWaiting();
});

self.addEventListener('message', event => {
    if (event.data && event.data.type === 'CACHE_URLS') {
        const urlsToCache = event.data.payload;
        caches.open(CACHE_NAME).then(cache => {
            cache.addAll(urlsToCache).then(() => {
                console.log('All URLs have been cached');
            }).catch(error => {
                console.error('Failed to cache:', error);
            });
        });
    }
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                if (response) {
                    return response;
                }
                return fetch(event.request).then(
                    response => {
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }
                        const responseToCache = response.clone();
                        caches.open(CACHE_NAME)
                            .then(cache => {
                                cache.put(event.request, responseToCache);
                            });
                        return response;
                    }
                );
            })
    );
});

self.addEventListener('activate', event => {
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});
