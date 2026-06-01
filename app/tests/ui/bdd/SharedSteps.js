const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const { getMailpitApiUrl, getUiTestEnvironment } = require('../support/test-environment.cjs');
const {
    clearMailpitMessages,
    clickTabAndWait,
    loginAs,
    runAndWaitForNetworkIdle,
    waitForGridRows,
    waitForPageBody,
} = require('../support/ui-helpers.cjs');

const { Given, When, Then } = createBdd();
const { mailpitUrl } = getUiTestEnvironment();
const GRID_ROWS_SELECTOR = 'table.table tbody tr:visible, .dataTable tbody tr:visible';

const openGridFilter = async (page) => {
    const filterBtn = page.locator('#filterDropdown, button:has-text("Filter")').first();
    await filterBtn.click();

    const searchInput = page.locator('[data-grid-view-target="searchInput"]');
    await expect(searchInput).toBeVisible({ timeout: 15000 });

    return { filterBtn, searchInput };
};

const waitForGridSearchResponse = async (page, searchInput) => {
    const currentPath = new URL(page.url()).pathname;

    await Promise.all([
        page.waitForResponse((response) => {
            const responseUrl = new URL(response.url());
            return response.status() === 200
                && responseUrl.pathname === currentPath
                && responseUrl.searchParams.has('search');
        }, { timeout: 30000 }),
        searchInput.press('Enter'),
    ]);

    await waitForGridRows(page, GRID_ROWS_SELECTOR);
};

const waitForMailpitMessageCount = async (page, query, minCount = 1) => {
    await expect.poll(async () => {
        const response = await page.request.get(getMailpitApiUrl('api/v1/search'), {
            params: { query },
        });
        if (!response.ok()) {
            return 0;
        }

        const data = await response.json();
        return data.total ?? 0;
    }, {
        timeout: 15000,
    }).toBeGreaterThanOrEqual(minCount);
};

const clickVisible = async (locator) => {
    const count = await locator.count();
    for (let i = 0; i < count; i += 1) {
        const candidate = locator.nth(i);
        if (await candidate.isVisible()) {
            await candidate.click();
            return;
        }
    }

    await locator.first().click();
};

// check if user is logged in
Given('I am logged in as {string}', async ({ page }, emailAddress) => {
    await loginAs(page, emailAddress);
});

