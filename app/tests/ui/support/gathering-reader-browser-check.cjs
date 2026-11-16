/** Non-destructive local acceptance. Creates and removes only synthetic reader fixtures. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const { expect } = require('@playwright/test');
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');

const fixture = String.raw`
if (function_exists('posix_geteuid') && posix_geteuid() === 0) { posix_setgid(33); posix_setuid(33); }
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if (!$tenant || $tenant->slug !== 'kmp' || $tenant->status !== 'active'
    || !in_array($tenant->dbServer, ['db', 'postgres', '127.0.0.1', 'localhost'], true)) {
    throw new \RuntimeException('Reader acceptance requires the local seeded tenant.');
}
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
$result = $manager->withTenant($tenant, function () use ($input) {
    $tables = \Cake\ORM\TableRegistry::getTableLocator();
    $members = $tables->get('Members'); $gatherings = $tables->get('Gatherings');
    if (isset($input['publicId'])) {
        $gathering = $gatherings->find()->where(['public_id' => $input['publicId'], 'name LIKE' => 'Reader Acceptance %'])->firstOrFail();
        if (!empty($input['cleanup'])) {
            $gatherings->deleteOrFail($gathering, ['purge' => true]);
            foreach ([$input['reader'], $input['organizer']] as $person) {
                $member = $members->find()->where(['email_address' => $person['email'], 'first_name' => 'Synthetic'])->firstOrFail();
                $members->deleteOrFail($member);
            }
            return ['cleaned' => true];
        }
        $gatherings->updateAll(['cancelled_at' => new \Cake\I18n\DateTime(), 'cancellation_reason' => 'Weather cancellation'], ['id' => $gathering->id]);
        return ['cancelled' => true];
    }
    $admin = $members->find()->where(['email_address' => 'admin@amp.ansteorra.org'])->firstOrFail();
    return $gatherings->getConnection()->transactional(function () use ($tables, $members, $gatherings, $admin) {
    $people = [];
    foreach (['reader', 'organizer'] as $kind) {
        $password = 'Temporary!' . bin2hex(random_bytes(20));
        $member = $members->newEntity([
            'first_name' => 'Synthetic', 'last_name' => ucfirst($kind), 'sca_name' => 'Synthetic ' . ucfirst($kind),
            'email_address' => 'reader-' . bin2hex(random_bytes(8)) . '@example.test', 'password' => $password,
            'status' => $admin->status, 'branch_id' => $admin->branch_id, 'membership_number' => 'SYNTHETIC',
            'membership_expires_on' => '2099-01-01', 'birth_year' => 1990, 'birth_month' => 1,
            'created_by' => $admin->id, 'street_address' => '1 Synthetic Street', 'city' => 'Test',
            'state' => 'TX', 'zip' => '75001', 'phone_number' => '5555550100',
        ]);
        $members->saveOrFail($member);
        $people[$kind] = ['id' => $member->id, 'email' => $member->email_address, 'password' => $password];
    }
    $type = $tables->get('GatheringTypes')->find()->firstOrFail();
    $start = new \Cake\I18n\DateTime('+14 days 15:00:00');
    $gathering = $gatherings->newEntity([
        'name' => 'Reader Acceptance ' . bin2hex(random_bytes(6)), 'branch_id' => $admin->branch_id,
        'gathering_type_id' => $type->id, 'start_date' => $start, 'end_date' => $start->addHours(8),
        'timezone' => 'America/Chicago', 'location' => 'Riverside Park, Testville',
        'description' => "Join us for a day of **arts, fellowship and learning**.\n\n## What to bring\n\nBring your projects and a picnic lunch. Newcomers are welcome.",
        'public_page_enabled' => true, 'created_by' => $people['organizer']['id'],
        'preregister_url' => 'https://example.test/registration',
    ]);
    $gatherings->saveOrFail($gathering);
    $staff = $tables->get('GatheringStaff');
    foreach ([
        ['member_id' => $people['organizer']['id'], 'role' => 'Steward', 'is_steward' => true, 'show_on_public_page' => true],
        ['sca_name' => 'Shared Event Contact', 'role' => 'Event questions', 'is_steward' => false, 'show_on_public_page' => true],
        ['sca_name' => 'Private Support Contact', 'role' => 'Helper', 'is_steward' => false, 'show_on_public_page' => false, 'contact_notes' => 'Private planning detail'],
    ] as $person) {
        $staff->saveOrFail($staff->newEntity($person + ['gathering_id' => $gathering->id, 'email' => 'contact@example.test', 'created_by' => $admin->id]));
    }
    $scheduled = $tables->get('GatheringScheduledActivities');
    foreach (['Welcome and introductions', 'Bardic circle'] as $index => $title) {
        $scheduled->saveOrFail($scheduled->newEntity([
            'gathering_id' => $gathering->id, 'display_title' => $title, 'is_other' => true,
            'start_datetime' => $start->addHours($index * 2), 'end_datetime' => $start->addHours($index * 2 + 1),
            'has_end_time' => true, 'description' => 'Everyone is welcome.', 'created_by' => $people['organizer']['id'],
        ]));
    }
    return $people + ['publicId' => $gathering->public_id];
    });
});
echo json_encode($result, JSON_THROW_ON_ERROR);
`;

(async () => {
    const data = runPhpJson(fixture);
    const output = process.env.KMP_READER_EVIDENCE_DIR || '/tmp/kmp-gathering-reader';
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const errors = [];
    try {
        const context = await browser.newContext({ baseURL: 'http://kmp.localhost:8080', viewport: { width: 1440, height: 1050 }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await loginAs(page, data.reader.email, data.reader.password);
        const onlineOnly = page.getByRole('button', { name: 'Use online only', exact: true });
        if (await onlineOnly.isVisible()) await onlineOnly.click();
        const url = `/gatherings/view/${data.publicId}`;
        await page.goto(url);
        fs.writeFileSync(path.join(output, 'layout.json'), JSON.stringify(await page.evaluate(() => {
            const article = document.querySelector('.gathering-reader');
            const main = document.querySelector('#main-content');
            return { articleInMain: main.contains(article), articleParent: article.parentElement.tagName,
                article: article.getBoundingClientRect().toJSON(), main: main.getBoundingClientRect().toJSON(),
                mainClasses: main.className, marginLeft: getComputedStyle(main).marginLeft };
        }), null, 2));
        await expect(page.getByRole('tab', { name: 'Overview', exact: true })).toHaveAttribute('aria-selected', 'true');
        await expect(page.locator('.gathering-public-surface')).toHaveCount(0);
        await expect(page.getByText('Private planning detail')).toHaveCount(0);
        await expect(page.getByText('Shared Event Contact')).toBeVisible();
        await expect(page.getByText('Welcome and introductions')).toBeVisible();
        await expect(page.locator('#nav-details-tab')).toHaveCount(0);
        await page.waitForTimeout(400);
        await page.screenshot({ path: path.join(output, 'member-desktop.png'), fullPage: true, animations: 'disabled' });
        await page.getByRole('button', { name: 'Attend This Gathering', exact: true }).click();
        const dialog = page.locator('#attendGatheringModal');
        await expect(dialog).toBeVisible();
        await dialog.getByLabel('Public Note', { exact: true }).fill('Bringing a song and a picnic.');
        await dialog.getByRole('button', { name: 'Register', exact: true }).click();
        await expect(page.getByText('You’re planning to attend', { exact: true })).toBeVisible();
        await page.reload();
        await page.getByRole('button', { name: 'Update Attendance', exact: true }).click();
        await expect(dialog.getByLabel('Public Note', { exact: true })).toHaveValue('Bringing a song and a picnic.');
        await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
        await expect(dialog).toBeHidden();
        await page.setViewportSize({ width: 390, height: 844 });
        await page.screenshot({ path: path.join(output, 'member-mobile.png'), fullPage: true, animations: 'disabled' });
        assert.equal(await page.locator('.gathering-reader').evaluate(el => el.scrollWidth <= el.clientWidth + 1), true);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);

        const organizerContext = await browser.newContext({ baseURL: 'http://kmp.localhost:8080', viewport: { width: 1440, height: 1050 }, reducedMotion: 'reduce' });
        const organizer = await organizerContext.newPage();
        organizer.on('pageerror', error => errors.push(error.message));
        await loginAs(organizer, data.organizer.email, data.organizer.password);
        const organizerOnline = organizer.getByRole('button', { name: 'Use online only', exact: true });
        if (await organizerOnline.isVisible()) await organizerOnline.click();
        await organizer.goto(url + '?tab=schedule');
        await expect(organizer.getByRole('tab', { name: /^Schedule/ })).toHaveAttribute('aria-selected', 'true');
        await organizer.getByRole('button', { name: 'Add Scheduled Activity', exact: true }).click();
        await expect(organizer.locator('#addScheduleModal')).toBeVisible();
        await organizer.locator('#addScheduleModal').getByRole('button', { name: 'Close', exact: true }).click();
        await organizer.getByRole('tab', { name: /^Activities/ }).locator('.badge').click();
        await expect(organizer).toHaveURL(/tab=activities/);
        await organizer.locator('#nav-activities button[data-bs-target="#editActivityDescriptionModal"]').first().click();
        const activityDialog = organizer.locator('#editActivityDescriptionModal');
        await activityDialog.getByLabel('Custom Description for This Gathering').fill('Bring a song and a project.');
        await activityDialog.getByRole('button', { name: 'Save Description', exact: true }).click();
        await expect(organizer.getByText('Bring a song and a project.', { exact: true }).first()).toBeVisible();
        await organizer.getByRole('tab', { name: 'Overview', exact: true }).focus();
        await organizer.keyboard.press('Space');
        await expect(organizer.locator('#nav-overview')).toBeVisible();
        await organizer.screenshot({ path: path.join(output, 'organizer-desktop.png'), fullPage: true });
        await organizer.getByRole('tab', { name: 'Record details', exact: true }).click();
        await expect(organizer.getByText('Created By', { exact: true })).toBeVisible();
        await organizer.getByRole('tab', { name: /^Staff/ }).click();
        await expect(organizer.getByText('Private Support Contact', { exact: true })).toBeVisible();

        runPhpJson(fixture, data);
        await page.reload();
        await expect(page.getByText('This gathering has been cancelled.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Update Attendance', exact: true })).toBeVisible();
        await page.screenshot({ path: path.join(output, 'cancelled-mobile.png'), fullPage: true });
        assert.deepEqual(errors, []);
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ nativeReader: true, contactsPrivate: true, rsvpSavedAndReopened: true, scheduleModal: true, activityDescriptionSaved: true, nestedTabClicks: true, keyboardTabs: true, organizerTools: true, mobileFits: true, cancelledExistingRsvp: true, pageErrors: errors }, null, 2));
        console.log(`Gathering reader browser checks passed. Evidence: ${output}`);
    } finally {
        await browser.close();
        runPhpJson(fixture, { ...data, cleanup: true });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
