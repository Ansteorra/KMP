const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const {
    loginAs,
    waitForTurboFrame,
    assertUrlContainsQuery,
    waitForPageBody,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then } = createBdd();

Given('I am logged in as awards admin for hotwire tests', async ({ page }) => {
    await loginAs(page, 'admin@amp.ansteorra.org');
});

When('I navigate to {string}', async ({ page }, path) => {
    await page.goto(path, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
});

When('I open the recommendations grid with search {string}', async ({ page }, searchText) => {
    await page.goto(`/awards/recommendations?search=${encodeURIComponent(searchText)}`);
    await waitForTurboFrame(page, 'recommendations-grid');
    await waitForTurboFrame(page, 'recommendations-grid-table');
});

When('I apply a recommendations grid search for {string}', async ({ page }, searchText) => {
    await expect(page.locator('turbo-frame#recommendations-grid-table table.table tbody tr').first()).toBeVisible({
        timeout: 30000,
    });
    const filterBtn = page.locator('#filterDropdown, button:has-text("Filter")').first();
    await filterBtn.click();
    await page.waitForTimeout(300);

    const searchInput = page.locator('[data-grid-view-target="searchInput"]');
    await searchInput.fill(searchText);
    await Promise.all([
        page.waitForResponse(
            (response) => response.url().includes('/awards/recommendations/grid-data') && response.status() === 200,
            { timeout: 30000 },
        ).catch(() => null),
        searchInput.press('Enter'),
    ]);
    await page.waitForTimeout(1000);
});

Then('the recommendations URL should include search {string}', async ({ page }, searchText) => {
    await assertUrlContainsQuery(page, `search=${encodeURIComponent(searchText)}`);
});

When('I go back in the browser on the recommendations grid', async ({ page }) => {
    await page.goBack();
    await page.waitForResponse(
        (response) => response.url().includes('/awards/recommendations/grid-data') && response.ok(),
        { timeout: 30000 },
    );
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

