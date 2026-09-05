<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Model\Entity\Member;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\SensitiveString;
use App\Services\Secrets\WritableSecretStoreInterface;
use App\Services\Storage\TenantDocumentProvisioner;
use App\Services\TenantDefaultSettingsInitializer;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Command\Command;
use Cake\Command\SchemacacheClearCommand;
use Cake\Core\Plugin;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\FactoryLocator;
use Cake\ORM\Locator\TableLocator;
use Cake\Utility\Text;
use Migrations\Command\MigrateCommand;
use RuntimeException;
use function Cake\Core\env;

/**
 * Provisions tenant metadata, database resources, migrations, and lifecycle state.
 */
class TenantProvisioningService
{
    private const PLATFORM_CONNECTION = 'platform';
    private const TENANT_CONNECTION = 'tenant_provision';

    /**
     * @param \Cake\Database\Connection|null $platformConnection Optional platform connection override
     */
    public function __construct(private readonly ?Connection $platformConnection = null)
    {
    }

    /**
     * Provision a tenant. Database creation and migrations require CLI/worker context.
     *
     * @param callable|null $commandRunner Callable receiving (object|string $command, list<string> $args): int|null
     * @param callable|null $progress Callable receiving (string $level, string $message): void
     */
    public function provision(
        TenantProvisioningRequest $request,
        ?callable $commandRunner = null,
        ?callable $progress = null,
    ): TenantProvisioningResult {
        AdministrativeDatabase::requireJob();
        $platform = $this->platformConnection();
        $request = $request->normalized((string)($platform->config()['host'] ?? 'localhost'));
        $this->validateRequest($request, $platform);
        $this->assertWorkerContext($request);

        $secretName = sprintf('tenant.%s.db.password', $request->slug);
        $password = $this->ensurePassword($secretName, $request->rotatePassword);
        $backupKekName = sprintf('tenant.%s.kek', $request->slug);
        $this->ensurePassword($backupKekName, false);

        $tenant = $this->upsertTenantMetadata($platform, $request);
        $this->upsertTenantHost($platform, (string)$tenant['id'], $request->host);
        $this->progress($progress, 'info', sprintf('Tenant metadata ready: %s (%s)', $request->slug, $tenant['id']));
        $this->progress($progress, 'info', sprintf('Stored DB password secret: %s', $secretName));
        $this->progress($progress, 'info', sprintf('Backup encryption key ready: %s', $backupKekName));

        if (!$request->skipCreateDatabase) {
            $this->maybeCreateDatabase($platform, $request, $password, $progress);
        } else {
            $this->progress($progress, 'warning', 'Skipping database creation; assuming resources already exist.');
        }

        $schemaVersion = null;
        if ($request->runMigrations) {
            if ($commandRunner === null) {
                throw new RuntimeException('Tenant migration execution requires a command runner.');
            }
            $schemaVersion = TenantContext::with(
                TenantMetadata::fromPlatformRow($tenant),
                fn(): ?string => $this->runTenantMigrations($request, $password, $commandRunner, $progress),
            );
        } else {
            $this->progress($progress, 'warning', 'Skipping tenant migrations; tenant will remain provisioning.');
        }

        (new TenantDocumentProvisioner())->ensure(TenantMetadata::fromPlatformRow($tenant));

        $this->markTenantStatus($platform, (string)$tenant['id'], $request->finalStatus, $schemaVersion);
        $tenant = $platform->execute('SELECT * FROM tenants WHERE id = ?', [$tenant['id']])->fetch('assoc') ?: $tenant;
        $this->progress(
            $progress,
            'success',
            sprintf('Tenant %s status is %s.', $request->slug, $request->finalStatus),
        );

        return new TenantProvisioningResult($tenant, $secretName, $schemaVersion, $request->finalStatus);
    }

    /**
     * Return the platform metadata connection.
     */
    private function platformConnection(): Connection
    {
        if ($this->platformConnection !== null) {
            return $this->platformConnection;
        }

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get(self::PLATFORM_CONNECTION);

        return $connection;
    }

