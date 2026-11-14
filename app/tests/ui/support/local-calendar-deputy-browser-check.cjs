/** Real scoped gathering forms and waiver storage using an unwarranted local deputy. */
const assert = require('node:assert/strict');
const { chromium, firefox, webkit, expect } = require('@playwright/test');
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');
const { getUiTestEnvironment } = require('./test-environment.cjs');

const fixtureScript = String.raw`
require 'vendor/autoload.php'; require 'config/bootstrap.php';
\Cake\Cache\Cache::disable();
$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if ($tenant?->slug !== 'kmp' || !in_array($tenant->dbServer, ['db', 'postgres', '127.0.0.1', 'localhost'], true)) {
    throw new RuntimeException('Local synthetic tenant required.');
}
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
echo json_encode($manager->withTenant($tenant, function () use ($input) {
    $l = \Cake\ORM\TableRegistry::getTableLocator();
    $members = $l->get('Members'); $gatherings = $l->get('Gatherings');
    if (isset($input['memberId'])) {
        $member = $members->find()->where(['id' => $input['memberId'], 'email_address' => $input['email']])->firstOrFail();
        $events = $gatherings->find()->where(['created_by' => $member->id])->all()->toList();
        if (($input['action'] ?? '') === 'attach') {
            $event = $gatherings->find()->where(['id' => $input['eventId'], 'created_by' => $member->id])->firstOrFail();
            $required = $l->get('Waivers.GatheringActivityWaivers')->find()->firstOrFail();
            $links = $l->get('GatheringsGatheringActivities');
            $links->saveOrFail($links->newEntity(['gathering_id' => $event->id,
                'gathering_activity_id' => $required->gathering_activity_id, 'sort_order' => 0]));
            return ['typeId' => $required->waiver_type_id];
        }
        $waivers = $l->get('Waivers.GatheringWaivers');
        $records = $events ? $waivers->find()->where(['gathering_id IN' => array_column($events, 'id')])->all()->toList() : [];
        if (($input['action'] ?? '') === 'inspect') {
            return ['waivers' => count($records), 'uploadedBy' => $records[0]->created_by ?? null,
                'documentId' => $records[0]->document_id ?? null];
        }
        foreach ($records as $record) {
            $document = $record->document_id;
            $waivers->deleteOrFail($record, ['purge' => true]);
            if ($document && !(new \App\Services\DocumentService())->deleteDocument((int)$document)->success) {
                throw new RuntimeException('Synthetic document cleanup failed.');
            }
        }
        $events[] = $gatherings->find()->where(['id' => $input['outsideId'],
            'name' => $input['outsideName']])->firstOrFail();
        foreach ($events as $event) {
            foreach (['GatheringsGatheringActivities', 'GatheringStaff'] as $alias) {
                $l->get($alias)->deleteAll(['gathering_id' => $event->id]);
            }
            $gatherings->deleteOrFail($event, ['purge' => true]);
        }
        $l->get('MemberRoles')->deleteAll(['member_id' => $member->id]);
        $members->deleteOrFail($member, ['purge' => true]);
        $l->get('GatheringTypes')->deleteOrFail($l->get('GatheringTypes')->get($input['typeId']), ['purge' => true]);
        return ['cleaned' => true];
    }
    return $members->getConnection()->transactional(function () use ($l, $members, $gatherings) {
        require_once 'plugins/Officers/config/Seeds/InitLocalCalendarDeputySeed.php';
        (new \InitLocalCalendarDeputySeed())->run();
        $tag = bin2hex(random_bytes(8)); $now = \Cake\I18n\DateTime::now();
        $branch = $l->get('Branches')->find()->where(['type' => 'Local Group'])->orderByAsc('id')->firstOrFail();
        $root = $l->get('Branches')->find()->where(['type' => 'Kingdom'])->firstOrFail();
        $password = bin2hex(random_bytes(24));
        $member = $members->saveOrFail($members->newEntity([
            'sca_name' => 'Local Calendar ' . $tag, 'first_name' => 'Synthetic', 'last_name' => 'Deputy',
            'email_address' => 'local-calendar.' . $tag . '@example.test', 'password' => $password,
            'branch_id' => $branch->id, 'status' => \App\Model\Entity\Member::STATUS_VERIFIED_MEMBERSHIP,
            'membership_expires_on' => $now->addDays(1),
            'street_address' => null, 'city' => null, 'state' => null, 'zip' => null, 'phone_number' => null,
        ]));
        if ($member->warrantable || $l->get('Warrants')->exists(['member_id' => $member->id])) {
            throw new RuntimeException('Verification requires a member without warrant eligibility or a warrant.');
        }
        $office = $l->get('Officers.Offices')->find()->where(['name' => \InitLocalCalendarDeputySeed::ROLE])->firstOrFail();
        $role = $l->get('MemberRoles')->newEmptyEntity();
        $role->patch(['member_id' => $member->id, 'role_id' => $office->grants_role_id, 'branch_id' => $branch->id,
            'start_on' => $now->subDays(1), 'expires_on' => $now->addHours(2),
            'created_by' => $member->id, 'approver_id' => $member->id], ['guard' => false]);
        $l->get('MemberRoles')->saveOrFail($role);
        $type = $l->get('GatheringTypes')->saveOrFail($l->get('GatheringTypes')->newEntity([
            'name' => 'Local Calendar ' . $tag, 'description' => 'Synthetic empty template', 'clonable' => false,
        ]));
        $outside = $gatherings->saveOrFail($gatherings->newEntity([
            'name' => 'Outside local deputy scope ' . $tag, 'branch_id' => $root->id,
            'gathering_type_id' => $type->id, 'start_date' => $now->addDays(7), 'end_date' => $now->addDays(8),
            'timezone' => 'UTC', 'created_by' => $members->find()->select(['id'])->orderByAsc('id')->firstOrFail()->id,
        ]));
        return ['memberId' => $member->id, 'email' => $member->email_address, 'password' => $password,
            'branchId' => $branch->id, 'branchName' => $branch->name, 'typeId' => $type->id,
            'outsideId' => $outside->id, 'outsideName' => $outside->name,
            'name' => 'Local Calendar ' . $tag, 'date' => $now->addDays(7)->format('Y-m-d')];
    });
}), JSON_THROW_ON_ERROR);
`;

