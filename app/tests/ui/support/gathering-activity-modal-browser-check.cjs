/** Local acceptance: only creates/removes its synthetic member and activities. */
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const { expect } = require('@playwright/test');
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');

const fixture = String.raw`
if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    posix_setgid(33);
    posix_setuid(33);
}
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if (!$tenant || $tenant->slug !== 'kmp' || $tenant->status !== 'active'
    || !in_array($tenant->dbServer, ['db', 'postgres', '127.0.0.1', 'localhost'], true)) {
    throw new \RuntimeException('This check requires the local seeded tenant.');
}
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
$result = $manager->withTenant($tenant, function () use ($input) {
    $locator = \Cake\ORM\TableRegistry::getTableLocator();
    $members = $locator->get('Members');
    $activities = $locator->get('GatheringActivities');
    if (!empty($input['email'])) {
        $member = $members->find()->where(['email_address' => $input['email']])->firstOrFail();
        $activity = $activities->find()->where(['name LIKE' => $input['activityName'] . '%'])->first();
        if (!empty($input['cleanup'])) {
            foreach ($activities->find()->where(['name LIKE' => $input['activityName'] . '%'])->all() as $entry) { $activities->deleteOrFail($entry, ['purge' => true]); }
            $locator->get('MemberRoles')->deleteAll(['member_id' => $member->id]);
            $members->deleteOrFail($member);
            return ['cleaned' => true];
        }
        if ($activity) $activity = $activities->get($activity->id);
        return ['id' => $activity?->id, 'icon' => $activity?->icon_name, 'source' => $activity?->icon_source, 'order' => $activity?->display_order, 'name' => $activity?->name, 'type' => $activity?->activity_type];
    }
    $admin = $members->find()->where(['email_address' => 'admin@amp.ansteorra.org'])->firstOrFail();
    $password = 'Temporary!' . bin2hex(random_bytes(24));
    $name = 'Synthetic modal activity ' . bin2hex(random_bytes(8));
    $member = $members->newEntity([
        'first_name' => 'Synthetic', 'last_name' => 'Modal', 'sca_name' => 'Synthetic Modal Tester',
        'email_address' => 'activity-modal-' . bin2hex(random_bytes(8)) . '@example.test', 'password' => $password,
        'status' => $admin->status, 'branch_id' => $admin->branch_id, 'membership_number' => 'SYNTHETIC',
        'membership_expires_on' => '2099-01-01', 'birth_year' => 1990, 'birth_month' => 1,
        'created_by' => $admin->id, 'street_address' => '1 Synthetic Street', 'city' => 'Test',
        'state' => 'TX', 'zip' => '75001', 'phone_number' => '5555550100',
    ]);
    $members->saveOrFail($member);
    $role = $locator->get('Roles')->find()->innerJoinWith('Permissions', fn($q) => $q->where(['Permissions.is_super_user' => true]))->firstOrFail();
    $locator->get('MemberRoles')->saveOrFail($locator->get('MemberRoles')->newEntity([
        'member_id' => $member->id, 'role_id' => $role->id, 'branch_id' => $admin->branch_id,
        'start_on' => '2020-01-01', 'expires_on' => '2099-01-01', 'approver_id' => $admin->id, 'entity_type' => 'Direct Grant',
    ]));
    return ['email' => $member->email_address, 'password' => $password, 'activityName' => $name];
});
echo json_encode($result, JSON_THROW_ON_ERROR);
`;

