// Local Docker regression without resetting existing development data.
const path = require('node:path');
const { spawn } = require('node:child_process');
const root = path.resolve(__dirname, '../../..');
const { chromium, expect } = require(path.join(root, 'node_modules/@playwright/test'));
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');
const { getAppContainerName } = require('./test-environment.cjs');

const fixturePhp = String.raw`require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(fgets(STDIN), true);
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if (!$tenant || $tenant->slug !== 'kmp'
    || !in_array($tenant->dbServer, ['db', 'postgres', 'localhost', '127.0.0.1'], true)) {
    throw new \RuntimeException('This check requires the local kmp tenant.');
}
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
echo json_encode($manager->withTenant($tenant, function () use ($input) {
    $l = \Cake\ORM\TableRegistry::getTableLocator();
    if (isset($input['inspectBestowal'])) {
        $bestowal = $l->get('Awards.Bestowals')->get($input['inspectBestowal']);
        return ['gatheringId' => $bestowal->gathering_id, 'roamingCourt' => $bestowal->roaming_court,
            'heraldNotes' => $bestowal->herald_notes, 'awardId' => $bestowal->award_id,
            'courtId' => $bestowal->gathering_scheduled_activity_id];
    }
    if (isset($input['holdGrant'])) {
        return $l->get('Awards.BestowalDelegations')->getConnection()->transactional(function () use ($input) {
            $grants = (new \Awards\Services\BestowalDelegationAccessService())->grantsFor(
                $input['holdGrant']['delegateId'], $input['holdGrant']['gatheringId'],
            );
            if ($grants === []) {
                throw new \RuntimeException('Expected an active grant to lock.');
            }
            fwrite(STDOUT, "locked\n");
            fflush(STDOUT);
            if (trim((string)fgets(STDIN)) !== 'release') {
                throw new \RuntimeException('Grant holder was interrupted.');
            }
            return ['released' => true];
        });
    }
    if (isset($input['cleanup'])) {
        $fixture = $input['cleanup'];
        $l->get('Awards.BestowalDelegations')->deleteAll(['gathering_id' => $fixture['gatheringId']]);
        $l->get('Awards.CourtAgendas')->deleteAll(['gathering_id' => $fixture['gatheringId']]);
        $l->get('Awards.Bestowals')->deleteAll(['id' => $fixture['bestowalId']]);
        $l->get('Gatherings')->deleteAll(['id' => $fixture['gatheringId'], 'name' => $fixture['name']]);
        $l->get('Awards.AwardGatheringActivities')->deleteAll(['gathering_activity_id' => $fixture['activityId']]);
        $l->get('GatheringActivities')->deleteAll(['id' => $fixture['activityId'], 'name' => $fixture['activityName']]);
        $l->get('Awards.AwardGatheringActivities')->deleteAll(['gathering_activity_id' => $fixture['editActivityId']]);
        $l->get('GatheringActivities')->deleteAll(['id' => $fixture['editActivityId'], 'name' => $fixture['editActivityName']]);
        $l->get('Awards.AwardGatheringActivities')->deleteAll(['gathering_activity_id' => $fixture['alternateActivityId']]);
        $l->get('GatheringActivities')->deleteAll(['id' => $fixture['alternateActivityId']]);
        $l->get('Members')->deleteAll(['id' => $fixture['delegateId'], 'email_address' => $fixture['email']]);
        return ['cleaned' => true];
    }
    return $l->get('Gatherings')->getConnection()->transactional(function () use ($l) {
        $name = 'Synthetic Event Delegate ' . uniqid();
        $email = 'delegate-' . uniqid() . '@example.test';
        $members = $l->get('Members');
        $admin = $members->find()->where(['email_address' => 'admin@amp.ansteorra.org'])->firstOrFail();
        $member = $members->newEntity([
            'sca_name' => $name, 'first_name' => 'Synthetic', 'last_name' => 'Delegate',
            'email_address' => $email, 'password' => 'TestPassword', 'birth_month' => 1, 'birth_year' => 1990,
            'street_address' => '', 'city' => '', 'state' => '', 'zip' => '', 'phone_number' => '',
            'created_by' => $admin->id,
        ]);
        $member->set('status', 'verified', ['guard' => false]);
        $members->saveOrFail($member);
        $source = $l->get('Gatherings')->find()->firstOrFail();
        $start = \Cake\I18n\DateTime::now()->addDays(2)->setTime(14, 0);
        $gathering = $l->get('Gatherings')->saveOrFail($l->get('Gatherings')->newEntity([
            'name' => $name, 'branch_id' => $source->branch_id, 'gathering_type_id' => $source->gathering_type_id,
            'start_date' => $start, 'end_date' => $start->addHours(1), 'location' => 'Synthetic Hall',
            'created_by' => $admin->id,
        ]));
        $award = $l->get('Awards.Awards')->find()->where(['branch_id IS NOT' => null])
            ->contain(['Domains'])->orderBy(['Awards.id' => 'ASC'])->firstOrFail();
        $replacement = $l->get('Awards.Awards')->find()->where([
            'branch_id' => $award->branch_id, 'domain_id' => $award->domain_id, 'Awards.id !=' => $award->id,
            'Awards.is_active' => true,
        ])->all()->filter(function ($candidate) {
            $specialties = $candidate->specialties;
            if (is_string($specialties)) $specialties = json_decode($specialties, true);
            return empty($specialties);
        })->first();
        if (!$replacement) throw new \RuntimeException('Expected two ordinary awards for the edit regression.');
        $activity = $l->get('GatheringActivities')->saveOrFail($l->get('GatheringActivities')->newEntity([
            'name' => 'Synthetic delegated court ' . uniqid(), 'activity_type' => 'Court',
        ]));
        $l->get('Awards.AwardGatheringActivities')->saveOrFail($l->get('Awards.AwardGatheringActivities')->newEntity([
            'award_id' => $award->id, 'gathering_activity_id' => $activity->id,
        ]));
        $l->get('Awards.AwardGatheringActivities')->saveOrFail($l->get('Awards.AwardGatheringActivities')->newEntity([
            'award_id' => $replacement->id, 'gathering_activity_id' => $activity->id,
        ]));
        $editActivity = $l->get('GatheringActivities')->saveOrFail($l->get('GatheringActivities')->newEntity([
            'name' => 'Synthetic original-award court ' . uniqid(), 'activity_type' => 'Court',
        ]));
        $l->get('Awards.AwardGatheringActivities')->saveOrFail($l->get('Awards.AwardGatheringActivities')->newEntity([
            'award_id' => $award->id, 'gathering_activity_id' => $editActivity->id,
        ]));
        $l->get('GatheringsGatheringActivities')->saveOrFail($l->get('GatheringsGatheringActivities')->newEntity([
            'gathering_id' => $gathering->id, 'gathering_activity_id' => $editActivity->id,
        ]));
        $alternateActivity = $l->get('GatheringActivities')->saveOrFail($l->get('GatheringActivities')->newEntity([
            'name' => 'Synthetic alternative award activity ' . uniqid(), 'activity_type' => 'Court',
        ]));
        $l->get('Awards.AwardGatheringActivities')->saveOrFail($l->get('Awards.AwardGatheringActivities')->newEntity([
            'award_id' => $replacement->id, 'gathering_activity_id' => $alternateActivity->id,
        ]));
        $l->get('GatheringsGatheringActivities')->saveOrFail($l->get('GatheringsGatheringActivities')->newEntity([
            'gathering_id' => $gathering->id, 'gathering_activity_id' => $alternateActivity->id,
        ]));
        $court = $l->get('GatheringScheduledActivities')->saveOrFail($l->get('GatheringScheduledActivities')->newEntity([
            'gathering_id' => $gathering->id, 'gathering_activity_id' => $editActivity->id,
            'start_datetime' => $start, 'display_title' => 'Synthetic original award session',
            'created_by' => $admin->id,
        ]));
        $bestowal = $l->get('Awards.Bestowals')->saveOrFail($l->get('Awards.Bestowals')->newEntity([
            'member_sca_name' => 'Synthetic delegated bestowal', 'award_id' => $award->id,
            'gathering_id' => $gathering->id, 'gathering_scheduled_activity_id' => $court->id,
            'lifecycle_status' => 'open',
            'source' => 'ad_hoc', 'noble_notes' => 'Synthetic delegated event secret',
        ]));
        return ['name' => $name, 'email' => $email, 'delegateId' => $member->id,
            'gatheringId' => $gathering->id, 'gatheringPublicId' => $gathering->public_id,
            'activityId' => $activity->id, 'activityName' => $activity->name,
            'editActivityId' => $editActivity->id, 'editActivityName' => $editActivity->name,
            'alternateActivityId' => $alternateActivity->id, 'courtId' => $court->id,
            'replacementAwardId' => $replacement->id, 'replacementAwardName' => $replacement->name,
            'domainName' => $award->domain->name,
            'bestowalId' => $bestowal->id, 'branchId' => $award->branch_id];
    });
}), JSON_THROW_ON_ERROR);`;

