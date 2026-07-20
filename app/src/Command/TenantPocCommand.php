<?php
declare(strict_types=1);

namespace App\Command;

use App\KMP\TenantMetadata;
use App\Services\Platform\TenantHostResolver;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\TenantConnectionManager;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use RuntimeException;

/**
 * Automates the two-tenant staging proof-of-concept harness.
 */
class TenantPocCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'tenant_poc';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Provision and verify a safe two-tenant platform POC.')
            ->addOption('yes', [
                'help' => 'Required confirmation that this POC may create/update tenant metadata and databases.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('verify-only', [
                'help' => 'Skip provisioning and only verify host resolution plus tenant smoke checks.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('allow-production', [
                'help' => 'Allow execution in production when the matching environment flag is set.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('tenant-a', [
                'help' => 'First tenant slug.',
                'default' => 'poc-alpha',
            ])
            ->addOption('tenant-b', [
                'help' => 'Second tenant slug.',
                'default' => 'poc-beta',
            ])
            ->addOption('host-a', [
                'help' => 'First tenant hostname.',
                'default' => 'poc-alpha.staging.example.test',
            ])
            ->addOption('host-b', [
                'help' => 'Second tenant hostname.',
                'default' => 'poc-beta.staging.example.test',
            ])
            ->addOption('display-name-a', [
                'help' => 'First tenant display name.',
                'default' => 'POC Alpha',
            ])
            ->addOption('display-name-b', [
                'help' => 'Second tenant display name.',
                'default' => 'POC Beta',
            ])
            ->addOption('db-prefix', [
                'help' => 'Prefix for generated tenant database and role names.',
                'default' => 'kmp_poc',
            ])
            ->addOption('db-server', [
                'help' => 'Tenant database server. Defaults to the platform DB host.',
            ])
            ->addOption('create-database', [
                'help' => 'Pass --create-database through to tenant provisioning.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('skip-create-database', [
                'help' => 'Pass --skip-create-database through to tenant provisioning.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('smoke-table', [
                'help' => 'Tenant table queried during the smoke check.',
                'default' => 'members',
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $this->assertExecutionAllowed($args);
            $smokeTable = (string)$args->getOption('smoke-table');
            $this->assertValidIdentifier($smokeTable, 'smoke table');
            $tenants = $this->tenantDefinitions($args);
            $this->assertDistinctTenants($tenants);

            if (!(bool)$args->getOption('verify-only')) {
                $platform = $this->platformConnection();
                if (($platform->config()['driver'] ?? null) !== Postgres::class) {
                    throw new RuntimeException('Tenant POC provisioning requires a PostgreSQL platform datasource.');
                }
                foreach ($tenants as $tenant) {
                    $this->provisionTenant($tenant, $args, $io);
                }
            } else {
                $io->warning('Verify-only mode: skipping tenant provisioning.');
            }

            $resolver = new TenantHostResolver();
            $manager = new TenantConnectionManager(SecretStoreFactory::fromConfig());
            foreach ($tenants as $tenant) {
                $metadata = $resolver->resolve($tenant['host']);
                if ($metadata === null) {
                    throw new RuntimeException(sprintf('Host %s did not resolve to a tenant.', $tenant['host']));
                }
                if ($metadata->slug !== $tenant['slug'] || $metadata->status !== 'active') {
                    throw new RuntimeException(sprintf(
                        'Host %s resolved to unexpected tenant %s with status %s.',
                        $tenant['host'],
                        $metadata->slug,
                        $metadata->status,
                    ));
                }
                $this->smokeTenant($manager, $metadata, $smokeTable, $io);
            }

            $io->success('Two-tenant POC verification passed.');

            return self::CODE_SUCCESS;
        } catch (RuntimeException $e) {
            $io->err($e->getMessage());

            return self::CODE_ERROR;
        }
    }

    /**
     * @return list<array{slug:string,host:string,displayName:string,dbName:string,dbRole:string,blobContainer:string}>
     */
    private function tenantDefinitions(Arguments $args): array
    {
        $dbPrefix = strtolower((string)$args->getOption('db-prefix'));
        $this->assertValidIdentifier($dbPrefix, 'database prefix');

        return [
            $this->tenantDefinition(
                (string)$args->getOption('tenant-a'),
                (string)$args->getOption('host-a'),
                (string)$args->getOption('display-name-a'),
                $dbPrefix,
            ),
            $this->tenantDefinition(
                (string)$args->getOption('tenant-b'),
                (string)$args->getOption('host-b'),
                (string)$args->getOption('display-name-b'),
                $dbPrefix,
            ),
        ];
    }

    /**
     * @return array{slug:string,host:string,displayName:string,dbName:string,dbRole:string,blobContainer:string}
     */
    private function tenantDefinition(string $slug, string $host, string $displayName, string $dbPrefix): array
    {
        $slug = strtolower(trim($slug));
        $host = strtolower(trim($host));
        $displayName = trim($displayName);
        $slugIdentifier = str_replace('-', '_', $slug);
        $dbName = $dbPrefix . '_' . $slugIdentifier;
        $dbRole = $dbName . '_role';
        $blobContainer = 'tenant-' . $slug;

        $this->assertValidSlug($slug);
        $this->assertValidHost($host);
        $this->assertValidIdentifier($dbName, 'database name');
        $this->assertValidIdentifier($dbRole, 'database role');
        if ($displayName === '') {
            throw new RuntimeException('Tenant display names must not be empty.');
        }

        return [
            'slug' => $slug,
            'host' => $host,
            'displayName' => $displayName,
            'dbName' => $dbName,
            'dbRole' => $dbRole,
            'blobContainer' => $blobContainer,
        ];
    }

    /**
     * @param list<array{slug:string,host:string}> $tenants Tenant definitions
     */
    private function assertDistinctTenants(array $tenants): void
    {
        $slugs = array_column($tenants, 'slug');
        $hosts = array_column($tenants, 'host');
        if (count(array_unique($slugs)) !== count($slugs)) {
            throw new RuntimeException('Tenant slugs must be distinct.');
        }
        if (count(array_unique($hosts)) !== count($hosts)) {
            throw new RuntimeException('Tenant hosts must be distinct.');
        }
    }

    /**
     * @param array{slug:string,host:string,displayName:string,dbName:string,dbRole:string,blobContainer:string} $tenant Tenant definition
     */
    private function provisionTenant(array $tenant, Arguments $args, ConsoleIo $io): void
    {
        $commandArgs = [
            $tenant['slug'],
            '--display-name',
            $tenant['displayName'],
            '--host',
            $tenant['host'],
            '--db-name',
            $tenant['dbName'],
            '--db-role',
            $tenant['dbRole'],
            '--blob-container',
            $tenant['blobContainer'],
            '--status',
            'active',
            '--smoke-table',
            (string)$args->getOption('smoke-table'),
        ];
        $dbServer = $args->getOption('db-server');
        if ($dbServer !== null && $dbServer !== '') {
            $commandArgs[] = '--db-server';
            $commandArgs[] = (string)$dbServer;
        }
        if ((bool)$args->getOption('create-database')) {
            $commandArgs[] = '--create-database';
        }
        if ((bool)$args->getOption('skip-create-database')) {
            $commandArgs[] = '--skip-create-database';
        }

        $io->out(sprintf('Provisioning tenant %s for host %s...', $tenant['slug'], $tenant['host']));
        $result = (int)$this->executeCommand(new TenantProvisionCommand(), $commandArgs, $io);
        if ($result !== self::CODE_SUCCESS) {
            throw new RuntimeException(sprintf('Provisioning failed for tenant %s.', $tenant['slug']));
        }
    }

    /**
     * Verify a tenant database is reachable and has the expected table.
     *
     * @param \App\Services\TenantConnectionManager $manager Tenant connection manager
     * @param \App\KMP\TenantMetadata $tenant Tenant metadata
     * @param string $table Table to query
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    private function smokeTenant(
        TenantConnectionManager $manager,
        TenantMetadata $tenant,
        string $table,
        ConsoleIo $io,
    ): void {
        $manager->withTenant($tenant, function () use ($tenant, $table, $io): void {
            /** @var \Cake\Database\Connection $connection */
            $connection = ConnectionManager::get(TenantConnectionManager::CONNECTION_ALIAS);
            if (!in_array($table, $connection->getSchemaCollection()->listTables(), true)) {
                throw new RuntimeException(sprintf(
                    'Tenant %s smoke check failed: table %s was not found.',
                    $tenant->slug,
                    $table,
                ));
            }
            $quotedTable = $connection->getDriver()->quoteIdentifier($table);
            $count = (int)$connection->execute(sprintf('SELECT COUNT(*) FROM %s', $quotedTable))->fetchColumn(0);
            $io->success(sprintf('Tenant %s smoke passed: %s rows in %s.', $tenant->slug, $count, $table));
        });
    }

    /**
     * Enforce explicit safety gates before touching staging resources.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @return void
     */
    private function assertExecutionAllowed(Arguments $args): void
    {
        if (!(bool)$args->getOption('yes')) {
            throw new RuntimeException('Pass --yes to confirm the two-tenant POC run.');
        }
        if (!$this->envFlag('KMP_ENABLE_TENANT_POC')) {
            throw new RuntimeException('Set KMP_ENABLE_TENANT_POC=true to enable this non-production POC harness.');
        }
        if ($this->isProductionEnvironment()) {
            $productionAllowed = (bool)$args->getOption('allow-production')
                && $this->envFlag('KMP_ALLOW_PRODUCTION_TENANT_POC');
            if (!$productionAllowed) {
                throw new RuntimeException(
                    'Refusing to run in production without --allow-production and '
                    . 'KMP_ALLOW_PRODUCTION_TENANT_POC=true.',
                );
            }
        }
        if ((bool)$args->getOption('create-database') && (bool)$args->getOption('skip-create-database')) {
            throw new RuntimeException(
                'Use either --create-database or --skip-create-database, not both.',
            );
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
        $connection = ConnectionManager::get('platform');

        return $connection;
    }

    /**
     * Read a boolean environment flag.
     *
     * @param string $name Environment variable name
     * @return bool
     */
    private function envFlag(string $name): bool
    {
        return filter_var(env($name, false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Determine whether the command is running in production.
     *
     * @return bool
     */
    private function isProductionEnvironment(): bool
    {
        $appEnv = strtolower((string)env('APP_ENV', ''));
        $cakeEnv = strtolower((string)env('CAKE_ENV', ''));

        return $appEnv === 'production' || $cakeEnv === 'production';
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
            throw new RuntimeException('Invalid slug. Use lowercase letters, numbers, and hyphens.');
        }
    }

    /**
     * Validate DNS host syntax.
     *
     * @param string $host Tenant host
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
     * Validate a PostgreSQL identifier.
     *
     * @param string $identifier Identifier value
     * @param string $label Human-readable label
     * @return void
     */
    private function assertValidIdentifier(string $identifier, string $label): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,62}$/', $identifier)) {
            throw new RuntimeException(sprintf(
                'Invalid %s. Use lowercase letters, numbers, and underscores; start with a letter.',
                $label,
            ));
        }
    }
}
