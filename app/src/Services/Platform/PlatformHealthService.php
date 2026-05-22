<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Database\Exception\DatabaseException;
use Cake\Database\Exception\MissingConnectionException;
use Cake\Database\Exception\MissingDriverException;
use Cake\Database\Exception\MissingExtensionException;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\Exception\MissingDatasourceConfigException;
use Cake\Datasource\Exception\MissingDatasourceException;
use Cake\Log\Log;
use PDOException;

/**
 * Checks platform metadata database availability with optional bounded retry.
 */
class PlatformHealthService implements PlatformHealthCheckerInterface
{
    /**
     * Constructor.
     *
     * @param string $connectionName Platform datasource alias
     * @param int $retryAttempts Number of retry attempts after the first failure
     * @param int $retryDelayMs Delay between retries in milliseconds
     */
    public function __construct(
        private readonly string $connectionName = 'platform',
        private readonly int $retryAttempts = 0,
        private readonly int $retryDelayMs = 0,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function check(): PlatformHealthStatus
    {
        $attempts = max(0, $this->retryAttempts) + 1;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $this->probe();

                return PlatformHealthStatus::healthy($this->connectionName, $attempt - 1);
            } catch (
                DatabaseException |
                MissingConnectionException |
                MissingDatasourceConfigException |
                MissingDatasourceException |
                MissingDriverException |
                MissingExtensionException |
                PDOException $exception
            ) {
                if ($attempt < $attempts) {
                    $this->delayRetry();
                    continue;
                }

                Log::error(sprintf(
                    'Platform metadata database degraded on connection "%s": %s',
                    $this->connectionName,
                    $exception::class,
                ));

                return PlatformHealthStatus::degraded($this->connectionName, $exception::class, $attempt - 1);
            }
        }

        return PlatformHealthStatus::degraded($this->connectionName, 'unknown', max(0, $this->retryAttempts));
    }

    /**
     * Execute a minimal availability probe.
     *
     * @return void
     */
    private function probe(): void
    {
        $connection = ConnectionManager::get($this->connectionName);
        $connection->execute('SELECT 1')->fetch('assoc');
    }

    /**
     * Delay between configured retry attempts.
     *
     * @return void
     */
    private function delayRetry(): void
    {
        if ($this->retryDelayMs > 0) {
            usleep($this->retryDelayMs * 1000);
        }
    }
}
