/** Local seeded tenants only. Synthetic fixture writes are scoped and cleaned after failures. */
const { expect } = require('@playwright/test');
const { randomInt } = require('node:crypto');
const AxeBuilder = require('@axe-core/playwright').default;
const { createTenantContext } = require('./tenant-context.cjs');
const { loginAs, runPhpJson } = require('./ui-helpers.cjs');

const fixturePhp = String.raw`require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$slug = $input['tenant'];
if (!in_array($slug, ['kmp', 'kmp2'], true)) throw new \RuntimeException('Seeded local tenants only');
$tenant = (new \App\Services\Platform\TenantHostResolver())->resolve($slug . '.localhost');
if (!$tenant || !in_array($tenant->dbServer, ['db', 'postgres', 'localhost', '127.0.0.1'], true)) {
    throw new \RuntimeException('Local tenant required');
}
$manager = new \App\Services\TenantConnectionManager(\App\Services\Secrets\SecretStoreFactory::fromConfig());
echo json_encode($manager->withTenant($tenant, function () use ($input, $slug) {
    $l = \Cake\ORM\TableRegistry::getTableLocator();
    if (isset($input['reassign'])) {
        $a = $l->get('WorkflowApprovals')->get($input['reassign']['id']);
        if ($a->request_title !== $input['reassign']['title']) throw new \RuntimeException('Fixture mismatch');
        $a->approver_config = ['member_id' => $input['reassign']['memberId']];
        $l->get('WorkflowApprovals')->saveOrFail($a);
        return ['updated' => true];
    }
    if (isset($input['cleanup'])) {
        $f = $input['cleanup'];
        $d = $l->get('WorkflowDefinitions')->get($f['definitionId']);
        if ($d->slug !== $f['slug']) throw new \RuntimeException('Fixture mismatch');
        $l->get('WorkflowApprovalResponses')->deleteAll(['workflow_approval_id' => $f['approvalId']]);
        $l->get('WorkflowApprovalTriageStates')->deleteAll(['workflow_approval_id' => $f['approvalId']]);
        $l->get('WorkflowApprovals')->deleteAll(['id' => $f['approvalId']]);
        $l->get('WorkflowExecutionLogs')->deleteAll(['workflow_instance_id' => $f['instanceId']]);
        $l->get('WorkflowInstances')->deleteAll(['id' => $f['instanceId']]);
        $l->get('WorkflowVersions')->deleteAll(['workflow_definition_id' => $f['definitionId']]);
        $l->get('WorkflowDefinitions')->deleteOrFail($d);
        foreach ($f['members'] as $m) {
            $l->get('Members')->deleteAll(['id' => $m['id'], 'email_address' => $m['email']]);
        }
        return ['cleaned' => true];
    }
    return $l->get('Members')->getConnection()->transactional(function () use ($l, $slug, $input) {
        $tag = 'sec-' . bin2hex(random_bytes(4));
        $members = $l->get('Members');
        $admin = $members->find()->where(['email_address' => $slug === 'kmp' ? 'admin@amp.ansteorra.org' : 'admin@amp2demo.com'])->firstOrFail();
        $users = [];
        foreach (['assigned', 'unrelated', 'replacement'] as $purpose) {
            $m = $members->newEntity([
                'sca_name' => $tag . ' ' . $purpose, 'first_name' => 'Synthetic', 'last_name' => $purpose,
                'email_address' => $tag . '-' . $purpose . '@example.test', 'password' => 'TestPassword',
                'birth_month' => 1, 'birth_year' => 1990, 'branch_id' => $admin->branch_id,
                'street_address' => '', 'city' => '', 'state' => '', 'zip' => '', 'phone_number' => '',
                'created_by' => $admin->id,
            ]);
            $m->set('status', 'verified', ['guard' => false]);
            $members->saveOrFail($m);
            $users[$purpose] = ['id' => (int)$m->id, 'email' => $m->email_address];
        }
        $d = $l->get('WorkflowDefinitions')->saveOrFail($l->get('WorkflowDefinitions')->newEntity([
            'name' => $tag, 'slug' => $tag, 'trigger_type' => 'manual',
        ]));
        $v = $l->get('WorkflowVersions')->saveOrFail($l->get('WorkflowVersions')->newEntity([
            'workflow_definition_id' => $d->id, 'version_number' => 1, 'definition' => ['nodes' => []], 'status' => 'published',
        ]));
        $i = $l->get('WorkflowInstances')->saveOrFail($l->get('WorkflowInstances')->newEntity([
            'workflow_definition_id' => $d->id, 'workflow_version_id' => $v->id, 'status' => 'waiting', 'context' => [],
        ]));
        $log = $l->get('WorkflowExecutionLogs')->saveOrFail($l->get('WorkflowExecutionLogs')->newEntity([
            'workflow_instance_id' => $i->id, 'node_id' => 'approval', 'node_type' => 'approval', 'status' => 'waiting',
        ]));
        $a = $l->get('WorkflowApprovals')->newEntity([
            'workflow_instance_id' => $i->id, 'node_id' => 'approval', 'execution_log_id' => $log->id,
            'approver_type' => 'member', 'approver_config' => ['member_id' => $users['assigned']['id']],
            'request_title' => $tag, 'required_count' => 2, 'approved_count' => 1, 'rejected_count' => 0,
            'status' => 'pending', 'allow_parallel' => true, 'version' => 1,
        ]);
        $a->set('id', $input['collisionId'], ['guard' => false]);
        $l->get('WorkflowApprovals')->saveOrFail($a);
        $l->get('WorkflowApprovalResponses')->saveOrFail($l->get('WorkflowApprovalResponses')->newEntity([
            'workflow_approval_id' => $a->id, 'member_id' => $admin->id, 'decision' => 'approved',
            'comment' => $tag, 'responded_at' => \Cake\I18n\DateTime::now(),
        ]));
        return ['slug' => $tag, 'title' => $tag, 'definitionId' => $d->id, 'instanceId' => $i->id,
            'approvalId' => (int)$a->id, 'members' => $users];
    });
}), JSON_THROW_ON_ERROR);`;

