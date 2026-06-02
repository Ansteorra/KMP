<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\TenantHostResolver;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\SensitiveString;
use App\Services\Secrets\WritableSecretStoreInterface;
use Cake\Command\Command;
use Cake\Command\SchemacacheClearCommand;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Plugin;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\FactoryLocator;
use Cake\ORM\Locator\TableLocator;
use Cake\Utility\Text;
use Migrations\Command\MigrateCommand;
use RuntimeException;

/**
 * Provisions a tenant registry row and tenant database resources.
 */
class TenantProvisionCommand extends Command
{
    private const PLATFORM_CONNECTION = 'platform';
    private const TENANT_CONNECTION = 'tenant_provision';
    private const STATUS_PROVISIONING = 'provisioning';
    private const STATUS_ACTIVE = 'active';

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'tenant provision';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Provision a tenant database and platform metadata.')
            ->addArgument('slug', [
                'help' => 'Tenant slug. Lowercase letters, numbers, and hyphens only.',
                'required' => true,
            ])
            ->addOption('display-name', [
                'help' => 'Human-readable tenant name.',
            ])
            ->addOption('name', [
                'help' => 'Alias for --display-name.',
            ])
            ->addOption('host', [
                'help' => 'Primary hostname for the tenant.',
                'required' => true,
            ])
            ->addOption('db-server', [
                'help' => 'Tenant database server/host. Defaults to platform DB host.',
            ])
            ->addOption('db-name', [
                'help' => 'Tenant database name. Defaults to kmp_tenant_<slug>.',
            ])
            ->addOption('db-role', [
                'help' => 'Tenant database role/user. Defaults to kmp_tenant_<slug>_role.',
            ])
            ->addOption('blob-container', [
                'help' => 'Blob container name to record for tenant documents. Defaults to tenant-<slug>.',
            ])
            ->addOption('status', [
                'help' => 'Final status after provisioning.',
                'default' => self::STATUS_ACTIVE,
                'choices' => [self::STATUS_ACTIVE, self::STATUS_PROVISIONING],
            ])
            ->addOption('create-database', [
                'help' => 'Create/update the PostgreSQL database and role.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('skip-create-database', [
                'help' => 'Only write metadata/secrets and assume database resources already exist.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('skip-migrations', [
                'help' => 'Skip tenant app migrations. Final status cannot be active when this is set.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('smoke-table', [
                'help' => 'Table expected to exist after migrations.',
                'default' => 'members',
            ])
            ->addOption('show-password', [
                'help' => 'Print the generated/reused DB password. Intended for local/dev use only.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('rotate-password', [
                'help' => 'Generate and store a new database password before provisioning.',
                'boolean' => true,
                'default' => false,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $slug = strtolower((string)$args->getArgument('slug'));
        $displayName = trim((string)($args->getOption('display-name') ?: $args->getOption('name') ?: $slug));
        $host = strtolower(trim((string)$args->getOption('host')));
        $dbName = (string)($args->getOption('db-name') ?: 'kmp_tenant_' . str_replace('-', '_', $slug));
        $dbRole = (string)($args->getOption('db-role') ?: $dbName . '_role');
        $blobContainer = (string)($args->getOption('blob-container') ?: 'tenant-' . $slug);
        $skipCreateDatabase = (bool)$args->getOption('skip-create-database');
        $runMigrations = !(bool)$args->getOption('skip-migrations');
        $finalStatus = (string)$args->getOption('status');

        try {
            $this->assertValidSlug($slug);
            $this->assertValidHost($host);
            $this->assertValidIdentifier($dbName, 'database name');
            $this->assertValidIdentifier($dbRole, 'database role');
            $this->assertValidBlobContainer($blobContainer);

            if (!$runMigrations && $finalStatus === self::STATUS_ACTIVE) {
                throw new RuntimeException('Cannot set status=active when --skip-migrations is used.');
            }
            if ($skipCreateDatabase && (bool)$args->getOption('create-database')) {
                throw new RuntimeException('Use either --create-database or --skip-create-database, not both.');
            }
            $platform = $this->platformConnection();
            if (!$skipCreateDatabase && !$this->shouldCreateDatabase($args, $platform)) {
                throw new RuntimeException(
                    'Database creation is disabled. Set KMP_AUTO_CREATE_DATABASES=true, pass --create-database, '
                    . 'or use --skip-create-database for metadata-only provisioning.',
                );
            }
            if ($displayName === '') {
                throw new RuntimeException('A non-empty --display-name or --name is required.');
            }

            $dbServer = (string)($args->getOption('db-server') ?: ($platform->config()['host'] ?? 'localhost'));
            $secretName = sprintf('tenant.%s.db.password', $slug);
            $password = $this->ensurePassword($secretName, (bool)$args->getOption('rotate-password'));

            $tenant = $this->upsertTenantMetadata(
                $platform,
                $slug,
                $displayName,
                $host,
                $dbServer,
                $dbName,
                $dbRole,
                $blobContainer,
            );
            $this->upsertTenantHost($platform, $tenant['id'], $host);

            $io->out(sprintf('Tenant metadata ready: %s (%s)', $slug, $tenant['id']));
            $io->out(sprintf('Stored DB password secret: %s', $secretName));
            if ((bool)$args->getOption('show-password')) {
                $io->out('Generated database password: ' . $password->reveal());
            }

            if (!$skipCreateDatabase) {
                $this->maybeCreateDatabase($platform, $args, $dbName, $dbRole, $password, $io);
            } else {
                $io->warning('Skipping database creation; assuming resources already exist.');
            }

            $schemaVersion = null;
            if ($runMigrations) {
                $schemaVersion = $this->runTenantMigrations(
                    $dbServer,
                    $dbName,
                    $dbRole,
                    $password,
                    (string)$args->getOption('smoke-table'),
                    $io,
                );
            } else {
                $io->warning('Skipping tenant migrations; tenant will remain provisioning.');
            }

            $this->markTenantStatus($platform, $tenant['id'], $finalStatus, $schemaVersion);
            $io->success(sprintf('Tenant %s status is %s.', $slug, $finalStatus));

            return self::CODE_SUCCESS;
        } catch (RuntimeException $e) {
            $io->err($e->getMessage());

            return self::CODE_ERROR;
        }
    }

    /**
     * Get the platform metadata connection.
     *
     * @return \Cake\Database\Connection
     */
    private function platformConnection(): Connection
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get(self::PLATFORM_CONNECTION);

        return $connection;
    }

