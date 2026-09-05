/** Standalone synthetic browser checks. No app database, login or customer data is used. */
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const source = await readFile(fileURLToPath(new URL('../../../assets/js/services/offline-vault-service.js', import.meta.url)), 'utf8');
const server = createServer((request, response) => {
    response.setHeader('Content-Type', request.url === '/vault.js' ? 'text/javascript' : 'text/html');
    response.end(request.url === '/vault.js' ? source : '<!doctype html><html lang="en"><title>Offline vault synthetic test</title><script type="module">import vault, {purgeLegacyOfflineStorage} from "/vault.js"; window.vault = vault; window.purgeLegacy = purgeLegacyOfflineStorage;</script><body><h1>Synthetic offline vault test</h1></body></html>');
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://localhost:${server.address().port}`;
const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
try {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(origin); await page.waitForFunction(() => !!window.vault);
    const cdp = await context.newCDPSession(page);
    await cdp.send('WebAuthn.enable');
    const { authenticatorId } = await cdp.send('WebAuthn.addVirtualAuthenticator', { options: {
        protocol: 'ctap2', ctap2Version: 'ctap2_1', transport: 'internal', hasResidentKey: true,
        hasUserVerification: true, isUserVerified: true, automaticPresenceSimulation: true, hasPrf: true
    } });
    await page.evaluate(async () => {
        const now = Date.now();
        await vault.enroll({ owner: 'synthetic-member-a', epoch: 'synthetic-epoch', serverTime: now, expiresAt: now + 604800000 }, 'device');
        await vault.mutate(data => { data.card = { name: 'SYNTHETIC-PRIVATE-MARKER' }; });
    });
    assert.equal(await page.evaluate(async () => JSON.stringify(await vault.stored()).includes('SYNTHETIC-PRIVATE-MARKER')), false);
    await page.reload(); await page.waitForFunction(() => !!window.vault);
    assert.equal(await page.evaluate(() => vault.key), null);
    await context.setOffline(true);
    await page.evaluate(() => vault.unlock());
    assert.equal(await page.evaluate(async () => (await vault.read()).card.name), 'SYNTHETIC-PRIVATE-MARKER');
    await context.setOffline(false);
    // Account change clears persisted records, not just the unlock overlay.
    await page.evaluate(async () => {
        try { await vault.verifyContext({ owner: 'synthetic-member-b', epoch: 'other' }); } catch { /* Expected rejection. */ }
    });
    assert.equal(await page.evaluate(() => vault.stored()), null);
    await page.evaluate(async () => {
        const now = Date.now();
        await vault.enroll({ owner: 'synthetic-member-a', epoch: 'synthetic-epoch', serverTime: now, expiresAt: now + 604800000 }, 'passphrase', 'synthetic four words woodland');
        await vault.mutate(data => { data.card = { name: 'SYNTHETIC-PASSPHRASE-MARKER' }; });
        vault.lock();
        await vault.unlock('synthetic four words woodland');
        await vault.clear();
        for (const name of ['kmp-rsvp-cache', 'kmp-offline-queue']) await new Promise((resolve, reject) => {
            const request = indexedDB.open(name, 1);
            request.onupgradeneeded = () => request.result.createObjectStore('legacy');
            request.onsuccess = () => {
                const db = request.result;
                const transaction = db.transaction('legacy', 'readwrite');
                transaction.objectStore('legacy').put('SYNTHETIC-LEGACY-PII', 'one');
                transaction.oncomplete = () => { db.close(); resolve(); };
            };
            request.onerror = reject;
        });
        await purgeLegacy();
    });
    for (const name of ['kmp-rsvp-cache', 'kmp-offline-queue']) {
        const result = await page.evaluate(name => new Promise(resolve => {
            const request = indexedDB.open(name, 2); request.onsuccess = () => {
                resolve([...request.result.objectStoreNames]); request.result.close();
            };
        }), name);
        assert.deepEqual(result, []);
    }
    await cdp.send('WebAuthn.removeVirtualAuthenticator', { authenticatorId });
    console.log('PASS: real IndexedDB ciphertext, PRF wrap/unwrap after reload with browser offline, account purge, passphrase fallback, plaintext database tombstones.');
    console.log('Physical device PIN/biometrics and provider offline operation still require manual device acceptance.');
    await context.close();
} finally {
    await browser.close();
    await new Promise(resolve => server.close(resolve));
}
