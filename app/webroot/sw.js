/* Only the public offline shell and build-controlled assets belong in Cache Storage. */
const SW_VERSION = '3.0.0';
const CACHE_NAME = `kmp-public-offline-v${SW_VERSION}`;
const SHELL = '/offline';
const MANIFEST = '/offline/assets';
const ownedCache = name => name === 'offline-cache-activity-card' || name.startsWith('kmp-mobile-v') || name.startsWith('kmp-public-offline-v');
const publicResponse = response => response?.ok && !response.redirected
    && !/no-store|private/i.test(response.headers.get('Cache-Control') || '');
const assetPath = path => /^\/(?:js|css|fonts|assets)\/[a-zA-Z0-9_.-]+\.(?:js|css|woff2?|png|svg)$/.test(path);

async function installPublicShell() {
    const manifest = await fetch(MANIFEST, { cache: 'no-store', credentials: 'omit' });
    if (!publicResponse(manifest) || manifest.headers.get('X-KMP-Public-Offline') !== '1') throw new Error('Invalid offline manifest');
    const data = await manifest.clone().json();
    const assets = data.assets.filter(path => typeof path === 'string' && assetPath(path));
    const shell = await fetch(SHELL, { cache: 'no-store', credentials: 'omit' });
    if (!publicResponse(shell) || shell.headers.get('X-KMP-Public-Offline') !== '1') throw new Error('Invalid offline shell');
    const cache = await caches.open(CACHE_NAME);
    await cache.put(MANIFEST, manifest);
    await cache.put(SHELL, shell);
    await cache.delete('/offline/ready');
    await Promise.all(assets.map(async path => {
        const response = await fetch(path, { credentials: 'omit' });
        if (!publicResponse(response)) throw new Error('Offline asset unavailable');
        await cache.put(path, response);
    }));
    await cache.put('/offline/ready', new Response('ready'));
}

// Security cleanup must activate even when an asset is temporarily unavailable.
self.addEventListener('install', event => event.waitUntil(installPublicShell().catch(() => {}).then(() => self.skipWaiting())));
self.addEventListener('activate', event => event.waitUntil((async () => {
    await Promise.all((await caches.keys()).filter(name => ownedCache(name) && name !== CACHE_NAME).map(name => caches.delete(name)));
    await self.clients.claim();
    for (const client of await self.clients.matchAll({ type: 'window' })) client.postMessage({ type: 'OFFLINE_SECURITY_UPDATE', version: SW_VERSION });
})()));
self.addEventListener('message', event => {
    if (event.data?.type === 'PREPARE_OFFLINE') {
        event.waitUntil(installPublicShell().then(() => event.ports[0]?.postMessage({ ready: true }))
            .catch(() => event.ports[0]?.postMessage({ ready: false })));
    }
    if (event.data?.type === 'GET_VERSION') event.ports[0]?.postMessage({ version: SW_VERSION });
    if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
    // CACHE_URLS from old clients is deliberately unsupported.
});
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    const mobileNavigation = request.mode === 'navigate' &&
        /^\/(?:offline\/?|members\/view-mobile-card\/?|gathering-attendances\/my-rsvps\/?|gatherings\/mobile-calendar\/?)$/i.test(url.pathname);
    if (!mobileNavigation && !assetPath(url.pathname)) return;
    event.respondWith((async () => {
        try { return await fetch(request); } catch (error) {
            const cache = await caches.open(CACHE_NAME);
            // Assets must already exist in the build allowlist cache. No runtime cache writes.
            const cached = await cache.match(mobileNavigation ? SHELL : request);
            if (cached) return cached;
            throw error;
        }
    })());
});
