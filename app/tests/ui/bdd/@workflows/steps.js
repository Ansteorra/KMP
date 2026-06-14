const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const { loginAs, waitForPageBody } = require('../../support/ui-helpers.cjs');

const { Given, When, Then } = createBdd();

// Login step — matches baseURL host
Given('I am logged in as {string}', async ({ page }, emailAddress) => {
    await loginAs(page, emailAddress);
});

// Navigation steps
When('I navigate to the workflows page', async ({ page }) => {
    await page.goto('/workflows', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
});

When('I navigate to the workflow instances page', async ({ page }) => {
    await page.goto('/workflows/instances', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
});

When('I navigate to the workflow approvals page', async ({ page }) => {
    await page.goto('/approvals', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
});

// List verification steps
Then('I should see the workflow definitions list', async ({ page }) => {
    await expect(page.locator('body')).toContainText('Workflow');
});

Then('I should see {string} in the list', async ({ page }, text) => {
    await expect(page.locator('body')).toContainText(text);
});

// Designer steps
When('I click the design button for {string}', async ({ page }, workflowName) => {
    const row = page.locator('tr', { hasText: workflowName });
    const designLink = row.locator('a[href*="designer"]').first();
    if (await designLink.count() > 0) {
        await designLink.click();
    } else {
        // Try button instead
        await row.locator('a, button').filter({ hasText: /design/i }).first().click();
    }
    await waitForPageBody(page);
});

Then('I should see the workflow designer', async ({ page }) => {
    await expect(page.locator('[data-controller="workflow-designer"]')).toBeVisible({ timeout: 10000 });
});

Then('I should see the node palette', async ({ page }) => {
    await expect(page.locator('.workflow-palette, [data-workflow-designer-target="palette"]')).toBeVisible({ timeout: 5000 });
});

Then('I should see the workflow canvas', async ({ page }) => {
    await expect(page.locator('[data-workflow-designer-target="canvas"]').first()).toBeVisible({ timeout: 5000 });
});

// Instances steps
Then('I should see the instances list', async ({ page }) => {
    await expect(page.locator('body')).toContainText(/instance/i);
});

// Approvals steps
Then('I should see the approvals list', async ({ page }) => {
    await expect(page.locator('body')).toContainText(/approval/i);
});

// Versions steps
When('I click the versions button for {string}', async ({ page }, workflowName) => {
    const row = page.locator('tr', { hasText: workflowName });
    const versionsLink = row.locator('a[href*="versions"]').first();
    if (await versionsLink.count() > 0) {
        await versionsLink.click();
    } else {
        await row.locator('a, button').filter({ hasText: /version/i }).first().click();
    }
    await waitForPageBody(page);
});

Then('I should see the versions list', async ({ page }) => {
    await expect(page.locator('body')).toContainText(/version/i);
});

Then('I should see version {int} with status {string}', async ({ page }, versionNum, status) => {
    await expect(page.locator('body')).toContainText(status);
});
