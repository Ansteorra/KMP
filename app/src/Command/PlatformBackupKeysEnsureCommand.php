<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\SecretStoreInterface;
use App\Services\Secrets\SensitiveString;
use App\Services\Secrets\WritableSecretStoreInterface;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use RuntimeException;
use Throwable;

/**
 * Idempotently provisions backup KEKs without printing secret values.
 */
final class PlatformBackupKeysEnsureCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform backup-keys ensure';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Ensure platform and tenant backup encryption keys exist.')
            ->addOption('platform-only', [
                'help' => 'Ensure only the platform database backup key.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('allow-read-only', [
                'help' => 'Allow missing keys only when the configured store is read-only during a legacy transition.',
                'boolean' => true,
                'default' => false,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $store = SecretStoreFactory::fromConfig();
            $created = 0;
            $existing = 0;
            $unavailable = 0;
            $this->countResult($this->ensure($store, 'platform.backup.kek'), $created, $existing, $unavailable);

            if (!$args->getOption('platform-only')) {
                $platform = ConnectionManager::get('platform');
                if (!$platform instanceof Connection) {
                    throw new RuntimeException('Platform database connection is unavailable.');
                }
                $rows = $platform->execute(
                    'SELECT slug FROM tenants WHERE status != :archived ORDER BY slug',
                    ['archived' => 'archived'],
                )->fetchAll('assoc');
                foreach ($rows as $row) {
                    $slug = strtolower((string)$row['slug']);
                    if (!preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?\z/', $slug)) {
                        throw new RuntimeException('A tenant slug is invalid; backup keys were not fully reconciled.');
                    }
                    $this->countResult(
                        $this->ensure($store, sprintf('tenant.%s.kek', $slug)),
                        $created,
                        $existing,
                        $unavailable,
                    );
                }
            }
            if ($unavailable > 0 && !$args->getOption('allow-read-only')) {
                throw new RuntimeException(sprintf(
                    'Configured secret store is read-only and %d backup encryption key(s) are missing.',
                    $unavailable,
                ));
            }
        } catch (Throwable $exception) {
            $io->err($exception->getMessage());

            return self::CODE_ERROR;
        }

        if ($unavailable > 0) {
            $io->warning(sprintf(
                '%d backup encryption key(s) could not be created in the legacy read-only store. ' .
                'Tenant migration backups still fail closed if a required tenant key is absent.',
                $unavailable,
            ));
        }
        $io->out(sprintf(
            'Backup encryption keys ready: %d created, %d already present, %d unavailable.',
            $created,
            $existing,
            $unavailable,
        ));

        return self::CODE_SUCCESS;
    }

    /**
     * Create a missing key without exposing its value.
     */
    private function ensure(SecretStoreInterface $store, string $name): ?bool
    {
        $existing = $store->get($name);
        if ($existing !== null && !$existing->isEmpty()) {
            return false;
        }
        if (!$store instanceof WritableSecretStoreInterface) {
            return null;
        }
        $value = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $store->put($name, new SensitiveString($value));

        return true;
    }

    /**
     * Add one ensure result to the aggregate counters.
     */
    private function countResult(?bool $result, int &$created, int &$existing, int &$unavailable): void
    {
        if ($result === true) {
            $created++;
        } elseif ($result === false) {
            $existing++;
        } else {
            $unavailable++;
        }
    }
}
