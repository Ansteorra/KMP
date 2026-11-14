/** Local acceptance with disposable agenda fixtures; preserves existing development data. */
const fs = require('node:fs');
const { chromium, expect } = require('@playwright/test');
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');
const fixturePhp = String.raw`
require 'vendor/autoload.php'; require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if (!$tenant || $tenant->slug !== 'kmp'
    || !in_array($tenant->dbServer, ['db', 'postgres', 'localhost', '127.0.0.1'], true)) {
    throw new \RuntimeException('Agenda acceptance requires the local kmp tenant.');
}
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
echo json_encode($manager->withTenant($tenant, function () use ($input) {
    $l = \Cake\ORM\TableRegistry::getTableLocator();
    if (isset($input['cleanup'])) {
        $f = $input['cleanup'];
        $g = $l->get('Gatherings')->find()->where(['id' => $f['gatheringId'], 'name' => $f['name']])->firstOrFail();
        $l->get('Awards.CourtAgendas')->deleteAll(['gathering_id' => $g->id]);
        $l->get('Awards.Bestowals')->deleteAll(['gathering_id' => $g->id]);
        $l->get('Gatherings')->deleteOrFail($g, ['purge' => true]);
        $l->get('Awards.AwardGatheringActivities')->deleteAll(['gathering_activity_id' => $f['activityId']]);
        $l->get('GatheringActivities')->deleteAll(['id' => $f['activityId']]);
        return ['cleaned' => true];
    }
    return $l->get('Gatherings')->getConnection()->transactional(function () use ($l) {
        $admin = $l->get('Members')->find()->where(['email_address' => 'admin@amp.ansteorra.org'])->firstOrFail();
        $type = $l->get('GatheringTypes')->find()->firstOrFail();
        $start = new \Cake\I18n\DateTime('+14 days 15:00:00');
        $name = 'Agenda Acceptance ' . bin2hex(random_bytes(6));
        $g = $l->get('Gatherings')->saveOrFail($l->get('Gatherings')->newEntity([
            'name' => $name, 'branch_id' => $admin->branch_id, 'gathering_type_id' => $type->id,
            'start_date' => $start, 'end_date' => $start->addHours(8), 'timezone' => 'America/Chicago',
            'location' => 'Synthetic Hall', 'created_by' => $admin->id,
        ]));
        $award = $l->get('Awards.Awards')->find()->where(['branch_id IS NOT' => null])->firstOrFail();
        $activity = $l->get('GatheringActivities')->saveOrFail($l->get('GatheringActivities')->newEntity([
            'name' => $name, 'activity_type' => 'Court',
        ]));
        $l->get('Awards.AwardGatheringActivities')->saveOrFail($l->get('Awards.AwardGatheringActivities')->newEntity([
            'award_id' => $award->id, 'gathering_activity_id' => $activity->id,
        ]));
        $schedule = $l->get('GatheringScheduledActivities');
        foreach (['First court', 'Second court'] as $index => $title) {
            $schedule->saveOrFail($schedule->newEntity([
                'gathering_id' => $g->id, 'gathering_activity_id' => $activity->id,
                'display_title' => $title, 'is_other' => false, 'has_end_time' => true,
                'start_datetime' => $start->addHours($index * 2), 'end_datetime' => $start->addHours($index * 2 + 1),
                'created_by' => $admin->id,
            ]));
        }
        $service = new \Awards\Services\CourtAgendaService();
        $agenda = $service->getOrCreateDefaultAgenda((int)$g->id, (int)$admin->id);
        $service->ensureEligibleCourtSegments((int)$agenda->id, (int)$admin->id);
        $segments = $l->get('Awards.CourtAgendaSegments')->find()->where(['court_agenda_id' => $agenda->id])
            ->orderBy(['sort_order' => 'ASC', 'id' => 'ASC'])->all()->toList();
        foreach (['Opening of court', 'Gathering announcement', 'Closing of court'] as $title) {
            $service->addBlock(['court_agenda_segment_id' => $segments[0]->id,
                'title' => $title, 'role' => 'announce', 'estimated_minutes' => 2], (int)$admin->id);
        }
        $l->get('Awards.Bestowals')->saveOrFail($l->get('Awards.Bestowals')->newEntity([
            'member_sca_name' => 'Synthetic Court Recipient', 'award_id' => $award->id,
            'gathering_id' => $g->id, 'roaming_court' => false, 'lifecycle_status' => 'open', 'source' => 'ad_hoc',
        ]));
        return ['gatheringId' => $g->id, 'name' => $name, 'activityId' => $activity->id,
            'firstSegmentId' => $segments[0]->id, 'secondSegmentId' => $segments[1]->id];
    });
}), JSON_THROW_ON_ERROR);`;