    /**
     * Ensure the tenant DB password secret exists and return it.
     *
     * @param string $secretName Secret name
     * @param bool $rotate Whether to rotate the secret
     * @return \App\Services\Secrets\SensitiveString
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
     * Create or update tenant metadata.
     *
     * @return array<string, mixed>
     */
    private function upsertTenantMetadata(
        Connection $connection,
        string $slug,
        string $displayName,
        string $host,
        string $dbServer,
        string $dbName,
        string $dbRole,
        string $blobContainer,
    ): array {
        $now = $this->now();
        $tenant = $connection->execute('SELECT * FROM tenants WHERE slug = ?', [$slug])->fetch('assoc') ?: null;
        $tenantConfig = json_encode(['documents' => ['blob_container' => $blobContainer]], JSON_UNESCAPED_SLASHES);
        if ($tenantConfig === false) {
            throw new RuntimeException('Unable to encode tenant configuration JSON.');
        }

        if ($tenant === null) {
            $tenant = [
                'id' => Text::uuid(),
                'slug' => $slug,
                'display_name' => $displayName,
                'status' => self::STATUS_PROVISIONING,
                'region' => 'us',
                'primary_host' => $host,
                'db_server' => $dbServer,
                'db_name' => $dbName,
                'db_role' => $dbRole,
                'key_vault_prefix' => 'tenant.' . $slug,
                'schema_version' => null,
                'feature_flags' => '{}',
                'tenant_config' => $tenantConfig,
                'queue_concurrency_limit' => 5,
                'created_at' => $now,
                'activated_at' => null,
                'suspended_at' => null,
                'archived_at' => null,
                'modified_at' => $now,
            ];
            $connection->insert('tenants', $tenant);

            return $tenant;
        }

        foreach (['db_name' => $dbName, 'db_role' => $dbRole] as $field => $value) {
            if ((string)$tenant[$field] !== $value) {
                throw new RuntimeException(sprintf(
                    'Tenant "%s" already exists with %s "%s"; refusing to change it to "%s".',
                    $slug,
                    $field,
                    (string)$tenant[$field],
                    $value,
                ));
            }
        }

        $connection->update('tenants', [
            'display_name' => $displayName,
            'status' => self::STATUS_PROVISIONING,
            'primary_host' => $host,
            'db_server' => $dbServer,
            'key_vault_prefix' => 'tenant.' . $slug,
            'tenant_config' => $tenantConfig,
            'modified_at' => $now,
        ], ['id' => $tenant['id']]);

        return $connection->execute('SELECT * FROM tenants WHERE slug = ?', [$slug])->fetch('assoc');
    }