// Keep a real PostgreSQL authorization transaction open while the browser revokes its grant.
const holdGrant = async (fixture) => {
    const child = spawn('docker', ['exec', '-i', '-w', '/var/www/html', getAppContainerName(),
        'php', '-d', 'xdebug.mode=off', '-r', fixturePhp], { stdio: ['pipe', 'pipe', 'pipe'] });
    let output = '';
    const timeout = setTimeout(() => child.kill(), 15000);
    const done = new Promise((resolve, reject) => {
        child.on('error', reject);
        child.on('close', (code) => {
            clearTimeout(timeout);
            if (code === 0 && output.includes('"released":true')) resolve();
            else reject(new Error('Concurrent grant transaction did not finish.'));
        });
    });
    // The readiness waiter also observes early exits so a failed fixture cannot hang the check.
    const ready = new Promise((resolve, reject) => {
        child.stdout.on('data', (chunk) => {
            output += chunk.toString();
            if (output.includes('locked\n')) resolve();
        });
        child.on('error', reject);
        child.on('close', () => reject(new Error('Grant transaction exited before acquiring its lock.')));
    });
    done.catch(() => {});
    child.stdin.write(JSON.stringify({ holdGrant: fixture }) + '\n');
    await ready;
    return { release: () => child.stdin.end('release\n'), done };
};