async function runSecurityBoundaries(browser) {
    const fixtures = [];
    const contexts = [];
    const errors = [];
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const session = async (tenant, email) => {
        const { context } = await createTenantContext(browser, { tenant });
        contexts.push(context);
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await loginAs(page, email);
        return page;
    };
    const denied = async (page, path, status = 403) => {
        const response = await page.request.get(path, { headers });
        expect(response.status()).toBe(status);
        const body = await response.text();
        for (const { fixture } of fixtures) {
            expect(body).not.toContain(fixture.title);
            expect(body).not.toContain(fixture.members.assigned.email);
        }
        if (status === 403) expect(await response.json()).toEqual({ error: 'Approval access denied.' });
    };
    const collisionId = randomInt(1000000000, 1500000000);
    try {
        for (const tenant of ['kmp', 'kmp2']) {
            const fixture = runPhpJson(fixturePhp, { tenant, collisionId });
            fixtures.push({ tenant, fixture });
            const assigned = await session(tenant, fixture.members.assigned.email);
            const unrelated = await session(tenant, fixture.members.unrelated.email);
            const detail = '/approvals/detail/' + fixture.approvalId;
            const candidates = '/approvals/eligible-approvers/' + fixture.approvalId;
            const allowed = await assigned.request.get(detail, { headers });
            expect(allowed.status()).toBe(200);
            expect((await allowed.json()).approvalId).toBe(fixture.approvalId);
            expect((await assigned.request.get(candidates, { headers })).status()).toBe(200);
            await denied(assigned, candidates + '?context=reassign');
            await denied(unrelated, detail);
            await denied(unrelated, candidates);
            await denied(unrelated, candidates + '?context=reassign');

            // A GET must preserve authentication; only explicit CSRF-protected POST signs out.
            await assigned.goto('/members/logout');
            const confirmation = assigned.locator('section[aria-labelledby="sign-out-heading"]');
            await expect(confirmation.getByRole('button', { name: 'Sign out', exact: true })).toBeVisible();
            expect((await assigned.request.get(detail, { headers })).status()).toBe(200);
            const scan = await new AxeBuilder({ page: assigned }).include('section[aria-labelledby="sign-out-heading"]').analyze();
            expect(scan.violations.map(v => v.id)).toEqual([]);
            await confirmation.getByRole('button', { name: 'Sign out', exact: true }).focus();
            await assigned.keyboard.press('Enter');
            await expect(assigned).toHaveURL(/\/members\/login/);
            await expect(assigned.locator('#email-address')).toBeVisible();
            await loginAs(assigned, fixture.members.assigned.email);

            runPhpJson(fixturePhp, { tenant, reassign: { id: fixture.approvalId, title: fixture.title, memberId: fixture.members.replacement.id } });
            await denied(assigned, detail);
            await denied(assigned, candidates);
            const replacement = await session(tenant, fixture.members.replacement.email);
            expect((await replacement.request.get(detail, { headers })).status()).toBe(200);
            const admin = await session(tenant, tenant === 'kmp' ? 'admin@amp.ansteorra.org' : 'admin@amp2demo.com');
            expect((await admin.request.get(candidates + '?context=reassign', { headers })).status()).toBe(200);
            fixtures.at(-1).page = replacement;
        }
        // The same numeric ID exists in both databases with a different private comment.
        for (const { tenant, fixture, page } of fixtures) {
            const foreign = fixtures.find(entry => entry.tenant !== tenant).fixture;
            const response = await page.request.get('/approvals/detail/' + collisionId, { headers });
            expect(response.status()).toBe(200);
            const body = await response.text();
            expect(body).toContain(fixture.title);
            expect(body).not.toContain(foreign.title);
        }
        expect(errors).toEqual([]);
        return 'PASS: two-tenant approval authorization, candidate restrictions, reassignment revocation, GET/POST logout, keyboard confirmation, and axe.';
    } finally {
        try {
            for (const context of contexts.reverse()) await context.close();
        } finally {
            for (const { tenant, fixture } of fixtures.reverse()) runPhpJson(fixturePhp, { tenant, cleanup: fixture });
        }
    }
}

