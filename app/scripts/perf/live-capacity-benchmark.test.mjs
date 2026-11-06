import { test } from 'node:test';
import assert from 'node:assert/strict';
import { releaseGate, validateManifest, Session } from './live-capacity-benchmark.mjs';

const replicas = Object.fromEntries(['web', 'gateway'].map(kind => [kind, [1, 2].map(index => ({
    container: `${kind}-${index}`, image: `registry.example/${kind}@sha256:${'a'.repeat(64)}`,
}))]));
const manifest = () => ({ synthetic: true, commit: 'a'.repeat(40), replicas,
    gatewayEndpoints: ['http://127.0.0.1:19080/events', 'http://127.0.0.1:19081/events'],
    database: { container: 'load-db', user: 'load', tenants: ['tenant1', 'tenant2', 'tenant3', 'tenant4', 'tenant5'] },
    sessions: Array.from({ length: 500 }, (_, index) => ({ tenant: `tenant${index % 5}`,
        email: `synthetic-${index}@example.test`, detailPath: `/members/view/${index + 1}`, writer: index < 5,
        origins: [`http://tenant${index % 5}.localhost:18080`, `http://tenant${index % 5}.localhost:18081`],
    })),
});

test('manifest requires distinct accounts, five tenants and immutable replica pairs', () => {
    assert.equal(validateManifest(manifest()).sessions.length, 500);
    const duplicate = manifest(); duplicate.sessions[1] = { ...duplicate.sessions[0] };
    assert.throws(() => validateManifest(duplicate), /distinct/);
    const mutable = manifest(); mutable.replicas = { ...replicas, web: [{ container: 'web', image: 'latest' }] };
    assert.throws(() => validateManifest(mutable), /immutable/);
    const foreign = manifest(); foreign.sessions[0].detailPath = '//foreign.example/read';
    assert.throws(() => validateManifest(foreign), /Relative/);
    const production = manifest(); production.synthetic = false;
    assert.throws(() => validateManifest(production), /Synthetic/);
});

test('release evidence cannot pass with shortened duration, lower rates, missing telemetry or a different baseline', () => {
    const report = { commit: 'a'.repeat(40), fixtureDigest: 'fixture', replicas, durationSeconds: 1800,
        sessions: 500, tenants: 5, ordinaryRequests: 90000, mutations: 9000, errors: [], missingUpdates: 0,
        requestP95Ms: 1090, liveP95Ms: 1900, distinctSessions: 500, minimumConnected: 500,
        telemetrySamples: 360, observabilityComplete: true, runtimeVerified: true, environment: { testHost: true } };
    const baseline = { ...report, mode: 'baseline', requestP95Ms: 1000 };
    assert.equal(releaseGate(report, baseline).passed, true);
    for (const patch of [{ durationSeconds: 1799 }, { sessions: 499 }, { tenants: 4 }, { ordinaryRequests: 89999 },
        { mutations: 8999 }, { errors: ['unexpected'] }, { missingUpdates: 1 }, { requestP95Ms: 1101 },
        { liveP95Ms: 2001 }, { distinctSessions: 499 }, { minimumConnected: 499 }, { observabilityComplete: false }, { runtimeVerified: false }]) {
        assert.equal(releaseGate({ ...report, ...patch }, baseline).passed, false, JSON.stringify(patch));
    }
    assert.equal(releaseGate(report, { ...baseline, commit: 'b'.repeat(40) }).passed, false);
    assert.equal(releaseGate(report, { ...baseline, fixtureDigest: 'different fixture' }).passed, false);
    assert.equal(releaseGate(report, { ...baseline, environment: { differentHost: true } }).passed, false);
    assert.equal(releaseGate(report, null).passed, false);
});

test('every affected observer must read committed content; one refresh cannot hide missing fanout', async () => {
    const pending = { subject: 'Saved subject', started: performance.now(), reading: new Set(),
        remaining: new Set(['first@example.test', 'second@example.test']) };
    const metrics = { pending: new Map([['event', pending]]), live: [], request: [], errors: [] };
    const observers = ['first', 'second'].map(name => new Session({ email: `${name}@example.test`,
        origins: ['https://tenant.example.test'], detailPath: '/members/view/7' }, metrics));
    const reads = [];
    observers.forEach(observer => { observer.request = async () => {
        reads.push(observer.spec.email); return { status: 200, text: async () => 'Saved subject' };
    }; });
    await observers[0].refresh('event'); await observers[0].refresh('event');
    assert.equal(metrics.pending.size, 1);
    assert.deepEqual([...pending.remaining], ['second@example.test']);
    await observers[1].refresh('event');
    assert.equal(metrics.pending.size, 0);
    assert.equal(metrics.live.length, 2);
    assert.equal(metrics.request.length, 2);
    assert.deepEqual(reads, ['first@example.test', 'second@example.test']);
});

test('ordinary reads request the full document and mutations retain their stream negotiation', async t => {
    const calls = [];
    t.mock.method(globalThis, 'fetch', async (url, options) => {
        calls.push(options); return new Response('authorized content');
    });
    const session = new Session({ origins: ['https://tenant.example.test'] }, {});
    await session.request('/members/view/7');
    assert.equal(calls[0].headers.Accept, 'text/html,application/xhtml+xml');
    assert.equal(calls[0].headers['X-Requested-With'], undefined);
    await session.request('/notes/add', { method: 'POST', headers: { Accept: 'text/vnd.turbo-stream.html' } });
    assert.equal(calls[1].headers.Accept, 'text/vnd.turbo-stream.html');
});
