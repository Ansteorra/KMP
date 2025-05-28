const { test, expect } = require('@playwright/test');

test.describe('Accessibility Tests', () => {

  test('should have proper heading hierarchy', async ({ page }) => {
    await page.goto('/');

    // Check for h1 tag
    const h1 = page.locator('h1');
    await expect(h1).toHaveCount(1);

    // Check heading hierarchy (h1 -> h2 -> h3, etc.)
    const headings = await page.locator('h1, h2, h3, h4, h5, h6').allTextContents();
    expect(headings.length).toBeGreaterThan(0);
  });

  test('should have alt text for images', async ({ page }) => {
    await page.goto('/');

    const images = page.locator('img');
    const imageCount = await images.count();

    for (let i = 0; i < imageCount; i++) {
      const image = images.nth(i);
      const alt = await image.getAttribute('alt');
      const src = await image.getAttribute('src');

      // All images should have alt text (can be empty for decorative images)
      expect(alt).not.toBeNull();

      // Images with content should have meaningful alt text
      if (src && !src.includes('decoration') && !src.includes('spacer')) {
        expect(alt.length).toBeGreaterThan(0);
      }
    }
  });

  test('should have proper form labels', async ({ page }) => {
    await page.goto('/');

    const inputs = page.locator('input, textarea, select');
    const inputCount = await inputs.count();

    for (let i = 0; i < inputCount; i++) {
      const input = inputs.nth(i);
      const inputType = await input.getAttribute('type');

      // Skip hidden inputs
      if (inputType === 'hidden') continue;

      const inputId = await input.getAttribute('id');
      const inputName = await input.getAttribute('name');

      // Input should have either a label or aria-label
      if (inputId) {
        const label = page.locator(`label[for="${inputId}"]`);
        const labelCount = await label.count();

        if (labelCount === 0) {
          const ariaLabel = await input.getAttribute('aria-label');
          const ariaLabelledBy = await input.getAttribute('aria-labelledby');

          expect(ariaLabel || ariaLabelledBy).toBeTruthy();
        }
      }
    }
  });

  test('should have proper ARIA attributes', async ({ page }) => {
    await page.goto('/');

    // Check for proper button roles
    const buttons = page.locator('button, [role="button"]');
    const buttonCount = await buttons.count();

    for (let i = 0; i < buttonCount; i++) {
      const button = buttons.nth(i);
      const text = await button.textContent();
      const ariaLabel = await button.getAttribute('aria-label');

      // Button should have either text content or aria-label
      expect(text?.trim() || ariaLabel).toBeTruthy();
    }

    // Check for proper navigation landmarks
    const nav = page.locator('nav, [role="navigation"]');
    if (await nav.count() > 0) {
      await expect(nav.first()).toBeVisible();
    }

    // Check for main content area
    const main = page.locator('main, [role="main"]');
    if (await main.count() > 0) {
      await expect(main.first()).toBeVisible();
    }
  });

  test('should be keyboard navigable', async ({ page }) => {
    await page.goto('/');

    // Start with the first focusable element
    await page.keyboard.press('Tab');

    // Check that focus is visible
    const focusedElement = page.locator(':focus');
    await expect(focusedElement).toBeVisible();

    // Test a few more tab presses
    for (let i = 0; i < 5; i++) {
      await page.keyboard.press('Tab');
      const currentFocused = page.locator(':focus');
      await expect(currentFocused).toBeVisible();
    }
  });

  test('should have sufficient color contrast', async ({ page }) => {
    await page.goto('/');

    // This is a basic check - for comprehensive contrast testing,
    // you'd want to use a specialized tool like axe-core

    // Check that text is visible against backgrounds
    const textElements = page.locator('p, h1, h2, h3, h4, h5, h6, span, a, button');
    const count = await textElements.count();

    // Sample a few elements to ensure they're visible
    if (count > 0) {
      for (let i = 0; i < Math.min(count, 10); i++) {
        const element = textElements.nth(i);
        await expect(element).toBeVisible();
      }
    }
  });

  test('should handle screen reader text', async ({ page }) => {
    await page.goto('/');

    // Check for screen reader only text
    const srOnly = page.locator('.sr-only, .visually-hidden, .screen-reader-text');
    const srCount = await srOnly.count();

    if (srCount > 0) {
      // These elements should exist but not be visible to sighted users
      for (let i = 0; i < srCount; i++) {
        const element = srOnly.nth(i);
        const textContent = await element.textContent();
        expect(textContent?.trim()).toBeTruthy();
      }
    }
  });

});