const mailFixturePhp = String.raw`require 'vendor/autoload.php';
require 'config/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$local = (new \App\Services\Platform\TenantHostResolver())->resolve('kmp.localhost');
if (!$local || !in_array($local->dbServer, ['db', 'postgres', 'localhost', '127.0.0.1'], true)) {
    throw new \RuntimeException('Local seeded stack required');
}
$p = \Cake\Datasource\ConnectionManager::get('platform');
if (isset($input['fixture']['adminId'])) {
    $admin = $p->execute('SELECT * FROM platform_users WHERE id = :id', ['id' => $input['fixture']['adminId']])->fetch('assoc');
} else {
    $admin = $p->execute('SELECT * FROM platform_users WHERE email = :email', ['email' => 'admin@example.org'])->fetch('assoc');
}
if (!$admin) throw new \RuntimeException('Seeded administrator required');
if (isset($input['code'])) {
    $secret = \App\Services\Secrets\SecretStoreFactory::fromConfig()->get($admin['totp_secret_ref']);
    $now = time();
    $config = (array)\Cake\Core\Configure::read('Platform.adminMfa');
    $period = (int)($config['period'] ?? 30);
    $verifier = new \App\Services\Platform\PlatformTotpVerifier(
        \App\Services\Secrets\SecretStoreFactory::fromConfig(), (int)($config['window'] ?? 1),
        $period, (int)($config['digits'] ?? 6), (string)($config['algorithm'] ?? 'sha1'),
    );
    echo json_encode(['code' => $verifier->codeForTimestamp($secret, $now), 'counter' => intdiv($now, $period),
        'lastCounter' => $admin['last_accepted_totp_counter'] === null ? null : (int)$admin['last_accepted_totp_counter']]);
    return;
}
if (isset($input['fixture'])) {
    $f = $input['fixture'];
    $row = $p->execute('SELECT * FROM tenants WHERE id = :id AND slug = :slug', ['id' => $f['id'], 'slug' => $f['slug']])->fetch('assoc');
    if (!$row) throw new \RuntimeException('Fixture mismatch');
    if (isset($input['cleanup'])) {
        // Retain immutable audit subjects; this registry-only fixture never provisions a database or host.
        $p->update('tenants', ['status' => 'archived', 'tenant_config' => '{}'], ['id' => $f['id']]);
        foreach ([$f['adminId'], $f['lockAdminId']] as $userId) {
            $p->update('platform_users', ['status' => 'disabled', 'auth_version' => bin2hex(random_bytes(16))], ['id' => $userId]);
        }
        \App\Services\Platform\TenantHostResolver::clearCache();
        echo json_encode(['cleaned' => true]);
        return;
    }
    $lockedActor = $p->execute('SELECT failed_login_count, locked_until FROM platform_users WHERE id = :id', ['id' => $f['lockAdminId']])->fetch('assoc');
    echo json_encode(['config' => json_decode($row['tenant_config'], true),
        'locked' => !empty($lockedActor['locked_until']) && strtotime($lockedActor['locked_until']) > time(),
        'failures' => (int)$lockedActor['failed_login_count'],
        'audits' => $p->execute('SELECT reason FROM audit_events WHERE subject_id = :id AND action = :action',
            ['id' => $f['id'], 'action' => 'tenant.config.updated'])->fetchAll('assoc')]);
    return;
}
$id = \Cake\Utility\Text::uuid();
$slug = 'sec-mail-' . bin2hex(random_bytes(4));
$p->insert('tenants', ['id' => $id, 'slug' => $slug, 'display_name' => 'Synthetic mail boundary',
    'status' => 'provisioning', 'db_server' => $local->dbServer, 'db_name' => str_replace('-', '_', $slug),
    'db_role' => $local->dbRole, 'tenant_config' => '{}', 'created_at' => gmdate('Y-m-d H:i:s')]);
// Each run has its own actor and MFA counter; never reset a real actor's replay protection.
$admin['id'] = \Cake\Utility\Text::uuid();
$admin['email'] = $slug . '@example.test';
$admin['password_hash'] = password_hash('TestPassword', PASSWORD_DEFAULT);
$admin['failed_login_count'] = 0;
$admin['locked_until'] = null;
$admin['last_accepted_totp_counter'] = null;
$admin['auth_version'] = bin2hex(random_bytes(16));
$p->insert('platform_users', $admin);
$lockActor = $admin;
$lockActor['id'] = \Cake\Utility\Text::uuid();
$lockActor['email'] = $slug . '-lock@example.test';
$p->insert('platform_users', $lockActor);
echo json_encode(['id' => $id, 'slug' => $slug, 'adminId' => $admin['id'], 'email' => $admin['email'],
    'lockAdminId' => $lockActor['id'], 'lockEmail' => $lockActor['email'],
    'loginFailure' => \Cake\Core\Configure::read('debug') && \Cake\Core\Configure::read('Platform.adminPortal.detailedLoginErrors')
        ? 'Platform admin password verification failed.' : 'Platform admin login failed.']);`;

