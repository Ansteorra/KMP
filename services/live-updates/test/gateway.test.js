import { test, before } from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPair, SignJWT } from 'jose';
import { createGateway } from '../src/gateway.js';
import { compareCursors } from '../src/cursor.js';

let keys;
before(async () => { keys = await generateKeyPair('RS256'); });
const tenant = '11111111-1111-4111-8111-111111111111';
const otherTenant = '22222222-2222-4222-8222-222222222222';
const origin = 'https://tenant.example.test';
async function setup(t, overrides = {}) {
    let receive;
    const broker = { ready: () => true, replay: async () => [], subscribe: callback => { receive = callback; } };
    const server = createGateway({ broker, publicKey: keys.publicKey, allowedOrigins: new Set([origin]), heartbeatMs: 60000 });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    t.after(() => server.shutdown());
    const token = await new SignJWT({ tenant, origin, topics: ['Gatherings:7'], ...overrides })
        .setProtectedHeader({ alg: 'RS256' }).setIssuer('kmp-ui').setAudience('kmp-live')
        .setIssuedAt().setExpirationTime('120s').sign(keys.privateKey);
    const url = `http://127.0.0.1:${server.address().port}`;
    const connect = (headers = {}) => fetch(`${url}/events`, { headers: { Origin: origin, Authorization: `Bearer ${token}`, ...headers } });
    return { connect, url, broker, publish: event => receive(event) };
}

test('requires exact Origin and authorization; never accepts query-string credentials', async t => {
    const { connect, url } = await setup(t);
    assert.equal((await connect({ Origin: 'https://attacker.example' })).status, 403);
    assert.equal((await connect({ Authorization: '' })).status, 401);
    assert.equal((await fetch(`${url}/events?token=secret`, { headers: { Origin: origin } })).status, 401);
});

test('fans out only subscribed tenant/resource changes, without shared record HTML', async t => {
    const { connect, publish } = await setup(t);
    const response = await connect({ 'Last-Event-ID': '1-0' });
    assert.equal(response.status, 200);
    const reader = response.body.getReader();
    publish({ tenant: otherTenant, cursor: '2-0', topics: { 'Gatherings:7': ['secret-other-tenant'] } });
    publish({ tenant, cursor: '3-0', topics: { 'Gatherings:8': ['secret-other-record'] } });
    publish({ tenant, cursor: '4-0', topics: { 'Gatherings:7': ['schedule'], 'Gatherings:8': ['private'] } });
    const data = new TextDecoder().decode((await reader.read()).value);
    assert.match(data, /id: 4-0/);
    assert.match(data, /kmp-invalidate/);
    assert.match(data, /schedule/);
    assert.doesNotMatch(data, /private|secret|Gatherings:8/);
    await reader.cancel();
});

test('replays missed events and reconciles when history is unavailable', async t => {
    const { connect, broker } = await setup(t);
    broker.replay = async () => [{ tenant, cursor: '7-0', topics: { 'Gatherings:7': ['attendance'] } }];
    const replay = await connect({ 'Last-Event-ID': '1-0' });
    const reader = replay.body.getReader();
    assert.match(new TextDecoder().decode((await reader.read()).value), /attendance/);
    await reader.cancel();
    broker.replay = async () => null;
    const gap = await connect({ 'Last-Event-ID': '1-0' });
    const gapReader = gap.body.getReader();
    assert.match(new TextDecoder().decode((await gapReader.read()).value), /action="kmp-invalidate"/);
    await gapReader.cancel();
});

test('rejects invalid claims and fails readiness during a Redis outage', async t => {
    const { connect, broker, url } = await setup(t, { topics: ['*'] });
    assert.equal((await connect()).status, 401);
    broker.ready = () => false;
    assert.equal((await fetch(`${url}/health`)).status, 503);
    assert.equal((await connect()).status, 503);
});

test('compares complete Redis IDs without number precision loss', () => {
    assert.equal(compareCursors('123-9', '123-10'), -1);
    assert.equal(compareCursors('9007199254740993-0', '9007199254740992-999'), 1);
    assert.equal(compareCursors('123-10', '123-10'), 0);
});

test('replay and buffered delivery discard duplicates and older events', async t => {
    const { connect, broker, publish } = await setup(t);
    let resume;
    broker.replay = () => new Promise(resolve => { resume = resolve; });
    const response = await connect({ 'Last-Event-ID': '7-0' });
    publish({ tenant, cursor: '9-0', topics: { 'Gatherings:7': ['schedule'] } });
    publish({ tenant, cursor: '8-0', topics: { 'Gatherings:7': ['older-buffered'] } });
    resume([
        { tenant, cursor: '7-0', topics: { 'Gatherings:7': ['duplicate'] } },
        { tenant, cursor: '9-0', topics: { 'Gatherings:7': ['schedule'] } },
    ]);
    const reader = response.body.getReader();
    const data = new TextDecoder().decode((await reader.read()).value);
    assert.equal((data.match(/id: 9-0/g) || []).length, 1);
    assert.doesNotMatch(data, /duplicate|older-buffered/);
    await reader.cancel();
});
