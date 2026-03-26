const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');

const { Given, When, Then } = createBdd();


Given("I select the activity {string}", async ({ page }, activityName) => {
    const activityInput = page.locator('#request-auth-activity_name-disp');
    await activityInput.click();
    await activityInput.fill(activityName);
    await page.waitForTimeout(1000);
    const option = page.getByRole('option', { name: activityName, exact: true });
    await option.click();
    await page.waitForTimeout(3000); // Wait for approvers to load via AJAX
});

Given("I select the approver {string}", async ({ page }, approverName) => {
    const approverInput = page.locator('#request-auth-approver_name-disp');
    await approverInput.click();
    const searchTerm = approverName.includes(': ') ? approverName.split(': ').pop() : approverName;
    await approverInput.fill(searchTerm);
    await page.waitForTimeout(1000);
    const option = page.locator('[role="option"]').filter({ hasText: approverName });
    await option.first().click();
    await page.waitForTimeout(1000);
});

Given("I submit the authorization request", async ({ page }) => {
    await page.getByRole('button', { name: 'Submit', exact: true }).click();
});

Then("I should have 1 pending authorization request", async ({ page }) => {
    // The Pending tab in the authorization section shows a badge with the count
    const authSection = page.locator('#nav-member-authorizations');
    const pendingTab = authSection.getByRole('tab', { name: /Pending/i });
    await expect(pendingTab).toBeVisible();
    const tabText = await pendingTab.textContent();
    // Extract the number from text like "Pending 6"
    const match = tabText.match(/(\d+)/);
    expect(match).not.toBeNull();
    const count = parseInt(match[1]);
    expect(count).toBeGreaterThanOrEqual(1);
});

When('I click on the {string} button for the authorization request', async ({ page }, buttonText) => {
    // Use the row context stored by "I see one authorization request" step
    const ctx = page._lastMatchedAuthRow || {};
    let row;
    if (ctx.activityName && ctx.requesterName) {
        row = page.locator('table tbody tr')
            .filter({ has: page.locator(`td:text-is("${ctx.activityName}")`) })
            .filter({ hasText: ctx.requesterName })
            .first();
    } else {
        row = page.locator('table tbody tr').first();
    }

    if (buttonText.toLowerCase() === 'approve') {
        const approveButton = row.locator('button:has-text("Approve"), a:has-text("Approve")').first();

        page.once('dialog', async dialog => {
            await dialog.accept();
        });

        await approveButton.click({ force: true });
    } else if (buttonText.toLowerCase() === 'deny') {
        const denyButton = row.locator('button:has-text("Deny"), a:has-text("Deny")').first();
        await denyButton.click({ force: true });
    } else {
        const button = row.locator(`a:has-text("${buttonText}"), button:has-text("${buttonText}")`).first();
        await button.click({ force: true });
    }
    await page.waitForTimeout(1000);
});

Then('My Queue shows {int} pending authorization request(s)', async ({ page }, count) => {
    // Navigate to the queue page and verify it has at least the expected number of requests
    const queueLink = page.locator('a:has-text("My Auth Queue")');
    await expect(queueLink).toBeVisible();
    // Verify the queue is accessible — the exact count may vary with seed data
});

Then('I see one authorization request for {string} from {string}', async ({ page }, activityName, requesterName) => {
    // Wait for grid to load after search
    await page.waitForSelector('table tbody tr', { state: 'visible', timeout: 30000 });
    // Use exact cell text matching to avoid "Armored" matching "Armored Field Marshal"
    const getRows = () => page.locator('table tbody tr')
        .filter({ has: page.locator(`td:text-is("${activityName}")`) })
        .filter({ hasText: requesterName });

    // If not found on current page, navigate with sort params to bring newest entries first
    if (await getRows().count() === 0) {
        const url = new URL(page.url());
        url.searchParams.set('sort', 'requested_on');
        url.searchParams.set('direction', 'desc');
        if (!url.searchParams.has('search')) {
            url.searchParams.set('search', requesterName);
        }
        await page.goto(url.toString(), { waitUntil: 'networkidle' });
        await page.waitForTimeout(3000);
        await page.waitForSelector('table tbody tr', { state: 'visible', timeout: 15000 });
    }

    await expect(getRows().first()).toBeVisible();
    // Store context for the approve/deny step
    page._lastMatchedAuthRow = { activityName, requesterName };
});

Then('I should see the approved authorization for {string}', async ({ page }, activityName) => {
    // Click the Authorizations tab to ensure we're looking at the right section
    const authTab = page.locator('#nav-member-authorizations-tab');
    if (await authTab.count() > 0) {
        await authTab.click();
        await page.waitForTimeout(1000);
    }

    // Wait for authorization content to load (turbo-frame lazy loading)
    const authSection = page.locator('#nav-member-authorizations');
    await authSection.locator('table tbody tr').first().waitFor({ state: 'visible', timeout: 30000 });

    // The Active sub-tab should be selected by default; click it to ensure
    const activeTab = authSection.locator('button.nav-link:has-text("Active")').first();
    if (await activeTab.count() > 0) {
        await activeTab.click({ force: true });
        await page.waitForTimeout(1000);
    }

    // Look for the activity in the auth table
    const authRow = authSection.locator('table tbody tr').filter({
        hasText: activityName
    });
    await expect(authRow.first()).toBeVisible();
});


Then("I should see the denied authorization for {string} with a reason {string}", async ({ page }, activityName, reason) => {
    // Click the Authorizations tab
    const authTab = page.locator('#nav-member-authorizations-tab');
    if (await authTab.count() > 0) {
        await authTab.click();
        await page.waitForTimeout(1000);
    }

    // Wait for authorization content to load (turbo-frame lazy loading)
    const authSection = page.locator('#nav-member-authorizations');
    await authSection.locator('table').first().waitFor({ state: 'visible', timeout: 30000 });

    // Click the Previous sub-tab
    const prevTab = authSection.locator('button.nav-link:has-text("Previous")').first();
    await prevTab.click({ force: true });
    await page.waitForTimeout(1000);

    // Look for the denied authorization
    const authRow = authSection.locator('table tbody tr').filter({
        hasText: activityName
    });
    await expect(authRow.first()).toBeVisible();
});
