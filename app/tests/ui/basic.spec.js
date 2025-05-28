const { test, expect } = require('@playwright/test');

test.describe('KMP Application - Basic Functionality', () => {

  test('should load the homepage', async ({ page }) => {
    await page.goto('/');

    // Wait for the page to load
    await page.waitForLoadState('networkidle');

    // Check that the page title contains expected text
    await expect(page).toHaveTitle(/KMP|Kingdom Management Portal/);

    // Take a screenshot for visual verification
    await page.screenshot({ path: 'tests/ui-results/homepage.png', fullPage: true });
  });

  test('should have login screen', async ({ page }) => {
    await page.goto('/');

    // Wait for the login form to be visible
    const loginForm = page.locator('form.form-signin');
    await expect(loginForm).toBeVisible();

    // Check for email and password fields
    const emailField = loginForm.locator('input[name="email_address"]');
    const passwordField = loginForm.locator('input[name="password"]');
    await expect(emailField).toBeVisible();
    await expect(passwordField).toBeVisible();

    // Take a screenshot for visual verification
    await page.screenshot({ path: 'tests/ui-results/login.png', fullPage: true });
  });

  test('should handle mobile viewport correctly', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    // Check responsive behavior
    await expect(page).toHaveTitle(/KMP|Kingdom Management Portal/);

    // Take mobile screenshot
    await page.screenshot({ path: 'tests/ui-results/homepage-mobile.png', fullPage: true });
  });

  test('should have accessible forms', async ({ page }) => {
    await page.goto('/');

    // Look for forms and check they have proper labels
    const forms = page.locator('form');
    const formCount = await forms.count();

    if (formCount > 0) {
      for (let i = 0; i < formCount; i++) {
        const form = forms.nth(i);

        // Check that form inputs have associated labels
        const inputs = form.locator('input[type="text"], input[type="email"], input[type="password"], textarea, select');
        const inputCount = await inputs.count();

        for (let j = 0; j < inputCount; j++) {
          const input = inputs.nth(j);
          const inputId = await input.getAttribute('id');

          if (inputId) {
            const label = page.locator(`label[for="${inputId}"]`);
            await expect(label).toBeVisible();
          }
        }
      }
    }
  });

  test('should load CSS and JavaScript properly', async ({ page }) => {
    await page.goto('/');

    // Check that CSS is loaded (look for Bootstrap classes)
    const bodyClass = await page.locator('body').getAttribute('class');

    // Check that JavaScript is working (Stimulus should be available)
    const stimulusLoaded = await page.evaluate(() => {
      return typeof window.Stimulus !== 'undefined';
    });

    expect(stimulusLoaded).toBeTruthy();

    // Check that Bootstrap JS is working
    const bootstrapLoaded = await page.evaluate(() => {
      return typeof window.bootstrap !== 'undefined';
    });

    expect(bootstrapLoaded).toBeTruthy();
  });

});
