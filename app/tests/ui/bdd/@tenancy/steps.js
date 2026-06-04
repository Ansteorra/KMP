const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');

const { createTenantContext } = require('../../support/tenant-context.cjs');
const {
    loginAs,
    waitForPageBody,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then, After } = createBdd();

const TENANCY_STATE_KEY = '__tenancyIsolation';

const tenancyState = (page) => {
    if (!page[TENANCY_STATE_KEY]) {
        page[TENANCY_STATE_KEY] = {
            contexts: [],
            currentPage: null,
            currentTenant: null,
            lastResponse: null,
        };
    }

    return page[TENANCY_STATE_KEY];
};

const activeTenantPage = (page) => {
    const state = tenancyState(page);
    if (!state.currentPage) {
        throw new Error('No active tenant page. Log into a tenant before navigating.');
    }

    return state.currentPage;
};

const openTenantSession = async (page, browser, tenant, emailAddress) => {
    const state = tenancyState(page);
    const tenantContext = await createTenantContext(browser, { tenant });
    const tenantPage = await tenantContext.context.newPage();

    state.contexts.push(tenantContext.context);
    state.currentPage = tenantPage;
    state.currentTenant = tenant;
    state.lastResponse = null;

    await loginAs(tenantPage, emailAddress);
};

Given('I am logged into tenant {string} as {string}', async ({ page, browser }, tenant, emailAddress) => {
    await openTenantSession(page, browser, tenant, emailAddress);
});

When('I switch to tenant {string} as {string}', async ({ page, browser }, tenant, emailAddress) => {
    await openTenantSession(page, browser, tenant, emailAddress);
});

When('I open member id {string} in the active tenant', async ({ page }, memberId) => {
    const tenantPage = activeTenantPage(page);
    const state = tenancyState(page);

    state.lastResponse = await tenantPage.goto(`/members/view/${encodeURIComponent(memberId)}`, {
        waitUntil: 'domcontentloaded',
    });
    await waitForPageBody(tenantPage);
});

When('I open branch public id {string} in the active tenant', async ({ page }, branchPublicId) => {
    const tenantPage = activeTenantPage(page);
    const state = tenancyState(page);

    state.lastResponse = await tenantPage.goto(`/branches/view/${encodeURIComponent(branchPublicId)}`, {
        waitUntil: 'domcontentloaded',
    });
    await waitForPageBody(tenantPage);
});

Then('the active tenant page should contain {string}', async ({ page }, expectedText) => {
    await expect(activeTenantPage(page).locator('body')).toContainText(expectedText, { timeout: 15000 });
});

Then('the active tenant page should not contain {string}', async ({ page }, unexpectedText) => {
    await expect(activeTenantPage(page).locator('body')).not.toContainText(unexpectedText);
});

Then('the active tenant response status should be {int}', async ({ page }, expectedStatus) => {
    const { lastResponse } = tenancyState(page);

    expect(lastResponse, 'Expected the active tenant navigation to produce an HTTP response.').not.toBeNull();
    expect(lastResponse.status()).toBe(expectedStatus);
});

After(async ({ page }) => {
    const state = page[TENANCY_STATE_KEY];
    if (!state) {
        return;
    }

    await Promise.all(state.contexts.map(async (context) => {
        await context.close();
    }));

    page[TENANCY_STATE_KEY] = null;
});
