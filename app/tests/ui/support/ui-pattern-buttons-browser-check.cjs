/** Read-only button/filter review using fictional showcase rows; no fixture reset or saved-view writes. */
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
const { chromium } = require(path.join(root, 'node_modules/playwright'));
const { expect } = require(path.join(root, 'node_modules/@playwright/test'));
const { loginAs } = require('./ui-helpers.cjs');

const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080';
const output = process.env.UI_PATTERNS_OUTPUT || path.join(root, 'tmp/ui-pattern-buttons');
const contentCapture = { animations: 'disabled', style: '.ui-app-header, #debug-kit-toolbar, iframe[src*="/debug-kit/toolbar/"] { visibility: hidden !important; }' };

/** Catch cropped labels/icons and inaccessible controls without treating multiline labels as failures. */
async function buttonMetrics(scope) {
    return scope.evaluate(element => [...element.querySelectorAll('.btn, .btn-close, .ui-stepper button, [role="tab"]')]
        .filter(node => node.checkVisibility({ checkVisibilityCSS: true }))
        .map(node => {
            const box = node.getBoundingClientRect();
            const tablist = node.getAttribute('role') === 'tab' ? node.closest('.nav-tabs') : null;
            const scrollableTab = Boolean(tablist && ['auto', 'scroll'].includes(getComputedStyle(tablist).overflowX)
                && tablist.scrollWidth > tablist.clientWidth);
            const icons = [...node.querySelectorAll('kmp-icon')].map(icon => {
                const rect = icon.getBoundingClientRect();
                return { left: rect.left, right: rect.right, top: rect.top, bottom: rect.bottom };
            });
            return { name: node.getAttribute('aria-label') || node.textContent.trim() || node.title,
                left: box.left, right: box.right, top: box.top, bottom: box.bottom,
                width: box.width, height: box.height, contentOverflow: node.scrollWidth - node.clientWidth,
                icons, scrollableTab, close: node.classList.contains('btn-close') };
        }));
}

function checkButtons(buttons, width) {
    for (const button of buttons) {
        expect(button.name, 'Every visible control has a name').toBeTruthy();
        expect(button.width, `${button.name}: target width`).toBeGreaterThanOrEqual(24);
        expect(button.height, `${button.name}: target height`).toBeGreaterThanOrEqual(24);
        if (!button.scrollableTab) {
            expect(button.left, `${button.name}: left edge`).toBeGreaterThanOrEqual(-1);
            expect(button.right, `${button.name}: right edge`).toBeLessThanOrEqual(width + 1);
        }
        expect(button.contentOverflow, `${button.name}: label is not cropped`).toBeLessThanOrEqual(1);
        if (button.close) {
            expect(button.width).toBe(44);
            expect(button.height).toBe(44);
        }
        for (const icon of button.icons) {
            expect(icon.left, `${button.name}: icon fits`).toBeGreaterThanOrEqual(button.left - 1);
            expect(icon.right, `${button.name}: icon fits`).toBeLessThanOrEqual(button.right + 1);
            expect(icon.top).toBeGreaterThanOrEqual(button.top - 1);
            expect(icon.bottom).toBeLessThanOrEqual(button.bottom + 1);
        }
    }
}

/** Expose the whole tab inside both its scroll strip and the viewport. */
async function checkTabVisible(tab, width) {
    await expect.poll(() => tab.evaluate((node, viewportWidth) => {
        const box = node.getBoundingClientRect();
        const strip = node.closest('[role="tablist"]').getBoundingClientRect();
        return box.left >= Math.max(0, strip.left) - 1 && box.right <= Math.min(viewportWidth, strip.right) + 1;
    }, width), { message: `Focused ${await tab.innerText()} tab is fully visible at ${width}px` }).toBe(true);
}

