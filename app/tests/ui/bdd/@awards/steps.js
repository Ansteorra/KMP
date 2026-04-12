const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');

const { Given, When, Then } = createBdd();

async function commitComboboxByTyping(page, inputSelector, hiddenSelector, text) {
    const input = page.locator(inputSelector);
    await input.fill(text);
    await input.press('Tab');
    await expect(page.locator(hiddenSelector)).toHaveValue(/.+/);
}

When('I enter {string} as an unmatched recommendation recipient', async ({ page }, recipient) => {
    const input = page.locator('#member-sca-name-disp');
    await input.fill(recipient);
    await page.waitForResponse(resp =>
        resp.url().includes('/members/auto-complete')
        && resp.status() === 200
    );
    await input.press('Tab');
});

Then('the submit recommendation form should mark the recipient as not registered', async ({ page }) => {
    await expect(page.locator('#not-found')).toBeChecked();
});

Then('the submit recommendation form should enable the local group field', async ({ page }) => {
    await expect(page.locator('[data-awards-rec-add-target="branch"]')).toHaveJSProperty('hidden', false);
    await expect(page.locator('#branch_name-disp')).toBeEnabled();
});

When('I submit a public recommendation for the unmatched recipient {string}', async ({ page }, recipient) => {
    await page.locator('#recommendation__requester_sca_name').fill('External Recommender');
    await page.locator('#contact-email').fill('external@example.com');

    const recipientInput = page.locator('#member-sca-name-disp');
    await recipientInput.fill(recipient);
    await page.waitForResponse(resp =>
        resp.url().includes('/members/auto-complete')
        && resp.status() === 200
    );
    await recipientInput.press('Tab');
    await expect(page.locator('[data-awards-rec-add-target="branch"]')).toHaveJSProperty('hidden', false);

    await commitComboboxByTyping(page, '#branch_name-disp', '[name="branch_id"]', 'Out of Kingdom');
    const awardResponse = page.waitForResponse(resp =>
        resp.url().includes('/awards/awards/awards-by-domain/')
        && resp.status() === 200
    );
    await commitComboboxByTyping(page, '#domain_name-disp', '[name="domain_id"]', 'General');
    await awardResponse;
    await expect(page.locator('#award_name-disp')).toBeEnabled();

    const firstAward = page.locator('#award_descriptions button[data-award-id]').first();
    const firstAwardText = (await firstAward.textContent()).trim();
    await commitComboboxByTyping(page, '#award_name-disp', '[name="award_id"]', firstAwardText);

    await page.locator('#recommendation_reason').fill('Public submission regression coverage for a non-member recipient.');
    await page.getByRole('button', { name: /submit/i }).click();
    await page.waitForLoadState('networkidle');
});

Then('the recommendation row for {string} should not link to a member profile', async ({ page }, recipient) => {
    const row = page.locator('table tbody tr', { hasText: recipient }).first();
    await expect(row).toContainText(recipient);
    await expect(row.locator('a').filter({ hasText: recipient })).toHaveCount(0);
});
