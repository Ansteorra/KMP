const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const { runSecurityBoundaries, runMailSecurity } = require('../../support/security-boundaries-browser-check.cjs');
const { When, Then } = createBdd();

When('I exercise the approval and logout security browser journeys', async ({ browser, page }) => {
    page.securityBoundaryResult = await runSecurityBoundaries(browser);
});

Then('the security browser journeys should pass', async ({ page, $testInfo }) => {
    expect(page.securityBoundaryResult).toMatch(/^PASS:/);
    await $testInfo.attach('security-boundaries', { body: page.securityBoundaryResult, contentType: 'text/plain' });
});

When('I exercise the tenant mail security browser journey', async ({ browser, page }) => {
    page.mailSecurityResult = await runMailSecurity(browser);
});

Then('the tenant mail security browser journey should pass', async ({ page, $testInfo }) => {
    expect(page.mailSecurityResult).toMatch(/^PASS:/);
    await $testInfo.attach('mail-security', { body: page.mailSecurityResult, contentType: 'text/plain' });
});
