/** Generated account, real WebAuthn signatures/PRF and IndexedDB; no external provider required. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright');
const { expect } = require('@playwright/test');
const { runPhpJson } = require('./ui-helpers.cjs');
const source = fs.readFileSync(require.resolve('./offline-app-browser-check.cjs'), 'utf8');
const fixtureScript = source.match(/const tenantFixture = String.raw`([\s\S]*?)`;/)[1];
(async () => {
    let fixture;
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    try {
        for (const scenario of (process.env.PASSKEY_SCENARIO ? [process.env.PASSKEY_SCENARIO] : ['supported', 'external-supported', 'hybrid', 'online-only', 'pin-only'])) {
            fixture = runPhpJson(fixtureScript);
            const prf = scenario.endsWith('supported');
            const pinOnly = scenario === 'pin-only';
            const context = await browser.newContext({ baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080' });
            if (pinOnly) await context.addInitScript(() => { Object.defineProperty(window, 'PublicKeyCredential', { value: undefined }); });
            if (scenario === 'hybrid') await context.addInitScript(() => {
                if (globalThis.PublicKeyCredential) PublicKeyCredential.getClientCapabilities = async () => ({ 'extension:prf': false });
            });
            let offline = false;
            await context.route('**/*', route => offline && route.request().serviceWorker() ? route.abort('internetdisconnected') : route.continue());
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            page.on('response', response => { if (response.status() >= 400 && /passkey/.test(response.url())) console.log('passkey HTTP', response.status(), new URL(response.url()).pathname); });
            const cdp = await context.newCDPSession(page);
            await cdp.send('WebAuthn.enable');
            await cdp.send('WebAuthn.addVirtualAuthenticator', { options: { protocol: 'ctap2', transport: scenario === 'external-supported' ? 'usb' : 'internal',
                hasResidentKey: true, hasUserVerification: true, isUserVerified: true, automaticPresenceSimulation: true, hasPrf: prf } });
            const target = name => page.locator(`[data-offline-access-target=${name}]`).filter({ visible: true }).first();
            const signIn = async () => {
                await page.goto('/members/login?method=password');
                await page.locator('[data-login-device-auth-target=email]').fill(fixture.email);
                await page.locator('[data-login-device-auth-target=passwordForm] [name=password]').fill(fixture.password);
                await page.getByRole('button', { name: 'Sign in', exact: true }).click();
                await page.waitForURL(url => /\/members\/view\//.test(url.pathname));
            };
            const checkPasskey = async () => {
                for (let step = 0; step < 2; step++) {
                    const finish = page.getByRole('button', { name: 'Finish passkey setup', exact: true });
                    await finish.waitFor(); await finish.click();
                    await expect(page.locator('[data-controller=offline-access]').filter({ visible: true })).not.toHaveAttribute('aria-busy', 'true');
                    if (!await finish.isVisible()) break;
                }
            };
            const savePin = async () => {
                await target('pin').fill('582694');
                await target('confirm').fill('582694');
                await target('next').click();
            };
            await signIn();
            await page.goto('/members/view-mobile-card');
            await page.waitForFunction(() => !!window.KMP_passkeyDebug);
            await page.evaluate(() => window.KMP_passkeyDebug.start());
            await page.getByRole('button', { name: 'Trust this personal device', exact: true }).click();
            await target('password').fill(fixture.password);
            await target('next').click();
            await expect(target('stepHeading')).toBeFocused();
            if (pinOnly) await savePin();
            else {
                await page.getByRole('button', { name: 'Set up passkey', exact: true }).click();
                await checkPasskey();
                if (!prf) {
                    await expect(target('availability')).toContainText('Your passkey works for sign-in');
                    await expect(target('pin')).toBeFocused();
                    if (scenario === 'online-only') await target('onlineOnly').click();
                    else await savePin();
                }
            }
            await expect(target('successHeading')).toBeFocused();
            await expect(target('readiness')).toContainText(scenario === 'online-only' ? 'Online only' : 'Ready offline', { timeout: 30000 });
            const report = await page.evaluate(() => window.KMP_passkeyDebug.report());
            assert(!report.includes(fixture.password)); assert(!report.includes(fixture.email));
            const events = JSON.parse(report).events;
            if (!pinOnly) assert(events.some(event => event.stage === 'assertion-verified'));
            if (prf) assert(events.some(event => event.stage === 'verify-committed'));
            fs.writeFileSync('/tmp/kmp-passkey-debug-' + scenario + '.json', report);
            await page.setViewportSize({ width: 320, height: 700 });
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
            await page.screenshot({ path: '/tmp/kmp-passkey-' + scenario + '.png', fullPage: true });
            await page.getByRole('button', { name: 'Done', exact: true }).click();
            await page.goto('/members/logout');
            if (pinOnly) await target('unlockPin').fill('582694');
            else {
                await expect(target('unlockButton')).toHaveText('Sign in with passkey');
                await expect(page.locator('[data-offline-access-target=unlockPin]')).toBeHidden();
            }
            await target('unlockButton').click();
            await page.waitForURL(url => /\/members\/view\//.test(url.pathname)).catch(async error => {
                console.log('login status:', await page.locator('[data-offline-access-target=status]').allTextContents());
                throw error;
            });
            assert.equal(await page.evaluate(async () => (await fetch('/offline/context')).status), 200);
            if (scenario === 'online-only') {
                await page.goto('/members/logout');
                offline = true; await context.setOffline(true);
                await page.goto('/members/login');
                await expect(target('status')).toContainText('Offline access is unavailable');
                await expect(target('unlockButton')).toBeDisabled();
                offline = false; await context.setOffline(false);
                await page.goto('/members/login');
                await target('unlockButton').click();
                await page.waitForURL(url => /\/members\/view\//.test(url.pathname));
                // Security exposes the same shared panel. Its online-only state has no private payload.
                await page.goto('/members/view/' + fixture.memberId);
                await page.getByRole('button', { name: 'Security', exact: true }).click();
                await page.getByRole('button', { name: 'Add offline PIN', exact: true }).click();
                await target('password').fill(fixture.password);
                await target('next').click();
                await checkPasskey();
                await savePin();
                await expect(target('readiness')).toContainText('Ready offline', { timeout: 30000 });
            }
            await page.goto('/members/logout');
            offline = true; await context.setOffline(true);
            await page.goto('/members/login');
            if (!prf && !pinOnly) {
                await expect(page.locator('[data-offline-access-target=unlockPin]')).toBeHidden();
                await expect(target('unlockButton')).toHaveText('Continue with passkey');
                await target('unlockButton').click();
                await expect(target('unlockPin')).toBeFocused();
            }
            if (!prf) await target('unlockPin').fill('582694');
            await target('unlockButton').click();
            await page.waitForSelector('[data-member-mobile-card-profile-target=memberDetails]:not([hidden])', { timeout: 10000 }).catch(async error => {
                console.log('offline navigator:', await page.evaluate(() => navigator.onLine));
                console.log('offline status:', await page.locator('[data-offline-access-target=status], [data-offline-vault-target=status]').allTextContents());
                await page.screenshot({ path: '/tmp/kmp-offline-failure.png', fullPage: true });
                throw error;
            });
            if (scenario === 'supported') {
                const device = page.locator('[data-offline-vault-target=device]');
                await device.locator('summary').click();
                await device.getByRole('button', { name: 'Stop trusting this device', exact: true }).click();
                const confirm = page.locator('[data-dialog-confirm]');
                await expect(confirm).toBeFocused();
                await confirm.click();
                await expect(page.locator('[data-offline-vault-target=status]')).toContainText('Connect and sign in');
                await expect(device).toBeVisible();
                await expect(page.locator('[data-member-mobile-card-profile-target=memberDetails]')).toBeVisible();
                console.log('PASS: offline public recovery retains the passkey-protected copy when revocation is unavailable');
                offline = false; await context.setOffline(false);
                await signIn();
                // Keep the recovery page open while its normal background refresh completes.
                await page.evaluate(() => sessionStorage.setItem('kmp.offline.resume', String(Date.now())));
                await page.goto('/offline');
                await device.locator('summary').click();
                await device.getByRole('button', { name: 'Stop trusting this device', exact: true }).click();
                await expect(confirm).toBeFocused();
                const [removed] = await Promise.all([
                    page.waitForResponse(response => response.url().endsWith('/offline/remove-passkey') && response.request().method() === 'POST'),
                    confirm.click(),
                ]);
                expect(removed.status()).toBe(204);
                await expect(page.locator('[data-offline-vault-target=enroll]')).toBeVisible();
                await expect(page.locator('[data-offline-vault-target=enroll] a')).toBeFocused();
                await expect(device).toBeHidden();
                console.log('PASS: signed-in public recovery revokes the server passkey before clearing the local copy');
            }
            assert.deepEqual(errors, []);
            await context.close();
            runPhpJson(fixtureScript, { cleanup: fixture.id, name: fixture.name, memberId: fixture.memberId, email: fixture.email });
            fixture = null;
            console.log(scenario + ': PASS enrollment, online login, logout, offline unlock, focus and mobile reflow');
        }
    } finally {
        await browser.close();
        if (fixture) runPhpJson(fixtureScript, { cleanup: fixture.id, name: fixture.name, memberId: fixture.memberId, email: fixture.email });
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
