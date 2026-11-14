/** Real list/record/Back journeys, including direct-open fallback, without mutations. */
const assert = require('node:assert/strict');
const { chromium, firefox, webkit, expect } = require('@playwright/test');
const { loginAs } = require('./ui-helpers.cjs');
const { getUiTestEnvironment } = require('./test-environment.cjs');

(async () => {
    const engine = process.env.PLAYWRIGHT_BROWSER || 'chromium';
    const browser = await ({ chromium, firefox, webkit })[engine].launch({ headless: true });
    const context = await browser.newContext({ baseURL: getUiTestEnvironment().baseUrl, reducedMotion: 'reduce', extraHTTPHeaders: { 'X-KMP-E2E': '1' } });
    const page = await context.newPage();
    if (process.env.PLAYWRIGHT_DIAGNOSTICS === '1') {
        page.on('requestfailed', request => console.error('Failed request', new URL(request.url()).pathname, request.failure()));
        page.on('console', message => { if (message.type() === 'error') console.error(message.text()); });
    }
    const errors = [];
    page.on('pageerror', error => {
        errors.push(error.message);
        if (process.env.PLAYWRIGHT_DIAGNOSTICS === '1') console.error(error.stack);
    });
    try {
        await loginAs(page, process.env.PLAYWRIGHT_ADMIN_EMAIL || 'admin@amp.ansteorra.org');
        await page.goto('/members?page=3&limit=10');
        const frame = page.locator('turbo-frame[id$="-table"]').first();
        const record = frame.locator('tbody a[href*="/members/view/"]').first();
        await expect(record).toBeVisible();
        await expect(frame).not.toHaveAttribute('aria-busy', 'true');
        const sourceUrl = page.url();
        const recordUrl = await record.getAttribute('href');
        const enabled = await page.locator('meta[name="kmp-turbo-enabled"]').getAttribute('content') === 'true';
        const marker = await page.evaluate(() => { window.__navigationCheck = crypto.randomUUID(); return window.__navigationCheck; });
        await record.click();
        await expect(page.locator('[data-kmp-back]').first()).toBeVisible();
        if (enabled) assert.equal(await page.evaluate(() => window.__navigationCheck), marker, 'record navigation must retain the Turbo runtime');
        const tabs = page.locator('[data-detail-tabs-target="tabBtn"]');
        if (await tabs.count() > 2) {
            await tabs.nth(1).click();
            await tabs.nth(2).click();
        }
        await page.locator('[data-kmp-back]').first().click();
        await expect(page).toHaveURL(sourceUrl);
        await expect(page.locator('turbo-frame[id$="-table"]').first().locator('tbody tr').first()).toBeVisible();
        assert.equal(new URL(page.url()).searchParams.get('page'), '3');
        assert.equal(new URL(page.url()).searchParams.get('limit'), '10');

        const direct = await context.newPage();
        await direct.goto(recordUrl);
        const fallback = direct.locator('[data-kmp-back]').first();
        await expect(fallback).toHaveAttribute('href', '/members');
        await fallback.click();
        await expect(direct).toHaveURL(/\/members(?:\?|$)/);
        await direct.close();
        assert.deepEqual(errors, []);
        console.log(`PASS ${engine}, Drive ${enabled ? 'enabled' : 'disabled'}: real Back skips local tabs and preserves list query; direct records use the list fallback`);
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exit(1); });
