const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const {
    waitForTurboStreamResponse,
    assertUrlContainsQuery,
    assertGridShellPreserved,
    waitForGridStateJson,
    waitForPageBody,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then, After } = createBdd();

const APP_ROOT = path.resolve(__dirname, '../../../..');
const REPO_ROOT = path.resolve(APP_ROOT, '..');
const { shouldUseDockerPhp } = require('../../support/test-environment.cjs');
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
    const fixtureJson = JSON.stringify(payload);
    const useDockerPhp = shouldUseDockerPhp();
    const output = useDockerPhp
        ? execFileSync(
            'docker',
            [
                'compose',
                'exec',
                '-T',
                '-e',
                `FIXTURE_JSON=${fixtureJson}`,
                'app',
                'php',
                '-d',
                'xdebug.mode=off',
                '-r',
                script,
            ],
            {
                cwd: REPO_ROOT,
                encoding: 'utf8',
            },
        ).trim()
        : execFileSync(
            'php',
            ['-d', 'xdebug.mode=off', '-r', script],
            {
                cwd: APP_ROOT,
                env: {
                    ...process.env,
                    FIXTURE_JSON: fixtureJson,
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

When('I navigate to {string}', async ({ page }, path) => {
    await page.goto(path, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
});

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

// ── Award Bestowals ─────────────────────────────────────────────────

const CREATE_HANDOFF_FIXTURE_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode((string)getenv('FIXTURE_JSON'), true, 512, JSON_THROW_ON_ERROR);
$locator = \\Cake\\ORM\\TableRegistry::getTableLocator();
$recommendations = $locator->get('Awards.Recommendations');
$members = $locator->get('Members');
$awards = $locator->get('Awards.Awards');

$requester = $members->find()
    ->select(['id', 'sca_name', 'email_address'])
    ->where(['email_address' => $input['requesterEmail']])
    ->firstOrFail();
$member = $members->find()
    ->select(['id', 'public_id', 'sca_name'])
    ->where(['sca_name' => $input['memberName']])
    ->firstOrFail();
$award = $awards->find()->select(['id', 'name'])->firstOrFail();

$service = new \\Awards\\Services\\RecommendationSubmissionService();
$result = $service->submitAuthenticated(
    $recommendations,
    [
        'award_id' => (int)$award->id,
        'member_sca_name' => (string)$member->sca_name,
        'member_public_id' => (string)$member->public_id,
        'reason' => (string)$input['reason'],
        'specialty' => 'No specialties available',
    ],
    [
        'id' => (int)$requester->id,
        'sca_name' => (string)$requester->sca_name,
        'email_address' => (string)$requester->email_address,
        'phone_number' => '',
    ],
);

if (!($result['success'] ?? false)) {
    throw new \\RuntimeException('Handoff fixture creation failed: ' . json_encode($result, JSON_THROW_ON_ERROR));
}

$recommendation = $result['recommendation'];
$recommendation->state = 'King Approved';
$recommendation->status = 'In Progress';
$recommendations->saveOrFail($recommendation);

echo json_encode([
    'recommendationId' => (int)$recommendation->id,
    'memberScaName' => (string)$member->sca_name,
    'token' => (string)$input['token'],
], JSON_THROW_ON_ERROR);
`;

const CREATE_HANDOFF_WITH_BESTOWAL_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode((string)getenv('FIXTURE_JSON'), true, 512, JSON_THROW_ON_ERROR);
$locator = \\Cake\\ORM\\TableRegistry::getTableLocator();
$recommendations = $locator->get('Awards.Recommendations');
$members = $locator->get('Members');
$awards = $locator->get('Awards.Awards');

$requester = $members->find()
    ->select(['id', 'sca_name', 'email_address'])
    ->where(['email_address' => $input['requesterEmail']])
    ->firstOrFail();
$member = $members->find()
    ->select(['id', 'public_id', 'sca_name'])
    ->where(['sca_name' => $input['memberName']])
    ->firstOrFail();
$award = $awards->find()->select(['id', 'name'])->firstOrFail();

$service = new \\Awards\\Services\\RecommendationSubmissionService();
$result = $service->submitAuthenticated(
    $recommendations,
    [
        'award_id' => (int)$award->id,
        'member_sca_name' => (string)$member->sca_name,
        'member_public_id' => (string)$member->public_id,
        'reason' => (string)$input['reason'],
        'specialty' => 'No specialties available',
    ],
    [
        'id' => (int)$requester->id,
        'sca_name' => (string)$requester->sca_name,
        'email_address' => (string)$requester->email_address,
        'phone_number' => '',
    ],
);

if (!($result['success'] ?? false)) {
    throw new \\RuntimeException('Handoff fixture creation failed: ' . json_encode($result, JSON_THROW_ON_ERROR));
}

$recommendation = $result['recommendation'];
$recommendation->state = 'Need to Schedule';
$recommendation->status = 'In Progress';
$recommendations->saveOrFail($recommendation);

$bestowalService = new \\Awards\\Services\\BestowalCreationService();
$bestowalResult = $bestowalService->createFromRecommendation((int)$recommendation->id, (int)$requester->id);
if (!($bestowalResult['success'] ?? false)) {
    throw new \\RuntimeException('Bestowal fixture creation failed: ' . json_encode($bestowalResult, JSON_THROW_ON_ERROR));
}

$bestowals = $locator->get('Awards.Bestowals');
$bestowal = $bestowals->get((int)$bestowalResult['data']['bestowalId']);
$bestowal->herald_notes = (string)$input['token'];
$bestowals->saveOrFail($bestowal);

$originalAward = $awards->get((int)$recommendation->award_id, contain: ['Domains']);
$alternateAward = $awards->find()
    ->contain(['Domains'])
    ->where([
        'Awards.id !=' => (int)$originalAward->id,
        'Awards.domain_id !=' => (int)$originalAward->domain_id,
    ])
    ->first();
if ($alternateAward === null) {
    $alternateAward = $awards->find()
        ->contain(['Domains'])
        ->where(['Awards.id !=' => (int)$originalAward->id])
        ->firstOrFail();
}

echo json_encode([
    'recommendationId' => (int)$recommendation->id,
    'bestowalId' => (int)$bestowalResult['data']['bestowalId'],
    'memberScaName' => (string)$member->sca_name,
    'token' => (string)$input['token'],
    'originalAwardId' => (int)$originalAward->id,
    'originalAwardName' => (string)$originalAward->name,
    'originalDomainName' => (string)$originalAward->domain->name,
    'alternateAwardId' => (int)$alternateAward->id,
    'alternateAwardName' => (string)$alternateAward->name,
    'alternateDomainName' => (string)$alternateAward->domain->name,
], JSON_THROW_ON_ERROR);
`;

const CLEANUP_BESTOWAL_FIXTURE_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode((string)getenv('FIXTURE_JSON'), true, 512, JSON_THROW_ON_ERROR);
$bestowals = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Bestowals');
$recommendations = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations');

foreach (array_values(array_unique(array_map('intval', $input['bestowalIds'] ?? []))) as $id) {
    if ($bestowals->exists(['id' => $id])) {
        $bestowals->delete($bestowals->get($id));
    }
}

foreach (array_values(array_unique(array_map('intval', $input['recommendationIds'] ?? []))) as $id) {
    if ($recommendations->exists(['id' => $id])) {
        $recommendations->delete($recommendations->get($id));
    }
}

echo json_encode(['deleted' => true], JSON_THROW_ON_ERROR);
`;

const LOOKUP_BESTOWAL_AWARD_IDS_PHP = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode((string)getenv('FIXTURE_JSON'), true, 512, JSON_THROW_ON_ERROR);
$recommendations = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations');
$bestowals = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Bestowals');

$recommendation = $recommendations->get((int)$input['recommendationId']);
$bestowal = $bestowals->get((int)$input['bestowalId']);

echo json_encode([
    'recommendationAwardId' => (int)$recommendation->award_id,
    'bestowalAwardId' => (int)$bestowal->award_id,
], JSON_THROW_ON_ERROR);
`;

const ensureBestowalFixture = (page) => {
    const fixture = page.__bestowalFixture;
    if (!fixture) {
        throw new Error('Bestowal fixture has not been created for this scenario.');
    }

    return fixture;
};

const getOpenBestowalEditModal = async (page) => {
    const modal = page.locator('#editBestowalModal.show').first();
    await expect(modal).toBeVisible();
    return modal;
};

const waitForBestowalEditForm = async (modal) => {
    const frame = modal.locator('turbo-frame#editBestowalQuick');
    await frame.locator('input[name="domain_id"]').waitFor({ state: 'attached', timeout: 30000 });
    await expect(frame.locator('input[name="domain_id"]')).not.toHaveValue('', { timeout: 30000 });
    await expect(frame.locator('input[name="award_id"]')).not.toHaveValue('', { timeout: 30000 });
};

const getBestowalEditSubmitButton = (page) => page.locator('#editBestowalModal.show #bestowal_submit');

const getBestowalCombo = (modal, dispField, hiddenField) => {
    const frame = modal.locator('turbo-frame#editBestowalQuick');
    const input = frame.locator(`input[name="${dispField}-Disp"]`);
    const combo = input.locator('xpath=ancestor::div[@data-controller="ac"][1]');
    const hidden = frame.locator(`input[name="${hiddenField}"]`);

    return { frame, input, combo, hidden };
};

const selectBestowalComboOption = async (comboBox, optionText) => {
    await waitForBestowalComboOption(comboBox, optionText);

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

const waitForBestowalComboOption = async (comboBox, optionText) => {
    await expect.poll(async () => comboBox.evaluate((element, label) => {
        const controller = window.Stimulus?.getControllerForElementAndIdentifier(element, 'ac');
        const options = Array.isArray(controller?.options)
            ? controller.options
            : JSON.parse(element.querySelector('[data-ac-target="dataList"]')?.textContent ?? '[]');

        return options.some((option) => option.text === label && option.enabled !== false);
    }, optionText), {
        timeout: 15000,
    }).toBe(true);
};

const clearBestowalCombo = async (comboBox) => {
    const clearBtn = comboBox.locator('[data-ac-target="clearBtn"]');
    await expect(clearBtn).toBeEnabled({ timeout: 5000 });
    await clearBtn.click();
    await comboBox.locator('[data-ac-target="input"]').evaluate((input) => {
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
};

const searchBestowalsGrid = async (page, query) => {
    const gridFrame = page.locator('turbo-frame#bestowals-grid');
    await gridFrame.locator('table.table tbody tr').first().waitFor({ state: 'visible', timeout: 30000 });
    const filterBtn = page.locator('#filterDropdown, button:has-text("Filter")').first();
    await filterBtn.click();
    await page.waitForTimeout(300);

    const searchInput = page.locator('[data-grid-view-target="searchInput"]');
    await searchInput.fill(query);
    await Promise.all([
        page.waitForResponse(
            (response) => {
                const responseUrl = new URL(response.url());
                return response.status() === 200
                    && responseUrl.pathname.includes('/awards/bestowals/grid-data')
                    && responseUrl.searchParams.has('search');
            },
            { timeout: 30000 },
        ).catch(() => null),
        searchInput.press('Enter'),
    ]);
    await expect.poll(async () => {
        const bodyText = await gridFrame.innerText().catch(() => '');
        return !bodyText.includes('Content missing');
    }, { timeout: 15000 }).toBe(true);
    await page.keyboard.press('Escape').catch(() => {});
    await page.waitForTimeout(300);
};

Given('I create a bestowal handoff recommendation fixture', async ({ page }) => {
    const token = `E2E-BESTOWAL-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const result = runPhpJson(CREATE_HANDOFF_FIXTURE_PHP, {
        requesterEmail: FIXTURE_REQUESTER_EMAIL,
        memberName: FIXTURE_MEMBER_NAME,
        token,
        reason: `${token} bestowal handoff coverage`,
    });

    page.__bestowalFixture = {
        ...result,
        token,
    };
});

Given('I create a bestowal handoff recommendation fixture with an active bestowal', async ({ page }) => {
    const token = `E2E-BESTOWAL-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const result = runPhpJson(CREATE_HANDOFF_WITH_BESTOWAL_PHP, {
        requesterEmail: FIXTURE_REQUESTER_EMAIL,
        memberName: FIXTURE_MEMBER_NAME,
        token,
        reason: `${token} bestowal cancel coverage`,
    });

    page.__bestowalFixture = {
        ...result,
        token,
    };
});

When('I search the recommendations grid for the current bestowal fixture token', async ({ page }) => {
    await searchRecommendationsGrid(page, ensureBestowalFixture(page).token);
});

When('I search the bestowals grid for the current bestowal fixture token', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    await searchBestowalsGrid(page, fixture.token);
});

When('I select the bestowal handoff recommendation in the grid', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const checkbox = page.locator(
        `table tbody tr[data-id="${fixture.recommendationId}"] input[data-grid-view-target="rowCheckbox"]`,
    );
    await expect(checkbox).toBeVisible();
    await checkbox.check();
});

Then('the bestowal handoff recommendation row should contain {string}', async ({ page }, text) => {
    const fixture = ensureBestowalFixture(page);
    await expect(page.locator(`table tbody tr[data-id="${fixture.recommendationId}"]`)).toContainText(text);
});

Then('the bestowals grid should load successfully', async ({ page }) => {
    await page.waitForSelector('turbo-frame#bestowals-grid', { state: 'attached', timeout: 30000 });
    await expect(page.locator('body')).not.toContainText('Database Error');
    await expect(page.locator('body')).not.toContainText('TypeError');
    await expect(page.locator('body')).not.toContainText('Content missing');
    await page.waitForSelector('turbo-frame#bestowals-grid table.table tbody tr', { state: 'visible', timeout: 30000 });
});

Then('the handoff recommendation should have a linked bestowal', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const lookup = runPhpJson(`
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode((string)getenv('FIXTURE_JSON'), true, 512, JSON_THROW_ON_ERROR);
$rec = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations')->get((int)$input['recommendationId']);
if ((int)($rec->bestowal_id ?? 0) <= 0) {
    throw new \\RuntimeException('Expected recommendation to be linked to a bestowal.');
}
$bestowal = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Bestowals')->get((int)$rec->bestowal_id);
echo json_encode([
    'bestowalId' => (int)$bestowal->id,
    'state' => (string)$bestowal->state,
], JSON_THROW_ON_ERROR);
`, { recommendationId: fixture.recommendationId });

    expect(lookup.bestowalId).toBeGreaterThan(0);
    expect(lookup.state).toBe('Created');
    fixture.bestowalId = lookup.bestowalId;
});

Then('the bestowals grid should contain the fixture member name', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const grid = page.locator('turbo-frame#bestowals-grid table.table').first();
    await expect(grid).toContainText(fixture.memberScaName);
});

When('I open the bestowal detail for the handoff fixture', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    let bestowalId = fixture.bestowalId;

    if (!bestowalId) {
        const lookup = runPhpJson(`
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode((string)getenv('FIXTURE_JSON'), true, 512, JSON_THROW_ON_ERROR);
$rec = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations')->get((int)$input['recommendationId']);
echo json_encode(['bestowalId' => (int)$rec->bestowal_id], JSON_THROW_ON_ERROR);
`, { recommendationId: fixture.recommendationId });
        bestowalId = lookup.bestowalId;
        fixture.bestowalId = bestowalId;
    }

    await page.goto(`/awards/bestowals/view/${bestowalId}`, { waitUntil: 'networkidle' });
});

When('I open the bestowal handoff recommendation detail view', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    await page.goto(`/awards/recommendations/view/${fixture.recommendationId}`, { waitUntil: 'networkidle' });
});

Then('the bestowal detail page should show {string} in the state row', async ({ page }, text) => {
    const stateRow = page.locator('tr').filter({ has: page.locator('th', { hasText: 'State' }) }).locator('td');
    await expect(stateRow).toContainText(text);
});

Then('the bestowal detail page should show {string} in the source row', async ({ page }, text) => {
    const sourceRow = page.locator('tr').filter({ has: page.locator('th', { hasText: 'Source' }) }).locator('td');
    await expect(sourceRow).toContainText(text);
});

When('I cancel the open bestowal from the detail page', async ({ page }) => {
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('link', { name: 'Cancel Bestowal' }).click();
    await page.waitForLoadState('networkidle');
});

When('I open the bestowal edit modal', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    let bestowalId = fixture.bestowalId;
    if (!bestowalId) {
        const lookup = runPhpJson(`
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode((string)getenv('FIXTURE_JSON'), true, 512, JSON_THROW_ON_ERROR);
$rec = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations')->get((int)$input['recommendationId']);
echo json_encode(['bestowalId' => (int)$rec->bestowal_id], JSON_THROW_ON_ERROR);
`, { recommendationId: fixture.recommendationId });
        bestowalId = lookup.bestowalId;
        fixture.bestowalId = bestowalId;
    }

    const visibleModal = page.locator('#editBestowalModal.show').first();
    const turboPath = `/awards/bestowals/turbo-edit-form/${bestowalId}`;
    await Promise.all([
        page.waitForResponse(
            (response) => response.url().includes(turboPath) && response.ok(),
            { timeout: 30000 },
        ).catch(() => null),
        page.locator('button.edit-bestowal').first().click(),
    ]);
    await expect(visibleModal).toBeVisible();
    await waitForBestowalEditForm(visibleModal);
});

