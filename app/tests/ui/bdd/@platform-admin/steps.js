const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');

const { createTenantContext } = require('../../support/tenant-context.cjs');
const {
    APP_ROOT,
    getAppContainerName,
    getUiTestEnvironment,
    shouldUseDockerPhp,
} = require('../../support/test-environment.cjs');
const {
    flushWorkflowsAndQueue,
    mailpitSearchTotal,
    runPhpJson,
    waitForPageBody,
    waitForQueueSettled,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then, After } = createBdd();

const STATE_KEY = '__platformTenantProvisioning';
const PLATFORM_HOST = 'platform.kmp.localhost';
const ADMIN_EMAIL = 'admin@example.org';
const ADMIN_PASSWORD = 'TestPassword';
const ADMIN_TOTP_SECRET = 'JBSWY3DPEHPK3PXP';
const ADMIN_USER_ID = '11111111-1111-4111-8111-111111111111';
const INITIAL_SUPER_USER_EMAIL = 'superuser@kmp2.localhost';

const stateFor = (page) => {
    if (!page[STATE_KEY]) {
        page[STATE_KEY] = {
            platformContext: null,
            platformPage: null,
            tenantContexts: [],
            lastProvisioningJob: null,
            lastRunnerOutput: null,
        };
    }

    return page[STATE_KEY];
};

const platformContextOptions = () => {
    const { baseUrl, hostHeader } = getUiTestEnvironment();
    if (hostHeader) {
        return { baseURL: baseUrl, extraHTTPHeaders: { Host: PLATFORM_HOST } };
    }

    const url = new URL(baseUrl);
    url.hostname = PLATFORM_HOST;

    return { baseURL: url.toString().replace(/\/+$/, '') };
};

const runCake = (args, { timeoutMs = 180000 } = {}) => {
    const useDocker = shouldUseDockerPhp();
    const file = useDocker ? 'docker' : 'bin/cake';
    const commandArgs = useDocker
        ? ['exec', '-w', '/var/www/html', getAppContainerName(), 'bin/cake', ...args]
        : args;

    return execFileSync(file, commandArgs, {
        cwd: APP_ROOT,
        env: process.env,
        stdio: ['ignore', 'pipe', 'pipe'],
        timeout: timeoutMs,
        encoding: 'utf8',
        maxBuffer: 32 * 1024 * 1024,
    });
};

const fixtureScript = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

use App\\Services\\Platform\\PlatformTotpVerifier;
use App\\Services\\Platform\\TenantHostResolver;
use App\\Services\\Secrets\\SecretStoreFactory;
use App\\Services\\Secrets\\SensitiveString;
use App\\Services\\Secrets\\WritableSecretStoreInterface;
use Cake\\Datasource\\ConnectionManager;

$input = json_decode(stream_get_contents(STDIN), true) ?: [];
$slug = strtolower((string)($input['slug'] ?? 'kmp2'));
$host = $slug . '.localhost';
$dbNames = [$slug . '_dev', 'kmp_tenant_' . str_replace('-', '_', $slug)];
$role = 'kmp_tenant_' . str_replace('-', '_', $slug) . '_role';
$totpSecret = (string)($input['totpSecret'] ?? '');
$totpSecretRef = 'platform.admin.e2e.totp';
$now = gmdate('Y-m-d H:i:s');

$platform = ConnectionManager::get('platform');
$store = SecretStoreFactory::fromConfig();
if (!$store instanceof WritableSecretStoreInterface) {
    throw new RuntimeException('The configured secret store is not writable.');
}
$store->put($totpSecretRef, new SensitiveString($totpSecret));

