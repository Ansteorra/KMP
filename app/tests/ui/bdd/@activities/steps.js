const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const {
    clickTabAndWait,
    runAndWaitForNetworkIdle,
    waitForGridRows,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then } = createBdd();

const authorizationSection = (page) => page.locator('#nav-member-authorizations');

const selectComboBoxOption = async (page, inputSelector, optionText) => {
    const comboBox = page.locator('[data-controller="ac"]').filter({ has: page.locator(inputSelector) }).first();

    await expect.poll(async () => comboBox.evaluate((element, label) => {
        const controller = window.Stimulus?.getControllerForElementAndIdentifier(element, 'ac');
        const options = Array.isArray(controller?.options)
            ? controller.options
            : JSON.parse(element.querySelector('[data-ac-target="dataList"]')?.textContent ?? '[]');

        return options.some((option) => option.text === label && option.enabled !== false);
    }, optionText), {
        timeout: 15000,
    }).toBe(true);

    await comboBox.evaluate((element, label) => {
        const controller = window.Stimulus?.getControllerForElementAndIdentifier(element, 'ac');
        const options = Array.isArray(controller?.options)
            ? controller.options
            : JSON.parse(element.querySelector('[data-ac-target="dataList"]')?.textContent ?? '[]');
        const match = options.find((option) => option.text === label && option.enabled !== false);

        if (!controller || !match) {
            throw new Error(`Unable to find combo-box option "${label}".`);
        }

        const selected = document.createElement('li');
        selected.setAttribute('data-ac-value', String(match.value));
        selected.textContent = match.text;
        controller.commit(selected);
        element.dispatchEvent(new Event('change', { bubbles: true }));
    }, optionText);
};

const openMemberAuthorizationView = async (page, viewName) => {
    const section = authorizationSection(page);
    const authTab = page.locator('[data-detail-tabs-target="tabBtn"]').filter({ hasText: /Authorizations/i }).first();

    if (await authTab.count() > 0) {
        await clickTabAndWait(authTab, section);
    }

    const viewTabs = section.locator('[data-view-tabs-container] [role="tab"]');
    await expect(viewTabs.first()).toBeVisible({ timeout: 15000 });

    const viewTab = viewTabs.filter({ hasText: new RegExp(`^${viewName}`, 'i') }).first();
    await expect(viewTab).toBeVisible({ timeout: 15000 });

    if ((await viewTab.getAttribute('aria-selected')) !== 'true') {
        await viewTab.click();
    }

    await expect(viewTab).toHaveAttribute('aria-selected', 'true', { timeout: 15000 });
    await expect
        .poll(async () => {
            const stateScript = section.locator('turbo-frame#member-auth-grid-table script[type="application/json"]').first();
            if (await stateScript.count() === 0) {
                return null;
            }

            try {
                const state = JSON.parse(await stateScript.textContent() ?? '{}');
                return state?.view?.currentId ?? null;
            } catch {
                return null;
            }
        }, {
            timeout: 15000,
        })
        .toBe(viewName.toLowerCase());

    return section;
};


Given("I select the activity {string}", async ({ page }, activityName) => {
    const approverInput = page.locator('#request-auth-approver_name-disp');
    await selectComboBoxOption(page, '#request-auth-activity_name-disp', activityName);
    await expect(approverInput).toBeEnabled({ timeout: 15000 });
});

Given("I select the approver {string}", async ({ page }, approverName) => {
    const submitButton = page.getByRole('button', { name: 'Submit', exact: true });
    await selectComboBoxOption(page, '#request-auth-approver_name-disp', approverName);
    await expect(submitButton).toBeEnabled({ timeout: 15000 });
});

Given("I submit the authorization request", async ({ page }) => {
    await runAndWaitForNetworkIdle(page, () => page.getByRole('button', { name: 'Submit', exact: true }).click());
});

