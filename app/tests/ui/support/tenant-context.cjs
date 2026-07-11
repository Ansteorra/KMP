const { getTenantContextOptions } = require('./test-environment.cjs');

/**
 * Open a fresh Playwright browser context bound to a specific seeded tenant.
 *
 * Two tenants are seeded (`kmp.localhost`, `kmp2.localhost`). The active tenant is
 * resolved by the HTTP Host header, so on loopback base URLs we inject a per-tenant
 * `Host` header; otherwise we swap the hostname in the base URL.
 *
 * @param {import('@playwright/test').Browser} browser
 * @param {{ tenant?: string }} [options]
 * @returns {Promise<{ context: import('@playwright/test').BrowserContext, baseURL: string, hostHeader: (string|null) }>}
 */
const createTenantContext = async (browser, { tenant = 'kmp' } = {}) => {
    const { baseURL, hostHeader } = getTenantContextOptions(tenant);

    const context = await browser.newContext({
        baseURL,
        ignoreHTTPSErrors: true,
        extraHTTPHeaders: {
            'X-KMP-E2E': '1',
            ...(hostHeader ? { Host: hostHeader } : {}),
        },
    });

    return { context, baseURL, hostHeader };
};

module.exports = {
    createTenantContext,
};
