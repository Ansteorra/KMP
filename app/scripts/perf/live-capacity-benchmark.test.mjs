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
    const report = { commit: 'a'.repeat(40), fixtureDigest: 'fixture', replicas, durationSeconds: 1800, elapsedSeconds: 1810,
        sessions: 500, tenants: 5, ordinaryRequests: 90000, mutations: 9000, errors: [], missingUpdates: 0,
        requestP95Ms: 1090, liveP95Ms: 1900, distinctSessions: 500, minimumConnected: 500,
        initialReconciliations: 500, screenReconciliations: 500,
        telemetrySamples: 360, observabilityComplete: true, runtimeVerified: true, environment: { testHost: true } };
    const baseline = { ...report, mode: 'baseline', requestP95Ms: 1000 };
    assert.equal(releaseGate(report, baseline).passed, true);
    for (const patch of [{ durationSeconds: 1799 }, { sessions: 499 }, { tenants: 4 }, { ordinaryRequests: 89999 },
        { mutations: 8999 }, { elapsedSeconds: undefined }, { elapsedSeconds: 1799 }, { elapsedSeconds: 1816 },
        { errors: ['unexpected'] }, { missingUpdates: 1 }, { requestP95Ms: 1101 },
        { initialReconciliations: 499 }, { screenReconciliations: 0 },
        { liveP95Ms: 2001 }, { distinctSessions: 499 }, { minimumConnected: 499 }, { observabilityComplete: false }, { runtimeVerified: false }]) {
        assert.equal(releaseGate({ ...report, ...patch }, baseline).passed, false, JSON.stringify(patch));
    }
    assert.equal(releaseGate(report, { ...baseline, commit: 'b'.repeat(40) }).passed, false);
    assert.equal(releaseGate(report, { ...baseline, fixtureDigest: 'different fixture' }).passed, false);
    assert.equal(releaseGate(report, { ...baseline, environment: { differentHost: true } }).passed, false);
    assert.equal(releaseGate(report, { ...baseline, elapsedSeconds: 1816 }).passed, false);
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

test('initial and renewed replay gaps perform the same authorized screen read as real clients', async () => {
    const metrics = { run: 'measured', errors: [], request: [] };
    const session = new Session({ origins: ['https://tenant.example.test'], detailPath: '/members/view/7?tab=notes' }, metrics);
    const calls = [];
    let finish;
    session.request = async (path, options) => {
        calls.push({ path, options });
        await new Promise(resolve => { finish = resolve; });
        return new Response('<main id="main-content">Authorized current details</main>');
    };
    const first = session.reconcileScreen();
    assert.equal(session.reconcileScreen(), first, 'concurrent gap reads coalesce');
    assert.equal(calls.length, 1);
    finish(); await first;
    assert.equal(session.initialReconciled, true);
    assert.equal(metrics.initialReconciliations, 1);
    assert.equal(metrics.screenReconciliations, 1);
    const renewed = session.reconcileScreen(); finish(); await renewed;
    assert.equal(metrics.initialReconciliations, 1);
    assert.equal(metrics.screenReconciliations, 2);
    assert.equal(metrics.request.length, 2);
    assert.deepEqual(metrics.errors, []);
    assert.equal(calls[0].path, '/members/view/7?tab=notes');
    assert.equal(calls[0].options.cache, 'no-store');
    assert.equal(calls[0].options.headers['X-KMP-UI-Refresh'], 'screen');
    assert.equal(calls[0].options.headers['X-Requested-With'], 'XMLHttpRequest');
});

test('failed gap reads cannot mark an initial screen reconciled', async () => {
    const metrics = { errors: [], request: [] };
    const session = new Session({ origins: ['https://tenant.example.test'], detailPath: '/members/view/7' }, metrics);
    session.request = async () => new Response('Forbidden', { status: 403 });
    await session.reconcileScreen();
    assert.equal(session.initialReconciled, false);
    assert.equal(metrics.initialReconciliations, undefined);
    assert.deepEqual(metrics.errors, ['Authorized screen reconciliation unavailable.']);
    assert.equal(metrics.request.length, 0, 'startup reads are excluded from measured request timing');
});

test('HTTP login submits the password form rather than offline setup or quick unlock forms', async () => {
    const session = new Session({ email: 'synthetic@example.invalid', detailPath: '/members/view/7',
        origins: ['https://tenant.example.test:8443', 'https://tenant.example.test:8444'] }, { baseline: false });
    const login = `<form><input name="offline_pin" value="not-a-server-login"></form>
        <form action="/members/login" data-login-device-auth-target="quickForm"><input name="quick_login"></form>
        <form action="/members/login" data-login-device-auth-target="passwordForm">
            <input name="_csrfToken" value="fixture-token"><input name="email_address"><input name="password">
        </form>`;
    const detail = '<meta name="kmp-turbo-enabled" content="true"><meta name="kmp-live-subscriptions-url" content="/live/subscriptions">';
    const posts = [];
    session.request = async (path, options = {}) => {
        if (options.method === 'POST') { posts.push({ path, fields: options.body }); return new Response(null, { status: 303 }); }
        return new Response(path === '/members/login' ? login : detail);
    };
    await session.login('synthetic-password');
    assert.equal(posts.length, 1);
    assert.equal(posts[0].path, '/members/login');
    assert.equal(posts[0].fields.get('email_address'), 'synthetic@example.invalid');
    assert.equal(posts[0].fields.get('password'), 'synthetic-password');
    assert.equal(posts[0].fields.get('_csrfToken'), 'fixture-token');
    assert.equal(posts[0].fields.has('offline_pin'), false);
    assert.equal(posts[0].fields.has('quick_login'), false);
});
