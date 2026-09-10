/** Local seeded application acceptance. No database resets; creates and removes a synthetic member and gathering. */
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const { expect } = require('@playwright/test');
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
        $members = $locator->get('Members');
        $member = $members->find()->where(['id' => $input['memberId'], 'email_address' => $input['email']])->firstOrFail();
        $members->deleteOrFail($member);
        return ['cleaned' => true];
    }
    $admin = $locator->get('Members')->find()->where(['email_address' => 'admin@amp.ansteorra.org'])->firstOrFail();
    $password = 'Temporary!' . bin2hex(random_bytes(24));
    $member = $locator->get('Members')->newEntity([
        'first_name' => 'Synthetic', 'last_name' => 'Offline', 'sca_name' => 'Synthetic Offline Member',
        'email_address' => 'offline-' . bin2hex(random_bytes(8)) . '@example.test',
        'password' => $password, 'status' => $admin->status, 'branch_id' => $admin->branch_id,
        'membership_number' => 'SYNTHETIC', 'membership_expires_on' => '2099-01-01',
        'birth_year' => 1990, 'birth_month' => 1, 'created_by' => $admin->id,
        'street_address' => '1 Synthetic Street', 'city' => 'Test', 'state' => 'TX',
        'zip' => '75001', 'phone_number' => '5555550100',
    ]);
    $locator->get('Members')->saveOrFail($member);
    $type = $locator->get('GatheringTypes')->find()->orderByAsc('id')->firstOrFail();
    $name = 'Synthetic offline security ' . \Cake\Utility\Text::uuid();
    $gathering = $gatherings->newEntity([
        'name' => $name, 'branch_id' => $admin->branch_id, 'gathering_type_id' => $type->id,
        'start_date' => \Cake\I18n\DateTime::now()->addDays(2),
        'end_date' => \Cake\I18n\DateTime::now()->addDays(3),
        'created_by' => $admin->id, 'location' => 'Synthetic local test location', 'timezone' => 'UTC',
    ]);
    $gatherings->saveOrFail($gathering);
    return ['id' => $gathering->id, 'name' => $name, 'email' => $member->email_address, 'memberId' => $member->id, 'password' => $password,
        'shortSiteTitle' => \App\KMP\StaticHelpers::getAppSetting('KMP.ShortSiteTitle')];
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
const waitIdle = page => page.waitForFunction(() => !document.querySelector('[data-controller~=offline-vault]').hasAttribute('aria-busy'), null, { timeout: 60000 });
const choosePin = async scope => {
    await expect(scope.locator('[data-offline-access-target=stepLabel]')).toHaveText('Step 2 of 3');
    const method = scope.getByLabel('Unlock this device with', { exact: true });
    if (await method.isVisible()) await method.selectOption('pin');
    await expect(scope.getByLabel('Choose a PIN (6–12 digits)', { exact: true })).toBeVisible();
};

