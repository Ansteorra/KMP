/* Only the public offline shell and build-controlled assets belong in Cache Storage. */
const SW_VERSION = '4.0.0';
// Cache format stays stable so a failed worker update retains the last usable public shell.
const CACHE_NAME = 'kmp-public-offline-v3.0.0';
const SHELL = '/offline';
const MANIFEST = '/offline/assets';
const SHELLS = [SHELL, '/offline?page=rsvps', '/offline?page=calendar'];
const ownedCache = name => name === 'offline-cache-activity-card' || name.startsWith('kmp-mobile-v') || name.startsWith('kmp-public-offline-v');
const publicResponse = response => response?.ok && !response.redirected
    && !/no-store|private/i.test(response.headers.get('Cache-Control') || '');
const assetPath = path => /^\/(?:js|css|fonts|assets)\/[a-zA-Z0-9_.-]+\.(?:js|css|woff2?|png|svg)$/.test(path);

async function installPublicShell() {
    const manifest = await fetch(MANIFEST, { cache: 'no-store', credentials: 'omit' });
    if (!publicResponse(manifest) || manifest.headers.get('X-KMP-Public-Offline') !== '1') throw new Error('Invalid offline manifest');
    const data = await manifest.clone().json();
    if (!Array.isArray(data.assets) || !data.assets.length) throw new Error('Empty offline manifest');
    const assets = data.assets.filter(path => typeof path === 'string' && assetPath(path));
    if (assets.length !== data.assets.length) throw new Error('Invalid offline assets');
    const shells = await Promise.all(SHELLS.map(async path => {
        const response = await fetch(path, { cache: 'no-store', credentials: 'omit' });
        if (!publicResponse(response) || response.headers.get('X-KMP-Public-Offline') !== '1') throw new Error('Invalid mobile shell');
        return [path, response];
    }));
    const cache = await caches.open(CACHE_NAME);
    await Promise.all(assets.map(async path => {
        if (await cache.match(path)) return;
        const response = await fetch(path, { credentials: 'omit' });
        if (!publicResponse(response)) throw new Error('Offline asset unavailable');
        await cache.put(path, response);
    }));
    // Publish the shell only after every required asset is saved. Failed refreshes retain the usable shell.
    await cache.put(MANIFEST, manifest);
    await Promise.all(shells.map(([path, response]) => cache.put(path, response)));
    await cache.put('/offline/ready', new Response('ready'));
}

// Security cleanup must activate even when an asset is temporarily unavailable.
self.addEventListener('install', event => event.waitUntil(installPublicShell().catch(() => {}).then(() => self.skipWaiting())));
self.addEventListener('activate', event => event.waitUntil((async () => {
    await Promise.all((await caches.keys()).filter(name => ownedCache(name) && name !== CACHE_NAME).map(name => caches.delete(name)));
    await self.clients.claim();
    for (const client of await self.clients.matchAll({ type: 'window' })) client.postMessage({ type: 'OFFLINE_SECURITY_UPDATE', version: SW_VERSION });
})()));
async function shellReady() {
    const cache = await caches.open(CACHE_NAME);
    const manifest = await cache.match(MANIFEST);
    if (!manifest || !await cache.match('/offline/ready')) return false;
    if (!(await Promise.all(SHELLS.map(path => cache.match(path)))).every(Boolean)) return false;
    const data = await manifest.json();
    if (!Array.isArray(data.assets) || !data.assets.length) return false;
    const entries = await Promise.all(data.assets.map(path => assetPath(path) ? cache.match(path) : null));
    return entries.every(Boolean);
}
self.addEventListener('message', event => {
    if (event.data?.type === 'OFFLINE_STATUS') {
        event.waitUntil(shellReady().then(ready => event.ports[0]?.postMessage({ ready }))
            .catch(() => event.ports[0]?.postMessage({ ready: false })));
    }
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
        /^\/(?:offline\/?|members\/(?:view-mobile-card|login)\/?|gathering-attendances\/my-rsvps\/?|gatherings\/mobile-calendar\/?)$/i.test(url.pathname);
    if (!mobileNavigation && !assetPath(url.pathname)) return;
    event.respondWith((async () => {
        const cache = await caches.open(CACHE_NAME);
        const page = url.pathname.includes('/my-rsvps') ? 'rsvps'
            : url.pathname.includes('/mobile-calendar') ? 'calendar' : url.searchParams.get('page');
        const shell = ['rsvps', 'calendar'].includes(page) ? `${SHELL}?page=${page}` : SHELL;
        const cached = await cache.match(mobileNavigation ? shell : url.pathname);
        // Hashed, build-controlled assets do not need a network round trip.
        if (!mobileNavigation && cached) return cached;
        const controller = new AbortController();
        let timer;
        try {
            const response = await Promise.race([
                fetch(request, { signal: controller.signal, cache: 'no-store' }),
                new Promise((resolve, reject) => {
                    timer = setTimeout(() => { controller.abort(); reject(new Error('Connection timed out')); }, 4000);
                })
            ]);
            if (mobileNavigation && response.status >= 500 && cached) return cached;
            return response;
        } catch (error) {
            if (cached) return cached;
            throw error;
        } finally { clearTimeout(timer); }
    })());
});
