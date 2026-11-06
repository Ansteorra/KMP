/** Local mutation acceptance with fixture cleanup; no database reset. */
const assert = require('node:assert/strict');
const { chromium, firefox, webkit, expect } = require('@playwright/test');
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');
const { getUiTestEnvironment } = require('./test-environment.cjs');

const fixtureScript = String.raw`
require 'vendor/autoload.php'; require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if (!$tenant || $tenant->slug !== 'kmp' || !in_array($tenant->dbServer, ['db', 'postgres', '127.0.0.1', 'localhost'], true)) {
    throw new \RuntimeException('Local tenant required.');
}
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
echo json_encode($manager->withTenant($tenant, function () use ($input) {
    $l = \Cake\ORM\TableRegistry::getTableLocator();
    $admin = $l->get('Members')->find()->where(['email_address' => 'admin@amp.ansteorra.org'])->firstOrFail();
    if (isset($input['awardState'])) {
        $row = $l->get('Awards.Bestowals')->get($input['awardState']);
        return ['courtId' => $row->gathering_scheduled_activity_id];
    }
    if (isset($input['attachAward'])) {
        $g = $l->get('Gatherings')->find()->where(['id' => $input['id'], 'name' => $input['name']])->firstOrFail();
        $schedule = $l->get('GatheringScheduledActivities')->find()->where(['id' => $input['activityId'], 'gathering_id' => $g->id])->firstOrFail();
        $award = $l->get('Awards.Awards')->find()->firstOrFail();
        $row = $l->get('Awards.Bestowals')->saveOrFail($l->get('Awards.Bestowals')->newEntity([
            'member_sca_name' => $g->name, 'award_id' => $award->id, 'gathering_id' => $g->id,
            'gathering_scheduled_activity_id' => $schedule->id, 'lifecycle_status' => 'open', 'source' => 'ad_hoc',
        ]));
        return ['id' => $row->id];
    }
    if (isset($input['cleanup'])) {
        $row = $l->get('Gatherings')->find()->where(['id' => $input['id'], 'name' => $input['name']])->firstOrFail();
        $l->get('Notes')->deleteAll(['entity_type' => 'Members', 'entity_id' => $admin->id, 'subject' => $input['name']]);
        $l->get('GatheringAttendances')->deleteAll(['gathering_id' => $row->id]);
        $l->get('Awards.CourtAgendas')->deleteAll(['gathering_id' => $row->id]);
        $l->get('Awards.Bestowals')->deleteAll(['gathering_id' => $row->id]);
        $l->get('GatheringScheduledActivities')->deleteAll(['gathering_id' => $row->id]);
        $l->get('Gatherings')->deleteOrFail($row);
        return ['cleaned' => true];
    }
    $type = $l->get('GatheringTypes')->find()->orderByAsc('id')->firstOrFail();
    $row = $l->get('Gatherings')->saveOrFail($l->get('Gatherings')->newEntity([
        'name' => 'Partial updates ' . \Cake\Utility\Text::uuid(), 'branch_id' => $admin->branch_id,
        'gathering_type_id' => $type->id, 'timezone' => 'UTC', 'published' => true,
        'start_date' => \Cake\I18n\DateTime::now()->addDays(2)->startOfDay(),
        'end_date' => \Cake\I18n\DateTime::now()->addDays(3)->endOfDay(), 'created_by' => $admin->id,
    ]));
    return ['id' => $row->id, 'publicId' => $row->public_id, 'name' => $row->name,
        'memberId' => $admin->id, 'startDate' => $row->start_date->format('Y-m-d')];
}), JSON_THROW_ON_ERROR);
`;

