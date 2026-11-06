const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const {
    loginAs,
    waitForTurboFrame,
    assertUrlContainsQuery,
    waitForPageBody,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then } = createBdd();

Then('dialog initialization should survive repeated document visits', async ({ page }) => {
    const errors = [];
    const onError = error => errors.push(error.message);
    page.on('pageerror', onError);
    try {
        await page.goto('/members');
        const enabled = await page.locator('meta[name="kmp-turbo-enabled"]').getAttribute('content') === 'true';
        const marker = await page.evaluate(() => { window.__dialogVisitCheck = crypto.randomUUID(); return window.__dialogVisitCheck; });
        for (const path of ['/action-items/my-tasks', '/approvals', '/approvals/all', '/awards/recommendations', '/action-items/my-tasks']) {
            await page.evaluate(path => {
                const link = document.createElement('a'); link.href = path; link.id = 'dialog-visit-link';
                link.textContent = 'Open dialog screen'; document.querySelector('#main-content').prepend(link);
            }, path);
            await page.locator('#dialog-visit-link').click();
            await expect(page).toHaveURL(new RegExp(`${path.replaceAll('/', '\\/')}(?:\\?|$)`));
            if (enabled) expect(await page.evaluate(() => window.__dialogVisitCheck)).toBe(marker);
            const selector = path.startsWith('/action-items') ? '#todoCompleteModal'
                : path.startsWith('/awards') ? '#recommendationWorkflowDecisionModal' : '#approvalResponseModal';
            const modal = page.locator(selector);
            await expect(modal).toBeAttached();
            await expect.poll(() => modal.evaluate(element => {
                const identifier = element.dataset.controller;
                return !!window.Stimulus?.getControllerForElementAndIdentifier(element, identifier);
            })).toBe(true);
            await modal.evaluate(element => {
                const trigger = document.createElement('button');
                trigger.type = 'button'; trigger.id = 'dialog-check-trigger'; trigger.textContent = 'Open test dialog';
                trigger.dataset.bsToggle = 'modal'; trigger.dataset.bsTarget = `#${element.id}`;
                trigger.dataset.todoId = '42'; trigger.dataset.todoTitle = 'Navigation check';
                trigger.dataset.outletBtnBtnDataValue = JSON.stringify({ id: 42 });
                document.querySelector('#main-content').prepend(trigger);
            });
            await page.locator('#dialog-check-trigger').click();
            await expect(modal).toBeVisible();
            await expect(modal).toBeFocused();
            if (path.startsWith('/action-items')) {
                await expect(modal.locator('[data-todo-target="id"]')).toHaveValue('42');
                await expect(modal.locator('[data-todo-target="title"]')).toHaveText('Navigation check');
            } else {
                await expect(modal.locator('input[name="approvalId"]')).toHaveValue('42');
            }
            await page.keyboard.press('Escape');
            await expect(modal).not.toBeVisible();
            await expect(page.locator('#dialog-check-trigger')).toBeFocused();
        }
        expect(errors).toEqual([]);
    } finally {
        page.off('pageerror', onError);
    }
});

const waitForRecommendationsGridReady = async (page) => {
    await waitForTurboFrame(page, 'recommendations-grid');
    const tableFrame = await waitForTurboFrame(page, 'recommendations-grid-table');
    await expect(tableFrame.locator('table.table')).toBeVisible({ timeout: 30000 });
};

const openFilterDropdown = async (page) => {
    const filterBtn = page.locator('[data-filter-button]').first();
    await expect(filterBtn).toBeVisible({ timeout: 30000 });

    const dropdown = filterBtn.locator('xpath=following-sibling::*[contains(concat(" ", normalize-space(@class), " "), " dropdown-menu ")]').first();
    if (!await dropdown.isVisible()) {
        await filterBtn.click();
    }

    if (!await dropdown.isVisible()) {
        await filterBtn.evaluate((button) => {
            const dropdownApi = window.bootstrap?.Dropdown;
            if (!dropdownApi) {
                throw new Error('Bootstrap Dropdown API is not available.');
            }
            dropdownApi.getOrCreateInstance(button).show();
        });
    }

    await expect(dropdown).toBeVisible({ timeout: 30000 });

    return dropdown;
};

Given('I am logged in as awards admin for hotwire tests', async ({ page }) => {
    await loginAs(page, 'admin@amp.ansteorra.org');
});

When('I navigate to {string}', async ({ page }, path) => {
    await page.goto(path, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
});

When('I open the recommendations grid with search {string}', async ({ page }, searchText) => {
    await page.goto(`/awards/recommendations?search=${encodeURIComponent(searchText)}`);
    await waitForRecommendationsGridReady(page);
});

When('I apply a recommendations grid search for {string}', async ({ page }, searchText) => {
    await waitForRecommendationsGridReady(page);
    const dropdown = await openFilterDropdown(page);

    const searchInput = dropdown.locator('[data-grid-view-target="searchInput"]');
    await expect(searchInput).toBeVisible({ timeout: 30000 });
    await Promise.all([
        page.waitForResponse(
            (response) => {
                const url = new URL(response.url());
                return url.pathname.endsWith('/awards/recommendations/grid-data')
                    && url.searchParams.get('search') === searchText
                    && response.status() === 200;
            },
            { timeout: 30000 },
        ),
        (async () => {
            await searchInput.click();
            await searchInput.fill('');
            await searchInput.pressSequentially(searchText);
            await searchInput.press('Enter');
        })(),
    ]);
    await waitForRecommendationsGridReady(page);
});

Then('the recommendations URL should include search {string}', async ({ page }, searchText) => {
    await assertUrlContainsQuery(page, `search=${encodeURIComponent(searchText)}`);
});

When('I go back in the browser on the recommendations grid', async ({ page }) => {
    await Promise.all([
        page.waitForResponse(
            (response) => response.url().includes('/awards/recommendations/grid-data') && response.ok(),
            { timeout: 30000 },
        ),
        page.goBack(),
    ]);
    await waitForRecommendationsGridReady(page);
});

Then('the recommendations URL should not include search {string}', async ({ page }, searchText) => {
    const url = page.url();
    expect(url).not.toContain(`search=${encodeURIComponent(searchText)}`);
});

When('I open the app settings grid with search {string}', async ({ page }, searchText) => {
    await page.goto(`/app-settings?search=${encodeURIComponent(searchText)}`);
    await waitForTurboFrame(page, 'app-settings-grid');
    await waitForTurboFrame(page, 'app-settings-grid-table');
});

Then('the app settings URL should include search {string}', async ({ page }, searchText) => {
    await assertUrlContainsQuery(page, `search=${encodeURIComponent(searchText)}`);
});
