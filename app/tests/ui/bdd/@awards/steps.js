const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');

const { Given, When, Then, After } = createBdd();

const APP_ROOT = path.resolve(__dirname, '../../../..');
const FIXTURE_MEMBER_NAME = 'Iris Basic User Demoer';
const FIXTURE_REQUESTER_EMAIL = 'admin@amp.ansteorra.org';

const FIXTURE_SETS = {
    'detail edit': [
        { name: 'detail', awardName: 'Award of Arms' },
    ],
    'quick edit': [
        { name: 'quick', awardName: 'Award of Amicitia of Ansteorra' },
    ],
    'bulk edit': [
        { name: 'bulk-one', awardName: 'Award of Arms' },
        { name: 'bulk-two', awardName: 'Award of the Compass Rose of Ansteorra' },
    ],
    grouping: [
        { name: 'group-head', awardName: 'Award of Arms' },
        { name: 'group-one', awardName: 'Award of Amicitia of Ansteorra' },
        { name: 'group-two', awardName: 'Award of the Compass Rose of Ansteorra' },
        { name: 'group-three', awardName: 'Award of the Golden Bridge of Ansteorra' },
    ],
};

const CREATE_FIXTURES_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode((string)getenv('FIXTURE_JSON'), true, 512, JSON_THROW_ON_ERROR);
$locator = \\Cake\\ORM\\TableRegistry::getTableLocator();
$recommendations = $locator->get('Awards.Recommendations');
$members = $locator->get('Members');
$awards = $locator->get('Awards.Awards');

$requester = $members->find()
    ->select(['id', 'sca_name', 'email_address', 'phone_number'])
    ->where(['email_address' => $input['requesterEmail']])
    ->firstOrFail();
$member = $members->find()
    ->select(['id', 'public_id', 'sca_name'])
    ->where(['sca_name' => $input['memberName']])
    ->firstOrFail();

$awardRows = $awards->find()
    ->select(['id', 'name'])
    ->where(['name IN' => array_values(array_unique(array_column($input['fixtures'], 'awardName')))])
    ->all()
    ->indexBy('name')
    ->toArray();

$service = new \\Awards\\Services\\RecommendationSubmissionService();
$created = [];

foreach ($input['fixtures'] as $fixture) {
    $award = $awardRows[$fixture['awardName']] ?? null;
    if ($award === null) {
        throw new \\RuntimeException('Award not found: ' . $fixture['awardName']);
    }

    $result = $service->submitAuthenticated(
        $recommendations,
        [
            'award_id' => (int)$award->id,
            'member_sca_name' => (string)$member->sca_name,
            'member_public_id' => (string)$member->public_id,
            'reason' => (string)$fixture['reason'],
            'specialty' => 'No specialties available',
        ],
        [
            'id' => (int)$requester->id,
            'sca_name' => (string)$requester->sca_name,
            'email_address' => (string)$requester->email_address,
            'phone_number' => (string)($requester->phone_number ?? ''),
        ],
    );

    if (!($result['success'] ?? false)) {
        throw new \\RuntimeException('Fixture creation failed: ' . json_encode($result, JSON_THROW_ON_ERROR));
    }

    $recommendation = $result['recommendation'];
    $created[$fixture['name']] = [
        'id' => (int)$recommendation->id,
        'awardName' => (string)$award->name,
        'memberScaName' => (string)$recommendation->member_sca_name,
        'reason' => (string)$recommendation->reason,
    ];
}

echo json_encode(['fixtures' => $created], JSON_THROW_ON_ERROR);
`;

const CLEANUP_FIXTURES_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode((string)getenv('FIXTURE_JSON'), true, 512, JSON_THROW_ON_ERROR);
$recommendations = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations');
$ids = array_values(array_unique(array_map('intval', $input['ids'] ?? [])));
rsort($ids);

foreach ($ids as $id) {
    if (!$recommendations->exists(['id' => $id])) {
        continue;
    }

    $recommendation = $recommendations->get($id);
    $recommendations->delete($recommendation);
}

echo json_encode(['deleted' => $ids], JSON_THROW_ON_ERROR);
`;

const normalizeText = (value) => value.replace(/\s+/g, ' ').trim();

