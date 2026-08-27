<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\SecretStoreInterface;
use App\Services\Secrets\WritableSecretStoreInterface;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use RuntimeException;
use Throwable;

/**
 * Copy legacy KMP_SECRET_* values into the database store without overwrites.
 */
final class PlatformSecretsImportEnvCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform secrets import-env';
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $config = (array)Configure::read('Secrets');
            $activeDriver = (string)($config['driver'] ?? 'file');
            $databaseConfig = (array)($config['drivers']['database'] ?? []);
            $masterDriver = (string)($databaseConfig['masterDriver'] ?? '');
            $masterKeyName = (string)($databaseConfig['masterKeyName'] ?? 'platform.master_kek');
            if ($masterDriver === '' || $masterDriver === 'database') {
                throw new RuntimeException('The database secret store master driver is not configured safely.');
            }

            $masterStore = SecretStoreFactory::fromDriver($masterDriver, $config);
            $masterKey = $masterStore->get($masterKeyName);
            if ($masterKey === null || $masterKey->isEmpty()) {
                if ($activeDriver === 'env') {
                    $io->warning(
                        'Legacy environment secret import deferred: the database master key is not available. ' .
                        'The active environment store remains unchanged.',
                    );

                    return self::CODE_SUCCESS;
                }

                throw new RuntimeException('The database secret store master key is unavailable.');
            }

            $source = SecretStoreFactory::fromDriver('env', $config);
            $target = SecretStoreFactory::fromDriver('database', $config);
            if (!$target instanceof WritableSecretStoreInterface) {
                throw new RuntimeException('The database secret store is not writable.');
            }
            $platform = ConnectionManager::get('platform');
            if (!$platform instanceof Connection) {
                throw new RuntimeException('Platform database connection is unavailable.');
            }

            [$imported, $existing, $missing] = $this->import(
                $source,
                $target,
                $this->candidateNames($platform, $masterKeyName),
            );
        } catch (Throwable $exception) {
            $io->err($exception->getMessage());

            return self::CODE_ERROR;
        }

        $io->out(sprintf(
            'Legacy environment secret import complete: %d imported, %d already present, %d not set.',
            $imported,
            $existing,
            $missing,
        ));

        return self::CODE_SUCCESS;
    }

    /**
     * @param list<string> $names
     * @return array{int, int, int}
     */
    private function import(
        SecretStoreInterface $source,
        WritableSecretStoreInterface $target,
        array $names,
    ): array {
        $imported = 0;
        $existing = 0;
        $missing = 0;
        foreach ($names as $name) {
            if ($target->exists($name)) {
                $existing++;
                continue;
            }
            $value = $source->get($name);
            if ($value === null) {
                $missing++;
                continue;
            }
            $target->put($name, $value);
            $imported++;
        }

        return [$imported, $existing, $missing];
    }

    /**
     * Build exact names from platform metadata. This avoids guessing the
     * punctuation that EnvVarSecretStore normalizes in KMP_SECRET_* names.
     *
     * @return list<string>
     */
    private function candidateNames(
        Connection $platform,
        string $masterKeyName,
    ): array {
        $exactNames = ['platform.backup.kek'];
        $tenants = $platform->execute('SELECT slug, tenant_config FROM tenants ORDER BY slug')->fetchAll('assoc');
        foreach ($tenants as $tenant) {
            $slug = strtolower((string)$tenant['slug']);
            if (!preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?\z/', $slug)) {
                throw new RuntimeException('A tenant slug is invalid; legacy secrets were not imported.');
            }
            $exactNames[] = sprintf('tenant.%s.db.password', $slug);
            $exactNames[] = sprintf('tenant.%s.kek', $slug);
            $tenantConfig = json_decode((string)($tenant['tenant_config'] ?? ''), true);
            if (is_array($tenantConfig)) {
                $this->collectSecretReferences($tenantConfig, $exactNames);
            }
        }
        $references = $platform->execute(
            'SELECT totp_secret_ref AS name FROM platform_users WHERE totp_secret_ref IS NOT NULL ' .
            'UNION SELECT name FROM tenant_secrets_index ORDER BY name',
        )->fetchAll('assoc');
        foreach ($references as $reference) {
            $name = trim((string)$reference['name']);
            if ($name !== '') {
                $exactNames[] = $name;
            }
        }

        $masterStorageKey = $this->storageKey($masterKeyName);
        $namesByStorageKey = [];
        foreach ($exactNames as $name) {
            $storageKey = $this->storageKey($name);
            if ($storageKey !== $masterStorageKey) {
                $namesByStorageKey[$storageKey] = $name;
            }
        }
        $names = array_values($namesByStorageKey);
        sort($names);

        return $names;
    }

    /**
     * Collect portable secret references from the validated tenant config.
     *
     * @param array<string, mixed> $config
     * @param list<string> $names
     */
    private function collectSecretReferences(array $config, array &$names): void
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $this->collectSecretReferences($value, $names);
                continue;
            }
            if (!is_string($key) || !str_ends_with($key, '_secret_ref') || !is_string($value)) {
                continue;
            }
            $name = trim($value);
            if ($name !== '' && !str_contains($name, '://')) {
                $names[] = $name;
            }
        }
    }

    /**
     * Match EnvVarSecretStore's logical-name normalization without exposing a
     * secret value or relying on its lossy list() inverse mapping.
     */
    private function storageKey(string $name): string
    {
        return strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '_', $name));
    }
}
