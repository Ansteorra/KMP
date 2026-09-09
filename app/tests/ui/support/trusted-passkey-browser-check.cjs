/** Local generated account; exercises real WebAuthn PRF through Chromium's virtual authenticator. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium, devices } = require('playwright');
const { expect } = require('@playwright/test');
const { runPhpJson } = require('./ui-helpers.cjs');
const source = fs.readFileSync(require.resolve('./offline-app-browser-check.cjs'), 'utf8');
const fixtureScript = source.match(/const tenantFixture = String.raw`([\s\S]*?)`;/)[1];
(async () => {
    const fixture = runPhpJson(fixtureScript);
    const siteTitle = fixture.shortSiteTitle;
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    try {
        for (const scenario of ['supported', 'provider-unsupported', 'browser-unsupported']) {
            const hasPrf = scenario === 'supported';
            const context = await browser.newContext({ baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080', ...(hasPrf ? devices['iPhone 13'] : { viewport: { width: 1280, height: 900 } }) });
            if (scenario === 'browser-unsupported') await context.addInitScript(() => {
                PublicKeyCredential.getClientCapabilities = async () => ({ 'extension:prf': false });
                navigator.credentials.create = () => { throw new Error('A passkey prompt must not open in this browser.'); };
            });
            let offline = false;
            await context.route('**/*', route => offline && route.request().serviceWorker() ? route.abort('internetdisconnected') : route.continue());
            const page = await context.newPage();
            const errors = []; page.on('pageerror', error => errors.push(error.message));
            const cdp = await context.newCDPSession(page);
            await cdp.send('WebAuthn.enable');
            await cdp.send('WebAuthn.addVirtualAuthenticator', { options: { protocol: 'ctap2', transport: 'internal',
                hasResidentKey: true, hasUserVerification: true, isUserVerified: true, automaticPresenceSimulation: true, hasPrf } });
            // Simulate an upgraded browser with the exact old quick-PIN storage schema.
            await page.goto('/members/login');
            await page.evaluate(email => {
                const deviceId = 'legacy-device-id-12345678';
                localStorage.setItem('kmp.quickLogin.deviceId', deviceId);
                localStorage.setItem('kmp.quickLogin.config', JSON.stringify({ email, deviceId, pinSalt: 'legacy-salt', pinHash: 'legacy-hash' }));
            }, fixture.email);
            await page.reload();
            const migrationNotice = page.locator('[data-login-device-auth-target=migrationNotice]');
            await expect(migrationNotice).toContainText('Your old PIN no longer works');
            await expect(migrationNotice).toBeVisible();
            await expect(page.locator('[data-login-device-auth-target=quickExperience]')).toBeHidden();
            await expect(page.locator('[data-login-device-auth-target=email]')).toHaveValue(fixture.email);
            offline = true; await context.setOffline(true);
            await expect(page.locator('[data-login-device-auth-target=passwordForm]')).toBeHidden();
            await expect(migrationNotice).toBeVisible();
            offline = false; await context.setOffline(false);
            await expect(page.locator('[data-login-device-auth-target=passwordForm]')).toBeVisible();
            if (hasPrf) await page.screenshot({ path: '/tmp/kmp-legacy-pin-login.png', fullPage: true });
            await page.reload();
            await expect(migrationNotice).toBeVisible();
            const signInForm = page.locator('[data-login-device-auth-target=passwordForm]');
            await signInForm.locator('[name=password]').fill('Deliberately incorrect test password');
            await Promise.all([
                page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/members/login'),
                page.getByRole('button', { name: 'Sign in', exact: true }).click()
            ]);
            await expect(migrationNotice).toBeVisible();
            await expect(signInForm).toBeVisible();
            await signInForm.locator('[name=password]').fill(fixture.password);
            await page.getByRole('button', { name: 'Sign in', exact: true }).click();
            await expect(page).toHaveURL(new RegExp(hasPrf ? '/members/view-mobile-card$' : '/members/view/' + fixture.memberId + '$'));
            await expect(page.locator('[data-offline-access-target=migration]:visible').first()).toContainText('You’re signed in');
            await page.screenshot({ path: '/tmp/kmp-legacy-pin-setup-' + scenario + '.png', fullPage: true });
            await page.goto('/members/view-mobile-card');
            await page.getByRole('button', { name: 'Set up new PIN or passkey', exact: true }).click();
            await expect(page.locator('[data-offline-access-target=stepHeading]')).toBeFocused();
            if (hasPrf) await page.screenshot({ path: '/tmp/kmp-trust-step-1.png', fullPage: true });
            await page.getByLabel(`Current ${siteTitle} password`, { exact: true }).fill(fixture.password);
            await page.getByRole('button', { name: 'Continue', exact: true }).click();
            await expect(page.locator('[data-offline-access-target=stepHeading]')).toHaveText(scenario === 'browser-unsupported' ? `Choose your ${siteTitle} PIN` : `Choose how to unlock ${siteTitle}`);
            await expect(page.locator('[data-offline-access-target=stepHeading]')).toBeFocused();
            if (hasPrf) await page.screenshot({ path: '/tmp/kmp-trust-step-2.png', fullPage: true });
            if (scenario !== 'browser-unsupported') {
                await page.getByLabel('Unlock this device with', { exact: true }).selectOption('device');
                await page.getByRole('button', { name: 'Set up passkey', exact: true }).click();
            }
            if (hasPrf) {
                for (let step = 0; step < 2; step++) {
                    const finish = page.getByRole('button', { name: 'Finish passkey setup', exact: true });
                    await finish.waitFor(); await finish.click();
                    await expect(page.locator('[data-controller=offline-access]')).not.toHaveAttribute('aria-busy', 'true');
                    if (!await finish.isVisible()) break;
                }
            } else {
                await expect(page.getByRole('button', { name: 'Set up passkey', exact: true })).toBeHidden();
                await expect(page.locator('[data-offline-access-target=methodChoice]')).toBeHidden();
                await expect(page.locator('[data-offline-access-target=availability]')).toContainText(`${siteTitle} PIN`);
                if (scenario === 'provider-unsupported') {
                    await expect(page.locator('[data-offline-access-target=pin]')).toBeFocused();
                    await expect(page.getByRole('button', { name: 'Check passkey support again', exact: true })).toBeVisible();
                    await page.screenshot({ path: '/tmp/kmp-passkey-pin-recovery.png', fullPage: true });
                    // A later setup skips the provider that already failed, without opening another prompt.
                    await page.getByRole('button', { name: 'Cancel setup', exact: true }).click();
                    await page.getByRole('button', { name: 'Set up new PIN or passkey', exact: true }).click();
                    await page.getByLabel(`Current ${siteTitle} password`, { exact: true }).fill(fixture.password);
                    await page.getByRole('button', { name: 'Continue', exact: true }).click();
                    await expect(page.locator('[data-offline-access-target=methodChoice]')).toBeHidden();
                    await page.getByRole('button', { name: 'Check passkey support again', exact: true }).click();
                    await expect(page.getByLabel('Unlock this device with', { exact: true })).toBeFocused();
                    await page.getByLabel('Unlock this device with', { exact: true }).selectOption('pin');
                }
                await page.getByLabel('Choose a PIN (6–12 digits)', { exact: true }).fill('582694');
                await page.getByLabel('Repeat your PIN', { exact: true }).fill('582694');
                assert.equal(await page.getByLabel(`Current ${siteTitle} password`, { exact: true }).isVisible(), false);
                await page.getByRole('button', { name: 'Save PIN and trust device', exact: true }).click();
            }
            await expect(page.locator('[data-offline-access-target=successHeading]')).toBeVisible();
            assert.equal(await page.evaluate(() => localStorage.getItem('kmp.quickLogin.migration.v1')), null);
            await expect(page.locator('[data-offline-access-target=migration]')).toBeHidden();
            await expect(page.locator('[data-offline-access-target=successHeading]')).toBeFocused();
            await expect(page.locator('[data-offline-access-target=successMethod]')).toContainText(hasPrf ? 'Your passkey is set up' : `Your ${siteTitle} PIN is set up`);
            await expect(page.locator('[data-offline-access-target=readiness]')).toContainText('Ready offline');
            // Background state events cannot dismiss or overwrite the completion screen.
            await page.evaluate(() => window.dispatchEvent(new Event('kmp:offline-state')));
            await expect(page.locator('[data-offline-access-target=successHeading]')).toBeVisible();
            await page.setViewportSize({ width: 320, height: 700 });
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            await page.screenshot({ path: hasPrf ? '/tmp/kmp-trust-passkey-success.png' : '/tmp/kmp-trust-pin-success.png', fullPage: true });
            await page.getByRole('button', { name: 'Done', exact: true }).click();
            await expect(page.locator('[data-offline-access-target=success]')).toBeHidden();
            await expect(page.locator('[data-offline-access-target=status]')).toBeFocused();
            await expect(page.locator('[data-offline-access-target=status]')).toContainText('Available offline');
            // Observe every login render, including while the password POST is still pending.
            await context.addInitScript(() => {
                window.loginCardLinkFlashed = false;
                new MutationObserver(() => {
                    if (!/^\/members\/login\/?$/.test(location.pathname)) return;
                    const link = document.querySelector('[data-offline-access-target=link]');
                    if (link && link.getClientRects().length) window.loginCardLinkFlashed = true;
                }).observe(document, { subtree: true, attributes: true, childList: true });
            });
            await page.goto('/members/logout');
            const unlock = page.getByRole('button', { name: hasPrf ? 'Unlock with passkey' : `Unlock ${siteTitle}`, exact: true });
            await unlock.waitFor();
            const passwordForm = page.locator('[data-login-device-auth-target=passwordForm]');
            await expect(passwordForm).toBeHidden();
            await page.getByRole('button', { name: 'Use email and password', exact: true }).click();
            await expect(unlock).toBeHidden();
            await expect(passwordForm).toBeVisible();
            await expect(page.locator('[data-login-device-auth-target=email]')).toBeFocused();
            await page.screenshot({ path: '/tmp/kmp-login-password-view.png', fullPage: true });
            await passwordForm.locator('[name=password]').fill('temporary input');
            const deviceSwitch = page.getByRole('button', { name: 'Use device unlock', exact: true });
            await deviceSwitch.focus();
            await page.keyboard.press('Enter');
            await expect(passwordForm).toBeHidden();
            await expect(passwordForm.locator('[name=password]')).toHaveValue('');
            await expect(unlock).toBeVisible();
            await page.getByRole('button', { name: 'Use email and password', exact: true }).click();
            await passwordForm.locator('[name=password]').fill('temporary input');
            offline = true; await context.setOffline(true);
            await expect(passwordForm).toBeHidden();
            await expect(passwordForm.locator('[name=password]')).toHaveValue('');
            await expect(deviceSwitch).toBeHidden();
            await expect(page.getByRole('button', { name: 'Use email and password', exact: true })).toBeHidden();
            await expect(unlock).toBeVisible();
            await page.goto('/members/login');
            await expect(page.getByRole('button', { name: 'Use email and password', exact: true })).toBeHidden();
            await page.screenshot({ path: '/tmp/kmp-login-device-offline.png', fullPage: true });
            if (!hasPrf) await page.getByLabel('Device PIN', { exact: true }).fill('582694');
            await unlock.click();
            await page.waitForSelector('[data-member-mobile-card-profile-target=memberDetails]:not([hidden])');
            await expect(page.locator('[data-offline-vault-target=status]')).toBeHidden();
            await page.evaluate(() => window.dispatchEvent(new Event('kmp:offline-progress')));
            await expect(page.locator('[data-offline-vault-target=status]')).toBeHidden();
            await expect(page.locator('[data-offline-access-target=status]')).toBeVisible();
            await page.screenshot({ path: hasPrf ? '/tmp/kmp-passkey-offline.png' : '/tmp/kmp-pin-fallback-offline.png' });
            offline = false; await context.setOffline(false);
            await expect.poll(() => page.evaluate(async () => (await fetch('/offline/context')).status), { timeout: 30000 }).toBe(200);
            await page.goto('/members/logout');
            if (!hasPrf) await page.getByLabel('Device PIN', { exact: true }).fill('582694');
            let releaseLogin;
            const loginGate = new Promise(resolve => { releaseLogin = resolve; });
            await page.route('**/members/login', async route => {
                if (route.request().method() === 'POST') await loginGate;
                await route.continue();
            });
            const posted = page.waitForRequest(request => request.method() === 'POST' && new URL(request.url()).pathname === '/members/login');
            await unlock.click();
            await posted;
            await expect(page.locator('[data-offline-access-target=link]')).toBeHidden();
            assert.equal(await page.evaluate(() => window.loginCardLinkFlashed), false);
            releaseLogin();
            await page.waitForURL(url => /\/members\/(?:view\/|view-mobile-card)/.test(url.pathname));
            assert.equal(new URL(page.url()).pathname, hasPrf ? '/members/view-mobile-card' : '/members/view/' + fixture.memberId);
            assert.equal(await page.evaluate(async () => (await fetch('/offline/context')).status), 200);
            // Password sign-in leaves the encrypted copy locked, but must not ask for another login.
            await page.unroute('**/members/login');
            await page.goto('/members/logout');
            await page.getByRole('button', { name: 'Use email and password', exact: true }).click();
            await page.locator('[data-login-device-auth-target=email]').fill(fixture.email);
            await page.locator('[data-login-device-auth-target=passwordForm] [name=password]').fill(fixture.password);
            await page.getByRole('button', { name: 'Sign in', exact: true }).click();
            await page.waitForURL(url => /\/members\/(?:view\/|view-mobile-card)/.test(url.pathname));
            for (const profile of ['/members/view/' + fixture.memberId, '/members/view-mobile-card']) {
                await page.goto(profile);
                const devicePanel = page.locator('[data-controller=offline-access]').first();
                await expect(devicePanel.locator('[data-offline-access-target=status]')).toContainText(`Unlock ${siteTitle} with`);
                await expect(devicePanel).toBeHidden();
                await expect(unlock).toBeHidden();
            }
            await page.screenshot({ path: '/tmp/kmp-signed-in-mobile-' + scenario + '.png' });
            offline = true; await context.setOffline(true);
            await expect(unlock).toBeVisible();
            assert.deepEqual(errors, []);
            await context.close();
            console.log(scenario + ': ' + (hasPrf ? 'PASS: real WebAuthn PRF enrollment, logout, offline passkey unlock, online password login restored.'
                : 'PASS: unsupported PRF skips passkey setup and offers PIN; logout and offline unlock work.'));
        }
    } finally {
        await browser.close();
        runPhpJson(fixtureScript, { cleanup: fixture.id, name: fixture.name, memberId: fixture.memberId, email: fixture.email });
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