const runPhpJson = (script, payload) => {
    const output = execFileSync(
        'php',
        ['-d', 'xdebug.mode=off', '-r', script],
        {
            cwd: APP_ROOT,
            env: {
                ...process.env,
                FIXTURE_JSON: JSON.stringify(payload),
            },
            encoding: 'utf8',
        },
    ).trim();

    return output === '' ? {} : JSON.parse(output);
};

const ensureFixtureSet = (page) => {
    const fixtures = page.__awardRecommendationFixtures;
    if (!fixtures) {
        throw new Error('Award recommendation fixtures have not been created for this scenario.');
    }

    return fixtures;
};

const getFixture = (page, name) => {
    const fixtures = ensureFixtureSet(page);
    const fixture = fixtures.fixtureMap[name];
    if (!fixture) {
        throw new Error(`Unknown recommendation fixture "${name}".`);
    }

    return fixture;
};

const getOpenRecommendationEditModal = async (page) => {
    const selectors = ['#editModal', '#editRecommendationModal'];

    for (const selector of selectors) {
        const modal = page.locator(selector);
        if (await modal.count() > 0 && await modal.first().isVisible()) {
            return modal.first();
        }
    }

    throw new Error('No recommendation edit modal is currently open.');
};

const getRecommendationFieldLocator = (modal, fieldLabel) => {
    switch (fieldLabel) {
        case 'Plan to Give At':
            return modal.locator('input[name="gathering_name-Disp"]');
        case 'Given On':
            return modal.locator('input[name="given"]');
        case 'Reason for No Action':
            return modal.locator('input[name="close_reason"]');
        default:
            return modal.getByLabel(fieldLabel, { exact: true });
    }
};

const searchRecommendationsGrid = async (page, query) => {
    await page.waitForSelector('table.table tbody tr', { state: 'visible', timeout: 30000 });
    const filterBtn = page.locator('#filterDropdown, button:has-text("Filter")').first();
    await filterBtn.click();
    await page.waitForTimeout(300);

    const searchInput = page.locator('[data-grid-view-target="searchInput"]');
    await searchInput.fill(query);
    await Promise.all([
        page.waitForResponse(
            (response) => response.url().includes('/awards/recommendations/grid-data') && response.status() === 200,
            { timeout: 30000 },
        ).catch(() => null),
        searchInput.press('Enter'),
    ]);
    await page.waitForTimeout(1000);
    await page.keyboard.press('Escape').catch(() => {});
    await page.waitForTimeout(500);
};

const selectFixtureRows = async (page) => {
    const fixtures = ensureFixtureSet(page);
    for (const id of fixtures.ids) {
        const checkbox = page.locator(`table tbody tr[data-id="${id}"] input[data-grid-view-target="rowCheckbox"]`);
        await expect(checkbox).toBeVisible();
        await checkbox.check();
    }
};

const getStateRow = (page) => page.locator('tr').filter({ has: page.locator('th', { hasText: 'State' }) }).locator('td');
const getStatusRow = (page) => page.locator('tr').filter({ has: page.locator('th', { hasText: 'Status' }) }).locator('td');

