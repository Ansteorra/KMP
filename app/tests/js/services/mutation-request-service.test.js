import { installMutationRequestMetadata } from '../../../assets/js/services/mutation-request-service.js';

describe('mutation origin metadata', () => {
    let host, original, dispose;
    beforeEach(() => {
        original = jest.fn().mockResolvedValue({ ok: true });
        host = { fetch: original, dispatchEvent: jest.fn(),
            location: { href: 'https://tenant.test/members', origin: 'https://tenant.test' } };
        dispose = installMutationRequestMetadata(host);
    });
    afterEach(() => { dispose(); jest.restoreAllMocks(); });

    test('adds metadata once while preserving the body, CSRF header, abort signal, and credentials', async () => {
        const body = new FormData(); body.set('subject', 'Keep this body');
        const signal = new AbortController().signal;
        await host.fetch('/notes/add', { method: 'POST', body, signal, credentials: 'same-origin',
            headers: { 'X-CSRF-Token': 'csrf-token', Accept: 'text/vnd.turbo-stream.html' } });
        expect(original).toHaveBeenCalledTimes(1);
        const options = original.mock.calls[0][1];
        expect(options.body).toBe(body);
        expect(options.signal).toBe(signal);
        expect(options.credentials).toBe('same-origin');
        expect(options.headers.get('X-CSRF-Token')).toBe('csrf-token');
        expect(options.headers.get('X-KMP-Mutation-ID')).toMatch(/^[a-f0-9-]{36}$/);
        expect(host.dispatchEvent).toHaveBeenCalledWith(expect.objectContaining({ type: 'kmp:mutation-committed' }));
    });

    test('leaves ordinary reads and cross-origin requests unchanged', async () => {
        const read = { headers: { Accept: 'text/html' } };
        const external = { method: 'POST', body: 'external' };
        await host.fetch('/members', read);
        await host.fetch('https://gateway.test/events', external);
        expect(original).toHaveBeenNthCalledWith(1, '/members', read);
        expect(original).toHaveBeenNthCalledWith(2, 'https://gateway.test/events', external);
        expect(host.dispatchEvent).not.toHaveBeenCalled();
    });

    test('validation failures retain the existing snapshot cache', async () => {
        original.mockResolvedValueOnce({ ok: false, status: 422 });
        await host.fetch('/notes/add', { method: 'POST' });
        expect(host.dispatchEvent).not.toHaveBeenCalled();
    });

    test('authorized UI reads do not evict snapshots just because their transport uses POST', async () => {
        await host.fetch('/live/subscriptions', { method: 'POST' });
        await host.fetch('/ui/refresh', { method: 'POST' });
        expect(host.dispatchEvent).not.toHaveBeenCalled();
        expect(original.mock.calls[0][1].headers).toBeUndefined();
    });
});
