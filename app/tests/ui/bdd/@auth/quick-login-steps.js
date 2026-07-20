const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const {
    getSignOutButton,
    isLocatorVisible,
    runAndWaitForNetworkIdle,
    waitForPageBody,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then } = createBdd();

When('I check the quick login checkbox', async ({ page }) => {
    await page.locator('#quick-login-enable').check();
});

When('I enter valid credentials for quick login setup', async ({ page }, dataTable) => {
    const data = dataTable.rowsHash();
    await page.getByRole('textbox', { name: 'Email Address' }).fill(data.email);
    await page.getByRole('textbox', { name: 'Password' }).fill(data.password);
});

Then('I should be redirected to the PIN setup page', async ({ page }) => {
    await page.waitForURL('**/members/setup-quick-login-pin', { timeout: 10000 });
});

When('I enter PIN {string} and confirmation {string}', async ({ page }, pin, confirm) => {
    await page.getByLabel('Quick login PIN', { exact: true }).fill(pin);
    await page.getByLabel('Confirm quick login PIN').fill(confirm);
});

When('I click the save PIN button', async ({ page }) => {
    await page.getByRole('button', { name: 'Save PIN' }).click();
});

Then('I should still be on the PIN setup page', async ({ page }) => {
    await expect(page).toHaveURL(/\/members\/setup-quick-login-pin/);
    await expect(page.getByLabel('Quick login PIN', { exact: true })).toBeVisible();
});

Then('I should be redirected away from PIN setup', async ({ page }) => {
    await page.waitForURL(
        url => !url.pathname.includes('setup-quick-login-pin'),
        { timeout: 10000 }
    );
});

When('I logout from the session', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
    const logoutButton = getSignOutButton(page);
    if (await isLocatorVisible(logoutButton)) {
        await runAndWaitForNetworkIdle(page, () => logoutButton.click());
    }
});

When('I navigate to the login page', async ({ page }) => {
    await page.goto('/members/login', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
});

Then('I should see the quick login tab', async ({ page }) => {
    const modeTabs = page.locator('[data-login-device-auth-target="modeTabs"]');
    await expect(modeTabs).toBeVisible();
    const quickTab = page.locator('[data-login-device-auth-target="quickTabButton"]');
    await expect(quickTab).toBeVisible();
});

Then('the quick login tab should be active', async ({ page }) => {
    const quickTab = page.locator('[data-login-device-auth-target="quickTabButton"]');
    await expect(quickTab).toHaveClass(/active/);
});

When('I enter PIN {string} in the quick login form', async ({ page }, pin) => {
    await page.locator('#quick-login-pin').fill(pin);
});

When('I submit the quick login form', async ({ page }) => {
    await runAndWaitForNetworkIdle(
        page,
        () => page.locator('[data-login-device-auth-target="quickForm"] button[type="submit"]').click(),
    );
});

Then('I should be successfully logged in with quick login', async ({ page }) => {
    await page.waitForURL(
        url => !url.pathname.includes('/members/login'),
        { timeout: 10000 }
    );
    const logoutButton = page.locator('a.btn-outline-secondary').filter({ hasText: /Sign out/i });
    await expect(logoutButton).toBeVisible();
});

Then('I should be on the login page', async ({ page }) => {
    await expect(page).toHaveURL(/\/members\/login/);
});
