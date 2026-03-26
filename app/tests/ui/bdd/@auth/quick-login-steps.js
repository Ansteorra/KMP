const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');

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
    await page.waitForTimeout(1000);
});

Then('I should still be on the PIN setup page', async ({ page }) => {
    await page.waitForTimeout(500);
    expect(page.url()).toContain('/members/setup-quick-login-pin');
});

Then('I should be redirected away from PIN setup', async ({ page }) => {
    await page.waitForURL(
        url => !url.pathname.includes('setup-quick-login-pin'),
        { timeout: 10000 }
    );
});

When('I logout from the session', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });
    const logoutButton = page.locator('a.btn-outline-secondary').filter({ hasText: /Sign out/i });
    if (await logoutButton.count() > 0) {
        await logoutButton.click();
        await page.waitForLoadState('networkidle');
    }
});

When('I navigate to the login page', async ({ page }) => {
    await page.goto('/members/login', { waitUntil: 'networkidle' });
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
    await page.locator('[data-login-device-auth-target="quickForm"] button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
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
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('/members/login');
});
