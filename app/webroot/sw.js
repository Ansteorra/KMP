/**
 * KMP Service Worker v2.0.0
 * 
 * Features:
 * - Versioned cache with migration support
 * - Network-first with cache fallback strategy
 * - Client notification on updates
 * - Graceful migration from v1.x caches
 */

const SW_VERSION = '2.0.0';
const CACHE_NAME = `kmp-mobile-v${SW_VERSION}`;

// Old cache names to migrate from
const OLD_CACHE_NAMES = [
    'offline-cache-activity-card',
    'kmp-mobile-v1.0.0',
    'kmp-mobile-v1.1.0'
];

// Critical assets to precache on install
const PRECACHE_URLS = [
    '/css/app.css',
    '/js/core.js',
    '/js/index.js',
    '/js/controllers.js',
    '/gathering-attendances/my-rsvps'
];

/**
 * Add resources to cache
 * @param {Array} resources - URLs to cache
 */
const addResourcesToCache = async (resources) => {
    try {
        const cache = await caches.open(CACHE_NAME);
        await cache.addAll(resources);
        console.log('[SW] Resources cached:', resources.length);
    } catch (error) {
        console.error('[SW] Failed to cache resources:', error);
    }
};

/**
 * Migrate entries from old caches to new cache
 * @returns {Promise<number>} Number of entries migrated
 */
const migrateOldCaches = async () => {
    let migratedCount = 0;
    const newCache = await caches.open(CACHE_NAME);
    
    for (const oldCacheName of OLD_CACHE_NAMES) {
        try {
            const oldCache = await caches.open(oldCacheName);
            const requests = await oldCache.keys();
            
            for (const request of requests) {
                const response = await oldCache.match(request);
                if (response) {
                    await newCache.put(request, response);
                    migratedCount++;
                }
            }
            
            console.log(`[SW] Migrated ${requests.length} entries from ${oldCacheName}`);
        } catch (error) {
            console.warn(`[SW] Could not migrate from ${oldCacheName}:`, error);
        }
    }
    
    return migratedCount;
};

/**
 * Delete old caches
 */
const deleteOldCaches = async () => {
    const cacheNames = await caches.keys();
    const deletions = cacheNames
        .filter(name => name !== CACHE_NAME)
        .map(name => {
            console.log('[SW] Deleting old cache:', name);
            return caches.delete(name);
        });
    
    await Promise.all(deletions);
};

/**
 * Notify all clients of service worker update
 */
const notifyClients = async (type, data = {}) => {
    const clients = await self.clients.matchAll({ type: 'window' });
    clients.forEach(client => {
        client.postMessage({
            type,
            version: SW_VERSION,
            ...data
        });
    });
};

// Connection state tracking
self.offline = false;

// Install event - precache critical assets
self.addEventListener('install', event => {
    console.log('[SW] Installing version', SW_VERSION);
    
    event.waitUntil(
        addResourcesToCache(PRECACHE_URLS)
            .then(() => {
                console.log('[SW] Precaching complete, skipping wait');
                return self.skipWaiting();
            })
    );
});

// Activate event - migrate old caches and clean up
self.addEventListener('activate', event => {
    console.log('[SW] Activating version', SW_VERSION);
    
    event.waitUntil(
        (async () => {
            // Migrate entries from old caches
            await migrateOldCaches();
            
            // Delete old caches
            await deleteOldCaches();
            
            // Take control of all clients immediately
            await self.clients.claim();
            
            // Notify clients of update
            await notifyClients('SW_UPDATED');
            
            console.log('[SW] Activation complete');
        })()
    );
});

// Message handler
self.addEventListener('message', event => {
    if (!event.data || !event.data.type) return;
    
    switch (event.data.type) {
        case 'SKIP_WAITING':
            console.log('[SW] Skip waiting requested');
            self.skipWaiting();
            break;
            
        case 'GET_VERSION':
            event.ports[0]?.postMessage({ version: SW_VERSION });
            break;
            
        case 'CACHE_URLS':
            const urlsToCache = event.data.payload;
            if (Array.isArray(urlsToCache)) {
                addResourcesToCache(urlsToCache);
            }
            break;
            
        case 'OFFLINE':
            console.log('[SW] Offline mode');
            self.offline = true;
            break;
            
        case 'ONLINE':
            console.log('[SW] Online mode');
            self.offline = false;
            break;
            
        default:
            console.warn('[SW] Unknown message type:', event.data.type);
    }
});

// Fetch handler - Network first with cache fallback
self.addEventListener('fetch', event => {
    // Only handle http/https requests
    if (!event.request.url.startsWith('http')) {
        return;
    }

    // Only cache GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // Skip caching for API calls that shouldn't be cached
    const url = new URL(event.request.url);
    if (url.pathname.startsWith('/api/csrf') || 
        url.pathname.includes('/login') ||
        url.pathname.includes('/logout')) {
        return;
    }

    event.respondWith(
        (async () => {
            try {
                // Try network first
                const networkResponse = await fetch(event.request);

                // Cache successful responses
                if (networkResponse && networkResponse.status === 200) {
                    const cache = await caches.open(CACHE_NAME);
                    cache.put(event.request, networkResponse.clone());
                }

                return networkResponse;
            } catch (error) {
                // Network failed, try cache
                console.log('[SW] Network failed, serving from cache:', event.request.url);
                
                const cachedResponse = await caches.match(event.request, {
                    ignoreVary: true
                });

                if (cachedResponse) {
                    return cachedResponse;
                }

                // No cache available
                console.error('[SW] No cache available for:', event.request.url);
                throw error;
            }
        })()
    );
});
