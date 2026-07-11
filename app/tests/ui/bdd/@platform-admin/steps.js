const { createBdd } = require('playwright-bdd');
const { expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const { createHash } = require('node:crypto');
const { existsSync, readFileSync, readdirSync } = require('node:fs');
const { join } = require('node:path');

const { createTenantContext } = require('../../support/tenant-context.cjs');
const {
    APP_ROOT,
    getAppContainerName,
    getUiTestEnvironment,
    shouldUseDockerPhp,
} = require('../../support/test-environment.cjs');
const {
    flushWorkflowsAndQueue,
    loginAs,
    mailpitSearchTotal,
    runPhpJson,
    waitForPageBody,
    waitForQueueSettled,
} = require('../../support/ui-helpers.cjs');

const { Given, When, Then, Before, After } = createBdd();

const STATE_KEY = '__platformTenantProvisioning';
const PLATFORM_HOST = 'platform.kmp.localhost';
const ADMIN_EMAIL = 'admin@example.org';
const ADMIN_PASSWORD = 'TestPassword';
const ADMIN_TOTP_SECRET = 'QJR6QMYZYRHDZCOK5STD';
const ADMIN_USER_ID = '11111111-1111-4111-8111-111111111111';
const INITIAL_SUPER_USER_EMAIL = 'superuser@kmp2.localhost';

Before({ tags: '@platform-admin' }, ({ $testInfo }) => {
    $testInfo.setTimeout(1800000);
});

const stateFor = (page) => {
    if (!page[STATE_KEY]) {
        page[STATE_KEY] = {
            platformContext: null,
            platformPage: null,
            tenantContexts: [],
            lastProvisioningJob: null,
            lastBackupJob: null,
            lastRestoreJob: null,
            lastRunnerOutput: null,
            latestTenantBackup: null,
            tenantBackupArchiveFilename: null,
            tenantBackupArchivePath: null,
            tenantBackupRecoveryKeyPath: null,
            tenantBackupRecoveryPackage: null,
            tenantFrontendPage: null,
            tenantFrontendRestoreId: null,
        };
    }

    return page[STATE_KEY];
};

const platformContextOptions = () => {
    const { baseUrl, hostHeader } = getUiTestEnvironment();
    if (hostHeader) {
        return {
            baseURL: baseUrl,
            extraHTTPHeaders: {
                Host: PLATFORM_HOST,
                'X-KMP-E2E': '1',
            },
        };
    }

    const url = new URL(baseUrl);
    url.hostname = PLATFORM_HOST;

    return {
        baseURL: url.toString().replace(/\/+$/, ''),
        extraHTTPHeaders: { 'X-KMP-E2E': '1' },
    };
};

const runCake = (args, { timeoutMs = 180000 } = {}) => {
    const useDocker = shouldUseDockerPhp();
    const file = useDocker ? 'docker' : 'bin/cake';
    const containerTimeoutSeconds = Math.max(1, Math.floor((timeoutMs - 5000) / 1000));
    const commandArgs = useDocker
        ? [
            'exec',
            '-w',
            '/var/www/html',
            getAppContainerName(),
            'timeout',
            '--signal=TERM',
            '--kill-after=10s',
            `${containerTimeoutSeconds}s`,
            'bin/cake',
            ...args,
        ]
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

const platformJobStatusScript = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

use Cake\\Datasource\\ConnectionManager;

$input = json_decode(stream_get_contents(STDIN), true) ?: [];
$status = ConnectionManager::get('platform')
    ->execute('SELECT status FROM platform_jobs WHERE id = :id', ['id' => $input['jobId'] ?? ''])
    ->fetchColumn(0);

echo json_encode(['status' => $status === false ? null : $status]);
`;

const runtimeTenantSchemaLockFiles = () => {
    const migrationDirectories = [
        join(APP_ROOT, 'config', 'Migrations'),
        join(APP_ROOT, 'vendor', 'dereuromark', 'cakephp-tools', 'config', 'Migrations'),
    ];
    const pluginsDirectory = join(APP_ROOT, 'plugins');
    if (existsSync(pluginsDirectory)) {
        for (const entry of readdirSync(pluginsDirectory, { withFileTypes: true })) {
            if (entry.isDirectory()) {
                migrationDirectories.push(join(pluginsDirectory, entry.name, 'config', 'Migrations'));
            }
        }
    }

    return migrationDirectories.flatMap((directory) => {
        if (!existsSync(directory)) {
            return [];
        }

        return readdirSync(directory)
            .filter((name) => /^schema-dump-tenant(?:_provision)?\.lock$/.test(name))
            .map((name) => join(directory, name));
    });
};

const fixtureScript = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

use App\\Services\\Platform\\PlatformTotpVerifier;
use App\\Services\\Platform\\TenantHostResolver;
use App\\Services\\BackupStorageService;
use App\\Services\\Secrets\\SecretStoreFactory;
use App\\Services\\Secrets\\SensitiveString;
use App\\Services\\Secrets\\WritableSecretStoreInterface;
use Cake\\Cache\\Cache;
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
Cache::clear('restore_status');
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
        $backupUris = $platform->execute(
            'SELECT object_uri FROM tenant_backups WHERE tenant_id = :tenantId AND object_uri IS NOT NULL',
            ['tenantId' => $tenantId],
        )->fetchAll('assoc');
        $backupStorage = new BackupStorageService();
        foreach ($backupUris as $backup) {
            $uri = (string)$backup['object_uri'];
            if (str_starts_with($uri, 'backup://')) {
                $path = substr($uri, strlen('backup://'));
                if ($backupStorage->exists($path)) {
                    $backupStorage->delete($path);
                }
            }
        }
        $platform->execute('DELETE FROM tenant_backups WHERE tenant_id = :tenantId', ['tenantId' => $tenantId]);
        $platform->execute('DELETE FROM platform_jobs WHERE tenant_id = :tenantId', ['tenantId' => $tenantId]);
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
if (!empty($input['setPassword'])) {
    $tenantConnection->execute(
        'UPDATE members SET password = :password WHERE email_address = :email',
        [
            'password' => password_hash((string)$input['setPassword'], PASSWORD_DEFAULT),
            'email' => $email,
        ],
    );
}
$member = $tenantConnection->execute(
    'SELECT email_address, status, password_token, password_token_expires_on FROM members WHERE email_address = :email LIMIT 1',
    ['email' => $email],
)->fetch('assoc') ?: null;
ConnectionManager::drop($connectionName);

echo json_encode(['member' => $member]);
`;

const tenantBackupStatusScript = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

use App\\Services\\BackupStorageService;
use App\\Services\\Backups\\BackupStreamCipher;
use Cake\\Datasource\\ConnectionManager;

$input = json_decode(stream_get_contents(STDIN), true) ?: [];
$slug = strtolower((string)($input['slug'] ?? ''));
$platform = ConnectionManager::get('platform');
$job = $platform->execute(
    "SELECT j.id, j.status, j.parameters, j.last_error
       FROM platform_jobs j
       JOIN tenants t ON t.id = j.tenant_id
      WHERE t.slug = :slug AND j.job_type = 'tenant_backup'
   ORDER BY j.created_at DESC
      LIMIT 1",
    ['slug' => $slug],
)->fetch('assoc') ?: [];
$backup = $platform->execute(
    "SELECT b.*, COUNT(e.id) AS event_count
       FROM tenant_backups b
       JOIN tenants t ON t.id = b.tenant_id
  LEFT JOIN platform_job_events e ON e.platform_job_id = b.platform_job_id
      WHERE t.slug = :slug
   GROUP BY b.id
   ORDER BY b.created_at DESC
      LIMIT 1",
    ['slug' => $slug],
)->fetch('assoc') ?: [];
$auditAction = null;
if (!empty($backup['id'])) {
    $auditAction = $platform->execute(
        'SELECT action FROM audit_events WHERE subject_id = :backupId ORDER BY created_at DESC, id DESC LIMIT 1',
        ['backupId' => $backup['id']],
    )->fetchColumn(0) ?: null;
}

$objectExists = false;
$checksumMatches = false;
$archiveHeader = [];
if (!empty($backup['object_uri']) && str_starts_with((string)$backup['object_uri'], 'backup://')) {
    $objectPath = substr((string)$backup['object_uri'], strlen('backup://'));
    $storage = new BackupStorageService();
    $objectExists = $storage->exists($objectPath);
    if ($objectExists) {
        $stream = $storage->readStream($objectPath);
        $hash = hash_init('sha256');
        $size = 0;
        $magic = fread($stream, strlen(BackupStreamCipher::MAGIC));
        hash_update($hash, $magic);
        $size += strlen($magic);
        if ($magic === BackupStreamCipher::MAGIC) {
            $lengthBytes = fread($stream, 4);
            hash_update($hash, $lengthBytes);
            $size += strlen($lengthBytes);
            $unpacked = unpack('Nlength', $lengthBytes);
            $headerBytes = fread($stream, (int)($unpacked['length'] ?? 0));
            hash_update($hash, $headerBytes);
            $size += strlen($headerBytes);
            $decoded = json_decode($headerBytes, true);
            $archiveHeader = is_array($decoded) ? $decoded : [];
        }
        while (!feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk === false) {
                throw new RuntimeException('Unable to inspect stored backup stream.');
            }
            hash_update($hash, $chunk);
            $size += strlen($chunk);
        }
        fclose($stream);
        $checksumMatches = hash_final($hash) === (string)$backup['object_sha256']
            && $size === (int)$backup['object_size_bytes'];
    }
}

echo json_encode([
    'job' => $job,
    'backup' => $backup,
    'audit_action' => $auditAction,
    'object_exists' => $objectExists,
    'checksum_matches' => $checksumMatches,
    'archive_header' => [
        'version' => $archiveHeader['version'] ?? null,
        'algorithm' => $archiveHeader['algorithm'] ?? null,
        'scope' => $archiveHeader['scope'] ?? null,
        'tenant_id' => $archiveHeader['tenant_id'] ?? null,
        'backup_id' => $archiveHeader['backup_id'] ?? null,
        'has_stream_header' => !empty($archiveHeader['stream_header']),
    ],
]);
`;

const tenantRestoreStatusScript = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

use Cake\\Datasource\\ConnectionManager;

$input = json_decode(stream_get_contents(STDIN), true) ?: [];
$slug = strtolower((string)($input['slug'] ?? ''));
$platform = ConnectionManager::get('platform');
$job = $platform->execute(
    "SELECT j.*, COUNT(e.id) AS event_count
       FROM platform_jobs j
       JOIN tenants t ON t.id = j.tenant_id
  LEFT JOIN platform_job_events e ON e.platform_job_id = j.id
      WHERE t.slug = :slug AND j.job_type = 'tenant_restore'
   GROUP BY j.id
   ORDER BY j.created_at DESC
      LIMIT 1",
    ['slug' => $slug],
)->fetch('assoc') ?: [];

echo json_encode(['job' => $job]);
`;

const currentTotpScript = `
require 'vendor/autoload.php';
require 'config/bootstrap.php';

use App\\Services\\Platform\\PlatformTotpVerifier;
use App\\Services\\Secrets\\SecretStoreFactory;

$input = json_decode(stream_get_contents(STDIN), true) ?: [];
$totp = new PlatformTotpVerifier(SecretStoreFactory::fromConfig());
echo json_encode(['totp' => $totp->codeForTimestamp((string)$input['secret'], time())]);
`;

const freshTotp = () => runPhpJson(currentTotpScript, { secret: ADMIN_TOTP_SECRET }).totp;

const fillGuardedBackupModal = async (
    platformPage,
    trigger,
    { title, confirmation, reason, submitLabel },
) => {
    await trigger.click();
    const dialog = platformPage.getByRole('dialog', { name: title });
    await expect(dialog).toBeVisible();
    await dialog.getByLabel(`Type "${confirmation}" to confirm`).fill(confirmation);
    await dialog.getByLabel('Operator reason').fill(reason);
    await dialog.getByLabel('MFA code').fill(freshTotp());

    return dialog.getByRole('button', { name: submitLabel, exact: true });
};

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
    await expect(state.platformPage.getByRole('heading', { name: 'Platform operations' })).toBeVisible();
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
        await platformPage.getByLabel('Email mode').selectOption('default');

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

    expect(status.job_status).toMatch(/^(queued|running|completed)$/);
    expect(status.tenant_status).toBe(status.job_status === 'completed' ? 'active' : 'provisioning');
    expect(status.host).toBe(`${slug}.localhost`);
    expect(status.parameters).toContain('"create_database":true');
    expect(status.parameters).toContain('"run_migrations":true');
    expect(status.parameters).toContain(`"initial_super_user_email":"${INITIAL_SUPER_USER_EMAIL}"`);
    expect(status.parameters).not.toContain(ADMIN_PASSWORD);
    expect(status.parameters).not.toContain(ADMIN_TOTP_SECRET);
    expect(status.parameters).not.toMatch(/password\\s*[:=]/i);
    expect(status.parameters).not.toContain('password_token');
});

