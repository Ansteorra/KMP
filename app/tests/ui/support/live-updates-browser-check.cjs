/** Real HTTP mutation → committed outbox → Redis → SSE → authorized browser read. */
const assert = require('node:assert/strict');
const { randomUUID } = require('node:crypto');
const { chromium, firefox, webkit, expect } = require('@playwright/test');
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');
const { getUiTestEnvironment } = require('./test-environment.cjs');

const fixtureScript = String.raw`
require 'vendor/autoload.php'; require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
$host = $input['host'];
if (!in_array($host, ['kmp.localhost', 'kmp2.localhost'], true)) throw new RuntimeException('Local tenants required.');
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve($host);
if (!$tenant || !in_array($tenant->dbServer, ['db', 'postgres', '127.0.0.1', 'localhost'], true)) {
    throw new RuntimeException('Local database required.');
}
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
echo json_encode($manager->withTenant($tenant, function () use ($input) {
    $locator = \Cake\ORM\TableRegistry::getTableLocator();
    $email = $input['host'] === 'kmp2.localhost' ? 'admin@amp2demo.com' : 'admin@amp.ansteorra.org';
    $member = $locator->get('Members')->find()->where(['email_address' => $email])->firstOrFail();
    if (isset($input['cleanup'])) {
        $locator->get('Notes')->deleteAll(['entity_type' => 'Members', 'entity_id' => $member->id, 'subject' => $input['subject']]);
    }
    return ['memberId' => $member->id];
}), JSON_THROW_ON_ERROR);
`;