(async () => {
    const fixture = runPhpJson(fixtureScript);
    const engine = process.env.PLAYWRIGHT_BROWSER || 'chromium';
    let browser;
    try {
        browser = await ({ chromium, firefox, webkit })[engine].launch({ headless: true });
        const context = await browser.newContext({ baseURL: getUiTestEnvironment().baseUrl, reducedMotion: 'reduce' });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await loginAs(page, fixture.email, fixture.password);
        assert.equal((await page.goto('/gatherings/add')).status(), 200);
        await expect(page.locator('input[name="branch_id"]')).toHaveValue(String(fixture.branchId));
        await page.locator('[name="name"]').fill(fixture.name);
        await page.locator('[name="gathering_type_id"]').selectOption(String(fixture.typeId));
        await page.locator('[name="start_date"]').fill(fixture.date + 'T10:00');
        await page.locator('[name="end_date"]').fill(fixture.date + 'T18:00');
        await page.locator('[name="location"]').fill('Synthetic local deputy check');
        await page.getByRole('button', { name: 'Create Gathering', exact: true }).click();
        await expect(page).toHaveURL(/\/gatherings\/view\//);
        await page.waitForLoadState('load');
        const detailUrl = page.url();
        const edit = await page.locator('a[href*="/gatherings/edit/"]').first().getAttribute('href');
        const eventId = Number(edit.match(/\/gatherings\/edit\/(\d+)/)[1]);
        await expect(page.getByText('Publish', { exact: true })).toHaveCount(0);
        await page.goto(edit);
        await page.locator('[name="name"]').fill(fixture.name + ' edited');
        await page.getByRole('button', { name: /Save/ }).click();
        await expect(page).toHaveURL(/\/gatherings\/view\//);
        await page.waitForLoadState('load');
        await expect(page.getByRole('heading').filter({ hasText: fixture.name + ' edited' }).first()).toBeVisible();
        const required = runPhpJson(fixtureScript, { ...fixture, action: 'attach', eventId });
        await page.goto(detailUrl + '?tab=gathering-waivers');
        await page.locator('a[href*="/waivers/gathering-waivers/upload"]').first().click();
        const wizard = page.locator('[data-controller="waiver-upload-wizard"]');
        await expect(wizard).toBeVisible();
        await wizard.locator(`input[name="waiver_type"][value="${required.typeId}"]`).check();
        await wizard.locator('[data-waiver-upload-wizard-target="nextButton"]').click();
        const png = await page.evaluate(() => {
            const canvas = document.createElement('canvas'); canvas.width = 100; canvas.height = 140;
            const drawing = canvas.getContext('2d'); drawing.fillStyle = 'white'; drawing.fillRect(0, 0, 100, 140);
            drawing.fillStyle = 'black'; drawing.fillText('Synthetic waiver', 5, 20);
            return canvas.toDataURL('image/png').split(',')[1];
        });
        await wizard.getByLabel('Waiver page files', { exact: true }).setInputFiles({
            name: 'synthetic-local-waiver.png', mimeType: 'image/png', buffer: Buffer.from(png, 'base64'),
        });
        await expect(wizard.locator('[data-waiver-upload-wizard-target="pagesPreview"] img')).toHaveCount(1);
        await wizard.locator('[data-waiver-upload-wizard-target="nextButton"]').click();
        const posted = page.waitForResponse(response => response.request().method() === 'POST'
            && new URL(response.url()).pathname === '/waivers/gathering-waivers/upload');
        await wizard.locator('[data-waiver-upload-wizard-target="submitButton"]').click();
        assert.equal((await posted).status(), 200);
        await expect(page).toHaveURL(/tab=gathering-waivers/);
        const result = runPhpJson(fixtureScript, { ...fixture, action: 'inspect' });
        assert.equal(result.waivers, 1);
        assert.equal(result.uploadedBy, fixture.memberId);
        assert.ok(result.documentId);
        await page.goto('/waivers/gathering-waivers/mobile-select-gathering');
        await expect(page.locator(`a[href*="gathering_id=${eventId}"]`).first()).toBeVisible();
        await page.goto('/gatherings/edit/' + fixture.outsideId);
        await expect(page.locator('form input[name="name"]')).toHaveCount(0);
        assert.deepEqual(errors, []);
        console.log(`PASS ${engine}: unwarranted Local Calendar Deputy created/edited a scoped event, uploaded a real waiver, accessed mobile uploads, and was denied outside scope and publication.`);
    } finally {
        try { await browser?.close(); }
        finally { assert.equal(runPhpJson(fixtureScript, fixture).cleaned, true); }
    }
})().catch(error => { console.error(error); process.exit(1); });
