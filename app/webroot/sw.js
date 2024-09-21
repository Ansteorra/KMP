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
                return caches.match(event.request);
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
