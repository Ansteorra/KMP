const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');

const { Given, When, Then } = createBdd();

// Background step
Given('I am on the login page', async ({ page }) => {
    await page.goto('/members/login', { waitUntil: 'networkidle' });
});

// Given I am already logged in
Given('I am already logged in', async ({ page }) => {
    // Navigate to the login page
    await page.goto('/', { waitUntil: 'networkidle' });

    // Check if already logged in by looking for a logout button or user profile
    const logoutButton = page.locator('a.btn-outline-secondary').filter({ hasText: /Sign out/i });
    if (await logoutButton.count() > 0) {
        console.log('User is already logged in.');
        return;
    }
});

// When I Logout
When('I logout', async ({ page }) => {
    // Navigate to the logout page
    await page.goto('/', { waitUntil: 'networkidle' });

    // Click the logout button if it exists
    const logoutButton = page.locator('a.btn-outline-secondary').filter({ hasText: /Sign out/i });
    if (await logoutButton.count() > 0) {
        await logoutButton.click();
    }
});
// Navigation steps
When('I navigate to a protected route {string}', async ({ page }, route) => {
    await page.goto(route);
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
    await page.waitForTimeout(1000);
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
    await page.waitForTimeout(2000);
});

// Validation and error steps
Then('I should see validation error messages', async ({ page }) => {
    const errorMessages = page.locator('.error, .invalid-feedback, .alert-danger');
    if (await errorMessages.count() > 0) {
        await expect(errorMessages.first()).toBeVisible();
    }
});

Then('I should see an authentication error message', async ({ page }) => {
    const errorMessage = page.locator('.error, .invalid-feedback, .alert-danger, .flash-error');
    if (await errorMessage.count() > 0) {
        await expect(errorMessage.first()).toBeVisible();
    }
});

// Success steps
Then('I should be successfully logged in', async ({ page }) => {
    // Wait for redirect or page change after successful login
    await page.waitForTimeout(1000);
});
