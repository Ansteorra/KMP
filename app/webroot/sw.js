//change
const CACHE_NAME = 'offline-cache-activity-card';

const addResourcesToCache = async (resources) => {
    const cache = await caches.open('v1');
    await cache.addAll(resources);
};

const putInCache = async (request, response) => {
    const cache = await caches.open('v1');
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
                caches.open(CACHE_NAME).then(cache => {
                    cache.addAll(urlsToCache).then(() => {
                        console.log('All URLs have been cached');
                    }).catch(error => {
                        console.error('Failed to cache:', error);
                    });
                });
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
    if (self.offline) {
        console.log("offline pulling from cache")
        event.respondWith(caches.match(event.request).then(response => { return response; }));
        return;
    }
    console.log("online pulling from web")
    event.respondWith(
        handelRequest(event).catch(error => {
            return caches.match(event.request).then(response => { return response; });
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