    /**
     * Validate tenant provisioning input before side effects.
     */
    private function validateRequest(TenantProvisioningRequest $request, Connection $platform): void
    {
        $this->assertValidSlug($request->slug);
        $this->assertValidHost($request->host);
        $this->assertValidIdentifier((string)$request->dbName, 'database name');
        $this->assertValidIdentifier((string)$request->dbRole, 'database role');
        if ($request->dbRole === ($platform->config()['username'] ?? null)) {
            throw new RuntimeException('The administrative database role cannot be used as a tenant runtime role.');
        }
        $registered = $platform->execute(
            'SELECT db_server, db_name, db_role FROM tenants WHERE slug = ?',
            [$request->slug],
        )->fetch('assoc');
        if (is_array($registered)) {
            $binding = [
                'db_server' => $request->dbServer,
                'db_name' => $request->dbName,
                'db_role' => $request->dbRole,
            ];
            foreach ($binding as $key => $value) {
                if ((string)$registered[$key] !== (string)$value) {
                    throw new RuntimeException(
                        'Registered tenant database bindings cannot be changed during provisioning.',
                    );
                }
            }
        }
        $conflict = $platform->execute(
            'SELECT 1 FROM tenants WHERE slug <> ? AND db_server = ? AND (db_name = ? OR db_role = ?)',
            [$request->slug, $request->dbServer, $request->dbName, $request->dbRole],
        )->fetchColumn(0);
        if ($conflict !== false) {
            throw new RuntimeException('Another registered tenant already uses the requested database or role.');
        }
        if ($platform->getDriver() instanceof Postgres) {
            if ($request->dbName === ($platform->config()['database'] ?? null)) {
                throw new RuntimeException('The platform database cannot be provisioned as a tenant database.');
            }

            if (strtolower((string)$request->dbServer) !== strtolower((string)$platform->config()['host'])) {
                throw new RuntimeException('Tenant provisioning requires an administrative job on the tenant server.');
            }
            $this->assertDedicatedRuntimeRole($request, is_array($registered));
            $unsafeRole = $platform->execute(
                'SELECT 1 FROM pg_roles WHERE rolname = ? AND (rolsuper OR rolreplication OR rolbypassrls)',
                [$request->dbRole],
            )->fetchColumn(0);
            if ($unsafeRole !== false) {
                throw new RuntimeException('A privileged database role cannot be used as a tenant runtime role.');
            }
        }

        $this->assertValidBlobContainer((string)$request->blobContainer);
        $this->assertValidIdentifier($request->smokeTable, 'smoke-test table');
        if (!preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/', $request->region)) {
            throw new RuntimeException('Invalid region. Use a safe region identifier.');
        }
        if (
            $request->initialSuperUserEmail !== null
            && filter_var($request->initialSuperUserEmail, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new RuntimeException('Initial tenant super-user email must be a valid email address.');
        }
        if (!$request->runMigrations && $request->finalStatus === TenantProvisioningRequest::STATUS_ACTIVE) {
            throw new RuntimeException('Cannot set status=active when --skip-migrations is used.');
        }
        if (
            !in_array(
                $request->finalStatus,
                [
                    TenantProvisioningRequest::STATUS_ACTIVE,
                    TenantProvisioningRequest::STATUS_PROVISIONING,
                ],
                true,
            )
        ) {
            throw new RuntimeException('Final tenant status must be active or provisioning.');
        }
        if ($request->skipCreateDatabase && $request->createDatabase) {
            throw new RuntimeException('Use either --create-database or --skip-create-database, not both.');
        }
        if (!$request->skipCreateDatabase && !$this->shouldCreateDatabase($request, $platform)) {
            throw new RuntimeException(
                'Database creation is disabled. Set KMP_AUTO_CREATE_DATABASES=true, pass --create-database, '
                . 'or use --skip-create-database for metadata-only provisioning.',
            );
        }
        if ($request->displayName === '') {
            throw new RuntimeException('A non-empty --display-name or --name is required.');
        }
    }

    /** Reject reuse of the platform/default runtime credentials before provisioning writes. */
    private function assertDedicatedRuntimeRole(TenantProvisioningRequest $request, bool $alreadyRegistered): void
    {
        foreach (['PLATFORM_DATABASE_URL', 'DATABASE_URL'] as $variable) {
            $url = parse_url((string)env($variable, ''));
            if (
                !is_array($url) || !isset($url['host'], $url['user'], $url['path'])
                || !in_array($url['scheme'] ?? '', ['postgres', 'postgresql'], true)
            ) {
                throw new RuntimeException('Explicit runtime PostgreSQL URLs are required before tenant provisioning.');
            }
            if ($request->dbRole !== rawurldecode($url['user'])) {
                continue;
            }
            if ($variable === 'PLATFORM_DATABASE_URL') {
                throw new RuntimeException('The platform runtime role cannot be used as a tenant runtime role.');
            }
            if (
                !$alreadyRegistered
                || strtolower((string)$request->dbServer) !== strtolower($url['host'])
                || $request->dbName !== rawurldecode(ltrim($url['path'], '/'))
            ) {
                throw new RuntimeException('The default runtime role is reserved for its existing registered tenant.');
            }
        }
    }

    /**
     * Prevent privileged database work from normal HTTP requests.
     */
    private function assertWorkerContext(TenantProvisioningRequest $request): void
    {
        if (($request->skipCreateDatabase && !$request->runMigrations) || PHP_SAPI === 'cli') {
            return;
        }

        throw new RuntimeException('Tenant database provisioning must run from a CLI worker context.');
    }

    /**
     * Ensure the tenant database password exists in the configured secret store.
     */
    private function ensurePassword(string $secretName, bool $rotate): SensitiveString
    {
        $store = SecretStoreFactory::fromConfig();
        if (!$store instanceof WritableSecretStoreInterface) {
            throw new RuntimeException(
                'Configured SecretStoreInterface is not writable; use a writable driver for provisioning.',
            );
        }

        if (!$rotate) {
            $existing = $store->get($secretName);
            if ($existing !== null && !$existing->isEmpty()) {
                return $existing;
            }
        }

        $password = new SensitiveString($this->generatePassword());
        $store->put($secretName, $password);

        return $password;
    }

    /**
     * @return array<string, mixed>
     */
    private function upsertTenantMetadata(Connection $connection, TenantProvisioningRequest $request): array
    {
        $now = $this->now();
        $tenant = $connection
            ->execute('SELECT * FROM tenants WHERE slug = ?', [$request->slug])
            ->fetch('assoc') ?: null;
        $config = $request->tenantConfig !== []
            ? $request->tenantConfig
            : ['documents' => ['blob_container' => $request->blobContainer]];
        $tenantConfig = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($tenantConfig === false) {
            throw new RuntimeException('Unable to encode tenant configuration JSON.');
        }

        if ($tenant === null) {
            $tenant = [
                'id' => Text::uuid(),
                'slug' => $request->slug,
                'display_name' => $request->displayName,
                'status' => TenantProvisioningRequest::STATUS_PROVISIONING,
                'region' => $request->region,
                'primary_host' => $request->host,
                'db_server' => $request->dbServer,
                'db_name' => $request->dbName,
                'db_role' => $request->dbRole,
                'schema_version' => null,
                'tenant_config' => $tenantConfig,
                'created_at' => $now,
                'activated_at' => null,
                'suspended_at' => null,
                'archived_at' => null,
                'modified_at' => $now,
            ];
            $connection->insert('tenants', $tenant);

            return $tenant;
        }

        foreach (['db_name' => $request->dbName, 'db_role' => $request->dbRole] as $field => $value) {
            if ((string)$tenant[$field] !== (string)$value) {
                throw new RuntimeException(sprintf(
                    'Tenant "%s" already exists with %s "%s"; refusing to change it to "%s".',
                    $request->slug,
                    $field,
                    (string)$tenant[$field],
                    (string)$value,
                ));
            }
        }

        $connection->update('tenants', [
            'display_name' => $request->displayName,
            'status' => TenantProvisioningRequest::STATUS_PROVISIONING,
            'region' => $request->region,
            'primary_host' => $request->host,
            'db_server' => $request->dbServer,
            'tenant_config' => $tenantConfig,
            'modified_at' => $now,
        ], ['id' => $tenant['id']]);

        return $connection->execute('SELECT * FROM tenants WHERE slug = ?', [$request->slug])->fetch('assoc');
    }

    /**
     * Create or update the primary host row for a tenant.
     */
    private function upsertTenantHost(Connection $connection, string $tenantId, string $host): void
    {
        $now = $this->now();
        $hostRow = $connection->execute(
            'SELECT id, tenant_id FROM tenant_hosts WHERE host_normalized = ?',
            [$host],
        )->fetch('assoc') ?: null;

        if ($hostRow !== null && (string)$hostRow['tenant_id'] !== $tenantId) {
            throw new RuntimeException(sprintf('Host "%s" is already assigned to another tenant.', $host));
        }

        if ($hostRow === null) {
            $connection->insert('tenant_hosts', [
                'id' => Text::uuid(),
                'tenant_id' => $tenantId,
                'host' => $host,
                'host_normalized' => $host,
                'is_primary' => true,
                'status' => TenantProvisioningRequest::STATUS_ACTIVE,
                'created_at' => $now,
                'modified_at' => $now,
            ]);
            TenantHostResolver::clearCache(self::PLATFORM_CONNECTION);

            return;
        }

        $connection->update('tenant_hosts', [
            'host' => $host,
            'is_primary' => true,
            'status' => TenantProvisioningRequest::STATUS_ACTIVE,
            'modified_at' => $now,
        ], ['id' => $hostRow['id']]);
        TenantHostResolver::clearCache(self::PLATFORM_CONNECTION);
    }

    /**
     * Create or update PostgreSQL tenant database resources.
     */
    private function maybeCreateDatabase(
        Connection $platform,
        TenantProvisioningRequest $request,
        SensitiveString $password,
        ?callable $progress,
    ): void {
        if (($platform->config()['driver'] ?? null) !== Postgres::class) {
            throw new RuntimeException('Automatic tenant database creation requires a PostgreSQL platform datasource.');
        }

        $driver = $platform->getDriver();
        $quotedRole = $driver->quoteIdentifier((string)$request->dbRole);
        $quotedDb = $driver->quoteIdentifier((string)$request->dbName);
        $quotedPassword = $driver->quote($password->reveal());

        $roleExists = (bool)$platform
            ->execute('SELECT 1 FROM pg_roles WHERE rolname = ?', [$request->dbRole])
            ->fetchColumn(0);
        if (!$roleExists) {
            $platform->execute(sprintf('CREATE ROLE %s LOGIN PASSWORD %s', $quotedRole, $quotedPassword));
            $this->progress($progress, 'info', sprintf('Created PostgreSQL role: %s', $request->dbRole));
        } else {
            $platform->execute(sprintf('ALTER ROLE %s WITH LOGIN PASSWORD %s', $quotedRole, $quotedPassword));
            $this->progress($progress, 'info', sprintf('Updated PostgreSQL role password: %s', $request->dbRole));
        }

        $dbExists = (bool)$platform
            ->execute('SELECT 1 FROM pg_database WHERE datname = ?', [$request->dbName])
            ->fetchColumn(0);
        if (!$dbExists) {
            $platform->execute(sprintf('CREATE DATABASE %s', $quotedDb));
            $this->progress($progress, 'info', sprintf('Created PostgreSQL database: %s', $request->dbName));
        }

        (new PostgresRuntimePrivileges($platform))->reconcile((string)$request->dbName, (string)$request->dbRole);
    }

    /**
     * Configure and run tenant app/plugin migrations.
     *
     * @param callable $commandRunner Callable receiving (object|string $command, list<string> $args): int|null
     */
    private function runTenantMigrations(
        TenantProvisioningRequest $request,
        SensitiveString $password,
        callable $commandRunner,
        ?callable $progress,
    ): ?string {
        $previousTenantConfig = ConnectionManager::getConfig(self::TENANT_CONNECTION);
        $previousDefaultConfig = ConnectionManager::getConfig('default');
        $previousDefaultAlias = ConnectionManager::aliases()['default'] ?? null;
        $previousLocator = FactoryLocator::get('Table');
        $this->configureTenantConnection(
            (string)$request->dbServer,
            (string)$request->dbName,
            (string)$request->dbRole,
            $password,
        );
        $tenantConnectionConfig = ConnectionManager::getConfig(self::TENANT_CONNECTION);
        if ($tenantConnectionConfig === null) {
            throw new RuntimeException('Unable to configure tenant provisioning datasource.');
        }
        if ($previousDefaultAlias !== null) {
            ConnectionManager::dropAlias('default');
        } else {
            ConnectionManager::drop('default');
        }
        ConnectionManager::alias(self::TENANT_CONNECTION, 'default');
        FactoryLocator::add('Table', new TableLocator());
        $ensureTenantConnection = function () use ($tenantConnectionConfig): void {
            if (ConnectionManager::getConfig(self::TENANT_CONNECTION) === null) {
                ConnectionManager::setConfig(self::TENANT_CONNECTION, $tenantConnectionConfig);
            }
            if ((ConnectionManager::aliases()['default'] ?? null) !== self::TENANT_CONNECTION) {
                if (in_array('default', ConnectionManager::configured(), true)) {
                    ConnectionManager::drop('default');
                }
                ConnectionManager::alias(self::TENANT_CONNECTION, 'default');
            }
        };

        try {
            $ensureTenantConnection();
            $commandRunner(SchemacacheClearCommand::class, ['--connection', self::TENANT_CONNECTION]);
            $result = (int)$commandRunner(new MigrateCommand(), [
                'migrate',
                '--connection',
                self::TENANT_CONNECTION,
                '--no-lock',
            ]);
            if ($result !== Command::CODE_SUCCESS) {
                throw new RuntimeException('Tenant application migrations failed.');
            }

            foreach ($this->pluginsWithMigrations() as $plugin) {
                $ensureTenantConnection();
                $result = (int)$commandRunner(new MigrateCommand(), [
                    'migrate',
                    '--connection',
                    self::TENANT_CONNECTION,
                    '--no-lock',
                    '--plugin',
                    $plugin,
                ]);
                if ($result !== Command::CODE_SUCCESS) {
                    throw new RuntimeException(sprintf('Tenant plugin migrations failed for %s.', $plugin));
                }
            }

            $this->smokeTestTenantDatabase($request->smokeTable, $progress);
            (new TenantDefaultSettingsInitializer())->initialize();
            $this->ensureInitialSuperUser($request, $progress);

            return $this->readSchemaVersion();
        } finally {
            if ((ConnectionManager::aliases()['default'] ?? null) === self::TENANT_CONNECTION) {
                ConnectionManager::dropAlias('default');
            }
            if (ConnectionManager::getConfig(self::TENANT_CONNECTION) !== null) {
                ConnectionManager::drop(self::TENANT_CONNECTION);
            }
            if ($previousDefaultAlias !== null) {
                ConnectionManager::alias($previousDefaultAlias, 'default');
            } elseif ($previousDefaultConfig !== null) {
                ConnectionManager::setConfig('default', $previousDefaultConfig);
            }
            if ($previousTenantConfig !== null) {
                ConnectionManager::setConfig(self::TENANT_CONNECTION, $previousTenantConfig);
            }
            FactoryLocator::add('Table', $previousLocator);
        }
    }

    /**
     * Configure the tenant provisioning datasource.
     */
    private function configureTenantConnection(
        string $dbServer,
        string $dbName,
        string $dbRole,
        SensitiveString $password,
    ): void {
        $baseConfig = AdministrativeDatabase::forTenant($this->platformConnection()->config(), $dbServer, $dbName);
        if (in_array(self::TENANT_CONNECTION, ConnectionManager::configured(), true)) {
            ConnectionManager::drop(self::TENANT_CONNECTION);
        }
        ConnectionManager::setConfig(self::TENANT_CONNECTION, $baseConfig);
    }

    /**
     * Configure a named tenant datasource for privilege checks.
     */
    private function configureNamedTenantConnection(
        string $connectionName,
        string $dbName,
        string $dbRole,
        SensitiveString $password,
    ): void {
        $baseConfig = ConnectionManager::getConfig('default');
        if ($baseConfig === null) {
            throw new RuntimeException('Default datasource configuration is not available.');
        }
        $platformConfig = $this->platformConnection()->config();
        unset($baseConfig['url']);

        ConnectionManager::setConfig($connectionName, array_merge($baseConfig, [
            'host' => $platformConfig['host'] ?? 'localhost',
            'database' => $dbName,
            'username' => $dbRole,
            'password' => $password->reveal(),
        ]));
    }

    /**
     * Smoke-test the migrated tenant database.
     */
    private function smokeTestTenantDatabase(string $table, ?callable $progress): void
    {
        $this->assertValidIdentifier($table, 'smoke-test table');
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get(self::TENANT_CONNECTION);
        if (!in_array($table, $connection->getSchemaCollection()->listTables(), true)) {
            throw new RuntimeException(sprintf('Tenant smoke test failed: expected table "%s" was not found.', $table));
        }

        $connection->execute('SELECT 1');
        $this->progress($progress, 'success', sprintf('Tenant smoke test passed (%s exists).', $table));
    }

    /**
     * Create the initial tenant Admin account so the tenant can claim access through forgot password.
     */
    private function ensureInitialSuperUser(TenantProvisioningRequest $request, ?callable $progress): void
    {
        if ($request->initialSuperUserEmail === null) {
            return;
        }

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get(self::TENANT_CONNECTION);
        $now = $this->now();
        $permissionId = $this->ensurePermission($connection, $now);
        $roleId = $this->ensureSuperUserRole($connection, $now);
        $this->ensureRolePermission($connection, $roleId, $permissionId, $now);
        $branchId = $this->ensureKingdomBranch($connection, $now);
        $memberId = $this->ensureSuperUserMember($connection, $request, $branchId, $now);
        $this->ensureMemberRole($connection, $memberId, $roleId, $branchId, $now);
        $this->progress(
            $progress,
            'success',
            sprintf('Initial tenant super user ready: %s.', $request->initialSuperUserEmail),
        );
    }

    /**
     * Ensure the tenant root branch exists for the initial super-user account.
     */
    private function ensureKingdomBranch(Connection $connection, string $now): int
    {
        $existing = $connection
            ->execute('SELECT id FROM branches WHERE name = ?', ['Kingdom'])
            ->fetchColumn(0);
        if ($existing !== false && $existing !== null) {
            return (int)$existing;
        }

        $branch = [
            'public_id' => $this->generatePublicId($connection, 'branches'),
            'name' => 'Kingdom',
            'location' => 'Kingdom',
            'parent_id' => null,
            'can_have_members' => true,
            'lft' => 1,
            'rght' => 2,
            'created' => $now,
            'modified' => $now,
        ];
        $branchColumns = $connection->getSchemaCollection()->describe('branches')->columns();
        if (in_array('type', $branchColumns, true)) {
            $branch['type'] = 'Kingdom';
        }
        $connection->insert('branches', $branch);

        return (int)$connection
            ->execute('SELECT id FROM branches WHERE name = ?', ['Kingdom'])
            ->fetchColumn(0);
    }

    /**
     * Ensure the permission that marks a role as super-user exists.
     */
    private function ensurePermission(Connection $connection, string $now): int
    {
        $existing = $connection
            ->execute('SELECT id FROM permissions WHERE name = ?', ['Is Super User'])
            ->fetchColumn(0);
        if ($existing !== false && $existing !== null) {
            $connection->execute(
                "UPDATE permissions
                    SET require_active_membership = TRUE,
                        require_active_background_check = FALSE,
                        require_min_age = 0,
                        is_system = TRUE,
                        is_super_user = TRUE,
                        requires_warrant = FALSE,
                        scoping_rule = ?,
                        modified = ?
                  WHERE id = ?",
                ['Global', $now, (int)$existing],
            );

            return (int)$existing;
        }

        $connection->insert('permissions', [
            'name' => 'Is Super User',
            'require_active_membership' => true,
            'require_active_background_check' => false,
            'require_min_age' => 0,
            'is_system' => true,
            'is_super_user' => true,
            'requires_warrant' => false,
            'scoping_rule' => 'Global',
            'created' => $now,
            'modified' => $now,
        ]);

        return (int)$connection
            ->execute('SELECT id FROM permissions WHERE name = ?', ['Is Super User'])
            ->fetchColumn(0);
    }

    /**
     * Ensure the initial Super User role exists.
     */
    private function ensureSuperUserRole(Connection $connection, string $now): int
    {
        $existing = $connection
            ->execute('SELECT id FROM roles WHERE name = ?', ['Super User'])
            ->fetchColumn(0);
        if ($existing !== false && $existing !== null) {
            return (int)$existing;
        }

        $connection->insert('roles', [
            'name' => 'Super User',
            'is_system' => true,
            'created' => $now,
            'modified' => $now,
        ]);

        return (int)$connection
            ->execute('SELECT id FROM roles WHERE name = ?', ['Super User'])
            ->fetchColumn(0);
    }

    /**
     * Ensure the Super User role grants super-user permission.
     */
    private function ensureRolePermission(Connection $connection, int $roleId, int $permissionId, string $now): void
    {
        $existing = $connection
            ->execute(
                'SELECT id FROM roles_permissions WHERE role_id = ? AND permission_id = ?',
                [$roleId, $permissionId],
            )
            ->fetchColumn(0);
        if ($existing !== false && $existing !== null) {
            return;
        }

        $connection->insert('roles_permissions', [
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created' => $now,
            'created_by' => 1,
        ]);
    }

    /**
     * Ensure the initial member account exists with no known password.
     */
    private function ensureSuperUserMember(
        Connection $connection,
        TenantProvisioningRequest $request,
        int $branchId,
        string $now,
    ): int {
        $email = (string)$request->initialSuperUserEmail;
        $existing = $connection
            ->execute('SELECT id FROM members WHERE email_address = ?', [$email])
            ->fetchColumn(0);
        if ($existing !== false && $existing !== null) {
            $connection->update('members', [
                'status' => Member::STATUS_VERIFIED_MEMBERSHIP,
                'membership_expires_on' => '2100-01-01',
                'branch_id' => $branchId,
                'warrantable' => true,
                'modified' => $now,
            ], ['id' => (int)$existing]);

            return (int)$existing;
        }

        $password = (new DefaultPasswordHasher())->hash(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
        $displayName = trim($request->displayName) !== '' ? $request->displayName : $request->slug;

        $connection->insert('members', [
            'public_id' => $this->generatePublicId($connection, 'members'),
            'password' => $password,
            'sca_name' => 'Tenant Administrator',
            'first_name' => mb_substr($displayName, 0, 30) ?: 'Tenant',
            'last_name' => 'Administrator',
            'email_address' => $email,
            'status' => Member::STATUS_VERIFIED_MEMBERSHIP,
            'membership_expires_on' => '2100-01-01',
            'branch_id' => $branchId,
            'warrantable' => true,
            'birth_month' => 1,
            'birth_year' => 1990,
            'additional_info' => '{}',
            'created' => $now,
            'modified' => $now,
        ]);

        return (int)$connection
            ->execute('SELECT id FROM members WHERE email_address = ?', [$email])
            ->fetchColumn(0);
    }

    /**
     * Generate an 8-character public ID that is unique in the tenant table.
     */
    private function generatePublicId(Connection $connection, string $table): string
    {
        $this->assertValidIdentifier($table, 'public-id table');
        do {
            $publicId = strtolower(bin2hex(random_bytes(4)));
            $exists = $connection
                ->execute(sprintf('SELECT 1 FROM %s WHERE public_id = ?', $table), [$publicId])
                ->fetchColumn(0);
        } while ($exists !== false && $exists !== null);

        return $publicId;
    }

    /**
     * Ensure the initial member has the Super User role.
     */
    private function ensureMemberRole(
        Connection $connection,
        int $memberId,
        int $roleId,
        int $branchId,
        string $now,
    ): void {
        $existing = $connection
            ->execute(
                'SELECT id FROM member_roles WHERE member_id = ? AND role_id = ? AND revoker_id IS NULL',
                [$memberId, $roleId],
            )
            ->fetchColumn(0);
        if ($existing !== false && $existing !== null) {
            $connection->update('member_roles', [
                'branch_id' => $branchId,
                'modified' => $now,
                'modified_by' => $memberId,
            ], ['id' => (int)$existing]);

            return;
        }

        $connection->insert('member_roles', [
            'member_id' => $memberId,
            'role_id' => $roleId,
            'branch_id' => $branchId,
            'start_on' => substr($now, 0, 10),
            'approver_id' => $memberId,
            'created' => $now,
            'modified' => $now,
            'created_by' => $memberId,
            'modified_by' => $memberId,
        ]);
    }

    /**
     * Mark final tenant lifecycle state after provisioning.
     */
    private function markTenantStatus(
        Connection $connection,
        string $tenantId,
        string $status,
        ?string $schemaVersion,
    ): void {
        $fields = [
            'status' => $status,
            'schema_version' => $schemaVersion,
            'modified_at' => $this->now(),
        ];
        if ($status === TenantProvisioningRequest::STATUS_ACTIVE) {
            $fields['activated_at'] = $this->now();
        }

        $connection->update('tenants', $fields, ['id' => $tenantId]);
        TenantHostResolver::clearCache(self::PLATFORM_CONNECTION);
    }

    /**
     * @return list<string>
     */
    private function pluginsWithMigrations(): array
    {
        $plugins = [];
        foreach (Plugin::getCollection() as $name => $plugin) {
            $migrationPath = $plugin->getPath() . 'config' . DS . 'Migrations';
            if (is_dir($migrationPath) && glob($migrationPath . DS . '*.php') !== []) {
                $plugins[] = (string)$name;
            }
        }

        return $plugins;
    }

    /**
     * Read the latest tenant migration version.
     */
    private function readSchemaVersion(): ?string
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get(self::TENANT_CONNECTION);
        $state = (new TenantMigrationCatalog())->inspect($connection);
        if (!$state->isCurrent()) {
            throw new RuntimeException(sprintf(
                'Tenant provisioning finished with incomplete migration state: %s.',
                json_encode($state->toMetadata(), JSON_UNESCAPED_SLASHES),
            ));
        }

        return $state->targetVersion;
    }

    /**
     * Validate tenant slug format.
     */
    private function assertValidSlug(string $slug): void
    {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?$/', $slug)) {
            throw new RuntimeException(
                'Invalid slug. Use 1-80 lowercase letters, numbers, and hyphens; no edge hyphens.',
            );
        }
    }