async function runMailSecurity(browser) {
    const fixture = runPhpJson(mailFixturePhp);
    const { context } = await createTenantContext(browser, { tenant: 'platform.kmp' });
    const page = await context.newPage();
    const freshCode = async () => {
        let candidate;
        await expect.poll(() => {
            candidate = runPhpJson(mailFixturePhp, { code: true, fixture });
            return candidate.lastCounter === null || candidate.counter > candidate.lastCounter;
        }, { timeout: 65000, intervals: [1000, 2000, 5000] }).toBe(true);
        return candidate.code;
    };
    const inspect = () => runPhpJson(mailFixturePhp, { fixture });
    try {
        await page.goto('/platform-admin/login');
        await page.getByLabel('Email').fill(fixture.email);
        await page.getByLabel('Password', { exact: true }).fill('TestPassword');
        const loginCode = await freshCode();
        await page.getByLabel('MFA code').fill(loginCode);
        await page.getByRole('button', { name: /Sign in/i }).click();
        await expect(page).toHaveURL(/\/platform-admin\/?$/);
        const configPath = '/platform-admin/tenants/' + fixture.slug + '/config';
        await page.goto(configPath);
        await page.getByLabel('Email mode', { exact: true }).selectOption('sendgrid');
        await page.getByLabel('SendGrid or Resend API key secret reference', { exact: true }).fill('tenant.other.email-api-key');
        await page.getByRole('button', { name: 'Save configuration', exact: true }).click();
        await expect(page.locator('main')).toContainText('approved email secret reference');
        expect(inspect().config).toEqual([]);
        await page.getByLabel('SendGrid or Resend API key secret reference', { exact: true }).fill('tenant.' + fixture.slug + '.email-api-key');
        await page.getByRole('button', { name: 'Save configuration', exact: true }).click();
        await expect(page.locator('main')).toContainText('EMAIL ' + fixture.slug);
        expect(inspect().config).toEqual([]);
        await page.getByLabel('Email change confirmation', { exact: true }).fill('EMAIL ' + fixture.slug);
        await page.getByLabel('Reason for email delivery change', { exact: true }).fill('Synthetic email authorization regression');
        await page.getByLabel('Fresh authenticator code for email changes', { exact: true }).fill(loginCode);
        await page.getByRole('button', { name: 'Save configuration', exact: true }).click();
        await expect(page.locator('main')).toContainText('MFA code was not valid');
        expect(inspect().config).toEqual([]);
        for (const viewport of [{ width: 1280, height: 740 }, { width: 320, height: 640 }, { width: 1280, height: 500 }]) {
            await page.setViewportSize(viewport);
            const scan = await new AxeBuilder({ page }).include('main').analyze();
            expect(scan.violations.map(v => v.id)).toEqual([]);
        }
        const code = await freshCode();
        const mfa = page.getByLabel('Fresh authenticator code for email changes', { exact: true });
        await mfa.fill(code);
        await mfa.focus();
        await page.keyboard.press('Enter');
        await expect(page).not.toHaveURL(new RegExp('/config$'));
        const after = inspect();
        expect(after.config.email.api_secret_ref).toBe('tenant.' + fixture.slug + '.email-api-key');
        expect(after.audits).toContainEqual({ reason: 'Synthetic email authorization regression' });
        await page.goto(configPath);
        await page.getByLabel('Sender display name', { exact: true }).fill('Replay must fail');
        await page.getByLabel('Email change confirmation', { exact: true }).fill('EMAIL ' + fixture.slug);
        await page.getByLabel('Reason for email delivery change', { exact: true }).fill('Synthetic replay denial regression');
        await page.getByLabel('Fresh authenticator code for email changes', { exact: true }).fill(code);
        await page.getByRole('button', { name: 'Save configuration', exact: true }).click();
        await expect(page.locator('main')).toContainText('MFA code was not valid');
        expect(inspect().config).toEqual(after.config);
        // Exercise the atomic lockout SQL against the real PostgreSQL-backed portal.
        // A separate actor starts with no successful login reserved in its five-attempt bucket.
        for (let attempt = 0; attempt < 5; attempt++) {
            await page.goto('/platform-admin/login');
            await page.getByLabel('Email').fill(fixture.lockEmail);
            await page.getByLabel('Password', { exact: true }).fill('incorrect');
            await page.getByLabel('MFA code').fill(loginCode);
            await page.getByRole('button', { name: /Sign in/i }).click();
            await expect(page.locator('#flash-messages')).toContainText(fixture.loginFailure);
        }
        expect(inspect()).toMatchObject({ failures: 5, locked: true });
        await page.goto('/platform-admin/logout');
        await expect(page.locator('section[aria-labelledby="sign-out-heading"]')).toBeVisible();
        await page.locator('section[aria-labelledby="sign-out-heading"]').getByRole('button', { name: 'Sign out', exact: true }).click();
        await expect(page).toHaveURL(/\/platform-admin\/login/);
        return 'PASS: Platform Admin email secret boundaries, fresh/replayed MFA, audit reason, PostgreSQL lockout, safe Platform Admin logout, keyboard submission, and desktop/mobile/short-window axe.';
    } finally {
        try {
            await context.close();
        } finally {
            runPhpJson(mailFixturePhp, { fixture, cleanup: true });
        }
    }
}

module.exports = { runSecurityBoundaries, runMailSecurity };
