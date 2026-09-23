/** Local synthetic acceptance for public RSVP scrolling, native forms, and confirmation. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright');
const { expect } = require('@playwright/test');
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');

const source = fs.readFileSync(require.resolve('./offline-app-browser-check.cjs'), 'utf8');
const fixtureMatch = source.match(/const tenantFixture = String.raw`([\s\S]*?)`;/);
assert.ok(fixtureMatch, 'tenantFixture not found in offline-app-browser-check.cjs');
const patch = (text, from, to) => {
    assert.ok(text.includes(from), `tenantFixture patch target not found: ${from}`);
    return text.replace(from, to);
};
let fixturePhp = fixtureMatch[1];
fixturePhp = patch(fixturePhp, "'name' => $name, 'branch_id'", "'public_page_enabled' => true, 'name' => $name, 'branch_id'");
fixturePhp = patch(fixturePhp, "return ['id' => $gathering->id", "return ['publicId' => $gathering->public_id, 'id' => $gathering->id");

(async () => {
    const fixture = runPhpJson(fixturePhp);
    let browser;
    try {
        browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
        const context = await browser.newContext({ baseURL: 'http://kmp.localhost:8080' });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const path = '/gatherings/public-landing/' + fixture.publicId;
        await page.goto(path);
        await expect(page.locator('#attendGatheringModal')).toHaveCount(0);
        await loginAs(page, fixture.email, fixture.password);

        const submit = async (action, trigger) => {
            const [response] = await Promise.all([
                page.waitForResponse(response => response.request().method() === 'POST'
                    && new URL(response.url()).pathname.startsWith('/gathering-attendances/' + action)),
                trigger(),
            ]);
            expect(response.status()).toBe(302);
            await page.waitForURL(url => url.pathname === path);
            await expect(page.locator('#attendGatheringModal')).not.toBeVisible();
        };

        for (const viewport of [{ width: 1100, height: 480 }, { width: 1280, height: 320 }, { width: 375, height: 667 }]) {
            await page.setViewportSize(viewport);
            await page.goto(path);
            const trigger = page.locator('[data-bs-target="#attendGatheringModal"]');
            const modal = page.locator('#attendGatheringModal');
            await trigger.focus();
            await page.keyboard.press('Enter');
            await expect(modal).toBeFocused();
            const body = modal.locator('.modal-body');
            expect(await body.evaluate(element => element.scrollHeight > element.clientHeight)).toBe(true);
            await body.hover();
            await page.mouse.wheel(0, 500);
            await expect.poll(() => body.evaluate(element => element.scrollTop)).toBeGreaterThan(0);
            await expect(modal.getByRole('button', { name: 'Register', exact: true })).toBeInViewport();
            // Escape and Cancel return focus to the public-page trigger.
            await page.keyboard.press('Escape');
            await expect(modal).not.toBeVisible();
            await expect(trigger).toBeFocused();
            await trigger.click();
            await modal.getByRole('button', { name: 'Cancel', exact: true }).click();
            await expect(modal).not.toBeVisible();
            await expect(trigger).toBeFocused();
            await trigger.click();
            await modal.getByLabel('Public Note', { exact: true }).fill('Synthetic public RSVP');
            const register = modal.getByRole('button', { name: 'Register', exact: true });
            await register.focus();
            await submit('add', () => page.keyboard.press('Enter'));
            await trigger.click();
            await expect(modal.getByLabel('Public Note', { exact: true })).toHaveValue('Synthetic public RSVP');
            await modal.getByLabel('Public Note', { exact: true }).fill('Updated from public landing');
            const update = modal.getByRole('button', { name: 'Update', exact: true });
            await expect(update).toBeInViewport();
            await page.screenshot({ path: `/tmp/kmp-public-rsvp-${viewport.width}x${viewport.height}.png` });
            await submit('edit', () => update.click());
            await trigger.click();
            await expect(modal.getByLabel('Public Note', { exact: true })).toHaveValue('Updated from public landing');
            await modal.getByLabel('Public Note', { exact: true }).fill('Updated with keyboard');
            await update.focus();
            await submit('edit', () => page.keyboard.press('Enter'));
            await trigger.click();
            await expect(modal.getByLabel('Public Note', { exact: true })).toHaveValue('Updated with keyboard');
            const remove = modal.getByRole('button', { name: 'Remove My Attendance', exact: true });
            await expect(remove).toBeInViewport();
            await remove.focus();
            await page.keyboard.press('Enter');
            const confirmation = page.getByRole('dialog', { name: 'Remove attendance registration', exact: true });
            await expect(confirmation).toBeVisible();
            await expect(confirmation.getByRole('button', { name: 'Remove', exact: true })).toBeFocused();
            await confirmation.getByRole('button', { name: 'Cancel', exact: true }).press('Enter');
            await expect(confirmation).toHaveCount(0);
            await expect(remove).toBeFocused();
            await remove.click();
            await expect(confirmation).toBeVisible();
            await expect(confirmation.getByRole('button', { name: 'Remove', exact: true })).toBeFocused();
            await submit('delete', () => confirmation.getByRole('button', { name: 'Remove', exact: true }).press('Enter'));
            await expect(trigger).toContainText('Attend This Gathering');
            console.log(`PASS ${viewport.width}x${viewport.height}: public RSVP scroll, register, update, remove/cancel, Escape, and focus return`);
        }
        assert.deepEqual(errors, []);
    } finally {
        try {
            await browser?.close();
        } finally {
            runPhpJson(fixturePhp, { cleanup: fixture.id, name: fixture.name, memberId: fixture.memberId, email: fixture.email });
        }
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