$platform->execute(
    "INSERT INTO platform_users (
        id, email, password_hash, status, totp_secret_ref, totp_enrolled_at,
        failed_login_count, locked_until, last_login_at, created_at, modified_at
    ) VALUES (
        :id, :email, :password_hash, 'active', :totp_secret_ref, :now,
        0, NULL, NULL, :now, :now
    )
    ON CONFLICT (email) DO UPDATE SET
        password_hash = EXCLUDED.password_hash,
        status = 'active',
        totp_secret_ref = EXCLUDED.totp_secret_ref,
        totp_enrolled_at = EXCLUDED.totp_enrolled_at,
        failed_login_count = 0,
        locked_until = NULL,
        modified_at = EXCLUDED.modified_at",
    [
        'id' => '${ADMIN_USER_ID}',
        'email' => 'admin@example.org',
        'password_hash' => password_hash('TestPassword', PASSWORD_DEFAULT),
        'totp_secret_ref' => $totpSecretRef,
        'now' => $now,
    ],
);

$tenantIds = $platform
    ->execute('SELECT id FROM tenants WHERE slug = :slug', ['slug' => $slug])
    ->fetchAll('assoc');
$ids = array_map(static fn(array $row): string => (string)$row['id'], $tenantIds);
if ($ids !== []) {
    foreach ($ids as $tenantId) {
        $platform->execute('DELETE FROM platform_jobs WHERE tenant_id = :tenantId', ['tenantId' => $tenantId]);
        $platform->execute('DELETE FROM tenant_backups WHERE tenant_id = :tenantId', ['tenantId' => $tenantId]);
        $platform->execute('DELETE FROM tenant_hosts WHERE tenant_id = :tenantId', ['tenantId' => $tenantId]);
    }
    $platform->execute('DELETE FROM tenants WHERE slug = :slug', ['slug' => $slug]);
}
$platform->execute(
    'DELETE FROM tenant_hosts WHERE host_normalized = :host OR host = :host',
    ['host' => $host],
);
$platform->execute(
    "DELETE FROM platform_jobs WHERE parameters::text ILIKE :slug OR idempotency_key ILIKE :slug",
    ['slug' => '%' . $slug . '%'],
);

foreach ($dbNames as $dbName) {
    $platform->execute('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ?', [$dbName]);
    $platform->execute(sprintf('DROP DATABASE IF EXISTS %s', $platform->getDriver()->quoteIdentifier($dbName)));
}
$platform->execute(sprintf('DROP ROLE IF EXISTS %s', $platform->getDriver()->quoteIdentifier($role)));
TenantHostResolver::clearCache();