/** Focus and native Bootstrap arrows must expose tabs in horizontal section strips. */
async function checkScrollableTabs(scope, width) {
    for (const tab of await scope.locator('.nav-tabs [role="tab"]').all()) {
        if (!(await tab.isVisible())) continue;
        await tab.focus();
        await expect(tab).toBeFocused();
        await checkTabVisible(tab, width);
    }
    const sections = scope.locator('.ui-section-nav [role="tab"]');
    if (await sections.count()) {
        await sections.first().focus();
        await sections.first().press('Home');
        for (let index = 1; index < await sections.count(); index++) {
            await sections.nth(index - 1).press('ArrowRight');
            await expect(sections.nth(index)).toBeFocused();
            await expect(sections.nth(index)).toHaveAttribute('aria-selected', 'true');
            await checkTabVisible(sections.nth(index), width);
        }
        await sections.last().press('Home');
        await expect(sections.first()).toBeFocused();
        await checkTabVisible(sections.first(), width);
    }
    for (const active of await scope.locator('.nav-tabs [role="tab"][aria-selected="true"]').all()) {
        if (await active.isVisible()) await active.focus();
    }
}

/** Wait for responsive reflow and report only fictional showcase content when it fails. */
async function checkPageBounds(page, width, label) {
    try {
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth), { message: label })
            .toBeLessThanOrEqual(width);
    } catch (error) {
        console.error(label, await page.evaluate(() => ({
            documentWidth: document.documentElement.scrollWidth,
            active: document.activeElement?.className,
            overflow: [...document.querySelectorAll('.ui-review-bar *, .ui-pattern-content *')]
                .filter(node => node.checkVisibility({ checkVisibilityCSS: true }) && !node.closest('.visually-hidden'))
                .filter(node => node.getBoundingClientRect().right > innerWidth + 1 || node.scrollWidth > node.clientWidth + 1)
                .map(node => ({ tag: node.tagName, class: node.className, text: node.textContent.trim().slice(0, 90),
                    right: node.getBoundingClientRect().right, width: node.clientWidth, scroll: node.scrollWidth,
                    whiteSpace: getComputedStyle(node).whiteSpace })).slice(0, 20),
        })));
        throw error;
    }
}

