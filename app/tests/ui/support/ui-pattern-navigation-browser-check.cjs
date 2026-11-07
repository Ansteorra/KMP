/** Read-only review of the showcase navigation and shared headers; no fixture reset. */
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
const { chromium } = require(path.join(root, 'node_modules/playwright'));
const { expect } = require(path.join(root, 'node_modules/@playwright/test'));
const { loginAs } = require('./ui-helpers.cjs');

const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://kmp.localhost:8080';
const output = process.env.UI_PATTERNS_OUTPUT || path.join(root, 'tmp/ui-pattern-navigation');

/** Measure real rendered text lines, including wrapped labels, against their own icon/count slots. */
async function navigationMetrics(nav) {
    return nav.evaluate(element => {
        const rectangle = node => {
            const box = node.getBoundingClientRect();
            return { left: box.left, right: box.right, top: box.top, bottom: box.bottom,
                width: box.width, height: box.height };
        };
        const rgb = value => value.match(/[\d.]+/g).slice(0, 3).map(Number);
        const luminance = channels => channels.map(channel => {
            const value = channel / 255;
            return value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4;
        }).reduce((value, channel, index) => value + channel * [.2126, .7152, .0722][index], 0);
        const contrast = node => {
            let background = 'rgb(255, 255, 255)';
            for (let ancestor = node; ancestor; ancestor = ancestor.parentElement) {
                const color = getComputedStyle(ancestor).backgroundColor;
                if (color !== 'rgba(0, 0, 0, 0)' && color !== 'transparent') { background = color; break; }
            }
            const values = [luminance(rgb(getComputedStyle(node).color)), luminance(rgb(background))].sort((a, b) => a - b);
            return (values[1] + .05) / (values[0] + .05);
        };
        return [...element.querySelectorAll('.navheader, .kmp-nav-item')]
            .filter(node => node.checkVisibility())
            .map(node => {
                const label = node.querySelector('.kmp-nav-label');
                const icon = node.querySelector('.kmp-nav-icon');
                const count = node.querySelector('.kmp-nav-count');
                const range = document.createRange();
                range.selectNodeContents(label);
                return { text: label.textContent.trim(), row: rectangle(node), label: rectangle(label),
                    icon: icon && rectangle(icon), count: count && rectangle(count),
                    lines: [...range.getClientRects()].map(box => ({ left: box.left, right: box.right, top: box.top })),
                    lineHeight: parseFloat(getComputedStyle(label).lineHeight),
                    fontWeight: Number(getComputedStyle(label).fontWeight), contrast: contrast(label),
                    countContrast: count && contrast(count), countSeparate: !count || !label.contains(count),
                    isSection: node.matches('.navheader') };
            });
    });
}