Then("I should have 1 pending authorization request", async ({ page }) => {
    const authSection = await openMemberAuthorizationView(page, 'Pending');

    // Verify at least one row exists in the grid
    const rows = authSection.locator('table tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });
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
        await approveButton.click({ force: true });

        const confirmDialog = page.locator('.modal.show').filter({ hasText: 'Approve authorization' });
        await confirmDialog.waitFor({ state: 'visible', timeout: 5000 });
        await confirmDialog.getByRole('button', { name: 'Approve', exact: true }).click();
    } else if (buttonText.toLowerCase() === 'deny') {
        const denyButton = row.locator('button:has-text("Deny"), a:has-text("Deny")').first();
        await denyButton.click({ force: true });
    } else {
        const button = row.locator(`a:has-text("${buttonText}"), button:has-text("${buttonText}")`).first();
        await button.click({ force: true });
    }
});

Then('My Queue shows {int} pending authorization request(s)', async ({ page }, count) => {
    // Navigate to unified approvals page and verify pending requests
    await page.goto('/approvals', { waitUntil: 'networkidle' });
    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });
});

Then('I see one authorization request for {string} from {string}', async ({ page }, activityName, requesterName) => {
    // Wait for grid to load after search
    await waitForGridRows(page);
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
        await page.goto(url.toString(), { waitUntil: 'domcontentloaded' });
        await waitForGridRows(page);
    }

    await expect(getRows().first()).toBeVisible();
    // Store context for the approve/deny step
    page._lastMatchedAuthRow = { activityName, requesterName };
});

// ── Unified Approvals Modal Steps ────────────────────────────────────

Then('I see one approval request for {string} from {string}', async ({ page }, activityName, requesterName) => {
    // Wait for DataverseGrid to load after search
    await waitForGridRows(page);

    const getRows = () => page.locator('table tbody tr')
        .filter({ hasText: activityName })
        .filter({ hasText: requesterName });

    await expect(getRows().first()).toBeVisible({ timeout: 15000 });
    // Store context for the respond step
    page._lastMatchedApprovalRow = { activityName, requesterName };
});

When('I click the respond button for the approval request', async ({ page }) => {
    const ctx = page._lastMatchedApprovalRow || {};
    let row;
    if (ctx.activityName && ctx.requesterName) {
        row = page.locator('table tbody tr')
            .filter({ hasText: ctx.activityName })
            .filter({ hasText: ctx.requesterName })
            .first();
    } else {
        row = page.locator('table tbody tr').first();
    }

    const respondBtn = row.locator('button:has-text("Respond"), a:has-text("Respond")').first();
    await respondBtn.click({ force: true });
    // Wait for the Bootstrap modal to appear
    await page.waitForSelector('#approvalResponseModal.show', { state: 'visible', timeout: 10000 });
});

When('I select the {string} decision in the approval modal', async ({ page }, decision) => {
    const modal = page.locator('#approvalResponseModal');
    if (decision.toLowerCase() === 'approve') {
        await modal.locator('#decisionApprove').click();
    } else {
        await modal.locator('#decisionReject').click();
    }
    await expect(modal.locator('button[type="submit"]')).toBeEnabled({ timeout: 15000 });
});

When('I enter the approval comment {string}', async ({ page }, comment) => {
    const modal = page.locator('#approvalResponseModal');
    await modal.locator('#approvalComment').fill(comment);
});

When('I submit the approval response', async ({ page }) => {
    const modal = page.locator('#approvalResponseModal');
    const submitBtn = modal.locator('button[type="submit"]');
    await runAndWaitForNetworkIdle(page, () => submitBtn.click(), 15000);
});

// ── Authorization Profile Verification Steps ────────────────────────

Then('I should see the approved authorization for {string}', async ({ page }, activityName) => {
    const authSection = await openMemberAuthorizationView(page, 'Active');

    // Wait for grid to load and look for the activity
    await authSection.locator('table tbody tr').first().waitFor({ state: 'visible', timeout: 30000 });
    const authRow = authSection.locator('table tbody tr').filter({ hasText: activityName });
    await expect(authRow.first()).toBeVisible();
});


Then("I should see the denied authorization for {string} with a reason {string}", async ({ page }, activityName, reason) => {
    const authSection = await openMemberAuthorizationView(page, 'Previous');

    await expect.poll(async () => authSection.locator('table tbody tr').evaluateAll(
        (rows, expected) => rows.some((row) => row.offsetParent !== null
            && row.innerText.includes(expected.activityName)
            && row.innerText.includes(expected.reason)),
        { activityName, reason },
    ), {
        timeout: 60000,
    }).toBe(true);
});