(async () => {
    const fixture = runPhpJson(fixturePhp);
    const output = process.env.COURT_AGENDA_OUTPUT || '/tmp/kmp-court-agenda';
    fs.mkdirSync(output, { recursive: true });
    let browser;
    const errors = [];
    try {
        browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
        const context = await browser.newContext({ baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080',
            viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await loginAs(page, process.env.PLAYWRIGHT_ADMIN_EMAIL || 'admin@amp.ansteorra.org');
        const url = `/awards/court-agendas/gathering/${fixture.gatheringId}?segment_id=${fixture.firstSegmentId}`;
        await page.goto(url, { waitUntil: 'networkidle' });
        const online = page.getByRole('button', { name: 'Use online only', exact: true });
        if (await online.isVisible()) await online.click();
        const items = page.locator('.court-agenda-item');
        await expect(items).toHaveCount(3);
        const titles = () => items.locator('h3').allTextContents();
        const original = await titles();
        const moving = items.filter({ has: page.getByRole('heading', { name: 'Gathering announcement', exact: true }) });
        const up = moving.getByRole('button', { name: 'Move Gathering announcement up', exact: true });
        const down = moving.getByRole('button', { name: 'Move Gathering announcement down', exact: true });
        await up.press('Enter');
        await expect(up).toBeFocused();
        await expect(up).toHaveAttribute('aria-disabled', 'true');
        await expect.poll(titles).toEqual([original[1], original[0], original[2]]);
        await down.press('Enter');
        await expect.poll(titles).toEqual(original);
        await down.press('Enter');
        await expect(down).toHaveAttribute('aria-disabled', 'true');
        const finalOrder = await titles();
        await page.reload({ waitUntil: 'networkidle' });
        await expect.poll(titles).toEqual(finalOrder);
        await expect(items.locator('[data-court-agenda-board-target="position"]')).toHaveText(['1.', '2.', '3.']);
        const details = moving.locator('summary');
        await details.press('Enter');
        await expect(moving.getByRole('button', { name: 'Remove from Agenda', exact: true })).toBeVisible();
        await details.press('Enter');
        await expect(moving.getByRole('button', { name: 'Remove from Agenda', exact: true })).not.toBeVisible();
        const edit = moving.getByRole('button', { name: 'Edit Gathering announcement', exact: true });
        const dialog = page.getByRole('dialog', { name: 'Edit agenda item: Gathering announcement', exact: true });
        const openEditor = async () => {
            await Promise.all([
                moving.locator('.modal').evaluate(el => new Promise(resolve => {
                    el.addEventListener('shown.bs.modal', () => resolve(), { once: true });
                })),
                edit.press('Enter'),
            ]);
        };
        await openEditor();
        await expect(dialog).toBeVisible();
        await dialog.getByLabel('Agenda notes', { exact: true }).fill('Remember the visiting herald.');
        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible();
        await expect(edit).toBeFocused();
        await openEditor();
        await dialog.getByRole('button', { name: 'Save item', exact: true }).click();
        await expect(dialog).not.toBeVisible();
        await openEditor();
        await expect(dialog.getByLabel('Agenda notes', { exact: true })).toHaveValue('Remember the visiting herald.');
        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible();
        await page.getByLabel('Court Activities', { exact: true }).selectOption(String(fixture.secondSegmentId));
        await page.getByRole('button', { name: 'Show court', exact: true }).press('Enter');
        await page.waitForLoadState('networkidle');
        await expect(items).toHaveCount(0);
        await expect(page.locator('#selected-court-heading')).toContainText('Second court');
        await page.goto(url, { waitUntil: 'networkidle' });
        await expect(items).toHaveCount(3);
        await expect(page.locator('#court-agenda-tools')).toBeVisible();
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.locator('#court-agenda-tools').screenshot({ path: `${output}/tools.png`, animations: 'disabled' });
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: `${output}/desktop.png`, fullPage: true, animations: 'disabled' });
        for (const width of [390, 320]) {
            await page.setViewportSize({ width, height: 844 });
            await page.evaluate(() => window.scrollTo(0, 0));
            await expect(page.locator('#court-agenda-tools')).not.toBeVisible();
            await expect(page.getByRole('button', { name: 'Add Synthetic Court Recipient to this court', exact: true })).not.toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
            const add = page.getByRole('button', { name: 'Add to court', exact: true });
            await add.press('Enter');
            const drawer = page.getByRole('dialog', { name: 'Add to this court', exact: true });
            await expect(drawer).toBeVisible();
            await expect.poll(() => drawer.evaluate(node => node.contains(document.activeElement))).toBe(true);
            await expect(drawer.getByRole('heading', { name: 'Add Eligible Bestowals', exact: true })).toBeVisible();
            await drawer.screenshot({ path: `${output}/drawer-${width}.png`, animations: 'disabled' });
            await page.keyboard.press('Escape');
            await expect(drawer).not.toBeVisible();
            await expect(add).toBeFocused();
            await page.screenshot({ path: `${output}/mobile-${width}.png`, fullPage: true, animations: 'disabled' });
        }
        await page.getByRole('button', { name: 'Add to court', exact: true }).click();
        await page.getByRole('button', { name: 'Add Synthetic Court Recipient to this court', exact: true }).click();
        await expect(items).toHaveCount(4);
        await page.getByRole('button', { name: 'Add to court', exact: true }).click();
        const tools = page.locator('#court-agenda-tools');
        await tools.getByLabel('Title', { exact: true }).fill('Mobile announcement');
        await tools.getByRole('button', { name: 'Add', exact: true }).click();
        await expect(items).toHaveCount(5);
        await expect(items.last().getByRole('heading')).toHaveText('Mobile announcement');
        expect(errors).toEqual([]);
        console.log(JSON.stringify({ desktop: 'passed', widths: [390, 320], reordering: 'persisted',
            editing: 'persisted', adding: 'passed', keyboardAndFocus: 'passed', screenshots: output }));
    } finally {
        await browser?.close();
        runPhpJson(fixturePhp, { cleanup: fixture });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
