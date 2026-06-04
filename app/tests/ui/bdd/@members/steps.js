const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const { flushWorkflowsAndQueue, runPhpJson, waitForQueueSettled } = require('../../support/ui-helpers.cjs');

const { Given, When, Then } = createBdd();

const APP_ROOT = path.resolve(__dirname, '../../../..');
const REPO_ROOT = path.resolve(APP_ROOT, '..');
const VALID_CARD_PATH = path.resolve(APP_ROOT, 'webroot/img/badge.png');
const INVALID_CARD_PATH = path.resolve(REPO_ROOT, 'README.md');

const SETUP_REGISTRATION_FIXTURE_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \Cake\ORM\TableRegistry::getTableLocator();
$definitions = $locator->get('WorkflowDefinitions');
$branches = $locator->get('Branches');

$definition = $definitions->find()->where(['slug' => 'member-registration'])->firstOrFail();
$definition->is_active = true;
$definitions->saveOrFail($definition);

\App\KMP\StaticHelpers::setAppSetting('KMP.EnablePublicRegistration', 'yes', 'string', true);
\App\KMP\StaticHelpers::setAppSetting('Members.NewMemberSecretaryEmail', 'adult.secretary@example.test', 'string', true);
\App\KMP\StaticHelpers::setAppSetting('Members.NewMinorSecretaryEmail', 'minor.secretary@example.test', 'string', true);
\App\KMP\StaticHelpers::setAppSetting('Email.SiteAdminSignature', 'Test Site Admin', 'string', true);
\App\KMP\StaticHelpers::setAppSetting('KMP.LongSiteTitle', 'Known Membership Portal', 'string', true);

$branch = $branches->find()
    ->select(['id', 'name'])
    ->where(['can_have_members' => true])
    ->orderBy(['id' => 'ASC'])
    ->firstOrFail();

$registrantType = strtolower((string)($input['registrantType'] ?? 'adult'));
$isYouth = $registrantType === 'youth';
$cardMode = strtolower((string)($input['cardMode'] ?? 'no'));
$token = preg_replace('/[^a-z0-9]/', '', strtolower((string)($input['token'] ?? uniqid('memberreg', true))));
$suffix = substr($token, -8);
$memberName = ($isYouth ? 'Youth' : 'Adult') . ' Registration ' . $suffix;
$firstName = $isYouth ? 'Youth' : 'Adult';
$lastName = 'Workflow' . strtoupper(substr($suffix, -4));
$birthYear = (int)date('Y') - ($isYouth ? 12 : 28);
$emailLocal = ($isYouth ? 'yreg' : 'areg') . substr(sha1($token), 0, 20);
$expectedStatus = $isYouth ? \App\Model\Entity\Member::STATUS_UNVERIFIED_MINOR : \App\Model\Entity\Member::STATUS_ACTIVE;
$expectedSuccessMessage = $isYouth
    ? 'Your registration has been submitted. The Kingdom Secretary will need to verify your account with your parent or guardian'
    : 'Your registration has been submitted. Please check your email for a link to set up your password.';

