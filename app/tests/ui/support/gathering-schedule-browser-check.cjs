/** Local synthetic acceptance for quarter-hour starts, durations, and public schedule output. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright');
const { expect } = require('@playwright/test');
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');
const source = fs.readFileSync(require.resolve('./offline-app-browser-check.cjs'), 'utf8');
const fixtureSource = source.match(/const tenantFixture = String.raw`([\s\S]*?)`;/)[1]
    .replace("'created_by' => $admin->id, 'location'", "'public_page_enabled' => true, 'created_by' => $admin->id, 'location'")
    .replace("return ['id' => $gathering->id, 'name'", "return ['publicId' => $gathering->public_id, 'startDate' => $gathering->start_date->format('Y-m-d'), 'id' => $gathering->id, 'name'")
    .replace("$attendance->deleteAll(['gathering_id' => $gathering->id]);", "$attendance->deleteAll(['gathering_id' => $gathering->id]); $locator->get('GatheringScheduledActivities')->deleteAll(['gathering_id' => $gathering->id]);");

async function verifySchedule(page, fixture) {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const details = '/gatherings/view/' + fixture.publicId;
    await page.goto(details);
    await page.locator('#nav-schedule-tab').click();
    const trigger = page.getByRole('button', { name: 'Add Scheduled Activity', exact: true });
    await trigger.focus();
    await page.keyboard.press('Enter');
    const add = page.locator('#addScheduleModal');
    await expect(add).toBeFocused();
    const times = await add.getByLabel('Start Time', { exact: true }).locator('option').evaluateAll(options => options.map(option => option.value));
    assert.equal(times.length, 96);
    assert.ok(times.every(value => ['00', '15', '30', '45'].includes(value.slice(3))));
    const duration = add.getByLabel('Duration', { exact: true });
    assert.deepEqual(await duration.locator('option').evaluateAll(options => options.map(option => option.value)),
        [...Array.from({ length: 16 }, (_, i) => String((i + 1) * 15)), 'other']);
    await add.getByLabel('Start Date', { exact: true }).fill(fixture.startDate);
    await add.getByLabel('Start Time', { exact: true }).selectOption('23:45');
    await duration.selectOption('90');
    await add.locator('#add-is-other').check();
    await add.getByLabel('Display Title', { exact: true }).fill('Quarter-hour browser check');
    await add.getByLabel('Description', { exact: true }).fill('Open-ended timing will be described here.');
    await page.screenshot({ path: '/tmp/kmp-schedule-desktop.png', animations: 'disabled' });
    const saved = page.waitForResponse(response => response.request().method() === 'POST');
    const response = await Promise.all([saved, add.getByRole('button', { name: 'Add Scheduled Activity', exact: true }).click({ timeout: 5000 })]);
    const result = await response[0].json();
    assert.equal(result.success, true, JSON.stringify(result));
    await page.waitForLoadState('load');
    await page.locator('#nav-schedule-tab').click();
    let row = page.locator('#nav-schedule tr').filter({ hasText: 'Quarter-hour browser check' });
    await expect(row).toContainText('11:45 PM');
    await expect(row).toContainText('1:15 AM');
    const publicPage = await page.context().newPage();
    await publicPage.goto('/gatherings/public-landing/' + fixture.publicId);
    await expect(publicPage.getByText('Quarter-hour browser check', { exact: true })).toBeVisible();
    await expect(publicPage.locator('body')).toContainText('11:45 PM');
    await expect(publicPage.locator('body')).toContainText('1:15 AM');
    await publicPage.close();
    await page.setViewportSize({ width: 375, height: 480 });
    await row.getByRole('button', { name: 'Edit Quarter-hour browser check', exact: true }).click();
    const edit = page.locator('#editScheduleModal');
    await expect(edit).toBeFocused();
    await expect(edit.getByLabel('Duration', { exact: true })).toHaveValue('90');
    await edit.getByLabel('Duration', { exact: true }).focus();
    await page.keyboard.press('Home');
    await page.keyboard.press('End');
    await expect(edit.getByLabel('Duration', { exact: true })).toHaveValue('other');
    await page.screenshot({ path: '/tmp/kmp-schedule-mobile.png', animations: 'disabled' });
    const updated = page.waitForResponse(response => response.request().method() === 'POST');
    await edit.getByRole('button', { name: 'Save Changes', exact: true }).focus();
    await page.keyboard.press('Enter');
    assert.equal((await (await updated).json()).success, true);
    await page.waitForLoadState('load');
    await page.locator('#nav-schedule-tab').click();
    row = page.locator('#nav-schedule tr').filter({ hasText: 'Quarter-hour browser check' });
    await expect(row).not.toContainText('1:15 AM');
    const editTrigger = row.getByRole('button', { name: 'Edit Quarter-hour browser check', exact: true });
    await editTrigger.click();
    await expect(edit).toBeFocused();
    await expect(edit.getByLabel('Duration', { exact: true })).toHaveValue('other');
    await page.keyboard.press('Escape');
    await expect(edit).not.toBeVisible();
    await expect(editTrigger).toBeFocused();
    assert.deepEqual(errors, []);
    console.log('PASS: quarter-hour choices; 15–240 minute durations; midnight save and public times; mobile keyboard Other/save/reopen/Escape; no browser errors');
}

if (require.main === module) {
    (async () => {
        const fixture = runPhpJson(fixtureSource);
        const browser = await chromium.launch({ args: ['--no-sandbox'] });
        try {
            const context = await browser.newContext({ baseURL: 'http://kmp.localhost:8080', viewport: { width: 1100, height: 480 }, timezoneId: 'Asia/Tokyo' });
            const page = await context.newPage();
            await loginAs(page, 'admin@amp.ansteorra.org');
            await verifySchedule(page, fixture);
        } finally {
            await browser.close();
            runPhpJson(fixtureSource, { cleanup: fixture.id, name: fixture.name, memberId: fixture.memberId, email: fixture.email });
        }
    })().catch(error => { console.error(error.stack); process.exitCode = 1; });
}
module.exports = { verifySchedule };
