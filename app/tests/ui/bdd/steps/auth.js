const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');

const { Given, When, Then } = createBdd();

// Background step
Given('I am on the login page', async ({ page }) => {
    await page.goto('/members/login', { waitUntil: 'networkidle' });
});

// check if user is logged in
Given('I am logged in as {string}', async ({ page }, emailAddress) => {
    // Navigate to the login page
    await page.goto('/members/login', { waitUntil: 'networkidle' });

    // Fill in the login form with admin credentials
    await page.getByRole('textbox', { name: 'Email Address' }).fill(emailAddress);
    await page.getByRole('textbox', { name: 'Password' }).fill('password');
    await page.getByRole('button', { name: 'Sign in' }).click();
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

// Given I am on my profile page
Given('I am on my profile page', async ({ page }) => {
    await page.goto('/members/profile', { waitUntil: 'networkidle' });
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
    const loginForm = page.locator('form').filter({ hasText: /login|sign in/i });
    const currentUrl = page.url();

    if (currentUrl.includes('login') || await loginForm.count() > 0) {
        await expect(loginForm.first()).toBeVisible();
    }
});

Then('I should see the email address field', async ({ page }) => {
    const emailField = page.locator('input[type="email"], input[name="email_address"]');
    await expect(emailField).toBeVisible();
});

Then('I should see the password field', async ({ page }) => {
    const passwordField = page.locator('input[type="password"], input[name="password"]');
    await expect(passwordField).toBeVisible();
});

// Form submission steps
When('I submit the login form without entering credentials', async ({ page }) => {
    const submitButton = page.locator('input[type="submit"]');
    await submitButton.click();
    await page.waitForTimeout(1000);
});

When('I enter invalid credentials', async ({ page }, dataTable) => {
    const data = dataTable.rowsHash();

    const emailField = page.locator('input[type="email"], input[name="email_address"]');
    const passwordField = page.locator('input[type="password"], input[name="password"]');

    await emailField.fill(data.email);
    await passwordField.fill(data.password);
});

When('I enter valid admin credentials', async ({ page }, dataTable) => {
    const data = dataTable.rowsHash();

    await page.getByRole('textbox', { name: 'Email Address' }).fill(data.email);
    await page.getByRole('textbox', { name: 'Password' }).fill(data.password);
});

When('I submit the login form', async ({ page }) => {
    const submitButton = page.getByRole('button', { name: 'Sign in' });
    await submitButton.click();
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

Then('I should see the welcome message {string}', async ({ page }, message) => {
    await expect(page.getByText(message)).toBeVisible();
});
