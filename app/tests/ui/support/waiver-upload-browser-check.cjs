/** Synthetic native upload → PDF → authorized download, retaining the originating detail tab. */
const assert = require('node:assert/strict');
const { chromium, firefox, webkit, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');
const { getUiTestEnvironment } = require('./test-environment.cjs');

const fixtureScript = String.raw`
require 'vendor/autoload.php'; require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if ($tenant?->slug !== 'kmp' || !in_array($tenant->dbServer, ['db', 'postgres', '127.0.0.1', 'localhost'], true)) {
    throw new RuntimeException('Local synthetic tenant required.');
}
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
echo json_encode($manager->withTenant($tenant, function () use ($input) {
    $l = \Cake\ORM\TableRegistry::getTableLocator();
    $gatherings = $l->get('Gatherings');
    $waivers = $l->get('Waivers.GatheringWaivers');
    if (isset($input['id'])) {
        $row = $gatherings->find()->where(['id' => $input['id'], 'name' => $input['name']])->firstOrFail();
        $records = $waivers->find()->where(['gathering_id' => $row->id])->all()->toList();
        if (!empty($input['inspect'])) {
            return ['count' => count($records), 'waiverId' => $records[0]->id ?? null];
        }
        $documents = array_filter(array_column($records, 'document_id'));
        $waivers->deleteManyOrFail($records);
        foreach ($documents as $id) {
            $result = (new \App\Services\DocumentService())->deleteDocument((int)$id);
            if (!$result->success) throw new RuntimeException('Synthetic document cleanup failed.');
        }
        $gatherings->deleteOrFail($row);
        return ['cleaned' => true];
    }
    $admin = $l->get('Members')->find()->where(['email_address' => 'admin@amp.ansteorra.org'])->firstOrFail();
    $required = $l->get('Waivers.GatheringActivityWaivers')->find()->firstOrFail();
    $type = $l->get('GatheringTypes')->find()->firstOrFail();
    $row = $gatherings->saveOrFail($gatherings->newEntity([
        'name' => 'Native upload ' . \Cake\Utility\Text::uuid(), 'branch_id' => $admin->branch_id,
        'gathering_type_id' => $type->id, 'timezone' => 'UTC', 'published' => true, 'created_by' => $admin->id,
        'start_date' => \Cake\I18n\DateTime::now()->addDays(2),
        'end_date' => \Cake\I18n\DateTime::now()->addDays(3),
    ]));
    $links = $l->get('GatheringsGatheringActivities');
    $conditions = ['gathering_id' => $row->id, 'gathering_activity_id' => $required->gathering_activity_id];
    if (!$links->exists($conditions)) $links->saveOrFail($links->newEntity($conditions + ['sort_order' => 0]));
    return ['id' => $row->id, 'publicId' => $row->public_id, 'name' => $row->name, 'typeId' => $required->waiver_type_id];
}), JSON_THROW_ON_ERROR);
`;

(async () => {
    const fixture = runPhpJson(fixtureScript);
    const engine = process.env.PLAYWRIGHT_BROWSER || 'chromium';
    let browser;
    try {
        browser = await ({ chromium, firefox, webkit })[engine].launch({ headless: true });
        const context = await browser.newContext({ baseURL: getUiTestEnvironment().baseUrl,
            reducedMotion: 'reduce', extraHTTPHeaders: { 'X-KMP-E2E': '1' } });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await loginAs(page, process.env.PLAYWRIGHT_ADMIN_EMAIL || 'admin@amp.ansteorra.org');
        await page.goto(`/gatherings/view/${fixture.publicId}?tab=gathering-waivers&search=retained`);
        const upload = page.locator('a[href*="/waivers/gathering-waivers/upload"]').first();
        await expect(upload).toBeVisible();
        await page.evaluate(() => { window.__nativeUploadMarker = true; });
        await upload.click();
        const wizard = page.locator('[data-controller="waiver-upload-wizard"]');
        await expect(wizard).toBeVisible();
        await expect(page.locator('meta[name="kmp-turbo-enabled"]')).toHaveAttribute('content', 'false');
        assert.equal(await page.evaluate(() => window.__nativeUploadMarker), undefined);
        const returnUrl = await wizard.getAttribute('data-waiver-upload-wizard-return-url-value');
        assert.equal(new URL(returnUrl, page.url()).searchParams.get('tab'), 'gathering-waivers');
        assert.equal(new URL(returnUrl, page.url()).searchParams.get('search'), 'retained');
        const scan = async () => {
            for (const viewport of [{ width: 1280, height: 740 }, { width: 320, height: 640 }, { width: 1280, height: 500 }]) {
                await page.setViewportSize(viewport);
                const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
                assert.deepEqual(result.violations.map(rule => ({ id: rule.id, targets: rule.nodes.map(node => node.target) })), [],
                    `Native upload at ${viewport.width}x${viewport.height}`);
            }
        };
        await scan();
        await wizard.locator(`input[name="waiver_type"][value="${fixture.typeId}"]`).check();
        await wizard.locator('[data-waiver-upload-wizard-target="nextButton"]').click();
        const png = await page.evaluate(() => {
            const canvas = document.createElement('canvas'); canvas.width = 100; canvas.height = 140;
            const drawing = canvas.getContext('2d'); drawing.fillStyle = 'white'; drawing.fillRect(0, 0, 100, 140);
            drawing.fillStyle = 'black'; drawing.fillText('Synthetic waiver', 5, 20);
            return canvas.toDataURL('image/png').split(',')[1];
        });
        await wizard.getByLabel('Waiver page files', { exact: true }).setInputFiles({
            name: 'synthetic-waiver.png', mimeType: 'image/png', buffer: Buffer.from(png, 'base64'),
        });
        await expect(wizard.locator('[data-waiver-upload-wizard-target="pagesPreview"] img')).toHaveCount(1);
        await scan();
        await wizard.locator('[data-waiver-upload-wizard-target="nextButton"]').click();
        await expect(wizard.locator('[data-waiver-upload-wizard-target="reviewPageCount"]')).toHaveText('1');
        await wizard.locator('[data-waiver-upload-wizard-target="notesField"]').fill(fixture.name);
        await scan();
        const posted = page.waitForResponse(response => response.request().method() === 'POST'
            && new URL(response.url()).pathname === '/waivers/gathering-waivers/upload');
        await wizard.locator('[data-waiver-upload-wizard-target="submitButton"]').click();
        const response = await posted;
        assert.equal(response.status(), 200);
        assert.equal((await response.json()).success, true);
        await expect(page).toHaveURL(new URL(returnUrl, getUiTestEnvironment().baseUrl).href);
        await expect(page.locator('#nav-gathering-waivers-tab')).toHaveAttribute('aria-selected', 'true');
        const saved = runPhpJson(fixtureScript, { ...fixture, inspect: true });
        assert.equal(saved.count, 1);
        const pdf = await page.request.get(`/waivers/gathering-waivers/download/${saved.waiverId}`);
        assert.equal(pdf.status(), 200);
        assert.match(pdf.headers()['content-type'], /application\/pdf/);
        assert.equal((await pdf.body()).subarray(0, 5).toString(), '%PDF-');
        assert.deepEqual(errors, []);
        console.log(`PASS ${engine}: native image upload produced one PDF, authorized download works, originating tab/query retained, three wizard states pass accessibility checks.`);
    } finally {
        await browser?.close();
        runPhpJson(fixtureScript, fixture);
    }
})().catch(error => { console.error(error); process.exit(1); });