(async () => {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const errors = [], writes = [], results = [];
    try {
        const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await loginAs(page, process.env.PLAYWRIGHT_ADMIN_EMAIL || 'admin@amp.ansteorra.org');
        page.on('request', request => { if (!['GET', 'HEAD'].includes(request.method())) writes.push(new URL(request.url()).pathname); });
        const frame = page.locator('#ui-patterns-grid-table');
        const state = () => frame.locator('script[type="application/json"]').evaluate(node => JSON.parse(node.textContent));
        const ready = async () => {
            await expect(frame.locator('script[type="application/json"]')).toHaveCount(1);
            await expect(page.locator('[data-view-tabs-container] [role="tab"]').first()).toBeVisible();
            await expect(frame).not.toHaveAttribute('aria-busy', 'true');
        };
        const visit = async url => {
            await page.goto(url, { waitUntil: 'networkidle' });
            const online = page.getByRole('button', { name: 'Use online only', exact: true });
            if (await online.isVisible()) await online.click();
        };
        const change = async action => {
            const before = await page.evaluate(() => {
                if (window.__buttonReviewLoads === undefined) {
                    window.__buttonReviewLoads = 0;
                    document.addEventListener('turbo:frame-load', event => {
                        if (event.target.id === 'ui-patterns-grid-table') window.__buttonReviewLoads++;
                    });
                }
                return window.__buttonReviewLoads;
            });
            await action();
            await page.waitForFunction(before => window.__buttonReviewLoads > before, before);
            await ready();
        };
        const remove = value => page.locator(`button.grid-view-filter-badge-control[data-filter-column="royal_progress"][data-filter-value="${value}"]`);
        const openFilters = async () => {
            const toggle = page.locator('[data-filter-button]');
            if (await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
        };
        const choose = async (field, value) => {
            await openFilters();
            await page.locator(`[data-filter-nav-item][data-filter-key="${field}"]`).click();
            await change(() => page.locator(`input[data-filter-column="${field}"][value="${value}"]`).check());
        };

        for (const [width, percent] of [[1440, 100], [390, 100], [320, 100], [390, 200]]) {
            await page.setViewportSize({ width, height: 1000 });
            await visit('/ui-patterns?pattern=collection&view_id=royal-progress');
            await ready();
            if (percent === 200) await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
            const group = page.getByRole('group', { name: 'Royal Progress: match any selected value', exact: true });
            await expect(group.locator('.grid-view-filter-group-label')).toHaveText('Royal Progress:');
            await expect(group.locator('[data-filter-badge]')).toHaveCount(3);
            expect((await state()).pagination.totalCount).toBe(6);
            const chips = await group.locator('button.grid-view-filter-badge-control').evaluateAll(nodes => nodes.map(node => {
                const box = node.getBoundingClientRect();
                return { width: box.width, height: box.height };
            }));
            for (const chip of chips) {
                expect(chip.width).toBe(width < 768 ? 44 : 32);
                expect(chip.height).toBe(chip.width);
            }
            const connectors = await group.locator('.grid-view-filter-or').evaluateAll(nodes => nodes.map(node => {
                const badge = node.parentElement.querySelector('[data-filter-badge]').getBoundingClientRect();
                const connector = node.getBoundingClientRect();
                return { adjacent: connector.right <= badge.left + 1,
                    sameRow: connector.top < badge.bottom && connector.bottom > badge.top };
            }));
            expect(connectors).toHaveLength(2);
            expect(connectors.every(item => item.adjacent && item.sameRow)).toBe(true);
            checkButtons(await buttonMetrics(page.locator('.ui-pattern-content')), width);
            checkButtons(await buttonMetrics(page.locator('.ui-review-bar')), width);
            const dateOverflows = await page.locator('[data-grid-layout="cards"] .ui-grid-date-cell')
                .evaluateAll(nodes => nodes.map(node => node.scrollWidth - node.clientWidth));
            expect(dateOverflows.every(overflow => overflow <= 1), 'Card dates remain within their fields').toBe(true);
            await checkPageBounds(page, width, `Royal Progress at ${width}px/${percent}%`);
            const suffix = `${width}${percent === 200 ? '-text-200' : ''}`;
            if (percent === 200) {
                const headerButtons = await buttonMetrics(page.locator('.ui-app-header'));
                checkButtons(headerButtons, width);
                for (const button of headerButtons) {
                    expect(button.width, `${button.name}: phone target width`).toBeGreaterThanOrEqual(44);
                    expect(button.height, `${button.name}: phone target height`).toBeGreaterThanOrEqual(44);
                }
                await page.locator('.ui-app-header').screenshot({ path: path.join(output, 'local-header-text-200.png'), animations: 'disabled' });
                await page.screenshot({ path: path.join(output, 'local-full-page-text-200.png'), fullPage: true, animations: 'disabled' });
            }
            await page.locator('.grid-view-toolbar').screenshot({ path: path.join(output, `royal-progress-controls-${suffix}.png`), ...contentCapture });
            await group.screenshot({ path: path.join(output, `royal-progress-chips-${suffix}.png`), ...contentCapture });
            await page.locator('.ui-pattern-content').screenshot({ path: path.join(output, `collection-content-${suffix}.png`), ...contentCapture });
            if (width < 768 && percent === 100) {
                await page.locator('.ui-pattern-content').screenshot({
                    path: path.join(output, `collection-preview-${width}.png`), ...contentCapture,
                    style: `${contentCapture.style} .ui-pattern-content { max-height: 1450px; overflow: hidden; }`,
                });
            }

            // Removing first, last and final choices retains the true OR query and keyboard position.
            await change(() => remove('crown-ansteorra').press('Enter'));
            expect((await state()).pagination.totalCount).toBe(4);
            await expect(remove('coronet-oakmere')).toBeFocused();
            await change(() => remove('coronet-silvermere').press('Enter'));
            expect((await state()).pagination.totalCount).toBe(2);
            await expect(remove('coronet-oakmere')).toBeFocused();
            await change(() => remove('coronet-oakmere').press('Enter'));
            expect((await state()).pagination.totalCount).toBe(14);
            await expect(page.locator('[data-filter-button]')).toBeFocused();
            await change(() => page.getByRole('button', { name: 'Restore saved view', exact: true }).press('Enter'));
            expect((await state()).pagination.totalCount).toBe(6);
            await expect(page.locator('[data-view-tabs-container] [role="tab"][aria-selected="true"]')).toBeFocused();
            await page.getByLabel('Search gatherings', { exact: true }).fill('court');
            await change(() => page.locator('.ui-grid-search').getByRole('button', { name: 'Search', exact: true }).click());
            expect((await state()).pagination.totalCount).toBe(2);
            await change(() => page.getByRole('button', { name: 'Remove search', exact: true }).press('Enter'));
            expect((await state()).search).toBe('');
            expect((await state()).pagination.totalCount).toBe(6);
            await expect(remove('crown-ansteorra')).toBeFocused();

            const split = page.locator('[data-view-tabs-container] .btn-group');
            const borders = await split.evaluate(node => [...node.querySelectorAll('button')].map(button => ({
                height: button.getBoundingClientRect().height, border: getComputedStyle(button).borderBottomColor,
            })));
            expect(borders[0].height).toBe(borders[1].height);
            expect(borders[0].border).toBe(borders[1].border);
            await page.getByRole('button', { name: 'View options: Royal Progress', exact: true }).press('Enter');
            await expect(split.locator('.dropdown-menu')).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(page.getByRole('button', { name: 'View options: Royal Progress', exact: true })).toBeFocused();
            results.push({ width, textPercent: percent, royalProgressChoices: 3, squareControls: chips, connectors });
        }

        // Actual controls retain OR within a field and AND across fields, including date chips.
        await page.setViewportSize({ width: 390, height: 1000 });
        await visit('/ui-patterns?pattern=collection&ignore_default=1');
        await ready();
        await choose('branch', 'oakmere'); await choose('branch', 'willowford');
        expect((await state()).pagination.totalCount).toBe(11);
        await choose('activity', 'court'); await choose('activity', 'rapier');
        expect((await state()).pagination.totalCount).toBe(4);
        await choose('royal_progress', 'crown-ansteorra');
        expect((await state()).pagination.totalCount).toBe(2);
        await page.locator('[data-filter-nav-item][data-filter-key="start_date"]').click();
        await change(() => page.getByLabel('From', { exact: true }).fill('2026-12-01'));
        await change(() => page.getByLabel('To', { exact: true }).fill('2026-12-31'));
        expect((await state()).pagination.totalCount).toBe(1);
        await page.keyboard.press('Escape');
        await page.locator('.grid-view-toolbar').screenshot({ path: path.join(output, 'mixed-filter-controls-390.png'), ...contentCapture });
        await openFilters();
        await change(() => page.getByRole('button', { name: 'Clear all filters', exact: true }).click());
        expect((await state()).pagination.totalCount).toBe(14);
        await page.keyboard.press('Escape');

        await visit('/ui-patterns?pattern=record&record=branch&section=record-branch-gatherings-tab&view_id=royal-progress');
        await expect(page.getByRole('tab', { name: 'Gatherings', exact: true })).toHaveAttribute('aria-selected', 'true');
        await ready();
        const locked = page.locator('[data-filter-locked="true"]');
        await expect(locked).toContainText('Barony of Oakmere');
        await expect(locked.locator('button')).toHaveCount(0);
        await expect(locked).toContainText('Locked filter');
        for (const value of ['crown-ansteorra', 'coronet-oakmere', 'coronet-silvermere']) await change(() => remove(value).press('Enter'));
        expect((await state()).filters.active.branch).toEqual(['oakmere']);
        await expect(page.locator('[data-filter-button]')).toBeFocused();

        // Shared markup also keeps the standard application's compact 24px controls usable.
        // No screenshots or row names from this real-data page enter review artifacts.
        await page.setViewportSize({ width: 1440, height: 1000 });
        await visit('/gatherings?ignore_default=1&dirty%5Bfilters%5D=1');
        const normalFrame = page.locator('#gatherings-grid-table');
        await expect(normalFrame.locator('script[type="application/json"]')).toHaveCount(1);
        const normalState = () => normalFrame.locator('script[type="application/json"]').evaluate(node => JSON.parse(node.textContent));
        const standard = await normalState();
        const field = Object.entries(standard.filters.available).find(([, meta]) => meta.options?.length >= 2 && meta.showInFilter !== false);
        expect(field, 'A standard selectable filter is available').toBeTruthy();
        const [key, metadata] = field;
        const values = metadata.options.slice(0, 2).map(option => String(option.value));
        await page.locator('[data-filter-button]').click();
        await page.locator(`[data-filter-nav-item][data-filter-key="${key}"]`).click();
        for (const [index, value] of values.entries()) {
            await page.locator(`input[data-filter-column="${key}"][value="${value}"]`).check();
            await expect.poll(async () => (await normalState()).filters.active[key]?.map(String)).toEqual(values.slice(0, index + 1));
        }
        await page.keyboard.press('Escape');
        const normalControls = page.locator(`button.grid-view-filter-badge-control[data-filter-column="${key}"]`);
        await expect(normalControls).toHaveCount(2);
        const normalSizes = await normalControls.evaluateAll(nodes => nodes.map(node => ({
            width: node.getBoundingClientRect().width, height: node.getBoundingClientRect().height,
        })));
        expect(normalSizes).toEqual([{ width: 24, height: 24 }, { width: 24, height: 24 }]);
        expect(await page.locator('[data-filter-pills-container]').evaluate(node => node.scrollWidth - node.clientWidth)).toBeLessThanOrEqual(1);
        await normalControls.first().press('Enter');
        await expect.poll(async () => (await normalState()).filters.active[key]?.map(String)).toEqual([values[1]]);
        await expect(normalControls).toHaveCount(1);
        await expect(normalControls).toBeFocused();
        results.push({ standardGatherings: true, squareControls: normalSizes, removalRetainsFocus: true });

        // Real controls in each fictional workflow remain readable and bounded, including enlarged text.
        for (const [width, percent] of [[1440, 100], [390, 100], [320, 100], [390, 200]]) {
            await page.setViewportSize({ width, height: 1000 });
            for (const pattern of ['collection', 'record', 'reader', 'task', 'workspace', 'dashboard']) {
                await visit(`/ui-patterns?pattern=${pattern}&ignore_default=1`);
                if (percent === 200) await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
                if (pattern === 'task') await page.getByRole('button', { name: 'Continue', exact: true }).click();
                const content = page.locator('.ui-pattern-content');
                await checkScrollableTabs(content, width);
                const buttons = await buttonMetrics(content);
                checkButtons(buttons, width);
                await checkPageBounds(page, width, `${pattern} at ${width}px/${percent}%`);
                const suffix = `${width}${percent === 200 ? '-text-200' : ''}`;
                await content.screenshot({ path: path.join(output, `${pattern}-buttons-${suffix}.png`), ...contentCapture });
                results.push({ pattern, width, textPercent: percent, controls: buttons.length });

                if (['collection', 'record', 'reader'].includes(pattern)) {
                    const triggerName = { collection: 'Create gathering', record: 'Edit branch', reader: 'Attend this gathering' }[pattern];
                    const trigger = page.getByRole('button', { name: triggerName, exact: true }).and(page.locator('[data-bs-toggle="modal"]'));
                    const shown = page.evaluate(() => new Promise(resolve => {
                        document.addEventListener('shown.bs.modal', () => resolve(), { once: true });
                    }));
                    await trigger.click();
                    await shown;
                    const modal = page.locator('.modal.show');
                    await expect(modal).toBeVisible();
                    checkButtons(await buttonMetrics(modal), width);
                    await modal.locator('.btn-close').press('Enter');
                    await expect(modal).toHaveCount(0);
                    await expect(trigger).toBeFocused();
                }
                if (pattern === 'record' && width === 320) {
                    await visit('/ui-patterns?pattern=record&record=branch&section=record-branch-gatherings-tab');
                    const restored = page.getByRole('tab', { name: 'Gatherings', exact: true });
                    await expect(restored).toHaveAttribute('aria-selected', 'true');
                    await checkTabVisible(restored, width);
                }
                if (pattern === 'workspace' && width < 992) {
                    const trigger = page.getByRole('button', { name: 'Add to court', exact: true });
                    await trigger.click();
                    const drawer = page.locator('#workspace-tools');
                    await expect(drawer).toHaveClass(/\bshow\b/);
                    await expect(drawer).not.toHaveClass(/\bshowing\b/);
                    checkButtons(await buttonMetrics(drawer), width);
                    await drawer.locator('.offcanvas-body').screenshot({ path: path.join(output, `court-tools-${suffix}.png`), ...contentCapture });
                    await drawer.locator('.btn-close').press('Enter');
                    await expect(drawer).not.toBeVisible();
                    await expect(trigger).toBeFocused();
                }
            }
        }
        expect(errors).toEqual([]);
        expect(writes).toEqual([]);
        fs.writeFileSync(path.join(output, 'button-results.json'), JSON.stringify({ results, errors, writes }, null, 2));
        console.log('PASS: filter semantics/removal focus, square controls, attached OR choices, locked filters, split tabs, and six-family button bounds/dialogs at desktop/mobile/200%; no domain writes.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