(async () => {
    const engine = process.env.PLAYWRIGHT_BROWSER || 'chromium';
    const browser = await ({ chromium, firefox, webkit })[engine].launch({ headless: true });
    const baseURL = getUiTestEnvironment().baseUrl;
    assert.equal(new URL(baseURL).hostname, 'kmp.localhost', 'Use the isolated local live-enabled web process.');
    const tenantTwo = new URL(baseURL); tenantTwo.hostname = 'kmp2.localhost';
    const subject = `Live check ${randomUUID()}`;
    const fixture = runPhpJson(fixtureScript, { host: 'kmp.localhost' });
    const otherFixture = runPhpJson(fixtureScript, { host: 'kmp2.localhost' });
    const contexts = await Promise.all([baseURL, baseURL, tenantTwo.origin].map(url => browser.newContext({
        baseURL: url, reducedMotion: 'reduce',
    })));
    for (const [index, context] of contexts.entries()) {
        const origin = index === 2 ? tenantTwo.origin : new URL(baseURL).origin;
        // Match the normal UAT contract without forwarding its DebugKit switch to the gateway.
        await context.route(`${origin}/**`, route => route.continue({
            headers: { ...route.request().headers(), 'X-KMP-E2E': '1' },
        }));
    }
    const [writer, observer, foreign] = await Promise.all(contexts.map(context => context.newPage()));
    const errors = [];
    [writer, observer, foreign].forEach(page => page.on('pageerror', error => errors.push(error.message)));
    [writer, observer, foreign].forEach(page => {
        page.on('console', message => {
            if (message.type() === 'error' && /Content Security|CORS|Access-Control|fetch/i.test(message.text())) {
                console.error(message.text());
            }
        });
        page.on('response', response => {
            if (new URL(response.url()).pathname === '/live/subscriptions') console.log('Subscription', response.status());
        });
    });
    try {
        for (const page of [writer, observer]) await loginAs(page, 'admin@amp.ansteorra.org');
        console.log('Two tenant-one sessions authenticated');
        await loginAs(foreign, 'admin@amp2demo.com');
        console.log('Tenant-two session authenticated');
        await writer.goto(`/members/view/${fixture.memberId}?tab=notes`);
        await observer.goto(`/members/view/${fixture.memberId}?tab=notes&search=retained#place`);
        await foreign.goto(`/members/view/${otherFixture.memberId}?tab=notes`);
        for (const page of [writer, observer, foreign]) {
            await expect(page.locator('#kmp-live-status')).toHaveText('Live updates connected.');
            await expect(page.locator('[data-notes-target="list"]')).toBeVisible();
        }
        await observer.getByRole('button', { name: 'Add a Note', exact: true }).click();
        const draft = observer.locator('#note_new form');
        await draft.getByLabel('Subject', { exact: true }).fill('Keep my unfinished note');
        await draft.getByLabel('Note', { exact: true }).fill('Unsubmitted local work');
        const observerUrl = observer.url();
        await observer.evaluate(() => { window.__liveDocument = document.documentElement; });
        await writer.getByRole('button', { name: 'Add a Note', exact: true }).click();
        const form = writer.locator('#note_new form');
        await form.getByLabel('Subject', { exact: true }).fill(subject);
        await form.getByLabel('Note', { exact: true }).fill('Published only through an authorized read');
        const started = Date.now();
        await form.getByRole('button', { name: 'Submit', exact: true }).click();
        await expect(writer.locator('[data-notes-target="list"]')).toContainText(subject);
        await expect(observer.locator('[data-notes-target="list"]')).toContainText(subject, { timeout: 10000 });
        const elapsed = Date.now() - started;
        await expect(draft.getByLabel('Subject', { exact: true })).toHaveValue('Keep my unfinished note');
        await expect(draft.getByLabel('Note', { exact: true })).toHaveValue('Unsubmitted local work');
        await expect(observer.locator('#nav-notes-tab')).toHaveAttribute('aria-selected', 'true');
        assert.equal(observer.url(), observerUrl);
        assert.equal(await observer.evaluate(() => window.__liveDocument === document.documentElement), true);
        await expect(foreign.locator('[data-notes-target="list"]')).not.toContainText(subject);
        // Exercise the registered broad-refresh path as well as the Notes partial.
        await draft.evaluate(form => form.reset());
        await observer.locator('#nav-gatherings-tab').click();
        const grid = observer.locator('#nav-gatherings turbo-frame[id$="-table"]');
        await expect(grid.locator('table')).toBeVisible();
        const gridId = await grid.getAttribute('id');
        const hidden = observer.locator('#nav-roles turbo-frame[id$="-table"]');
        await expect(hidden).toHaveCount(1);
        const hiddenId = await hidden.getAttribute('id');
        await observer.evaluate(({ gridId, hiddenId }) => {
            window.__liveGrid = document.getElementById(gridId);
            window.__liveHiddenGrid = document.getElementById(hiddenId);
        }, { gridId, hiddenId });
        const keptUrl = observer.url();
        const currentQuery = await grid.evaluate(frame => frame.dataset.gridCurrentSrc || frame.dataset.gridSrc || frame.getAttribute('src'));
        const frameReads = [];
        const track = response => {
            const request = response.request();
            const id = request.headers()['turbo-frame'];
            if (id) frameReads.push({ id, url: new URL(request.url()).pathname + new URL(request.url()).search });
        };
        observer.on('response', track);
        const refreshed = observer.waitForResponse(response => response.request().headers()['turbo-frame'] === gridId);
        await observer.evaluate(async () => {
            const response = await fetch('/ui/refresh', {
                method: 'POST', headers: { 'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ context_url: location.pathname + location.search }),
            });
            if (!response.ok) throw Error('Registered refresh failed');
            const template = document.createElement('template'); template.innerHTML = await response.text();
            document.body.append(template.content);
        });
        assert.equal((await refreshed).status(), 200);
        await expect(grid).not.toHaveAttribute('aria-busy', 'true');
        await expect(hidden).toHaveAttribute('data-kmp-stale-screen', 'true');
        assert.equal(observer.url(), keptUrl);
        assert.equal(await observer.evaluate(() => window.__liveGrid === document.getElementById(window.__liveGrid.id)), true);
        assert.equal(frameReads.filter(read => read.id === gridId).length, 1);
        assert.equal(frameReads.find(read => read.id === gridId).url, currentQuery);
        assert.equal(frameReads.filter(read => read.id === hiddenId).length, 0);
        const hiddenRefreshed = observer.waitForResponse(response => response.request().headers()['turbo-frame'] === hiddenId);
        await observer.locator('#nav-roles-tab').click();
        assert.equal((await hiddenRefreshed).status(), 200);
        await expect(hidden).not.toHaveAttribute('data-kmp-stale-screen');
        assert.equal(await observer.evaluate(() => window.__liveHiddenGrid === document.getElementById(window.__liveHiddenGrid.id)), true);
        observer.off('response', track);
        assert.deepEqual(errors, []);
        console.log(`PASS ${engine}: committed Note delivered in ${elapsed}ms; draft/tab/query retained; tenant isolated; broad refresh retains frames and refreshes hidden lists on open`);
    } catch (error) {
        console.error('Browser errors:', errors);
        for (const [index, page] of [writer, observer, foreign].entries()) {
            console.error(`Session ${index}: ${page.url()}`);
            console.error(await page.evaluate(() => [...document.forms].map(form => ({
                action: form.action, valid: form.checkValidity(),
                invalidFields: [...form.elements].filter(field => field.willValidate && !field.validity.valid).map(field => field.name),
            }))));
            await page.screenshot({ path: `/tmp/kmp-live-session-${index}.png` });
        }
        throw error;
    } finally {
        await browser.close();
        runPhpJson(fixtureScript, { host: 'kmp.localhost', cleanup: true, subject });
    }
})().catch(error => { console.error(error); process.exit(1); });