// Given I am on my profile page
Given('I navigate to my profile page', async ({ page }) => {
    await page.goto('/members/profile', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
});

Then('I should see the flash message {string}', async ({ page }, message) => {
    const flashMessage = page.getByRole('alert').first();
    await expect(flashMessage).toContainText(message, { timeout: 15000 });
});

Given('I click on the {string} button', async ({ page }, buttonText) => {
    await page.getByRole('button', { name: buttonText, exact: true }).click();
});

Given('I am at the test email inbox', async ({ page }) => {
    await page.goto(mailpitUrl, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
});

When('I check for an email with subject {string}', async ({ page }, subject) => {
    await waitForMailpitMessageCount(page, `subject:"${subject}"`);
    await page.goto(mailpitUrl, { waitUntil: 'domcontentloaded' });
    const emailRow = page.locator(`.subject b:has-text("${subject}")`).first();
    await expect(emailRow).toBeVisible();
});

When('I open the email with subject {string}', async ({ page }, subject) => {
    await waitForMailpitMessageCount(page, `subject:"${subject}"`);
    await page.goto(mailpitUrl, { waitUntil: 'domcontentloaded' });
    const emailRow = page.locator(`.subject b:has-text("${subject}")`).first();
    await emailRow.click();
});

Then('the email should start with the body:', async ({ page }, expectedContent) => {
    const emailBody = await page.locator('#nav-plain-text div').textContent();
    // Normalize non-breaking spaces and middots that email formatters add
    const normalized = emailBody.replace(/[·\u00B7\u00A0]/g, ' ').replace(/\s+/g, ' ').trim();
    const expectedNormalized = expectedContent.replace(/\s+/g, ' ').trim();
    expect(normalized).toContain(expectedNormalized);
});

Then('the email should be addressed to {string}', async ({ page }, emailAddress) => {
    const toCell = page.locator('table tr').filter({ hasText: 'To' }).getByRole('link', { name: emailAddress, exact: true });
    await expect(toCell).toBeVisible();
});

Then('the email should be from {string}', async ({ page }, emailAddress) => {
    const fromCell = page.locator('table tr').filter({ hasText: 'From' }).getByRole('link', { name: emailAddress, exact: true });
    await expect(fromCell).toBeVisible();
});

Then('there should be an email to {string} with subject {string}', async ({ page }, recipient, subject) => {
    await waitForMailpitMessageCount(page, `to:${recipient} subject:"${subject}"`);
    const response = await page.request.get(getMailpitApiUrl('api/v1/search'), {
        params: { query: `to:${recipient} subject:"${subject}"` },
    });
    expect(response.ok()).toBeTruthy();
    const data = await response.json();
    expect(data.total).toBeGreaterThanOrEqual(1);
});

Given('I delete all test emails', async ({ page }) => {
    await clearMailpitMessages(page.request);
});

// Authorization Queue Steps
When('I click on my name {string}', async ({ page }, userName) => {
    if (await page.getByRole('heading', { name: new RegExp(userName) }).isVisible()) {
        return;
    }

    // Click on the profile link in the navigation; the visible label may be rendered outside the link.
    const nameLink = page.locator('a.nav-link[href="/members/profile"], a[href="/members/profile"]');
    await clickVisible(nameLink);
    await page.waitForTimeout(500);
});

When('I click on the {string} link', async ({ page }, linkText) => {
    if (linkText === 'My Auth Queue') {
        await page.goto('/activities/authorization-approvals/my-queue', { waitUntil: 'networkidle' });
        return;
    }

    // Click on a link with the specified text
    await clickVisible(page.getByRole('link', { name: new RegExp(linkText) }));
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
    await clearMailpitMessages(page.request);
});

// ── Reusable DataGrid Step Definitions ──────────────────────────────

When('I search the grid for {string}', async ({ page }, searchText) => {
    await waitForGridRows(page, GRID_ROWS_SELECTOR);
    const { filterBtn, searchInput } = await openGridFilter(page);
    await searchInput.fill(searchText);
    await waitForGridSearchResponse(page, searchInput);
    await page.keyboard.press('Escape');
    await expect(filterBtn).toHaveAttribute('aria-expanded', 'false');
});

Given('I sort the grid by {string} descending', async ({ page }, columnName) => {
    const url = new URL(page.url());
    const sortKey = columnName
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
    url.searchParams.set('sort', sortKey);
    url.searchParams.set('direction', 'desc');
    url.searchParams.set('dirty[sort]', '1');
    await page.goto(url.toString(), { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
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
    const tab = page.getByRole('tab', { name: tabName });
    await clickTabAndWait(tab);
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
    await runAndWaitForNetworkIdle(page, () => page.getByRole('button', { name: /submit|save/i }).click());
});

// ── Navigation ──────────────────────────────────────────────────────

Given('I navigate to {string}', async ({ page }, path) => {
    await page.goto(path, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
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

Then('I should be on a page containing {string}', async ({ page }, text) => {
    await expect(page.locator('body')).toContainText(text);
});

Then('the grid should show {int} or more results', async ({ page }, minCount) => {
    // Wait for turbo-frame grid to load
    const rows = await waitForGridRows(page, GRID_ROWS_SELECTOR);
    const count = await rows.count();
    expect(count).toBeGreaterThanOrEqual(minCount);
});

When('I search for {string} in the grid search box', async ({ page }, searchText) => {
    // Grid search is inside Filter dropdown — click to open it first
    await waitForGridRows(page, GRID_ROWS_SELECTOR);
    const { filterBtn, searchInput } = await openGridFilter(page);
    await searchInput.fill(searchText);
    await waitForGridSearchResponse(page, searchInput);
    await page.keyboard.press('Escape');
    await expect(filterBtn).toHaveAttribute('aria-expanded', 'false');
});

Then('the grid should show results containing {string}', async ({ page }, text) => {
    await waitForGridRows(page, GRID_ROWS_SELECTOR);
    const grid = page.locator('table.table, .dataTable').first();
    await expect(grid).toContainText(text);
});
