const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const {
    loginAs,
    waitForTurboFrame,
    assertUrlContainsQuery,
    waitForPageBody,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then } = createBdd();

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
