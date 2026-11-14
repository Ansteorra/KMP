import { createClient } from 'redis';
import { compareCursors } from './cursor.js';

export async function createRedisBroker(url, prefix = 'kmp:live:') {
    const reader = createClient({ url });
    const subscriber = reader.duplicate();
    let receive = () => {};
    let disconnected = () => {};
    for (const client of [reader, subscriber]) {
        client.on('error', () => { disconnected(); });
        client.on('reconnecting', () => { disconnected(); });
    }
    await reader.connect(); await subscriber.connect();
    await subscriber.subscribe(`${prefix}events`, value => {
        try {
            const message = JSON.parse(value);
            const data = JSON.parse(message.data);
            receive({ ...data, cursor: message.cursor });
        } catch { disconnected(); }
    });
    return {
        ready: () => reader.isReady && subscriber.isReady,
        subscribe(callback, onDisconnect) {
            receive = callback; disconnected = onDisconnect;
            return () => { receive = () => {}; disconnected = () => {}; };
        },
        async replay(tenant, cursor) {
            const key = `${prefix}history:${tenant}`;
            const first = await reader.xRange(key, '-', '+', { COUNT: 1 });
            if (!first.length || compareCursors(first[0].id, cursor) > 0) return null;
            const last = await reader.xRevRange(key, '+', '-', { COUNT: 1 });
            if (!last.length || compareCursors(last[0].id, cursor) < 0) return null;
            const events = await reader.xRange(key, `(${cursor}`, '+', { COUNT: 10000 });
            return events.map(event => ({ ...JSON.parse(event.message.data), cursor: event.id }));
        },
        async close() { await Promise.all([reader.close(), subscriber.close()]); },
    };
}
