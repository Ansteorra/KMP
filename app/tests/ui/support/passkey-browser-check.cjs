/** Real server verification using a virtual authenticator and synthetic local accounts. */
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');
const origin = process.env.PASSKEY_TEST_ORIGIN || 'https://kmp.localhost:9443';
if (!/^https:\/\/kmp\.localhost:\d+$/.test(origin)) throw new Error('Local synthetic tenant required.');

const fixtureScript = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true);
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if (!$tenant || $tenant->slug !== 'kmp' || !in_array($tenant->dbServer, ['db', 'postgres', '127.0.0.1', 'localhost'], true)) {
    throw new \RuntimeException('Local synthetic tenant required.');
}
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
$result = $manager->withTenant($tenant, function () use ($input) {
    $members = \Cake\ORM\TableRegistry::getTableLocator()->get('Members');
    if (isset($input['cleanup'])) {
        $members->getConnection()->execute('DELETE FROM members WHERE id = :id AND email_address = :email',
            ['id' => $input['cleanup'], 'email' => $input['email']]);
        return ['cleaned' => true];
    }
    $branch = \Cake\ORM\TableRegistry::getTableLocator()->get('Branches')->find()->firstOrFail();
    $member = $members->newEntity([
        'email_address' => 'passkey-' . bin2hex(random_bytes(6)) . '@example.invalid',
        'first_name' => 'Synthetic', 'last_name' => 'Passkey', 'sca_name' => 'Synthetic Passkey Test',
        'password' => 'TestPassword', 'status' => \App\Model\Entity\Member::STATUS_ACTIVE,
        'branch_id' => $branch->id, 'birth_month' => 1, 'birth_year' => 1990,
        'street_address' => '', 'city' => '', 'state' => '', 'zip' => '', 'phone_number' => '',
    ]);
    $members->saveOrFail($member);
    return ['id' => $member->id, 'email' => $member->email_address];
});
echo json_encode($result, JSON_THROW_ON_ERROR);
`;

(async () => {
    const fixture = runPhpJson(fixtureScript);
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const context = await browser.newContext({ baseURL: origin, ignoreHTTPSErrors: true, viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_0 like Mac OS X) AppleWebKit/605.1.15 Version/26.0 Mobile/15E148 Safari/604.1' });
    // Exercise explicit and conditional login separately: a virtual device can select immediately.
    await context.addInitScript(() => {
        if (window.PublicKeyCredential) {
            const available = PublicKeyCredential.isConditionalMediationAvailable?.bind(PublicKeyCredential);
            PublicKeyCredential.isConditionalMediationAvailable = async () =>
                !!window.testConditionalPasskey && !!await available?.();
        }
    });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const name = `Synthetic browser passkey ${Date.now()}`;
    try {
        const cdp = await context.newCDPSession(page);
        await cdp.send('WebAuthn.enable');
        await cdp.send('WebAuthn.addVirtualAuthenticator', { options: {
            protocol: 'ctap2', ctap2Version: 'ctap2_1', transport: 'internal', hasResidentKey: true,
            hasUserVerification: true, isUserVerified: true, automaticPresenceSimulation: true
        } });
        const dialog = page.getByRole('dialog', { name: 'Security', exact: true });
        const openSecurity = async target => {
            const button = target.getByRole('button', { name: 'Security', exact: true });
            await button.click();
            await target.getByRole('dialog', { name: 'Security', exact: true }).getByRole('heading', { name: 'Your account security' }).waitFor();
        };
        const openPasskeys = async () => {
            await openSecurity(page);
            await dialog.getByRole('button', { name: /^Manage passkeys/ }).click();
        };
        console.log('Security acceptance: password login');
        await loginAs(page, fixture.email);
        const [cardResponse] = await Promise.all([
            page.waitForResponse(response => new URL(response.url()).pathname === '/members/view-mobile-card-json'),
            page.goto('/members/view-mobile-card')
        ]);
        assert.equal(cardResponse.status(), 200);
        const card = await cardResponse.json();
        assert.ok(card.member?.branch, 'Mobile card must render authenticated data');
        const mobileUrl = page.url();
        await page.reload();
        assert.equal(page.url(), mobileUrl, 'Mobile refresh must retain the authenticated session');
        await openPasskeys();
        await dialog.getByRole('heading', { name: 'An easier way to sign in' }).waitFor();
        await dialog.getByRole('button', { name: 'Get started', exact: true }).click();
        await dialog.getByLabel('Your KMP password', { exact: true }).fill('discard-this');
        await page.keyboard.press('Escape');
        await dialog.waitFor({ state: 'hidden' });
        await page.waitForFunction(() => document.activeElement?.textContent === 'Security');
        await openPasskeys();
        await dialog.getByRole('button', { name: 'Get started', exact: true }).click();
        assert.equal(await dialog.getByLabel('Your KMP password', { exact: true }).inputValue(), '');
        await page.screenshot({ path: '/tmp/kmp-passkey-wizard-mobile.png', fullPage: true });
        await dialog.getByLabel('Name this passkey').fill(name);
        await dialog.getByLabel('Your KMP password', { exact: true }).fill('TestPassword');
        await dialog.getByRole('button', { name: 'Continue', exact: true }).click();
        await dialog.getByRole('button', { name: 'Create passkey', exact: true }).waitFor({ state: 'visible' });
        assert.equal(await dialog.getByLabel('Your KMP password', { exact: true }).inputValue(), '');
        assert.equal(await page.evaluate(() => document.activeElement?.textContent), 'Save your passkey');
        await dialog.getByRole('button', { name: 'Create passkey', exact: true }).click();
        await dialog.getByRole('heading', { name: 'Your passkey is ready', exact: true }).waitFor();
        await dialog.getByRole('button', { name: 'View my passkeys', exact: true }).click();
        await dialog.getByRole('button', { name: `Remove ${name}`, exact: true }).waitFor();
        assert.equal(page.url(), mobileUrl);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        await dialog.getByRole('button', { name: 'Done', exact: true }).click();
        const cookiesBefore = await context.cookies();
        await page.goto('/members/logout');
        await page.evaluate(() => {
            window.testConditionalPasskey = true;
            document.querySelector('#email-address').blur();
            document.querySelector('#email-address').focus();
        });
        await page.waitForURL(url => !url.pathname.endsWith('/login'), { timeout: 15000 });
        assert.ok(!page.url().endsWith('/login'), 'Conditional autofill must verify the signature and sign in');
        await page.goto('/members/logout');
        await page.locator('#email-address').fill(fixture.email);
        await page.getByRole('button', { name: 'Continue', exact: true }).click();
        await page.getByRole('button', { name: 'Use a passkey', exact: true }).click();
        await page.waitForURL(url => !url.pathname.endsWith('/login'), { timeout: 15000 });
        await openPasskeys();
        await dialog.getByRole('button', { name: `Remove ${name}`, exact: true }).waitFor();
        const cookiesAfter = await context.cookies();
        assert.ok(cookiesAfter.some(cookie => cookie.httpOnly && cookiesBefore.some(old => old.name === cookie.name && old.value !== cookie.value)), 'Authentication session cookie must rotate');
        await dialog.getByRole('button', { name: `Remove ${name}`, exact: true }).click();
        await dialog.getByRole('button', { name: 'Remove passkey', exact: true }).click();
        await dialog.getByRole('heading', { name: 'Passkey removed', exact: true }).waitFor();
        await page.goto('/members/logout');
        await page.locator('#email-address').fill(fixture.email);
        await page.getByRole('button', { name: 'Continue', exact: true }).click();
        await page.getByRole('button', { name: 'Use a passkey', exact: true }).click();
        await page.getByText('Passkey verification failed. Retry or sign in with your password.', { exact: true }).waitFor();
        console.log('Security acceptance: password login');
        await loginAs(page, fixture.email);
        await page.goto('/members/view-mobile-card');
        await openSecurity(page);
        await page.screenshot({ path: '/tmp/kmp-security-hub-mobile.png', fullPage: true });
        await dialog.getByRole('button', { name: /^Sign out all devices/ }).click();
        await dialog.getByText('All passkeys for this account will stop working.', { exact: true }).waitFor();
        await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
        await dialog.getByRole('heading', { name: 'Your account security' }).waitFor();
        await dialog.getByRole('button', { name: /^Change password/ }).click();
        await dialog.getByRole('button', { name: 'Continue', exact: true }).click();
        await dialog.getByLabel('New KMP password', { exact: true }).fill('SyntheticChangedPassword');
        await dialog.getByLabel('Repeat new password', { exact: true }).fill('DifferentPassword');
        await dialog.getByRole('button', { name: 'Save password', exact: true }).click();
        await dialog.getByText('The passwords do not match. Enter the same new password in both boxes.', { exact: true }).waitFor();
        await dialog.getByLabel('Repeat new password', { exact: true }).fill('SyntheticChangedPassword');
        await dialog.getByRole('button', { name: 'Save password', exact: true }).click();
        await page.waitForURL('**/members/login');
        console.log('Security acceptance: login with changed password');
        await loginAs(page, fixture.email, 'SyntheticChangedPassword');
        await page.goto('/members/view-mobile-card');
        const desktop = await browser.newContext({ baseURL: origin, ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } });
        try {
            const desk = await desktop.newPage();
            console.log('Security acceptance: desktop login');
            await loginAs(desk, fixture.email, 'SyntheticChangedPassword');
            await desk.goto('/members/profile');
            const profileUrl = desk.url();
            assert.equal(await desk.getByRole('button', { name: 'Security', exact: true }).count(), 1);
            await openSecurity(desk);
            const settings = desk.getByRole('dialog', { name: 'Security', exact: true });
            assert.equal(desk.url(), profileUrl);
            await desk.screenshot({ path: '/tmp/kmp-security-hub-desktop.png', fullPage: true });
            await settings.getByRole('button', { name: 'Done', exact: true }).click();
            await desk.getByRole('link', { name: 'Sign out', exact: true }).click();
            await desk.waitForURL('**/members/login');
        } finally { await desktop.close(); }
        const basic = await browser.newContext({ baseURL: origin, ignoreHTTPSErrors: true, javaScriptEnabled: false });
        try {
            const login = await basic.newPage();
            await login.goto('/members/login');
            assert.equal(await login.locator('#password').isVisible(), true);
            assert.equal(await login.getByRole('button', { name: 'Continue', exact: true }).isVisible(), false);
            await login.locator('#email-address').fill(fixture.email);
            await login.locator('#password').fill('SyntheticChangedPassword');
            await login.getByRole('button', { name: 'Sign in', exact: true }).click();
            await login.getByRole('link', { name: 'Sign out', exact: true }).waitFor();
        } finally { await basic.close(); }
        await openSecurity(page);
        await dialog.getByRole('button', { name: /^Sign out all devices/ }).click();
        await dialog.getByRole('button', { name: 'Sign out all devices', exact: true }).click();
        await page.waitForURL('**/members/login');
        assert.deepEqual(errors, []);
        console.log('PASS: password-authorized passkey enrollment, conditional autofill and explicit login with real server signature verification, login after logout, removal denial, mobile and desktop modals, password change, both sign-out choices, no-JavaScript password login, focus and mobile reflow.');
    } finally {
        await browser.close();
        runPhpJson(fixtureScript, { cleanup: fixture.id, email: fixture.email });
    }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