(async () => {
    const fixture = runPhpJson(fixtureScript);
    const engine = process.env.PLAYWRIGHT_BROWSER || 'chromium';
    const browser = await ({ chromium, firefox, webkit })[engine].launch({ headless: true });
    const context = await browser.newContext({ baseURL: getUiTestEnvironment().baseUrl, reducedMotion: 'reduce', extraHTTPHeaders: { 'X-KMP-E2E': '1' } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const markDocument = () => page.evaluate(() => { window.__partialDocument = document.documentElement; });
    const unchanged = async () => assert.equal(await page.evaluate(() => window.__partialDocument === document.documentElement), true);
    const detail = '/gatherings/view/' + fixture.publicId + '?tab=schedule&search=retained#place';
    try {
        await loginAs(page, process.env.PLAYWRIGHT_ADMIN_EMAIL || 'admin@amp.ansteorra.org');
        await page.goto('/members/view/' + fixture.memberId + '?tab=notes');
        await markDocument();
        await page.getByRole('button', { name: 'Add a Note', exact: true }).click();
        const notes = page.locator('form[data-notes-new-form]');
        await notes.getByLabel('Subject', { exact: true }).fill(fixture.name);
        await notes.getByLabel('Note', { exact: true }).fill('Saved without leaving Notes');
        await notes.getByRole('button', { name: 'Submit', exact: true }).click();
        await expect(page.locator('[data-notes-target="list"]')).toContainText(fixture.name);
        await expect(notes.getByLabel('Subject', { exact: true })).toHaveValue('');
        await expect(page.locator('#nav-notes-tab')).toHaveAttribute('aria-selected', 'true');
        await unchanged();

        console.log('Notes saved without navigation');
        await page.goto(detail);
        await markDocument();
        await page.locator('#gathering-rsvp-trigger').click();
        let modal = page.locator('#attendGatheringModal');
        await modal.locator('[name="public_note"]').fill('First partial RSVP');
        await modal.locator('input[type="checkbox"][name="share_with_kingdom"]').check();
        await modal.getByRole('button', { name: 'Register', exact: true }).click();
        await expect(page.locator('#gathering-rsvp-summary')).toContainText('planning to attend');
        await expect(page.locator('#gathering-attendance-count')).toHaveText('1');
        await expect(page.locator('#gathering-overview-content')).toContainText('First partial RSVP');
        await expect(page.locator('#gathering-rsvp-trigger')).toBeFocused();
        await expect(page.locator('#nav-schedule-tab')).toHaveAttribute('aria-selected', 'true');
        assert.equal(new URL(page.url()).searchParams.get('search'), 'retained');
        assert.equal(new URL(page.url()).hash, '#place');
        await unchanged();
        await page.locator('#gathering-rsvp-trigger').click();
        modal = page.locator('#attendGatheringModal');
        await modal.locator('[name="public_note"]').fill('Updated partial RSVP');
        await modal.getByRole('button', { name: 'Update', exact: true }).click();
        await expect(page.locator('#gathering-overview-content')).toContainText('Updated partial RSVP');
        await expect(page.locator('#gathering-attendance-content')).toContainText('Updated partial RSVP');
        await unchanged();

        console.log('RSVP summaries updated');
        await page.locator('#schedule-add-trigger').click();
        const add = page.locator('#addScheduleModal');
        await add.locator('#add-is-other').check();
        await add.getByLabel('Start Date', { exact: true }).fill(fixture.startDate);
        await add.getByLabel('Start Time', { exact: true }).selectOption('12:15');
        await add.getByLabel('Duration', { exact: true }).selectOption('30');
        await add.getByLabel('Display Title', { exact: true }).fill('Partial schedule entry');
        await add.getByLabel('Duration', { exact: true }).evaluate(select => {
            select.add(new Option('Invalid duration', '999')); select.value = '999';
        });
        await add.getByRole('button', { name: 'Add Scheduled Activity', exact: true }).click();
        await expect(add.locator('[role="alert"]').last()).toBeVisible();
        await expect(add.getByLabel('Display Title', { exact: true })).toHaveValue('Partial schedule entry');
        await expect(page.locator('#gathering-schedule-count')).toHaveText('0');
        await add.getByLabel('Duration', { exact: true }).selectOption('30');
        await add.getByRole('button', { name: 'Add Scheduled Activity', exact: true }).click();
        await expect(page.locator('#gathering-schedule-count')).toHaveText('1');
        await expect(page.locator('#gathering-overview-content')).toContainText('Partial schedule entry');
        await expect(page.locator('#gathering-schedule-content')).toContainText('Partial schedule entry');
        await expect(page.locator('[id^="schedule-edit-"]')).toBeFocused();
        await unchanged();
        await page.locator('[id^="schedule-edit-"]').click();
        const edit = page.locator('#editScheduleModal');
        await edit.getByLabel('Display Title', { exact: true }).fill('Revised schedule entry');
        await edit.getByRole('button', { name: /Save/ }).click();
        await expect(page.locator('#gathering-overview-content')).toContainText('Revised schedule entry');
        const activityId = await page.locator('[id^="schedule-edit-"]').getAttribute('data-activity-id');
        const award = runPhpJson(fixtureScript, { ...fixture, attachAward: true, activityId });
        await page.reload();
        await markDocument();
        await page.locator('#nav-gathering-bestowals-tab').click();
        const awards = page.locator(`#gathering-bestowals-grid-${fixture.id}-table`);
        await expect(awards.locator('table')).toBeVisible();
        await awards.evaluate((frame, name) => {
            const url = new URL(frame.dataset.gridCurrentSrc || frame.dataset.gridSrc, location.origin);
            url.searchParams.set('search', name); url.searchParams.set('limit', '10');
            frame.dataset.gridCurrentSrc = url.pathname + url.search;
        }, fixture.name);
        await page.locator('#nav-schedule-tab').click();
        const awardsPath = await awards.evaluate(frame => new URL(frame.dataset.gridCurrentSrc || frame.dataset.gridSrc, location.origin).pathname);
        const refreshedAwards = page.waitForResponse(response => response.request().method() === 'GET'
            && new URL(response.url()).pathname === awardsPath
            && new URL(response.url()).searchParams.get('search') === fixture.name);
        await page.locator('[data-action="gathering-schedule#deleteActivity"]').click();
        await page.getByRole('dialog', { name: 'Delete scheduled activity', exact: true }).getByRole('button', { name: 'Delete', exact: true }).click();
        await expect(page.locator('#gathering-schedule-count')).toHaveText('0');
        assert.equal((await refreshedAwards).status(), 200);
        assert.equal(runPhpJson(fixtureScript, { awardState: award.id }).courtId, null);
        await expect(page.locator('#gathering-overview-content')).not.toContainText('Revised schedule entry');
        await unchanged();

        console.log('Schedule and award dependencies updated');
        await page.goto('/gathering-attendances/my-rsvps');
        const card = page.locator('.mobile-event-card').filter({ hasText: fixture.name });
        await expect(card).toHaveCount(1);
        await markDocument();
        await card.getByRole('button', { name: 'Edit', exact: true }).click();
        const mobileModal = page.locator('.modal.show').last();
        await mobileModal.locator('[name="public_note"]').fill('Mobile partial update');
        await mobileModal.getByRole('button', { name: /Update|Save/ }).click();
        await expect(card).toContainText('Mobile partial update');
        await unchanged();
        await card.getByRole('button', { name: 'Edit', exact: true }).click();
        await page.locator('.modal.show').last().getByRole('button', { name: /Remove My Attendance|Cancel RSVP/ }).click();
        await page.getByRole('dialog', { name: 'Remove attendance registration', exact: true }).getByRole('button', { name: 'Remove', exact: true }).click();
        await expect(card).toHaveCount(0);
        await expect(page.locator('#upcoming-tab')).toHaveAttribute('aria-selected', 'true');
        await unchanged();
        const [year, month] = fixture.startDate.split('-');
        await page.goto(`/gatherings/calendar?year=${year}&month=${Number(month)}`);
        const calendarTrigger = page.locator(`[href="/gatherings/quick-view/${fixture.publicId}"][data-action*="showQuickView"]`).first();
        await expect(calendarTrigger).toBeVisible();
        await markDocument();
        await calendarTrigger.click();
        await page.locator('#gatheringQuickViewModal').getByRole('button', { name: 'Mark Your Attendance', exact: true }).click();
        const calendarModal = page.locator('#attendanceModal');
        await calendarModal.locator('[name="public_note"]').fill('Calendar partial RSVP');
        const calendarPath = await page.locator('#gatherings-calendar-grid-table').evaluate(frame => new URL(frame.dataset.gridCurrentSrc || frame.getAttribute('src') || frame.dataset.gridSrc, location.origin).pathname);
        const calendarRefreshed = page.waitForResponse(response => response.request().method() === 'GET'
            && new URL(response.url()).pathname === calendarPath);
        await calendarModal.getByRole('button', { name: 'Register', exact: true }).click();
        assert.equal((await calendarRefreshed).status(), 200);
        await expect(calendarModal).not.toBeVisible();
        await expect(calendarTrigger).toBeFocused();
        await calendarTrigger.click();
        await expect(page.locator('#gatheringQuickViewModal')).toContainText("You're attending this gathering!");
        await unchanged();
        assert.deepEqual(errors, []);
        console.log('PASS: Notes, RSVP detail/list updates, schedules, dependent counts/overview, focus, and document/tab/query preservation');
    } finally {
        await browser.close();
        runPhpJson(fixtureScript, { ...fixture, cleanup: true });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
