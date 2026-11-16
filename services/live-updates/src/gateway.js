import { createServer } from 'node:http';
import { jwtVerify } from 'jose';
import { compareCursors } from './cursor.js';

const escape = value => value.replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);

/** Connections carry invalidations only. CakePHP authorizes and renders every read. */
export function createGateway({ broker, publicKey, allowedOrigins, heartbeatMs = 15000 }) {
    const clients = new Set();
    const metrics = { delivered: 0, rejected: 0, connections: 0 };
    const disconnectAll = () => { for (const client of clients) client.response.end(); };
    const unsubscribe = broker.subscribe(envelope => {
        for (const client of clients) {
            if (envelope.tenant !== client.claims.tenant) continue;
            if (client.buffer) {
                if (client.buffer.length >= 1000) client.response.end();
                else client.buffer.push(envelope);
            } else deliver(client, envelope);
        }
    }, disconnectAll);

    function deliver(client, envelope) {
        if (client.response.writableEnded || (client.cursor && compareCursors(envelope.cursor, client.cursor) <= 0)) return;
        const changes = [...new Set(client.claims.topics.flatMap(topic => envelope.topics[topic] || []))];
        if (!changes.length) return;
        const request = envelope.requestId ? ` request-id="${escape(envelope.requestId)}"` : '';
        const stream = `<turbo-stream action="kmp-invalidate"${request} changes="${escape(JSON.stringify(changes))}"></turbo-stream>`;
        if (!client.response.write(`id: ${envelope.cursor}\ndata: ${stream}\n\n`)) client.response.end();
        client.cursor = envelope.cursor;
        metrics.delivered++;
    }

    const server = createServer(async (request, response) => {
        response.setHeader('Cache-Control', 'no-store');
        const path = new URL(request.url, 'http://gateway').pathname;
        if (path === '/livez') { response.writeHead(200); response.end('ok'); return; }
        if (path === '/health') {
            response.writeHead(broker.ready() ? 200 : 503, { 'Content-Type': 'application/json' });
            response.end(JSON.stringify({ ready: broker.ready(), ...metrics }));
            return;
        }
        const origin = request.headers.origin;
        if (path !== '/events' || !allowedOrigins.has(origin)) {
            metrics.rejected++; response.writeHead(403); response.end(); return;
        }
        response.setHeader('Access-Control-Allow-Origin', origin);
        response.setHeader('Vary', 'Origin');
        if (request.method === 'OPTIONS') {
            response.writeHead(204, { 'Access-Control-Allow-Methods': 'GET',
                'Access-Control-Allow-Headers': 'Authorization, Last-Event-ID' });
            response.end(); return;
        }
        if (request.method !== 'GET' || !broker.ready()) {
            response.writeHead(503); response.end(); return;
        }
        try {
            const authorization = request.headers.authorization || '';
            if (!authorization.startsWith('Bearer ') || authorization.length > 16384) throw new Error('Missing token');
            const { payload: claims } = await jwtVerify(authorization.slice(7), publicKey, {
                issuer: 'kmp-ui', audience: 'kmp-live', algorithms: ['RS256'], requiredClaims: ['exp', 'iat', 'tenant', 'topics', 'origin'],
            });
            if (claims.origin !== origin || typeof claims.tenant !== 'string' || !/^[a-f0-9-]{36}$/i.test(claims.tenant)
                || !Number.isInteger(claims.exp) || !Number.isInteger(claims.iat) || claims.exp <= claims.iat
                || claims.exp - claims.iat > 120 || claims.iat > Date.now() / 1000 + 5
                || !Array.isArray(claims.topics) || !claims.topics.length || claims.topics.length > 64
                || claims.topics.some(topic => typeof topic !== 'string' || !/^[A-Za-z.]+:(list|[A-Za-z0-9_-]+)$/.test(topic))) {
                throw new Error('Invalid subscription');
            }
            const cursor = request.headers['last-event-id'];
            if (cursor && (cursor.length > 48 || !/^\d+-\d+$/.test(cursor))) throw new Error('Invalid cursor');
            response.writeHead(200, { 'Content-Type': 'text/event-stream', 'Cache-Control': 'no-cache, no-transform', 'X-Accel-Buffering': 'no' });
            response.flushHeaders();
            const client = { response, claims, cursor, buffer: [] };
            clients.add(client); metrics.connections = clients.size;
            const heartbeat = setInterval(() => {
                if (!response.write(': heartbeat\n\n')) response.end();
            }, heartbeatMs);
            const expires = setTimeout(() => response.end(), Math.max(1, claims.exp * 1000 - Date.now()));
            response.on('close', () => {
                clearInterval(heartbeat); clearTimeout(expires); clients.delete(client); metrics.connections = clients.size;
            });
            const replay = cursor ? await broker.replay(claims.tenant, cursor) : null;
            // A reconnect without available history always requests an authorized reconciliation.
            if (!replay) response.write('data: <turbo-stream action="kmp-invalidate"></turbo-stream>\n\n');
            else for (const envelope of replay) deliver(client, envelope);
            const buffered = client.buffer; client.buffer = null;
            for (const envelope of buffered) deliver(client, envelope);
        } catch {
            metrics.rejected++;
            if (!response.headersSent) response.writeHead(401);
            response.end();
        }
    });
    server.shutdown = async () => {
        disconnectAll(); unsubscribe?.();
        await new Promise(resolve => server.close(resolve));
    };
    return server;
}
