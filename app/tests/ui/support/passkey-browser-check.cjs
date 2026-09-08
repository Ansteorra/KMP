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
        await loginAs(page, fixture.email);
        await page.goto('/passkeys');
        await page.getByLabel('Passkey name').fill(name);
        await page.getByLabel('Confirm your KMP password').fill('TestPassword');
        await page.getByRole('button', { name: 'Continue', exact: true }).click();
        await page.getByRole('button', { name: 'Create passkey', exact: true }).waitFor({ state: 'visible' });
        assert.equal(await page.getByLabel('Confirm your KMP password').inputValue(), '');
        assert.equal(await page.evaluate(() => document.activeElement?.textContent), 'Create passkey');
        await Promise.all([
            page.waitForURL('**/passkeys'),
            page.getByRole('button', { name: 'Create passkey', exact: true }).click()
        ]);
        await page.getByRole('link', { name: `Remove ${name}`, exact: true }).waitFor();
        await page.screenshot({ path: '/tmp/kmp-passkey-mobile.png', fullPage: true });
        const overflow = await page.evaluate(() => [...document.querySelectorAll('body *')].filter(el => el.getBoundingClientRect().right > innerWidth + 1).slice(0, 8).map(el => ({ tag: el.tagName, cls: el.className, right: el.getBoundingClientRect().right })));
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, JSON.stringify(overflow));
        const cookiesBefore = await context.cookies();
        await page.goto('/members/logout');
        await page.getByRole('button', { name: 'Sign in with a passkey', exact: true }).click();
        await page.waitForURL(url => !url.pathname.endsWith('/login'), { timeout: 15000 });
        await page.goto('/passkeys');
        await page.getByRole('link', { name: `Remove ${name}`, exact: true }).waitFor();
        const cookiesAfter = await context.cookies();
        assert.ok(cookiesAfter.some(cookie => cookie.httpOnly && cookiesBefore.some(old => old.name === cookie.name && old.value !== cookie.value)), 'Authentication session cookie must rotate');
        await page.getByRole('link', { name: `Remove ${name}`, exact: true }).click();
        await page.getByRole('button', { name: 'Confirm', exact: true }).click();
        await page.getByRole('link', { name: `Remove ${name}`, exact: true }).waitFor({ state: 'hidden' });
        await page.goto('/members/logout');
        await page.getByRole('button', { name: 'Sign in with a passkey', exact: true }).click();
        await page.getByText('Passkey verification failed. Retry or sign in with your password.', { exact: true }).waitFor();
        assert.match(new URL(page.url()).pathname, /members\/login/);
        assert.deepEqual(errors, []);
        console.log('PASS: password-authorized passkey enrollment, real server signature verification, login after logout, removal denial, focus and mobile reflow.');
    } finally {
        await browser.close();
        runPhpJson(fixtureScript, { cleanup: fixture.id, email: fixture.email });
    }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