When('I clear the bestowal edit award type field', async ({ page }) => {
    const modal = await getOpenBestowalEditModal(page);
    const { combo } = getBestowalCombo(modal, 'domain_name', 'domain_id');
    await clearBestowalCombo(combo);
    await page.waitForTimeout(300);
});

When('I clear the bestowal edit award to bestow field', async ({ page }) => {
    const modal = await getOpenBestowalEditModal(page);
    const { combo } = getBestowalCombo(modal, 'award_name', 'award_id');
    await clearBestowalCombo(combo);
    await page.waitForTimeout(300);
});

When('I select the original bestowal award type in the edit modal', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const modal = await getOpenBestowalEditModal(page);
    const { combo } = getBestowalCombo(modal, 'domain_name', 'domain_id');
    await Promise.all([
        page.waitForResponse(
            (response) => response.url().includes('/awards/awards-by-domain/')
                && response.ok(),
            { timeout: 15000 },
        ),
        selectBestowalComboOption(combo, fixture.originalDomainName),
    ]).catch(async () => {
        await selectBestowalComboOption(combo, fixture.originalDomainName);
        await page.waitForTimeout(1000);
    });
    await page.waitForTimeout(500);
});

When('I select the original bestowal award in the edit modal', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const modal = await getOpenBestowalEditModal(page);
    const awardCombo = getBestowalCombo(modal, 'award_name', 'award_id');
    await expect(awardCombo.input).toBeEnabled({ timeout: 10000 });
    await waitForBestowalComboOption(awardCombo.combo, fixture.originalAwardName);
    await selectBestowalComboOption(awardCombo.combo, fixture.originalAwardName);
    await expect(awardCombo.hidden).not.toHaveValue('', { timeout: 15000 });
    await expect(awardCombo.input).not.toHaveValue('', { timeout: 15000 });
    await page.waitForTimeout(300);
});