    /**
     * Create or update the primary tenant host row.
     *
     * @param \Cake\Database\Connection $connection Platform connection
     * @param string $tenantId Tenant UUID
     * @param string $host Normalized hostname
     * @return void
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
                'status' => self::STATUS_ACTIVE,
                'created_at' => $now,
                'modified_at' => $now,
            ]);
            TenantHostResolver::clearCache(self::PLATFORM_CONNECTION);

            return;
        }

        $connection->update('tenant_hosts', [
            'host' => $host,
            'is_primary' => true,
            'status' => self::STATUS_ACTIVE,
            'modified_at' => $now,
        ], ['id' => $hostRow['id']]);
        TenantHostResolver::clearCache(self::PLATFORM_CONNECTION);
    }

    /**
     * Create/update PostgreSQL database resources.
     *
     * @return void
     */
    private function maybeCreateDatabase(
        Connection $platform,
        Arguments $args,
        string $dbName,
        string $dbRole,
        SensitiveString $password,
        ConsoleIo $io,
    ): void {
        if (($platform->config()['driver'] ?? null) !== Postgres::class) {
            throw new RuntimeException('Automatic tenant database creation requires a PostgreSQL platform datasource.');
        }

        $driver = $platform->getDriver();
        $quotedRole = $driver->quoteIdentifier($dbRole);
        $quotedDb = $driver->quoteIdentifier($dbName);
        $quotedPassword = $driver->quote($password->reveal());

        $roleExists = (bool)$platform->execute('SELECT 1 FROM pg_roles WHERE rolname = ?', [$dbRole])->fetchColumn(0);
        if (!$roleExists) {
            $platform->execute(sprintf('CREATE ROLE %s LOGIN PASSWORD %s', $quotedRole, $quotedPassword));
            $io->out(sprintf('Created PostgreSQL role: %s', $dbRole));
        } else {
            $platform->execute(sprintf('ALTER ROLE %s WITH LOGIN PASSWORD %s', $quotedRole, $quotedPassword));
            $io->out(sprintf('Updated PostgreSQL role password: %s', $dbRole));
        }

        $dbExists = (bool)$platform->execute('SELECT 1 FROM pg_database WHERE datname = ?', [$dbName])->fetchColumn(0);
        if (!$dbExists) {
            $platform->execute(sprintf('CREATE DATABASE %s OWNER %s', $quotedDb, $quotedRole));
            $io->out(sprintf('Created PostgreSQL database: %s', $dbName));
        }

        $platform->execute(sprintf('GRANT ALL PRIVILEGES ON DATABASE %s TO %s', $quotedDb, $quotedRole));
        $this->grantTenantSchemaPrivileges($platform, $dbName, $dbRole, $password);
    }

