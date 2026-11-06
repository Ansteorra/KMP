const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { runPhpJson } = require('../../support/ui-helpers.cjs');

const { Then } = createBdd();

Then('core and plugin screens pass accessibility checks at {int} by {int}', async ({ page, $testInfo }, width, height) => {
    const fixture = runPhpJson(String.raw`
        require 'vendor/autoload.php'; require 'config/bootstrap.php';
        $l = \Cake\ORM\TableRegistry::getTableLocator();
        echo json_encode([
            'member' => $l->get('Members')->find()->where(['email_address' => 'admin@amp.ansteorra.org'])->firstOrFail()->id,
            'permission' => $l->get('Permissions')->find()->orderByAsc('id')->firstOrFail()->id,
            'gathering' => $l->get('Gatherings')->find()->orderByAsc('id')->firstOrFail()->public_id,
        ]);
    `);
    await page.setViewportSize({ width, height });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const reports = [];
    const scan = async state => {
        await page.waitForFunction(() => document.readyState === 'complete'
            && ![...document.querySelectorAll('turbo-frame[aria-busy="true"]')].some(frame => frame.getClientRects().length));
        await page.evaluate(() => document.fonts.ready);
        const result = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
        // Keep rule evidence without serializing authenticated HTML or form values.
        const summarize = rule => ({ id: rule.id, impact: rule.impact, help: rule.help,
            targets: rule.nodes.map(node => node.target) });
        reports.push({ state, width, height, violations: result.violations.map(summarize),
            manualReview: result.incomplete.map(summarize) });
        expect(result.violations, `${state}: ${result.violations.map(rule => rule.id).join(', ')}`).toEqual([]);
    };
    try {
        const routes = ['/members?limit=10', `/members/view/${fixture.member}?tab=notes`,
            '/action-items/my-tasks', '/approvals', '/approvals/all', '/awards/recommendations',
            '/officers/offices', '/activities/activities', '/gatherings/calendar',
            `/gatherings/view/${fixture.gathering}`, `/permissions/view/${fixture.permission}?tab=policies`,
            '/waivers/waiver-types'];
        for (const route of routes) {
            expect((await page.goto(route)).status(), route).toBe(200);
            await scan(route);
        }
        await page.goto(`/members/view/${fixture.member}?tab=notes`);
        const addNote = page.getByRole('button', { name: 'Add a Note', exact: true });
        await addNote.focus();
        await page.keyboard.press('Enter');
        const form = page.locator('form[data-notes-new-form]');
        await expect(form).toBeVisible();
        await expect(addNote).toHaveAttribute('aria-expanded', 'true');
        await scan('Notes creation disclosure');
        // Exercise the actual shared confirmation and its keyboard/focus lifecycle.
        await page.evaluate(() => {
            window.__a11yDialogResult = undefined;
            window.KMP_accessibility.confirm('Keep your place while checking this dialog?', { title: 'Navigation check' })
                .then(result => { window.__a11yDialogResult = result; });
        });
        await expect(page.getByRole('dialog')).toBeVisible();
        await scan('Shared confirmation dialog');
        await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expect.poll(() => page.evaluate(() => window.__a11yDialogResult)).toBe(false);
        await expect(addNote).toBeFocused();

        await page.goto(`/members/view/${fixture.member}?tab=authorizations`);
        const options = page.getByRole('button', { name: /^View options:/ }).first();
        await expect(options).toBeVisible();
        await options.focus();
        await page.keyboard.press('Enter');
        await expect(options).toHaveAttribute('aria-expanded', 'true');
        await scan('Saved view options menu');
        await page.keyboard.press('Escape');
        await expect(options).toHaveAttribute('aria-expanded', 'false');
        await expect(options).toBeFocused();
    } finally {
        await $testInfo.attach(`accessibility-${width}x${height}`, {
            body: JSON.stringify(reports, null, 2), contentType: 'application/json',
        });
    }
});