When('the platform job runner drains queued jobs', async ({ page }) => {
    const state = stateFor(page);
    state.lastRunnerOutput = runCake(['platform', 'jobs', 'run', '--limit', '5'], {
        timeoutMs: 1200000,
    });
    expect(state.lastRunnerOutput).toContain('Platform jobs runner claimed');

    const jobId = state.lastRestoreJob?.id
        ?? state.lastBackupJob?.id
        ?? state.lastProvisioningJob?.job_id;
    expect(jobId).toBeTruthy();
    await expect.poll(
        () => runPhpJson(platformJobStatusScript, { jobId }).status,
        {
            message: `Platform job ${jobId} should reach a terminal state`,
            timeout: 1200000,
            intervals: [1000, 2000, 5000],
        },
    ).toMatch(/^(completed|failed)$/);
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

Then('runtime tenant migrations should not write schema lock files', async () => {
    expect(runtimeTenantSchemaLockFiles()).toEqual([]);
});

When(
    'I queue a {int} day backup for tenant {string} through the Platform Admin portal',
    async ({ page }, retentionDays, slug) => {
        const platformPage = stateFor(page).platformPage;
        await platformPage.goto(`/platform-admin/tenants/${slug}/backups`, { waitUntil: 'domcontentloaded' });
        await waitForPageBody(platformPage);
        await platformPage.getByLabel('Retention days').fill(String(retentionDays));
        await Promise.all([
            platformPage.waitForURL(new RegExp(`/platform-admin/tenants/${slug}/backups$`), { timeout: 30000 }),
            platformPage.getByRole('button', { name: 'Queue backup' }).click(),
        ]);
        await expect(platformPage.getByText(/Tenant backup has been queued:/)).toBeVisible();
    },
);

Then('tenant {string} should have a queued backup job without secret leakage', async ({ page }, slug) => {
    const status = runPhpJson(tenantBackupStatusScript, { slug }, { timeoutMs: 120000 });
    stateFor(page).lastBackupJob = status.job;

    expect(status.job.status).toMatch(/^(queued|running|completed)$/);
    expect(status.job.parameters).toContain(`"tenant_slug":"${slug}"`);
    expect(status.job.parameters).toContain('"retention_days":7');
    expect(status.job.parameters).not.toContain(ADMIN_PASSWORD);
    expect(status.job.parameters).not.toContain(ADMIN_TOTP_SECRET);
    expect(status.job.parameters).not.toMatch(/password\\s*[:=]/i);
});

Then('tenant {string} should have a verified encrypted JSON logical backup', async ({ page }, slug) => {
    const status = runPhpJson(tenantBackupStatusScript, { slug }, { timeoutMs: 120000 });
    const state = stateFor(page);
    state.lastBackupJob = status.job;
    state.latestTenantBackup = status.backup;

    expect(status.job.status).toBe('completed');
    expect(status.job.last_error || '').toBe('');
    expect(status.backup).toMatchObject({
        status: 'completed',
        backup_type: 'json',
        encryption_algorithm: 'XCHACHA20-POLY1305-SECRETSTREAM',
    });
    expect(status.backup.object_uri).toMatch(
        new RegExp(`^backup://tenants/${slug}/[0-9a-f-]{36}\\.json\\.gz\\.enc$`),
    );
    expect(Number(status.backup.object_size_bytes)).toBeGreaterThan(0);
    expect(status.backup.object_sha256).toMatch(/^[0-9a-f]{64}$/);
    expect(status.backup.wrapped_dek || '').not.toBe('');
    expect(status.backup.wrapped_dek_key_name).toBe(`tenant.${slug}.kek`);
    expect(status.object_exists).toBe(true);
    expect(status.checksum_matches).toBe(true);
    expect(Number(status.backup.event_count)).toBeGreaterThanOrEqual(2);
    expect(status.archive_header).toMatchObject({
        version: 2,
        algorithm: 'XCHACHA20-POLY1305-SECRETSTREAM',
        scope: 'tenant',
        tenant_id: status.backup.tenant_id,
        backup_id: status.backup.id,
        has_stream_header: true,
    });
});

Then('the completed tenant backup should have an operator timeline', async ({ page }) => {
    const state = stateFor(page);
    const platformPage = state.platformPage;
    await platformPage.goto(`/platform-admin/jobs/${state.lastBackupJob.id}`, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(platformPage);

    await expect(platformPage.getByRole('heading', { name: 'Progress timeline' })).toBeVisible();
    await expect(platformPage.getByText('Job execution started.')).toBeVisible();
    await expect(platformPage.getByText('Job completed successfully.')).toBeVisible();
});

When(
    'I download the managed tenant backup and recovery key for {string}',
    async ({ page, $testInfo }, slug) => {
        const state = stateFor(page);
        const platformPage = state.platformPage;
        await platformPage.goto(`/platform-admin/tenants/${slug}/backups`, { waitUntil: 'domcontentloaded' });
        await waitForPageBody(platformPage);

        const backupSection = platformPage.locator('section').filter({
            has: platformPage.getByRole('heading', { name: 'Recorded Tenant Backups' }),
        });
        const backupRow = backupSection.locator('tbody tr').first();
        await expect(backupRow).toContainText('JSON logical archive');

        const archiveButton = backupRow.getByRole('button', {
            name: 'Download encrypted archive',
            exact: true,
        });
        const archiveSubmit = await fillGuardedBackupModal(platformPage, archiveButton, {
            title: 'Download tenant backup',
            confirmation: `DOWNLOAD ${slug}`,
            reason: 'Escrow the E2E tenant recovery pair.',
            submitLabel: 'Download encrypted archive',
        });
        const [archiveDownload] = await Promise.all([
            platformPage.waitForEvent('download'),
            archiveSubmit.click(),
        ]);
        state.tenantBackupArchiveFilename = archiveDownload.suggestedFilename();
        state.tenantBackupArchivePath = $testInfo.outputPath(state.tenantBackupArchiveFilename);
        await archiveDownload.saveAs(state.tenantBackupArchivePath);

        const recoveryButton = backupRow.getByRole('button', {
            name: 'Download recovery key',
            exact: true,
        });
        const recoverySubmit = await fillGuardedBackupModal(platformPage, recoveryButton, {
            title: 'Download tenant recovery key',
            confirmation: `DOWNLOAD KEY ${slug}`,
            reason: 'Escrow the E2E tenant recovery key.',
            submitLabel: 'Download recovery key',
        });
        const [recoveryDownload] = await Promise.all([
            platformPage.waitForEvent('download'),
            recoverySubmit.click(),
        ]);
        state.tenantBackupRecoveryKeyPath = $testInfo.outputPath(recoveryDownload.suggestedFilename());
        await recoveryDownload.saveAs(state.tenantBackupRecoveryKeyPath);
        state.tenantBackupRecoveryPackage = JSON.parse(
            readFileSync(state.tenantBackupRecoveryKeyPath, 'utf8'),
        );
    },
);

Then(
    'the downloaded tenant backup and recovery key for {string} are a matched pair',
    async ({ page }, slug) => {
        const state = stateFor(page);
        const archive = readFileSync(state.tenantBackupArchivePath);
        const recoveryPackage = state.tenantBackupRecoveryPackage;

        expect(state.tenantBackupArchiveFilename).toMatch(
            new RegExp(`^tenant-${slug}-[0-9a-f-]{36}\\.json\\.gz\\.enc$`),
        );
        expect(recoveryPackage).toMatchObject({
            format: 'kmp-managed-backup-recovery-key',
            version: 1,
            scope: 'tenant',
            backup_id: state.latestTenantBackup.id,
            tenant: {
                id: state.latestTenantBackup.tenant_id,
                slug,
            },
            backup_type: 'json',
            encryption_algorithm: 'XCHACHA20-POLY1305-SECRETSTREAM',
            archive: {
                size_bytes: archive.length,
                sha256: createHash('sha256').update(archive).digest('hex'),
            },
            data_encryption_key: {
                encoding: 'base64',
            },
        });
        expect(Buffer.from(recoveryPackage.data_encryption_key.value, 'base64')).toHaveLength(32);
        expect(recoveryPackage).not.toHaveProperty('wrapped_dek');
        expect(recoveryPackage).not.toHaveProperty('key_encryption_key');
    },
);

When('I suspend tenant {string} through the Platform Admin portal', async ({ page }, slug) => {
    const platformPage = stateFor(page).platformPage;
    await platformPage.goto(`/platform-admin/tenants/${slug}`, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(platformPage);
    await platformPage.getByLabel(`Type SUSPEND ${slug}`).fill(`SUSPEND ${slug}`);
    await platformPage.getByLabel('Operator reason').fill('Exercise the guarded restore recovery path.');
    await platformPage.getByLabel('MFA code').fill(freshTotp());
    await Promise.all([
        platformPage.waitForURL(new RegExp(`/platform-admin/tenants/${slug}$`), { timeout: 30000 }),
        platformPage.getByRole('button', { name: 'Suspend tenant' }).click(),
    ]);
    await expect(platformPage.getByText('Tenant status changed to suspended.')).toBeVisible();

    const status = runPhpJson(tenantStatusScript, { slug }, { timeoutMs: 120000 });
    expect(status.tenant_status).toBe('suspended');
});

When('I queue the latest backup to restore tenant {string}', async ({ page }, slug) => {
    const platformPage = stateFor(page).platformPage;
    await platformPage.goto(`/platform-admin/tenants/${slug}/backups`, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(platformPage);
    const restoreButton = platformPage.getByRole('button', { name: 'Queue destructive restore' });
    const restoreSubmit = await fillGuardedBackupModal(platformPage, restoreButton, {
        title: 'Queue tenant restore',
        confirmation: `RESTORE ${slug}`,
        reason: 'Verify the encrypted tenant recovery path.',
        submitLabel: 'Queue destructive restore',
    });
    await Promise.all([
        platformPage.waitForURL(new RegExp(`/platform-admin/tenants/${slug}/backups$`), { timeout: 30000 }),
        restoreSubmit.click(),
    ]);
    await expect(platformPage.getByText(/Tenant restore has been queued:/)).toBeVisible();
});

When('I delete the latest managed backup for tenant {string}', async ({ page }, slug) => {
    const platformPage = stateFor(page).platformPage;
    await platformPage.goto(`/platform-admin/tenants/${slug}/backups`, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(platformPage);
    const backupSection = platformPage.locator('section').filter({
        has: platformPage.getByRole('heading', { name: 'Recorded Tenant Backups' }),
    });
    const backupRow = backupSection.locator('tbody tr').first();
    const deleteButton = backupRow.getByRole('button', { name: 'Delete backup', exact: true });
    const deleteSubmit = await fillGuardedBackupModal(platformPage, deleteButton, {
        title: 'Delete tenant backup',
        confirmation: `DELETE BACKUP ${slug}`,
        reason: 'Remove the completed E2E recovery point after verification.',
        submitLabel: 'Delete backup',
    });

    await Promise.all([
        platformPage.waitForURL(new RegExp(`/platform-admin/tenants/${slug}/backups$`), { timeout: 30000 }),
        deleteSubmit.click(),
    ]);
    await expect(platformPage.getByText('Tenant backup archive deleted. Audit metadata has been retained.')).toBeVisible();
});

Then('tenant {string} should retain audited metadata for the deleted backup', async ({ page }, slug) => {
    const status = runPhpJson(tenantBackupStatusScript, { slug }, { timeoutMs: 120000 });

    expect(status.backup.status).toBe('deleted');
    expect(status.backup.object_uri).toBeNull();
    expect(status.object_exists).toBe(false);
    expect(status.audit_action).toBe('tenant_backup.deleted');
});

Then('tenant {string} should have a queued restore job without secret leakage', async ({ page }, slug) => {
    const status = runPhpJson(tenantRestoreStatusScript, { slug }, { timeoutMs: 120000 });
    stateFor(page).lastRestoreJob = status.job;

    expect(status.job.status).toMatch(/^(queued|running|completed)$/);
    expect(status.job.parameters).toContain(`"tenant_slug":"${slug}"`);
    expect(status.job.parameters).toContain(`"backup_id":"`);
    expect(status.job.parameters).not.toContain(ADMIN_PASSWORD);
    expect(status.job.parameters).not.toContain(ADMIN_TOTP_SECRET);
    expect(status.job.parameters).not.toMatch(/password\\s*[:=]/i);
});

Then('tenant {string} should have a completed restore job', async ({ page }, slug) => {
    const status = runPhpJson(tenantRestoreStatusScript, { slug }, { timeoutMs: 120000 });
    stateFor(page).lastRestoreJob = status.job;

    expect(status.job.status).toBe('completed');
    expect(status.job.last_error || '').toBe('');
    expect(Number(status.job.event_count)).toBeGreaterThanOrEqual(2);
});

When('I reactivate tenant {string} through the Platform Admin portal', async ({ page }, slug) => {
    const platformPage = stateFor(page).platformPage;
    await platformPage.goto(`/platform-admin/tenants/${slug}`, { waitUntil: 'domcontentloaded' });
    await waitForPageBody(platformPage);
    await platformPage.getByLabel(`Type REACTIVATE ${slug}`).fill(`REACTIVATE ${slug}`);
    await platformPage.getByLabel('Reactivation reason').fill('Restore completed and tenant checks passed.');
    await platformPage.getByLabel('Reactivation MFA code').fill(freshTotp());
    await Promise.all([
        platformPage.waitForURL(new RegExp(`/platform-admin/tenants/${slug}$`), { timeout: 30000 }),
        platformPage.getByRole('button', { name: 'Reactivate tenant' }).click(),
    ]);
    await expect(platformPage.getByText('Tenant status changed to active.')).toBeVisible();
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
        await tenantPage.getByRole('link', { name: 'Forgot Password?' }).click({ noWaitAfter: true });
        await tenantPage.waitForURL(/\/members\/forgot-password$/, {
            waitUntil: 'domcontentloaded',
            timeout: 60000,
        });
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

        flushWorkflowsAndQueue({ tenantSlug: slug });
        await waitForQueueSettled({ timeoutMs: 60000, tenantSlug: slug });
        await expect.poll(
            async () => mailpitSearchTotal(tenantPage.request, `to:${email}`),
            { timeout: 30000 },
        ).toBeGreaterThanOrEqual(1);
    },
);

When(
    'I restore the downloaded managed backup through tenant {string}',
    async ({ page, browser }, slug) => {
        const state = stateFor(page);
        const member = runPhpJson(
            tenantMemberScript,
            {
                slug,
                email: INITIAL_SUPER_USER_EMAIL,
                setPassword: ADMIN_PASSWORD,
            },
            { timeoutMs: 120000 },
        ).member;
        expect(member?.email_address).toBe(INITIAL_SUPER_USER_EMAIL);

        const tenantContext = await createTenantContext(browser, { tenant: slug });
        const tenantPage = await tenantContext.context.newPage();
        state.tenantContexts.push(tenantContext.context);
        state.tenantFrontendPage = tenantPage;

        await loginAs(tenantPage, INITIAL_SUPER_USER_EMAIL, ADMIN_PASSWORD);
        await tenantPage.goto('/backups', { waitUntil: 'domcontentloaded' });
        await waitForPageBody(tenantPage);
        await tenantPage.locator('#backup-file-input').setInputFiles(state.tenantBackupArchivePath);
        await tenantPage.locator('#backup-recovery-key-input').setInputFiles(
            state.tenantBackupRecoveryKeyPath,
        );

        await tenantPage.getByRole('button', { name: 'Import and Restore', exact: true }).click();
        const confirmDialog = tenantPage.getByRole('dialog', { name: 'Restore backup' });
        await expect(confirmDialog).toBeVisible({ timeout: 10000 });
        const restoreResponsePromise = tenantPage.waitForResponse((response) => {
            const request = response.request();

            return request.method() === 'POST'
                && new URL(response.url()).pathname === '/backups/restore';
        }, { timeout: 120000 });
        await confirmDialog.getByRole('button', { name: 'Restore backup', exact: true }).click();
        const restoreResponse = await restoreResponsePromise;

        expect(restoreResponse.status()).toBe(202);
        expect(restoreResponse.headers()['content-type']).toContain('application/json');
        const payload = await restoreResponse.json();
        expect(payload.success).toBe(true);
        expect(payload.status).toMatchObject({
            status: 'running',
            phase: 'queued',
            recovery_key_import: true,
            source: state.tenantBackupArchiveFilename,
        });
        expect(payload.status.restore_id).toMatch(/^[0-9a-f]{32}$/);
        expect(payload.status).not.toHaveProperty('restore_key');
        state.tenantFrontendRestoreId = payload.status.restore_id;
        await expect(
            tenantPage.getByRole('dialog', { name: 'Backup encryption key' }),
        ).toHaveCount(0);
    },
);

Then(
    'another tenant remains available while tenant {string} restore is queued',
    async ({ page, browser }, slug) => {
        const state = stateFor(page);
        expect(state.tenantFrontendRestoreId).toBeTruthy();

        const unaffectedSlug = slug === 'kmp' ? 'kmp2' : 'kmp';
        const unaffectedTenant = await createTenantContext(browser, { tenant: unaffectedSlug });
        const unaffectedPage = await unaffectedTenant.context.newPage();
        state.tenantContexts.push(unaffectedTenant.context);
        const response = await unaffectedPage.goto('/members/login', { waitUntil: 'domcontentloaded' });
        await waitForPageBody(unaffectedPage);

        expect(response.status()).toBe(200);
        await expect(unaffectedPage.getByLabel('Email Address')).toBeVisible();
        await expect(unaffectedPage.getByLabel('Password')).toBeVisible();
    },
);

Then(
    'the tenant frontend restore for {string} completes with the recovery key',
    async ({ page }, slug) => {
        const state = stateFor(page);
        try {
            runCake(
                ['platform', 'schedule', 'run', 'tenant-queue-drain', '-q'],
                { timeoutMs: 600000 },
            );
        } catch (error) {
            const output = [error?.message, error?.stdout, error?.stderr]
                .filter(Boolean)
                .join('\n');
            if (!/Too many workers (running|already)/.test(output)) {
                throw error;
            }
        }

        let status = null;
        await expect.poll(
            async () => {
                const response = await state.tenantFrontendPage.request.get('/backups/status', {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok() || !response.headers()['content-type']?.includes('application/json')) {
                    return `http:${response.status()}`;
                }

                status = await response.json();
                if (status.status === 'failed') {
                    throw new Error(`Tenant restore failed: ${status.message || 'unknown error'}`);
                }

                return status.status;
            },
            {
                message: `Expected the ${slug} restore to complete`,
                timeout: 600000,
                intervals: [1000, 2000, 5000],
            },
        ).toBe('completed');

        expect(status).toMatchObject({
            locked: false,
            status: 'completed',
            phase: 'completed',
            recovery_key_import: true,
            restore_id: state.tenantFrontendRestoreId,
            source: state.tenantBackupArchiveFilename,
        });
        expect(Number(status.table_count)).toBeGreaterThan(0);
        expect(Number(status.rows_processed)).toBeGreaterThan(0);
        expect(status.message).toMatch(/Restore\/import completed:/);
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
