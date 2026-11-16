<?php
declare(strict_types=1);

namespace App\Database\Driver;

use App\Services\PostgresPool;
use Cake\Database\Driver\Postgres;
use PDO;
use SensitiveParameter;

/** Preserve Cake's real database metadata while connecting through the local pool. */
class PooledPostgres extends Postgres
{
    /** @inheritDoc */
    protected function createPdo(string $dsn, #[SensitiveParameter] array $config): PDO
    {
        $dsn = PostgresPool::dsn($config, (string)$config['poolDirectory']);
        $config['flags'][PDO::ATTR_PERSISTENT] = false;

        return parent::createPdo($dsn, $config);
    }
}