When('I change the bestowal edit award to the alternate award', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const modal = await getOpenBestowalEditModal(page);
    const domainCombo = getBestowalCombo(modal, 'domain_name', 'domain_id');
    await clearBestowalCombo(domainCombo.combo);
    await Promise.all([
        page.waitForResponse(
            (response) => response.url().includes('/awards/awards-by-domain/')
                && response.ok(),
            { timeout: 15000 },
        ),
        selectBestowalComboOption(domainCombo.combo, fixture.alternateDomainName),
    ]).catch(async () => {
        await selectBestowalComboOption(domainCombo.combo, fixture.alternateDomainName);
        await page.waitForTimeout(1000);
    });
    await page.waitForTimeout(500);

    const awardCombo = getBestowalCombo(modal, 'award_name', 'award_id');
    await expect(awardCombo.input).toBeEnabled({ timeout: 10000 });
    await selectBestowalComboOption(awardCombo.combo, fixture.alternateAwardName);
    await expect(awardCombo.hidden).not.toHaveValue('', { timeout: 15000 });
    await page.waitForTimeout(300);
});

When('I submit the bestowal edit modal', async ({ page }) => {
    const modal = await getOpenBestowalEditModal(page);
    const submitButton = getBestowalEditSubmitButton(page);
    await expect(submitButton).toBeEnabled();
    await Promise.all([
        page.waitForResponse(
            (response) => response.url().includes('/awards/bestowals/edit/')
                && response.request().method() === 'POST',
            { timeout: 30000 },
        ),
        submitButton.click(),
    ]);
    await expect(modal).toBeHidden({ timeout: 15000 });
    await page.waitForLoadState('networkidle');
});

