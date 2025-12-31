//change
const CACHE_NAME = 'offline-cache-activity-card';

const addResourcesToCache = async (resources) => {
    caches.open(CACHE_NAME).then(cache => {
        cache.addAll(resources).then(() => {
            console.log('All URLs have been cached');
        }).catch(error => {
            console.error('Failed to cache:', error);
        });
    });
};

const putInCache = async (request, response) => {
    const cache = await caches.open(CACHE_NAME);
    await cache.put(request, response);
};

self.addEventListener('install', event => {
    // Skip waiting to activate the new service worker immediately
    self.skipWaiting();
});
self.offline = false;
self.addEventListener('message', event => {
    if (event.data && event.data.type) {
        switch (event.data.type) {
            case 'CACHE_URLS':
                const urlsToCache = event.data.payload;
                addResourcesToCache(urlsToCache);
                break;
            case 'OFFLINE':
                console.log('Offline event received');
                self.offline = true;
                break;
            case 'ONLINE':
                console.log('Online event received');
                self.offline = false;
                break;
            // Add more cases here for other event data types
            default:
                console.warn('Unknown event data type:', event.data.type);
        }
    }
});

self.addEventListener('fetch', (event) => {
    // Only handle http and https requests, skip chrome-extension and other schemes
    if (!event.request.url.startsWith('http')) {
        return;
    }

    // Only cache GET requests - POST, PUT, DELETE etc. cannot be cached
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        (async function () {
            try {
                // Try to fetch from network first
                const networkResponse = await fetch(event.request);
                
                // Cache successful responses (including JSON endpoints)
                if (networkResponse && networkResponse.status === 200) {
                    const cache = await caches.open(CACHE_NAME);
                    // Clone the response because it can only be consumed once
                    cache.put(event.request, networkResponse.clone());
                }
                
                return networkResponse;
            } catch (err) {
                // Network failed, try to serve from cache
                console.log('Network request failed, serving from cache:', event.request.url);
                const cachedResponse = await caches.open(CACHE_NAME).then((cache) => 
                    cache.match(event.request, { ignoreVary: true })
                );
                
                if (cachedResponse) {
                    return cachedResponse;
                }
                
                // If no cache available, return a basic offline response
                console.error('No cache available for:', event.request.url);
                throw err;
            }
        })(),
    );
});
/*
self.addEventListener('fetch', event => {
    event.respondWith(
        fetch(event.request)
            .then(response => {
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }
                return response;
            })
            .catch(() => {
                var cache await caches.open(CACHE_NAME);
                cache.match(event.request).then(response => {
                    if (response) {
                        return response;
                    }
                    throw new Error('Resource not found in cache');
                });
            })
    );
});
*/

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