Given('I create recommendation fixtures for {string}', async ({ page }, setName) => {
    const fixtureDefinitions = FIXTURE_SETS[setName];
    if (!fixtureDefinitions) {
        throw new Error(`Unknown award recommendation fixture set "${setName}".`);
    }

    const token = `E2E-REC-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const result = runPhpJson(CREATE_FIXTURES_PHP, {
        requesterEmail: FIXTURE_REQUESTER_EMAIL,
        memberName: FIXTURE_MEMBER_NAME,
        fixtures: fixtureDefinitions.map((fixture) => ({
            ...fixture,
            reason: `${token} ${fixture.name} workflow coverage`,
        })),
    });

    const fixtureMap = result.fixtures ?? {};
    const ids = Object.values(fixtureMap).map((fixture) => fixture.id);
    const headId = ids.length > 0 ? Math.min(...ids) : null;
    const headName = Object.entries(fixtureMap).find(([, fixture]) => fixture.id === headId)?.[0] ?? null;

    page.__awardRecommendationFixtures = {
        token,
        fixtureMap,
        ids,
        headId,
        headName,
    };
});

When('I search the recommendations grid for the current fixture token', async ({ page }) => {
    await searchRecommendationsGrid(page, ensureFixtureSet(page).token);
});

When('I open the {string} recommendation detail view', async ({ page }, name) => {
    const fixture = getFixture(page, name);
    await page.goto(`/awards/recommendations/view/${fixture.id}`, { waitUntil: 'networkidle' });
});

When('I open the group head recommendation detail view', async ({ page }) => {
    const fixtures = ensureFixtureSet(page);
    await page.goto(`/awards/recommendations/view/${fixtures.headId}`, { waitUntil: 'networkidle' });
});

When('I open the detail edit modal', async ({ page }) => {
    await page.getByRole('button', { name: 'Edit', exact: true }).click();
    await page.waitForSelector('#editModal.show', { state: 'visible', timeout: 10000 });
    await page.locator('#editModal select[name="state"]').waitFor({ state: 'visible', timeout: 10000 });
});

When('I open the {string} recommendation quick edit modal from the grid', async ({ page }, name) => {
    const fixture = getFixture(page, name);
    const row = page.locator(`table tbody tr[data-id="${fixture.id}"]`);
    await expect(row).toBeVisible();
    await row.locator('button.edit-rec').click();
    await page.waitForSelector('#editRecommendationModal.show', { state: 'visible', timeout: 10000 });
    await page.locator('#editRecommendationModal select[name="state"]').waitFor({ state: 'visible', timeout: 10000 });
});

When('I change the open recommendation state to {string}', async ({ page }, state) => {
    const modal = await getOpenRecommendationEditModal(page);
    await modal.locator('select[name="state"]').selectOption({ label: state });
    await page.waitForTimeout(500);
});

When('I select the first available gathering in the open recommendation edit modal', async ({ page }) => {
    const modal = await getOpenRecommendationEditModal(page);
    const input = modal.locator('input[name="gathering_name-Disp"]');
    const combo = input.locator('xpath=ancestor::div[@data-controller="ac"][1]');
    await expect(input).toBeVisible();
    await expect(input).toBeEnabled({ timeout: 10000 });
    await input.fill('Scale Future Gathering');
    const option = combo.locator('ul.auto-complete-list li').first();
    await expect(option).toBeVisible({ timeout: 10000 });
    await option.click();
    await expect(modal.locator('input[name="gathering_id"]')).not.toHaveValue('', { timeout: 5000 });
});

When('I set the open recommendation given date to today', async ({ page }) => {
    const modal = await getOpenRecommendationEditModal(page);
    const today = new Date().toISOString().slice(0, 10);
    await modal.locator('input[name="given"]').fill(today);
});

When('I fill in the open recommendation note with {string}', async ({ page }, note) => {
    const modal = await getOpenRecommendationEditModal(page);
    await modal.locator('textarea[name="note"]').fill(note);
});

When('I fill in the open recommendation close reason with {string}', async ({ page }, reason) => {
    const modal = await getOpenRecommendationEditModal(page);
    await modal.locator('input[name="close_reason"]').fill(reason);
});

When('I submit the open recommendation edit modal', async ({ page }) => {
    const modal = await getOpenRecommendationEditModal(page);
    await modal.locator('button[type="submit"]').click();
    await page.waitForTimeout(1500);
    if (await modal.isVisible()) {
        await expect(modal).toBeHidden({ timeout: 10000 });
    }
    await page.waitForLoadState('networkidle');
});

Then('the open recommendation edit modal should show the {string} field', async ({ page }, fieldLabel) => {
    const modal = await getOpenRecommendationEditModal(page);
    await expect(getRecommendationFieldLocator(modal, fieldLabel)).toBeVisible();
});

Then('the open recommendation edit modal should not show the {string} field', async ({ page }, fieldLabel) => {
    const modal = await getOpenRecommendationEditModal(page);
    await expect(getRecommendationFieldLocator(modal, fieldLabel)).not.toBeVisible();
});

Then('the recommendation detail page should show {string} in the state row', async ({ page }, text) => {
    await expect(getStateRow(page)).toContainText(text);
});

Then('the recommendation detail page should show {string} in the status row', async ({ page }, text) => {
    await expect(getStatusRow(page)).toContainText(text);
});

When('I select all current fixture recommendations in the grid', async ({ page }) => {
    await selectFixtureRows(page);
});

When('I open the bulk edit modal', async ({ page }) => {
    await page.locator('button[data-bulk-action-key="bulk-edit"]').click();
    await page.waitForSelector('#bulkEditRecommendationModal.show', { state: 'visible', timeout: 10000 });
    await page.locator('#bulkEditRecommendationModal select[name="newState"]').waitFor({ state: 'visible', timeout: 10000 });
});

When('I change the bulk edit state to {string}', async ({ page }, state) => {
    const modal = page.locator('#bulkEditRecommendationModal');
    await modal.locator('select[name="newState"]').selectOption({ label: state });
    await page.waitForTimeout(500);
});

When('I fill in the bulk edit close reason with {string}', async ({ page }, reason) => {
    await page.locator('#bulkEditRecommendationModal input[name="close_reason"]').fill(reason);
});

When('I fill in the bulk edit note with {string}', async ({ page }, note) => {
    await page.locator('#bulkEditRecommendationModal textarea[name="note"]').fill(note);
});

When('I submit the bulk edit modal', async ({ page }) => {
    const modal = page.locator('#bulkEditRecommendationModal');
    await modal.locator('button[type="submit"]').click();
    await page.waitForTimeout(1500);
    if (await modal.isVisible()) {
        await expect(modal).toBeHidden({ timeout: 10000 });
    }
    await page.waitForLoadState('networkidle');
});

Then('the bulk edit modal should show the {string} field', async ({ page }, fieldLabel) => {
    const modal = page.locator('#bulkEditRecommendationModal');
    await expect(getRecommendationFieldLocator(modal, fieldLabel)).toBeVisible();
});

Then('each current fixture recommendation row should contain {string}', async ({ page }, text) => {
    const fixtures = ensureFixtureSet(page);
    for (const id of fixtures.ids) {
        await expect(page.locator(`table tbody tr[data-id="${id}"]`)).toContainText(text);
    }
});

Then('the {string} recommendation row should contain {string}', async ({ page }, name, text) => {
    const fixture = getFixture(page, name);
    await expect(page.locator(`table tbody tr[data-id="${fixture.id}"]`)).toContainText(text);
});

When('I open the group recommendations modal', async ({ page }) => {
    await page.locator('button[data-bulk-action-key="group-recs"]').click();
    await page.waitForSelector('#groupRecommendationsModal.show', { state: 'visible', timeout: 10000 });
});

Then('the group recommendations modal should describe grouping the selected recommendations', async ({ page }) => {
    await expect(page.locator('#groupRecommendationsModal')).toContainText(
        'will be grouped together. The first selected recommendation will become the group head.',
    );
});

When('I submit the group recommendations modal', async ({ page }) => {
    await page.locator('#groupRecommendationsModal button[type="submit"]').click();
    await page.waitForTimeout(1500);
    await expect(page.locator('#groupRecommendationsModal')).toBeHidden({ timeout: 10000 });
    await page.waitForLoadState('networkidle');
});

Then('the recommendation detail page should show the {string} tab', async ({ page }, tabLabel) => {
    await expect(page.getByRole('tab', { name: new RegExp(`^${tabLabel}`) })).toBeVisible();
});

Then('the recommendation detail page should not show the {string} tab', async ({ page }, tabLabel) => {
    await expect(page.getByRole('tab', { name: new RegExp(`^${tabLabel}`) })).toHaveCount(0);
});

Then('the recommendation group head should list {int} grouped recommendations', async ({ page }, count) => {
    await page.getByRole('tab', { name: /^Grouped/ }).click();
    await expect(page.locator('#nav-grouped tbody tr')).toHaveCount(count);
});

When('I remove the grouped recommendation {string} from the detail view', async ({ page }, name) => {
    const fixture = getFixture(page, name);
    await page.getByRole('tab', { name: /^Grouped/ }).click();
    const row = page.locator('#nav-grouped tbody tr').filter({
        has: page.locator(`a[href="/awards/recommendations/view/${fixture.id}"]`),
    });
    await expect(row).toHaveCount(1);
    page.once('dialog', (dialog) => dialog.accept());
    await row.locator('[title="Remove from group"], .btn-outline-danger').first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
});

When('I ungroup all recommendations from the detail view', async ({ page }) => {
    await page.getByRole('tab', { name: /^Grouped/ }).click();
    page.once('dialog', (dialog) => dialog.accept());
    await page.locator('#nav-grouped').getByText('Ungroup All').click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
});

After(async ({ page }) => {
    if (!page.__awardRecommendationFixtures?.ids?.length) {
        return;
    }

    runPhpJson(CLEANUP_FIXTURES_PHP, {
        ids: page.__awardRecommendationFixtures.ids,
    });
});

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