When('I select the handoff bestowal in the grid', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    let bestowalId = fixture.bestowalId;
    if (!bestowalId) {
        const lookup = runPhpJson(`
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode((string)getenv('FIXTURE_JSON'), true, 512, JSON_THROW_ON_ERROR);
$rec = \\Cake\\ORM\\TableRegistry::getTableLocator()->get('Awards.Recommendations')->get((int)$input['recommendationId']);
echo json_encode(['bestowalId' => (int)$rec->bestowal_id], JSON_THROW_ON_ERROR);
`, { recommendationId: fixture.recommendationId });
        bestowalId = lookup.bestowalId;
        fixture.bestowalId = bestowalId;
    }

    const row = page.locator(
        `turbo-frame#bestowals-grid turbo-frame#bestowals-grid-table table tbody tr[data-id="${bestowalId}"]`,
    );
    await expect.poll(async () => row.isVisible(), { timeout: 15000 }).toBe(true);
    const checkbox = row.locator('input[data-grid-view-target="rowCheckbox"]');
    await checkbox.check();
});

When('I open the bestowal bulk edit modal', async ({ page }) => {
    await page.locator('button[data-bulk-action-key="bulk-edit"]').click();
    await page.waitForSelector('#bulkEditBestowalModal.show', { state: 'visible', timeout: 10000 });
    await page.locator('#bulkEditBestowalModal select[name="newState"]').waitFor({ state: 'visible', timeout: 10000 });
});