    /**
     * Validate DNS hostname input.
     */
    private function assertValidHost(string $host): void
    {
        $pattern = '/^(?=.{1,255}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*'
            . '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/';
        if (strlen($host) > 255 || !preg_match($pattern, $host)) {
            throw new RuntimeException(
                'Invalid host. Use a DNS hostname with lowercase letters, numbers, dots, and hyphens.',
            );
        }
    }

    /**
     * Validate PostgreSQL identifier input.
     */
    private function assertValidIdentifier(string $identifier, string $label): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,62}$/', $identifier)) {
            throw new RuntimeException(sprintf(
                'Invalid %s. Use 1-63 lowercase letters, numbers, and underscores; start with a letter.',
                $label,
            ));
        }
    }

    /**
     * Validate Azure blob container naming.
     */
    private function assertValidBlobContainer(string $container): void
    {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])$/', $container) || str_contains($container, '--')) {
            throw new RuntimeException(
                'Invalid blob container. Use 3-63 lowercase letters, numbers, and single hyphens.',
            );
        }
    }

    /**
     * Generate a tenant database password.
     */
    private function generatePassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Return current UTC database timestamp.
     */
    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Read a boolean environment flag.
     */
    private function envFlag(string $name): bool
    {
        return in_array(strtolower((string)env($name, 'false')), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Determine whether automatic tenant database creation is enabled.
     */
    private function shouldCreateDatabase(TenantProvisioningRequest $request, Connection $platform): bool
    {
        if ($request->createDatabase) {
            return true;
        }

        return $this->envFlag('KMP_AUTO_CREATE_DATABASES')
            && ($platform->config()['driver'] ?? null) === Postgres::class;
    }

    /**
     * Emit progress when a callback is available.
     */
    private function progress(?callable $progress, string $level, string $message): void
    {
        if ($progress !== null) {
            $progress($level, $message);
        }
    }
}
