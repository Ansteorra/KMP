/** Local synthetic acceptance for RSVP dialogs and app back-arrow history. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright');
const { expect } = require('@playwright/test');
const { runPhpJson } = require('./ui-helpers.cjs');
const source = fs.readFileSync(require.resolve('./offline-app-browser-check.cjs'), 'utf8');
const memberFixture = source.match(/const tenantFixture = String.raw`([\s\S]*?)`;/)[1]
    .replace("return ['id' => $gathering->id, 'name'", "return ['publicId' => $gathering->public_id, 'startDate' => $gathering->start_date->format('Y-m-d'), 'id' => $gathering->id, 'name'");
(async () => {
    const fixture = runPhpJson(memberFixture);
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    try {
        const context = await browser.newContext({ baseURL: 'http://kmp.localhost:8080', viewport: { width: 1100, height: 650 } });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto('/members/login?method=password');
        await page.getByRole('textbox', { name: 'Email Address', exact: true }).fill(fixture.email);
        await page.getByLabel('Password', { exact: true }).fill(fixture.password);
        await page.getByRole('button', { name: 'Sign in', exact: true }).click();
        await page.waitForURL(url => /\/members\/view\//.test(url.pathname));
        const [year, month] = fixture.startDate.split('-');
        const calendarPath = '/gatherings/calendar?' + new URLSearchParams({ year, month, search: fixture.name });
        await page.goto(calendarPath);
        const calendarUrl = page.url();
        const gatheringLink = page.locator('a[data-action="click->gatherings-calendar#showQuickView"]').filter({ hasText: fixture.name }).first();
        const quick = page.locator('#gatheringQuickViewModal');
        const attendance = page.locator('#attendanceModal');
        await gatheringLink.focus();
        await page.keyboard.press('Enter');
        await expect(quick).toBeVisible();
        await quick.getByRole('button', { name: 'Mark Your Attendance', exact: true }).click();
        await expect(attendance).toBeVisible();
        await expect(quick).not.toBeVisible();
        await expect(attendance.getByLabel('Share with Kingdom', { exact: true })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(attendance).not.toBeVisible();
        await expect(gatheringLink).toBeFocused();
        // Repeated open/close catches competing modal instances and stale transitions.
        await gatheringLink.click();
        await quick.getByRole('button', { name: 'Mark Your Attendance', exact: true }).click();
        await expect(attendance).toBeVisible();
        await attendance.getByRole('button', { name: 'Register', exact: true }).click();
        await expect(attendance).not.toBeVisible();
        await expect(page.locator('.modal-backdrop')).toHaveCount(0);
        await expect(page.getByText('Your attendance has been registered.', { exact: true })).toBeVisible();
        await gatheringLink.click();
        await expect(quick.getByText("You're attending this gathering!", { exact: true })).toBeVisible();
        await quick.getByRole('link', { name: 'Full Details', exact: true }).click();
        await page.waitForURL(url => url.pathname === '/gatherings/view/' + fixture.publicId);
        const details = page.url();
        const back = page.getByRole('link', { name: 'Go back', exact: true }).first();
        await expect(back).toHaveAttribute('href', calendarPath);
        await page.locator('[data-bs-target="#attendGatheringModal"]').click();
        await expect(page.locator('#attendGatheringModal')).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(page.locator('#attendGatheringModal')).not.toBeVisible();
        // A headerless fragment and a modal index must not replace the calendar back target.
        await page.evaluate(async id => {
            await fetch('/gatherings/attendance-modal/' + id);
            await fetch('/grid-subscriptions', { headers: { 'Turbo-Frame': 'email-subscriptions' } });
        }, fixture.id);
        await page.reload();
        await expect(back).toHaveAttribute('href', calendarPath);
        await back.click();
        await expect(page).toHaveURL(calendarUrl);
        await expect(gatheringLink).toBeVisible();
        assert.notEqual(page.url(), details);
        await page.setViewportSize({ width: 375, height: 480 });
        await gatheringLink.click();
        await quick.getByRole('button', { name: 'Edit', exact: true }).click();
        await expect(attendance).toBeVisible();
        await expect(attendance.getByRole('button', { name: 'Update', exact: true })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(gatheringLink).toBeFocused();
        assert.deepEqual(errors, []);
        console.log('PASS: calendar quick view → RSVP → save/edit; repeated dialog transitions; desktop/mobile Escape and focus return; full-details RSVP → back to filtered calendar; fragment/index fetches and reload preserve the back URL');
    } finally {
        await browser.close();
        runPhpJson(memberFixture, { cleanup: fixture.id, name: fixture.name, memberId: fixture.memberId, email: fixture.email });
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
