import vm from 'vm';
import fs from 'fs';
import path from 'path';
const source = fs.readFileSync(path.join(__dirname, '../../../webroot/sw.js'), 'utf8');
function worker() {
    const handlers = {};
    const stores = new Map();
    const cache = name => {
        if (!stores.has(name)) stores.set(name, new Map());
        const map = stores.get(name);
        return { put: jest.fn(async (key, value) => map.set(typeof key === 'string' ? key : key.url, value)),
            match: jest.fn(async key => map.get(typeof key === 'string' ? key : key.url)) };
    };
    const context = { URL, AbortController, setTimeout, clearTimeout, Response: class { constructor(body) { this.body = body; } }, self: { location: { origin: 'https://kmp.test' }, addEventListener: (name, fn) => { handlers[name] = fn; },
        clients: { claim: jest.fn(), matchAll: async () => [] }, skipWaiting: jest.fn() },
        caches: { keys: async () => [...stores.keys()], delete: async name => stores.delete(name), open: async name => cache(name) }, fetch: jest.fn() };
    vm.runInNewContext(source, context);
    return { handlers, stores, context };
}
test('private GETs and JSON/photo responses never enter a cache or fall back to legacy private entries', async () => {
    const { handlers, stores } = worker(); stores.set('kmp-mobile-v2.1.1', new Map([['https://kmp.test/members/view/1', 'PRIVATE']]));
    for (const url of ['/members/view/1', '/members/view-mobile-card-json', '/members/mobile-card-photo', '/gathering-attendances/my-rsvps?api=1']) {
        const event = { request: { url: 'https://kmp.test' + url, method: 'GET', mode: 'cors' }, respondWith: jest.fn() };
        handlers.fetch(event); expect(event.respondWith).not.toHaveBeenCalled();
    }
});
test('activation deletes old private caches without copying them and preserves unrelated caches', async () => {
    const { handlers, stores } = worker(); stores.set('kmp-mobile-v2.1.1', new Map([['/private', 'PII']])); stores.set('another-app', new Map());
    let activation; handlers.activate({ waitUntil: promise => { activation = promise; } }); await activation;
    expect(stores.has('kmp-mobile-v2.1.1')).toBe(false); expect(stores.has('another-app')).toBe(true);
    expect(JSON.stringify([...stores])).not.toContain('PII');
});
test('CACHE_URLS from old clients cannot populate the public cache', () => {
    const { handlers, context } = worker(); handlers.message({ data: { type: 'CACHE_URLS', payload: ['/members/view/1'] } });
    expect(context.fetch).not.toHaveBeenCalled();
});

test('an unavailable public asset cannot keep the old unsafe worker active', async () => {
    const { handlers, context } = worker();
    context.fetch.mockRejectedValue(new Error('Temporary network failure'));
    let installation;
    handlers.install({ waitUntil: promise => { installation = promise; } });
    await installation;
    expect(context.self.skipWaiting).toHaveBeenCalledTimes(1);
});
test('offline preparation reports failure instead of claiming readiness when a public fetch fails', async () => {
    const { handlers, context } = worker();
    context.fetch.mockRejectedValue(new Error('Asset missing'));
    const port = { postMessage: jest.fn() };
    let preparation;
    handlers.message({ data: { type: 'PREPARE_OFFLINE' }, ports: [port], waitUntil: promise => { preparation = promise; } });
    await preparation;
    expect(port.postMessage).toHaveBeenCalledWith({ ready: false });
});

test.each(['/members/login', '/members/view-mobile-card', '/gathering-attendances/my-rsvps', '/gatherings/mobile-calendar', '/offline'])('mobile entry %s opens the public shell on connection failure', async url => {
    const { handlers, stores, context } = worker();
    stores.set('kmp-public-offline-v3.0.0', new Map([['/offline', 'PUBLIC-SHELL'], ['/offline?page=rsvps', 'PUBLIC-SHELL'], ['/offline?page=calendar', 'PUBLIC-SHELL']]));
    const navigate = async () => {
        let pending;
        handlers.fetch({ request: { url: 'https://kmp.test' + url, method: 'GET', mode: 'navigate' }, respondWith: promise => { pending = promise; } });
        return pending;
    };
    context.fetch.mockRejectedValue(new Error('Disconnected'));
    expect(await navigate()).toBe('PUBLIC-SHELL');
    expect(context.fetch).toHaveBeenLastCalledWith(expect.any(Object), expect.objectContaining({ cache: 'no-store' }));
    context.fetch.mockResolvedValue({ status: 503 });
    expect(await navigate()).toBe('PUBLIC-SHELL');
    context.fetch.mockResolvedValue({ status: 200, body: 'PRIVATE ONLINE HTML' });
    expect((await navigate()).status).toBe(200);
    expect(stores.get('kmp-public-offline-v3.0.0').size).toBe(3);
});