(async () => {
    const data = runPhpJson(fixture);
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    try {
        const context = await browser.newContext({
            baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080',
            viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce',
        });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await loginAs(page, data.email, data.password);
        const onlineOnly = page.getByRole('button', { name: 'Use online only', exact: true });
        if (await onlineOnly.isVisible()) await onlineOnly.click();
        await page.goto('/gathering-activities');
        const modal = page.locator('#gatheringActivityModal');
        const frame = page.locator('#gathering-activities-grid-table');
        const add = page.getByRole('button', { name: 'Add Gathering Activity', exact: true });
        await frame.locator('tbody tr').first().waitFor();
        await add.click();
        await expect(modal).toBeVisible();
        await expect(modal.getByLabel('Name', { exact: true })).toBeFocused();
        await modal.getByLabel('Name', { exact: true }).fill(data.activityName);
        await modal.getByLabel('Display order', { exact: true }).fill('4');
        const activityType = modal.getByRole('combobox', { name: 'Activity Type', exact: true });
        await expect(activityType).toHaveValue('General');
        assert.deepEqual(await activityType.locator('option').allTextContents(), [
            'Order Circle', 'Royal Progress', 'Court', 'Business', 'Martial',
            'Arts & Sciences', 'Youth', 'General', 'Other',
        ]);
        await activityType.selectOption('Order Circle');
        await modal.getByRole('searchbox', { name: 'Search icons', exact: true }).fill('rapier');
        const icon = modal.getByRole('button', { name: 'Fencing blades', exact: true });
        await icon.focus();
        await page.keyboard.press('Space');
        await expect(icon).toHaveAttribute('aria-pressed', 'true');
        await modal.getByRole('button', { name: 'Add activity', exact: true }).click();
        await expect(modal).toBeHidden();
        assert.equal(new URL(page.url()).pathname, '/gathering-activities');
        const saved = runPhpJson(fixture, data);
        assert.equal(saved.icon, 'fencing');
        assert.equal(saved.order, 4);
        assert.equal(saved.type, 'Order Circle');
        assert.ok(saved.id);

        // Exercise the same filtered/sorted list across validation and a successful edit.
        await page.goto('/gathering-activities?search=' + encodeURIComponent(data.activityName)
            + '&sort=display_order&direction=desc');
        let edit = frame.getByRole('button', { name: 'Edit ' + data.activityName, exact: true });
        await expect(edit).toBeVisible();
        const contextUrl = page.url();
        await edit.focus();
        await page.keyboard.press('Enter');
        await expect(modal.getByLabel('Name', { exact: true })).toBeFocused();
        await expect(modal.getByLabel('Selected icon', { exact: true })).toHaveValue('fencing');
        await expect(activityType).toHaveValue('Order Circle');
        await activityType.focus();
        await page.keyboard.press('End');
        await expect(activityType).toHaveValue('Other');
        await activityType.selectOption('Arts & Sciences');
        await modal.getByLabel('Name', { exact: true }).fill(data.activityName + ' updated');
        await modal.getByLabel('Display order', { exact: true }).fill('-1');
        await modal.locator('form').evaluate(form => { form.noValidate = true; });
        await modal.getByRole('button', { name: 'Save changes', exact: true }).click();
        await expect(modal.getByRole('alert').filter({ hasText: 'Please correct' })).toBeVisible();
        await expect(modal.getByLabel('Display order', { exact: true })).toBeFocused();
        await expect(modal.getByLabel('Name', { exact: true })).toHaveValue(data.activityName + ' updated');
        assert.equal(runPhpJson(fixture, data).name, data.activityName);
        await modal.getByLabel('Display order', { exact: true }).fill('2');
        await modal.getByRole('button', { name: 'Save changes', exact: true }).click();
        await expect(modal).toBeHidden();
        await expect(frame.getByRole('button', { name: 'Edit ' + data.activityName + ' updated', exact: true })).toBeVisible();
        assert.equal(page.url(), contextUrl);
        assert.equal(runPhpJson(fixture, data).order, 2);
        assert.equal(runPhpJson(fixture, data).type, 'Arts & Sciences');
        await expect(page.locator('body')).not.toHaveClass(/modal-open/);
        assert.equal(await page.evaluate(() => document.activeElement !== document.body), true);

        // Mobile modal contains its scrolling; Escape returns keyboard focus to the trigger.
        await page.setViewportSize({ width: 390, height: 844 });
        edit = frame.getByRole('button', { name: 'Edit ' + data.activityName + ' updated', exact: true });
        await edit.click();
        await expect(modal.getByLabel('Name', { exact: true })).toBeFocused();
        const bounds = await modal.locator('.modal-dialog').boundingBox();
        assert.ok(bounds.width <= 390 && bounds.height <= 844);
        await modal.getByRole('button', { name: 'Save changes', exact: true }).scrollIntoViewIfNeeded();
        await expect(modal.getByRole('button', { name: 'Save changes', exact: true })).toBeInViewport();
        await page.keyboard.press('Escape');
        await expect(modal).toBeHidden();
        await expect(edit).toBeFocused();
        await page.setViewportSize({ width: 1280, height: 900 });

        // Complex configuration stays on details, and editing there preserves its tab.
        await frame.getByRole('link', { name: 'View ' + data.activityName + ' updated', exact: true }).click();
        await page.waitForURL(/gathering-activities\/view\//);
        await page.goto('/gathering-activities/view/' + saved.id + '?tab=assigned-by');
        await page.getByRole('button', { name: 'Edit', exact: true }).click();
        await expect(modal.getByLabel('Name', { exact: true })).toBeFocused();
        await modal.getByLabel('Description', { exact: true }).fill('Updated in the shared modal');
        await modal.getByRole('button', { name: 'Save changes', exact: true }).click();
        await expect(modal).toBeHidden();
        await expect(page.getByRole('cell', { name: 'Updated in the shared modal', exact: true })).toBeVisible();
        assert.equal(new URL(page.url()).searchParams.get('tab'), 'assigned-by');
        assert.deepEqual(errors, []);
        console.log('PASS: modal add/edit, appearance, validation, grid state, details, mobile, and keyboard focus');
    } finally {
        await browser.close();
        runPhpJson(fixture, { ...data, cleanup: true });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
