import { installFrameRequestLifecycle } from '../../../assets/js/services/frame-request-lifecycle-service.js';

describe('obsolete frame reads', () => {
    let original, dispose;
    beforeEach(() => { original = jest.fn(); window.fetch = original; dispose = installFrameRequestLifecycle(); });
    afterEach(() => dispose());

    test('page removal cancels old reads and normalizes Firefox cancellation without masking real failures', async () => {
        original.mockImplementation((input, options) => new Promise((resolve, reject) => {
            options.signal.addEventListener('abort', () => reject(new TypeError('NetworkError when attempting to fetch resource.')));
        }));
        const read = window.fetch('/members/grid-data', { headers: { 'Turbo-Frame': 'members-table' } });
        const canceled = expect(read).rejects.toMatchObject({ name: 'AbortError' });
        document.dispatchEvent(new CustomEvent('turbo:before-render', { detail: { renderMethod: 'replace' } }));
        await canceled;
        const failure = new TypeError('Actual network outage'); original.mockRejectedValue(failure);
        await expect(window.fetch('/members/grid-data', { headers: { 'Turbo-Frame': 'members-table' } })).rejects.toBe(failure);
    });

    test('morphing retains active reads and caller cancellation still propagates', async () => {
        original.mockResolvedValue({ ok: true });
        await window.fetch('/members/grid-data', { headers: { 'Turbo-Frame': 'members-table' } });
        const signal = original.mock.calls[0][1].signal;
        document.dispatchEvent(new CustomEvent('turbo:before-render', { detail: { renderMethod: 'morph' } }));
        expect(signal.aborted).toBe(false);
        window.dispatchEvent(new Event('pagehide'));
        expect(signal.aborted).toBe(true);
        await window.fetch('/other-document');
        expect(original.mock.calls[1][1]).not.toHaveProperty('signal');
    });

    test('cancellation after response headers stays inside the fetch lifecycle', async () => {
        let rejectBody;
        original.mockImplementation((input, options) => Promise.resolve({
            headers: new Headers({ 'Content-Type': 'text/html' }),
            arrayBuffer: () => new Promise((resolve, reject) => {
                rejectBody = reject;
                options.signal.addEventListener('abort', () => reject(new TypeError('Canceled body read')));
            }),
        }));
        const read = window.fetch('/members/grid-data', { headers: { 'Turbo-Frame': 'members-table' } });
        const canceled = expect(read).rejects.toMatchObject({ name: 'AbortError' });
        await Promise.resolve();
        expect(rejectBody).toBeDefined();
        window.dispatchEvent(new Event('pagehide'));
        await canceled;
    });
});
