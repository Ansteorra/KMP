<?php
declare(strict_types=1);

namespace App\Services\Secrets;

use Cake\Core\Configure;
use InvalidArgumentException;

class SecretStoreFactory
{
    /**
     * @param array<string, mixed>|null $config
     */
    public static function fromConfig(?array $config = null): SecretStoreInterface
    {
        $config ??= (array)Configure::read('Secrets');
        $driver = (string)($config['driver'] ?? 'file');

        return self::buildStore($driver, (array)($config['drivers'] ?? []));
    }

    /**
     * @param array<string, mixed> $drivers
     */
    private static function buildStore(string $name, array $drivers, array $stack = []): SecretStoreInterface
    {
        if (in_array($name, $stack, true)) {
            throw new InvalidArgumentException(sprintf('Recursive secrets driver reference detected for "%s".', $name));
        }
        $stack[] = $name;
        $config = (array)($drivers[$name] ?? []);

        return match ($name) {
            'file' => new FileSecretStore(
                (string)($config['path'] ?? CONFIG . 'secrets.local.json'),
                (string)($config['environment'] ?? 'local'),
                array_values((array)($config['allowInEnvironments'] ?? ['local', 'development', 'dev', 'test', 'ci'])),
            ),
            'env' => new EnvVarSecretStore((string)($config['prefix'] ?? 'KMP_SECRET_')),
            'database' => self::buildDatabase($config, $drivers, $stack),
            'chain' => self::buildChain($config, $drivers, $stack),
            default => throw new InvalidArgumentException(sprintf('Unknown secrets driver "%s".', $name)),
        };
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $drivers
     * @param list<string> $stack
     */
    private static function buildChain(array $config, array $drivers, array $stack): ChainSecretStore
    {
        $stores = [];
        foreach ((array)($config['drivers'] ?? []) as $driverName) {
            $driverName = (string)$driverName;
            if ($driverName === 'chain') {
                throw new InvalidArgumentException('ChainSecretStore cannot contain itself.');
            }
            $stores[$driverName] = self::buildStore($driverName, $drivers, $stack);
        }

        return new ChainSecretStore($stores, isset($config['writeTo']) ? (string)$config['writeTo'] : null);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $drivers
     * @param list<string> $stack
     */
    private static function buildDatabase(array $config, array $drivers, array $stack): DatabaseSecretStore
    {
        $masterDriver = (string)($config['masterDriver'] ?? '');
        if ($masterDriver === '' || $masterDriver === 'database') {
            throw new InvalidArgumentException('DatabaseSecretStore requires a non-database masterDriver.');
        }

        return new DatabaseSecretStore(
            self::buildStore($masterDriver, $drivers, $stack),
            (string)($config['masterKeyName'] ?? 'platform.master_kek'),
            (string)($config['connection'] ?? 'platform'),
            (string)($config['namespace'] ?? 'platform'),
            isset($config['tenantId']) ? (string)$config['tenantId'] : null,
            (string)($config['keyName'] ?? 'platform-secrets'),
            (string)($config['keyVersion'] ?? 'v1'),
        );
    }
}