echo json_encode([
    'registrantType' => $registrantType,
    'cardMode' => $cardMode,
    'branchId' => (int)$branch->id,
    'branchName' => (string)$branch->name,
    'memberName' => $memberName,
    'firstName' => $firstName,
    'lastName' => $lastName,
    'email' => $emailLocal . '@example.test',
    'birthMonth' => 1,
    'birthYear' => $birthYear,
    'expectedStatus' => $expectedStatus,
    'expectPasswordToken' => !$isYouth,
    'expectedCardPresent' => $cardMode === 'uploaded',
    'expectedCardPhrase' => $cardMode === 'uploaded' ? 'uploaded' : 'not uploaded',
    'expectedSuccessMessage' => $expectedSuccessMessage,
    'adultSecretaryEmail' => 'adult.secretary@example.test',
    'minorSecretaryEmail' => 'minor.secretary@example.test',
    'expectedWelcomeSubject' => 'Welcome ' . $memberName,
    'expectedAdultSecretarySubject' => 'New Member Registration: ' . $memberName,
    'expectedMinorSecretarySubject' => 'New Minor Member Registration: ' . $memberName,
], JSON_THROW_ON_ERROR);
`;

const INSPECT_REGISTRATION_FIXTURE_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \Cake\ORM\TableRegistry::getTableLocator();
$members = $locator->get('Members');
$workflowInstances = $locator->get('WorkflowInstances');

$member = $members->find()
    ->select([
        'id',
        'public_id',
        'sca_name',
        'email_address',
        'status',
        'password_token',
        'password_token_expires_on',
        'membership_card_path',
    ])
    ->where(['email_address' => $input['email']])
    ->first();

if ($member === null) {
    echo json_encode(['memberFound' => false], JSON_THROW_ON_ERROR);
    return;
}

$workflowInstance = null;
$instances = $workflowInstances->find()
    ->orderBy(['WorkflowInstances.id' => 'DESC'])
    ->all();
foreach ($instances as $instance) {
    $context = $instance->context ?? [];
    $triggerMemberId = $context['trigger']['memberId'] ?? null;
    if ((int)$triggerMemberId !== (int)$member->id) {
        continue;
    }
    $workflowInstance = $instance;
    break;
}

$cardPath = $member->membership_card_path;
$cardExists = false;
if (!empty($cardPath)) {
    $cardExists = file_exists(WWW_ROOT . '../images/uploaded/' . $cardPath);
}

echo json_encode([
    'memberFound' => true,
    'memberId' => (int)$member->id,
    'memberPublicId' => (string)$member->public_id,
    'memberName' => (string)$member->sca_name,
    'memberEmail' => (string)$member->email_address,
    'status' => (string)$member->status,
    'passwordTokenPresent' => !empty($member->password_token),
    'passwordTokenExpiresOn' => $member->password_token_expires_on?->format('Y-m-d H:i:s'),
    'membershipCardPresent' => !empty($cardPath),
    'membershipCardPath' => $cardPath,
    'membershipCardExists' => $cardExists,
    'workflowInstance' => $workflowInstance ? [
        'id' => (int)$workflowInstance->id,
        'status' => (string)$workflowInstance->status,
    ] : null,
], JSON_THROW_ON_ERROR);
`;

const normalizeText = (value) => value.replace(/[·\u00B7\u00A0]/g, ' ').replace(/\s+/g, ' ').trim();

const resetDevDatabase = () => {
    const resetFlag = (process.env.PLAYWRIGHT_RESET_DB ?? '').toLowerCase();
    if (resetFlag === '0' || resetFlag === 'false' || resetFlag === 'no') {
        return;
    }

    execFileSync(
        'bash',
        [path.join(REPO_ROOT, 'reset_dev_database.sh')],
        {
            cwd: REPO_ROOT,
            env: process.env,
            stdio: 'pipe',
        },
    );
};

const ensureFixture = (page) => {
    const fixture = page.__memberRegistrationFixture;
    if (!fixture) {
        throw new Error('Member registration fixture has not been prepared.');
    }

    return fixture;
};

const refreshFixtureState = (page) => {
    const fixture = ensureFixture(page);
    fixture.state = runPhpJson(INSPECT_REGISTRATION_FIXTURE_PHP, {
        email: fixture.email,
    });

    return fixture.state;
};

const waitForFixtureState = async (page, predicate, errorMessage, attempts = 10) => {
    let lastState = null;
    for (let attempt = 0; attempt < attempts; attempt += 1) {
        lastState = refreshFixtureState(page);
        if (predicate(lastState)) {
            return lastState;
        }
        await page.waitForTimeout(1000);
    }

    throw new Error(`${errorMessage}\nLast state: ${JSON.stringify(lastState)}`);
};