When('I change the bestowal bulk edit state to {string}', async ({ page }, state) => {
    const modal = page.locator('#bulkEditBestowalModal');
    await modal.locator('select[name="newState"]').selectOption({ label: state });
    await page.waitForTimeout(500);
});

Then('the bestowal edit modal submit button should be enabled', async ({ page }) => {
    const submit = getBestowalEditSubmitButton(page);
    await expect(submit).toBeEnabled({ timeout: 15000 });
});

Then('the bestowal edit modal submit button should be disabled', async ({ page }) => {
    const submit = getBestowalEditSubmitButton(page);
    await expect(submit).toBeDisabled({ timeout: 15000 });
});

Then('the bestowal edit award to bestow field should be disabled', async ({ page }) => {
    const modal = await getOpenBestowalEditModal(page);
    const { input } = getBestowalCombo(modal, 'award_name', 'award_id');
    await expect(input).toBeDisabled();
});

Then('the bestowal edit award to bestow field should be empty', async ({ page }) => {
    const modal = await getOpenBestowalEditModal(page);
    const { hidden, input } = getBestowalCombo(modal, 'award_name', 'award_id');
    await expect(hidden).toHaveValue('');
    await expect(input).toHaveValue('');
});

Then('the bestowal edit award type field should be empty', async ({ page }) => {
    const modal = await getOpenBestowalEditModal(page);
    const { hidden, input } = getBestowalCombo(modal, 'domain_name', 'domain_id');
    await expect(hidden).toHaveValue('');
    await expect(input).toHaveValue('');
});

