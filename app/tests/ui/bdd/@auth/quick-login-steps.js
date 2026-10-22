const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const { getSignOutButton, loginAs, waitForPageBody } = require('../../support/ui-helpers.cjs');

const { Given, When, Then } = createBdd();

const devicePanel = (page) => page.locator('[data-controller="offline-access"]')
    .filter({ visible: true }).first();
const deviceTarget = (page, name) => devicePanel(page).locator(`[data-offline-access-target="${name}"]`);

Given('I start PIN setup on a personal device', async ({ page }, dataTable) => {
    const credentials = dataTable.rowsHash();
    // Exercise the supported PIN-only path without depending on a hardware passkey provider.
    await page.addInitScript(() => Object.defineProperty(window, 'PublicKeyCredential', { value: undefined }));
    await loginAs(page, credentials.email, credentials.password);
    await page.goto('/members/view-mobile-card', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
    await page.getByRole('button', { name: 'Trust this personal device', exact: true }).click();
    await deviceTarget(page, 'password').fill(credentials.password);
    await devicePanel(page).getByRole('button', { name: 'Continue', exact: true }).click();
    await expect(deviceTarget(page, 'pin')).toBeVisible();
    await expect(deviceTarget(page, 'stepHeading')).toBeFocused();
});

When('I save device PIN {string} with confirmation {string}', async ({ page }, pin, confirmation) => {
    await deviceTarget(page, 'pin').fill(pin);
    await deviceTarget(page, 'confirm').fill(confirmation);
    await devicePanel(page).getByRole('button', { name: 'Save PIN and trust device', exact: true }).click();
});

Then('my personal device should be ready offline', async ({ page }) => {
    await expect(deviceTarget(page, 'successHeading')).toBeFocused();
    await expect(deviceTarget(page, 'readiness')).toContainText('Ready offline', { timeout: 30000 });
});

Then('the device PIN mismatch should be announced', async ({ page }) => {
    await expect(deviceTarget(page, 'setupErrorMessage')).toHaveText('Enter the same 6–12 digit PIN in both boxes.');
    await expect(deviceTarget(page, 'setupErrorMessage')).toHaveAttribute('role', 'alert');
    await expect(deviceTarget(page, 'confirm')).toHaveAttribute('aria-invalid', 'true');
    await expect(deviceTarget(page, 'pin')).toBeVisible();
    await expect(deviceTarget(page, 'successHeading')).toBeHidden();
});

When('I finish device setup and log out', async ({ page }) => {
    await devicePanel(page).getByRole('button', { name: 'Done', exact: true }).click();
    await page.goto('/members/logout', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/\/members\/login/);
});

When('I unlock the personal device with PIN {string}', async ({ page }, pin) => {
    await deviceTarget(page, 'unlockPin').fill(pin);
    await deviceTarget(page, 'unlockButton').click();
});

Then('I should be signed in through device unlock', async ({ page }) => {
    await expect(page).toHaveURL(url => /\/members\/view\//.test(url.pathname));
    await expect(getSignOutButton(page)).toBeVisible();
    expect((await page.request.get('/offline/context')).status()).toBe(200);
});

When('I sign out after device unlock', async ({ page }) => {
    await getSignOutButton(page).click();
});

Then('the personal device should be locked on the login page', async ({ page }) => {
    await expect(page).toHaveURL(/\/members\/login/);
    await expect(deviceTarget(page, 'unlockPin')).toBeVisible();
});