test('a hanging connection falls back within four seconds', async () => {
    jest.useFakeTimers();
    try {
        const { handlers, stores, context } = worker();
        stores.set('kmp-public-offline-v3.0.0', new Map([['/offline', 'PUBLIC-SHELL'], ['/offline?page=rsvps', 'PUBLIC-SHELL'], ['/offline?page=calendar', 'PUBLIC-SHELL']]));
        context.fetch.mockImplementation(() => new Promise(() => {}));
        let pending;
        handlers.fetch({ request: { url: 'https://kmp.test/offline', method: 'GET', mode: 'navigate' }, respondWith: promise => { pending = promise; } });
        await jest.advanceTimersByTimeAsync(4000);
        expect(await pending).toBe('PUBLIC-SHELL');
    } finally { jest.useRealTimers(); }
});


const publicReply = data => ({ ok: true, redirected: false, headers: { get: name => name === 'X-KMP-Public-Offline' ? '1' : 'public' },
    clone() { return this; }, json: async () => data });
const askWorker = async (handlers, type) => {
    let pending;
    const port = { postMessage: jest.fn() };
    handlers.message({ data: { type }, ports: [port], waitUntil: promise => { pending = promise; } });
    await pending;
    return port.postMessage.mock.calls[0][0];
};
test('shell readiness checks actual assets, not just the ready marker', async () => {
    const { handlers, stores } = worker();
    const rows = new Map([['/offline', 'SHELL'], ['/offline?page=rsvps', 'RSVPS'], ['/offline?page=calendar', 'CALENDAR'], ['/offline/ready', 'ready'],
        ['/offline/assets', publicReply({ assets: ['/js/build.js'] })]]);
    stores.set('kmp-public-offline-v3.0.0', rows);
    expect(await askWorker(handlers, 'OFFLINE_STATUS')).toEqual({ ready: false });
    rows.set('/js/build.js', 'JS');
    expect(await askWorker(handlers, 'OFFLINE_STATUS')).toEqual({ ready: true });
});
test('failed refresh and worker activation preserve the previously ready public shell', async () => {
    const { handlers, stores, context } = worker();
    const rows = new Map([['/offline', 'OLD PUBLIC SHELL'], ['/offline?page=rsvps', 'RSVPS'], ['/offline?page=calendar', 'CALENDAR'], ['/offline/ready', 'ready'],
        ['/offline/assets', publicReply({ assets: ['/js/old.js'] })], ['/js/old.js', 'OLD JS']]);
    stores.set('kmp-public-offline-v3.0.0', rows);
    stores.set('kmp-mobile-v2.1.1', new Map([['/private', 'PRIVATE']]));
    context.fetch.mockResolvedValueOnce(publicReply({ assets: ['/js/new.js'] }))
        .mockResolvedValueOnce(publicReply('NEW SHELL')).mockRejectedValueOnce(new Error('Interrupted download'));
    expect(await askWorker(handlers, 'PREPARE_OFFLINE')).toEqual({ ready: false });
    let activation;
    handlers.activate({ waitUntil: promise => { activation = promise; } }); await activation;
    expect(rows.get('/offline')).toBe('OLD PUBLIC SHELL');
    expect(await askWorker(handlers, 'OFFLINE_STATUS')).toEqual({ ready: true });
    expect(stores.has('kmp-mobile-v2.1.1')).toBe(false);
});


test('bundled font query strings use the same precached public asset offline', async () => {
    const { handlers, stores, context } = worker();
    stores.set('kmp-public-offline-v3.0.0', new Map([['/fonts/bootstrap-icons-HASH.woff2', 'FONT']]));
    let pending;
    handlers.fetch({ request: { url: 'https://kmp.test/fonts/bootstrap-icons-HASH.woff2?package-hash', method: 'GET', mode: 'cors' }, respondWith: promise => { pending = promise; } });
    expect(await pending).toBe('FONT');
    expect(context.fetch).not.toHaveBeenCalled();
});


test.each([
    ['/members/view-mobile-card', 'CARD'],
    ['/gathering-attendances/my-rsvps', 'RSVPS'],
    ['/gatherings/mobile-calendar?year=2026&month=9', 'CALENDAR'],
    ['/offline?page=rsvps', 'RSVPS'],
    ['/offline?page=calendar', 'CALENDAR'],
])('navigation to %s uses its own normal mobile page template', async (url, expected) => {
    const { handlers, stores, context } = worker();
    stores.set('kmp-public-offline-v3.0.0', new Map([['/offline', 'CARD'],
        ['/offline?page=rsvps', 'RSVPS'], ['/offline?page=calendar', 'CALENDAR']]));
    context.fetch.mockRejectedValue(new Error('Disconnected'));
    let pending;
    handlers.fetch({ request: { url: 'https://kmp.test' + url, method: 'GET', mode: 'navigate' },
        respondWith: promise => { pending = promise; } });
    expect(await pending).toBe(expected);
});
