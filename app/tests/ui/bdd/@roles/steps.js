const { execFileSync } = require('node:child_process');
const path = require('node:path');

const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const {
    clickTabAndWait,
    getSignOutButton,
    isLocatorVisible,
    loginAs,
    runPhpJson,
    runAndWaitForNetworkIdle,
    waitForPageBody,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then } = createBdd();

const APP_ROOT = path.resolve(__dirname, '../../../..');
const REPO_ROOT = path.resolve(APP_ROOT, '..');
const SETUP_FIXTURE_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode((string)stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$locator = \Cake\ORM\TableRegistry::getTableLocator();
$permissions = $locator->get('Permissions');
$members = $locator->get('Members');
$branches = $locator->get('Branches');
$awards = $locator->get('Awards.Awards');
$recommendations = $locator->get('Awards.Recommendations');

$permission = $permissions->find()
    ->select(['id', 'name'])
    ->where(['name' => 'Branch Non-Armiguous Recommendation Manager'])
    ->firstOrFail();
$member = $members->find()
    ->select(['id', 'public_id', 'sca_name', 'email_address'])
    ->where(['email_address' => 'iris@ampdemo.com'])
    ->firstOrFail();
$admin = $members->find()
    ->select(['id', 'sca_name', 'email_address', 'phone_number', 'branch_id'])
    ->where(['email_address' => 'admin@amp.ansteorra.org'])
    ->firstOrFail();
$branch = $branches->find()
    ->select(['id', 'name'])
    ->where(['id' => (int)$admin->branch_id])
    ->firstOrFail();
$award = $awards->find()
    ->select(['id', 'name'])
    ->where(['name' => 'Award of Arms'])
    ->firstOrFail();

$token = preg_replace('/[^a-z0-9]/', '', strtolower((string)($input['token'] ?? uniqid('rolereport', true))));
$suffix = strtoupper(substr($token, -8));

if ($suffix === '') {
    $suffix = 'E2E';
}

$reason = 'Role lifecycle access token ' . $suffix;
$submission = (new \Awards\Services\RecommendationSubmissionService())->submitAuthenticated(
    $recommendations,
    [
        'award_id' => (int)$award->id,
        'member_sca_name' => (string)$member->sca_name,
        'member_public_id' => (string)$member->public_id,
        'reason' => $reason,
        'specialty' => 'No specialties available',
    ],
    [
        'id' => (int)$admin->id,
        'sca_name' => (string)$admin->sca_name,
        'email_address' => (string)$admin->email_address,
        'phone_number' => (string)($admin->phone_number ?? ''),
    ],
);

if (!($submission['success'] ?? false)) {
    throw new \RuntimeException('Recommendation fixture creation failed: ' . json_encode($submission, JSON_THROW_ON_ERROR));
}

$recommendation = $submission['recommendation'];

echo json_encode([
    'roleName' => 'PW Awards Access ' . $suffix,
    'editedRoleName' => 'PW Awards Access Updated ' . $suffix,
    'permissionId' => (int)$permission->id,
    'permissionName' => (string)$permission->name,
    'memberId' => (int)$member->id,
    'memberName' => (string)$member->sca_name,
    'memberEmail' => (string)$member->email_address,
    'adminEmail' => (string)$admin->email_address,
    'branchId' => (int)$branch->id,
    'branchName' => (string)$branch->name,
    'adminId' => (int)$admin->id,
    'recommendationId' => (int)$recommendation->id,
    'searchToken' => $suffix,
    'accessPath' => '/awards/recommendations/member-submitted-recs-grid-data/' . (int)$admin->id . '?search=' . rawurlencode($suffix),
], JSON_THROW_ON_ERROR);
`;

const INSPECT_FIXTURE_PHP = String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

$input = json_decode((string)stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$roleId = (int)($input['roleId'] ?? 0);
$memberId = (int)($input['memberId'] ?? 0);

if ($roleId <= 0 || $memberId <= 0) {
    echo json_encode(['roleFound' => false], JSON_THROW_ON_ERROR);
    return;
}

$locator = \Cake\ORM\TableRegistry::getTableLocator();
$roles = $locator->get('Roles');
$memberRoles = $locator->get('MemberRoles');

$role = $roles->find()
    ->contain(['Permissions'])
    ->where(['Roles.id' => $roleId])
    ->first();

if ($role === null) {
    echo json_encode(['roleFound' => false], JSON_THROW_ON_ERROR);
    return;
}

$currentMemberRole = $memberRoles->find('current')
    ->where([
        'role_id' => $roleId,
        'member_id' => $memberId,
    ])
    ->orderByDesc('id')
    ->first();
$previousMemberRole = $memberRoles->find('previous')
    ->where([
        'role_id' => $roleId,
        'member_id' => $memberId,
    ])
    ->orderByDesc('id')
    ->first();

$mapRole = static function ($memberRole) {
    if ($memberRole === null) {
        return null;
    }

    return [
        'id' => (int)$memberRole->id,
        'startOn' => $memberRole->start_on?->format('Y-m-d H:i:s'),
        'expiresOn' => $memberRole->expires_on?->format('Y-m-d H:i:s'),
        'revokerId' => $memberRole->revoker_id !== null ? (int)$memberRole->revoker_id : null,
        'status' => $memberRole->expires_on === null ? 'current' : 'inactive',
    ];
};

echo json_encode([
    'roleFound' => true,
    'roleId' => (int)$role->id,
    'roleName' => (string)$role->name,
    'permissionNames' => array_values(array_map(static fn ($permission) => (string)$permission->name, $role->permissions ?? [])),
    'currentMemberRole' => $mapRole($currentMemberRole),
    'previousMemberRole' => $mapRole($previousMemberRole),
], JSON_THROW_ON_ERROR);
`;

const resetDevDatabase = () => {
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
    const fixture = page.__rolePermissionFixture;
    if (!fixture) {
        throw new Error('Role permission fixture has not been prepared.');
    }

    return fixture;
};

const refreshFixtureState = (page) => {
    const fixture = ensureFixture(page);
    fixture.state = runPhpJson(INSPECT_FIXTURE_PHP, {
        roleId: fixture.roleId,
        memberId: fixture.memberId,
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

const setRoleMemberSelection = async (modal, fixture) => {
    const picker = modal.locator('[data-role-add-member-target="scaMember"]');
    await picker.evaluate((element, values) => {
        const hiddenId = element.querySelector('input[name="member_id"]');
        const hiddenText = element.querySelector('input[name="sca_name"]');
        const displayInput = element.querySelector('input[type="text"]');

        if (!hiddenId || !hiddenText || !displayInput) {
            throw new Error('Could not find role member autocomplete inputs.');
        }

        hiddenId.value = String(values.memberId);
        hiddenText.value = values.memberName;
        displayInput.value = values.memberName;

        if ('value' in element) {
            element.value = { value: String(values.memberId), text: values.memberName };
        }

        for (const target of [hiddenId, hiddenText, displayInput, element]) {
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }, {
        memberId: fixture.memberId,
        memberName: fixture.memberName,
    });
};

const signOutIfNeeded = async (page) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);

    const signOutButton = getSignOutButton(page);
    if (await isLocatorVisible(signOutButton)) {
        await runAndWaitForNetworkIdle(page, () => signOutButton.click(), 30000);
    }
};

const switchUser = async (page, emailAddress) => {
    await signOutIfNeeded(page);
    await loginAs(page, emailAddress);
};

const openPermissionsTab = async (page) => {
    const tab = page.getByRole('tab', { name: 'Permissions', exact: true });
    await clickTabAndWait(tab, page.locator('#nav-rolePermissions'));
};

const openAssignedMembersTab = async (page) => {
    const tab = page.getByRole('tab', { name: 'Assigned Members', exact: true });
    await clickTabAndWait(tab, page.locator('#nav-assignedMembers'));
};

const openAddPermissionModal = async (page) => {
    await openPermissionsTab(page);
    await page.getByRole('button', { name: 'Add Permission', exact: true }).click();
    const modal = page.locator('#addPermissionModal');
    await expect(modal).toBeVisible({ timeout: 10000 });
    return modal;
};

const openAddMemberModal = async (page) => {
    await openAssignedMembersTab(page);
    await page.getByRole('button', { name: 'Add Member', exact: true }).click();
    const modal = page.locator('#addMemberModal');
    await expect(modal).toBeVisible({ timeout: 10000 });
    return modal;
};

const navigateToFixtureAccessPath = async (page) => {
    const fixture = ensureFixture(page);
    const response = await page.goto(fixture.accessPath, {
        waitUntil: 'domcontentloaded',
        timeout: 30000,
    });
    await waitForPageBody(page);
    expect(response).not.toBeNull();
    return response;
};

Given('I prepare the award recommendation role fixture', async ({ page }) => {
    resetDevDatabase();
    page.__rolePermissionFixture = runPhpJson(SETUP_FIXTURE_PHP, {
        token: `rolepermission${Date.now().toString(36)}seed`,
    });
});

When('I switch to the fixture member account', async ({ page }) => {
    const fixture = ensureFixture(page);
    await switchUser(page, fixture.memberEmail);
});

When('I switch to the fixture admin account', async ({ page }) => {
    const fixture = ensureFixture(page);
    await switchUser(page, fixture.adminEmail);
});

When('I create the prepared access role', async ({ page }) => {
    const fixture = ensureFixture(page);
    await page.goto('/roles/add', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
    await page.getByLabel('Name', { exact: true }).fill(fixture.roleName);

    await Promise.all([
        page.waitForURL(/\/roles\/view\/\d+/, { timeout: 30000 }),
        page.getByRole('button', { name: /^Submit$/ }).click(),
    ]);
    await waitForPageBody(page);

    const roleIdMatch = page.url().match(/\/roles\/view\/(\d+)/);
    expect(roleIdMatch).not.toBeNull();
    fixture.roleId = Number(roleIdMatch[1]);

    await expect(page.locator('body')).toContainText(fixture.roleName);
    await expect(page.getByRole('alert').first()).toContainText('The role has been saved.', { timeout: 15000 });
});

When('I rename the prepared access role', async ({ page }) => {
    const fixture = ensureFixture(page);
    await page.getByRole('button', { name: 'Edit', exact: true }).click();
    const modal = page.locator('#editModal');
    await expect(modal).toBeVisible({ timeout: 10000 });
    await modal.getByLabel('Name', { exact: true }).fill(fixture.editedRoleName);

    await Promise.all([
        page.waitForLoadState('networkidle'),
        modal.getByRole('button', { name: /^Submit$/ }).click(),
    ]);

    await expect(page.getByRole('alert').first()).toContainText('The role has been saved.', { timeout: 15000 });
    await expect(page.locator('body')).toContainText(fixture.editedRoleName);
    refreshFixtureState(page);
});

When('the add permission modal should require a selection before submit', async ({ page }) => {
    const modal = await openAddPermissionModal(page);
    await expect(modal.getByRole('button', { name: /^Submit$/ })).toBeDisabled();
    page.__rolePermissionModal = modal;
});

When('I add the fixture award recommendation permission to the role', async ({ page }) => {
    const fixture = ensureFixture(page);
    const existingModal = page.__rolePermissionModal;
    const modal = existingModal && await existingModal.isVisible()
        ? existingModal
        : await openAddPermissionModal(page);

    await chooseAutocompleteOption(modal, 'Permission', fixture.permissionName, fixture.permissionName);
    const submitButton = modal.getByRole('button', { name: /^Submit$/ });
    await expect(submitButton).toBeEnabled({ timeout: 15000 });

    await Promise.all([
        page.waitForLoadState('networkidle'),
        submitButton.click(),
    ]);

    await expect(page.getByRole('alert').first()).toContainText('The permission has been added to the role.', { timeout: 15000 });
    await openPermissionsTab(page);
    await expect(page.locator('#nav-rolePermissions')).toContainText(fixture.permissionName);
});

When('the add member modal should require a member before submit', async ({ page }) => {
    const modal = await openAddMemberModal(page);
    await expect(modal.getByRole('button', { name: /^Submit$/ })).toBeDisabled();
    page.__roleMemberModal = modal;
});

When('I assign the prepared member to the role', async ({ page }) => {
    const fixture = ensureFixture(page);
    const existingModal = page.__roleMemberModal;
    const modal = existingModal && await existingModal.isVisible()
        ? existingModal
        : await openAddMemberModal(page);

    await setRoleMemberSelection(modal, fixture);
    const branchSelect = modal.getByLabel('Branch', { exact: true });
    if (await branchSelect.count() > 0) {
        await branchSelect.selectOption(String(fixture.branchId));
    }
    const submitButton = modal.getByRole('button', { name: /^Submit$/ });
    await expect(submitButton).toBeEnabled({ timeout: 15000 });

    await Promise.all([
        page.waitForLoadState('networkidle'),
        submitButton.click(),
    ]);

    await expect(page.getByRole('alert').first()).toContainText('The Member role has been saved.', { timeout: 15000 });
    await openAssignedMembersTab(page);
    await expect(page.locator('#nav-assignedMembers')).toContainText(fixture.memberName);
});

Then('the role fixture should show the member has the permission-granting role', async ({ page }) => {
    const fixture = ensureFixture(page);
    const state = await waitForFixtureState(
        page,
        (current) => Boolean(
            current.roleFound
            && current.roleName === fixture.editedRoleName
            && Array.isArray(current.permissionNames)
            && current.permissionNames.includes(fixture.permissionName)
            && current.currentMemberRole,
        ),
        'Expected the prepared role to have the permission and an active direct grant.',
    );

    expect(state.currentMemberRole.revokerId).toBeNull();
    expect(state.currentMemberRole.expiresOn).toBeNull();
});

Then('awards recommendation access should be denied', async ({ page }) => {
    await navigateToFixtureAccessPath(page);
    await expect(page.locator('body')).toContainText(/unauthorized request|redirecting you to your profile/i);
});

Then('awards recommendation access should be allowed', async ({ page }) => {
    const fixture = ensureFixture(page);
    await navigateToFixtureAccessPath(page);
    await expect(page.locator('body')).not.toContainText(/unauthorized request/i);
    await expect(page.locator('body')).toContainText(fixture.searchToken, { timeout: 15000 });
});

When('I deactivate the prepared member role assignment', async ({ page }) => {
    const fixture = ensureFixture(page);
    await page.goto(`/roles/view/${fixture.roleId}`, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
    await openAssignedMembersTab(page);

    const row = page.locator('table tbody tr').filter({ hasText: fixture.memberName }).first();
    await expect(row).toBeVisible({ timeout: 15000 });

    await row.locator('a,button').filter({ hasText: /^Deactivate$/ }).first().click();

    const confirmDialog = page.getByRole('dialog', { name: 'Confirm action' });
    await expect(confirmDialog).toBeVisible({ timeout: 10000 });
    await Promise.all([
        page.waitForLoadState('networkidle'),
        confirmDialog.getByRole('button', { name: 'Confirm', exact: true }).click(),
    ]);

    await expect(page.locator('table tbody tr').filter({ hasText: fixture.memberName })).toHaveCount(0, { timeout: 15000 });
});

Then('the role fixture should show the member assignment is deactivated', async ({ page }) => {
    const state = await waitForFixtureState(
        page,
        (current) => Boolean(current.roleFound && !current.currentMemberRole && current.previousMemberRole),
        'Expected the member role direct grant to be deactivated.',
    );

    expect(state.previousMemberRole.revokerId).toBeGreaterThan(0);
    expect(state.previousMemberRole.expiresOn).toBeTruthy();
});