const chooseAutocompleteOption = async (scope, label, queryText, optionText) => {
    const input = scope.getByLabel(label, { exact: true });
    await input.click();
    await input.fill(queryText);
    const option = scope.locator('[role="option"]').filter({ hasText: optionText }).first();
    await expect(option).toBeVisible({ timeout: 15000 });
    await option.click();
};

const getFlashText = async (page) => {
    const alert = page.getByRole('alert').first();
    await expect(alert).toBeVisible({ timeout: 15000 });

    return normalizeText(await alert.textContent());
};

const clearMailpit = async (page) => {
    const response = await page.request.delete('http://127.0.0.1:8025/api/v1/messages');
    expect(response.ok()).toBeTruthy();
};

const waitForEmailPresence = async (page, subject, shouldExist, attempts = 10) => {
    let count = 0;
    for (let attempt = 0; attempt < attempts; attempt += 1) {
        await page.goto('http://127.0.0.1:8025', { waitUntil: 'networkidle' });
        count = await page.locator(`.subject b:has-text("${subject}")`).count();
        if ((shouldExist && count > 0) || (!shouldExist && count === 0)) {
            return count;
        }
        await page.waitForTimeout(1000);
    }

    throw new Error(`Expected subject "${subject}" ${shouldExist ? 'to appear' : 'not to appear'} in Mailpit, found ${count} row(s).`);
};

const assertEmailBodyContains = async (page, subject, recipient, snippets) => {
    await waitForEmailPresence(page, subject, true);
    await page.goto('http://127.0.0.1:8025', { waitUntil: 'networkidle' });
    const row = page.locator(`.subject b:has-text("${subject}")`).first();
    await expect(row).toBeVisible({ timeout: 15000 });
    await row.click();
    const toCell = page.locator('table tr').filter({ hasText: 'To' }).getByRole('link', { name: recipient, exact: true });
    await expect(toCell).toBeVisible();
    const emailBody = normalizeText(await page.locator('#nav-plain-text div').textContent());
    for (const snippet of snippets) {
        expect(emailBody).toContain(normalizeText(snippet));
    }
};

const assertNoEmailWithSubject = async (page, subject) => {
    const count = await waitForEmailPresence(page, subject, false);
    expect(count).toBe(0);
};

Given(
    'I prepare the member registration fixture for a {string} registrant with a {string} membership card',
    async ({ page }, registrantType, cardMode) => {
        resetDevDatabase();
        page.__memberRegistrationFixture = runPhpJson(SETUP_REGISTRATION_FIXTURE_PHP, {
            registrantType,
            cardMode,
            token: `memberregistration${Date.now()}`,
        });
        refreshFixtureState(page);
        await clearMailpit(page);
    },
);

When('I submit the prepared public registration form', async ({ page }) => {
    const fixture = ensureFixture(page);
    await page.goto('/members/register', { waitUntil: 'networkidle' });

    await page.locator('input[name="sca_name"]').fill(fixture.memberName);
    await chooseAutocompleteOption(page, 'Branch', fixture.branchName, fixture.branchName);
    await page.locator('input[name="first_name"]').fill(fixture.firstName);
    await page.locator('input[name="last_name"]').fill(fixture.lastName);
    await page.locator('input[name="street_address"]').fill('123 Workflow Way');
    await page.locator('input[name="city"]').fill('Austin');
    await page.locator('input[name="state"]').fill('TX');
    await page.locator('input[name="zip"]').fill('78701');
    await page.locator('input[name="phone_number"]').fill('5551234567');
    await page.locator('input[name="email_address"]').fill(fixture.email);
    await page.locator('select[name="birth_month"]').selectOption(String(fixture.birthMonth));
    await page.locator('select[name="birth_year"]').selectOption(String(fixture.birthYear));

    if (fixture.cardMode === 'uploaded') {
        await page.locator('input[name="member_card"]').setInputFiles(VALID_CARD_PATH);
    } else if (fixture.cardMode === 'invalid') {
        await page.locator('input[name="member_card"]').setInputFiles(INVALID_CARD_PATH);
    }

    const submitButton = page.getByRole('button', { name: /^Submit$/ });
    if (fixture.cardMode === 'invalid') {
        await submitButton.click();
        await page.waitForLoadState('networkidle');

        return;
    }

    await Promise.all([
        page.waitForURL('**/members/login', { timeout: 30000 }),
        submitButton.click(),
    ]);
    await page.waitForLoadState('networkidle');
});