(async () => {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const results = [], headers = [], errors = [], writes = [];
    const initialExpansion = new Map();
    let context;
    try {
        context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await loginAs(page, process.env.PLAYWRIGHT_ADMIN_EMAIL || 'admin@amp.ansteorra.org');
        page.on('request', request => {
            if (!['GET', 'HEAD'].includes(request.method())) writes.push(new URL(request.url()).pathname);
        });

        for (const width of [1440, 1024, 768, 390, 320]) {
            await page.setViewportSize({ width, height: 1000 });
            await page.goto('/ui-patterns?pattern=collection&ignore_default=1', { waitUntil: 'networkidle' });
            const online = page.getByRole('button', { name: 'Use online only', exact: true });
            if (await online.isVisible()) await online.click();
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
            const nav = page.locator('#ui-app-navigation');
            const opener = page.getByRole('button', { name: 'Open application navigation', exact: true });
            const mobile = width < 992;
            if (mobile) {
                await expect(nav).not.toBeVisible();
                await opener.press('Enter');
                await expect(nav).toHaveClass(/\bshow\b/);
                await expect(nav).not.toHaveClass(/\bshowing\b/);
            } else {
                await expect(nav).toBeVisible();
                await expect(opener).not.toBeVisible();
            }

            for (const label of ['Action Items', 'Members']) {
                const toggle = nav.getByRole('button', { name: label, exact: true });
                if (!initialExpansion.has(label)) {
                    const expanded = await toggle.getAttribute('aria-expanded') === 'true';
                    initialExpansion.set(label, await toggle.getAttribute(expanded ? 'data-expand-url' : 'data-collapse-url'));
                }
                const panelId = await toggle.getAttribute('aria-controls');
                const panel = page.locator(`[id="${panelId}"]`);
                if (await toggle.getAttribute('aria-expanded') === 'true') await toggle.press('Enter');
                await expect(toggle).toHaveAttribute('aria-expanded', 'false');
                await expect(panel).not.toBeVisible();
                await expect(panel).not.toHaveClass(/\bcollapsing\b/);
                await toggle.press('Space');
                await expect(toggle).toHaveAttribute('aria-expanded', 'true');
                await expect(panel).toBeVisible();
                await expect(panel).not.toHaveClass(/\bcollapsing\b/);
                await expect(toggle).toBeFocused();
                const focus = await toggle.evaluate(node => ({ visible: node.matches(':focus-visible'),
                    outline: getComputedStyle(node).outlineStyle, width: parseFloat(getComputedStyle(node).outlineWidth) }));
                expect(focus.visible).toBe(true);
                expect(focus.outline).not.toBe('none');
                expect(focus.width).toBeGreaterThanOrEqual(2);
            }

            const rows = await navigationMetrics(nav);
            expect(rows.length).toBeGreaterThan(5);
            for (const item of rows) {
                expect(item.row.height, `${item.text}: usable target`).toBeGreaterThanOrEqual(40);
                expect(item.label.left, `${item.text}: label has its own column`).toBeGreaterThanOrEqual(item.icon.right + 1);
                expect(item.label.right, `${item.text}: label stays within row`).toBeLessThanOrEqual(item.row.right + 1);
                expect(item.countSeparate, `${item.text}: count is separate from wrapping text`).toBe(true);
                expect(item.contrast, `${item.text}: text contrast`).toBeGreaterThanOrEqual(4.5);
                for (const line of item.lines) {
                    expect(line.left, `${item.text}: wrapped line stays after icon`).toBeGreaterThanOrEqual(item.label.left - 1);
                    expect(line.right, `${item.text}: wrapped line stays within label`).toBeLessThanOrEqual(item.label.right + 1);
                }
                if (item.count) {
                    expect(item.count.left, `${item.text}: count does not overlap label`).toBeGreaterThanOrEqual(item.label.right - 1);
                    expect(item.count.right, `${item.text}: count stays in row`).toBeLessThanOrEqual(item.row.right + 1);
                    expect(item.count.top, `${item.text}: count is not stranded below label`).toBeLessThan(item.label.top + item.lineHeight);
                    expect(item.countContrast, `${item.text}: count contrast`).toBeGreaterThanOrEqual(4.5);
                }
                if (item.isSection) expect(item.fontWeight, `${item.text}: section emphasis`).toBeGreaterThanOrEqual(600);
            }
            const navBox = await nav.boundingBox();
            expect(navBox.x).toBeGreaterThanOrEqual(0);
            expect(navBox.x + navBox.width).toBeLessThanOrEqual(width + 1);
            expect(await nav.evaluate(node => node.scrollWidth - node.clientWidth)).toBeLessThanOrEqual(1);
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
            await nav.getByRole('button', { name: 'Action Items', exact: true }).focus();
            await page.screenshot({ path: path.join(output, `navigation-${width}.png`), animations: 'disabled' });
            await nav.screenshot({ path: path.join(output, `navigation-panel-${width}.png`), animations: 'disabled' });
            results.push({ width, rows, sidebarWidth: navBox.width });

            if (mobile) {
                await page.keyboard.press('Escape');
                await expect(nav).not.toBeVisible();
                await expect(opener).toBeFocused();
                await opener.press('Enter');
                await expect(nav).toHaveClass(/\bshow\b/);
                await expect(nav).not.toHaveClass(/\bshowing\b/);
                const close = nav.getByRole('button', { name: 'Close navigation', exact: true });
                await close.focus();
                await page.keyboard.press('Shift+Tab');
                expect(await nav.evaluate(node => node.contains(document.activeElement))).toBe(true);
                await close.press('Enter');
                await expect(nav).not.toBeVisible();
                await expect(opener).toBeFocused();
            }
        }
        expect(results.some(result => result.rows.some(row => row.label.height > row.lineHeight * 1.5))).toBe(true);

        // Enlarged text keeps labels/counts separate within the narrow navigation drawer.
        await page.setViewportSize({ width: 390, height: 1000 });
        await page.goto('/ui-patterns?pattern=collection&ignore_default=1', { waitUntil: 'networkidle' });
        await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
        await page.getByRole('button', { name: 'Open application navigation', exact: true }).press('Enter');
        const enlargedNav = page.locator('#ui-app-navigation');
        await expect(enlargedNav).toHaveClass(/\bshow\b/);
        await expect(enlargedNav).not.toHaveClass(/\bshowing\b/);
        const actionItems = enlargedNav.getByRole('button', { name: 'Action Items', exact: true });
        if (await actionItems.getAttribute('aria-expanded') !== 'true') await actionItems.press('Enter');
        const enlargedRows = await navigationMetrics(enlargedNav);
        expect(await page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize))).toBe(32);
        await page.screenshot({ path: path.join(output, 'navigation-text-200-390.png'), animations: 'disabled' });
        if (await enlargedNav.evaluate(node => node.scrollWidth > node.clientWidth + 1)) {
            console.log('Enlarged navigation overflow:', await enlargedNav.evaluate(node => {
                const right = node.getBoundingClientRect().right;
                return [...node.querySelectorAll('*')].filter(child => child.checkVisibility()
                    && child.getBoundingClientRect().right > right + 1)
                    .map(child => ({ tag: child.tagName, class: child.className, text: child.textContent.trim().slice(0, 80),
                        right: child.getBoundingClientRect().right, navRight: right }));
            }));
        }
        expect(await enlargedNav.evaluate(node => node.scrollWidth - node.clientWidth)).toBeLessThanOrEqual(1);
        for (const item of enlargedRows) {
            expect(item.label.left, `${item.text}: enlarged label stays after icon`).toBeGreaterThanOrEqual(item.icon.right + 1);
            expect(item.label.right, `${item.text}: enlarged label fits`).toBeLessThanOrEqual(item.row.right + 1);
            if (item.count) {
                expect(item.count.left, `${item.text}: enlarged count stays separate`).toBeGreaterThanOrEqual(item.label.right - 1);
                expect(item.count.right, `${item.text}: enlarged count fits`).toBeLessThanOrEqual(item.row.right + 1);
            }
        }
        for (const [label, words] of [
            [enlargedNav.locator('#ui-navigation-title'), ['Application', 'navigation']],
            [enlargedNav.getByRole('link', { name: /^My Approvals/ }).locator('.kmp-nav-label'), ['Approvals']],
        ]) {
            const lineCounts = await label.evaluate((node, samples) => samples.map(word => {
                const text = [...node.childNodes].find(child => child.nodeType === Node.TEXT_NODE && child.textContent.includes(word));
                const start = text.textContent.indexOf(word);
                const range = document.createRange();
                range.setStart(text, start);
                range.setEnd(text, start + word.length);
                return { word, lines: new Set([...range.getClientRects()].map(box => box.top)).size };
            }), words);
            for (const sample of lineCounts) expect(sample.lines, `${sample.word}: whole word at 200%`).toBe(1);
        }
        await actionItems.focus();
        await page.screenshot({ path: path.join(output, 'navigation-text-200-390.png'), animations: 'disabled' });
        results.push({ width: 390, textPercent: 200, rows: enlargedRows });
        await page.keyboard.press('Escape');

        // Every page family uses the same readable header contract at desktop and phone widths.
        for (const width of [1440, 390, 320]) {
            const typography = new Set();
            await page.setViewportSize({ width, height: 1000 });
            for (const pattern of ['collection', 'record', 'reader', 'task', 'workspace', 'dashboard']) {
                await page.goto(`/ui-patterns?pattern=${pattern}&ignore_default=1`, { waitUntil: 'networkidle' });
                const heading = page.locator('.ui-page-header h1');
                await expect(heading).toBeVisible();
                const metrics = await heading.evaluate(node => {
                    const style = getComputedStyle(node), box = node.getBoundingClientRect();
                    return { text: node.textContent.trim(), fontSize: style.fontSize, fontWeight: style.fontWeight,
                        lineHeight: style.lineHeight, left: box.left, right: box.right, width: window.innerWidth };
                });
                typography.add(`${metrics.fontSize}/${metrics.lineHeight}/${metrics.fontWeight}`);
                expect(metrics.left).toBeGreaterThanOrEqual(0);
                expect(metrics.right).toBeLessThanOrEqual(width);
                expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                const review = await page.locator('.ui-review-bar').evaluate(node => {
                    const first = node.firstElementChild.getBoundingClientRect();
                    const actions = node.lastElementChild.getBoundingClientRect();
                    return first.left < actions.right && first.right > actions.left
                        && first.top < actions.bottom && first.bottom > actions.top;
                });
                expect(review, 'Review title and controls do not overlap').toBe(false);
                await page.locator('.ui-page-header').screenshot({ path: path.join(output, `header-${pattern}-${width}.png`), animations: 'disabled' });
                headers.push({ pattern, ...metrics });
            }
            expect(typography.size, `Consistent heading typography at ${width}px`).toBe(1);
        }
        expect(errors).toEqual([]);
        expect(writes).toEqual([]);
        fs.writeFileSync(path.join(output, 'navigation-results.json'), JSON.stringify({ results, headers, errors, writes }, null, 2));
        console.log('PASS: five navigation widths, separate wrapping/count slots, contrast, keyboard focus, drawer dismissal and six consistent page headers; no domain writes.');
    } finally {
        try {
            // Expansion is session state written by the existing GET endpoints, not domain data.
            // Restore precisely the two settings exercised here even if an assertion fails.
            for (const [label, route] of initialExpansion) {
                const response = await context.request.get(route, { headers: {
                    'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json',
                } });
                expect(response.ok(), `Restore ${label} navigation preference`).toBe(true);
            }
        } finally {
            await browser.close();
        }
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
