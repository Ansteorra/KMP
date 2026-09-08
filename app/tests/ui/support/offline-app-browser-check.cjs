/** Local seeded application acceptance. No database resets; creates and removes one synthetic gathering. */
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');

const tenantFixture = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if (!$tenant || $tenant->slug !== 'kmp' || $tenant->status !== 'active'
    || !in_array($tenant->dbServer, ['db', 'postgres', '127.0.0.1', 'localhost'], true)) {
    throw new \RuntimeException('This acceptance check requires the local seeded tenant.');
}
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
$result = $manager->withTenant($tenant, function () use ($input) {
    $locator = \Cake\ORM\TableRegistry::getTableLocator();
    $gatherings = $locator->get('Gatherings');
    if (isset($input['cleanup'])) {
        $gathering = $gatherings->find()->where(['id' => $input['cleanup'], 'name' => $input['name']])->firstOrFail();
        $attendance = $locator->get('GatheringAttendances');
        $attendance->deleteAll(['gathering_id' => $gathering->id]);
        $gatherings->deleteOrFail($gathering);
        return ['cleaned' => true];
    }
    $admin = $locator->get('Members')->find()->where(['email_address' => 'admin@amp.ansteorra.org'])->firstOrFail();
    $type = $locator->get('GatheringTypes')->find()->orderByAsc('id')->firstOrFail();
    $name = 'Synthetic offline security ' . \Cake\Utility\Text::uuid();
    $gathering = $gatherings->newEntity([
        'name' => $name, 'branch_id' => $admin->branch_id, 'gathering_type_id' => $type->id,
        'start_date' => \Cake\I18n\DateTime::now()->addDays(2),
        'end_date' => \Cake\I18n\DateTime::now()->addDays(3),
        'created_by' => $admin->id, 'location' => 'Synthetic local test location', 'timezone' => 'UTC',
    ]);
    $gatherings->saveOrFail($gathering);
    return ['id' => $gathering->id, 'name' => $name, 'email' => $admin->email_address];
});
echo json_encode($result, JSON_THROW_ON_ERROR);
`;

const stored = page => page.evaluate(() => new Promise((resolve, reject) => {
    const request = indexedDB.open('kmp-offline-vault');
    request.onerror = reject;
    request.onsuccess = () => {
        const db = request.result;
        const query = db.transaction('vault').objectStore('vault').get('current');
        query.onsuccess = () => { resolve(query.result || null); db.close(); };
        query.onerror = reject;
    };
}));
const waitIdle = page => page.waitForFunction(() => !document.querySelector('[data-controller=offline-vault]').hasAttribute('aria-busy'), null, { timeout: 60000 });

(async () => {
    const fixture = runPhpJson(tenantFixture);
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    try {
        const context = await browser.newContext({ baseURL: 'http://kmp.localhost:8080' });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await loginAs(page, fixture.email);
        await page.goto('/offline');
        await page.waitForSelector('[data-offline-vault-target=enroll]:not([hidden])');
        assert.equal(await page.locator('meta[name=kmp-offline-session], meta[name=csrfToken], #debug-kit-toolbar').count(), 0);
        await page.locator('#offline-new-passphrase').fill('synthetic four words woodland');
        await page.getByRole('button', { name: 'Use offline passphrase', exact: true }).click();
        await waitIdle(page);
        assert.match(await page.locator('[data-offline-vault-target=status]').textContent(), /Offline data saved/);
        assert.equal(await page.locator('[data-offline-vault-target=card] dl').count(), 1);
        const record = await stored(page);
        assert.equal(record.wrapper.method, 'passphrase');
        assert.equal(JSON.stringify(record).includes(fixture.name), false);
        const paths = await page.evaluate(async () => {
            const paths = [];
            for (const name of await caches.keys()) {
                const cache = await caches.open(name);
                for (const request of await cache.keys()) {
                    const path = new URL(request.url).pathname;
                    paths.push(path);
                    if (path === '/offline') {
                        const html = await (await cache.match(request)).text();
                        if (/csrfToken|kmp-offline-session|debug-kit-toolbar/.test(html)) throw new Error('Private shell markup cached');
                    }
                }
            }
            return paths;
        });
        assert.equal(paths.some(path => path.startsWith('/members/') || path.startsWith('/gathering-attendances/')), false);
        await context.setOffline(true);
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForSelector('[data-offline-vault-target=locked]:not([hidden])');
        assert.equal(await page.locator('[data-offline-vault-target=card]').textContent(), '');
        await page.locator('#offline-unlock-passphrase').fill('synthetic four words woodland');
        await page.getByRole('button', { name: 'Unlock offline data', exact: true }).click();
        await page.waitForSelector('[data-offline-vault-target=card] dl');
        await page.getByRole('button', { name: `Queue private RSVP for ${fixture.name}`, exact: true }).click();
        await waitIdle(page);
        assert.equal(await page.getByRole('button', { name: `Remove pending RSVP for ${fixture.name}`, exact: true }).count(), 1);
        const pending = await page.evaluate(() => window.RsvpCacheService.getPendingRsvps());
        assert.equal(pending.length, 1);
        assert.deepEqual(Object.keys(pending[0]).sort(), ['createdAt', 'gathering_id', 'id']);
        // Startup cleanup preserves the new encrypted, owner-bound queue across reloads.
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForSelector('[data-offline-vault-target=locked]:not([hidden])');
        await page.locator('#offline-unlock-passphrase').fill('synthetic four words woodland');
        await page.getByRole('button', { name: 'Unlock offline data', exact: true }).click();
        await page.waitForSelector('[data-offline-vault-target=card] dl');
        assert.equal((await page.evaluate(() => window.RsvpCacheService.getPendingRsvps()))[0].id, pending[0].id);
        await context.setOffline(false);
        await page.getByRole('button', { name: 'Sync pending RSVPs', exact: true }).click();
        await page.waitForFunction(async () => (await window.RsvpCacheService.getPendingRsvps()).length === 0);
        const result = await page.evaluate(async ({ id, requestId }) => {
            const actor = (await (await fetch('/offline/context')).json()).data;
            const send = async owner => {
                const response = await fetch('/gathering-attendances/mobile-rsvp', { method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': actor.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ gathering_id: id, offline_request_id: requestId, offline_owner: owner, offline_epoch: actor.epoch,
                        share_with_kingdom: true, public_note: 'SYNTHETIC INJECTED NOTE' }) });
                return { status: response.status, body: await response.json() };
            };
            const retry = await send(actor.owner);
            const mismatch = await send('other-synthetic-owner');
            const own = (await (await fetch('/gathering-attendances/my-rsvps', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })).json()).data.upcoming;
            const matching = own.filter(row => row.gathering.id === id);
            return { retry: retry.status, retrySuccess: retry.body.success, mismatch: mismatch.status, count: matching.length,
                private: matching.length === 1 && !matching[0].sharing.kingdom && !matching[0].sharing.hosting_group && !matching[0].sharing.crown && !matching[0].note };
        }, { id: fixture.id, requestId: pending[0].id });
        assert.deepEqual(result, { retry: 200, retrySuccess: true, mismatch: 403, count: 1, private: true });
        await page.setViewportSize({ width: 390, height: 844 });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        const otherTab = await context.newPage();
        await otherTab.goto('/offline');
        await otherTab.waitForSelector('[data-offline-vault-target=locked]:not([hidden])');
        await page.bringToFront();
        if (await page.locator('[data-offline-vault-target=locked]').isVisible()) {
            await page.locator('#offline-unlock-passphrase').fill('synthetic four words woodland');
            await page.getByRole('button', { name: 'Unlock offline data', exact: true }).click();
            await page.waitForSelector('[data-offline-vault-target=card] dl');
        }
        await otherTab.evaluate(() => window.Stimulus.getControllerForElementAndIdentifier(document.querySelector('[data-controller=offline-vault]'), 'offline-vault').lock());
        await page.waitForSelector('[data-offline-vault-target=locked]:not([hidden])');
        await otherTab.close();
        assert.equal(await page.locator('[data-offline-vault-target=card]').textContent(), '');
        await page.keyboard.press('Tab');
        assert.equal(await page.evaluate(() => document.activeElement?.tagName === 'BODY'), false);
        const cookies = await context.cookies();
        const foreign = await browser.newContext({ baseURL: 'http://kmp2.localhost:8080' });
        await foreign.addCookies(cookies.filter(cookie => cookie.domain === 'kmp.localhost').map(cookie => ({ ...cookie, domain: 'kmp2.localhost' })));
        const foreignPage = await foreign.newPage();
        const replay = await foreignPage.goto('/members/profile');
        assert.notEqual(replay.status(), 503);
        assert.match(new URL(foreignPage.url()).pathname, /members\/login/);
        await foreign.close();
        await page.goto('/members/logout');
        await page.waitForFunction(async () => new Promise(resolve => {
            const request = indexedDB.open('kmp-offline-vault');
            request.onsuccess = () => {
                const db = request.result;
                const query = db.transaction('vault').objectStore('vault').get('current');
                query.onsuccess = () => { resolve(!query.result); db.close(); };
            };
        }));
        assert.equal(await stored(page), null);
        assert.deepEqual(errors, []);
        console.log('PASS: signed-in card, public-only cache, offline reload/unlock, encrypted RSVP queue across reload, cross-tab lock, same-owner sync, duplicate retry, actor mismatch denial, private sharing, logout purge, real-cookie cross-tenant denial, mobile reflow.');
        await context.close();
    } finally {
        await browser.close();
        runPhpJson(tenantFixture, { cleanup: fixture.id, name: fixture.name });
    }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
