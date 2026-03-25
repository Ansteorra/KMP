const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');

const { Given, When, Then } = createBdd();

// check if user is logged in
Given('I am logged in as {string}', async ({ page }, emailAddress) => {
    // Navigate to the login page
    await page.goto('https://localhost:8080/', { waitUntil: 'networkidle' });
    await page.goto('/members/login', { waitUntil: 'networkidle' });

    // Fill in the login form with admin credentials
    await page.getByRole('textbox', { name: 'Email Address' }).fill(emailAddress);
    await page.getByRole('textbox', { name: 'Password' }).fill('Password123');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForTimeout(1000); // Wait for the login to complete
});

// Given I am on my profile page
Given('I navigate to my profile page', async ({ page }) => {
    await page.goto('/members/profile', { waitUntil: 'networkidle' });
});

Then('I should see the flash message {string}', async ({ page }, message) => {
    await page.getByRole('alert', { classname: "alert" });
    //check the message we get is the one we expect
    const flashMessage = await page.getByRole('alert', { classname: 'alert' }).textContent();
    expect(flashMessage).toContain(message);
});

Given('I click on the {string} button', async ({ page }, buttonText) => {
    await page.getByRole('button', { name: buttonText, exact: true }).click();
});

Given('I am at the test email inbox', async ({ page }) => {
    await page.goto('http://localhost:8025', { waitUntil: 'networkidle' });
});

When('I check for an email with subject {string}', async ({ page }, subject) => {
    // Example: Check for an email with the given subject in the test inbox
    const emailRow = await page.locator(`.subject b:has-text("${subject}")`).first();
    await expect(emailRow).toBeVisible();
});

When('I open the email with subject {string}', async ({ page }, subject) => {
    // Example: Open the email with the given subject
    const emailRow = await page.locator(`.subject b:has-text("${subject}")`).first();
    await emailRow.click();
});

Then('the email should start with the body:', async ({ page }, expectedContent) => {
    // Example: Check if the email body contains the expected content
    const emailBody = await page.locator('#nav-plain-text div').textContent();
    expect(emailBody).toContain(expectedContent);
});

// Authorization Queue Steps
When('I click on my name {string}', async ({ page }, userName) => {
    // Click on the user's name in the navigation or profile area
    await page.locator(`.nav-link span:has-text('${userName}')`).click();
});

When('I click on the {string} link', async ({ page }, linkText) => {
    // Click on a link with the specified text
    await page.getByRole('link', { name: linkText }).click();
});

When('I enter the value {string} in the input field with label {string}', async ({ page }, value, label) => {
    // Fill in an input field with the specified label
    await page.getByLabel(label).fill(value);
});

When('I select the option {string} from the dropdown with label {string}', async ({ page }, option, label) => {
    // Select an option from a dropdown with the specified label
    await page.getByLabel(label).selectOption(option);
});

Given("The test inbox is empty", async ({ page }) => {
    await page.goto('http://localhost:8025', { waitUntil: 'networkidle' });
    const deleteAllButton = await page.getByRole('button', { name: ' Delete all' });

    // Check if the delete button is enabled before clicking
    if (await deleteAllButton.isEnabled()) {
        await deleteAllButton.click();
        await page.getByRole('button', { name: 'Delete', exact: true }).click();
    } else {
        console.log('❗️ Delete all button is disabled, skipping emptying inbox');
    }
});

// ── Reusable DataGrid Step Definitions ──────────────────────────────

When('I search the grid for {string}', async ({ page }, searchText) => {
    const searchInput = page.locator('[data-grid-filter-target="searchBox"], input[type="search"], .dataTables_filter input').first();
    await searchInput.fill(searchText);
    await page.waitForTimeout(500);
});

Then('the grid should contain {string}', async ({ page }, text) => {
    const grid = page.locator('table.table, .dataTable, [data-controller*="grid"]').first();
    await expect(grid).toContainText(text);
});

Then('the grid should not contain {string}', async ({ page }, text) => {
    const grid = page.locator('table.table, .dataTable, [data-controller*="grid"]').first();
    await expect(grid).not.toContainText(text);
});

When('I click on the grid row containing {string}', async ({ page }, text) => {
    const row = page.locator(`table tbody tr:has-text("${text}")`).first();
    await row.click();
});

// ── Tab Navigation ──────────────────────────────────────────────────

When('I click the {string} tab', async ({ page }, tabName) => {
    await page.getByRole('tab', { name: tabName }).click();
    await page.waitForTimeout(300);
});

Then('the {string} tab should be active', async ({ page }, tabName) => {
    const tab = page.getByRole('tab', { name: tabName });
    await expect(tab).toHaveClass(/active/);
});

Then('I should see {string} in the active tab', async ({ page }, text) => {
    const activePanel = page.locator('.tab-pane.active.show, .tab-pane.active');
    await expect(activePanel).toContainText(text);
});

// ── Form Interactions ───────────────────────────────────────────────

When('I fill in {string} with {string}', async ({ page }, label, value) => {
    await page.getByLabel(label).fill(value);
});

When('I select {string} from {string}', async ({ page }, option, label) => {
    await page.getByLabel(label).selectOption({ label: option });
});

When('I check the {string} checkbox', async ({ page }, label) => {
    await page.getByLabel(label).check();
});

When('I submit the form', async ({ page }) => {
    await page.getByRole('button', { name: /submit|save/i }).click();
    await page.waitForLoadState('networkidle');
});

// ── Navigation ──────────────────────────────────────────────────────

Given('I navigate to {string}', async ({ page }, path) => {
    await page.goto(path, { waitUntil: 'networkidle' });
});

Then('I should be on {string}', async ({ page }, path) => {
    expect(page.url()).toContain(path);
});

Then('the page should contain {string}', async ({ page }, text) => {
    await expect(page.locator('body')).toContainText(text);
});

Then('the page should not contain {string}', async ({ page }, text) => {
    await expect(page.locator('body')).not.toContainText(text);
});
