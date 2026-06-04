const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const {
    clickTabAndWait,
    runAndWaitForNetworkIdle,
    runPhpJson,
    waitForGridRows,
    waitForPageBody,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then } = createBdd();

const GRID_ROWS_SELECTOR = 'table.table tbody tr:visible, .dataTable tbody tr:visible';

const selectAutocompleteOption = async (page, comboBox, optionText) => {
    const input = comboBox.locator('[data-ac-target="input"]');
    await expect(input).toBeVisible({ timeout: 15000 });
    await Promise.all([
        page.waitForResponse((response) => response.url().includes('/members/auto-complete')
            && response.status() === 200, { timeout: 15000 }).catch(() => null),
        input.fill(optionText),
    ]);

    await expect.poll(async () => comboBox.evaluate((element, label) => {
        const controller = window.Stimulus?.getControllerForElementAndIdentifier(element, 'ac');
        const options = Array.isArray(controller?.options)
            ? controller.options
            : JSON.parse(element.querySelector('[data-ac-target="dataList"]')?.textContent ?? '[]');

        return options.some((option) => option.text && option.text.includes(label) && option.enabled !== false);
    }, optionText), {
        timeout: 15000,
    }).toBe(true);

    await comboBox.evaluate((element, label) => {
        const controller = window.Stimulus?.getControllerForElementAndIdentifier(element, 'ac');
        const options = Array.isArray(controller?.options)
            ? controller.options
            : JSON.parse(element.querySelector('[data-ac-target="dataList"]')?.textContent ?? '[]');
        const match = options.find((option) => option.text && option.text.includes(label) && option.enabled !== false);

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

// The member autocomplete (`/members/auto-complete`) is server-backed: the `ac`
// controller renders the result <li role="option"> elements directly rather than
// populating `controller.options` (which only mirrors a static dataList). Drive it
// the way a user would — type, wait for the rendered option, then click it.
const selectMemberAutocomplete = async (page, comboBox, optionText) => {
    const input = comboBox.locator('[data-ac-target="input"]');
    await expect(input).toBeVisible({ timeout: 15000 });
    await input.click();
    await input.fill('');
    await Promise.all([
        page.waitForResponse((response) => response.url().includes('/members/auto-complete')
            && response.status() === 200, { timeout: 15000 }).catch(() => null),
        input.type(optionText, { delay: 20 }),
    ]);

    const option = comboBox
        .locator('[data-ac-target="results"] li[role="option"]', { hasText: optionText })
        .first();
    await expect(option).toBeVisible({ timeout: 15000 });
    await option.click();

    await expect(comboBox.locator('[data-ac-target="hidden"]'))
        .not.toHaveValue('', { timeout: 8000 });
};

let lifecycle = {};

const FIXTURE_DISCOVERY_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$locator = \Cake\ORM\TableRegistry::getTableLocator();
$branches = $locator->get('Branches');
$members = $locator->get('Members');

$manager = $members->find()
    ->select(['id', 'public_id', 'sca_name', 'email_address', 'branch_id'])
    ->where(['email_address' => 'bryce@ampdemo.com'])
    ->firstOrFail();

$branch = $branches->find()
    ->select(['id', 'name', 'public_id'])
    ->where(['id' => $manager->branch_id])
    ->firstOrFail();

$gatheringType = $locator->get('GatheringTypes')->find()
    ->select(['id', 'name'])
    ->orderBy(['name' => 'ASC'])
    ->firstOrFail();

echo json_encode([
    'branchId' => (int)$branch->id,
    'branchPublicId' => (string)$branch->public_id,
    'branchName' => (string)$branch->name,
    'gatheringTypeName' => (string)$gatheringType->name,
    'managerId' => (int)$manager->id,
    'managerPublicId' => (string)$manager->public_id,
    'managerName' => (string)$manager->sca_name,
    'managerEmail' => (string)$manager->email_address,
], JSON_THROW_ON_ERROR);
`;

const ensureLifecycle = () => {
    if (!lifecycle.gatheringName) {
        throw new Error('Gatherings lifecycle fixture has not been prepared.');
    }

    return lifecycle;
};

const futureLocalDateTime = (hoursFromNow) => {
    const date = new Date(Date.now() + hoursFromNow * 60 * 60 * 1000);
    const pad = (value) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const selectComboBoxOption = async (page, inputSelector, optionText) => {
    const comboBox = page.locator('[data-controller="ac"]').filter({ has: page.locator(inputSelector) }).first();
    await expect(comboBox).toBeVisible({ timeout: 15000 });

    await expect.poll(async () => comboBox.evaluate((element, label) => {
        const data = JSON.parse(element.querySelector('[data-ac-target="dataList"]')?.textContent ?? '[]');
        return data.some((option) => option.text === label && option.enabled !== false);
    }, optionText), { timeout: 15000 }).toBe(true);

    await comboBox.evaluate((element, label) => {
        const controller = window.Stimulus?.getControllerForElementAndIdentifier(element, 'ac');
        const data = JSON.parse(element.querySelector('[data-ac-target="dataList"]')?.textContent ?? '[]');
        const match = data.find((option) => option.text === label && option.enabled !== false);

        if (!match) {
            throw new Error(`Unable to find combo-box option "${label}".`);
        }

        if (controller) {
            const selected = document.createElement('li');
            selected.setAttribute('data-ac-value', String(match.value));
            selected.textContent = match.text;
            controller.commit(selected);
        } else {
            element.querySelector('[data-ac-target="input"]').value = match.text;
            element.querySelector('[data-ac-target="hidden"]').value = match.value;
            element.querySelector('[data-ac-target="hiddenText"]').value = match.text;
        }

        element.dispatchEvent(new CustomEvent('autocomplete.change', {
            bubbles: true,
            detail: { value: match.value, text: match.text },
        }));
    }, optionText);
};

const selectBranch = async (page, branchName) => {
    const branchSelect = page.locator('select[name="branch_id"]');
    if (await branchSelect.count() > 0 && await branchSelect.first().isVisible() && await branchSelect.first().isEnabled()) {
        await branchSelect.first().selectOption({ label: branchName });
        return;
    }

    // With multiple branches seeded the template renders KMP comboBoxControl
    // (an `ac` Stimulus autocomplete backed by a hidden branch_id input).
    const branchCombo = page.locator('[data-controller="ac"]').filter({
        has: page.locator('input[name="branch_name"]'),
    }).first();
    if (await branchCombo.count() > 0) {
        await selectAutocompleteOption(page, branchCombo, branchName);
        return;
    }

    const legacyCombo = page.locator('#branch-name-disp');
    if (await legacyCombo.count() > 0 && await legacyCombo.first().isVisible()) {
        await selectComboBoxOption(page, '#branch-name-disp', branchName);
    }
};

const openGridFilter = async (page) => {
    await waitForGridRows(page, GRID_ROWS_SELECTOR);
    const filterBtn = page.locator('#filterDropdown, button:has-text("Filter")').first();
    await filterBtn.click();
    const searchInput = page.locator('[data-grid-view-target="searchInput"]');
    await expect(searchInput).toBeVisible({ timeout: 15000 });

    return { filterBtn, searchInput };
};

const searchCurrentGrid = async (page, text) => {
    const { filterBtn, searchInput } = await openGridFilter(page);
    const responsePath = new URL(page.url()).pathname;

    await searchInput.fill(text);
    await Promise.all([
        page.waitForResponse((response) => {
            const responseUrl = new URL(response.url());
            return response.status() === 200
                && responseUrl.pathname === responsePath
                && responseUrl.searchParams.has('search');
        }, { timeout: 30000 }).catch(() => null),
        searchInput.press('Enter'),
    ]);

    await waitForGridRows(page, GRID_ROWS_SELECTOR);
    await page.keyboard.press('Escape');
    await expect(filterBtn).toHaveAttribute('aria-expanded', 'false');
};

const assertGridContains = async (page, text) => {
    await waitForGridRows(page, GRID_ROWS_SELECTOR);
    const grid = page.locator('table.table, .dataTable, [data-controller*="grid"]').first();
    await expect(grid).toContainText(text, { timeout: 15000 });
};

const assertGatheringIsVisibleToCurrentUser = async (page) => {
    const data = ensureLifecycle();
    await page.goto(data.gatheringUrl, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
    await expect(page.getByRole('heading', { name: data.gatheringName })).toBeVisible({ timeout: 15000 });
};

const openGatheringView = async (page) => {
    const data = ensureLifecycle();
    await page.goto(data.gatheringUrl, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
    await expect(page.getByRole('heading', { name: data.gatheringName })).toBeVisible({ timeout: 15000 });
};

Given('I prepare the gatherings lifecycle fixture', async () => {
    const token = `gath${Date.now()}`;
    const discovered = runPhpJson(FIXTURE_DISCOVERY_PHP);

    lifecycle = {
        ...discovered,
        token,
        gatheringName: `BDD Lifecycle Gathering ${token}`,
        gatheringDescription: `Playwright-BDD gathering lifecycle fixture ${token}`,
        gatheringLocation: `BDD Hall ${token}`,
        staffRole: `BDD Steward ${token}`,
        staffEmail: `staff-${token}@example.com`,
        attendanceNote: `BDD attendance note ${token}`,
        startDate: futureLocalDateTime(2),
        endDate: futureLocalDateTime(3),
    };
});

When('I create the gatherings lifecycle type', async ({ page }) => {
    const data = ensureLifecycle();
    await page.goto('/gathering-types/add', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);

    await page.getByLabel('Name').fill(data.typeName);
    await page.getByLabel('Description').fill(data.typeDescription);
    await page.getByLabel('Calendar Color').fill('#198754');
    await runAndWaitForNetworkIdle(page, () => page.getByRole('button', { name: 'Submit', exact: true }).click());
});

Then('the gatherings lifecycle type should appear in the gathering types grid', async ({ page }) => {
    const data = ensureLifecycle();
    await expect(page.getByRole('alert').first()).toContainText(`The gathering type "${data.typeName}" has been created successfully.`, { timeout: 15000 });

    await page.goto('/gathering-types', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
    await searchCurrentGrid(page, data.typeName);
    await assertGridContains(page, data.typeName);
});

When('I create the gatherings lifecycle gathering', async ({ page }) => {
    const data = ensureLifecycle();
    await page.goto('/gatherings/add', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);

    await page.getByLabel('Name').fill(data.gatheringName);
    await selectBranch(page, data.branchName);
    await page.locator('select[name="gathering_type_id"]').selectOption({ label: data.gatheringTypeName });
    await page.getByLabel('Start Date & Time').fill(data.startDate);
    await page.getByLabel('End Date & Time').fill(data.endDate);
    await page.getByLabel('Location').fill(data.gatheringLocation);
    await page.getByLabel('Event Timezone').selectOption('America/Chicago');
    // Description renders through the EasyMDE markdown-editor Stimulus controller,
    // which hides the underlying <textarea> behind a CodeMirror surface. Type into
    // the editor surface (forceSync keeps the textarea in sync for submission).
    const descriptionEditor = page.locator('.EasyMDEContainer .CodeMirror').first();
    await descriptionEditor.click();
    await page.keyboard.type(data.gatheringDescription);
    await runAndWaitForNetworkIdle(page, () => page.getByRole('button', { name: 'Create Gathering', exact: true }).click());

    data.gatheringUrl = new URL(page.url()).pathname;
});

Then('the gatherings lifecycle gathering should appear in the gatherings grid', async ({ page }) => {
    const data = ensureLifecycle();
    await expect(page.getByRole('alert').first()).toContainText(`The gathering "${data.gatheringName}" has been created successfully.`, { timeout: 15000 });
    await expect(page.getByRole('heading', { name: data.gatheringName })).toBeVisible({ timeout: 15000 });

    await assertGatheringIsVisibleToCurrentUser(page);
});

When('I add the gatherings lifecycle staff member', async ({ page }) => {
    const data = ensureLifecycle();
    await openGatheringView(page);

    const staffTab = page.getByRole('tab', { name: /Staff/i });
    await clickTabAndWait(staffTab, page.locator('#nav-staff'));
    await page.getByRole('button', { name: /Add Staff Member/i }).click();

    const modal = page.locator('#addStaffModal');
    await expect(modal).toBeVisible({ timeout: 15000 });

    const comboBox = modal.locator('[data-controller="ac"]').first();
    await selectMemberAutocomplete(page, comboBox, data.managerName);

    await modal.getByLabel('Role').fill(data.staffRole);
    // Add as a regular (non-steward) staff member so the staff table renders the
    // Role column we assert on. Stewards display in a separate section without the
    // role text. Contact info is optional for non-stewards but harmless to set.
    await modal.locator('#add-email').fill(data.staffEmail);

    await runAndWaitForNetworkIdle(page, () => modal.getByRole('button', { name: /Add Staff Member/i }).click());
});

Then('the gatherings lifecycle staff member should appear on the gathering staff tab', async ({ page }) => {
    const data = ensureLifecycle();
    await expect(page.getByRole('alert').first()).toContainText('The staff member has been added.', { timeout: 15000 });

    const staffPanel = page.locator('#nav-staff');
    await expect(staffPanel).toContainText(data.managerName, { timeout: 15000 });
    await expect(staffPanel).toContainText(data.staffRole, { timeout: 15000 });
});

When('I record attendance for the gatherings lifecycle gathering', async ({ page }) => {
    const data = ensureLifecycle();
    await openGatheringView(page);

    await page.getByRole('button', { name: /Attend This Gathering/i }).click();
    const modal = page.locator('#attendGatheringModal');
    await expect(modal).toBeVisible({ timeout: 15000 });
    await modal.locator('#public-note').fill(data.attendanceNote);
    await modal.getByLabel('Share with Hosting Group').check();
    await runAndWaitForNetworkIdle(page, () => modal.getByRole('button', { name: 'Register', exact: true }).click());
});

Then('the gatherings lifecycle attendance should appear on the gathering attendance tab', async ({ page }) => {
    const data = ensureLifecycle();
    await expect(page.getByRole('alert').first()).toContainText('Your attendance has been registered.', { timeout: 15000 });

    const attendanceTab = page.getByRole('tab', { name: /Attendance/i });
    await clickTabAndWait(attendanceTab, page.locator('#nav-attendance'));
    const attendancePanel = page.locator('#nav-attendance');
    await expect(attendancePanel).toContainText('Total Announced Attendance:', { timeout: 15000 });
    await expect(attendancePanel).toContainText(data.managerName, { timeout: 15000 });
    await expect(attendancePanel).toContainText(data.attendanceNote, { timeout: 15000 });
});

Then('the gatherings lifecycle attendance should appear in the member gatherings listing', async ({ page }) => {
    const data = ensureLifecycle();
    await page.goto('/members/profile', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);

    const gatheringsTab = page.getByRole('tab', { name: /Gatherings/i });
    await clickTabAndWait(gatheringsTab, page.locator('#nav-gatherings'));

    // The member profile gatherings panel is a custom Upcoming/Past sub-tab table,
    // not a dataverse grid. The future-dated gathering renders in the active "Upcoming" pane.
    // Scope to #nav-gatherings to avoid colliding with other profile tabs that reuse #upcoming.
    const gatheringsPanel = page.locator('#nav-gatherings');
    await expect(gatheringsPanel).toContainText(data.gatheringName, { timeout: 15000 });
    await expect(gatheringsPanel).toContainText(data.attendanceNote, { timeout: 15000 });
});