Then('the linked recommendation should keep its original award', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const lookup = runPhpJson(LOOKUP_BESTOWAL_AWARD_IDS_PHP, {
        recommendationId: fixture.recommendationId,
        bestowalId: fixture.bestowalId,
    });
    expect(lookup.recommendationAwardId).toBe(fixture.originalAwardId);
});

Then('the bestowal should have the alternate award', async ({ page }) => {
    const fixture = ensureBestowalFixture(page);
    const lookup = runPhpJson(LOOKUP_BESTOWAL_AWARD_IDS_PHP, {
        recommendationId: fixture.recommendationId,
        bestowalId: fixture.bestowalId,
    });
    expect(lookup.bestowalAwardId).toBe(fixture.alternateAwardId);
    expect(lookup.recommendationAwardId).not.toBe(fixture.alternateAwardId);
});

Then('the bestowal bulk edit submit button should be disabled', async ({ page }) => {
    const modal = page.locator('#bulkEditBestowalModal');
    await expect(modal.locator('#bestowal_bulk_submit')).toBeDisabled();
});

Then('the bestowal bulk edit submit button should be enabled', async ({ page }) => {
    const modal = page.locator('#bulkEditBestowalModal');
    await expect.poll(async () => modal.locator('#bestowal_bulk_submit').isEnabled(), {
        timeout: 15000,
    }).toBe(true);
});

const waitForConfigGrid = async (page, frameId) => {
    const gridFrame = page.locator(`turbo-frame#${frameId}`);
    await gridFrame.waitFor({ state: 'attached', timeout: 30000 });
    await expect(page.locator('body')).not.toContainText('Database Error');
    await expect(page.locator('body')).not.toContainText('Forbidden');
    await page.waitForSelector(`turbo-frame#${frameId} table.table tbody tr`, {
        state: 'visible',
        timeout: 30000,
    });
};

