/** Dataverse showcase review; keeps real records untouched and removes only views created here. */
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
const { chromium } = require(path.join(root, 'node_modules/playwright'));
const { expect } = require(path.join(root, 'node_modules/@playwright/test'));
const { loginAs } = require('./ui-helpers.cjs');

const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080';
const output = process.env.UI_PATTERNS_OUTPUT || path.join(root, 'tmp/ui-pattern-grid');
const gridKey = 'UiPatterns.collection.main';

/** Parse the downloaded CSV, including quoted commas and doubled quote escapes. */
function parseCsv(text) {
    const rows = [];
    let row = [], cell = '', quoted = false;
    for (let index = 0; index < text.length; index++) {
        const char = text[index];
        if (char === '"' && quoted && text[index + 1] === '"') { cell += '"'; index++; }
        else if (char === '"') quoted = !quoted;
        else if (char === ',' && !quoted) { row.push(cell); cell = ''; }
        else if (char === '\n' && !quoted) { row.push(cell.replace(/\r$/, '')); rows.push(row); row = []; cell = ''; }
        else cell += char;
    }
    if (cell || row.length) { row.push(cell); rows.push(row); }
    return rows;
}

(async () => {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const createdIds = new Set();
    const createdNames = new Set();
    const deletedIds = new Set();
    const errors = [], writes = [], screenshots = [], layoutFailures = [];
    let context, page, csrf, originalDefault;
    try {
        context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
        page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await loginAs(page, process.env.PLAYWRIGHT_ADMIN_EMAIL || 'admin@amp.ansteorra.org');
        await page.goto('/ui-patterns?pattern=collection&ignore_default=1', { waitUntil: 'networkidle' });
        const online = page.getByRole('button', { name: 'Use online only', exact: true });
        if (await online.isVisible()) await online.click();
        const frame = page.locator('#ui-patterns-grid-table');
        const state = () => frame.locator('script[type="application/json"]').evaluate(node => JSON.parse(node.textContent));
        const ready = async () => {
            await expect(frame.locator('script[type="application/json"]')).toHaveCount(1);
            await expect(page.locator('[data-view-tabs-container] [role="tab"]').first()).toBeVisible();
            await expect(frame).not.toHaveAttribute('aria-busy', 'true');
        };
        const change = async action => {
            const previousLoads = await page.evaluate(() => {
                if (window.__showcaseGridLoads === undefined) {
                    window.__showcaseGridLoads = 0;
                    document.addEventListener('turbo:frame-load', event => {
                        if (event.target.id === 'ui-patterns-grid-table') window.__showcaseGridLoads++;
                    });
                }
                return window.__showcaseGridLoads;
            });
            const loaded = page.waitForResponse(response => new URL(response.url()).pathname === '/ui-patterns/grid-data'
                && response.request().method() === 'GET' && !new URL(response.url()).searchParams.has('export'));
            await action();
            expect((await loaded).status()).toBe(200);
            await page.waitForFunction(previous => window.__showcaseGridLoads > previous, previousLoads);
            await ready();
        };
        const goAll = async () => {
            await page.goto('/ui-patterns?pattern=collection&ignore_default=1', { waitUntil: 'networkidle' });
            await ready();
        };
        const openFilters = async () => {
            const button = page.locator('[data-filter-button]');
            if (await button.getAttribute('aria-expanded') !== 'true') await button.click();
            await expect(button).toHaveAttribute('aria-expanded', 'true');
        };
        const chooseFilter = async (field, value) => {
            await openFilters();
            await page.locator(`[data-filter-nav-item][data-filter-key="${field}"]`).click();
            await change(() => page.locator(`input[data-filter-column="${field}"][value="${value}"]`).check());
            await expect(page.locator(`input[data-filter-column="${field}"][value="${value}"]`)).toBeFocused();
        };
        const quickSearch = async value => {
            await page.getByLabel('Search gatherings', { exact: true }).fill(value);
            await change(() => page.locator('.ui-grid-search').getByRole('button', { name: 'Search', exact: true }).click());
        };
        await ready();
        csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        originalDefault = (await state()).view.preferredId;
        const ordinaryBefore = await (await context.request.get('/grid-views?gridKey=Gatherings.index.main')).json();
        page.on('request', request => {
            if (request.method() === 'POST') writes.push({ path: new URL(request.url()).pathname, body: request.postDataJSON() });
        });

        // Same grid controls remain reachable without horizontal page overflow at each size.
        for (const width of [1440, 390, 320]) {
            await page.setViewportSize({ width, height: 1000 });
            await goAll();
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
            await page.screenshot({ path: path.join(output, `dataverse-${width}.png`), animations: 'disabled' });
            screenshots.push(`dataverse-${width}.png`);
            await openFilters();
            const menu = page.locator('[data-filter-button]').locator('..').locator('.dropdown-menu');
            const box = await menu.boundingBox();
            expect(box.x).toBeGreaterThanOrEqual(0);
            expect(box.x + box.width).toBeLessThanOrEqual(width + 1);
            await page.screenshot({ path: path.join(output, `dataverse-filters-${width}.png`), animations: 'disabled' });
            await page.keyboard.press('Escape');
            await expect(menu).not.toBeVisible();
            await expect(page.locator('[data-filter-button]')).toBeFocused();
        }

        // Keyboard sorting and frame pagination keep the collection context.
        await page.setViewportSize({ width: 1440, height: 1000 });
        await goAll();
        expect((await state()).pagination.totalCount).toBe(14);
        const displayOptions = page.locator('[data-ui-pattern-grid-target="displayOptions"]');
        if (!await displayOptions.evaluate(node => node.open)) await displayOptions.locator('summary').click();
        await page.getByRole('button', { name: 'Select page', exact: true }).click();
        await expect(frame.locator('tbody input[type="checkbox"]:checked')).toHaveCount(10);
        await expect(page.locator('[data-ui-pattern-grid-target="selectionCount"]')).toHaveText('10 selected on this page');
        await page.getByRole('button', { name: 'Clear selection', exact: true }).click();
        await expect(frame.locator('tbody input[type="checkbox"]:checked')).toHaveCount(0);
        await change(() => page.getByRole('link', { name: 'Page 2', exact: true }).click());
        expect((await state()).pagination.currentPage).toBe(2);
        await change(() => page.getByRole('link', { name: 'Page 1', exact: true }).click());
        await change(() => frame.locator('thead button[data-column-key="name"]').press('Enter'));
        await expect(frame.locator('thead button[data-column-key="name"]')).toBeFocused();
        expect((await state()).sort).toEqual({ field: 'name', direction: 'asc' });
        expect(new URL(page.url()).searchParams.get('pattern')).toBe('collection');
        const tabs = page.locator('[data-view-tabs-container] [role="tab"]');
        await tabs.first().focus();
        await page.keyboard.press('End');
        await expect(tabs.last()).toBeFocused();
        await page.keyboard.press('Home');
        await expect(tabs.first()).toBeFocused();

        // OR within branch/activity filters, AND across fields, plus real date-range controls.
        await page.setViewportSize({ width: 390, height: 900 });
        await goAll();
        await chooseFilter('branch', 'oakmere');
        await chooseFilter('branch', 'willowford');
        expect((await state()).filters.active.branch.sort()).toEqual(['oakmere', 'willowford']);
        expect((await state()).pagination.totalCount).toBe(11);
        await chooseFilter('activity', 'court');
        await chooseFilter('activity', 'rapier');
        expect((await state()).pagination.totalCount).toBe(4);
        await chooseFilter('royal_progress', 'crown-ansteorra');
        expect((await state()).pagination.totalCount).toBe(2);
        await openFilters();
        await page.locator('[data-filter-nav-item][data-filter-key="start_date"]').click();
        await change(() => page.getByLabel('From', { exact: true }).fill('2026-12-01'));
        await change(() => page.getByLabel('To', { exact: true }).fill('2026-12-31'));
        expect((await state()).pagination.totalCount).toBe(1);
        await page.keyboard.press('Escape');
        await expect(frame.getByRole('link', { name: 'Oakmere Winter Court', exact: true })).toBeVisible();

        // Cards and table reuse one selection, including selection after changing presentation.
        const checkbox = frame.getByRole('checkbox', { name: 'Select Oakmere Winter Court', exact: true });
        await checkbox.check();
        await expect(page.locator('[data-ui-pattern-grid-target="selectionCount"]')).toHaveText('1 selected on this page');
        await page.getByText('Display & selection', { exact: true }).click();
        await page.getByRole('button', { name: 'Table', exact: true }).click();
        await expect(checkbox).toBeChecked();
        await page.getByRole('button', { name: 'Cards', exact: true }).click();
        await expect(frame.locator('tbody input[type="checkbox"]')).toHaveCount(1);
        await page.getByRole('button', { name: 'Review selected gatherings', exact: true }).click();
        await expect(page.locator('#showcase-grid-selection')).toContainText('Oakmere Winter Court');
        await page.locator('#showcase-grid-selection').getByRole('button', { name: 'Done', exact: true }).click();
        await expect(page.locator('#showcase-grid-selection')).not.toBeVisible();
        await openFilters();
        await change(() => page.getByRole('button', { name: 'Clear all filters', exact: true }).click());
        expect((await state()).pagination.totalCount).toBe(14);
        await page.keyboard.press('Escape');
        await expect(page.locator('[data-ui-pattern-grid-target="selectionBar"]')).not.toBeVisible();

        // Column visibility/order drive real CSV output, including rows beyond the current page.
        await page.getByRole('button', { name: 'Columns', exact: true }).click();
        const columns = page.locator('[id="columnPickerModal-UiPatterns.collection.main"]');
        await expect(columns).toBeVisible();
        await columns.getByRole('checkbox', { name: 'Show Branch column', exact: true }).uncheck();
        await columns.getByRole('checkbox', { name: 'Show Location column', exact: true }).check();
        await columns.getByRole('button', { name: 'Move Location column up', exact: true }).click();
        const expectedColumns = await columns.locator('[data-sortable-list-target="item"]').evaluateAll(items => items
            .filter(item => item.querySelector('input').checked).map(item => item.dataset.columnKey));
        await page.screenshot({ path: path.join(output, 'dataverse-columns-390.png'), animations: 'disabled' });
        await change(() => columns.getByRole('button', { name: 'Apply', exact: true }).click());
        await expect(columns).not.toBeVisible();
        expect((await state()).columns.visible).toEqual(expectedColumns);
        const downloadPromise = page.waitForEvent('download');
        await page.getByRole('button', { name: 'Export CSV', exact: true }).click();
        const download = await downloadPromise;
        const csvPath = path.join(output, 'showcase-export.csv');
        await download.saveAs(csvPath);
        const csv = parseCsv(fs.readFileSync(csvPath, 'utf8'));
        const current = await state();
        expect(csv[0]).toEqual(expectedColumns.map(key => current.columns.all[key].label));
        expect(csv.length - 1).toBe(14);
        expect(csv.every(row => row.length === expectedColumns.length)).toBe(true);

        // Save/search/reopen/update/default/delete uses the real personal view service.
        await chooseFilter('branch', 'oakmere');
        await page.keyboard.press('Escape');
        await quickSearch('oakmere');
        await page.getByRole('button', { name: 'Table', exact: true }).click();
        await change(() => frame.locator('thead button[data-column-key="name"]').press('Enter'));
        if ((await state()).sort.direction !== 'desc') {
            await change(() => frame.locator('thead button[data-column-key="name"]').press('Enter'));
        }
        expect((await state()).sort).toEqual({ field: 'name', direction: 'desc' });
        await page.getByRole('button', { name: 'Cards', exact: true }).click();
        expect((await state()).sort).toEqual({ field: 'name', direction: 'desc' });
        await change(() => page.getByLabel('Rows per page', { exact: true }).selectOption('25'));
        const name = `Showcase browser review ${Date.now()}`;
        createdNames.add(name);
        await page.getByRole('button', { name: 'Save current settings as a new view', exact: true }).click();
        const saveDialog = page.getByRole('dialog', { name: 'Save view', exact: true });
        await saveDialog.getByLabel('View name', { exact: true }).fill(name);
        const savedResponse = page.waitForResponse(response => new URL(response.url()).pathname === '/grid-views/add');
        await saveDialog.getByRole('button', { name: 'Save view', exact: true }).click();
        expect((await savedResponse).status()).toBe(200);
        await page.waitForURL(url => !!url.searchParams.get('view_id') && !['all', 'published', 'royal-progress', 'drafts'].includes(url.searchParams.get('view_id')));
        const id = new URL(page.url()).searchParams.get('view_id');
        createdIds.add(id);
        await page.goto(`/ui-patterns?pattern=collection&view_id=${id}`, { waitUntil: 'networkidle' });
        await ready();
        expect((await state()).search).toBe('oakmere');
        expect((await state()).columns.visible).toEqual(expectedColumns);
        expect((await state()).filters.active.branch).toEqual(['oakmere']);
        expect((await state()).sort).toEqual({ field: 'name', direction: 'desc' });
        expect((await state()).config.pageSize).toBe(25);
        await quickSearch('archery');
        await expect(page.locator('[data-ui-pattern-grid-target="viewStatus"]')).toContainText('Unsaved changes');
        await change(() => page.getByRole('button', { name: 'Restore saved view', exact: true }).click());
        await expect(page.getByLabel('Search gatherings', { exact: true })).toHaveValue('oakmere');
        await quickSearch('archery');
        await page.getByRole('button', { name: `View options: ${name}`, exact: true }).click();
        await page.getByRole('button', { name: 'Update View', exact: true }).click();
        const updateDialog = page.getByRole('dialog', { name: 'Update view', exact: true });
        await change(() => updateDialog.getByRole('button', { name: 'Update view', exact: true }).click());
        await page.goto(`/ui-patterns?pattern=collection&view_id=${id}`, { waitUntil: 'networkidle' });
        await ready();
        expect((await state()).search).toBe('archery');
        expect((await state()).view.hasChanges).toBe(false);
        await page.getByRole('button', { name: `View options: ${name}`, exact: true }).click();
        await change(() => page.getByRole('button', { name: 'Set as Default', exact: true }).click());
        await page.goto('/ui-patterns?pattern=collection', { waitUntil: 'networkidle' });
        await ready();
        expect(String((await state()).view.currentId)).toBe(id);
        await expect(page.getByRole('tab', { name: new RegExp(`${name}.*Default view`) })).toHaveAttribute('aria-selected', 'true');
        await page.screenshot({ path: path.join(output, 'dataverse-saved-view-390.png'), animations: 'disabled' });
        const savedViewWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        if (savedViewWidth > 390) {
            layoutFailures.push({ width: savedViewWidth, expected: 390 });
            console.log('Saved-view overflow:', await page.locator('.ui-dataverse *').evaluateAll(nodes => nodes
                .filter(node => node.getBoundingClientRect().right > 390)
                .map(node => ({ tag: node.tagName, class: node.className, text: node.textContent.trim().slice(0, 100) })).slice(0, 12)));
        }
        const filteredDownloadPromise = page.waitForEvent('download');
        await page.getByRole('button', { name: 'Export CSV', exact: true }).click();
        const filteredDownload = await filteredDownloadPromise;
        const filteredPath = path.join(output, 'showcase-filtered-export.csv');
        await filteredDownload.saveAs(filteredPath);
        const filteredCsv = parseCsv(fs.readFileSync(filteredPath, 'utf8'));
        expect(filteredCsv).toHaveLength(2);
        expect(filteredCsv[1][filteredCsv[0].indexOf('Gathering')]).toBe('Oakmere Archery Practice');
        await page.screenshot({ path: path.join(output, 'dataverse-saved-view-390.png'), animations: 'disabled' });
        await page.getByRole('button', { name: `View options: ${name}`, exact: true }).click();
        await page.getByRole('button', { name: 'Delete View', exact: true }).click();
        const deleteResponse = page.waitForResponse(response => new URL(response.url()).pathname === `/grid-views/delete/${id}`);
        await page.getByRole('dialog', { name: 'Delete view', exact: true }).getByRole('button', { name: 'Delete view', exact: true }).click();
        expect((await deleteResponse).status()).toBe(200);
        await page.waitForURL(url => url.searchParams.get('view_id') !== id);
        await ready();
        await expect(page.locator('[data-view-tabs-container]')).not.toContainText(name);
        const remainingViews = await (await context.request.get(`/grid-views?gridKey=${gridKey}`)).json();
        expect(remainingViews.data.views.some(view => String(view.id) === id)).toBe(false);
        deletedIds.add(id);

        // The same grid also works inside a record tab without dropping its locked branch or host section.
        await page.goto('/ui-patterns?pattern=record&record=branch', { waitUntil: 'networkidle' });
        await page.getByRole('tab', { name: 'Gatherings', exact: true }).click();
        await ready();
        expect((await state()).config.gridKey).toBe('UiPatterns.branch.gatherings');
        expect((await state()).filters.active.branch).toEqual(['oakmere']);
        await openFilters();
        await page.locator('[data-filter-nav-item][data-filter-key="branch"]').click();
        await expect(page.locator('input[data-filter-column="branch"][value="oakmere"]')).toBeDisabled();
        await expect(page.locator('input[data-filter-column="branch"][value="oakmere"]')).toBeChecked();
        await page.keyboard.press('Escape');
        await change(() => page.locator('.ui-dataverse').getByRole('tab', { name: 'Royal Progress', exact: true }).click());
        expect((await state()).filters.active.branch).toEqual(['oakmere']);
        const embeddedUrl = new URL(page.url());
        expect(embeddedUrl.searchParams.get('pattern')).toBe('record');
        expect(embeddedUrl.searchParams.get('record')).toBe('branch');
        expect(embeddedUrl.searchParams.get('section')).toBe('record-branch-gatherings-tab');
        const embeddedView = String((await state()).view.currentId);
        await frame.getByRole('link', { name: 'Oakmere Winter Court', exact: true }).click();
        await page.waitForURL(url => url.pathname === '/ui-patterns/reader');
        await expect(page.locator('.ui-page-header h1')).toHaveText('Oakmere Winter Court');
        await page.getByRole('link', { name: 'Go back', exact: true }).click();
        await page.waitForURL(url => url.searchParams.get('pattern') === 'record');
        await ready();
        await expect(page.getByRole('tab', { name: 'Gatherings', exact: true })).toHaveAttribute('aria-selected', 'true');
        expect((await state()).filters.active.branch).toEqual(['oakmere']);
        expect(String((await state()).view.currentId)).toBe(embeddedView);
        expect(new URL(page.url()).searchParams.get('section')).toBe('record-branch-gatherings-tab');
        for (const width of [390, 1440]) {
            await page.setViewportSize({ width, height: 1000 });
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
            await page.screenshot({ path: path.join(output, `dataverse-branch-record-${width}.png`), fullPage: true, animations: 'disabled' });
            screenshots.push(`dataverse-branch-record-${width}.png`);
        }
        const ordinaryAfter = await (await context.request.get('/grid-views?gridKey=Gatherings.index.main')).json();
        expect(ordinaryAfter).toEqual(ordinaryBefore);
        expect(writes.every(write => write.path.startsWith('/grid-views/')
            && (!write.body.gridKey || write.body.gridKey === gridKey))).toBe(true);
        expect(errors).toEqual([]);
        expect(layoutFailures).toEqual([]);
        fs.writeFileSync(path.join(output, 'dataverse-results.json'), JSON.stringify({ screenshots, errors,
            writes: writes.map(write => write.path), checks: 'responsive filters, keyboard tabs/sort, pagination, same-row selection, CSV columns and full result scope, saved-view lifecycle and namespace isolation' }, null, 2));
        console.log('PASS: real Dataverse filters, saved views, defaults, columns, export, responsive controls and isolation.');
    } finally {
        try {
            if (context && csrf) {
                const post = (route, data) => context.request.post(route, { data, headers: {
                    'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json',
                } });
                // A full-page navigation can dispose the POST response before Playwright reads
                // it. Discover this run's exact unique names so even that failure is cleaned up.
                const remaining = await (await context.request.get(`/grid-views?gridKey=${gridKey}`)).json();
                for (const view of remaining.data.views) {
                    if (createdNames.has(view.name)) createdIds.add(String(view.id));
                }
                if (originalDefault !== undefined) {
                    const route = originalDefault === null ? '/grid-views/clear-default' : `/grid-views/set-default/${originalDefault}`;
                    const response = await post(route, { gridKey });
                    expect(response.ok(), 'Restore the original showcase default').toBe(true);
                    expect((await response.json()).success).toBe(true);
                }
                for (const id of createdIds) {
                    if (!deletedIds.has(id)) {
                        const response = await post(`/grid-views/delete/${id}`, { gridKey });
                        expect(response.ok(), `Remove test-created view ${id}`).toBe(true);
                        expect((await response.json()).success).toBe(true);
                    }
                }
                const afterCleanup = await (await context.request.get(`/grid-views?gridKey=${gridKey}`)).json();
                expect(afterCleanup.data.views.some(view => createdNames.has(view.name))).toBe(false);
            }
        } finally {
            await browser.close();
        }
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
