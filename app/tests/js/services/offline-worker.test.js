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
    const context = { URL, self: { location: { origin: 'https://kmp.test' }, addEventListener: (name, fn) => { handlers[name] = fn; },
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