Then('the bestowal statuses grid should load successfully', async ({ page }) => {
    await waitForConfigGrid(page, 'bestowal-statuses-grid');
});

Then('the bestowal states grid should load successfully', async ({ page }) => {
    await waitForConfigGrid(page, 'bestowal-states-grid');
});

When('I open the bestowal state named {string} from the grid', async ({ page }, stateName) => {
    const row = page.locator('turbo-frame#bestowal-states-grid table.table tbody tr', {
        hasText: stateName,
    }).first();
    await expect(row).toBeVisible({ timeout: 30000 });
    await row.locator('a').first().click();
    await page.waitForLoadState('networkidle');
});

Then('the bestowal state detail should show the {string} tab', async ({ page }, tabLabel) => {
    await expect(page.locator('#nav-field-rules-tab, button.nav-link', { hasText: tabLabel }).first()).toBeVisible();
});

When('I navigate to the bestowal state view for {string}', async ({ page }, stateName) => {
    await page.goto('/awards/bestowal-states');
    await waitForConfigGrid(page, 'bestowal-states-grid');
    const row = page.locator('turbo-frame#bestowal-states-grid table.table tbody tr', {
        hasText: stateName,
    }).first();
    await expect(row).toBeVisible({ timeout: 30000 });
    await row.locator('a').first().click();
    await page.waitForLoadState('networkidle');
    page.__bestowalWorkflowStateName = stateName;
});

When('I add a visible field rule for {string} on the current bestowal state', async ({ page }, fieldTarget) => {
    await page.locator('#nav-field-rules-tab').click();
    await page.locator('[data-bs-target="#addFieldRuleModal"]').click();
    const modal = page.locator('#addFieldRuleModal');
    await expect(modal).toBeVisible();
    await modal.locator('select[name="field_target"]').selectOption(fieldTarget);
    await modal.locator('select[name="rule_type"]').selectOption('Visible');
    await modal.getByRole('button', { name: 'Add Rule', exact: true }).click();
    await expect(page.getByRole('alert').first()).toContainText('Field rule added.', { timeout: 15000 });
});

When('I submit the bestowal state transitions form on the current state', async ({ page }) => {
    await page.locator('#nav-transitions-tab').click();
    const form = page.locator('#nav-transitions form');
    await expect(form).toBeVisible();
    await form.getByRole('button', { name: 'Save Transitions', exact: true }).click();
    await expect(page.getByRole('alert').first()).toContainText('Transitions updated.', { timeout: 15000 });
});

When('I submit the open recommendation quick edit with a turbo stream response', async ({ page }) => {
    const modal = page.locator('#editRecommendationModal');
    await expect(modal).toBeVisible();
    const form = modal.locator('turbo-frame#editRecommendationQuick form').first();
    await expect(form).toBeVisible({ timeout: 15000 });

    await waitForTurboStreamResponse(page, async () => {
        await form.locator('button[type="submit"]').click();
    });

    await expect(modal).toBeHidden({ timeout: 15000 });
    await page.locator('turbo-frame#recommendations-grid-table table.table tbody tr').first().waitFor({
        state: 'visible',
        timeout: 30000,
    });
});

Then('the recommendations URL should include the current fixture token', async ({ page }) => {
    const token = page.__awardRecommendationFixtures?.token;
    expect(token).toBeTruthy();
    await assertUrlContainsQuery(page, encodeURIComponent(token));
});

Then('the recommendations grid shell should remain connected', async ({ page }) => {
    await assertGridShellPreserved(page, '[data-controller*="grid-view"]');
});

Then('the recommendations grid state script should be present', async ({ page }) => {
    const state = await waitForGridStateJson(page, 'recommendations-grid-table');
    expect(state).toHaveProperty('config');
});

After(async ({ page }) => {
    if (!page.__bestowalFixture) {
        return;
    }

    const fixture = page.__bestowalFixture;
    runPhpJson(CLEANUP_BESTOWAL_FIXTURE_PHP, {
        bestowalIds: fixture.bestowalId ? [fixture.bestowalId] : [],
        recommendationIds: fixture.recommendationId ? [fixture.recommendationId] : [],
    });
});
