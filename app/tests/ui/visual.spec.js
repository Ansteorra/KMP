const { test, expect } = require('@playwright/test');

test.describe('Visual Regression Tests', () => {

  test('homepage visual comparison', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Hide dynamic content that might cause flaky tests
    await page.addStyleTag({
      content: `
        .timestamp, .current-time, .last-updated { visibility: hidden !important; }
        .loading, .spinner { display: none !important; }
      `
    });

    // Take full page screenshot
    await expect(page).toHaveScreenshot('homepage-full.png', {
      fullPage: true,
      animations: 'disabled'
    });
  });

  test('navigation visual comparison', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Screenshot just the navigation area
    const navigation = page.locator('nav, .navbar, header').first();
    if (await navigation.count() > 0) {
      await expect(navigation).toHaveScreenshot('navigation.png');
    }
  });

  test('responsive design - tablet view', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveScreenshot('homepage-tablet.png', {
      fullPage: true,
      animations: 'disabled'
    });
  });

  test('responsive design - mobile view', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveScreenshot('homepage-mobile.png', {
      fullPage: true,
      animations: 'disabled'
    });
  });

  test('form visual comparison', async ({ page }) => {
    await page.goto('/');

    // Find the first form on the page
    const form = page.locator('form').first();
    if (await form.count() > 0) {
      await expect(form).toHaveScreenshot('form-default.png');
    }
  });

  test('dark mode visual comparison', async ({ page }) => {
    // If your app supports dark mode
    await page.goto('/');

    // Add dark mode class or toggle dark mode
    await page.evaluate(() => {
      document.body.classList.add('dark-mode', 'dark-theme');
      document.documentElement.setAttribute('data-theme', 'dark');
    });

    await page.waitForTimeout(500); // Wait for theme transition

    await expect(page).toHaveScreenshot('homepage-dark.png', {
      fullPage: true,
      animations: 'disabled'
    });
  });

});