$totp = new PlatformTotpVerifier($store);
echo json_encode([
    'totp' => $totp->codeForTimestamp($totpSecret, time()),
    'slug' => $slug,
    'host' => $host,
]);
`;

const tenantStatusScript = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

use App\\Services\\Secrets\\SecretStoreFactory;
use Cake\\Datasource\\ConnectionManager;

$input = json_decode(stream_get_contents(STDIN), true) ?: [];
$slug = strtolower((string)($input['slug'] ?? ''));
$platform = ConnectionManager::get('platform');
$row = $platform->execute(
    "SELECT
        t.id,
        t.slug,
        t.status AS tenant_status,
        t.schema_version,
        t.db_server,
        t.db_name,
        t.db_role,
        h.host,
        j.id AS job_id,
        j.status AS job_status,
        j.parameters,
        j.last_error
    FROM tenants t
    LEFT JOIN tenant_hosts h ON h.tenant_id = t.id AND h.is_primary = TRUE
    LEFT JOIN LATERAL (
        SELECT id, status, parameters, last_error
        FROM platform_jobs
        WHERE tenant_id = t.id AND job_type = 'tenant_provision'
        ORDER BY created_at DESC
        LIMIT 1
    ) j ON TRUE
    WHERE t.slug = :slug
    LIMIT 1",
    ['slug' => $slug],
)->fetch('assoc') ?: [];

$dbExists = false;
$membersTableExists = false;
$initialSuperUser = null;
if (!empty($row['db_name'])) {
    $dbExists = (bool)$platform
        ->execute('SELECT 1 FROM pg_database WHERE datname = ?', [$row['db_name']])
        ->fetchColumn(0);
    if ($dbExists) {
        $base = ConnectionManager::getConfig('default');
        unset($base['url']);
        $connectionName = 'platform_e2e_probe_' . preg_replace('/[^a-z0-9_]/', '_', $slug);
        if (in_array($connectionName, ConnectionManager::configured(), true)) {
            ConnectionManager::drop($connectionName);
        }
        ConnectionManager::setConfig($connectionName, array_merge($base, [
            'host' => $row['db_server'] ?? 'db',
            'database' => $row['db_name'],
            'username' => $row['db_role'],
            'password' => SecretStoreFactory::fromConfig()->get('tenant.' . $slug . '.db.password')?->reveal(),
        ]));
        $tenantConnection = ConnectionManager::get($connectionName);
        $membersTableExists = in_array('members', $tenantConnection->getSchemaCollection()->listTables(), true);
        if ($membersTableExists) {
            $initialSuperUser = $tenantConnection->execute(
                "SELECT
                    m.email_address,
                    m.status,
                    mb.name AS member_branch,
                    COUNT(CASE WHEN rb.name = 'Kingdom' THEN 1 END) AS kingdom_role_scopes,
                    COUNT(CASE WHEN r.name = 'Super User' THEN 1 END) AS super_user_roles,
                    COUNT(CASE WHEN p.is_super_user = TRUE THEN 1 END) AS super_user_permissions,
                    COUNT(CASE WHEN p.is_super_user = TRUE AND p.requires_warrant = FALSE THEN 1 END)
                        AS unwarranted_super_user_permissions
                FROM members m
                LEFT JOIN branches mb ON mb.id = m.branch_id
                LEFT JOIN member_roles mr ON mr.member_id = m.id AND mr.revoker_id IS NULL
                LEFT JOIN roles r ON r.id = mr.role_id
                LEFT JOIN branches rb ON rb.id = mr.branch_id
                LEFT JOIN roles_permissions rp ON rp.role_id = mr.role_id
                LEFT JOIN permissions p ON p.id = rp.permission_id
                WHERE m.email_address = :email
                GROUP BY m.email_address, m.status, mb.name
                LIMIT 1",
                ['email' => $input['initialSuperUserEmail'] ?? 'superuser@kmp2.localhost'],
            )->fetch('assoc') ?: null;
        }
        ConnectionManager::drop($connectionName);
    }
}

echo json_encode($row + [
    'db_exists' => $dbExists,
    'members_table_exists' => $membersTableExists,
    'initial_super_user' => $initialSuperUser,
]);
`;

const tenantMemberScript = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

use App\\Services\\Secrets\\SecretStoreFactory;
use Cake\\Datasource\\ConnectionManager;

$input = json_decode(stream_get_contents(STDIN), true) ?: [];
$slug = strtolower((string)($input['slug'] ?? ''));
$email = strtolower((string)($input['email'] ?? ''));
$platform = ConnectionManager::get('platform');
$tenant = $platform->execute(
    'SELECT db_server, db_name, db_role FROM tenants WHERE slug = :slug LIMIT 1',
    ['slug' => $slug],
)->fetch('assoc') ?: [];
if ($tenant === []) {
    throw new RuntimeException('Tenant not found.');
}

$base = ConnectionManager::getConfig('default');
unset($base['url']);
$connectionName = 'platform_e2e_member_' . preg_replace('/[^a-z0-9_]/', '_', $slug);
if (in_array($connectionName, ConnectionManager::configured(), true)) {
    ConnectionManager::drop($connectionName);
}
ConnectionManager::setConfig($connectionName, array_merge($base, [
    'host' => $tenant['db_server'] ?? 'db',
    'database' => $tenant['db_name'],
    'username' => $tenant['db_role'],
    'password' => SecretStoreFactory::fromConfig()->get('tenant.' . $slug . '.db.password')?->reveal(),
]));
$tenantConnection = ConnectionManager::get($connectionName);
$member = $tenantConnection->execute(
    'SELECT email_address, status, password_token, password_token_expires_on FROM members WHERE email_address = :email LIMIT 1',
    ['email' => $email],
)->fetch('assoc') ?: null;
ConnectionManager::drop($connectionName);

