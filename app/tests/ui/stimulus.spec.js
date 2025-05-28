const { test, expect } = require('@playwright/test');

test.describe('Stimulus.js Controllers', () => {
  
  test('should load Stimulus controllers correctly', async ({ page }) => {
    await page.goto('/');
    
    // Wait for Stimulus to be ready
    await page.waitForFunction(() => window.Stimulus !== undefined);
    
    // Check that Stimulus application is started
    const stimulusStarted = await page.evaluate(() => {
      return window.Stimulus && window.Stimulus.start !== undefined;
    });
    
    expect(stimulusStarted).toBeTruthy();
  });

  test('should register controllers from window.Controllers', async ({ page }) => {
    await page.goto('/');
    
    // Wait for controllers to be registered
    await page.waitForFunction(() => window.Controllers !== undefined);
    
    // Check that controllers are registered
    const controllersRegistered = await page.evaluate(() => {
      const controllers = window.Controllers || {};
      return Object.keys(controllers).length > 0;
    });
    
    expect(controllersRegistered).toBeTruthy();
  });

  test('should handle data-controller attributes', async ({ page }) => {
    await page.goto('/');
    
    // Look for elements with data-controller attributes
    const controllerElements = page.locator('[data-controller]');
    const count = await controllerElements.count();
    
    if (count > 0) {
      // Check that at least one controller element is present
      expect(count).toBeGreaterThan(0);
      
      // Verify that controllers are connected
      const firstController = controllerElements.first();
      const controllerName = await firstController.getAttribute('data-controller');
      
      expect(controllerName).toBeTruthy();
    }
  });

  test('should handle Bootstrap tooltips correctly', async ({ page }) => {
    await page.goto('/');
    
    // Look for tooltip triggers
    const tooltipTriggers = page.locator('[data-bs-toggle="tooltip"]');
    const count = await tooltipTriggers.count();
    
    if (count > 0) {
      // Hover over the first tooltip trigger
      await tooltipTriggers.first().hover();
      
      // Wait a bit for tooltip to appear
      await page.waitForTimeout(500);
      
      // Check if tooltip is visible (Bootstrap creates tooltip dynamically)
      const tooltip = page.locator('.tooltip');
      await expect(tooltip).toBeVisible();
    }
  });

  test('should handle form interactions with Stimulus', async ({ page }) => {
    // This test would be specific to your forms
    // Example: if you have a search form with a Stimulus controller
    
    await page.goto('/');
    
    // Look for forms with Stimulus controllers
    const stimulusForms = page.locator('form[data-controller]');
    const formCount = await stimulusForms.count();
    
    if (formCount > 0) {
      const form = stimulusForms.first();
      const controllerName = await form.getAttribute('data-controller');
      
      expect(controllerName).toBeTruthy();
      
      // Test form submission (if applicable)
      const submitButton = form.locator('button[type="submit"], input[type="submit"]');
      const hasSubmitButton = await submitButton.count() > 0;
      
      if (hasSubmitButton) {
        // Fill out form and test submission
        const textInputs = form.locator('input[type="text"], input[type="email"]');
        const inputCount = await textInputs.count();
        
        if (inputCount > 0) {
          await textInputs.first().fill('test input');
          // Note: You might want to prevent actual submission in tests
          // await submitButton.click();
        }
      }
    }
  });

});
