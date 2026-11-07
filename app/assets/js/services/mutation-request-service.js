/** Correlate a mutation response and its committed invalidation without logging record data. */
export function installMutationRequestMetadata(host = window) {
    const original = host.fetch;
    host.fetch = function (input, options = {}) {
        const request = typeof Request !== 'undefined' && input instanceof Request ? input : null;
        const method = (options.method || request?.method || 'GET').toUpperCase();
        const url = new URL(request?.url || input, host.location.href);
        const uiRead = ['/live/subscriptions', '/ui/refresh'].includes(url.pathname);
        if (!uiRead && !['GET', 'HEAD', 'OPTIONS'].includes(method) && url.origin === host.location.origin) {
            const headers = new Headers(options.headers || request?.headers);
            headers.set('X-KMP-Mutation-ID', crypto.randomUUID());
            return original.call(host, input, { ...options, headers }).then(response => {
                if (response.ok) host.dispatchEvent?.(new Event('kmp:mutation-committed'));
                return response;
            });
        }
        return original.call(host, input, options);
    };
    return () => { host.fetch = original; };
}
