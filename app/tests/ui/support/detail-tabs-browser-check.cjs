/** Read-only detail navigation and isolated modal/stream acceptance against the running app. */
const assert = require('node:assert/strict');
const { chromium, firefox, webkit, expect } = require('@playwright/test');
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');
const { getUiTestEnvironment } = require('./test-environment.cjs');

(async () => {
    const engine = process.env.PLAYWRIGHT_BROWSER || 'chromium';
    const browser = await ({ chromium, firefox, webkit })[engine].launch({ headless: true });
    const context = await browser.newContext({ baseURL: getUiTestEnvironment().baseUrl, reducedMotion: 'reduce', extraHTTPHeaders: { 'X-KMP-E2E': '1' } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
        await loginAs(page, process.env.PLAYWRIGHT_ADMIN_EMAIL || 'admin@amp.ansteorra.org');
        await page.goto('/members/profile?tab=gatherings&grid_context=unrelated&search=retained#details');
        await expect(page.locator('#nav-gatherings-tab')).toHaveAttribute('aria-selected', 'true');
        const table = page.locator('#nav-gatherings turbo-frame[id$="-table"]');
        await expect(table.locator('table')).toBeVisible();
        await expect(table).not.toHaveAttribute('aria-busy', 'true');
        const tableId = await table.getAttribute('id');
        await table.evaluate(frame => { window.__detailTable = frame; });
        let documentLoads = 0;
        let gridLoads = 0;
        const track = request => {
            const headers = request.headers();
            if ((request.isNavigationRequest() && request.frame() === page.mainFrame())
                || (request.method() === 'GET' && headers.accept?.includes('text/html') && !headers['turbo-frame']
                    && headers['x-kmp-ui-refresh'] !== 'screen')) documentLoads++;
            if (new URL(request.url()).pathname.includes('gatherings-grid-data')) gridLoads++;
        };
        page.on('request', track);
        await page.locator('#nav-notes-tab').click();
        await expect(page.locator('#nav-notes-tab')).toHaveAttribute('aria-selected', 'true');
        assert.equal(new URL(page.url()).searchParams.get('search'), 'retained');
        assert.equal(new URL(page.url()).hash, '#details');
        await page.goBack();
        await expect(page.locator('#nav-gatherings-tab')).toHaveAttribute('aria-selected', 'true');
        await page.goForward();
        await expect(page.locator('#nav-notes-tab')).toHaveAttribute('aria-selected', 'true');
        await page.goBack();
        await expect(page.locator('#nav-gatherings-tab')).toHaveAttribute('aria-selected', 'true');
        await page.goForward();
        await expect(page.locator('#nav-notes-tab')).toHaveAttribute('aria-selected', 'true');
        await page.locator('#nav-gatherings-tab').focus();
        await page.keyboard.press('Enter');
        await expect(page.locator('#nav-gatherings-tab')).toHaveAttribute('aria-selected', 'true');
        assert.equal(await table.evaluate(frame => frame === window.__detailTable), true);
        assert.equal(documentLoads, 0, 'tab navigation must not load documents');
        assert.equal(gridLoads, 0, 'returning to a loaded tab must not fetch its grid again');

        // The endpoint is intercepted: this exercises real Bootstrap/Turbo without writing records.
        await page.route('**/__detail-tabs-check/save', route => route.fulfill({
            status: 422, contentType: 'text/vnd.turbo-stream.html',
            body: '<turbo-stream action="replace" target="flash-messages"><template><div id="flash-messages"><div class="alert alert-danger" role="alert">Please supply a term note.</div></div></template></turbo-stream>',
        }));
        await table.evaluate(frame => frame.insertAdjacentHTML('beforeend',
            '<button id="detail-check-trigger" type="button" data-bs-toggle="modal" data-bs-target="#detail-check-modal">Test edit</button>'));
        await page.evaluate(() => document.body.insertAdjacentHTML('beforeend', `
            <div class="modal fade" id="detail-check-modal" tabindex="-1" aria-labelledby="detail-check-title">
                <div class="modal-dialog"><div class="modal-content">
                    <form data-controller="turbo-modal" data-action="submit->turbo-modal#submitAsTurboStream"
                        action="/__detail-tabs-check/save" method="post" data-turbo="true">
                        <div class="modal-header"><h2 id="detail-check-title">Test edit</h2></div>
                        <div class="modal-body"><div data-turbo-modal-feedback></div>
                            <label for="detail-check-note">Note</label><input id="detail-check-note" name="note" value="Keep my draft">
                            <input type="hidden" name="page_context_url">
                        </div>
                        <div class="modal-footer"><button type="button" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="decision" value="save">Save</button></div>
                    </form>
                </div></div>
            </div>`));
        await page.locator('#detail-check-trigger').click();
        const modal = page.locator('#detail-check-modal');
        await expect(modal).toBeVisible();
        await modal.getByRole('button', { name: 'Save', exact: true }).click();
        await expect(modal.getByRole('alert')).toHaveText('Please supply a term note.');
        await expect(modal.getByLabel('Note', { exact: true })).toHaveValue('Keep my draft');
        await expect(modal).toBeVisible();
        await page.unroute('**/__detail-tabs-check/save');
        await page.route('**/__detail-tabs-check/save', route => {
            assert.ok(route.request().postData().includes('save'), 'clicked submit action must be posted');
            return route.fulfill({ status: 200, contentType: 'text/vnd.turbo-stream.html',
                body: `<turbo-stream action="replace" target="${tableId}"><template></template></turbo-stream>` });
        });
        const refreshed = page.waitForResponse(response => response.request().method() === 'GET'
            && new URL(response.url()).pathname.includes('gatherings-grid-data'));
        await modal.getByRole('button', { name: 'Save', exact: true }).click();
        assert.equal((await refreshed).status(), 200);
        await expect(modal).not.toBeVisible();
        await expect(table.locator('table')).toBeVisible();
        await expect(page.locator('#nav-gatherings-tab')).toHaveAttribute('aria-selected', 'true');
        await expect.poll(() => page.evaluate(() => document.activeElement.closest('turbo-frame')?.id)).toBe(tableId);
        assert.equal(documentLoads, 0, 'modal saves must not load documents');

        await page.goto('/officers/offices');
        assert.ok(documentLoads > 0, 'the counter must still detect an actual document navigation');
        page.off('request', track);
        await expect(page.locator('#offices-grid-table table')).toBeVisible();
        await expect(page.locator('#offices-grid')).toHaveCount(1);
        const sorted = page.waitForResponse(response => new URL(response.url()).pathname === '/officers/offices/grid-data');
        await page.locator('#offices-grid-table .dataverse-sort-button').first().click();
        assert.equal((await sorted).status(), 200);
        assert.equal(new URL(page.url()).pathname, '/officers/offices');
        await expect(page.locator('#offices-grid')).toHaveCount(1);

        const branch = runPhpJson(String.raw`
            require 'vendor/autoload.php'; require 'config/bootstrap.php';
            $row = \Cake\ORM\TableRegistry::getTableLocator()->get('Branches')->find()->where(['parent_id IS' => null])->firstOrFail();
            echo json_encode(['publicId' => $row->public_id]);
        `);
        await page.goto('/branches/view/' + branch.publicId + '?tab=gatherings');
        await expect(page.locator('#nav-gatherings-tab')).toHaveAttribute('aria-selected', 'true');
        await page.locator('#nav-branch-next-month-gatherings-tab').click();
        await expect(page.locator('#nav-branch-next-month-gatherings-tab')).toHaveAttribute('aria-selected', 'true');
        assert.equal(new URL(page.url()).searchParams.get('tab'), 'gatherings', 'nested tabs must preserve host context');
        assert.deepEqual(errors, []);
        console.log('PASS: detail history, loaded-frame reuse, modal validation, partial saves, focus return, and nested tabs');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