(async () => {
    const fixture = runPhpJson(fixturePhp);
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080';
    const context = await browser.newContext({ baseURL });
    const page = await context.newPage();
    const errors = [];
    let holder;
    let delegatePage;
    try {
        await loginAs(page, 'admin@amp.ansteorra.org');
        await page.waitForLoadState('networkidle');
        page.on('pageerror', (error) => errors.push(error.message));
        await page.goto(`/awards/bestowals/view/${fixture.bestowalId}`);
        const initialEligibility = page.waitForResponse(response => response.ok()
            && response.url().includes(`/gathering-eligibility/${fixture.bestowalId}?`));
        await page.getByRole('button', { name: 'Edit', exact: true }).click();
        const editDialog = page.getByRole('dialog', { name: 'Edit Bestowal', exact: true });
        await expect(editDialog).toBeVisible();
        await expect(editDialog.locator('input[name="award_id"]')).not.toHaveValue('');
        await page.waitForLoadState('networkidle');
        expect((await (await initialEligibility).json()).eligible).toBe(true);
        const eligibilityWarning = editDialog.locator('[data-awards-bestowal-edit-target="gatheringEligibilityWarning"]');
        const courtWarning = editDialog.locator('[data-awards-bestowal-edit-target="courtEligibilityWarning"]');
        await expect(eligibilityWarning).not.toBeVisible();
        await expect(courtWarning).not.toBeVisible();
        await expect(editDialog.locator('input[name="gathering_id"]')).toHaveValue(String(fixture.gatheringId));
        const domainCombo = editDialog.locator('[data-awards-bestowal-edit-target="domain"]');
        await domainCombo.getByRole('button', { name: 'Clear', exact: true }).click();
        await expect(editDialog.locator('input[name="award_id"]')).toHaveValue('');
        await domainCombo.locator('[data-ac-target="input"]').fill(fixture.domainName);
        await page.getByRole('option', { name: fixture.domainName, exact: true }).click();
        await page.waitForLoadState('networkidle');
        const awardCombo = editDialog.locator('[data-awards-bestowal-edit-target="award"]');
        await expect(awardCombo.locator('[data-ac-target="input"]')).toBeEnabled();
        await awardCombo.locator('[data-ac-target="input"]').click();
        await page.getByRole('option', { name: fixture.replacementAwardName, exact: true }).click({ timeout: 5000 });
        await expect(editDialog.locator('input[name="award_id"]')).toHaveValue(String(fixture.replacementAwardId));
        await expect(editDialog.locator('input[name="gathering_id"]')).toHaveValue(String(fixture.gatheringId));
        await editDialog.locator('textarea[name="herald_notes"]').fill('Edit preservation regression');
        await expect(courtWarning).toBeVisible();
        await expect(courtWarning).toContainText('cannot present the selected award');
        await expect(courtWarning).toContainText('gathering assignment has been kept');
        await expect(eligibilityWarning).not.toBeVisible();
        await expect(editDialog.getByLabel('Court session', { exact: true })).toHaveValue(String(fixture.courtId));
        await editDialog.getByLabel('Court session', { exact: true }).selectOption('');
        await expect(courtWarning).not.toBeVisible();
        await expect(editDialog.locator('input[name="gathering_id"]')).toHaveValue(String(fixture.gatheringId));
        await editDialog.getByRole('button', { name: 'Submit', exact: true }).click();
        await expect(editDialog).not.toBeVisible();
        await expect.poll(() => runPhpJson(fixturePhp, { inspectBestowal: fixture.bestowalId })).toEqual({
            gatheringId: fixture.gatheringId, roamingCourt: false, heraldNotes: 'Edit preservation regression',
            awardId: fixture.replacementAwardId, courtId: null,
        });
        await page.goto(`/awards/court-agendas/gathering/${fixture.gatheringId}`);
        const opener = page.getByRole('button', { name: 'Event Award Delegation', exact: true });
        await opener.click();
        const dialog = page.getByRole('dialog', { name: 'Event Award Delegation', exact: true });
        await expect(dialog).toBeVisible();
        await expect(page).toHaveURL(new RegExp(`/awards/court-agendas/gathering/${fixture.gatheringId}$`));
        const group = dialog.getByLabel('Awarding group', { exact: true });
        await expect(group).toBeFocused();
        await group.selectOption(String(fixture.branchId));
        const recipient = dialog.getByLabel('Person receiving access', { exact: true });
        await recipient.fill(fixture.name);
        await expect(page.getByRole('option', { name: fixture.name, exact: true })).toBeVisible();
        await recipient.press('ArrowDown');
        await recipient.press('Enter');
        await expect(page.locator('input[name="delegate_public_id"]')).not.toHaveValue('');
        await page.getByRole('button', { name: 'Grant event access', exact: true }).click();
        const revoke = page.getByRole('button', { name: `Revoke access for ${fixture.name}`, exact: true });
        await expect(revoke).toBeVisible();
        await expect(dialog).toBeVisible();
        await expect(dialog.getByRole('alert')).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible();
        await expect(opener).toBeFocused();
        await opener.click();
        await expect(revoke).toBeVisible();
        await revoke.focus();
        await expect(revoke).toBeFocused();

        const delegateContext = await browser.newContext({ baseURL });
        delegatePage = await delegateContext.newPage();
        await loginAs(delegatePage, fixture.email);
        await delegatePage.waitForLoadState('networkidle');
        delegatePage.on('pageerror', (error) => errors.push(error.message));
        await delegatePage.goto(`/awards/bestowals/view/${fixture.bestowalId}`);
        await expect(delegatePage.getByText('Synthetic delegated event secret', { exact: true })).toBeVisible();
        await delegatePage.goto(`/gatherings/view/${fixture.gatheringPublicId}`);
        await expect(delegatePage.getByRole('tab', { name: /^Staff\b/ })).toHaveCount(0);
        await expect(delegatePage.getByRole('tab', { name: 'Record details', exact: true })).toHaveCount(0);
        await delegatePage.getByRole('tab', { name: /^Activities\b/ }).click();
        await delegatePage.getByRole('button', { name: 'Add Activity', exact: true }).click();
        const activityDialog = delegatePage.getByRole('dialog', { name: `Add Activity to ${fixture.name}`, exact: true });
        await activityDialog.getByLabel('Select Activity', { exact: true }).selectOption(String(fixture.activityId));
        await activityDialog.getByRole('button', { name: 'Add Activity', exact: true }).click();
        await expect(activityDialog).not.toBeVisible();
        await delegatePage.getByRole('tab', { name: /^Schedule\b/ }).click();
        await delegatePage.getByRole('button', { name: 'Add Scheduled Activity', exact: true }).click();
        const scheduleDialog = delegatePage.getByRole('dialog', { name: 'Add Scheduled Activity', exact: true });
        await expect(scheduleDialog.getByLabel('Start Date', { exact: true })).toHaveValue(/\d{4}-\d{2}-\d{2}/);
        await scheduleDialog.getByLabel('Gathering Activity', { exact: true }).selectOption(String(fixture.activityId));
        await scheduleDialog.getByLabel('Duration', { exact: true }).selectOption('240');
        await scheduleDialog.getByLabel('Display Title', { exact: true }).fill('Delegate prepared kingdom court');
        await scheduleDialog.getByRole('button', { name: 'Add Scheduled Activity', exact: true }).click();
        await expect(scheduleDialog.getByRole('alert')).toContainText('must fall within the gathering dates');
        await scheduleDialog.getByLabel('Duration', { exact: true }).selectOption('15');
        await scheduleDialog.getByRole('button', { name: 'Add Scheduled Activity', exact: true }).click();
        await expect(scheduleDialog).not.toBeVisible();
        await delegatePage.getByRole('tab', { name: /^Schedule\b/ }).click();
        await expect(delegatePage.getByRole('tabpanel', { name: /^Schedule\b/ })
            .getByText('Delegate prepared kingdom court', { exact: true })).toBeVisible();
        await delegatePage.goto(`/awards/court-agendas/gathering/${fixture.gatheringId}`);
        const courtSelector = delegatePage.getByLabel('Court Activities', { exact: true });
        const preparedCourt = courtSelector.getByRole('option', { name: /^Delegate prepared kingdom court/ });
        await expect(preparedCourt).toHaveCount(1);
        await courtSelector.selectOption(await preparedCourt.getAttribute('value'));
        await delegatePage.getByRole('button', { name: 'Show court', exact: true }).click();
        await expect(delegatePage.getByRole('heading', { name: 'Delegate prepared kingdom court', exact: true })).toBeVisible();
        await delegatePage.getByRole('button', { name: 'Event Award Delegation', exact: true }).click();
        await expect(delegatePage.getByRole('dialog', { name: 'Event Award Delegation', exact: true })).toBeVisible();
        await expect(delegatePage.getByRole('button', { name: 'Grant event access', exact: true })).toHaveCount(0);
        await expect(delegatePage.getByRole('button', { name: /^Revoke access/ })).toHaveCount(0);

        holder = await holdGrant(fixture);
        let revoked = false;
        const revokedResponse = page.waitForResponse(response =>
            response.request().method() === 'POST' && response.url().includes('/bestowal-delegations/revoke/'));
        const requested = page.waitForRequest(request =>
            request.method() === 'POST' && request.url().includes('/bestowal-delegations/revoke/'));
        const press = revoke.press('Enter');
        revokedResponse.then(() => { revoked = true; });
        await requested;
        await new Promise(resolve => setTimeout(resolve, 250));
        expect(revoked).toBe(false);
        holder.release();
        await holder.done;
        holder = null;
        await Promise.all([press, revokedResponse]);
        await expect(page.getByText('Delegated access revoked.', { exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: /^Revoke access/ })).toHaveCount(0);
        await delegatePage.goto(`/awards/bestowals/view/${fixture.bestowalId}`);
        await expect(delegatePage).toHaveURL(/\/pages\/unauthorized/);
        await delegateContext.close();
        await page.goto(`/gatherings/view/${fixture.gatheringPublicId}`);
        await page.getByRole('tab', { name: 'Award Bestowals', exact: true }).click();
        const gatheringURL = page.url();
        await page.getByRole('button', { name: 'Event Award Delegation', exact: true }).click();
        const gatheringDialog = page.getByRole('dialog', { name: 'Event Award Delegation', exact: true });
        await expect(gatheringDialog.getByLabel('Awarding group', { exact: true })).toBeFocused();
        await expect(page).toHaveURL(gatheringURL);
        await page.keyboard.press('Escape');
        await expect(gatheringDialog).not.toBeVisible();
        if (errors.length) throw new Error(errors.join('\n'));
        process.stdout.write('PASS: incompatible court warnings preserve the gathering, explicit court clearing saves safely; modal delegation, court scheduling, and concurrent revocation.\n');
    } catch (error) {
        await page.screenshot({ path: '/tmp/kmp-delegation-modal-check.png' });
        if (delegatePage && !delegatePage.isClosed()) {
            await delegatePage.screenshot({ path: '/tmp/kmp-delegation-scheduling-check.png' });
        }
        if (errors.length) process.stderr.write(errors.join('\n') + '\n');
        throw error;
    } finally {
        if (holder) {
            holder.release();
            await holder.done.catch(() => {});
        }
        await browser.close();
        runPhpJson(fixturePhp, { cleanup: fixture });
    }
})().catch((error) => {
    process.stderr.write(`${error.stack || error}\n`);
    process.exitCode = 1;
});
