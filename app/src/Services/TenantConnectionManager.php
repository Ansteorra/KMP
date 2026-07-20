<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Services\Secrets\SecretStoreInterface;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\FactoryLocator;
use Cake\ORM\Locator\TableLocator;
use RuntimeException;

class TenantConnectionManager
{
    public const CONNECTION_ALIAS = 'tenant';

    /**
     * Tenant mail override applier.
     *
     * @var \App\Services\TenantMailConfigurator
     */
    private readonly TenantMailConfigurator $mailConfigurator;

    /**
     * Constructor.
     *
     * @param \App\Services\Secrets\SecretStoreInterface $secretStore Secret store
     * @param \App\Services\TenantMailConfigurator|null $mailConfigurator Tenant mail override applier
     */
    public function __construct(
        private readonly SecretStoreInterface $secretStore,
        ?TenantMailConfigurator $mailConfigurator = null,
    ) {
        $this->mailConfigurator = $mailConfigurator ?? new TenantMailConfigurator($secretStore);
    }

    /**
     * Run work with a tenant connection alias and isolated table locator.
     *
     * @template TReturn
     * @param \App\KMP\TenantMetadata $tenant Tenant metadata
     * @param callable():TReturn $callback Callback to run
     * @return TReturn
     */
    public function withTenant(TenantMetadata $tenant, callable $callback): mixed
    {
        $tenantConfig = $this->buildConnectionConfig($tenant);
        $previousTenantConfig = ConnectionManager::getConfig(self::CONNECTION_ALIAS);
        $previousDefaultConfig = ConnectionManager::getConfig('default');
        $previousDefaultAlias = ConnectionManager::aliases()['default'] ?? null;
        $previousLocator = FactoryLocator::get('Table');

        ConnectionManager::drop(self::CONNECTION_ALIAS);
        ConnectionManager::setConfig(self::CONNECTION_ALIAS, $tenantConfig);
        if ($previousDefaultAlias !== null) {
            ConnectionManager::dropAlias('default');
        } else {
            ConnectionManager::drop('default');
        }
        ConnectionManager::alias(self::CONNECTION_ALIAS, 'default');
        FactoryLocator::add('Table', new TableLocator());
        $restoreMail = $this->mailConfigurator->apply($tenant);

        try {
            return TenantContext::with($tenant, $callback);
        } finally {
            $restoreMail();
            $this->assertNoOpenTransaction();
            ConnectionManager::dropAlias('default');
            ConnectionManager::drop(self::CONNECTION_ALIAS);
            if ($previousDefaultAlias !== null) {
                ConnectionManager::alias($previousDefaultAlias, 'default');
            } elseif ($previousDefaultConfig !== null) {
                ConnectionManager::setConfig('default', $previousDefaultConfig);
            }
            if ($previousTenantConfig !== null) {
                ConnectionManager::setConfig(self::CONNECTION_ALIAS, $previousTenantConfig);
            }
            FactoryLocator::add('Table', $previousLocator);
        }
    }

    /**
     * Build the CakePHP connection config for a tenant DB.
     *
     * @param \App\KMP\TenantMetadata $tenant Tenant metadata
     * @return array<string, mixed>
     */
    public function buildConnectionConfig(TenantMetadata $tenant): array
    {
        $password = $this->secretStore->get(sprintf('tenant.%s.db.password', $tenant->slug));
        if ($password === null) {
            throw new RuntimeException(sprintf('Missing database password secret for tenant "%s".', $tenant->slug));
        }

        $baseConfig = ConnectionManager::getConfig('default');
        if ($baseConfig === null) {
            throw new RuntimeException('Default datasource configuration is not available.');
        }

        unset($baseConfig['url']);

        return array_merge($baseConfig, [
            'name' => self::CONNECTION_ALIAS,
            'host' => $tenant->dbServer,
            'database' => $tenant->dbName,
            'username' => $tenant->dbRole,
            'password' => $password->reveal(),
        ]);
    }

    /**
     * Roll back and fail if tenant-scoped work leaves a transaction open.
     *
     * @return void
     */
    private function assertNoOpenTransaction(): void
    {
        foreach ([self::CONNECTION_ALIAS, 'default'] as $connectionName) {
            if (!in_array($connectionName, ConnectionManager::configured(), true)) {
                continue;
            }

            /** @var \Cake\Database\Connection $connection */
            $connection = ConnectionManager::get($connectionName);
            if ($connection instanceof Connection && $connection->inTransaction()) {
                $connection->rollback();
                throw new RuntimeException('Tenant connection scope ended with an open transaction.');
            }
        }
    }
}