    /**
     * Ensure the tenant role can create migration tables in the tenant DB public schema.
     */
    private function grantTenantSchemaPrivileges(
        Connection $platform,
        string $dbName,
        string $dbRole,
        SensitiveString $password,
    ): void {
        $serverConfig = $platform->config();
        $baseConfig = ConnectionManager::getConfig('default');
        if ($baseConfig === null) {
            throw new RuntimeException('Default datasource configuration is not available.');
        }
        unset($baseConfig['url']);

        $adminConnectionName = 'tenant_schema_admin';
        if (in_array($adminConnectionName, ConnectionManager::configured(), true)) {
            ConnectionManager::drop($adminConnectionName);
        }
        ConnectionManager::setConfig($adminConnectionName, array_merge($baseConfig, [
            'host' => $serverConfig['host'] ?? 'localhost',
            'database' => $dbName,
            'username' => $serverConfig['username'] ?? null,
            'password' => $serverConfig['password'] ?? null,
        ]));

        try {
            /** @var \Cake\Database\Connection $adminConnection */
            $adminConnection = ConnectionManager::get($adminConnectionName);
            $driver = $adminConnection->getDriver();
            $quotedRole = $driver->quoteIdentifier($dbRole);
            $adminConnection->execute(sprintf('ALTER SCHEMA public OWNER TO %s', $quotedRole));
            $adminConnection->execute(sprintf('GRANT ALL ON SCHEMA public TO %s', $quotedRole));
            $this->verifyTenantRoleCanCreateInPublicSchema($dbName, $dbRole, $password);
        } finally {
            ConnectionManager::drop($adminConnectionName);
        }
    }

    /**
     * Fail fast if PostgreSQL schema privileges are still insufficient.
     */
    private function verifyTenantRoleCanCreateInPublicSchema(
        string $dbName,
        string $dbRole,
        SensitiveString $password,
    ): void {
        $probeConnectionName = 'tenant_schema_probe';
        if (in_array($probeConnectionName, ConnectionManager::configured(), true)) {
            ConnectionManager::drop($probeConnectionName);
        }
        $this->configureNamedTenantConnection($probeConnectionName, $dbName, $dbRole, $password);

        try {
            /** @var \Cake\Database\Connection $probeConnection */
            $probeConnection = ConnectionManager::get($probeConnectionName);
            $probeConnection->execute('CREATE TABLE IF NOT EXISTS __kmp_schema_privilege_probe (id INTEGER)');
            $probeConnection->execute('DROP TABLE IF EXISTS __kmp_schema_privilege_probe');
        } finally {
            ConnectionManager::drop($probeConnectionName);
        }
    }

