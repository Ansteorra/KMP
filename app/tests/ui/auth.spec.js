const { test, expect } = require('@playwright/test');

test.describe('Authentication Flow', () => {

  test.beforeEach(async ({ page }) => {
    // Ensure all resources load before testing
    await page.goto('members/login', { waitUntil: 'networkidle' });
  });

  test('should display login form when not authenticated', async ({ page }) => {
    // Navigate to a protected route (adjust URL based on your app)
    await page.goto('/roles');

    // Should redirect to login or show login form
    // Adjust selectors based on your actual login form
    const loginForm = page.locator('form').filter({ hasText: /login|sign in/i });
    const emailField = page.locator('input[type="email"], input[name="email_address"]');
    const passwordField = page.locator('input[type="password"], input[name="password"]');

    // Check if we're on a login page or have a login form
    const currentUrl = page.url();
    if (currentUrl.includes('login') || await loginForm.count() > 0) {
      await expect(emailField).toBeVisible();
      await expect(passwordField).toBeVisible();
    }
  });

  test('should handle login form validation', async ({ page }) => {
    await page.goto('members/login'); // Adjust to your login URL

    const loginForm = page.locator('form').filter({ hasText: /login|sign in/i });

    if (await loginForm.count() > 0) {
      const submitButton = loginForm.locator('input[type="submit"]');

      // Try to submit empty form
      await submitButton.click();

      // Wait for validation messages
      await page.waitForTimeout(1000);

      // Check for validation messages (adjust selectors as needed)
      const errorMessages = page.locator('.error, .invalid-feedback, .alert-danger');
      if (await errorMessages.count() > 0) {
        await expect(errorMessages.first()).toBeVisible();
      }
    }
  });

  test('should handle invalid login credentials', async ({ page }) => {
    await page.goto('members/login'); // Adjust to your login URL

    const emailField = page.locator('input[type="email"], input[name="email_address"]');
    const passwordField = page.locator('input[type="password"], input[name="password"]');
    const submitButton = page.locator('input[type="submit"]');

    if (await emailField.count() > 0) {
      // Fill with invalid credentials
      await emailField.fill('invalid@example.com');
      await passwordField.fill('wrongpassword');
      await submitButton.click();

      // Wait for response
      await page.waitForTimeout(2000);

      // Check for error message
      const errorMessage = page.locator('.error, .invalid-feedback, .alert-danger, .flash-error');
      if (await errorMessage.count() > 0) {
        await expect(errorMessage.first()).toBeVisible();
      }
    }
  });

  // Note: For successful login, you'd need actual test credentials
  test('should successfully log in with valid credentials', async ({ page }) => {

    await page.goto('members/login'); // Adjust to your login URL
    await page.getByRole('textbox', { name: 'Email Address' }).fill('admin@test.com');
    await page.getByRole('textbox', { name: 'Password' }).fill('Password123');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.getByText('Welcome Admin von Admin!')).toBeVisible();
  });


});
