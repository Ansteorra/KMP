const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const {
    getSignOutButton,
    isLocatorVisible,
    waitForPageBody,
    waitForSuccessfulLogin,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then } = createBdd();

// Background step
Given('I am on the login page', async ({ page }) => {
    await page.goto('/members/login', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
});

// Given I am already logged in
Given('I am already logged in', async ({ page }) => {
    // Navigate to the login page
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);

    // Check if already logged in by looking for a logout button or user profile
    const logoutButton = getSignOutButton(page);
    if (await isLocatorVisible(logoutButton)) {
        console.log('User is already logged in.');
        return;
    }
});

// When I Logout
When('I logout', async ({ page }) => {
    // Navigate to the logout page
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);

    // Click the logout button if it exists
    const logoutButton = getSignOutButton(page);
    if (await isLocatorVisible(logoutButton)) {
        await logoutButton.click();
        await waitForPageBody(page);
    }
});
// Navigation steps
When('I navigate to a protected route {string}', async ({ page }, route) => {
    await page.goto(route, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
});

// Form visibility steps  
Then('I should see the login form', async ({ page }) => {
    const loginController = page.locator('[data-controller="login-device-auth"]');
    await expect(loginController).toBeVisible();
});

Then('I should see the email address field', async ({ page }) => {
    await expect(page.locator('#email-address')).toBeVisible();
});

Then('I should see the password field', async ({ page }) => {
    await expect(page.locator('#password')).toBeVisible();
});

// Form submission steps
When('I submit the login form without entering credentials', async ({ page }) => {
    await page.locator('input[type="submit"][value="Sign in"]').click();
});

When('I enter invalid credentials', async ({ page }, dataTable) => {
    const data = dataTable.rowsHash();

    await page.locator('#email-address').fill(data.email);
    await page.locator('#password').fill(data.password);
});

When('I enter valid admin credentials', async ({ page }, dataTable) => {
    const data = dataTable.rowsHash();

    await page.locator('#email-address').fill(data.email);
    await page.locator('#password').fill(data.password);
});

When('I submit the login form', async ({ page }) => {
    await page.locator('input[type="submit"][value="Sign in"]').click();
});

// Validation and error steps
Then('I should see validation error messages', async ({ page }) => {
    const errorMessages = page.locator('.error, .invalid-feedback, .alert-danger');
    await expect(errorMessages.first()).toBeVisible({ timeout: 15000 });
});

Then('I should see an authentication error message', async ({ page }) => {
    const errorMessage = page.locator('.error, .invalid-feedback, .alert-danger, .flash-error');
    await expect(errorMessage.first()).toBeVisible({ timeout: 15000 });
});

// Success steps
Then('I should be successfully logged in', async ({ page }) => {
    await waitForSuccessfulLogin(page, 15000);
    await expect(getSignOutButton(page)).toBeVisible({ timeout: 15000 });
});