echo json_encode(['member' => $member]);
`;

Given('the {string} tenant has been removed from the platform registry', async ({ page }, slug) => {
    const setup = runPhpJson(fixtureScript, { slug, totpSecret: ADMIN_TOTP_SECRET }, { timeoutMs: 120000 });
    stateFor(page).totp = setup.totp;
});

Given('I am logged into the Platform Admin portal', async ({ page, browser }) => {
    const state = stateFor(page);
    state.platformContext = await browser.newContext({
        ...platformContextOptions(),
        ignoreHTTPSErrors: true,
    });
    state.platformPage = await state.platformContext.newPage();

    await state.platformPage.goto('/platform-admin/login', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(state.platformPage);
    await state.platformPage.getByLabel('Email').fill(ADMIN_EMAIL);
    await state.platformPage.getByLabel('Password').fill(ADMIN_PASSWORD);
    await state.platformPage.getByLabel('MFA code').fill(state.totp);
    await Promise.all([
        state.platformPage.waitForURL(/\/platform-admin\/?$/, { timeout: 30000 }),
        state.platformPage.getByRole('button', { name: 'Sign in' }).click(),
    ]);
    await expect(state.platformPage.getByRole('heading', { name: 'Platform Admin Dashboard' })).toBeVisible();
});

When(
    'I create tenant {string} for host {string} through the Platform Admin portal',
    async ({ page }, slug, host) => {
        const platformPage = stateFor(page).platformPage;
        await platformPage.goto('/platform-admin/tenants/add', { waitUntil: 'domcontentloaded' });
        await waitForPageBody(platformPage);
        await platformPage.getByLabel('Tenant slug').fill(slug);
        await platformPage.getByLabel('Display name', { exact: true }).fill('KMP2 E2E Kingdom');
        await platformPage.getByLabel('Region').fill('us');
        await platformPage.getByLabel('Primary host').fill(host);
        await platformPage.getByLabel('Tenant super user email').fill(INITIAL_SUPER_USER_EMAIL);
        await platformPage.getByLabel('Database server').fill('db');
        await platformPage.getByLabel('Database name').fill(`${slug}_dev`);
        await platformPage.getByLabel('Database role').fill(`kmp_tenant_${slug}_role`);
        await platformPage.getByLabel('Queue limit').fill('5');
        await platformPage.getByLabel('Azure blob container').fill(`tenant-${slug}`);
        await platformPage.getByLabel('Email mode').selectOption('disabled');

        await Promise.all([
            platformPage.waitForURL(new RegExp(`/platform-admin/tenants/${slug}$`), { timeout: 30000 }),
            platformPage.getByRole('button', { name: 'Create and queue provisioning' }).click(),
        ]);
        await expect(platformPage.getByText(/Tenant provisioning has been queued:/)).toBeVisible();
        await expect(platformPage.getByRole('heading', { name: 'Provisioning Status' })).toBeVisible();
    },
);

Then('tenant {string} should have a queued provisioning job without secret leakage', async ({ page }, slug) => {
    const status = runPhpJson(tenantStatusScript, { slug });
    stateFor(page).lastProvisioningJob = status;

    expect(status.tenant_status).toBe('provisioning');
    expect(status.host).toBe(`${slug}.localhost`);
    expect(status.job_status).toBe('queued');
    expect(status.parameters).toContain('"create_database":true');
    expect(status.parameters).toContain('"run_migrations":true');
    expect(status.parameters).toContain(`"initial_super_user_email":"${INITIAL_SUPER_USER_EMAIL}"`);
    expect(status.parameters).not.toContain(ADMIN_PASSWORD);
    expect(status.parameters).not.toContain(ADMIN_TOTP_SECRET);
    expect(status.parameters).not.toMatch(/password\\s*[:=]/i);
    expect(status.parameters).not.toContain('password_token');
});

When('the platform job runner drains queued jobs', async ({ page }) => {
    stateFor(page).lastRunnerOutput = runCake(['platform', 'jobs', 'run', '--limit', '5'], {
        timeoutMs: 300000,
    });
    expect(stateFor(page).lastRunnerOutput).toContain('Platform jobs runner claimed');
});

Then('tenant {string} should be active for host {string}', async ({ page }, slug, host) => {
    const status = runPhpJson(tenantStatusScript, { slug }, { timeoutMs: 120000 });

    expect(status.tenant_status).toBe('active');
    expect(status.host).toBe(host);
    expect(status.job_status).toBe('completed');
    expect(status.last_error || '').toBe('');
    expect(status.db_exists).toBe(true);
    expect(status.members_table_exists).toBe(true);
    expect(status.schema_version || '').not.toBe('');
});

Then('tenant {string} should have the initial super user {string}', async ({ page }, slug, email) => {
    const status = runPhpJson(
        tenantStatusScript,
        { slug, initialSuperUserEmail: email },
        { timeoutMs: 120000 },
    );

    expect(status.initial_super_user).toMatchObject({
        email_address: email,
        status: 'verified',
        member_branch: 'Kingdom',
    });
    expect(Number(status.initial_super_user.kingdom_role_scopes)).toBeGreaterThan(0);
    expect(Number(status.initial_super_user.super_user_roles)).toBeGreaterThan(0);
    expect(Number(status.initial_super_user.super_user_permissions)).toBeGreaterThan(0);
    expect(Number(status.initial_super_user.unwarranted_super_user_permissions)).toBeGreaterThan(0);
});

Then('the tenant {string} host should show the sign in page', async ({ page, browser }, slug) => {
    const tenantContext = await createTenantContext(browser, { tenant: slug });
    const tenantPage = await tenantContext.context.newPage();
    stateFor(page).tenantContexts.push(tenantContext.context);

    const response = await tenantPage.goto('/members/login', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(tenantPage);

    expect(response.status()).toBeLessThan(500);
    await expect(tenantPage.getByLabel('Email Address')).toBeVisible();
    await expect(tenantPage.getByLabel('Password')).toBeVisible();
});

Then(
    'the tenant super user {string} can request a password reset on tenant {string}',
    async ({ page, browser }, email, slug) => {
        const tenantContext = await createTenantContext(browser, { tenant: slug });
        const tenantPage = await tenantContext.context.newPage();
        stateFor(page).tenantContexts.push(tenantContext.context);

        await tenantPage.goto('/members/login', { waitUntil: 'domcontentloaded' });
        await waitForPageBody(tenantPage);
        await tenantPage.getByRole('link', { name: 'Forgot Password?' }).click();
        await waitForPageBody(tenantPage);
        await tenantPage.getByLabel('Email Address').fill(email);
        await Promise.all([
            tenantPage.waitForURL(/\/members\/login$/, { timeout: 30000 }),
            tenantPage.getByRole('button', { name: 'Send Password Reset' }).click(),
        ]);
        await expect(
            tenantPage.getByText('If your email is on file, a password reset link has been sent.'),
        ).toBeVisible();

        const member = runPhpJson(tenantMemberScript, { slug, email }, { timeoutMs: 120000 }).member;
        expect(member).toMatchObject({
            email_address: email,
            status: 'verified',
        });
        expect(member.password_token || '').not.toBe('');
        expect(member.password_token_expires_on || '').not.toBe('');

        flushWorkflowsAndQueue();
        await waitForQueueSettled({ timeoutMs: 60000 });
        await expect.poll(
            async () => mailpitSearchTotal(tenantPage.request, `to:${email}`),
            { timeout: 30000 },
        ).toBeGreaterThanOrEqual(1);
    },
);

After(async ({ page }) => {
    const state = page[STATE_KEY];
    if (!state) {
        return;
    }

    await Promise.all([
        ...(state.platformContext ? [state.platformContext.close()] : []),
        ...state.tenantContexts.map((context) => context.close()),
    ]);
    page[STATE_KEY] = null;
});
