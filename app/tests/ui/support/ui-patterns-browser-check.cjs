/** Non-destructive showcase checks against the existing local tenant; no fixture reset. */
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
const { chromium } = require(path.join(root, 'node_modules/playwright'));
const { expect } = require(path.join(root, 'node_modules/@playwright/test'));
const { loginAs } = require('./ui-helpers.cjs');

const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080';
const output = process.env.UI_PATTERNS_OUTPUT || path.join(root, 'tmp/ui-pattern-showcase');
const patterns = ['collection', 'record', 'reader', 'task', 'workspace', 'dashboard'];

(async () => {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const results = [];
    const errors = [];
    try {
        const context = await browser.newContext({
            baseURL, viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce',
        });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await loginAs(page, process.env.PLAYWRIGHT_ADMIN_EMAIL || 'admin@amp.ansteorra.org');
        await page.goto('/ui-patterns', { waitUntil: 'networkidle' });
        const online = page.getByRole('button', { name: 'Use online only', exact: true });
        if (await online.isVisible()) await online.click();

        // Review every family at desktop, normal phone, and narrow phone widths.
        for (const pattern of patterns) {
            for (const width of [1440, 390, 320]) {
                await page.setViewportSize({ width, height: 1000 });
                const response = await page.goto(`/ui-patterns?pattern=${pattern}`, { waitUntil: 'networkidle' });
                expect(response.status()).toBe(200);
                await expect(page.locator('.ui-page-header h1')).toBeVisible();
                const size = await page.evaluate(() => ({
                    width: window.innerWidth, scroll: document.documentElement.scrollWidth,
                }));
                expect(size.scroll, `${pattern} at ${width}px must fit the page`).toBeLessThanOrEqual(size.width);
                results.push({ pattern, width, ...size });
                await page.screenshot({ path: path.join(output, `${pattern}-${width}.png`), animations: 'disabled' });
            }
        }
        for (const record of ['member', 'role']) {
            for (const width of [1440, 390, 320]) {
                await page.setViewportSize({ width, height: 1000 });
                await page.goto(`/ui-patterns?pattern=record&record=${record}`, { waitUntil: 'networkidle' });
                await expect(page.locator('.ui-page-header h1')).toBeVisible();
                expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                await page.screenshot({ path: path.join(output, `record-${record}-${width}.png`), animations: 'disabled' });
            }
        }
        // Every review state remains usable without exposing an application write path.
        for (const pattern of patterns) {
            for (const state of ['empty', 'error', 'restricted']) {
                const response = await page.goto(`/ui-patterns?pattern=${pattern}&state=${state}`);
                expect(response.status()).toBe(200);
                await expect(page.locator('[data-example-state]')).toHaveAttribute('data-example-state', state);
                expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
            }
        }

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/ui-patterns?pattern=collection&record=branch&ignore_default=1');
        const gridFrame = page.locator('#ui-patterns-grid-table');
        await page.getByLabel('Search gatherings', { exact: true }).fill('Winter Court');
        await page.locator('.ui-grid-search').getByRole('button', { name: 'Search', exact: true }).click();
        await expect(gridFrame.locator('tbody tr[data-id]')).toHaveCount(1);
        await page.reload();
        await expect(page.getByLabel('Search gatherings', { exact: true })).toHaveValue('Winter Court');
        const card = gridFrame.locator('tr[data-id="winter-court"]');
        await card.getByRole('checkbox').check();
        await expect(page.locator('[data-ui-pattern-grid-target="selectionCount"]')).toHaveText('1 selected on this page');
        await page.getByText('Display & selection', { exact: true }).click();
        await page.getByRole('button', { name: 'Table', exact: true }).click();
        await expect(page.locator('.ui-dataverse')).toHaveAttribute('data-grid-layout', 'table');
        await expect(card.getByRole('checkbox')).toBeChecked();
        await page.getByRole('button', { name: 'Cards', exact: true }).click();
        const filterButton = page.locator('[data-filter-button]');
        await filterButton.click();
        const filters = filterButton.locator('..').locator('.dropdown-menu');
        await expect(filters).toBeVisible();
        const box = await filters.boundingBox();
        expect(box.x).toBeGreaterThanOrEqual(0);
        expect(box.x + box.width).toBeLessThanOrEqual(391);
        await page.keyboard.press('Escape');
        await expect(filters).not.toBeVisible();
        await expect(filterButton).toBeFocused();

        // Back returns to the searched Dataverse collection, including its query state.
        await card.getByRole('link', { name: 'Oakmere Winter Court', exact: true }).click();
        await page.getByRole('link', { name: 'Go back', exact: true }).click();
        await expect(page.getByLabel('Search gatherings', { exact: true })).toHaveValue('Winter Court');

        await page.goto('/ui-patterns?pattern=record&record=role&q=preserve');
        await page.getByRole('tab', { name: 'Members', exact: true }).press('Enter');
        expect(new URL(page.url()).searchParams.get('q')).toBe('preserve');
        await expect(page.getByRole('tab', { name: 'Members', exact: true })).toHaveAttribute('aria-selected', 'true');
        await page.getByRole('tab', { name: 'Permissions', exact: true }).press('Enter');
        await page.getByRole('button', { name: 'Add permission', exact: true }).click();
        const permissionDialog = page.locator('#record-add-permission');
        await permissionDialog.getByLabel('Permission', { exact: true }).selectOption('Manage gathering activities');
        await permissionDialog.getByRole('button', { name: 'Add permission', exact: true }).click();
        await expect(permissionDialog).not.toBeVisible();
        await expect(page.locator('[data-ui-patterns-target="permissions"]')).toContainText('Manage gathering activities');

        const writes = [];
        page.on('request', request => {
            if (request.method() !== 'GET') writes.push(new URL(request.url()).pathname);
        });
        await page.goto('/ui-patterns?pattern=reader');
        await page.getByRole('button', { name: 'Attend this gathering', exact: true }).click();
        await page.locator('#reader-attendance').getByRole('button', { name: 'Save RSVP', exact: true }).click();
        await expect(page.locator('#reader-attendance')).not.toBeVisible();
        await expect(page.locator('#reader-rsvp-summary')).toContainText('Attending');
        await page.getByRole('link', { name: 'Schedule', exact: true }).click();
        await expect.poll(async () => page.locator('#reader-schedule').evaluate(node => {
            const headerBottom = document.querySelector('.ui-app-header').getBoundingClientRect().bottom;
            const feedbackBottom = document.querySelector('.ui-feedback').getBoundingClientRect().bottom;
            return node.getBoundingClientRect().top - Math.max(headerBottom, feedbackBottom);
        })).toBeGreaterThanOrEqual(0);

        await page.goto('/ui-patterns?pattern=task');
        await page.getByRole('button', { name: 'Continue', exact: true }).click();
        await expect(page.locator('#task-file-title')).toBeFocused();
        await page.getByRole('button', { name: 'Continue', exact: true }).click();
        await expect(page.locator('#task-step-2')).toBeVisible();
        await page.getByRole('button', { name: 'Use a sample waiver for this review', exact: true }).click();
        await page.getByRole('button', { name: 'Continue', exact: true }).click();
        await page.getByLabel('I checked every page is present', { exact: false }).check();
        await page.getByRole('button', { name: 'Submit waiver', exact: true }).click();
        await expect(page.locator('[data-ui-patterns-target="completionHeading"]')).toBeFocused();
        await expect(page.locator('[data-ui-patterns-target="taskComplete"]')).toBeVisible();

        await page.goto('/ui-patterns?pattern=workspace');
        const first = page.locator('[data-court="kingdom"] [data-ui-patterns-target="agendaItem"]').first();
        const title = await first.locator('h3').innerText();
        await first.getByRole('button', { name: `Move ${title} down`, exact: true }).click();
        await expect(page.locator('[data-court="kingdom"] [data-ui-patterns-target="agendaItem"]').nth(1)).toContainText(title);
        await page.getByRole('button', { name: 'Add to court', exact: true }).click();
        const courtTools = page.locator('#workspace-tools');
        await expect(courtTools).toBeVisible();
        await expect(courtTools).toHaveClass(/\bshow\b/);
        await courtTools.getByRole('button', { name: 'Add Rowan of Willowbank to this court', exact: true }).click();
        await page.keyboard.press('Escape');
        await expect(courtTools).not.toBeVisible();
        await expect(page.locator('[data-court="kingdom"]')).toContainText('Rowan of Willowbank');

        await page.getByRole('button', { name: 'Open application navigation', exact: true }).click();
        await expect(page.locator('#ui-app-navigation')).toBeVisible();
        await expect(page.locator('#ui-app-navigation')).toHaveClass(/\bshow\b/);
        await page.keyboard.press('Escape');
        await expect(page.locator('#ui-app-navigation')).not.toBeVisible();
        await expect(page.getByRole('button', { name: 'Open application navigation', exact: true })).toBeFocused();
        expect(writes).toEqual([]);
        expect(errors).toEqual([]);
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ results, errors, writes }, null, 2));
        console.log('PASS: six families, responsive states, navigation, collection tools, dialogs, waiver task, court tools; no writes.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
