const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');

const { Given, When, Then } = createBdd();


Given("I select the activity {string}", async ({ page }, activityName) => {
    await page.getByRole('textbox', { name: 'Activity' }).click();
    await page.getByRole('textbox', { name: 'Activity' }).fill(activityName);
    await page.waitForTimeout(1000);
    await page.getByRole('option', { name: activityName, exact: true }).click();
    await page.waitForTimeout(1000);
});

Given("I select the approver {string}", async ({ page }, approverName) => {
    await page.getByRole('textbox', { name: 'Send Request To Send Request' }).click();
    await page.getByRole('textbox', { name: 'Send Request To Send Request' }).fill(approverName);
    await page.waitForTimeout(1000); // Wait for options to load
    await page.getByRole('option', { name: approverName, exact: true }).click();
    await page.waitForTimeout(1000); // Wait for options to load
});

Given("I submit the authorization request", async ({ page }) => {
    await page.getByRole('button', { name: 'Submit', exact: true }).click();
});

Then("I should have 1 pending authorization request", async ({ page }) => {
    const pendingRequests = await page.locator('#nav-pending-authorization-tab span.badge').textContent();
    expect(pendingRequests).toBe("1");
});

When('I click on the {string} button for the authorization request', async ({ page }, buttonText) => {
    // Find the first row in the pending approvals table
    const row = await page.locator('#nav-pending-approvals table tbody tr').first();

    if (buttonText.toLowerCase() === 'approve') {
        // Handle the Approve button which has a confirmation dialog
        const approveButton = await row.locator('td.actions a.btn-primary:has-text("Approve")').first();

        // Set up dialog handler to accept the confirmation
        page.once('dialog', async dialog => {
            expect(dialog.type()).toBe('confirm');
            await dialog.accept();
        });

        // Click the approve button which will trigger the confirmation and form submission
        await approveButton.click();
    } else if (buttonText.toLowerCase() === 'deny') {
        // Handle the Deny button which opens a modal
        const denyButton = await row.locator('td.actions button.deny-btn:has-text("Deny")').first();
        await denyButton.click();
    } else {
        // Generic fallback for other buttons
        const button = await row.locator(`td.actions a:has-text("${buttonText}"), td.actions button:has-text("${buttonText}")`).first();
        await button.click();
    }
});

Then('My Queue shows {int} pending authorization request(s)', async ({ page }, count) => {
    // Check for the queue indicator showing the number of pending requests
    const queueCount = await page.locator('.sublink.nav-link span:has-text("My Auth Queue") .badge').textContent();
    await expect(queueCount).toEqual(count.toString());
});

Then('I see one authorization request for {string} from {string}', async ({ page }, activityName, requesterName) => {
    // Verify that there's an authorization request showing the activity and requester
    const authRequest = await page.locator('#nav-pending-approvals div table tbody tr').filter({
        hasText: activityName
    }).filter({
        hasText: requesterName
    });
    await expect(authRequest).toBeVisible();
});

Then('I should see the approved authorization for {string}', async ({ page }, activityName) => {
    // Scroll to the current authorization frame to trigger lazy loading
    await page.locator('#current-authorization-frame').scrollIntoViewIfNeeded();

    // Wait for the current authorization frame to load its content
    await page.waitForSelector('#current-authorization-frame[complete]', { state: 'attached' });

    // Look for the authorization in the current authorizations table
    const authorizationRow = await page.locator('#nav-current-authorization table tbody tr').filter({
        hasText: activityName
    });

    // Verify the authorization row is visible
    await expect(authorizationRow).toBeVisible();

    // Optionally verify it has proper dates and actions
    const authCell = await authorizationRow.locator('td').first();
    await expect(authCell).toHaveText(activityName);
});


Then("I should see the denied authorization for {string} with a reason {string}", async ({ page }, activityName, reason) => {
    // Scroll to the current authorization frame to trigger lazy loading
    await page.locator('#nav-previous-authorization-tab').click();
    await page.locator('#previous-authorization-frame').scrollIntoViewIfNeeded();

    // Wait for the current authorization frame to load its content
    await page.waitForSelector('#previous-authorization-frame[complete]', { state: 'attached' });

    // Look for the denied authorization in the current authorizations table
    const authorizationRow = await page.locator('#nav-previous-authorization table tbody tr').filter({
        hasText: activityName
    }).filter({
        hasText: reason
    });

    // Verify the authorization row is visible
    await expect(authorizationRow).toBeVisible();

    // Optionally verify it has proper dates and actions
    const authCell = await authorizationRow.locator('td').first();
    await expect(authCell).toHaveText(activityName);
});