    /**
     * Run application and plugin migrations on the tenant connection.
     *
     * @return string|null Latest migration version if available
     */
    private function runTenantMigrations(
        string $dbServer,
        string $dbName,
        string $dbRole,
        SensitiveString $password,
        string $smokeTable,
        ConsoleIo $io,
    ): ?string {
        $previousTenantConfig = ConnectionManager::getConfig(self::TENANT_CONNECTION);
        $previousDefaultConfig = ConnectionManager::getConfig('default');
        $previousDefaultAlias = ConnectionManager::aliases()['default'] ?? null;
        $previousLocator = FactoryLocator::get('Table');
        $this->configureTenantConnection($dbServer, $dbName, $dbRole, $password);
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
            $this->executeCommand(SchemacacheClearCommand::class, ['--connection', self::TENANT_CONNECTION], $io);
            $result = (int)$this->executeCommand(new MigrateCommand(), [
                'migrate',
                '--connection',
                self::TENANT_CONNECTION,
            ], $io);
            if ($result !== self::CODE_SUCCESS) {
                throw new RuntimeException('Tenant application migrations failed.');
            }

            foreach ($this->pluginsWithMigrations() as $plugin) {
                $ensureTenantConnection();
                $result = (int)$this->executeCommand(new MigrateCommand(), [
                    'migrate',
                    '--connection',
                    self::TENANT_CONNECTION,
                    '--plugin',
                    $plugin,
                ], $io);
                if ($result !== self::CODE_SUCCESS) {
                    throw new RuntimeException(sprintf('Tenant plugin migrations failed for %s.', $plugin));
                }
            }

            $this->smokeTestTenantDatabase($smokeTable, $io);

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
     * Configure the transient tenant migration connection.
     *
     * @return void
     */
    private function configureTenantConnection(
        string $dbServer,
        string $dbName,
        string $dbRole,
        SensitiveString $password,
    ): void {
        $baseConfig = ConnectionManager::getConfig('default');
        if ($baseConfig === null) {
            throw new RuntimeException('Default datasource configuration is not available.');
        }
        unset($baseConfig['url']);

        if (in_array(self::TENANT_CONNECTION, ConnectionManager::configured(), true)) {
            ConnectionManager::drop(self::TENANT_CONNECTION);
        }

        ConnectionManager::setConfig(self::TENANT_CONNECTION, array_merge($baseConfig, [
            'host' => $dbServer,
            'database' => $dbName,
            'username' => $dbRole,
            'password' => $password->reveal(),
        ]));
    }

    /**
     * Configure a transient tenant connection against the platform DB host.
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
     * Verify that the migrated tenant database is reachable and has the expected table.
     *
     * @param string $table Table expected after migrations
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    private function smokeTestTenantDatabase(string $table, ConsoleIo $io): void
    {
        $this->assertValidIdentifier($table, 'smoke-test table');
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get(self::TENANT_CONNECTION);
        if (!in_array($table, $connection->getSchemaCollection()->listTables(), true)) {
            throw new RuntimeException(sprintf('Tenant smoke test failed: expected table "%s" was not found.', $table));
        }

        $connection->execute('SELECT 1');
        $io->success(sprintf('Tenant smoke test passed (%s exists).', $table));
    }

    /**
     * Persist final tenant lifecycle status.
     *
     * @param \Cake\Database\Connection $connection Platform connection
     * @param string $tenantId Tenant UUID
     * @param string $status Final status
     * @param string|null $schemaVersion Latest schema version
     * @return void
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
        if ($status === self::STATUS_ACTIVE) {
            $fields['activated_at'] = $this->now();
        }

        $connection->update('tenants', $fields, ['id' => $tenantId]);
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
     * Read the latest Phinx migration version from the tenant connection.
     *
     * @return string|null Latest schema version
     */
    private function readSchemaVersion(): ?string
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get(self::TENANT_CONNECTION);
        if (!in_array('phinxlog', $connection->getSchemaCollection()->listTables(), true)) {
            return null;
        }

        $version = $connection->execute('SELECT MAX(version) FROM phinxlog')->fetchColumn(0);

        return $version === false || $version === null ? null : (string)$version;
    }

    /**
     * Validate tenant slug syntax.
     *
     * @param string $slug Tenant slug
     * @return void
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
     * Validate DNS host syntax.
     *
     * @param string $host Normalized host
     * @return void
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
     * Validate a PostgreSQL identifier before quoting it.
     *
     * @param string $identifier Identifier value
     * @param string $label Human-readable label
     * @return void
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
     * Validate Azure-compatible blob container syntax.
     *
     * @param string $container Container name
     * @return void
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
     * Generate a random URL-safe database password.
     *
     * @return string Password
     */
    private function generatePassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Current UTC timestamp for platform rows.
     *
     * @return string Timestamp
     */
    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Read a boolean-like environment variable.
     *
     * @param string $name Environment variable name
     * @return bool
     */
    private function envFlag(string $name): bool
    {
        return in_array(strtolower((string)env($name, 'false')), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Return true when tenant DB creation is explicitly enabled.
     *
     * @param \Cake\Console\Arguments $args Command args
     * @return bool
     */
    private function shouldCreateDatabase(Arguments $args, Connection $platform): bool
    {
        if ((bool)$args->getOption('create-database')) {
            return true;
        }

        return $this->envFlag('KMP_AUTO_CREATE_DATABASES')
            && ($platform->config()['driver'] ?? null) === Postgres::class;
    }
}
