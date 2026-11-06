/** Cancel obsolete frame reads before page removal, including native navigation. */
export function installFrameRequestLifecycle(host = window) {
    const original = host.fetch;
    let page = new AbortController();
    const cancel = () => { page.abort(); page = new AbortController(); };
    const beforeRender = event => { if (event.detail?.renderMethod !== 'morph') cancel(); };
    const beforeUnload = event => queueMicrotask(() => { if (!event.defaultPrevented) cancel(); });
    document.addEventListener('turbo:before-render', beforeRender);
    host.addEventListener('pagehide', cancel);
    host.addEventListener('beforeunload', beforeUnload);
    host.fetch = async function (input, options = {}) {
        const request = typeof Request !== 'undefined' && input instanceof Request ? input : null;
        const headers = new Headers(options.headers || request?.headers);
        const upstream = options.signal || request?.signal;
        const isFrame = headers.has('Turbo-Frame') && (options.method || request?.method || 'GET').toUpperCase() === 'GET';
        const signal = isFrame ? AbortSignal.any([page.signal, ...(upstream ? [upstream] : [])]) : upstream;
        try {
            const response = await original.call(host, input, signal ? { ...options, signal } : options);
            if (isFrame && /(?:text\/html|application\/xhtml\+xml|text\/vnd\.turbo-stream\.html)/i.test(response.headers?.get('Content-Type') || '')) {
                // Turbo 8 starts its async frame response delegate without awaiting it.
                // Finish finite HTML reads here so cancellation after response headers
                // cannot become an unhandled body-read rejection in that delegate.
                const body = await response.arrayBuffer();
                if (signal.aborted) throw new DOMException('The request was canceled.', 'AbortError');
                const buffered = new Response(body, { status: response.status, statusText: response.statusText, headers: response.headers });
                Object.defineProperties(buffered, {
                    url: { value: response.url }, redirected: { value: response.redirected }, type: { value: response.type },
                });
                return buffered;
            }
            return response;
        } catch (error) {
            // Firefox can report a canceled fetch as NetworkError. Turbo recognizes
            // AbortError; genuine failures still propagate to its error handling.
            if (signal?.aborted) throw new DOMException('The request was canceled.', 'AbortError');
            throw error;
        }
    };
    return () => {
        cancel(); host.fetch = original;
        document.removeEventListener('turbo:before-render', beforeRender);
        host.removeEventListener('pagehide', cancel);
        host.removeEventListener('beforeunload', beforeUnload);
    };
}
