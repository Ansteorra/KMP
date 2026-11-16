import { createParser } from 'eventsource-parser';

const newer = (left, right) => {
    if (!/^\d+-\d+$/.test(left || '')) return false;
    if (!right) return true;
    const a = left.split('-').map(BigInt), b = right.split('-').map(BigInt);
    return a[0] > b[0] || (a[0] === b[0] && a[1] > b[1]);
};

/** Shared browser/capacity transport: renew before expiry and retain the old stream until its replacement is ready. */
export function maintainLiveConnection({ authorize, signal, onEvent, onStatus = () => {},
    onConnected = () => {}, onDisconnected = () => {}, onError = () => {}, random = Math.random }) {
    const own = new AbortController();
    const lifetime = signal ? AbortSignal.any([signal, own.signal]) : own.signal;
    const readers = new Set();
    const requests = new Set();
    let active, opening, retry, renewal, cursor;
    let connected = false;
    let finish;
    const done = new Promise(resolve => { finish = resolve; });

    function retryLater() {
        if (lifetime.aborted) return;
        clearTimeout(retry);
        retry = setTimeout(connect, 3000);
    }

    async function connect() {
        if (opening || lifetime.aborted) return;
        const attempt = (async () => {
            let controller;
            try {
                const ticket = await authorize(AbortSignal.any([lifetime, AbortSignal.timeout(10000)]));
                if (!ticket || lifetime.aborted) { if (!ticket) own.abort(); return; }
                const remaining = Number(ticket.expiresIn ?? (ticket.expiresAt - Date.now() / 1000)) * 1000;
                if (!Number.isFinite(remaining) || remaining <= 1000 || remaining > 120000) {
                    throw new Error('Invalid live subscription lifetime.');
                }
                controller = new AbortController(); requests.add(controller);
                const timeout = setTimeout(() => controller.abort(), 10000);
                let response;
                try {
                    response = await fetch(ticket.url, {
                        credentials: 'omit', cache: 'no-store', signal: AbortSignal.any([lifetime, controller.signal]),
                        headers: { ...ticket.headers, Authorization: `Bearer ${ticket.token}`, Accept: 'text/event-stream',
                            ...(cursor ? { 'Last-Event-ID': cursor } : {}) },
                    });
                } finally { clearTimeout(timeout); }
                if (!response.ok || !response.body) throw new Error('Live stream unavailable.');
                if (lifetime.aborted) { controller.abort(); return; }
                const previous = active; active = controller; previous?.abort();
                clearTimeout(retry); clearTimeout(renewal);
                if (!connected) { connected = true; onConnected(); }
                onStatus('connected');
                renewal = setTimeout(connect, Math.max(1000, remaining * (.75 + random() * .15)));
                const parser = createParser({
                    onEvent: event => {
                        if (lifetime.aborted || controller.signal.aborted) return;
                        if (event.id && !newer(event.id, cursor)) return;
                        if (!event.id) cursor = undefined; // A replay gap also resets an obsolete/ahead cursor.
                        onEvent(event);
                        if (event.id) cursor = event.id;
                    },
                });
                const reader = (async () => {
                    const decoder = new TextDecoder();
                    try {
                        for await (const chunk of response.body) parser.feed(decoder.decode(chunk, { stream: true }));
                    } catch (error) {
                        if (!lifetime.aborted && !controller.signal.aborted) onError(error);
                    } finally {
                        requests.delete(controller);
                        if (active === controller && !lifetime.aborted) {
                            active = null; connected = false; onDisconnected();
                            clearTimeout(renewal); onStatus('reconnecting'); retryLater();
                        }
                    }
                })();
                readers.add(reader); reader.finally(() => readers.delete(reader));
            } catch (error) {
                controller?.abort(); requests.delete(controller);
                if (!lifetime.aborted) { onError(error); onStatus('reconnecting'); retryLater(); }
            }
        })();
        opening = attempt;
        try { await attempt; } finally { if (opening === attempt) opening = null; }
    }

    lifetime.addEventListener('abort', async () => {
        clearTimeout(retry); clearTimeout(renewal);
        for (const request of requests) request.abort();
        if (connected) { connected = false; onDisconnected(); }
        active = null;
        await Promise.allSettled([...readers, opening].filter(Boolean));
        finish();
    }, { once: true });
    if (lifetime.aborted) finish(); else connect();
    return { close: () => own.abort(), done };
}