Then('I should see the expected registration success message', async ({ page }) => {
    const fixture = ensureFixture(page);
    const flashText = await getFlashText(page);
    expect(flashText).toContain(normalizeText(fixture.expectedSuccessMessage));
});

Then('the registration should create the member in the expected state', async ({ page }) => {
    const fixture = ensureFixture(page);
    const state = await waitForFixtureState(
        page,
        (current) => Boolean(current.memberFound),
        'Expected the registration to create a member record.',
    );

    expect(state.memberName).toBe(fixture.memberName);
    expect(state.memberEmail).toBe(fixture.email);
    expect(state.status).toBe(fixture.expectedStatus);
    expect(state.passwordTokenPresent).toBe(fixture.expectPasswordToken);
    if (fixture.expectPasswordToken) {
        expect(state.passwordTokenExpiresOn).toBeTruthy();
    }
    expect(state.membershipCardPresent).toBe(fixture.expectedCardPresent);
    expect(state.membershipCardExists).toBe(fixture.expectedCardPresent);
});

When('I process the registration email queue', async () => {
    flushWorkflowsAndQueue();
    await waitForQueueSettled();
});

Then('the registration emails should match the expected workflow notifications', async ({ page }) => {
    const fixture = ensureFixture(page);
    const state = await waitForFixtureState(
        page,
        (current) => Boolean(current.memberFound),
        'Expected the member to exist before asserting registration emails.',
    );

    if (fixture.registrantType === 'adult') {
        await assertEmailBodyContains(page, fixture.expectedWelcomeSubject, fixture.email, [
            `Welcome, ${fixture.memberName}!`,
            '/members/reset-password/',
        ]);
        await assertEmailBodyContains(
            page,
            fixture.expectedAdultSecretarySubject,
            fixture.adultSecretaryEmail,
            [
                `${fixture.memberName} has recently registered.`,
                `membership card was ${fixture.expectedCardPhrase}`,
                `/members/view/${state.memberId}`,
            ],
        );
        await assertNoEmailWithSubject(page, fixture.expectedMinorSecretarySubject);

        return;
    }

    await assertEmailBodyContains(
        page,
        fixture.expectedMinorSecretarySubject,
        fixture.minorSecretaryEmail,
        [
            `A new minor named ${fixture.memberName} has recently registered.`,
            'account is currently inaccessible',
            `membership card was ${fixture.expectedCardPhrase}`,
            `/members/view/${state.memberId}`,
        ],
    );
    await assertNoEmailWithSubject(page, fixture.expectedWelcomeSubject);
    await assertNoEmailWithSubject(page, fixture.expectedAdultSecretarySubject);
});

Then('the invalid upload should block registration before any member is created', async ({ page }) => {
    const fixture = ensureFixture(page);
    const flashText = await getFlashText(page);
    expect(flashText).toContain('Invalid file type. Only PNG and JPEG images are allowed.');
    expect(page.url()).toContain('/members/register');

    const state = refreshFixtureState(page);
    expect(state.memberFound).toBe(false);
    await assertNoEmailWithSubject(page, fixture.expectedWelcomeSubject);
    await assertNoEmailWithSubject(page, fixture.expectedAdultSecretarySubject);
    await assertNoEmailWithSubject(page, fixture.expectedMinorSecretarySubject);
});
