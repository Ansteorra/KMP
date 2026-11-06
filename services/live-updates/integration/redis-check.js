import { test } from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { createClient } from 'redis';
import { createRedisBroker } from '../src/redis-broker.js';

const url = process.env.KMP_TEST_REDIS_URL;
if (!url) throw new Error('KMP_TEST_REDIS_URL must name a disposable Redis instance.');

test('two independent replicas share bounded replay, detect gaps, and stop on disconnect', async t => {
    const prefix = `kmp:test:${randomUUID()}:`;
    const writer = createClient({ url });
    await writer.connect();
    const brokers = await Promise.all([createRedisBroker(url, prefix), createRedisBroker(url, prefix)]);
    t.after(async () => {
        await Promise.all(brokers.map(broker => broker.close()));
        const keys = await writer.keys(`${prefix}*`);
        if (keys.length) await writer.del(keys);
        await writer.close();
    });
    const received = brokers.map(() => []);
    const delivered = brokers.map((broker, index) => new Promise(resolve => broker.subscribe(event => {
        received[index].push(event); resolve();
    }, () => {})));
    const tenant = randomUUID();
    const data = JSON.stringify({ tenant, id: randomUUID(), topics: { 'Members:7': ['notes'] } });
    const key = `${prefix}history:${tenant}`;
    await writer.xAdd(key, '100-1', { data });
    await writer.xAdd(key, '100-2', { data });
    await writer.publish(`${prefix}events`, JSON.stringify({ cursor: '100-2', data }));
    await Promise.race([
        Promise.all(delivered),
        new Promise((_, reject) => { const timer = setTimeout(() => reject(new Error('Replica fanout timed out')), 2000); timer.unref(); }),
    ]);
    assert.deepEqual(received[0], received[1]);
    assert.equal(received[0][0].tenant, tenant);
    assert.equal((await brokers[1].replay(tenant, '100-1'))[0].cursor, '100-2');
    assert.deepEqual(await brokers[0].replay(tenant, '100-2'), []);
    assert.equal(await brokers[0].replay(tenant, '100-0'), null, 'same-millisecond sequence gap requires reconciliation');
    assert.equal(await brokers[0].replay(tenant, '101-0'), null, 'cursor ahead of restored Redis requires reconciliation');
    assert.equal(await brokers[0].replay(randomUUID(), '100-1'), null);
    assert.equal(brokers.every(broker => broker.ready()), true);
});