(async () => {
    const fixture = runPhpJson(tenantFixture);
    const siteTitle = fixture.shortSiteTitle;
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    try {
        const context = await browser.newContext({ baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080', viewport: { width: 390, height: 844 } });
        let disconnected = false;
        // Also block worker-owned requests so worker restarts cannot escape offline emulation.
        await context.route('**/*', route => disconnected && route.request().serviceWorker()
            ? route.abort('internetdisconnected') : route.continue());
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await loginAs(page, fixture.email, fixture.password);
        // Ordinary signed-in pages offer a single deliberate trust choice.
        await page.goto('/members/view-mobile-card');
        await page.getByRole('button', { name: 'Trust this personal device', exact: true }).click();
        await page.getByLabel(`Current ${siteTitle} password`, { exact: true }).fill(fixture.password);
        await page.getByRole('button', { name: 'Continue', exact: true }).click();
        await choosePin(page);
        await page.getByLabel('Choose a PIN (6–12 digits)', { exact: true }).fill('582694');
        await page.getByLabel('Repeat your PIN', { exact: true }).fill('582694');
        await page.getByRole('button', { name: 'Save PIN and trust device', exact: true }).click();
        await expect(page.locator('[data-offline-access-target=successMethod]')).toContainText(`Your ${siteTitle} PIN is set up`);
        await expect(page.locator('[data-offline-access-target=readiness]')).toContainText('Ready offline');
        await page.getByRole('button', { name: 'Done', exact: true }).click();
        await expect(page.locator('[data-offline-access-target=status]')).toContainText('Available offline');
        const cardStructure = await page.locator('[data-member-mobile-card-profile-target=memberDetails]').evaluate(el => [...el.children].map(child => [child.tagName, child.className, child.getAttribute('data-member-mobile-card-profile-target')]));
        const record = await stored(page);
        assert.equal(record.wrapper.method, 'trusted');
        assert.equal(record.wrapper.unlockMethod, 'pin');
        assert.equal(record.wrapper.key, undefined);
        assert.equal(JSON.stringify(record).includes(fixture.password), false);
        assert.equal(JSON.stringify(record).includes(fixture.name), false);
        await page.waitForSelector('[data-member-mobile-card-profile-target=memberDetails]:not([hidden])');
        assert.equal(await page.getByRole('link', { name: 'Open my card', exact: true }).isVisible(), false);
        // A normal fetch updates the trusted copy even while automatic full refresh is throttled.
        const capturedName = 'Synthetic freshly captured card';
        await page.route('**/members/view-mobile-card-json', async route => {
            const response = await route.fetch();
            const json = await response.json();
            json.member.sca_name = capturedName;
            await route.fulfill({ response, json });
        });
        const received = await page.evaluate(async () => (await (await fetch('/members/view-mobile-card-json', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })).json()).member.sca_name);
        assert.equal(received, capturedName);
        await page.unroute('**/members/view-mobile-card-json');
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
        disconnected = true;
        await context.setOffline(true);
        await page.goto('/members/view-mobile-card', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('[data-member-mobile-card-profile-target=memberDetails]:not([hidden])');
        assert.deepEqual(await page.locator('[data-member-mobile-card-profile-target=memberDetails]').evaluate(el => [...el.children].map(child => [child.tagName, child.className, child.getAttribute('data-member-mobile-card-profile-target')])), cardStructure);
        assert.equal(await page.locator('meta[name=kmp-offline-session], meta[name=csrfToken], #debug-kit-toolbar').count(), 0);
        assert.equal(await page.locator('[data-member-mobile-card-profile-target=memberDetails]').textContent().then(text => text.includes(capturedName)), true);
        await page.screenshot({ path: '/tmp/kmp-natural-card-offline.png' });
        await page.getByRole('button', { name: 'Open menu', exact: true }).click();
        await page.keyboard.press('Escape');
        assert.equal(await page.getByRole('button', { name: 'Open menu', exact: true }).getAttribute('aria-expanded'), 'false');
        await page.getByRole('button', { name: 'Open menu', exact: true }).click();
        await page.getByRole('link', { name: 'My RSVPs', exact: true }).click();
        await page.waitForSelector('.my-rsvps-container');
        await page.getByRole('tab', { name: /Upcoming/ }).waitFor();
        assert.equal(await page.locator('[data-member-mobile-card-profile-target=memberDetails]').isVisible(), false);
        await page.getByRole('button', { name: 'Open menu', exact: true }).click();
        await page.getByRole('link', { name: 'Events', exact: true }).click();
        await page.waitForSelector('.mobile-events-container');
        await page.getByRole('button', { name: 'Toggle filters' }).waitFor();
        await page.locator('.mobile-event-card').filter({ hasText: fixture.name }).getByRole('button', { name: 'RSVP', exact: true }).click();
        await expect(page.getByRole('dialog').getByLabel('Share with Kingdom', { exact: true })).toBeFocused();
        assert.equal(await page.getByRole('dialog').locator('input:checked').count(), 0);
        await page.keyboard.press('Escape');
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
        assert.equal((await page.evaluate(() => window.RsvpCacheService.getPendingRsvps())).length, 0);
        const rsvpButton = page.locator('.mobile-event-card').filter({ hasText: fixture.name }).getByRole('button', { name: 'RSVP', exact: true });
        await expect(rsvpButton).toBeFocused();
        await rsvpButton.click();
        await page.getByRole('dialog').getByLabel('Share with Hosting Group', { exact: true }).check();
        await page.getByRole('dialog').getByLabel('Share with Nobility/Crown', { exact: true }).check();
        await page.screenshot({ path: '/tmp/kmp-rsvp-visibility-offline.png' });
        await page.getByRole('button', { name: 'Save RSVP', exact: true }).click();
        await waitIdle(page);
        await page.locator('.mobile-event-card').filter({ hasText: fixture.name }).getByRole('button', { name: 'Cancel waiting RSVP' }).waitFor();
        assert.equal(await page.locator('.mobile-event-card').filter({ hasText: fixture.name }).getByRole('button', { name: 'Cancel waiting RSVP', exact: true }).count(), 1);
        const pending = await page.evaluate(() => window.RsvpCacheService.getPendingRsvps());
        assert.equal(pending.length, 1);
        assert.equal(pending[0].share_with_kingdom, false);
        assert.equal(pending[0].share_with_hosting_group, true);
        assert.equal(pending[0].share_with_crown, true);
        // Startup cleanup preserves the new encrypted, owner-bound queue across reloads.
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.locator('.mobile-event-card').filter({ hasText: fixture.name }).getByRole('button', { name: 'Cancel waiting RSVP', exact: true }).waitFor();
        assert.equal((await page.evaluate(() => window.RsvpCacheService.getPendingRsvps()))[0].id, pending[0].id);
        await page.getByRole('button', { name: 'Open menu', exact: true }).click();
        await page.getByRole('link', { name: 'My RSVPs', exact: true }).click();
        const queuedCard = page.locator('.mobile-event-card').filter({ hasText: fixture.name });
        await expect(queuedCard).toContainText('Shared: hosting group, Crown');
        await expect(queuedCard).toContainText('Waiting to send');
        await context.clearCookies();
        await page.goto('/offline');
        await page.waitForSelector('[data-member-mobile-card-profile-target=memberDetails]:not([hidden])');
        assert.equal((await page.evaluate(() => window.RsvpCacheService.getPendingRsvps()))[0].id, pending[0].id);
        // The unlocked encrypted login restores an expired server session on reconnect.
        disconnected = false;
        await context.setOffline(false);
        // Reconnection restores the normal card and its Security button without a separate action.
        await page.evaluate(() => window.dispatchEvent(new Event('online')));
        await page.waitForURL('**/members/view-mobile-card').catch(async error => {
            console.error('Reconnect diagnostic', await page.evaluate(async () => ({
                url: location.href, resume: sessionStorage.getItem('kmp.offline.resume'), now: Date.now(),
                status: document.querySelector('[data-offline-vault-target=status]')?.textContent,
                contextStatus: (await fetch('/offline/context')).status
            })));
            throw error;
        });
        await page.getByRole('button', { name: 'Security', exact: true }).waitFor();
        // Any ordinary page now sends waiting work without visiting offline setup.
        await expect.poll(() => page.evaluate(async () => (await window.RsvpCacheService.getPendingRsvps()).length), { timeout: 60000 }).toBe(0);
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
                visibilityPreserved: matching.length === 1 && !matching[0].sharing.kingdom && matching[0].sharing.hosting_group && matching[0].sharing.crown && !matching[0].note };
        }, { id: fixture.id, requestId: pending[0].id });
        assert.deepEqual(result, { retry: 200, retrySuccess: true, mismatch: 403, count: 1, visibilityPreserved: true });
        disconnected = true;
        await context.setOffline(true);
        await page.goto('/members/view-mobile-card');
        await page.waitForSelector('[data-member-mobile-card-profile-target=memberDetails]:not([hidden])');
        await waitIdle(page);
        await page.setViewportSize({ width: 390, height: 844 });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        const otherTab = await context.newPage();
        await otherTab.goto('/offline');
        await otherTab.getByLabel('Device PIN', { exact: true }).fill('582694');
        await otherTab.getByRole('button', { name: `Unlock ${siteTitle}`, exact: true }).click();
        await otherTab.waitForSelector('[data-member-mobile-card-profile-target=memberDetails]:not([hidden])');
        await page.bringToFront();
        await page.waitForSelector('[data-member-mobile-card-profile-target=memberDetails]:not([hidden])');
        await otherTab.close();
        assert.equal(await page.locator('[data-offline-vault-target=locked]').isVisible(), false);
        const cookies = await context.cookies();
        const foreign = await browser.newContext({ baseURL: (process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080').replace('kmp.localhost', 'kmp2.localhost') });
        await foreign.addCookies(cookies.filter(cookie => cookie.domain === 'kmp.localhost').map(cookie => ({ ...cookie, domain: 'kmp2.localhost' })));
        const foreignPage = await foreign.newPage();
        const replay = await foreignPage.goto('/members/profile');
        assert.notEqual(replay.status(), 503);
        assert.match(new URL(foreignPage.url()).pathname, /members\/login/);
        await foreign.close();
        disconnected = false;
        await context.setOffline(false);
        const revocationTab = await context.newPage();
        await revocationTab.goto('/offline');
        await revocationTab.getByLabel('Device PIN', { exact: true }).fill('582694');
        await revocationTab.getByRole('button', { name: `Unlock ${siteTitle}`, exact: true }).click();
        await revocationTab.waitForSelector('[data-member-mobile-card-profile-target=memberDetails]:not([hidden])');
        await page.goto('/members/logout');
        await page.getByLabel('Device PIN', { exact: true }).waitFor();
        assert.equal((await stored(page)).wrapper.unlockMethod, 'pin');
        await revocationTab.getByLabel('Device PIN', { exact: true }).waitFor();
        assert.equal(await revocationTab.locator('[data-member-mobile-card-profile-target=memberDetails]').isVisible(), false);
        disconnected = true;
        await context.setOffline(true);
        await page.goto('/members/login');
        await page.getByLabel('Device PIN', { exact: true }).fill('000000');
        await page.getByRole('button', { name: `Unlock ${siteTitle}`, exact: true }).click();
        await expect(page.locator('[data-offline-access-target=status]')).toContainText('Unable to unlock');
        await page.getByLabel('Device PIN', { exact: true }).fill('582694');
        await page.getByRole('button', { name: `Unlock ${siteTitle}`, exact: true }).click();
        await page.waitForSelector('[data-member-mobile-card-profile-target=memberDetails]:not([hidden])');
        await page.reload();
        await page.waitForSelector('[data-member-mobile-card-profile-target=memberDetails]:not([hidden])');
        disconnected = false;
        await context.setOffline(false);
        await expect.poll(() => page.evaluate(async () => (await fetch('/offline/context')).status), { timeout: 30000 }).toBe(200);
        await page.goto('/members/logout');
        await page.getByLabel('Device PIN', { exact: true }).fill('582694');
        await page.getByRole('button', { name: `Unlock ${siteTitle}`, exact: true }).click();
        await page.waitForURL(url => /\/members\/(?:view\/|view-mobile-card)/.test(url.pathname));
        assert.equal(await page.evaluate(async () => (await fetch('/offline/context')).status), 200);
        disconnected = true;
        await context.setOffline(true);
        await page.goto('/offline');
        await page.waitForSelector('[data-member-mobile-card-profile-target=memberDetails]:not([hidden])');
        await page.getByText('This device', { exact: true }).click();
        await page.locator('[data-action="offline-vault#forget"]').click();
        await page.getByRole('button', { name: 'Confirm', exact: true }).click();
        await expect.poll(async () => !!await stored(page)).toBe(false);
        await revocationTab.close();
        assert.deepEqual(errors, []);
        console.log('PASS: mobile routes and Menu continuity, automatic return to online controls, signed-in card, public-only cache, automatic offline reopen, encrypted RSVP queue across reload and session expiry, trusted tabs, same-owner sync, duplicate retry, actor mismatch denial, chosen visibility, logout lock and offline PIN unlock, explicit device removal, real-cookie cross-tenant denial, mobile reflow.');
        await context.close();

        // Shared browsers do not save private data until the user explicitly trusts them.
        const deviceContext = await browser.newContext({ baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080', viewport: { width: 390, height: 844 } });
        const devicePage = await deviceContext.newPage();
        devicePage.on('pageerror', error => errors.push(error.message));
        await loginAs(devicePage, fixture.email, fixture.password);
        await devicePage.goto('/members/view-mobile-card');
        await devicePage.getByRole('button', { name: 'Not now', exact: true }).click();
        await devicePage.reload();
        assert.equal(await devicePage.getByRole('button', { name: 'Trust this personal device', exact: true }).isVisible(), false);
        const security = devicePage.getByRole('button', { name: 'Security', exact: true });
        await security.click();
        await devicePage.getByRole('heading', { name: 'Your account security', exact: true }).waitFor();
        assert.equal(await devicePage.locator('#security-settings').evaluate(frame => frame.contains(document.activeElement)), true);
        await devicePage.getByRole('button', { name: 'Change password', exact: false }).click();
        await devicePage.getByRole('button', { name: 'Continue', exact: true }).click();
        await devicePage.getByLabel(`New ${siteTitle} password`, { exact: true }).fill('synthetic mismatch words');
        await devicePage.getByLabel('Repeat new password', { exact: true }).fill('different synthetic words');
        await devicePage.getByRole('button', { name: 'Save password', exact: true }).click();
        assert.equal(await devicePage.getByLabel('Repeat new password', { exact: true }).getAttribute('aria-invalid'), 'true');
        await devicePage.keyboard.press('Escape');
        await devicePage.locator('#securityModal').waitFor({ state: 'hidden' });
        await devicePage.waitForFunction(() => document.activeElement?.matches('[data-bs-target="#securityModal"]'));
        assert.equal(await security.evaluate(button => button === document.activeElement), true);
        assert.equal(await devicePage.locator('#security-settings').textContent(), '');
        // The same wizard is usable inside the phone-sized Security dialog.
        await security.click();
        const trust = devicePage.locator('#security-settings [data-controller=offline-access]');
        await trust.getByRole('button', { name: 'Trust this personal device', exact: true }).click();
        await expect(trust.locator('[data-offline-access-target=stepHeading]')).toBeFocused();
        await expect(devicePage.getByRole('button', { name: 'Change password', exact: false })).toBeHidden();
        await trust.getByLabel(`Current ${siteTitle} password`, { exact: true }).fill(fixture.password);
        await trust.getByRole('button', { name: 'Continue', exact: true }).click();
        await expect(trust.locator('[data-offline-access-target=stepLabel]')).toHaveText('Step 2 of 3');
        await trust.getByRole('button', { name: 'Back', exact: true }).click();
        await expect(trust.getByLabel(`Current ${siteTitle} password`, { exact: true })).toHaveValue('');
        await trust.getByRole('button', { name: 'Cancel setup', exact: true }).click();
        await expect(trust.getByRole('button', { name: 'Trust this personal device', exact: true })).toBeFocused();
        await trust.getByRole('button', { name: 'Trust this personal device', exact: true }).click();
        await trust.getByLabel(`Current ${siteTitle} password`, { exact: true }).fill(fixture.password);
        await trust.getByRole('button', { name: 'Continue', exact: true }).click();
        await choosePin(trust);
        await trust.getByLabel('Choose a PIN (6–12 digits)', { exact: true }).fill('582694');
        await trust.getByLabel('Repeat your PIN', { exact: true }).fill('582694');
        await trust.getByRole('button', { name: 'Save PIN and trust device', exact: true }).click();
        await expect(trust.locator('[data-offline-access-target=successHeading]')).toBeFocused();
        await expect(trust.locator('[data-offline-access-target=readiness]')).toContainText('Ready offline');
        await devicePage.screenshot({ path: '/tmp/kmp-trust-security-success.png', fullPage: true });
        await expect(devicePage.getByRole('button', { name: 'Done', exact: true })).toHaveCount(1);
        await trust.getByRole('button', { name: 'Done', exact: true }).click();
        await expect(devicePage.getByRole('button', { name: 'Change password', exact: false })).toBeVisible();
        await devicePage.keyboard.press('Escape');
        await devicePage.locator('#securityModal').waitFor({ state: 'hidden' });
        await expect(security).toBeFocused();
        assert.deepEqual(errors, []);
        await deviceContext.close();
        console.log('PASS: shared-device opt-out, Security modal focus/escape and password validation.');
    } finally {
        await browser.close();
        runPhpJson(tenantFixture, { cleanup: fixture.id, name: fixture.name, memberId: fixture.memberId, email: fixture.email });
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
