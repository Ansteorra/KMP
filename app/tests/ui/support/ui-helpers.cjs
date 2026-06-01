const { expect } = require('@playwright/test');

const { getMailpitApiUrl, getUiTestEnvironment } = require('./test-environment.cjs');

const DEFAULT_TIMEOUT = 15000;

const wait = (delayMs) => new Promise((resolve) => {
    setTimeout(resolve, delayMs);
});

const waitForPageBody = async (page, timeout = DEFAULT_TIMEOUT) => {
    await expect(page.locator('body')).toBeVisible({ timeout });
};

const waitForSuccessfulLogin = async (page, timeout = DEFAULT_TIMEOUT) => {
    await page.waitForURL((url) => !url.pathname.includes('/members/login'), { timeout });
    await waitForPageBody(page, timeout);
};

const loginAs = async (page, emailAddress, password = 'TestPassword') => {
    await page.goto('/members/login', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
    await page.locator('#email-address').fill(emailAddress);
    await page.locator('#password').fill(password);

    await Promise.all([
        waitForSuccessfulLogin(page, 30000),
        page.locator('input[type="submit"][value="Sign in"]').click(),
    ]);
};

const clearMailpitMessages = async (requestContext) => {
    const response = await requestContext.delete(getMailpitApiUrl('api/v1/messages'));
    if (!response.ok() && response.status() !== 404) {
        throw new Error(`Mailpit cleanup failed with status ${response.status()}.`);
    }
};

const waitForAppReady = async (requestContext, timeout = 60000) => {
    const { baseUrl, hostHeader } = getUiTestEnvironment();
    const startedAt = Date.now();
    let lastErrorMessage = 'no response received';
    const requestOptions = {
        failOnStatusCode: false,
        timeout: 5000,
        headers: hostHeader ? { Host: hostHeader } : undefined,
    };

    while (Date.now() - startedAt < timeout) {
        try {
            const response = await requestContext.get(baseUrl, requestOptions);

            if (response.ok() || [301, 302, 303, 307, 308, 401, 403].includes(response.status())) {
                return;
            }

            lastErrorMessage = `HTTP ${response.status()}`;
        } catch (error) {
            lastErrorMessage = error.message;
        }

        await wait(1000);
    }

    throw new Error(`Timed out waiting for ${baseUrl}: ${lastErrorMessage}`);
};

const runAndWaitForNetworkIdle = async (page, action, timeout = DEFAULT_TIMEOUT) => {
    await Promise.all([
        page.waitForLoadState('networkidle', { timeout }),
        Promise.resolve().then(action),
    ]);
    await waitForPageBody(page, timeout);
};

const waitForGridRows = async (
    scope,
    selector = 'table.table tbody tr:visible, .dataTable tbody tr:visible, table tbody tr:visible',
    timeout = 30000,
) => {
    const rows = scope.locator(selector);
    await expect(rows.first()).toBeVisible({ timeout });
    return rows;
};

const clickTabAndWait = async (tab, panel = null, timeout = DEFAULT_TIMEOUT) => {
    await tab.click();
    await expect(tab).toHaveClass(/active/, { timeout });
    if (panel) {
        await expect(panel).toBeVisible({ timeout });
    }
};

const getSignOutButton = (page) => page.locator('a.btn-outline-secondary').filter({ hasText: /Sign out/i }).first();

const isLocatorVisible = async (locator) => {
    try {
        return await locator.isVisible();
    } catch {
        return false;
    }
};

module.exports = {
    clearMailpitMessages,
    clickTabAndWait,
    getSignOutButton,
    isLocatorVisible,
    loginAs,
    runAndWaitForNetworkIdle,
    waitForAppReady,
    waitForGridRows,
    waitForPageBody,
    waitForSuccessfulLogin,
};
