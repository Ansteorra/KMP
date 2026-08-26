<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\KMP\TenantMetadata;
use App\Services\TenantConnectionManager;
use Cake\Datasource\ConnectionManager;

/**
 * Inspects migration history through an isolated tenant connection scope.
 */
final class TenantMigrationStateInspector implements TenantMigrationStateInspectorInterface
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly TenantConnectionManager $connectionManager,
        private readonly TenantMigrationCatalog $catalog,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function inspect(TenantMetadata $tenant): TenantMigrationState
    {
        return $this->connectionManager->withTenant(
            $tenant,
            function (): TenantMigrationState {
                /** @var \Cake\Database\Connection $connection */
                $connection = ConnectionManager::get(TenantConnectionManager::CONNECTION_ALIAS);

                return $this->catalog->inspect($connection);
            },
        );
    }
}
